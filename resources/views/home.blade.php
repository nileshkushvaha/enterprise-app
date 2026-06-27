@extends('layouts.frontend')
@section('bare', true)

@section('title', config('app.name') . ' — Find Your Perfect 1-on-1 Tutor')

@section('content')

@php
$appName = config('app.name', 'EduVerse');

$countries = ['🇮🇳 India', '🇺🇸 United States', '🇬🇧 United Kingdom', '🇨🇦 Canada', '🇦🇺 Australia', '🌐 All Regions'];

$subjects = ['All', 'Computer Science', 'Mathematics', 'English', 'Science', 'Languages', 'Competitive Exams'];

$features = [
['icon'=>'🎯', 'title'=>'K-12 All Subjects', 'desc'=>'Math, Science, English online tutoring for every grade'],
['icon'=>'📚', 'title'=>'Personalised Plans', 'desc'=>'Custom learning paths built around your goals & pace'],
['icon'=>'✍️', 'title'=>'English Excellence', 'desc'=>'Reading, writing, grammar and vocabulary coaching'],
['icon'=>'🔬', 'title'=>'Science Mastery', 'desc'=>'Physics, Chemistry, Biology at all academic levels'],
['icon'=>'🏆', 'title'=>'Exam Preparation', 'desc'=>'JEE, NEET, SAT, GRE, UPSC — expert coaching'],
['icon'=>'💰', 'title'=>'Affordable Pricing', 'desc'=>'Transparent plans with a free 30-minute demo session'],
];

$stats = [
['value'=>10000, 'suffix'=>'+', 'label'=>'Active Students', 'icon'=>'👨‍🎓', 'color'=>'from-indigo-500 to-violet-500'],
['value'=>200, 'suffix'=>'+', 'label'=>'Expert Tutors', 'icon'=>'👩‍🏫', 'color'=>'from-blue-500 to-indigo-500'],
['value'=>500, 'suffix'=>'+', 'label'=>'Courses', 'icon'=>'📖', 'color'=>'from-violet-500 to-purple-500'],
['value'=>40000, 'suffix'=>'+', 'label'=>'Hours Taught', 'icon'=>'⏱️', 'color'=>'from-amber-500 to-orange-500'],
];

$whys = [
['icon'=>'🚀', 'title'=>'Creating Future Leaders', 'desc'=>'We believe every student can achieve greatness. Our world-class tutors equip learners with skills for the challenges of the 21st century.', 'color'=>'from-indigo-500 to-violet-600'],
['icon'=>'🎓', 'title'=>'Student-Centred Learning', 'desc'=>'Every session is tailored to the individual learner. We adapt to your style, speed, and goals — not the other way around.', 'color'=>'from-blue-500 to-indigo-600'],
['icon'=>'✨', 'title'=>'Personalised Experience', 'desc'=>'Our innovative tutoring enables creativity and sharpens skills with learning tailored to each unique learner.', 'color'=>'from-violet-500 to-pink-600'],
];

$steps = [
['num'=>'01', 'icon'=>'🌍', 'title'=>'Choose Your Curriculum', 'desc'=>'Select your country and curriculum to access courses designed specifically for your board, syllabus, or competitive exam entrance goals.', 'side'=>'right'],
['num'=>'02', 'icon'=>'🔍', 'title'=>'Explore Courses Made For You', 'desc'=>'Browse expert-built courses aligned with your curriculum, academic schedule, and personal requirements.', 'side'=>'left'],
['num'=>'03', 'icon'=>'📅', 'title'=>'Book a Free Demo Class', 'desc'=>'Experience a free 30-minute demo to connect with your tutor, understand the teaching style, and ensure it fits your learning needs.', 'side'=>'right'],
['num'=>'04', 'icon'=>'📈', 'title'=>'Enroll & Start Learning', 'desc'=>'Join your chosen course and start learning with engaging lessons, smart assessments, and ongoing academic support.', 'side'=>'left'],
];

$courses = [
['emoji'=>'💻','color'=>'from-blue-500 to-indigo-600','category'=>'Computer Science','title'=>'Data Structures & Algorithms','tutor'=>'Dr. Rahul Verma','students'=>'2.4K','rating'=>'4.9','reviews'=>'128','price'=>'₹1,499','duration'=>'40 hrs','level'=>'Intermediate','tag'=>'Bestseller'],
['emoji'=>'📐','color'=>'from-violet-500 to-purple-600','category'=>'Mathematics','title'=>'Calculus & Linear Algebra Mastery','tutor'=>'Prof. Priya Sharma','students'=>'1.8K','rating'=>'4.8','reviews'=>'96','price'=>'₹1,299','duration'=>'35 hrs','level'=>'Advanced','tag'=>'Top Rated'],
['emoji'=>'🧪','color'=>'from-emerald-500 to-teal-600','category'=>'Science','title'=>'Physics for JEE & NEET 2025','tutor'=>'Dr. Amit Patel','students'=>'3.1K','rating'=>'4.9','reviews'=>'205','price'=>'₹1,799','duration'=>'50 hrs','level'=>'Advanced','tag'=>'Hot 🔥'],
];

