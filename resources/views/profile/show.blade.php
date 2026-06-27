@extends('layouts.frontend')

@section('title', 'My Profile — ' . config('app.name'))

@section('breadcrumbs')
    <x-frontend.breadcrumb :crumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'My Profile'],
    ]" />
@endsection

@section('content')
<div class="min-h-screen bg-[#05080F]" x-data="{
    activeTab: '{{ session('active_tab', 'general') }}',
    avatarPreview: '{{ $user->avatar ? asset('storage/' . $user->avatar) : '' }}',
    uploading: false,
    uploadError: '',

    async uploadAvatar(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.uploading = true;
        this.uploadError = '';
        const form = new FormData();
        form.append('avatar', file);
        form.append('_token', document.querySelector('meta[name=csrf-token]').content);
        try {
            const res = await fetch('{{ route('profile.avatar.upload') }}', { method: 'POST', body: form });
            const json = await res.json();
            if (json.success) { this.avatarPreview = json.url; }
            else { this.uploadError = 'Upload failed. Please try again.'; }
        } catch (e) { this.uploadError = 'Upload error. Please try again.'; }
        finally { this.uploading = false; }
    },

    async deleteAvatar() {
        if (!confirm('Remove your profile photo?')) return;
        const res = await fetch('{{ route('profile.avatar.delete') }}', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.success) this.avatarPreview = '';
    }
}">

    {{-- Background orbs --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute top-[-12rem] left-[-12rem] w-[56rem] h-[56rem] rounded-full bg-indigo-600/6 blur-[160px]"></div>
        <div class="absolute bottom-[-12rem] right-[-12rem] w-[48rem] h-[48rem] rounded-full bg-violet-600/5 blur-[140px]"></div>
        <div class="absolute top-[40%] left-[30%] w-[32rem] h-[32rem] rounded-full bg-blue-600/4 blur-[120px]"></div>
    </div>

    {{-- ── PROFILE HERO BANNER ───────────────────────────────────────── --}}
    <div class="relative z-10 border-b border-white/[0.05]" style="background:linear-gradient(135deg,rgba(99,102,241,.08) 0%,rgba(139,92,246,.06) 50%,rgba(59,130,246,.04) 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">

                {{-- Avatar --}}
                <div class="relative group flex-shrink-0">
                    <div class="w-20 h-20 rounded-2xl overflow-hidden border-2 border-white/20 bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-xl shadow-indigo-500/20">
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" class="w-full h-full object-cover" alt="Avatar">
                        </template>
                        <template x-if="!avatarPreview">
                            <span class="text-3xl font-bold text-white">{{ strtoupper(substr($user->first_name ?? $user->name, 0, 1)) }}</span>
                        </template>
                    </div>
                    {{-- Camera overlay --}}
                    <label class="absolute inset-0 rounded-2xl flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity"
                           :class="{'cursor-not-allowed': uploading}">
                        <input type="file" class="sr-only" accept="image/*" @change="uploadAvatar($event)" :disabled="uploading">
                        <svg x-show="!uploading" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg x-show="uploading" class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24" style="display:none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </label>
                    {{-- Online badge --}}
                    <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-400 border-2 border-[#05080F]"></span>
                </div>

                {{-- User info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-3 mb-1">
                        <h1 class="text-2xl font-bold text-white">{{ $user->full_name ?? $user->name }}</h1>
                        @if($user->email_verified_at)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/25">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            Verified
                        </span>
                        @endif
                        @if($user->hasTwoFactorEnabled())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-500/15 text-indigo-300 border border-indigo-500/25">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            2FA Active
                        </span>
                        @endif
                    </div>
                    <p class="text-slate-400 text-sm mb-3">{{ $user->email }}@if($user->profile?->country) &nbsp;·&nbsp; {{ $user->profile->country->flag }} {{ $user->profile->country->name }}@endif</p>

                    {{-- Quick stats --}}
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-1.5 text-xs text-slate-500">
                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Member since {{ $user->created_at->format('M Y') }}
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-slate-500">
                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Last login {{ $user->last_login_at?->diffForHumans() ?? 'Recently' }}
                        </div>
                        @if($userAvatar ?? false)
                        <button @click="deleteAvatar()" type="button" class="flex items-center gap-1 text-xs text-slate-600 hover:text-red-400 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Remove photo
                        </button>
                        @endif
                    </div>
                    <p x-show="uploadError" x-text="uploadError" class="text-xs text-red-400 mt-2"></p>
                    <button x-show="avatarPreview" @click="deleteAvatar()" type="button"
                        class="mt-2 flex items-center gap-1 text-xs text-slate-600 hover:text-red-400 transition-colors"
                        style="display:none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Remove photo
                    </button>
                </div>

                {{-- Right: edit hint --}}
                <div class="hidden lg:flex flex-col items-end gap-2 flex-shrink-0">
                    <div class="text-xs text-slate-600 text-right">Hover avatar to change photo</div>
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/[0.03] border border-white/[0.06]">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs text-slate-400">Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── MAIN CONTENT ──────────────────────────────────────────────── --}}
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Global alerts --}}
        @if(session('success'))
        <div class="mb-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 p-4 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-sm text-emerald-300 font-medium">{{ session('success') }}</p>
        </div>
        @endif
        @if(session('error'))
        <div class="mb-6 rounded-2xl bg-red-500/10 border border-red-500/25 p-4 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-red-300">{{ session('error') }}</p>
        </div>
        @endif

        <div class="flex flex-col lg:flex-row gap-6">

            {{-- ── LEFT: Tab Navigation Sidebar ─────────────────────────── --}}
            <div class="lg:w-56 flex-shrink-0">
                <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-2 sticky top-20">
                    <p class="px-3 py-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">Account</p>
                    @foreach([
                        ['general',      'General',       'heroicon-user',    'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',             'indigo'],
                        ['security',     'Security',      'heroicon-shield',  'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'emerald'],
                        ['preferences',  'Preferences',   'heroicon-cog',     'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',   'violet'],
                        ['notifications','Notifications', 'heroicon-bell',    'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'sky'],
                    ] as [$tab, $label, $_, $icon, $color])
                    <button type="button" @click="activeTab = '{{ $tab }}'"
                        :class="activeTab === '{{ $tab }}'
                            ? 'bg-{{ $color }}-600/15 text-{{ $color }}-300 border-{{ $color }}-500/30'
                            : 'text-slate-400 hover:text-white hover:bg-white/[0.04] border-transparent'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all border text-left mb-0.5">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                        </svg>
                        {{ $label }}
                    </button>
                    @endforeach

                    {{-- Danger zone separator --}}
                    <div class="border-t border-white/[0.06] my-2 mx-1"></div>
                    <p class="px-3 py-1.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">Account</p>
                    <a href="{{ route('dashboard') }}"
                       class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-white/[0.04] transition-all">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                </div>
            </div>

            {{-- ── RIGHT: Tab Content ────────────────────────────────────── --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- TAB: GENERAL                                           --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div x-show="activeTab === 'general'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf

                        {{-- Personal Info --}}
                        <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7 mb-5">
                            <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/15 border border-indigo-500/25 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4.5 h-4.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Personal Information</h2>
                                    <p class="text-xs text-slate-500">Your name, contact, and identity details</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                {{-- First Name --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">First Name <span class="text-red-400">*</span></label>
                                    <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border @error('first_name') border-red-500/50 @else border-white/[0.05] @enderror text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all"
                                        placeholder="First name" required>
                                    @error('first_name')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                                </div>

                                {{-- Last Name --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Last Name</label>
                                    <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all"
                                        placeholder="Last name">
                                </div>

                                {{-- Phone --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Phone Number</label>
                                    <input type="text" name="phone" value="{{ old('phone', $user->profile?->phone) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all"
                                        placeholder="+91 98765 43210">
                                </div>

                                {{-- Email --}}
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Email Address <span class="text-red-400">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border @error('email') border-red-500/50 @else border-white/[0.05] @enderror text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all"
                                        placeholder="you@example.com" required>
                                    @error('email')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                                </div>

                                {{-- Gender --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Gender</label>
                                    <select name="gender"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all appearance-none">
                                        <option value="" class="bg-[#0d1117]">— Select —</option>
                                        @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $val => $label)
                                            <option value="{{ $val }}" class="bg-[#0d1117]" {{ old('gender', $user->profile?->gender) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Date of Birth --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Date of Birth</label>
                                    <input type="date" name="date_of_birth"
                                        value="{{ old('date_of_birth', $user->profile?->date_of_birth?->format('Y-m-d')) }}"
                                        max="{{ now()->subYears(5)->format('Y-m-d') }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all [color-scheme:dark]">
                                </div>
                            </div>
                        </div>

                        {{-- Address --}}
                        <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7 mb-5">
                            <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                                <div class="w-9 h-9 rounded-xl bg-violet-500/15 border border-violet-500/25 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4.5 h-4.5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Address</h2>
                                    <p class="text-xs text-slate-500">Your location and mailing address</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Street Address</label>
                                    <input type="text" name="address" value="{{ old('address', $user->profile?->address) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all"
                                        placeholder="123 Main Street, Apt 4B">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">City</label>
                                    <input type="text" name="city" value="{{ old('city', $user->profile?->city) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all" placeholder="Mumbai">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">State / Province</label>
                                    <input type="text" name="state" value="{{ old('state', $user->profile?->state) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all" placeholder="Maharashtra">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Postal Code</label>
                                    <input type="text" name="postal_code" value="{{ old('postal_code', $user->profile?->postal_code) }}"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all" placeholder="400001">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Country</label>
                                    <select name="country_id"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500/30 transition-all appearance-none">
                                        <option value="" class="bg-[#0d1117]">— Select Country —</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" class="bg-[#0d1117]"
                                                {{ old('country_id', $user->profile?->country_id) == $country->id ? 'selected' : '' }}>
                                                {{ $country->flag }} {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit"
                                class="px-7 py-3 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/25 transition-all active:scale-[.98]">
                                Save Changes
                            </button>
                            <span class="text-xs text-slate-600">Changes are saved immediately</span>
                        </div>
                    </form>
                </div>

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- TAB: SECURITY                                          --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div x-show="activeTab === 'security'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     style="display:none">

                    {{-- 2FA Status --}}
                    <div class="rounded-2xl border p-7 mb-5 {{ $user->hasTwoFactorEnabled() ? 'border-emerald-500/25 bg-emerald-500/[0.04]' : 'border-white/[0.04] bg-white/[0.025]' }} backdrop-blur-xl"
                         x-data="{ showDisable: false, showRegenerate: false }">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b {{ $user->hasTwoFactorEnabled() ? 'border-emerald-500/15' : 'border-white/[0.06]' }}">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 {{ $user->hasTwoFactorEnabled() ? 'bg-emerald-500/15 border border-emerald-500/25' : 'bg-slate-700/50 border border-white/10' }}">
                                <svg class="w-4.5 h-4.5 {{ $user->hasTwoFactorEnabled() ? 'text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-base font-semibold text-white">Two-Factor Authentication</h2>
                                <p class="text-xs text-slate-500">Add an extra layer of security to your account</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if($user->hasTwoFactorEnabled())
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/25">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                                        <span class="text-xs text-emerald-400 font-medium">Enabled</span>
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-xs text-amber-400">Not enabled</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-slate-400 max-w-lg">
                                @if($user->hasTwoFactorEnabled())
                                    Two-factor authentication is active. Your account requires a code from your authenticator app on each login.
                                @else
                                    Two-factor authentication is not enabled. We strongly recommend enabling 2FA to protect your account.
                                @endif
                            </p>
                            @if($user->hasTwoFactorEnabled())
                            <button type="button" @click="showDisable = !showDisable"
                                class="flex-shrink-0 px-4 py-2 rounded-xl text-xs font-semibold text-red-400 border border-red-500/30 hover:bg-red-500/10 transition">
                                Disable 2FA
                            </button>
                            @else
                            <a href="{{ route('auth.two-factor.setup') }}"
                               class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 transition shadow-lg shadow-indigo-500/25">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Enable 2FA
                            </a>
                            @endif
                        </div>

                        @if($user->hasTwoFactorEnabled())
                        <div class="mt-6 pt-5 border-t border-white/[0.06]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-300">Recovery Codes</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ count($user->twoFactorRecoveryCodes()) }} codes remaining — store them safely</p>
                                </div>
                                <button type="button" @click="showRegenerate = !showRegenerate"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold text-indigo-400 border border-indigo-500/30 hover:bg-indigo-500/10 transition">
                                    Regenerate Codes
                                </button>
                            </div>
                            @if(session('recovery_codes'))
                            <div class="mt-4 p-4 rounded-xl bg-[#080e1a] border border-indigo-500/20">
                                <p class="text-xs text-indigo-300 mb-3 font-medium">⚠️ Save these new codes — old ones are invalidated:</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 font-mono">
                                    @foreach(session('recovery_codes') as $code)
                                    <div class="px-2 py-1.5 rounded-lg bg-white/5 text-xs text-indigo-300 text-center tracking-widest select-all">{{ $code }}</div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            <div x-show="showRegenerate" x-transition class="mt-4 p-5 rounded-xl bg-amber-500/5 border border-amber-500/15">
                                <p class="text-sm text-amber-300 mb-3 font-medium">Confirm your password to regenerate recovery codes</p>
                                <form method="POST" action="{{ route('auth.two-factor.regenerate-codes') }}" class="flex gap-2">
                                    @csrf
                                    <input type="password" name="password" placeholder="Current password"
                                        class="flex-1 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-sm text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition">
                                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 transition">Regenerate</button>
                                </form>
                            </div>
                            <div x-show="showDisable" x-transition class="mt-4 p-5 rounded-xl bg-red-500/5 border border-red-500/15">
                                <p class="text-sm text-red-300 mb-3 font-medium">Confirm your password to disable two-factor authentication</p>
                                <form method="POST" action="{{ route('auth.two-factor.disable') }}" class="flex gap-2">
                                    @csrf @method('DELETE')
                                    <input type="password" name="password" placeholder="Current password"
                                        class="flex-1 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-sm text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-red-500/30 transition">
                                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-500 transition">Disable 2FA</button>
                                </form>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Security Alerts --}}
                    <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7 mb-5">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/15 border border-blue-500/25 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4.5 h-4.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-white">Login Alerts</h2>
                                <p class="text-xs text-slate-500">Get notified when your account is accessed</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('profile.security.alerts') }}" class="space-y-3">
                            @csrf
                            @foreach([
                                ['login_alerts_enabled', 'Login alert emails', 'Receive an email every time someone signs in to your account', $user->login_alerts_enabled],
                                ['new_device_alerts_enabled', 'New device alerts', 'Get notified when a new browser or device signs in', $user->new_device_alerts_enabled],
                            ] as [$field, $label, $desc, $enabled])
                            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-white/[0.04] hover:border-white/[0.08] hover:bg-white/[0.02] cursor-pointer transition-all">
                                <div>
                                    <p class="text-sm font-medium text-slate-200">{{ $label }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $desc }}</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="{{ $field }}" value="1" {{ $enabled ? 'checked' : '' }} onchange="this.form.submit()">
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </label>
                                </div>
                            </label>
                            @endforeach
                        </form>
                    </div>

                    {{-- Change Password --}}
                    <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7 mb-5"
                         x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4.5 h-4.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-base font-semibold text-white">Change Password</h2>
                                <p class="text-xs text-slate-500">
                                    Last changed: <span class="text-slate-400">{{ $user->password_changed_at?->diffForHumans() ?? 'Never' }}</span>
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('profile.password') }}">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
                                @foreach([
                                    ['current_password', 'showCurrent', 'Current Password', 'current-password', '••••••••'],
                                    ['password',         'showNew',     'New Password',      'new-password',     'Min. 8 characters'],
                                    ['password_confirmation', 'showConfirm', 'Confirm Password', 'new-password', 'Repeat new password'],
                                ] as [$name, $showVar, $label, $autocomplete, $placeholder])
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">{{ $label }}</label>
                                    <div class="relative">
                                        <input :type="{{ $showVar }} ? 'text' : 'password'" name="{{ $name }}"
                                            class="w-full pr-10 px-4 py-3 rounded-xl bg-white/[0.05] border @error($name) border-red-500/50 @else border-white/[0.05] @enderror text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500/30 transition-all"
                                            placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}">
                                        <button type="button" @click="{{ $showVar }} = !{{ $showVar }}"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path x-show="!{{ $showVar }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                <path x-show="{{ $showVar }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" style="display:none"/>
                                            </svg>
                                        </button>
                                    </div>
                                    @error($name)<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                                </div>
                                @endforeach
                            </div>

                            <div class="p-4 rounded-xl bg-white/[0.02] border border-white/[0.06] mb-5">
                                <p class="text-xs text-slate-500 font-semibold mb-2.5">Password requirements</p>
                                <ul class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1.5">
                                    @foreach(['At least 8 characters', 'Uppercase & lowercase', 'At least one number', 'At least one symbol'] as $rule)
                                    <li class="flex items-center gap-1.5 text-xs text-slate-500">
                                        <svg class="w-3 h-3 text-slate-700 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $rule }}
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <button type="submit"
                                class="px-7 py-3 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 shadow-lg shadow-amber-500/20 transition-all active:scale-[.98]">
                                Update Password
                            </button>
                        </form>
                    </div>

                    {{-- Login History --}}
                    <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7 mb-5">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                            <div class="w-9 h-9 rounded-xl bg-slate-700/50 border border-white/[0.05] flex items-center justify-center flex-shrink-0">
                                <svg class="w-4.5 h-4.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-white">Recent Login Activity</h2>
                                <p class="text-xs text-slate-500">Last 10 login attempts on your account</p>
                            </div>
                        </div>
                        @if($loginHistory->isEmpty())
                            <div class="py-10 text-center">
                                <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-sm text-slate-500">No login history yet.</p>
                            </div>
                        @else
                            <div class="space-y-1">
                                @foreach($loginHistory as $log)
                                <div class="flex items-center justify-between py-3 px-4 rounded-xl hover:bg-white/[0.02] transition-colors border border-transparent hover:border-white/[0.05]">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $log->isSuccessful() ? 'bg-emerald-400' : 'bg-red-400' }}"></div>
                                        <div>
                                            <p class="text-sm text-slate-300">{{ $log->browser }} on {{ $log->platform }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->ip_address }} · {{ $log->device_type }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-xs text-slate-500">{{ $log->logged_in_at?->diffForHumans() }}</p>
                                        <span class="text-xs px-2 py-0.5 rounded-full {{ $log->isSuccessful() ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400' }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Active Sessions --}}
                    <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7"
                         x-data="{
                            sessions: @js($activeSessions->map(fn($s) => [
                                'id'           => $s->session_id,
                                'browser'      => $s->browser ?? 'Unknown',
                                'platform'     => $s->platform ?? 'Unknown',
                                'device_type'  => $s->device_type ?? 'desktop',
                                'ip_address'   => $s->ip_address ?? '—',
                                'last_seen'    => $s->last_activity_at?->diffForHumans() ?? 'Unknown',
                                'created_at'   => $s->created_at?->format('d M Y, h:i A') ?? '—',
                                'is_current'   => $s->isCurrent('{{ $currentSessionId }}'),
                            ])->values()->toArray()),
                            revoking: null,
                            revokingAll: false,
                            flashMsg: '',
                            flashType: '',
                            deviceIcon(type) {
                                if (type === 'mobile')  return '📱';
                                if (type === 'tablet')  return '⬜';
                                if (type === 'desktop') return '🖥️';
                                return '🌐';
                            },
                            async revokeSession(sessionId) {
                                this.revoking = sessionId;
                                try {
                                    const res = await fetch('{{ route('profile.sessions.revoke', ':id') }}'.replace(':id', sessionId), {
                                        method: 'DELETE',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                    });
                                    const data = await res.json();
                                    if (data.success) { this.sessions = this.sessions.filter(s => s.id !== sessionId); this.flash('Session revoked.', 'success'); }
                                    else { this.flash(data.message || 'Failed.', 'error'); }
                                } catch (e) { this.flash('Network error.', 'error'); }
                                finally { this.revoking = null; }
                            },
                            async revokeAll() {
                                if (!confirm('Revoke all other sessions?')) return;
                                this.revokingAll = true;
                                try {
                                    const res = await fetch('{{ route('profile.sessions.revoke-all') }}', {
                                        method: 'DELETE',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                    });
                                    const data = await res.json();
                                    if (data.success) { this.sessions = this.sessions.filter(s => s.is_current); this.flash(data.message, 'success'); }
                                    else { this.flash('Failed.', 'error'); }
                                } catch (e) { this.flash('Network error.', 'error'); }
                                finally { this.revokingAll = false; }
                            },
                            flash(msg, type) { this.flashMsg = msg; this.flashType = type; setTimeout(() => { this.flashMsg = ''; }, 4000); }
                         }">
                        <div class="flex items-center justify-between gap-4 mb-6 pb-5 border-b border-white/[0.04]">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/15 border border-indigo-500/25 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4.5 h-4.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Active Sessions</h2>
                                    <p class="text-xs text-slate-500">Devices signed in to your account</p>
                                </div>
                            </div>
                            <button @click="revokeAll()"
                                :disabled="revokingAll || sessions.filter(s => !s.is_current).length === 0"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-red-400 border border-red-500/30 hover:bg-red-500/10 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                <svg class="w-3.5 h-3.5" :class="revokingAll ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                <span x-text="revokingAll ? 'Revoking...' : 'Revoke All Others'"></span>
                            </button>
                        </div>

                        <div x-show="flashMsg" x-transition
                             class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl text-sm"
                             :class="flashType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/25 text-emerald-300' : 'bg-red-500/10 border border-red-500/25 text-red-300'"
                             style="display:none">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="flashMsg"></span>
                        </div>

                        <div class="space-y-2.5">
                            <template x-for="session in sessions" :key="session.id">
                                <div class="group flex items-center justify-between gap-4 p-4 rounded-xl border transition-all"
                                     :class="session.is_current ? 'border-indigo-500/25 bg-indigo-500/[0.04]' : 'border-white/[0.04] bg-white/[0.02] hover:border-white/[0.08] hover:bg-white/[0.04]'">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-lg"
                                             :class="session.is_current ? 'bg-indigo-500/15 border border-indigo-500/25' : 'bg-white/[0.05] border border-white/[0.05]'">
                                            <span x-text="deviceIcon(session.device_type)"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="text-sm font-medium text-slate-200 truncate" x-text="session.browser + ' on ' + session.platform"></p>
                                                <span x-show="session.is_current"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 flex-shrink-0">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse inline-block"></span>
                                                    This device
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                <span x-text="session.ip_address"></span>
                                                &nbsp;·&nbsp;<span class="capitalize" x-text="session.device_type"></span>
                                                &nbsp;·&nbsp;Last seen <span x-text="session.last_seen"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <button x-show="!session.is_current" @click="revokeSession(session.id)" :disabled="revoking === session.id"
                                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-red-400 border border-red-500/20 hover:bg-red-500/10 hover:border-red-500/40 disabled:opacity-50 opacity-0 group-hover:opacity-100 transition-all">
                                        <svg class="w-3.5 h-3.5" :class="revoking === session.id ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="revoking !== session.id" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                                            <path x-show="revoking === session.id" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <span x-text="revoking === session.id ? 'Revoking…' : 'Revoke'"></span>
                                    </button>
                                    <div x-show="session.is_current" class="flex-shrink-0">
                                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </div>
                                </div>
                            </template>
                            <div x-show="sessions.length === 0" class="py-10 text-center">
                                <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
                                <p class="text-sm text-slate-500">No active sessions found.</p>
                            </div>
                        </div>

                        <div class="mt-5 flex items-start gap-3 p-4 rounded-xl bg-amber-500/5 border border-amber-500/15">
                            <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <p class="text-xs text-amber-400/80 leading-relaxed">
                                <strong class="text-amber-300">Security tip:</strong>
                                If you see an unfamiliar session, revoke it immediately and
                                <a href="{{ route('profile.show') }}#security" class="underline decoration-dotted hover:text-amber-300 transition">change your password</a>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- TAB: PREFERENCES                                       --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div x-show="activeTab === 'preferences'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     style="display:none">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        <input type="hidden" name="first_name" value="{{ $user->first_name ?? $user->name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7">
                            <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                                <div class="w-9 h-9 rounded-xl bg-violet-500/15 border border-violet-500/25 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4.5 h-4.5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Display Preferences</h2>
                                    <p class="text-xs text-slate-500">Theme, language, and regional settings</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                {{-- Theme --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Theme</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach(['dark' => ['🌙', 'Dark'], 'light' => ['☀️', 'Light'], 'system' => ['💻', 'System']] as $val => [$emoji, $label])
                                        <label class="cursor-pointer">
                                            <input type="radio" name="theme" value="{{ $val }}" class="sr-only peer"
                                                {{ old('theme', $user->profile?->theme ?? 'dark') === $val ? 'checked' : '' }}>
                                            <div class="px-2 py-2.5 rounded-xl border border-white/[0.06] text-center text-xs text-slate-400 peer-checked:border-indigo-500/60 peer-checked:bg-indigo-600/15 peer-checked:text-indigo-300 hover:border-white/20 transition-all">
                                                <div class="text-lg mb-0.5">{{ $emoji }}</div>
                                                {{ $label }}
                                            </div>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Language --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Language</label>
                                    <select name="language"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all appearance-none">
                                        @foreach(['en' => 'English', 'hi' => 'Hindi', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'ar' => 'Arabic', 'zh' => 'Chinese'] as $code => $name)
                                            <option value="{{ $code }}" class="bg-[#0d1117]" {{ old('language', $user->profile?->language ?? 'en') === $code ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Date Format --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Date Format</label>
                                    <select name="date_format"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all appearance-none">
                                        @foreach(['Y-m-d' => 'YYYY-MM-DD', 'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'd-m-Y' => 'DD-MM-YYYY'] as $val => $display)
                                            <option value="{{ $val }}" class="bg-[#0d1117]" {{ old('date_format', $user->profile?->date_format ?? 'Y-m-d') === $val ? 'selected' : '' }}>{{ $display }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Timezone --}}
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Timezone</label>
                                    <select name="timezone"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all appearance-none">
                                        @php $currentTz = old('timezone', $user->profile?->timezone ?? 'Asia/Kolkata'); @endphp
                                        @foreach($timezones as $tz)
                                            <option value="{{ $tz }}" class="bg-[#0d1117]" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Time Format --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2">Time Format</label>
                                    <select name="time_format"
                                        class="w-full px-4 py-3 rounded-xl bg-white/[0.05] border border-white/[0.05] text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all appearance-none">
                                        <option value="H:i" class="bg-[#0d1117]" {{ old('time_format', $user->profile?->time_format ?? 'H:i') === 'H:i' ? 'selected' : '' }}>24-hour (14:30)</option>
                                        <option value="h:i A" class="bg-[#0d1117]" {{ old('time_format', $user->profile?->time_format) === 'h:i A' ? 'selected' : '' }}>12-hour (2:30 PM)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-6">
                                <button type="submit"
                                    class="px-7 py-3 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 shadow-lg shadow-violet-500/20 transition-all active:scale-[.98]">
                                    Save Preferences
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- TAB: NOTIFICATIONS                                     --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div x-show="activeTab === 'notifications'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     style="display:none">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        <input type="hidden" name="first_name" value="{{ $user->first_name ?? $user->name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <div class="rounded-2xl border border-white/[0.04] bg-white/[0.025] backdrop-blur-xl p-7">
                            <div class="flex items-center gap-3 mb-6 pb-5 border-b border-white/[0.04]">
                                <div class="w-9 h-9 rounded-xl bg-sky-500/15 border border-sky-500/25 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4.5 h-4.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Notification Preferences</h2>
                                    <p class="text-xs text-slate-500">Control which notifications you receive</p>
                                </div>
                            </div>

                            @php $notifPrefs = $user->profile?->notification_preferences ?? []; @endphp

                            <div class="space-y-3">
                                @foreach([
                                    ['email_notifications',  'Email Notifications', 'Account alerts, course updates, and important messages', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'indigo'],
                                    ['system_notifications', 'System Notifications', 'In-app notifications for activity and updates', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'violet'],
                                    ['marketing_emails',     'Marketing Emails', 'Promotions, new courses, and special offers', 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', 'amber'],
                                ] as [$key, $label, $description, $icon, $color])
                                <div class="flex items-center justify-between p-5 rounded-xl border border-white/[0.04] hover:border-white/[0.08] hover:bg-white/[0.02] transition-all"
                                     x-data="{ checked: {{ ($notifPrefs[$key] ?? true) ? 'true' : 'false' }} }">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-{{ $color }}-500/15 border border-{{ $color }}-500/25 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <svg class="w-5 h-5 text-{{ $color }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-200">{{ $label }}</p>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $description }}</p>
                                        </div>
                                    </div>
                                    <label class="relative flex items-center cursor-pointer flex-shrink-0 ml-4">
                                        <input type="hidden" name="{{ $key }}" :value="checked ? '1' : '0'">
                                        <input type="checkbox" class="sr-only peer" x-model="checked">
                                        <div class="w-11 h-6 bg-white/10 border border-white/20 rounded-full peer-checked:bg-indigo-600 peer-checked:border-indigo-500 transition-all"></div>
                                        <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all peer-checked:translate-x-5"></div>
                                    </label>
                                </div>
                                @endforeach
                            </div>

                            <div class="mt-6">
                                <button type="submit"
                                    class="px-7 py-3 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 shadow-lg shadow-sky-500/20 transition-all active:scale-[.98]">
                                    Save Preferences
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>{{-- /right content --}}
        </div>{{-- /flex layout --}}

        {{-- Bottom padding --}}
        <div class="h-8"></div>
    </div>{{-- /container --}}
</div>
@endsection
