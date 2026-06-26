@extends('emails.layouts.base')

@section('body')

<h1>Welcome to {{ $appName }}! 🎉</h1>

<p>Hi {{ $user->first_name ?? $user->name }},</p>

<p>
  We're thrilled to have you on board. Your account has been created and is just one step away
  from being active — we've sent you a separate email to verify your email address.
</p>

<div class="hb">
  <p style="margin:0;color:rgba(255,255,255,0.85);">
    <strong style="color:#fff;">What happens next?</strong><br><br>
    1. Check your inbox for the verification email.<br>
    2. Click the verification link to activate your account.<br>
    3. Sign in and start learning!
  </p>
</div>

<p>
  Once verified, you'll have full access to all courses, live sessions with expert tutors,
  progress tracking, and more.
</p>

<div class="dv"></div>

<p class="sm">
  If you did not create this account, no action is required — just ignore this email.
  The account will be automatically deleted if not verified within 72 hours.
</p>

<p class="sm">
  Need help? Contact us at
  <a href="mailto:{{ config('mail.from.address') }}" style="color:rgba(99,102,241,0.8);">{{ config('mail.from.address') }}</a>
</p>

@endsection