$tutors = [
['name'=>'Dr. Priya Sharma', 'subject'=>'Mathematics & Statistics', 'exp'=>'8 yrs', 'rating'=>'4.9', 'students'=>'1.2K', 'emoji'=>'👩', 'color'=>'from-violet-500 to-purple-600'],
['name'=>'Rahul Verma', 'subject'=>'Computer Science & AI/ML', 'exp'=>'6 yrs', 'rating'=>'4.8', 'students'=>'980', 'emoji'=>'👨‍💻','color'=>'from-blue-500 to-indigo-600'],
['name'=>'Dr. Anjali Singh', 'subject'=>'Physics & Chemistry', 'exp'=>'10 yrs', 'rating'=>'5.0', 'students'=>'2.1K', 'emoji'=>'👩‍🔬','color'=>'from-emerald-500 to-teal-600'],
['name'=>'Arjun Mehta', 'subject'=>'English & Communication', 'exp'=>'5 yrs', 'rating'=>'4.9', 'students'=>'756', 'emoji'=>'📝', 'color'=>'from-amber-500 to-orange-600'],
];

$testimonials = [
['name'=>'Elena Rodriguez', 'role'=>'Grade 10 Student', 'emoji'=>'👩', 'rating'=>5, 'text'=>'My grades improved dramatically in just 3 months! The personalised approach made all the difference. My tutor explains concepts in a way that just clicks. Truly transformative experience.'],
['name'=>'Michael O\'Connor','role'=>'Engineering Aspirant', 'emoji'=>'👨', 'rating'=>5, 'text'=>'Excellent tutors and a structured learning plan. The regular assessments helped me stay on track and I cracked my entrance exam on the very first attempt!'],
['name'=>'Priya Gupta', 'role'=>'Working Professional', 'emoji'=>'👩‍💼', 'rating'=>5, 'text'=>'Flexible scheduling is a game-changer for professionals like me. I learned Python from scratch and earned a promotion within 6 months. The quality here is outstanding.'],
];

$faqs = [
['q'=>'How does online tutoring work?', 'a'=>'Our platform connects you with expert tutors via live interactive video sessions. After selecting your course, you schedule sessions at your convenience and learn with screen sharing, a digital whiteboard, and real-time problem solving.'],
['q'=>'What subjects do you offer tutoring for?', 'a'=>'We cover Mathematics, Science (Physics, Chemistry, Biology), Computer Science, English, Languages, and competitive exam prep (JEE, NEET, SAT, GRE, UPSC). New courses are added every month.'],
['q'=>'How do I schedule a tutoring session?', 'a'=>'Browse tutors, pick one that matches your needs, view their availability calendar, and book a session with a few clicks. Sessions can be booked up to 24 hours in advance.'],
['q'=>'Can tutoring sessions be customised to my needs?', 'a'=>'Absolutely. Every tutor creates a personalised learning plan based on your goals, current level, and learning style. Sessions are adaptive and designed around your specific requirements.'],
['q'=>'How are tutors selected and verified?', 'a'=>'All tutors undergo a rigorous vetting process — credential verification, background checks, demo sessions, and ongoing performance reviews. Only the top 5% of applicants are accepted.'],
['q'=>'What technology do I need for online tutoring?', 'a'=>'You need a stable internet connection and a device with a camera and microphone. No special software is required — everything runs in your modern web browser.'],
];
@endphp

{{-- ============================================================
     MAIN WRAPPER — Alpine.js root
     ============================================================ --}}
