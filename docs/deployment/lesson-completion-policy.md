# Lesson Timing & Completion Policy — Rollout Runbook

Procedure for the agreed timeline (architecture in `docs/lessons.md`,
"Completion policy"; join window in `docs/meetings.md` §5b).

For a **7:00–8:00 PM** lesson after this rollout:

| When | What |
|---|---|
| 6:50 PM | "Join the lesson" appears (10 min before start) |
| 8:00 PM | Scheduled end. Page says "Scheduled time ended. Joining closes at 8:05 PM." — a participant who dropped can still rejoin |
| 8:05 PM | Joining closes. Badge reads **Lesson ended · Completion pending**; the page polls every minute |
| 8:05 PM+ | Attendance pull may run (only when an attendance-capable provider and `meeting.attendance_sync_enabled` exist) |
| 8:15–8:20 PM | Lesson completed by the 5-minute sweep. Booking → Completed, earnings/dispute windows start as before |
| afterwards | If recorded, the recording appears under the booking once ingestion finishes — never before completion, never on a fixed promise |

## Root causes this rollout addresses

1. **Completion lagged the lesson end by 30–45 min** — grace 30 min,
   sweep every 15 min. Now 15 min, every 5 min.
2. **Joining stayed open 15 min after the end**, and the page kept
   saying "in progress". Now 5 min, with an explicit closing time.
3. **An ended, unfinalized lesson read "Confirmed"**, indistinguishable
   from an upcoming one. Now "Lesson ended · Completion pending".
4. **Evidence-based finalization could not be switched on safely**: the
   finalizer treated silence as both-absent. `LessonEvidenceCoverage`
   now holds evidence-less lessons for a human, and activation is gated
   by a read-only preflight. **Blocker still present in production:**
   no production meeting provider implements attendance reporting
   (Zoom's adapter has no attendance surface; Google Meet/manual have
   none), and nothing writes the participant identity map
   (`booking_meetings.metadata.attendance_participants`). Until a Zoom
   attendance adapter exists, completion stays **time-based** — the
   policy below is safe today, and evidence-based completion is a
   later, separately audited step.

## What changes in production

Settings migration `2026_09_11_100300_align_lesson_timing_and_completion_policy`
(forward values; `down()` restores the previous ones):

| Setting | Before | After | Effect |
|---|---|---|---|
| `meeting.meeting_link_visible_before_minutes` | 15 | **10** | join opens 10 min before start; Zoom host reservations for NEW bookings shrink by 5 min on the front |
| `meeting.meeting_link_visible_after_minutes` | 15 | **5** | join closes 5 min after end; `meetings:close-expired` (Google Meet only) ends meetings 5–10 min after the scheduled end; reservations shrink by 10 min at the back |
| `lessons.auto_complete_grace_minutes` | 30 | **15** | completion due 15 min after end (both policies) |
| `lessons.attendance_finalize_delay_minutes` | 30 | **15** | attendance sealed at the same mark |
| `meeting.attendance_sync_delay_minutes` | 15 | **5** | first attendance pull can precede the seal |

Not changed: `lessons.automated_finalization_enabled` (stays **false**),
`auto_complete_enabled`, `require_*`, `min_attendance_seconds`,
dispute/refund/earnings-hold/payout settings.

Scheduler: `lessons:auto-complete` and `meetings:sync-attendance` move
from every 15 to every 5 minutes (`lessons:finalize-due` already was).

Trade-offs to be aware of: a lesson that runs over its scheduled end
loses the join link at +5 (participants already inside a Zoom meeting
are not affected; Zoom meetings are not force-ended). The window for a
participant to report a no-show/technical issue before time-based
completion is now 15 minutes after the end instead of 30; reports
after completion remain possible through the review desk.

## Deploy (mutating steps marked ✎)

```bash
cd /var/www/sirieducation
git pull --ff-only
composer install --no-dev --optimize-autoloader        # ✎ code only
php artisan migrate --path=database/settings --force    # ✎ the one settings migration above; no schema migrations
php artisan optimize:clear && php artisan optimize      # ✎ caches
php artisan queue:restart                               # ✎ graceful; a running recording transfer may take up to 3600 s to drain
```

The scheduler picks up the new cadence at its next tick; no cron edit.

## Verify (read-only)

```bash
php artisan lessons:completion-preflight
```

Expect: "Active completion policy: time-based", join window 10/5,
completion delay 15 with cron `*/5 * * * *`, seal 15 / pull 5, and one
blocker: "No enabled meeting provider can report attendance". Exit
code 1 is expected and only means evidence-based activation is not
possible yet. The "Ended lessons still open" table lists lessons the
sweep will pick up in the next 5 minutes and any it will hold
(technical issue, late evidence, required attendance) — those need an
admin decision, never a bulk command.

Spot-check one lesson end to end: open a student booking during the
lesson, at +3 min ("Joining closes at"), at +6 min ("Joining closed at",
"Lesson ended · Completion pending"), and at +20 min (Completed).

## Activate evidence-based completion (later, separate step)

Preconditions: an attendance-capable provider enabled, ingestion
switches on, participant maps written for new meetings, and
`lessons:completion-preflight` exiting 0.

```bash
php artisan lessons:completion-preflight                    # read-only, must exit 0
php artisan lessons:evidence-finalization on                # dry run, writes nothing
php artisan lessons:evidence-finalization on --confirm      # ✎ audited flip
```

Rollback of that step alone:

```bash
php artisan lessons:evidence-finalization off --confirm     # ✎ audited; time-based sweep resumes
```

## Rollback of the timing values

```bash
php artisan migrate:rollback --path=database/settings --step=1 --force   # ✎ restores 15/15/30/30/15
php artisan optimize:clear && php artisan optimize
```

Or set the previous values in Admin → Settings → Meetings
(Joining) and Admin → Settings → Platform (Auto-completion
Delay). The scheduler cadence needs a code revert. Lessons completed
in the meantime stay completed — nothing is bulk-reopened.
