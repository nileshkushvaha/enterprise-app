# Recurring bookings — production verification runbook

The gate for `recurring_future_generation_enabled` and
`recurring_wallet_auto_settle_enabled`.

Both settings ship `false`. They are **fail-closed release gates**, not
feature toggles: with them off the platform refuses to make a promise it
may not be able to keep, and says so to the student. They are turned on
only after the steps below have been performed **on the target
environment** and the evidence recorded.

**A passing test suite is not evidence for anything on this page.** The
suite proves the code is correct. It cannot prove that this server's
cron fires, that this supervisor restarts this worker, or that this
mail provider delivers. Every step below therefore observes the running
system; none of them may be substituted with `php artisan test`.

Run steps in order. Stop at the first failure — later steps assume the
earlier ones hold.

---

## 0. Before you start

```bash
cd /path/to/app

# Confirm both gates are still closed.
php artisan tinker --execute='$s=app(\App\Settings\BookingSettings::class);
 printf("future_generation=%s auto_settle=%s horizon_days=%d\n",
 var_export($s->recurring_future_generation_enabled,true),
 var_export($s->recurring_wallet_auto_settle_enabled,true),
 $s->recurring_confirmation_horizon_days);'
```

Expected: `future_generation=false auto_settle=false horizon_days=60`.

Record: environment name, hostname, app version/commit, operator, date.

---

## Part 1 — items 1–8: does generation actually run here?

Gates `recurring_future_generation_enabled`.

### Item 1 — the system cron invokes the Laravel scheduler

```bash
crontab -l | grep 'schedule:run'
```

Expected: a line like
`* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

Then prove it actually fires, **without running anything yourself**:

```bash
# Note the time, wait at least 2 minutes, then:
php artisan tinker --execute='echo \App\Models\SchedulerHistory::query()
 ->orderByDesc("ran_at")->limit(5)
 ->get(["command","status","ran_at"])->toJson(JSON_PRETTY_PRINT);'
```

**Evidence:** `ran_at` timestamps inside the last two minutes for
commands nobody triggered by hand.
**Fails if:** the newest row predates your wait — cron is not running.

### Item 2 — `booking:generate-series` runs unattended, hourly

```bash
php artisan schedule:list | grep booking:generate-series
```

Expected: `0 * * * *` with a "Next Due" in the future.

Wait for **two** scheduled hours to pass, then:

```bash
tail -n 40 storage/logs/booking-series-generation.log

php artisan tinker --execute='echo \App\Models\SchedulerHistory::query()
 ->where("command","like","%generate-series%")
 ->orderByDesc("ran_at")->limit(5)
 ->get(["status","duration_ms","ran_at"])->toJson(JSON_PRETTY_PRINT);'
```

**Evidence:** two entries roughly 60 minutes apart, `status` successful,
with no manual invocation in your shell history between them.
**Why two:** one entry only proves it ran once; two prove a *schedule*.

### Item 3 — queue workers are supervised and survive restarts

Generation dispatches `GenerateBookingSeriesOccurrences` onto the
**`notifications`** queue. Both series generation and every booking
notification depend on that worker.

```bash
sudo supervisorctl status                 # or: systemctl status <unit>
php artisan queue:monitor notifications
```

Process-death test:

```bash
pgrep -af 'queue:work.*notifications'
sudo kill -9 <pid>
sleep 15
pgrep -af 'queue:work.*notifications'     # must show a NEW pid
```

Server-restart test (schedule a maintenance window):

```bash
sudo reboot
# after it comes back:
pgrep -af 'queue:work.*notifications'
crontab -l | grep schedule:run
```

**Evidence:** a new pid after the kill, and a running worker plus intact
cron after a full reboot.
**Fails if:** the worker must be started by hand — generation would then
stop silently at the next deploy or reboot.

### Item 4 — a controlled series beyond the horizon receives new bookings

Create a **real** series in production owned by an internal test student,
with an instructor whose availability you control.

Temporarily enable the first gate for the duration of this test only:

```bash
php artisan tinker --execute='$s=app(\App\Settings\BookingSettings::class);
 $s->recurring_future_generation_enabled=true; $s->save();'
```

Book, through the UI, a **weekly** schedule of ~20 classes (well past the
60-day horizon). Record the series id, then:

```bash
SERIES=<series-id>

php artisan tinker --execute="\$s=\App\Models\BookingSeries::find('$SERIES');
 printf(\"bookings=%d generated_through=%s last_generated=%s failures=%d\n\",
 \$s->bookings()->count(), \$s->generated_through_date, \$s->last_generated_at, \$s->generation_failures);"