<div
    x-data="{
        mobileOpen: false,
        scrolled: false,
        activeCountry: '🇮🇳 India',
        activeFaq: null,
    }"
    x-init="
        window.addEventListener('scroll', () => { scrolled = window.scrollY > 30; });

        /* ---- Counter animation triggered by Intersection Observer ---- */
        const animateCounters = () => {
            document.querySelectorAll('[data-counter]').forEach(el => {
                if (el.dataset.done) return;
                el.dataset.done = '1';
                const target   = parseInt(el.dataset.counter);
                const duration = 2200;
                const start    = Date.now();
                const tick = () => {
                    const elapsed  = Date.now() - start;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased    = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.floor(target * eased).toLocaleString();
                    if (progress < 1) requestAnimationFrame(tick);
                    else el.textContent = target.toLocaleString();
                };
                requestAnimationFrame(tick);
            });
        };
        const statsEl = document.getElementById('stats-section');
        if (statsEl) {
            new IntersectionObserver(entries => {
                entries.forEach(e => { if (e.isIntersecting) animateCounters(); });
            }, { threshold: 0.3 }).observe(statsEl);
        }
    ">

    {{-- ============================================================
     NAVBAR
     ============================================================ --}}
    <nav class="navbar fixed top-0 left-0 right-0 z-50" :class="{ scrolled: scrolled }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Logo --}}
                <a href="/" class="flex items-center gap-2.5 flex-shrink-0">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-white">{{ explode(' ', $appName)[0] }}<span class="text-grad"> {{ implode(' ', array_slice(explode(' ', $appName), 1)) }}</span></span>
                </a>

                {{-- Desktop Nav --}}
                <div class="hidden md:flex items-center gap-7">
                    @foreach(['Home'=>'#','Courses'=>'#courses','Tutors'=>'#tutors','Pricing'=>'#pricing','Blog'=>'#','Contact'=>'#'] as $label => $href)
                    <a href="{{ $href }}" class="text-gray-400 hover:text-white text-sm font-medium transition-colors duration-200">{{ $label }}</a>
                    @endforeach
                </div>

                {{-- Auth Buttons — dynamic based on auth state --}}
                <div class="hidden md:flex items-center gap-3">
                    @auth
                    <a href="{{ route('dashboard') }}" class="text-gray-300 hover:text-white text-sm font-medium transition-colors px-3 py-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="glass-md px-5 py-2.5 rounded-xl text-gray-300 hover:text-white text-sm font-semibold transition-colors flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sign Out
                        </button>
                    </form>
                    @else
                    <a href="{{ route('auth.login') }}" class="text-gray-300 hover:text-white text-sm font-medium transition-colors px-3 py-2">Sign In</a>
                    <a href="{{ route('auth.register') }}" class="btn-amber px-5 py-2.5 rounded-xl text-white text-sm font-bold">Get Started →</a>
                    @endauth
                </div>

                {{-- Mobile Hamburger --}}
                <button @click="mobileOpen = !mobileOpen" class="md:hidden text-gray-300 p-2 rounded-lg glass-md">
                    <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Mobile Menu --}}
            <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="md:hidden py-4 border-t border-white/10">
                <div class="flex flex-col gap-1">
                    @foreach(['Home'=>'#','Courses'=>'#courses','Tutors'=>'#tutors','Pricing'=>'#pricing','Blog'=>'#'] as $label => $href)
                    <a href="{{ $href }}" class="text-gray-300 hover:text-white px-4 py-2.5 rounded-lg hover:bg-white/10 text-sm font-medium transition-colors">{{ $label }}</a>
                    @endforeach
                    <div class="border-t border-white/10 mt-2 pt-3 flex gap-3 px-4">
                        @auth
                        <a href="{{ route('dashboard') }}" class="flex-1 text-center glass py-2.5 rounded-xl text-gray-300 text-sm font-semibold flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        <form method="POST" action="{{ route('auth.logout') }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full text-center btn-amber py-2.5 rounded-xl text-white text-sm font-bold">Sign Out</button>
                        </form>
                        @else
                        <a href="{{ route('auth.login') }}" class="flex-1 text-center glass py-2.5 rounded-xl text-gray-300 text-sm font-semibold">Sign In</a>
                        <a href="{{ route('auth.register') }}" class="flex-1 text-center btn-amber py-2.5 rounded-xl text-white text-sm font-bold">Get Started</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- ============================================================
     HERO SECTION
     ============================================================ --}}
    <section class="hero-mesh min-h-screen flex flex-col">

        {{-- Background orbs --}}
        <div class="bg-orb w-[600px] h-[600px] top-[-200px] right-[-150px] opacity-20"
            style="background: radial-gradient(circle, #6366F1, transparent)"></div>
        <div class="bg-orb w-[400px] h-[400px] bottom-0 left-[-80px] opacity-15"
            style="background: radial-gradient(circle, #8B5CF6, transparent); animation-delay: 3s;"></div>
        <div class="bg-orb w-[250px] h-[250px] top-1/3 left-1/3 opacity-10"
            style="background: radial-gradient(circle, #F59E0B, transparent); animation-delay: 5s;"></div>

        {{-- Spacer for fixed navbar --}}
        <div class="h-16 flex-shrink-0"></div>

        {{-- Main Hero Content --}}
        <div class="max-w-7xl mx-auto px-4 w-full flex-1 flex items-center py-12 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 xl:gap-20 items-center w-full">

                {{-- LEFT: Copy --}}
                <div>
                    {{-- Badge --}}
                    <div class="inline-flex items-center gap-2 glass rounded-full px-4 py-2 mb-6">
                        <div class="badge-dot"></div>
                        <span class="text-gray-300 text-sm">Trusted by <strong class="text-white">10,000+</strong> Students</span>
                        <span class="text-amber-400 text-xs font-bold ml-1">⭐ 4.9 Rated</span>
                    </div>

                    {{-- Headline --}}
                    <h1 class="text-4xl sm:text-5xl xl:text-6xl font-bold text-white leading-[1.1] mb-6">
                        Top Online Private<br>
                        Tutors for<br>
                        <span class="text-grad">1-on-1 Learning</span>
                    </h1>

                    {{-- Subheadline --}}
                    <p class="text-gray-400 text-lg leading-relaxed mb-8 max-w-lg">
                        Get expert online tutoring for school subjects and competitive exams.
                        Learn at your own pace with <span class="text-white font-medium">personalised sessions</span> from verified experts.
                    </p>

                    {{-- CTAs --}}
                    <div class="flex flex-wrap gap-4 mb-10">
                        <a href="/register" class="btn-amber px-8 py-4 rounded-2xl text-white font-bold text-base shadow-xl">
                            Find Tutor Now 🚀
                        </a>
                        <a href="#how-it-works" class="glass-md px-8 py-4 rounded-2xl text-white font-semibold text-base hover:bg-white/15 transition-colors flex items-center gap-2">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center text-xs">▶</div>
                            Watch Demo
                        </a>
                    </div>

                    {{-- Mini Stats --}}
                    <div class="flex flex-wrap gap-6 items-center">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">10K+</div>
                            <div class="text-gray-500 text-xs mt-0.5">Students</div>
                        </div>
                        <div class="w-px h-8 bg-white/15"></div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">200+</div>
                            <div class="text-gray-500 text-xs mt-0.5">Expert Tutors</div>
                        </div>
                        <div class="w-px h-8 bg-white/15"></div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">500+</div>
                            <div class="text-gray-500 text-xs mt-0.5">Courses</div>
                        </div>
                        <div class="w-px h-8 bg-white/15"></div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">4.9 ★</div>
                            <div class="text-gray-500 text-xs mt-0.5">Avg Rating</div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Dashboard Mockup --}}
                <div class="hidden lg:flex items-center justify-center relative py-12">

                    {{-- Floating geometric bg shapes --}}
                    <div class="absolute w-72 h-72 rounded-full border border-white/5 top-4 left-4 float-y-slow"></div>
                    <div class="absolute w-48 h-48 rounded-full border border-indigo-500/10 bottom-8 right-8 float-y" style="animation-delay:2s"></div>

                    {{-- Main course card --}}
                    <div class="glass-light rounded-3xl p-6 w-80 shadow-2xl float-y relative z-10">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">💻</div>
                            <div>
                                <div class="font-bold text-slate-900 text-sm">Data Structures & Algo</div>
                                <div class="text-slate-500 text-xs">Computer Science · Intermediate</div>
                            </div>
                        </div>

                        {{-- Progress --}}
                        <div class="mb-4">
                            <div class="flex justify-between text-xs mb-1.5">
                                <span class="text-slate-500">Course Progress</span>
                                <span class="text-indigo-600 font-bold">68%</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full w-[68%] bg-gradient-to-r from-indigo-500 to-violet-500 rounded-full shimmer-line"></div>
                            </div>
                        </div>

                        {{-- Next lesson --}}
                        <div class="glass rounded-xl p-3 mb-4" style="background:rgba(99,102,241,.06); border-color:rgba(99,102,241,.15);">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">▶</span>
                                <div>
                                    <div class="text-xs text-slate-500">Next Lesson</div>
                                    <div class="text-sm font-semibold text-slate-900">Binary Trees & BST</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex -space-x-2">
                                @foreach(['🟣','🔵','🟢'] as $dot)
                                <div class="w-7 h-7 rounded-full border-2 border-white bg-gradient-to-br from-indigo-400 to-violet-400 flex items-center justify-center text-xs">{{ $loop->iteration }}</div>
                                @endforeach
                                <div class="w-7 h-7 rounded-full border-2 border-white bg-slate-100 flex items-center justify-center text-[10px] text-slate-500 font-bold">+9</div>
                            </div>
                            <button class="btn-indigo px-4 py-2 rounded-xl text-white text-xs font-bold">Continue →</button>
                        </div>
                    </div>

                    {{-- Floating Achievement Badge --}}
                    <div class="absolute -bottom-2 -left-6 glass-light rounded-2xl p-4 shadow-xl float-y" style="animation-delay:1.5s; z-index:20;">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-xl">🏆</div>
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase tracking-wide">Achievement</div>
                                <div class="font-bold text-slate-900 text-sm">7-Day Streak!</div>
                                <div class="flex gap-0.5 mt-0.5">
                                    @for($i=0;$i<7;$i++)<div class="w-3 h-1.5 bg-amber-400 rounded-full {{ $i < 5 ? '' : 'opacity-30' }}">
                                </div>@endfor
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Floating Tutor Badge --}}
                <div class="absolute -top-2 -right-4 glass-light rounded-2xl p-4 shadow-xl float-y" style="animation-delay:.8s; z-index:20;">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 flex items-center justify-center text-2xl border-2 border-white shadow">👩</div>
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Your Tutor</div>
                            <div class="font-bold text-slate-900 text-sm">Dr. Sarah K.</div>
                            <div class="text-amber-400 text-xs">★★★★★ <span class="text-slate-400">(128)</span></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <div class="badge-dot"></div>
                        <span class="text-[11px] text-emerald-500 font-medium">Available Now</span>
                    </div>
                </div>

                {{-- Floating Live Session Badge --}}
                <div class="absolute top-1/2 -right-8 glass-light rounded-2xl px-4 py-3 shadow-xl" style="transform:translateY(40px); z-index:20;">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-bold text-slate-900">Live Session</span>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Physics · 43 students</div>
                </div>
            </div>
        </div>
