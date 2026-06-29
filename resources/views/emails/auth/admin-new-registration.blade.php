@extends('emails.layouts.base')

@section('body')

<h1>New Registration Pending Approval 👤</h1>

<p>Hi <strong>{{ $admin->first_name ?? $admin->name }}</strong>,</p>

<p>
  A new user has registered on <strong>{{ $appName }}</strong> and requires administrator approval
  before they can access the platform.
</p>

<div class="hb" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.20);border-radius:14px;padding:20px 24px;margin:24px 0;">
  <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#ffffff;">👤 New Registration</p>
  <p style="margin:0;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.8;">
    📛 &nbsp;<strong style="color:rgba(255,255,255,0.80);">Name:</strong> &nbsp;{{ $registeredUser->name }}<br>
    ✉️ &nbsp;<strong style="color:rgba(255,255,255,0.80);">Email:</strong> &nbsp;{{ $registeredUser->email }}<br>
    🌐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">IP Address:</strong> &nbsp;{{ $ipAddress }}<br>
    🕐 &nbsp;<strong style="color:rgba(255,255,255,0.80);">Registered:</strong> &nbsp;{{ now()->format('d M Y, h:i A T') }}
  </p>
</div>

<p style="font-size:14px;color:rgba(255,255,255,0.65);">
  To approve or reject this registration, visit the Users section in the admin panel.
</p>

<div class="btn-wrap" style="text-align:center;margin:32px 0;">
  <a href="{{ $appUrl }}/admin/users" class="btn" style="display:inline-block;padding:16px 44px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;color:#ffffff;background:linear-gradient(135deg,#4F46E5,#7C3AED);box-shadow:0 4px 24px rgba(99,102,241,0.45);">
    Review User →
  </a>
</div>

@endsection