```

Note the count. **Wait for at least one scheduled hourly run** — do not
run the command yourself. Re-run the query.

**Evidence:** `bookings` has increased and `generated_through_date` has
advanced, with `last_generated_at` matching a scheduler run.
**Fails if:** nothing changed — generation is not reaching this series.

> Turn the gate back off after Part 1 unless you are proceeding straight
> to the release decision.

### Item 5 — conflict and recovery when an occurrence cannot be generated

Make a future, not-yet-generated occurrence impossible, then let the
sweep reach it. Using the instructor from item 4:

1. In the instructor's Leave/Time-off, block the whole day of an
   occurrence that is **beyond** `generated_through_date`.
2. Wait for the next hourly run.

```bash
php artisan tinker --execute="echo \App\Models\BookingSeriesException::query()
 ->where('booking_series_id','$SERIES')->get(['local_date','action','reason','notified_at'])
 ->toJson(JSON_PRETTY_PRINT);"
```

**Evidence:** a row with `action = conflict`, a human-readable `reason`,
and `notified_at` set.

Then recover: remove the leave, open the class in **My Bookings →
Repeating schedule**, and use **Try again**.

**Evidence:** a booking now exists for that date and the exception row is
gone.
**Why this matters:** the hourly sweep will never revisit that date on
its own — it sits behind the generation watermark — so recovery is
operator/student-driven by design.

### Item 6 — the lost-class notification reaches a real channel

The exception row above should have produced a message.

```bash
php artisan tinker --execute='echo \DB::table("notifications")
 ->orderByDesc("created_at")->limit(3)
 ->get(["type","notifiable_id","created_at"])->toJson(JSON_PRETTY_PRINT);'
```

That row is **not sufficient**. Confirm delivery on the configured
channel:

- open the test student's real mailbox and find the message;
- cross-check the provider dashboard (Resend) for the send;
- confirm the message names the affected date and time.

**Evidence:** a screenshot or provider log id for the delivered message.
**Fails if:** the database row exists but nothing was delivered — the
worker is running but mail transport is broken.

### Item 7 — failures are visible

Confirm each surface shows a real failure rather than silence:

```bash
# Queue failures
php artisan queue:failed

# Generation failures per series
php artisan tinker --execute='echo \App\Models\BookingSeries::query()
 ->where("status","active")
 ->orderByDesc("generation_failures")->limit(10)
 ->get(["id","generation_failures","last_generated_at"])->toJson(JSON_PRETTY_PRINT);'

# Scheduler failures
php artisan tinker --execute='echo \App\Models\SchedulerHistory::query()
 ->where("status","!=","success")->orderByDesc("ran_at")->limit(10)
 ->get(["command","status","ran_at"])->toJson(JSON_PRETTY_PRINT);'
```

Also confirm a human actually looks: **/admin → Scheduler Monitor** lists
`booking:generate-series` with a recent successful run, and **/admin →
Booking payment reconciliation issues** is reachable by the on-call role.

**Evidence:** each command returns (empty is fine), the admin pages load,
and someone owns the alert.
**Deliberately not claimed:** there is no automatic alert on a *stalled*
schedule. Add a monitor on "an active series whose `last_generated_at` is
older than 3 hours" if you want paging — see §Recovery.

### Item 8 — repeated execution creates no duplicates

```bash
php artisan tinker --execute="echo \App\Models\Booking::where('booking_series_id','$SERIES')->count();"

php artisan booking:generate-series --series=$SERIES --sync
php artisan booking:generate-series --series=$SERIES --sync
php artisan booking:generate-series --series=$SERIES --sync

php artisan tinker --execute="echo \App\Models\Booking::where('booking_series_id','$SERIES')->count();"
```

**Evidence:** the count is identical before and after.

Confirm the database-level guarantee is present, not just the behaviour:

```bash
php artisan tinker --execute='echo collect(\DB::select("SHOW INDEX FROM bookings"))
 ->pluck("Key_name")->unique()->filter(fn($k)=>str_contains($k,"series"))->values()->toJson();'