</div>

{{-- Country Selector Banner --}}
<div class="max-w-4xl mx-auto px-4 w-full pb-16 relative z-10">
    <div class="glass-md rounded-2xl p-5 shadow-2xl shadow-indigo-500/10">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-white text-sm font-semibold">Select Your Country</p>
                <p class="text-gray-400 text-xs">Find tutors and courses tailored to your curriculum</p>
            </div>
            <div class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex-shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                <span class="text-emerald-400 text-xs font-medium" x-text="activeCountry || '🇮🇳 India'"></span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach($countries as $country)
            <button
                @click="activeCountry = '{{ $country }}'"
                :class="activeCountry === '{{ $country }}'
                        ? 'bg-indigo-500/25 border-indigo-500/60 text-indigo-300 shadow-lg shadow-indigo-500/10'
                        : 'text-gray-400 hover:text-gray-200 hover:border-white/25'"
                class="glass px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all duration-200 border border-white/10 flex items-center gap-1.5">
                {{ $country }}
                <span x-show="activeCountry === '{{ $country }}'" class="w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block flex-shrink-0"></span>
            </button>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
            <p class="text-gray-500 text-xs">
                Showing tutors available in <span class="text-white font-medium" x-text="activeCountry || '🇮🇳 India'"></span>
            </p>
            <a href="{{ route('auth.register') }}" class="btn-amber px-5 py-2 rounded-xl text-white font-bold text-sm">
                Find Tutors →
            </a>
        </div>
    </div>
