@extends('emails.layouts.base')

@section('body')

<h1>Account Locked 🔒</h1>

<p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

<p>
  Your <strong>{{ $appName }}</strong> account has been temporarily locked after
  <strong>{{ $attempts }} consecutive failed login attempts</strong>.
</p>

<div class="hb" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#f87171;">🔒 Lock Details</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.8;">
    ⏱ &nbsp;Your account will automatically unlock in <strong style="color:#fff;">{{ $minutes }} minutes</strong>.<br>
    🌐 &nbsp;Or click the button below to unlock it immediately.
  </p>
</div>

<div class="btn-wrap" style="text-align:center;margin:32px 0;">
  <a href="{{ $unlockUrl }}" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Unlock My Account →
  </a>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.45);margin-bottom:8px;">
  Button not working? Paste this URL into your browser:
</p>
<div class="url-box" style="background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:14px 18px;word-break:break-all;font-size:12px;color:rgba(99,102,241,0.80);font-family:'Courier New',Courier,monospace;">
  {{ $unlockUrl }}
</div>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<div class="alert-box" style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.18);border-radius:12px;padding:16px 20px;margin:0;">
  <p style="font-size:13px;color:rgba(255,255,255,0.50);margin:0;line-height:1.65;">
    ⚠️ &nbsp;If this wasn't you, someone may be trying to access your account.
    We recommend <a href="{{ $appUrl }}/forgot-password" style="color:rgba(99,102,241,0.80);">resetting your password</a> immediately.
  </p>
</div>

@endsection