```

**Evidence:** `bookings_series_occurrence_unique` is present. This is
what makes generation idempotent under retries and concurrent workers —
the behaviour above follows from it rather than from convention.

---

## Part 2 — items 9–12: auto-settlement from wallet

Gates `recurring_wallet_auto_settle_enabled`. **Do not start Part 2
until Part 1 is fully green.**

Enable both gates for the duration of this test only, and opt the test
series in through **My Bookings → Repeating schedule → "Use my balance to
confirm future classes automatically"** (never by editing the column —
the point is to test the consent path a student uses).

```bash
php artisan tinker --execute='$s=app(\App\Settings\BookingSettings::class);
 $s->recurring_wallet_auto_settle_enabled=true; $s->save();'
```

### Item 9 — sufficient balance confirms exactly once, for the right amount

Fund the test student's wallet with **exactly one class price**, note the
balance, and wait for the next hourly run.

```bash
php artisan tinker --execute="\$u=<student-id>;
 \$w=\App\Models\Wallet::where('user_id',\$u)->first();
 printf(\"balance=%d\n\", \$w->available_balance_minor);
 echo \App\Models\Booking::where('booking_series_id','$SERIES')
   ->orderByDesc('created_at')->limit(3)
   ->get(['reference','payment_status','price','starts_at'])->toJson(JSON_PRETTY_PRINT);"
```

**Evidence, all three:**
- the newest generated class is `payment_status = paid`;
- the wallet fell by **exactly** that class's price, not more;
- exactly one captured `booking_payments` row and one
  `wallet_ledger_entries` debit exist for it:

```bash
php artisan tinker --execute="\$b='<booking-id>';
 echo \App\Models\BookingPayment::where('booking_id',\$b)->get(['provider','amount_minor','status'])->toJson();
 echo \App\Models\WalletLedgerEntry::where('source_id', \App\Models\BookingPayment::where('booking_id',\$b)->value('id'))
   ->get(['entry_type','direction','amount_minor'])->toJson();"
```

### Item 10 — insufficient balance debits nothing

Leave the wallet with **less** than one class price. Wait for the next
run.

**Evidence:**
- the newly generated class is `payment_status = pending` (payment due);
- the wallet balance is **unchanged** — no partial debit;
- no `booking_payments` row exists for that booking.

**Why this is a hard requirement:** a partial settlement would drain the
balance to zero *and* leave the class unpaid, which is worse than doing
nothing.

### Item 11 — auto-settlement is idempotent under retry and concurrency

```bash
# Fund for exactly one class, then force repeated passes.
php artisan booking:generate-series --series=$SERIES --sync
php artisan booking:generate-series --series=$SERIES --sync

# And concurrently:
php artisan booking:generate-series --series=$SERIES --sync &
php artisan booking:generate-series --series=$SERIES --sync &
wait
```

**Evidence:** the wallet fell by one class price in total; exactly one
captured payment and one ledger debit exist per booking; no booking is
`paid` twice and `queue:failed` is empty.

### Item 12 — recovery procedures are documented and rehearsed

Perform each one once, so it is known to work here:

| Situation | Action |
|---|---|
| Generation stalled for one series | `php artisan booking:generate-series --series=<id> --sync` |
| Generation stalled for all | `php artisan booking:generate-series` (queued) |
| Queue jobs failed | `php artisan queue:retry all`, then `php artisan queue:failed` |
| A class could not be booked | My Bookings → Repeating schedule → **Try again** on that date |
| Student wants auto-settle stopped | My Bookings → untick. Works even with the gate off |
| Stop new long/ongoing schedules platform-wide | set `recurring_future_generation_enabled=false` — existing series keep generating |
| Stop all unattended spending platform-wide | set `recurring_wallet_auto_settle_enabled=false` — takes effect on the next pass |
| Refund owed but stuck | /admin → Booking payment reconciliation issues → `Refund not completed` |

**Evidence:** each row exercised at least once, with the operator named.

---

## Rolling back

Both gates are safe to switch off at any time:

- `recurring_future_generation_enabled=false` — blocks **creation** of new
  schedules that need future generation. Existing schedules continue to
  generate; nothing already promised is withdrawn.
- `recurring_wallet_auto_settle_enabled=false` — stops all unattended
  settlement on the next pass. Per-student consent is retained, so
  re-enabling does not require students to opt in again.

Neither requires a deploy, a migration, or a queue drain.

---

## Recommended monitoring before go-live

Not built — these are the alerts worth adding, and their absence is a
known operational risk rather than an oversight:

1. **Stalled generation** — active series with
   `last_generated_at < now() - 3 hours`.
2. **Repeated generation failure** — `generation_failures > 3`.
3. **Refund owed** — any open `refund_not_completed` reconciliation issue.
4. **Queue depth / age** on the `notifications` queue.
