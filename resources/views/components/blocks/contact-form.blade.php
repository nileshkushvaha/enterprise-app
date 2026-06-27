<!-- Contact Form Block -->
<section class="contact-form-block py-12">
    <div class="container max-w-2xl">
        @if($title ?? false)
            <h2 class="text-2xl font-bold mb-2">{{ $title }}</h2>
        @endif
        @if($description ?? false)
            <p class="text-gray-600 mb-6">{{ $description }}</p>
        @endif
        <form method="POST" action="{{ route('contact.submit') }}" class="space-y-6">
            @csrf
            
            <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">
                {{ $button_text ?? 'Send Message' }}
            </button>
        </form>
    </div>
</section>
