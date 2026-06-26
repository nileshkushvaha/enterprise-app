@extends('emails.layouts.base')

@section('body')

<h1>Reset Your Password 🔐</h1>

<p>
  We received a request to reset the password for your <strong>{{ $appName }}</strong> account.
  Click the button below to choose a new password.
</p>

<div class="btn-wrap" style="text-align:center;margin:36px 0;">
  <a href="{{ $url }}" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Reset My Password →
  </a>
</div>

<div class="hb" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:18px 22px;margin:24px 0;">
  <p style="margin:0;font-size:13px;color:rgba(255,255,255,0.55);line-height:1.65;">
    ⏱ &nbsp;This link expires in <strong style="color:#ffffff;">{{ $expireMinutes }} minutes</strong>
    and can only be used once.
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.45);margin-bottom:8px;">
  Button not working? Copy and paste this URL into your browser:
</p>
<div class="url-box" style="background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:14px 18px;word-break:break-all;font-size:12px;color:rgba(99,102,241,0.80);font-family:'Courier New',Courier,monospace;">
  {{ $url }}
</div>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<div class="alert-box" style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.18);border-radius:12px;padding:16px 20px;margin:0;">
  <p style="font-size:13px;color:rgba(255,255,255,0.50);margin:0;line-height:1.65;">
    ⚠️ &nbsp;If you did not request a password reset, please ignore this email —
    your password will remain unchanged and this link will expire automatically.
  </p>
</div>

@endsection
