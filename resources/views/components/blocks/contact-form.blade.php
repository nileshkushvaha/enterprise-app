<!-- Contact Form Block -->
<section class="contact-form-block py-12">
    <div class="container max-w-2xl">
        @if($title ?? false)
            <h2 class="text-2xl font-bold mb-2">{{ $title }}</h2>
        @endif
        @if($description ?? false)
            <p class="text-gray-600 mb-6">{{ $description }}</p>
        @endif
        @if(session('success'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif
        <form method="POST" action="{{ route('contact.submit') }}" class="space-y-6">
            @csrf

            <input type="hidden" name="block_id" value="{{ $block_id ?? '' }}">
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            @foreach($fields ?? [] as $index => $field)
                @php
                    $fieldName = $field['name'] ?? ('field_' . $index);
                    $fieldType = $field['type'] ?? 'text';
                    $fieldLabel = $field['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                    $isRequired = (bool) ($field['required'] ?? false);
                    $placeholder = $field['placeholder'] ?? '';
                    $fieldValue = old($fieldName);
                    $options = array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? ''))));
                @endphp

                <div>
                    <label for="{{ $fieldName }}" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ $fieldLabel }}@if($isRequired) <span class="text-red-600">*</span>@endif
                    </label>

                    @if($fieldType === 'textarea')
                        <textarea
                            id="{{ $fieldName }}"
                            name="{{ $fieldName }}"
                            rows="4"
                            @if($isRequired) required @endif
                            placeholder="{{ $placeholder }}"
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        >{{ $fieldValue }}</textarea>
                    @elseif($fieldType === 'select')
                        <select
                            id="{{ $fieldName }}"
                            name="{{ $fieldName }}"
                            @if($isRequired) required @endif
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        >
                            <option value="">Select...</option>
                            @foreach($options as $option)
                                <option value="{{ $option }}" @selected($fieldValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="{{ $fieldName }}"
                            name="{{ $fieldName }}"
                            type="{{ in_array($fieldType, ['email', 'tel', 'text', 'phone'], true) ? ($fieldType === 'phone' ? 'tel' : $fieldType) : 'text' }}"
                            value="{{ $fieldValue }}"
                            @if($isRequired) required @endif
                            placeholder="{{ $placeholder }}"
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        >
                    @endif

                    @if(isset($errors))
                        @error($fieldName)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
            @endforeach

            <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">
                {{ $button_text ?? 'Send Message' }}
            </button>
        </form>
    </div>
</section>
