@extends('emails.layouts.base')

@section('body')

<h1>Your Account Has Been Approved! 🎉</h1>

<p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

<p>
  Great news — your <strong>{{ $appName }}</strong> account has been approved by our team.
  You can now access the platform.
</p>

<div class="hb" style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#34d399;">✅ Account Approved</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.60);line-height:1.8;">
    Your account is now active and ready to use.<br>
    Sign in to get started right away.
  </p>
</div>

<div class="btn-wrap" style="text-align:center;margin:32px 0;">
  <a href="{{ $appUrl }}/login" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Sign In Now →
  </a>
</div>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<p style="font-size:13px;color:rgba(255,255,255,0.30);margin:0;">
  Questions? <a href="mailto:{{ config('mail.from.address') }}" style="color:rgba(99,102,241,0.80);">Contact support</a>.
</p>

@endsection
