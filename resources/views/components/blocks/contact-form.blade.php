{{-- Contact Form Block — Light Glassmorphism --}}
<section class="relative py-20 overflow-hidden">

    {{-- Decorative background blobs --}}
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-gradient-to-br from-indigo-300/30 to-violet-300/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 h-[500px] w-[500px] rounded-full bg-gradient-to-tl from-purple-300/25 to-blue-300/20 blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-64 w-96 rounded-full bg-indigo-200/20 blur-2xl"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 items-start">

            {{-- ── Left info panel ──────────────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Heading --}}
                <div>
                    @if($title ?? false)
                        <h2 class="text-3xl sm:text-4xl font-bold text-slate-800 leading-tight">{{ $title }}</h2>
                    @endif
                    @if($description ?? false)
                        <p class="mt-3 text-base text-slate-500 leading-relaxed">{{ $description }}</p>
                    @endif
                </div>

                {{-- Decorative feature cards --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-3.5 rounded-2xl bg-white/50 backdrop-blur-sm border border-white/60 px-4 py-3.5 shadow-sm">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-100">
                            <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Email us</p>
                            <p class="text-sm font-medium text-slate-700 mt-0.5">hello@example.com</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3.5 rounded-2xl bg-white/50 backdrop-blur-sm border border-white/60 px-4 py-3.5 shadow-sm">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-violet-100">
                            <svg class="h-5 w-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Response time</p>
                            <p class="text-sm font-medium text-slate-700 mt-0.5">Within 24 hours</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3.5 rounded-2xl bg-white/50 backdrop-blur-sm border border-white/60 px-4 py-3.5 shadow-sm">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                            <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Always free</p>
                            <p class="text-sm font-medium text-slate-700 mt-0.5">No hidden charges</p>
                        </div>
                    </div>
                </div>

                {{-- Decorative illustration --}}
                <div class="hidden lg:block">
                    <div class="relative h-44 w-44">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-400/20 to-violet-400/20 backdrop-blur-sm border border-white/40 shadow-xl rotate-6"></div>
                        <div class="absolute inset-2 rounded-2xl bg-gradient-to-br from-indigo-500/10 to-violet-500/10 backdrop-blur-sm border border-white/50 shadow-inner -rotate-3 flex items-center justify-center">
                            <svg class="h-16 w-16 text-indigo-400/60" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Right form panel ─────────────────────────────────────── --}}
            <div class="lg:col-span-3">
                <div class="relative rounded-3xl bg-white/40 backdrop-blur-xl border border-white/60 shadow-2xl shadow-indigo-200/30 p-8 sm:p-10">

                    {{-- Subtle inner highlight --}}
                    <div class="pointer-events-none absolute inset-0 rounded-3xl bg-gradient-to-br from-white/60 via-transparent to-transparent" aria-hidden="true"></div>

                    {{-- Success state --}}
                    @if(session('success'))
                    <div class="mb-6 flex items-start gap-3 rounded-2xl bg-emerald-50/80 backdrop-blur-sm border border-emerald-200/60 px-5 py-4 text-sm text-emerald-700 shadow-sm">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 mt-0.5">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold">Message sent!</p>
                            <p class="mt-0.5 text-emerald-600">{{ session('success') }}</p>
                        </div>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('contact.submit') }}" class="relative space-y-5">
                        @csrf
                        <input type="hidden" name="block_id" value="{{ $block_id ?? '' }}">
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                        @php
                            $fields = $fields ?? [];
                            $inputBase = 'w-full rounded-xl bg-white/70 backdrop-blur-sm border px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-400/50 focus:border-indigo-300 focus:bg-white/90 hover:bg-white/80';
                            $inputNormal = $inputBase . ' border-slate-200/80 shadow-sm';
                            $inputError  = $inputBase . ' border-red-300 bg-red-50/60';

                            // Group into pairs for 2-column layout (skip textarea/select)
                            $rendered = [];
                        @endphp

                        {{-- Render fields — pair text/email/tel side-by-side when consecutive --}}
                        @php $i = 0; @endphp
                        @while($i < count($fields))
                            @php
                                $field     = $fields[$i];
                                $fieldName = $field['name'] ?? ('field_' . $i);
                                $fieldType = $field['type'] ?? 'text';
                                $isInline  = in_array($fieldType, ['text', 'email', 'tel', 'phone', 'number', 'url']);
                                $nextField = $fields[$i + 1] ?? null;
                                $nextType  = $nextField['type'] ?? '';
                                $nextInline = $nextField && in_array($nextType, ['text', 'email', 'tel', 'phone', 'number', 'url']);
                                $makePair  = $isInline && $nextInline;
                            @endphp

                            @if($makePair)
                                {{-- 2-column pair --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @for($p = 0; $p < 2; $p++)
                                    @php
                                        $f         = $fields[$i + $p];
                                        $fName     = $f['name'] ?? ('field_' . ($i + $p));
                                        $fType     = $f['type'] ?? 'text';
                                        $fLabel    = $f['label'] ?? ucfirst(str_replace('_', ' ', $fName));
                                        $fRequired = (bool) ($f['required'] ?? false);
                                        $fPlaceholder = $f['placeholder'] ?? '';
                                        $fValue    = old($fName);
                                        $hasErr    = isset($errors) && $errors->has($fName);
                                    @endphp
                                    <div>
                                        <label for="{{ $fName }}" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                                            {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                        </label>
                                        <input
                                            id="{{ $fName }}"
                                            name="{{ $fName }}"
                                            type="{{ $fType === 'phone' ? 'tel' : $fType }}"
                                            value="{{ $fValue }}"
                                            @if($fRequired) required @endif
                                            placeholder="{{ $fPlaceholder }}"
                                            class="{{ $hasErr ? $inputError : $inputNormal }}"
                                        >
                                        @if(isset($errors))
                                            @error($fName)
                                                <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                                                    <svg class="h-3 w-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        @endif
                                    </div>
                                    @endfor
                                </div>
                                @php $i += 2; @endphp
                            @else
                                {{-- Single full-width field --}}
                                @php
                                    $fLabel    = $field['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                                    $fRequired = (bool) ($field['required'] ?? false);
                                    $fPlaceholder = $field['placeholder'] ?? '';
                                    $fValue    = old($fieldName);
                                    $fOptions  = array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? ''))));
                                    $hasErr    = isset($errors) && $errors->has($fieldName);
                                @endphp
                                <div>
                                    <label for="{{ $fieldName }}" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                                        {{ $fLabel }}@if($fRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </label>
                                    @if($fieldType === 'textarea')
                                        <textarea
                                            id="{{ $fieldName }}"
                                            name="{{ $fieldName }}"
                                            rows="4"
                                            @if($fRequired) required @endif
                                            placeholder="{{ $fPlaceholder }}"
                                            class="{{ $hasErr ? $inputError : $inputNormal }} resize-none"
                                        >{{ $fValue }}</textarea>
                                    @elseif($fieldType === 'select')
                                        <div class="relative">
                                            <select
                                                id="{{ $fieldName }}"
                                                name="{{ $fieldName }}"
                                                @if($fRequired) required @endif
                                                class="{{ $hasErr ? $inputError : $inputNormal }} appearance-none pr-10"
                                            >
                                                <option value="">Select…</option>
                                                @foreach($fOptions as $option)
                                                    <option value="{{ $option }}" @selected($fValue === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    @else
                                        <input
                                            id="{{ $fieldName }}"
                                            name="{{ $fieldName }}"
                                            type="{{ in_array($fieldType, ['email','tel','text','phone'], true) ? ($fieldType === 'phone' ? 'tel' : $fieldType) : 'text' }}"
                                            value="{{ $fValue }}"
                                            @if($fRequired) required @endif
                                            placeholder="{{ $fPlaceholder }}"
                                            class="{{ $hasErr ? $inputError : $inputNormal }}"
                                        >
                                    @endif
                                    @if(isset($errors))
                                        @error($fieldName)
                                            <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                                                <svg class="h-3 w-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    @endif
                                </div>
                                @php $i++; @endphp
                            @endif
                        @endwhile

                        {{-- Submit --}}
                        <div class="pt-1">
                            <button
                                type="submit"
                                class="group relative w-full inline-flex items-center justify-center gap-2.5 overflow-hidden rounded-2xl px-8 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all duration-200 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5 active:translate-y-0"
                                style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #7c3aed 100%)"
                            >
                                {{-- Shimmer --}}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/15 to-transparent skew-x-12"></span>

                                <svg class="h-4 w-4 relative" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                </svg>
                                <span class="relative">{{ $button_text ?? 'Send Message' }}</span>
                            </button>

                            <p class="mt-3 text-center text-[11px] text-slate-400">
                                🔒 Your information is kept private and never shared.
                            </p>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>
