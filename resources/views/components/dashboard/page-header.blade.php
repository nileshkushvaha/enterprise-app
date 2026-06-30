@props(['date' => '', 'name' => ''])

<div class="mb-10">
    <div class="flex items-start justify-between flex-wrap gap-4">
        <div>
            <p class="text-slate-400 text-sm mb-1">{{ $date }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
                Welcome back,
                <span class="text-grad">{{ $name }}</span>! 👋
            </h1>
            <p class="text-slate-400">Ready to continue your learning journey?</p>
        </div>
        <a href="#" class="btn-indigo px-5 py-2.5 rounded-xl text-white text-sm font-semibold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Browse Courses
        </a>
    </div>
</div>
