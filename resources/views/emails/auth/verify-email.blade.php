@extends('emails.layouts.base')

@section('body')

<h1>Verify Your Email Address ✉️</h1>

<p>Hi <strong>{{ $notifiable->first_name ?? $notifiable->name }}</strong>,</p>

<p>
  Thank you for joining <strong>{{ $appName }}</strong>. To activate your account and start
  your learning journey, please confirm your email address by clicking the button below.
</p>

<div class="btn-wrap" style="text-align:center;margin:36px 0;">
  <a href="{{ $url }}" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Verify Email Address →
  </a>
</div>

<div class="hb" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:18px 22px;margin:24px 0;">
  <p style="margin:0;font-size:13px;color:rgba(255,255,255,0.55);line-height:1.6;">
    ⏱ &nbsp;This link expires in <strong style="color:#ffffff;">{{ $expiry }} minutes</strong>.
    After that, you can request a new one from the login page.
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.45);margin-bottom:8px;">
  If the button doesn't work, copy and paste this URL into your browser:
</p>
<div class="url-box" style="background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:14px 18px;word-break:break-all;font-size:12px;color:rgba(99,102,241,0.80);font-family:'Courier New',Courier,monospace;">
  {{ $url }}
</div>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<p style="font-size:13px;color:rgba(255,255,255,0.30);margin:0;">
  If you didn't create an account with {{ $appName }}, you can safely ignore this email —
  no account will be created without verification.
</p>

@endsection
