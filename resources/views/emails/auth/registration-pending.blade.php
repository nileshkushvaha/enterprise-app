@extends('emails.layouts.base')

@section('body')

<h1>Account Under Review ⏳</h1>

<p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>

<p>
  Thank you for registering on <strong>{{ $appName }}</strong>!
  Your account has been created and is currently awaiting administrator approval.
</p>

<div class="hb" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#ffffff;">📋 What happens next?</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.60);line-height:1.8;">
    <span style="color:#a78bfa;font-weight:600;">①</span> &nbsp;An administrator will review your registration<br>
    <span style="color:#a78bfa;font-weight:600;">②</span> &nbsp;You will receive an email once your account is approved<br>
    <span style="color:#a78bfa;font-weight:600;">③</span> &nbsp;Sign in and start your journey
  </p>
</div>

<p>
  We aim to review all registrations within one business day.
  If you haven't heard from us after 48 hours, please contact support.
</p>

<div class="dv" style="height:1px;background:rgba(255,255,255,0.07);margin:28px 0;"></div>

<p style="font-size:13px;color:rgba(255,255,255,0.30);margin:0;">
  Didn't create this account? <a href="mailto:{{ config('mail.from.address') }}" style="color:rgba(99,102,241,0.80);">Contact support</a>.
</p>

@endsection
