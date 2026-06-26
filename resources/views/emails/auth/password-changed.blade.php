@extends('emails.layouts.base')

@section('body')

<h1>Password Changed Successfully 🔒</h1>

<p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

<p>
  This is a confirmation that the password for your <strong>{{ $appName }}</strong> account
  was successfully changed.
</p>

<div class="hb" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#ffffff;">Change Details</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.8;">
    🕐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">Time:</strong> &nbsp;{{ $changedAt }}<br>
    🌐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">IP Address:</strong> &nbsp;{{ $ipAddress }}
  </p>
</div>

<p>If you made this change, no further action is needed. Your account is secure.</p>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<div class="alert-box" style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.18);border-radius:12px;padding:16px 20px;margin:0 0 24px;">
  <p style="font-size:14px;color:rgba(239,100,100,0.90);font-weight:600;margin:0 0 6px;">
    ⚠️ Didn't make this change?
  </p>
  <p style="font-size:13px;color:rgba(255,255,255,0.55);margin:0;line-height:1.65;">
    Your account may have been compromised. Secure it immediately by resetting your password
    and contacting our support team.
  </p>
</div>

<div class="btn-wrap" style="text-align:center;margin:28px 0;">
  <a href="{{ $appUrl }}/forgot-password" class="btn btn-danger" style="display:inline-block;padding:14px 36px;border-radius:14px;font-weight:700;font-size:14px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#DC2626,#B91C1C);box-shadow:0 4px 20px rgba(220,38,38,0.40);">
    Secure My Account →
  </a>
</div>

<p style="font-size:13px;color:rgba(255,255,255,0.30);text-align:center;margin:0;">
  For security, we notify you of all password changes.<br>
  Support: <a href="mailto:{{ config('mail.from.address') }}" style="color:rgba(99,102,241,0.75);">{{ config('mail.from.address') }}</a>
</p>

@endsection
