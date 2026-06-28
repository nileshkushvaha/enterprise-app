{{-- Shared inline CSS — included by every layout --}}
<style>
    body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    [x-cloak] { display: none !important; }

    /* ── Keyframes ─────────────────────────────────────────────── */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(24px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0) scale(1); }
        50%       { transform: translateY(-18px) scale(1.04); }
    }
    @keyframes floatSlow {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50%       { transform: translateY(-30px) rotate(4deg); }
    }
    @keyframes blobShift {
        0%, 100% { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; }
        50%       { border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%; }
    }
    @keyframes shimmer {
        0%   { background-position: -200% center; }
        100% { background-position:  200% center; }
    }
    @keyframes gradientShift {
        0%   { background-position: 0%   50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0%   50%; }
    }
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 20px rgba(99,102,241,.3); }
        50%       { box-shadow: 0 0 40px rgba(139,92,246,.5); }
    }
    @keyframes spin-slow {
        to { transform: rotate(360deg); }
    }

    /* ── Utility classes ────────────────────────────────────────── */
    .animate-fade-in-up   { animation: fadeInUp .55s ease-out both; }
    .animate-fade-in      { animation: fadeIn .4s ease-out both; }
    .animate-float        { animation: float 6s ease-in-out infinite; }
    .animate-float-slow   { animation: floatSlow 9s ease-in-out infinite; }
    .animate-blob         { animation: blobShift 8s ease-in-out infinite; }
    .animate-spin-slow    { animation: spin-slow 20s linear infinite; }
    .anim-delay-100  { animation-delay: .10s; }
    .anim-delay-200  { animation-delay: .20s; }
    .anim-delay-300  { animation-delay: .30s; }
    .anim-delay-400  { animation-delay: .40s; }
    .anim-delay-600  { animation-delay: .60s; }
    .anim-delay-800  { animation-delay: .80s; }

    /* ── Gradient text ──────────────────────────────────────────── */
    .gradient-text {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .gradient-text-warm {
        background: linear-gradient(135deg, #f59e0b 0%, #ef4444 50%, #ec4899 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* ── Animated shimmer gradient ──────────────────────────────── */
    .shimmer-text {
        background: linear-gradient(90deg, #6366f1, #8b5cf6, #a855f7, #ec4899, #8b5cf6, #6366f1);
        background-size: 200% auto;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: shimmer 3s linear infinite;
    }

    /* ── Card glow ──────────────────────────────────────────────── */
    .card-glow:hover {
        box-shadow: 0 0 0 1px rgba(99,102,241,.2), 0 8px 32px rgba(99,102,241,.12);
    }
    .card-glow-warm:hover {
        box-shadow: 0 0 0 1px rgba(245,158,11,.2), 0 8px 32px rgba(245,158,11,.10);
    }

    /* ── Glass morphism (light) ─────────────────────────────────── */
    .glass-light {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.5);
    }
    .glass-light-sm {
        background: rgba(255,255,255,0.5);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.4);
    }

    /* ── Gradient button ────────────────────────────────────────── */
    .btn-gradient {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        background-size: 200% auto;
        transition: background-position .4s ease, transform .2s ease, box-shadow .2s ease;
        box-shadow: 0 4px 20px rgba(99,102,241,.35);
    }
    .btn-gradient:hover {
        background-position: right center;
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(99,102,241,.50);
    }

    /* ── Blob orb ───────────────────────────────────────────────── */
    .blob-orb {
        position: absolute;
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        filter: blur(60px);
        opacity: 0.35;
        pointer-events: none;
    }
</style>
