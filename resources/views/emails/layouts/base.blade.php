<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
<meta charset="utf-8">
<meta name="x-apple-disable-message-reformatting">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<title>{{ $subject ?? config('app.name') }}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;background:#0C0F2E;color:#ffffff;}
  .ew{background:#0C0F2E;padding:40px 16px;}
  .ec{max-width:600px;margin:0 auto;}
  .eh{text-align:center;padding:40px 0 30px;}
  .li{width:44px;height:44px;background:linear-gradient(135deg,#6366F1,#8B5CF6);border-radius:12px;display:inline-block;}
  .lt{font-size:22px;font-weight:700;color:#ffffff;vertical-align:middle;margin-left:8px;}
  .eb{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:40px;}
  .ef{text-align:center;padding:30px 20px;color:rgba(255,255,255,0.3);font-size:12px;line-height:1.8;}
  .btn{display:inline-block;padding:16px 36px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;text-align:center;background:linear-gradient(135deg,#6366F1,#8B5CF6);color:#ffffff;}
  .dv{height:1px;background:rgba(255,255,255,0.08);margin:28px 0;}
  h1{font-size:28px;font-weight:700;color:#ffffff;line-height:1.3;margin-bottom:16px;}
  p{font-size:15px;line-height:1.7;color:rgba(255,255,255,0.7);margin-bottom:16px;}
  .hb{background:rgba(99,102,241,0.10);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:20px;margin:24px 0;}
  .sm{font-size:13px;color:rgba(255,255,255,0.35);}
  .link{color:rgba(99,102,241,0.9);word-break:break-all;font-size:13px;}
</style>
</head>
<body>
<div class="ew">
  <div class="ec">
    <div class="eh">
      <a href="{{ $appUrl }}" style="text-decoration:none;">
        <span class="li"></span>
        <span class="lt">{{ $appName }}</span>
      </a>
    </div>
    <div class="eb">
      @yield('body')
    </div>
    <div class="ef">
      <p class="sm">© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
    </div>
  </div>
</div>
</body>
</html>