</div>
</section>

{{-- ============================================================
     ALL-IN-ONE FEATURES SECTION
     ============================================================ --}}
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="inline-block bg-indigo-50 text-indigo-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">All-in-One Platform</span>
            <h2 class="text-4xl font-bold text-slate-900">Online Tutoring for School Subjects<br class="hidden sm:block"> & Competitive Exams</h2>
            <div class="section-accent"></div>
            <p class="mt-4 text-slate-500 max-w-xl mx-auto">From K-12 academics to entrance exam coaching — one platform for every learning need.</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-16 items-center">
            {{-- Features Grid --}}
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach($features as $f)
                <div class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 hover:bg-indigo-50/60 transition-colors border border-transparent hover:border-indigo-100 group">
                    <div class="w-10 h-10 rounded-xl bg-white shadow-sm border border-slate-100 flex items-center justify-center text-xl flex-shrink-0 group-hover:scale-110 transition-transform">{{ $f['icon'] }}</div>
                    <div>
                        <h3 class="font-semibold text-slate-900 text-sm mb-0.5">{{ $f['title'] }}</h3>
                        <p class="text-slate-500 text-xs leading-relaxed">{{ $f['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Visual Dashboard Card --}}
            <div class="relative">
                <div class="bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 rounded-3xl p-8 text-white shadow-2xl shadow-indigo-500/30">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold">Learning Dashboard</h3>
                        <div class="flex items-center gap-1.5 bg-white/15 rounded-full px-3 py-1 text-xs">
                            <div class="badge-dot"></div>
                            <span>Live</span>
                        </div>
                    </div>

                    {{-- Progress Items --}}
                    @foreach([['Mathematics','78%','w-[78%]'],['Physics','55%','w-[55%]'],['English','91%','w-[91%]']] as [$subj,$pct,$w])
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1.5">
                            <span class="text-white/80">{{ $subj }}</span>
                            <span class="font-bold">{{ $pct }}</span>
                        </div>
                        <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full {{ $w }} bg-white/70 rounded-full"></div>
                        </div>
                    </div>
                    @endforeach

                    <div class="mt-6 grid grid-cols-3 gap-3 pt-4 border-t border-white/20">
                        <div class="text-center">
                            <div class="text-2xl font-bold">24</div>
                            <div class="text-white/60 text-xs mt-0.5">Sessions</div>
                        </div>
                        <div class="text-center border-x border-white/20">
                            <div class="text-2xl font-bold">48h</div>
                            <div class="text-white/60 text-xs mt-0.5">Learned</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">A+</div>
                            <div class="text-white/60 text-xs mt-0.5">Grade</div>
                        </div>
                    </div>
                </div>

                {{-- Floating badge --}}
                <div class="absolute -bottom-5 -right-5 glass-light rounded-2xl px-5 py-4 shadow-xl">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📈</span>
                        <div>
                            <div class="text-xs text-slate-500">Improvement</div>
                            <div class="font-bold text-slate-900 text-sm text-grad">+42% this month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     ANIMATED STATS
     ============================================================ --}}
<section id="stats-section" class="py-20 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-950 relative overflow-hidden">
    <div class="bg-orb w-[500px] h-[500px] top-[-200px] left-[10%]" style="background:radial-gradient(circle,#4F46E5,transparent)"></div>
    <div class="bg-orb w-[400px] h-[400px] bottom-[-150px] right-[10%]" style="background:radial-gradient(circle,#7C3AED,transparent);animation-delay:4s;"></div>

    <div class="max-w-7xl mx-auto px-4 relative z-10">
        <div class="text-center mb-14">
            <h2 class="text-3xl font-bold text-white">Why Thousands Choose <span class="text-grad">Us</span></h2>
            <div class="section-accent"></div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($stats as $stat)
            <div class="glass rounded-3xl p-8 text-center card-lift group">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gradient-to-br {{ $stat['color'] }} flex items-center justify-center text-2xl shadow-lg group-hover:scale-110 transition-transform">{{ $stat['icon'] }}</div>
                <div class="text-4xl font-bold text-white mb-1">
                    <span data-counter="{{ $stat['value'] }}">0</span>{{ $stat['suffix'] }}
                </div>
                <div class="text-gray-400 text-sm">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================
     WHY CHOOSE US
     ============================================================ --}}
