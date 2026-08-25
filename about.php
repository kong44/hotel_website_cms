<?php
/**
 * Indra Hotel - About Us Page
 * Story, Architecture, Leadership, Timeline Milestones, Gallery, and Philosophy
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

$defaultMetaTitle = 'About Our Sanctuary | ' . hotel_name() . ' Phnom Penh';
$defaultMetaDesc = 'Discover the story, architecture, leadership team, and tranquil hospitality of ' . hotel_name() . ' — an urban boutique sanctuary in Tuol Kork, Phnom Penh.';
$defaultMetaKeywords = hotel_name() . ', about boutique hotel phnom penh, tuol kork luxury hotel, cambodia boutique sanctuary, sustainable luxury phnom penh';

$pageMeta = [
    'title' => get_setting('about_seo_title', $defaultMetaTitle),
    'description' => get_setting('about_seo_description', $defaultMetaDesc),
    'keywords' => get_setting('about_seo_keywords', $defaultMetaKeywords),
    'image' => get_setting('home_hero_image', 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq'),
    'type' => 'website'
];

// Decode JSON arrays for dynamic sections
$teamJsonSaved = get_setting('about_team_members_json', '');
$teamMembers = !empty($teamJsonSaved) ? json_decode($teamJsonSaved, true) : [];

$timelineJsonSaved = get_setting('about_timeline_json', '');
$timelineMilestones = !empty($timelineJsonSaved) ? json_decode($timelineJsonSaved, true) : [];

$galleryJsonSaved = get_setting('about_gallery_photos_json', '');
$galleryPhotos = !empty($galleryJsonSaved) ? json_decode($galleryJsonSaved, true) : [];

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
                <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block"><?= e(get_setting('about_story_badge', 'The Indra Story')) ?></span>
                <h2 class="font-headline text-2xl sm:text-4xl font-bold text-stone-900 tracking-tight leading-snug">
                    <?= e(get_setting('about_story_title', 'Reimagining Boutique Hospitality with Intimacy and Soul')) ?>
                </h2>
                <div class="space-y-4 text-stone-600 text-sm sm:text-base leading-relaxed">
                    <?php 
                    $p1Default = "Nestled in the prestigious Tuol Kork district of Phnom Penh, <strong>" . e(hotel_name()) . "</strong> was founded with a singular vision: to create a tranquil sanctuary that offers effortless calm away from the energetic pulse of Cambodia's capital city.";
                    $p1 = get_setting('about_story_p1', '');
                    if (!empty($p1)): ?>
                        <p><?= nl2br(e($p1)) ?></p>
                    <?php else: ?>
                        <p><?= $p1Default ?></p>
                    <?php endif; ?>

                    <?php 
                    $p2Default = "With just 12 meticulously crafted luxury suites, our boutique hotel prioritizes personal attention, unhurried space, and privacy. Every suite features expansive private balconies, bespoke teak craftsmanship, and organic amenities crafted locally.";
                    $p2 = get_setting('about_story_p2', '');
                    if (!empty($p2)): ?>
                        <p><?= nl2br(e($p2)) ?></p>
                    <?php else: ?>
                        <p><?= $p2Default ?></p>
                    <?php endif; ?>

                    <?php 
                    $p3Default = "Whether you are visiting Phnom Penh for cultural exploration, business, or romantic leisure, our intuitive concierge team ensures your stay is seamless, authentic, and unforgettable.";
                    $p3 = get_setting('about_story_p3', '');
                    if (!empty($p3)): ?>
                        <p><?= nl2br(e($p3)) ?></p>
                    <?php else: ?>
                        <p><?= $p3Default ?></p>
                    <?php endif; ?>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-stone-200">
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]"><?= e(get_setting('about_stat1_val', '12')) ?></span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider"><?= e(get_setting('about_stat1_label', 'Luxury Suites')) ?></span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]"><?= e(get_setting('about_stat2_val', '100%')) ?></span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider"><?= e(get_setting('about_stat2_label', 'Saltwater Pool')) ?></span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]"><?= e(get_setting('about_stat3_val', '24/7')) ?></span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider"><?= e(get_setting('about_stat3_label', 'Front Desk')) ?></span>
                    </div>
                    <div class="p-4 bg-white rounded-xl border border-stone-200 text-center shadow-2xs">
                        <span class="block font-headline text-2xl sm:text-3xl font-bold text-[#343c0a]"><?= e(get_setting('about_stat4_val', '4.9★')) ?></span>
                        <span class="text-[11px] text-stone-500 font-medium uppercase tracking-wider"><?= e(get_setting('about_stat4_label', 'Guest Rating')) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Visual Gallery -->
            <div class="lg:col-span-5 relative">
                <div class="rounded-2xl overflow-hidden shadow-2xl border border-stone-200 aspect-[4/5] bg-stone-100">
                    <img src="<?= e(get_setting('about_story_image', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1000&q=80')) ?>" 
                         alt="<?= e(hotel_name()) ?> Atmosphere" 
                         class="w-full h-full object-cover">
                </div>
                <div class="hidden sm:block absolute -bottom-6 -left-6 bg-white p-6 rounded-xl border border-stone-200 shadow-xl max-w-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#dfe8a6] text-[#191e00] flex items-center justify-center font-bold shrink-0">
                            <span class="material-symbols-outlined text-xl"><?= e(get_setting('about_story_card_icon', 'verified')) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-stone-900"><?= e(get_setting('about_story_card_title', 'Direct Booking Guarantee')) ?></span>
                            <span class="text-[11px] text-stone-500"><?= e(get_setting('about_story_card_desc', 'Best rates, free upgrades & flexible cancellation.')) ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Core Pillars Section (Dynamic Builder) -->
<?php
$pillarsRaw = get_setting('about_pillars_json', '[]');
$aboutPillars = json_decode($pillarsRaw, true);
if (!is_array($aboutPillars) || empty($aboutPillars)) {
    $aboutPillars = [
        [
            'icon' => get_setting('about_pillar1_icon', 'nature_people'),
            'title' => get_setting('about_pillar1_title', 'Tranquil Urban Oasis'),
            'desc' => get_setting('about_pillar1_desc', 'Designed with lush tropical foliage, quiet courtyard reflection ponds, and natural acoustic insulation so you can unwind completely.')
        ],
        [
            'icon' => get_setting('about_pillar2_icon', 'restaurant'),
            'title' => get_setting('about_pillar2_title', 'Artisan Culinary Flavors'),
            'desc' => get_setting('about_pillar2_desc', 'From freshly brewed organic Cambodian specialty coffee to authentic Khmer dishes and refined international classics at The Bistro.')
        ],
        [
            'icon' => get_setting('about_pillar3_icon', 'loyalty'),
            'title' => get_setting('about_pillar3_title', 'Personalized Concierge'),
            'desc' => get_setting('about_pillar3_desc', 'Dedicated local recommendations, private chauffeur bookings, sunset river cruises, and tailored itineraries across Phnom Penh.')
        ]
    ];
}
$pillarColsClass = (count($aboutPillars) === 2) ? 'md:grid-cols-2' : ((count($aboutPillars) >= 4) ? 'md:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3');
?>
<section class="py-16 sm:py-20 bg-white border-t border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2"><?= e(get_setting('about_pillars_badge', 'Our Core Values')) ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">
                <?= e(get_setting('about_pillars_title', 'Crafted for Discerning Travelers')) ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 <?= $pillarColsClass ?> gap-8">
            <?php foreach ($aboutPillars as $p): ?>
            <div class="p-8 rounded-2xl bg-stone-50 border border-stone-200 space-y-4 transition hover:shadow-md">
                <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl"><?= e($p['icon'] ?? 'star') ?></span>
                </div>
                <h3 class="font-headline font-bold text-lg text-stone-900"><?= e($p['title'] ?? '') ?></h3>
                <p class="text-stone-600 text-xs sm:text-sm leading-relaxed">
                    <?= e($p['desc'] ?? '') ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Leadership Team Section (Optional) -->
<?php if (get_setting('about_show_team', '1') === '1' && !empty($teamMembers)): ?>
<section class="py-16 sm:py-24 bg-[#f9f9f9] border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2"><?= e(get_setting('about_team_badge', 'Hospitality Leadership')) ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">
                <?= e(get_setting('about_team_title', 'Meet Our Dedicated Concierge & Management')) ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($teamMembers as $m): ?>
            <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden space-y-4 group hover:shadow-md transition">
                <?php if (!empty($m['photo'])): ?>
                <div class="aspect-[4/3] overflow-hidden bg-stone-100">
                    <img src="<?= e($m['photo']) ?>" alt="<?= e($m['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                <?php endif; ?>
                <div class="p-6 space-y-2">
                    <span class="inline-block px-2.5 py-1 rounded bg-[#dfe8a6]/40 text-[#343c0a] font-bold text-[10px] uppercase tracking-wider"><?= e($m['role']) ?></span>
                    <h3 class="font-headline font-bold text-xl text-stone-900"><?= e($m['name']) ?></h3>
                    <?php if (!empty($m['bio'])): ?>
                    <p class="text-stone-600 text-xs sm:text-sm leading-relaxed"><?= e($m['bio']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($m['contact'])): ?>
                    <div class="pt-2 border-t border-stone-100 text-xs">
                        <a href="mailto:<?= e($m['contact']) ?>" class="text-[#343c0a] hover:underline font-semibold flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">mail</span>
                            <span><?= e($m['contact']) ?></span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Journey Timeline Section (Optional) -->
<?php if (get_setting('about_show_timeline', '1') === '1' && !empty($timelineMilestones)): ?>
<section class="py-16 sm:py-24 bg-white border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2"><?= e(get_setting('about_timeline_badge', 'Our Journey')) ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">
                <?= e(get_setting('about_timeline_title', 'Milestones of Excellence')) ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($timelineMilestones as $t): ?>
            <div class="p-6 bg-stone-50 rounded-2xl border border-stone-200 space-y-3 relative hover:bg-white hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="font-headline font-bold text-2xl text-[#343c0a]"><?= e($t['year']) ?></span>
                    <div class="w-9 h-9 rounded-lg bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-lg"><?= e($t['icon'] ?? 'flag') ?></span>
                    </div>
                </div>
                <h3 class="font-headline font-bold text-base text-stone-900"><?= e($t['title']) ?></h3>
                <p class="text-stone-600 text-xs leading-relaxed"><?= e($t['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Architectural & Atmosphere Gallery Section (Optional) -->
<?php if (get_setting('about_show_gallery', '1') === '1' && !empty($galleryPhotos)): ?>
<section class="py-16 sm:py-24 bg-[#f9f9f9] border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2"><?= e(get_setting('about_gallery_badge', 'Architectural Details')) ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">
                <?= e(get_setting('about_gallery_title', 'Designed for Serenity & Space')) ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($galleryPhotos as $g): ?>
            <div class="rounded-2xl overflow-hidden shadow-sm border border-stone-200 group bg-stone-100 aspect-[4/3] relative">
                <img src="<?= e($g['url']) ?>" alt="<?= e($g['caption'] ?? 'Gallery') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                <?php if (!empty($g['caption'])): ?>
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-4 text-white">
                    <span class="text-xs font-semibold block leading-tight"><?= e($g['caption']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call to Action -->
<section class="py-16 sm:py-24 bg-[#343c0a] text-white relative overflow-hidden">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 space-y-6">
        <h2 class="font-headline text-2xl sm:text-4xl font-bold tracking-tight">
            <?= e(get_setting('about_cta_title', 'Experience the Refinement of ' . hotel_name())) ?>
        </h2>
        <p class="max-w-2xl mx-auto text-stone-200 text-sm sm:text-base font-light">
            <?= e(get_setting('about_cta_desc', 'Reserve your suite directly on our official website for complimentary airport pick-up on stays of 3+ nights, priority check-in, and exclusive seasonal rates.')) ?>
        </p>
        <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
            <?php 
            $b1Text = get_setting('about_cta_btn1_text', 'Explore Rooms & Suites');
            $b1Url = get_setting('about_cta_btn1_url', '/rooms');
            $b2Text = get_setting('about_cta_btn2_text', 'Contact Concierge');
            $b2Url = get_setting('about_cta_btn2_url', '/contact');
            ?>
            <?php if (!empty($b1Text)): ?>
            <a href="<?= url($b1Url) ?>" class="px-6 py-3 rounded-lg bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold text-xs sm:text-sm tracking-wide transition shadow">
                <?= e($b1Text) ?>
            </a>
            <?php endif; ?>
            <?php if (!empty($b2Text)): ?>
            <a href="<?= url($b2Url) ?>" class="px-6 py-3 rounded-lg bg-white/10 hover:bg-white/20 text-white font-semibold text-xs sm:text-sm tracking-wide transition border border-white/20">
                <?= e($b2Text) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
