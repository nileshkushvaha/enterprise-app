@props([
    'label'    => '',
    'value'    => '0',
    'gradient' => 'from-indigo-500 to-violet-500',
    'icon'     => '',
])

<div class="rounded-2xl border border-white/[0.07] p-5 relative overflow-hidden group hover:border-white/[0.12] transition-all"
     style="background:rgba(255,255,255,0.03)">
    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500"
         style="background:radial-gradient(ellipse at top left, rgba(99,102,241,.06), transparent 70%)"></div>
    <div class="relative z-10">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $gradient }} flex items-center justify-center mb-4 shadow-lg">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $icon }}"/>
            </svg>
        </div>
        <p class="text-3xl font-bold text-white mb-1">{{ $value }}</p>
        <p class="text-slate-400 text-sm">{{ $label }}</p>
    </div>
</div>