<section class="py-24 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="inline-block bg-indigo-50 text-indigo-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">Why Us</span>
            <h2 class="text-4xl font-bold text-slate-900">Why Choose <span class="text-grad">Our Platform</span></h2>
            <div class="section-accent"></div>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach($whys as $i => $why)
            <div class="card-lift bg-white rounded-3xl p-8 shadow-sm border border-slate-100 group">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br {{ $why['color'] }} flex items-center justify-center text-3xl mb-6 shadow-lg group-hover:scale-110 transition-transform">{{ $why['icon'] }}</div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">{{ $why['title'] }}</h3>
                <p class="text-slate-500 leading-relaxed text-sm">{{ $why['desc'] }}</p>
                <div class="mt-6 flex items-center gap-2 text-indigo-600 text-sm font-semibold group-hover:gap-3 transition-all">
                    Learn More <span>→</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================
     HOW IT WORKS
     ============================================================ --}}
<section id="how-it-works" class="py-24 bg-white">
    <div class="max-w-5xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="inline-block bg-violet-50 text-violet-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">Process</span>
            <h2 class="text-4xl font-bold text-slate-900">How It <span class="text-grad">Works</span></h2>
            <div class="section-accent"></div>
            <p class="mt-4 text-slate-500">Get started in 4 simple steps</p>
        </div>

        <div class="relative">
            {{-- Center connecting line (desktop) --}}
            <div class="hidden lg:block absolute left-1/2 top-6 bottom-6 w-px" style="background:linear-gradient(to bottom,#6366F1,rgba(99,102,241,0))"></div>

            <div class="space-y-12">
                @foreach($steps as $step)
                <div class="grid lg:grid-cols-2 gap-8 items-center {{ $step['side'] === 'left' ? '' : '' }}">
                    @if($step['side'] === 'right')
                    {{-- Spacer left --}}
                    <div class="hidden lg:flex justify-end pr-10">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-2xl shadow-xl shadow-indigo-500/30 z-10 relative">{{ $step['icon'] }}</div>
                    </div>
                    {{-- Content right --}}
                    <div class="glass-light rounded-3xl p-6 shadow-sm border border-slate-100 card-lift">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-xs font-bold text-indigo-400 bg-indigo-50 px-2.5 py-1 rounded-full">Step {{ $step['num'] }}</span>
                            <span class="text-xl lg:hidden">{{ $step['icon'] }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $step['title'] }}</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                    @else
                    {{-- Content left --}}
                    <div class="glass-light rounded-3xl p-6 shadow-sm border border-slate-100 card-lift">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-xs font-bold text-violet-400 bg-violet-50 px-2.5 py-1 rounded-full">Step {{ $step['num'] }}</span>
                            <span class="text-xl lg:hidden">{{ $step['icon'] }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $step['title'] }}</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                    {{-- Icon right --}}
                    <div class="hidden lg:flex justify-start pl-10">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-violet-500 to-pink-600 flex items-center justify-center text-2xl shadow-xl shadow-violet-500/30 z-10 relative">{{ $step['icon'] }}</div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     COURSES
     ============================================================ --}}
<section id="courses" class="py-24 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between mb-12 gap-4">
            <div>
                <span class="inline-block bg-blue-50 text-blue-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">Popular Courses</span>
                <h2 class="text-4xl font-bold text-slate-900">Explore Top <span class="text-grad">Courses</span></h2>
                <div class="section-accent" style="margin:12px 0 0;"></div>
            </div>
            <a href="#" class="text-indigo-600 font-semibold text-sm hover:text-indigo-700 flex items-center gap-1 flex-shrink-0">View All Courses →</a>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($courses as $course)
            <div class="card-lift bg-white rounded-3xl overflow-hidden shadow-sm border border-slate-100 flex flex-col">
                {{-- Thumbnail --}}
                <div class="relative h-44 bg-gradient-to-br {{ $course['color'] }} overflow-hidden flex items-center justify-center">
                    <span class="text-8xl thumb-zoom select-none">{{ $course['emoji'] }}</span>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                    <div class="absolute top-3 left-3">
                        <span class="bg-white/20 backdrop-blur text-white text-[11px] font-semibold px-3 py-1 rounded-full">{{ $course['category'] }}</span>
                    </div>
                    <div class="absolute top-3 right-3">
                        <span class="bg-amber-400 text-amber-900 text-[11px] font-bold px-3 py-1 rounded-full">{{ $course['tag'] }}</span>
                    </div>
                    <div class="absolute bottom-3 right-3">
                        <span class="bg-white/20 backdrop-blur text-white text-[11px] px-2.5 py-1 rounded-full">{{ $course['level'] }}</span>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-6 flex flex-col flex-1">
                    <h3 class="font-bold text-slate-900 text-base mb-1 leading-snug">{{ $course['title'] }}</h3>
                    <p class="text-slate-400 text-xs mb-3">by {{ $course['tutor'] }}</p>

                    <div class="flex items-center gap-3 text-xs text-slate-400 mb-3">
                        <span class="flex items-center gap-1">⏱ {{ $course['duration'] }}</span>
                        <span>·</span>
                        <span class="flex items-center gap-1">👥 {{ $course['students'] }} students</span>
                    </div>

                    <div class="flex items-center gap-1.5 mb-4">
                        <span class="text-amber-500 font-bold text-sm">{{ $course['rating'] }}</span>
                        <div class="flex text-amber-400 text-xs">★★★★★</div>
                        <span class="text-slate-400 text-xs">({{ $course['reviews'] }})</span>
                    </div>

                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-slate-100">
                        <span class="text-xl font-bold text-slate-900">{{ $course['price'] }}</span>
                        <a href="/register" class="btn-indigo px-5 py-2.5 rounded-xl text-white text-sm font-bold">Enroll Now</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================
     MEET OUR TUTORS
     ============================================================ --}}
