<?php
/**
 * Indra Hotel - About Us Page
 * Story, Architecture, Design, and Philosophy
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

$pageMeta = [
    'title' => 'About Our Sanctuary | ' . hotel_name() . ' Phnom Penh',
    'description' => 'Discover the story, architecture, and tranquil hospitality of ' . hotel_name() . ' — an urban boutique sanctuary in Tuol Kork, Phnom Penh.',
    'keywords' => hotel_name() . ', about boutique hotel phnom penh, tuol kork luxury hotel, cambodia boutique sanctuary, sustainable luxury phnom penh',
    'image' => get_setting('home_hero_image', 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq'),
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<?= render_public_page_hero('about', [
    'badge' => 'Our Heritage & Philosophy',
    'title' => 'A Contemporary Sanctuary in Phnom Penh',
    'subtitle' => 'Designed as an intimate urban retreat, ' . hotel_name() . ' seamlessly merges serene modernist architecture with warm Khmer hospitality.',
    'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
    'icon' => 'spa'
]) ?>


<!-- Breadcrumb -->
<div class="bg-white border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex items-center space-x-2 text-xs font-medium text-stone-500">
            <a href="<?= url('/home') ?>" class="hover:text-[#343c0a]">Home</a>
            <span class="text-stone-300">/</span>
            <span class="text-stone-900 font-semibold">About Us</span>
        </nav>
    </div>
</div>

<!-- Main Story Section -->
<section class="py-16 sm:py-24 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
            
            <!-- Left Narrative -->
            <div class="lg:col-span-7 space-y-6">
                <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block">The Indra Story</span>
                <h2 class="font-headline text-2xl sm:text-4xl font-bold text-stone-900 tracking-tight leading-snug">
                    Reimagining Boutique Hospitality with Intimacy and Soul
                </h2>
                <div class="space-y-4 text-stone-600 text-sm sm:text-base leading-relaxed">
                    <p>
                        Nestled in the prestigious Tuol Kork district of Phnom Penh, <strong><?= e(hotel_name()) ?></strong> was founded with a singular vision: to create a tranquil sanctuary that offers effortless calm away from the energetic pulse of Cambodia's capital city.
                    </p>
                    <p>
                        With just 12 meticulously crafted luxury suites, our boutique hotel prioritizes personal attention, unhurried space, and privacy. Every suite features expansive private balconies, bespoke teak craftsmanship, and organic amenities crafted locally.
                    </p>
                    <p>
                        Whether you are visiting Phnom Penh for cultural exploration, business, or romantic leisure, our intuitive concierge team ensures your stay is seamless, authentic, and unforgettable.
                    </p>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-stone-200">
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]">12</span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider">Luxury Suites</span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]">100%</span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider">Saltwater Pool</span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]">24/7</span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider">Front Desk</span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]">4.9★</span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider">Guest Rating</span>
                    </div>
                </div>
            </div>

            <!-- Right Visual Gallery -->
            <div class="lg:col-span-5 relative">
                <div class="rounded-2xl overflow-hidden shadow-2xl border border-stone-200 aspect-[4/5] bg-stone-100">
                    <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1000&q=80" 
                         alt="Indra Hotel Atmosphere" 
                         class="w-full h-full object-cover">
                </div>
                <div class="hidden sm:block absolute -bottom-6 -left-6 bg-white p-6 rounded-xl border border-stone-200 shadow-xl max-w-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#dfe8a6] text-[#191e00] flex items-center justify-center font-bold shrink-0">
                            <span class="material-symbols-outlined text-xl">verified</span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-stone-900">Direct Booking Guarantee</span>
                            <span class="text-[11px] text-stone-500">Best rates, free upgrades & flexible cancellation.</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Core Pillars Section -->
<section class="py-16 sm:py-20 bg-white border-t border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2">Our Core Values</span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">
                Crafted for Discerning Travelers
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Pillar 1 -->
            <div class="p-8 rounded-2xl bg-stone-50 border border-stone-200 space-y-4 transition hover:shadow-md">
                <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">nature_people</span>
                </div>
                <h3 class="font-headline font-bold text-lg text-stone-900">Tranquil Urban Oasis</h3>
                <p class="text-stone-600 text-xs sm:text-sm leading-relaxed">
                    Designed with lush tropical foliage, quiet courtyard reflection ponds, and natural acoustic insulation so you can unwind completely.
                </p>
            </div>

            <!-- Pillar 2 -->
            <div class="p-8 rounded-2xl bg-stone-50 border border-stone-200 space-y-4 transition hover:shadow-md">
                <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">restaurant</span>
                </div>
                <h3 class="font-headline font-bold text-lg text-stone-900">Artisan Culinary Flavors</h3>
                <p class="text-stone-600 text-xs sm:text-sm leading-relaxed">
                    From freshly brewed organic Cambodian specialty coffee to authentic Khmer dishes and refined international classics at The Bistro.
                </p>
            </div>

            <!-- Pillar 3 -->
            <div class="p-8 rounded-2xl bg-stone-50 border border-stone-200 space-y-4 transition hover:shadow-md">
                <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">loyalty</span>
                </div>
                <h3 class="font-headline font-bold text-lg text-stone-900">Personalized Concierge</h3>
                <p class="text-stone-600 text-xs sm:text-sm leading-relaxed">
                    Dedicated local recommendations, private chauffeur bookings, sunset river cruises, and tailored itineraries across Phnom Penh.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-16 sm:py-24 bg-[#343c0a] text-white relative overflow-hidden">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 space-y-6">
        <h2 class="font-headline text-2xl sm:text-4xl font-bold tracking-tight">
            Experience the Refinement of <?= e(hotel_name()) ?>
        </h2>
        <p class="max-w-2xl mx-auto text-stone-200 text-sm sm:text-base font-light">
            Reserve your suite directly on our official website for complimentary airport pick-up on stays of 3+ nights, priority check-in, and exclusive seasonal rates.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
            <a href="<?= url('/rooms') ?>" class="px-6 py-3 rounded-lg bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold text-xs sm:text-sm tracking-wide transition shadow">
                Explore Rooms & Suites
            </a>
            <a href="<?= url('/contact') ?>" class="px-6 py-3 rounded-lg bg-white/10 hover:bg-white/20 text-white font-semibold text-xs sm:text-sm tracking-wide transition border border-white/20">
                Contact Concierge
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
