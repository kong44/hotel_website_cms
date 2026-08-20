<?php
/**
 * Indra Hotel - Centralized Page Heroes & Banners CMS Manager
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$adminTitle = 'Page Heroes & Banners Manager';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try submitting again.');
    } else {
        process_hero_settings_post();
        set_flash('success', 'Page Heroes & Banner settings saved successfully.');
    }
    header('Location: ' . BASE_URL . '/admin/page-heroes.php');
    exit;
}

$pageHeroesList = [
    'home' => [
        'title' => 'Homepage',
        'icon' => 'home',
        'defaults' => [
            'badge' => 'Boutique Luxury Sanctuary',
            'title' => 'Where Modern Sophistication Meets Khmer Grace',
            'subtitle' => 'An urban boutique retreat in Tuol Kork, Phnom Penh offering serene architectural luxury and organic hospitality.',
            'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'about' => [
        'title' => 'About Us',
        'icon' => 'info',
        'defaults' => [
            'badge' => 'Our Heritage & Philosophy',
            'title' => 'A Contemporary Sanctuary in Phnom Penh',
            'subtitle' => 'Designed as an intimate urban retreat, Indra Hotel seamlessly merges serene modernist architecture with warm Khmer hospitality.',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'rooms' => [
        'title' => 'Rooms & Suites Catalog',
        'icon' => 'bed',
        'defaults' => [
            'badge' => 'Contemporary Sanctuary',
            'title' => 'Rooms, Suites & Spaces',
            'subtitle' => 'Thoughtfully designed accommodations, luxury suites, and executive conference spaces catering to discerning travelers.',
            'image' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'dining' => [
        'title' => 'Dining & Bistro (Eat & Drink)',
        'icon' => 'restaurant',
        'defaults' => [
            'badge' => 'Culinary Artistry',
            'title' => 'Eat & Drink',
            'subtitle' => 'From artisanal morning coffee to sunset cocktails and fine Asian-fusion dining, indulge in an elevated gastronomic atmosphere.',
            'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'wellness' => [
        'title' => 'Gym, Pool & Wellness',
        'icon' => 'fitness_center',
        'defaults' => [
            'badge' => 'Rejuvenation & Mindful Living',
            'title' => 'Gym, Pool & Wellness',
            'subtitle' => 'Revitalize your body and mind in our state-of-the-art fitness center and serene outdoor saltwater swimming pool.',
            'image' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'offers' => [
        'title' => 'Special Offers & Packages',
        'icon' => 'local_offer',
        'defaults' => [
            'badge' => 'Exclusive Privilege',
            'title' => 'Special Offers & Packages',
            'subtitle' => 'Curated stay packages, seasonal discounts, and exclusive direct booking privileges at Indra Hotel.',
            'image' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'gallery' => [
        'title' => 'Photo Gallery',
        'icon' => 'photo_library',
        'defaults' => [
            'badge' => 'Visual Showcase',
            'title' => 'Photo Gallery',
            'subtitle' => 'Immerse yourself in the serene architecture, contemporary rooms, and world-class amenities of Indra Hotel.',
            'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'location' => [
        'title' => 'Prime Location & Guide',
        'icon' => 'pin_drop',
        'defaults' => [
            'badge' => 'Prime Tuol Kork Setting',
            'title' => 'Location & City Guide',
            'subtitle' => 'Nestled in the upscale, tranquil district of Tuol Kork with effortless connectivity to Phnom Penh\'s premier cultural landmarks.',
            'image' => 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ],
    'contact' => [
        'title' => 'Contact Us',
        'icon' => 'mail',
        'defaults' => [
            'badge' => 'We Are Here For You',
            'title' => 'Contact Sanctuary',
            'subtitle' => 'Have a question regarding reservations, dining, or special requests? Reach out to our dedicated concierge.',
            'image' => 'https://images.unsplash.com/photo-1596524430615-b46475ddff6e?auto=format&fit=crop&w=1600&q=80',
            'video' => ''
        ]
    ]
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">CMS Content</span>
                <span class="text-xs text-stone-400">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Page Heroes</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-3xl text-[#343c0a]">view_day</span>
                <span>Page Heroes & Banners Manager</span>
            </h1>
            <p class="text-xs text-stone-500 mt-1">Configure titles, slogans, background imagery, and ambient video loops for all public pages.</p>
        </div>

        <button type="submit" form="page-heroes-form" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2 cursor-pointer">
            <span class="material-symbols-outlined text-base">save</span>
            <span>Save All Page Heroes</span>
        </button>
    </div>

    <!-- Quick Navigation Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 text-xs font-semibold">
        <span class="text-stone-400 uppercase text-[10px] tracking-wider shrink-0 pr-1">Jump To Page:</span>
        <?php foreach ($pageHeroesList as $pKey => $pMeta): ?>
        <a href="#hero-section-<?= $pKey ?>" class="px-3 py-1.5 rounded-lg border border-stone-200 bg-white text-stone-700 hover:bg-stone-100 transition shrink-0 flex items-center gap-1.5 shadow-2xs">
            <span class="material-symbols-outlined text-sm text-[#343c0a]"><?= e($pMeta['icon']) ?></span>
            <span><?= e($pMeta['title']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <form id="page-heroes-form" action="<?= BASE_URL ?>/admin/page-heroes.php" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

        <?php foreach ($pageHeroesList as $pageKey => $pageMeta): ?>
        <div id="hero-section-<?= $pageKey ?>" class="scroll-mt-24">
            <?= render_admin_hero_editor_card($pageKey, $pageMeta['title'], $pageMeta['defaults']) ?>
        </div>
        <?php endforeach; ?>

        <div class="pt-4 border-t border-stone-200 flex justify-end">
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-3 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-lg">save</span>
                <span>Save All Page Heroes</span>
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