<section id="tutors" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="inline-block bg-emerald-50 text-emerald-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">Expert Faculty</span>
            <h2 class="text-4xl font-bold text-slate-900">Meet Our <span class="text-grad">Top Tutors</span></h2>
            <div class="section-accent"></div>
            <p class="mt-4 text-slate-500 max-w-md mx-auto">Handpicked experts with proven track records and a passion for teaching.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($tutors as $tutor)
            <div class="tutor-card card-lift bg-white rounded-3xl p-6 text-center shadow-sm border border-slate-100 group">
                {{-- Avatar --}}
                <div class="relative inline-block mb-4">
                    <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br {{ $tutor['color'] }} flex items-center justify-center text-4xl shadow-xl tutor-avatar border-4 border-white ring-4 ring-indigo-50">{{ $tutor['emoji'] }}</div>
                    <div class="absolute bottom-0 right-0 w-5 h-5 bg-emerald-500 rounded-full border-2 border-white"></div>
                </div>

                <h3 class="font-bold text-slate-900 text-sm mb-0.5">{{ $tutor['name'] }}</h3>
                <p class="text-slate-500 text-xs mb-3">{{ $tutor['subject'] }}</p>

                <div class="flex justify-center items-center gap-1 mb-3">
                    <div class="text-amber-400 text-xs">★★★★★</div>
                    <span class="text-xs font-bold text-slate-700">{{ $tutor['rating'] }}</span>
                </div>

                <div class="flex justify-center gap-4 text-xs text-slate-400 mb-4">
                    <span>🎓 {{ $tutor['exp'] }} exp</span>
                    <span>👥 {{ $tutor['students'] }}</span>
                </div>

                <a href="/register" class="block btn-indigo px-4 py-2.5 rounded-xl text-white text-xs font-bold w-full opacity-90 group-hover:opacity-100 transition-opacity">Book Session</a>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-10">
            <a href="#" class="inline-flex items-center gap-2 border border-indigo-200 text-indigo-600 font-semibold px-8 py-3 rounded-xl hover:bg-indigo-50 transition-colors text-sm">
                View All 200+ Tutors →
            </a>
        </div>
    </div>
</section>

{{-- ============================================================
     TESTIMONIALS
     ============================================================ --}}
<section class="py-24 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-950 relative overflow-hidden">
    <div class="bg-orb w-[400px] h-[400px] top-[-100px] right-[-50px]" style="background:radial-gradient(circle,#6366F1,transparent)"></div>

    <div class="max-w-7xl mx-auto px-4 relative z-10">
        <div class="text-center mb-16">
            <span class="inline-block glass text-indigo-300 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">Student Stories</span>
            <h2 class="text-4xl font-bold text-white">What <span class="text-grad">Parents & Students</span> Say</h2>
            <div class="section-accent"></div>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @foreach($testimonials as $t)
            <div class="quote-card glass card-lift rounded-3xl p-7 relative">
                {{-- Stars --}}
                <div class="flex text-amber-400 text-sm mb-4 gap-0.5">
                    @for($i=0;$i<$t['rating'];$i++)★@endfor
                        </div>

                        <p class="text-gray-300 text-sm leading-relaxed mb-6">"{{ $t['text'] }}"</p>

                        <div class="flex items-center gap-3 pt-4 border-t border-white/10">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-xl border-2 border-white/20">{{ $t['emoji'] }}</div>
                            <div>
                                <div class="text-white font-semibold text-sm">{{ $t['name'] }}</div>
                                <div class="text-gray-500 text-xs">{{ $t['role'] }}</div>
                            </div>
                        </div>
                </div>
                @endforeach
            </div>

            {{-- Trust indicators --}}
            <div class="mt-12 flex flex-wrap justify-center gap-8 items-center">
                @foreach([['⭐','4.9/5','Average Rating'],['👍','98%','Satisfaction Rate'],['🔄','86%','Students Return'],['🏆','#1','Tutoring Platform']] as [$icon,$val,$lbl])
                <div class="text-center">
                    <div class="text-2xl mb-1">{{ $icon }}</div>
                    <div class="text-white font-bold text-lg">{{ $val }}</div>
                    <div class="text-gray-500 text-xs">{{ $lbl }}</div>
                </div>
                @endforeach
            </div>
        </div>
</section>

{{-- ============================================================
     FAQ
     ============================================================ --}}
