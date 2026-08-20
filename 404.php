<?php
/**
 * Indra Hotel - 404 Page Not Found
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pageMeta = [
    'title' => 'Page Not Found (404) | ' . hotel_name(),
    'description' => 'The page you are looking for does not exist or has been moved.',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-24 sm:py-32 bg-[#f9f9f9] flex items-center justify-center min-h-[60vh]">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
        <div class="w-20 h-20 mx-auto rounded-full bg-[#dfe8a6]/40 text-[#343c0a] flex items-center justify-center font-bold">
            <span class="material-symbols-outlined text-4xl">travel_explore</span>
        </div>
        
        <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block">404 Error</span>
        <h1 class="font-headline text-3xl sm:text-5xl font-bold text-stone-900 tracking-tight">
            Page Not Found
        </h1>
        
        <p class="text-stone-600 text-sm sm:text-base leading-relaxed max-w-md mx-auto">
            The sanctuary space you are looking for may have been relocated or is temporarily unavailable.
        </p>

        <div class="flex flex-wrap items-center justify-center gap-3 pt-4">
            <a href="<?= url('/home') ?>" class="px-6 py-3 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white font-bold text-xs tracking-wide transition shadow flex items-center gap-2">
                <span class="material-symbols-outlined text-base">home</span>
                <span>Return to Homepage</span>
            </a>
            <a href="<?= url('/rooms') ?>" class="px-6 py-3 rounded-lg bg-white hover:bg-stone-50 text-stone-800 font-semibold text-xs tracking-wide transition border border-stone-300 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">bed</span>
                <span>View Accommodations</span>
            </a>
            <a href="<?= url('/contact') ?>" class="px-6 py-3 rounded-lg bg-white hover:bg-stone-50 text-stone-800 font-semibold text-xs tracking-wide transition border border-stone-300 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">mail</span>
                <span>Contact Us</span>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
