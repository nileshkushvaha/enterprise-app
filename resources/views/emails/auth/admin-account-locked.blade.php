@extends('emails.layouts.base')

@section('body')

<h1>Account Locked — Admin Alert 🔒</h1>

<p>Hi <strong>{{ $admin->first_name ?? $admin->name }}</strong>,</p>

<p>
  A user account on <strong>{{ $appName }}</strong> has been automatically locked due to
  too many consecutive failed login attempts.
</p>

<div class="hb" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#f87171;">🔒 Locked Account</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.8;">
    👤 &nbsp;<strong style="color:rgba(255,255,255,0.80);">User:</strong> &nbsp;{{ $lockedUser->name }} ({{ $lockedUser->email }})<br>
    🌐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">IP Address:</strong> &nbsp;{{ $ipAddress }}<br>
    🕐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">Time:</strong> &nbsp;{{ now()->format('d M Y, h:i A T') }}
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.65);">
  The user has been sent an unlock email. You can also manually unlock their account
  from the admin panel.
</p>

<div class="btn-wrap" style="text-align:center;margin:32px 0;">
  <a href="{{ $appUrl }}/admin/users" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    View Users →
  </a>
</div>

@endsection