<section class="py-24 bg-white">
    <div class="max-w-3xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="inline-block bg-amber-50 text-amber-600 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">FAQ</span>
            <h2 class="text-4xl font-bold text-slate-900">Frequently Asked <span class="text-grad">Questions</span></h2>
            <div class="section-accent"></div>
            <p class="mt-4 text-slate-500">Find answers to the most common questions about our service.</p>
        </div>

        <div class="space-y-3">
            @foreach($faqs as $i => $faq)
            <div
                class="border border-slate-200 rounded-2xl overflow-hidden hover:border-indigo-200 transition-colors"
                x-data="{ open: false }">
                <button
                    @click="open = !open"
                    class="w-full flex items-center justify-between px-6 py-5 text-left hover:bg-slate-50 transition-colors"
                    :aria-expanded="open">
                    <span class="font-semibold text-slate-900 text-sm pr-4">{{ $faq['q'] }}</span>
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0 transition-all duration-300" :class="open ? 'bg-indigo-100 rotate-45' : ''">
                        <svg class="w-4 h-4 text-slate-500 transition-colors" :class="open ? 'text-indigo-600' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </button>
                <div x-show="open" x-collapse class="px-6 pb-5">
                    <p class="text-slate-500 text-sm leading-relaxed">{{ $faq['a'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================
     CTA BANNER
     ============================================================ --}}
<section class="py-24 bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 relative overflow-hidden">
    <div class="bg-orb w-[500px] h-[500px] top-[-200px] right-[-100px] opacity-20" style="background:radial-gradient(circle,#fff,transparent)"></div>
    <div class="bg-orb w-[300px] h-[300px] bottom-[-100px] left-[-50px] opacity-15" style="background:radial-gradient(circle,#F59E0B,transparent);animation-delay:3s"></div>

    {{-- Dot grid --}}
    <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle,rgba(255,255,255,.4) 1px,transparent 1px);background-size:30px 30px;"></div>

    <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
        <div class="inline-flex items-center gap-2 bg-white/15 rounded-full px-4 py-2 mb-6">
            <span class="text-amber-300">🎯</span>
            <span class="text-white/80 text-sm">Limited Seats Available</span>
        </div>
        <h2 class="text-4xl sm:text-5xl font-bold text-white mb-6 leading-tight">
            Ready to Transform<br>Your Learning?
        </h2>
        <p class="text-white/70 text-lg mb-10 max-w-lg mx-auto">
            Join thousands of students already learning with our expert tutors. Start your personalised journey today.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="/register" class="btn-amber px-10 py-4 rounded-2xl text-white font-bold text-base shadow-2xl">
                Get Started Free →
            </a>
            <a href="#" class="bg-white/15 border border-white/30 px-10 py-4 rounded-2xl text-white font-semibold text-base hover:bg-white/25 transition-colors backdrop-blur">
                Book Free Demo
            </a>
        </div>
        <p class="text-white/40 text-xs mt-6">No credit card required · Free 30-min demo · Cancel anytime</p>
    </div>
</section>

{{-- ============================================================
     FOOTER
     ============================================================ --}}
<footer class="bg-slate-950 text-gray-400">
    <div class="max-w-7xl mx-auto px-4 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">

            {{-- Brand --}}
            <div class="lg:col-span-1">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-white">{{ explode(' ', $appName)[0] }}<span class="text-grad"> {{ implode(' ', array_slice(explode(' ', $appName), 1)) }}</span></span>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-5">
                    The leading platform connecting students with verified expert tutors for personalised 1-on-1 learning experiences.
                </p>
                <div class="flex items-center gap-3">
                    @foreach(['𝕏','f','in','▶'] as $icon)
                    <a href="#" class="w-9 h-9 rounded-xl glass flex items-center justify-center text-gray-400 hover:text-white hover:bg-indigo-500/20 transition-colors text-sm font-bold">{{ $icon }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-white font-semibold mb-5 text-sm uppercase tracking-wider">Quick Links</h4>
                <ul class="space-y-2.5">
                    @foreach(['Home','About Us','Courses & Subjects','SAT Series','Pricing','Contact Us'] as $link)
                    <li><a href="#" class="foot-link text-sm">{{ $link }}</a></li>
                    @endforeach
                </ul>
            </div>

            {{-- Subjects --}}
            <div>
                <h4 class="text-white font-semibold mb-5 text-sm uppercase tracking-wider">Subjects</h4>
                <ul class="space-y-2.5">
                    @foreach(['Mathematics','Computer Science','Physics & Chemistry','English Language','Biology & Life Science','Competitive Exams'] as $link)
                    <li><a href="#" class="foot-link text-sm">{{ $link }}</a></li>
                    @endforeach
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="text-white font-semibold mb-5 text-sm uppercase tracking-wider">Legal</h4>
                <ul class="space-y-2.5 mb-6">
                    @foreach(['Privacy Policy','Terms of Service','Cookie Policy','Refund Policy','FAQ'] as $link)
                    <li><a href="#" class="foot-link text-sm">{{ $link }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="border-t border-white/5 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-gray-600">© {{ date('Y') }} {{ $appName }}. All rights reserved. Built with ❤️ for learners worldwide.</p>
            <div class="flex items-center gap-4 text-xs text-gray-600">
                <a href="#" class="hover:text-gray-400 transition-colors">Privacy</a>
                <span>·</span>
                <a href="#" class="hover:text-gray-400 transition-colors">Terms</a>
                <span>·</span>
                <a href="#" class="hover:text-gray-400 transition-colors">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

</div>{{-- /x-data --}}

@endsection