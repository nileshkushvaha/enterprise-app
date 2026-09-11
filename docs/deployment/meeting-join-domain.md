# Participant join domain — `meet.sirieducation.com`

The address participants are handed to join a lesson. It is the SIRI
join gateway (`/join/{booking}`) served by the **same application** on a
second hostname — an authenticated entry point that redirects to the
provider's participant URL. It is not a Zoom domain, not a proxy of the
meeting, and Zoom's own domain is still where the meeting runs.

## What the application needs

| Item | Value |
|---|---|
| Setting | Admin → Settings → Meetings → **Join Link Domain** = `https://meet.sirieducation.com` (bare HTTPS origin; a path, query, port other than the real one, or credentials is rejected) |
| Settings migration | `2026_09_11_100200_add_participant_join_base_url_setting` (`php artisan migrate --path=database/settings --force`) |
| `APP_URL` | unchanged (`https://sirieducation.com`) — the main host issues the sign-in handoff |
| `SESSION_DOMAIN` | unchanged (unset, host-only cookie). Do **not** widen it to `.sirieducation.com`; the handoff exists so that is unnecessary |
| `SESSION_SECURE_COOKIE` | `true` in production (both hosts are HTTPS) |
| Cache | the handoff token and its single-use marker live in the default cache store; the marker is written with an atomic `add`, which is only exactly-once when every web node shares the store (database or Redis — never `file` or `array` on a multi-node deployment) |
| Route cache | rebuild after deploy (`route:cache`); `/join/{booking}` and `/dashboard/meetings/{booking}/handoff` are new |

Links already sent on the main host (`/dashboard/meetings/{booking}/join`)
keep working.

## Access-log redaction (required)

The first request to the meeting host carries `?handoff=<token>` in its
request line. The application answers `Cache-Control: no-store` and
`Referrer-Policy: no-referrer` and redirects to the clean URL, but the
original line is still written to the web server's access log unless it
is redacted. The token is single-use and expires in 60 s, so a logged
token is not reusable, but it must still not be retained. In nginx:

```nginx
map $request $request_redacted {
    ~^(?<pre>.*[?&]handoff=)[^&\s]+(?<post>.*)$  "${pre}REDACTED${post}";
    default                                        $request;
}

log_format redacted '$remote_addr - $remote_user [$time_local] "$request_redacted" '
                    '$status $body_bytes_sent "$http_referer" "$http_user_agent"';

server {
    server_name meet.sirieducation.com;
    access_log /var/log/nginx/meet.access.log redacted;
    ...
}
```

Apply the same `log_format` to any proxy in front of the meeting host,
and confirm no upstream (CDN, WAF, APM) records full request URLs for
this host. The application never logs the token.

## Sign-in across the two hosts

```text
browser → https://meet.sirieducation.com/join/{id}          (no cookie here)
       ← 302 https://sirieducation.com/dashboard/meetings/{id}/handoff
browser → main host (existing session; or login, then back here)
       ← 302 https://meet.sirieducation.com/join/{id}?handoff=<token>   token: random, 60 s, single use, bound to user+booking
browser → meeting host redeems it ONCE (atomic across nodes), only over HTTPS on this origin,
          stores a booking-scoped join grant in its own session (not a login), drops the token
       ← 302 https://meet.sirieducation.com/join/{id}        (no-store, no-referrer)
browser → gateway checks (participant, account, lifecycle, status, window)
       ← 302 provider participant join URL   (never the host URL)
```

The grant lets that user through `/join/{id}` for that booking only,
for 10 minutes. `/dashboard/...` and account routes on the meeting host
remain guest routes; nobody is signed in there.

## DNS

```text
meet.sirieducation.com.   A      <public IPv4 of the prepared server>
meet.sirieducation.com.   AAAA   <public IPv6, if the server has one>
```

No CNAME to any Zoom hostname. If the prepared server is the same
machine as the main site, point it at the same address.

## TLS

A certificate covering `meet.sirieducation.com` (its own, or a SAN on
the existing certificate). With certbot on the serving host:

```bash
sudo certbot --nginx -d meet.sirieducation.com
```

## Reverse proxy / web server

Two supported shapes. Either way the application must see the request
with `Host: meet.sirieducation.com` and know it arrived over HTTPS.

**A. Same server as the main site (preferred).** Add a server block
with the same document root and PHP-FPM upstream as the main site:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name meet.sirieducation.com;

    ssl_certificate     /etc/letsencrypt/live/meet.sirieducation.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/meet.sirieducation.com/privkey.pem;

    root /var/www/siri/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass  unix:/run/php/php8.3-fpm.sock;   # match the main site's upstream
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 80;
    listen [::]:80;
    server_name meet.sirieducation.com;
    return 301 https://$host$request_uri;
}
```

**B. Separate front server proxying to the main application server.**
The proxy must forward the original host and scheme, and the
application must trust that proxy:

```nginx
location / {
    proxy_pass         https://<main application server>;
    proxy_set_header   Host              $host;
    proxy_set_header   X-Forwarded-Host  $host;
    proxy_set_header   X-Forwarded-Proto $scheme;
    proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
}
```

and on the application, in `bootstrap/app.php`, trust the proxy's
address (`$middleware->trustProxies(at: ['<proxy ip>'])`). Without this
the application builds `http://` URLs and marks the session cookie
insecure. The repository currently trusts no proxies, so shape B needs
that code change before it can work.

## Verify before announcing the domain

```bash
curl -sI https://meet.sirieducation.com/join/00000000-0000-0000-0000-000000000000
# expect a 302 to https://sirieducation.com/dashboard/meetings/.../handoff (a guest is handed to the main host), or 404 for an unknown id after sign-in
```

Then, signed in as a test student on the main site, open a lesson's
join link inside its window and confirm the two redirects end at the
Zoom participant URL, with `meeting_join_handoff_issued` and
`meeting_join_redirected` in `activity_log`.
