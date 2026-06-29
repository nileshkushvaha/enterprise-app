@extends('emails.layouts.base')

@section('body')

<h1>Failed Login Attempt ⚠️</h1>

<p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

<p>
  We detected a failed login attempt on your <strong>{{ $appName }}</strong> account.
  If this was you, please try again. If it wasn't, your account is still safe — no one
  got in.
</p>

<div class="hb" style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#fbbf24;">⚠️ Attempt Details</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.8;">
    🌐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">IP Address:</strong> &nbsp;{{ $ipAddress }}<br>
    🔢 &nbsp;<strong style="color:rgba(255,255,255,0.80);">Remaining Attempts:</strong>
    &nbsp;<strong style="color:#fbbf24;">{{ $remainingAttempts }}</strong>
    before your account is temporarily locked
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.65);">
  If you were not trying to log in, we recommend changing your password immediately.
</p>

<div class="btn-wrap" style="text-align:center;margin:32px 0;">
  <a href="{{ $appUrl }}/forgot-password" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Change Password →
  </a>
</div>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<p style="font-size:13px;color:rgba(255,255,255,0.40);text-align:center;">
  If you recognise this attempt as your own, simply ignore this email.
</p>

@endsection
