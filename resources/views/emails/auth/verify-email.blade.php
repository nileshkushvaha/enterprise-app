@extends('emails.layouts.base')

@section('body')

<h1>Verify Your Email Address ✉️</h1>

<p>Hi {{ $notifiable->first_name ?? $notifiable->name }},</p>

<p>
  Thank you for signing up to <strong>{{ $appName }}</strong>. Please verify your email address
  to activate your account and start your learning journey.
</p>

<div style="text-align:center;margin:36px 0;">
  <a href="{{ $url }}" class="btn">Verify Email Address →</a>
</div>

<div class="hb">
  <p style="margin:0;font-size:13px;color:rgba(255,255,255,0.6);">
    ⏱ This verification link expires in <strong style="color:#fff;">{{ $expiry }} minutes</strong>.
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.5);">
  If the button above doesn't work, copy and paste the link below into your browser:
</p>

<p><a href="{{ $url }}" class="link">{{ $url }}</a></p>

<div class="dv"></div>

<p class="sm">
  If you did not create an account with {{ $appName }}, no action is required.
  This link will expire automatically.
</p>

@endsection
