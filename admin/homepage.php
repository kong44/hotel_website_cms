<?php
/**
 * Indra Hotel - Homepage Content Management System (SoftBook CMS)
 * Allows customizing Hero (Image/Video Background), Story, KPI Counters, Rooms Showcase, Dining & Wellness Banners, Location & SEO.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploader.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'Homepage Content Management';

// Handle POST Save Action
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed. Please try again.');
        header('Location: ' . BASE_URL . '/admin/homepage.php');
        exit;
    }

    $activeTab = trim($_POST['active_tab'] ?? 'hero');

    // List of all homepage configurable settings
    $settingsMap = [
        // Tab 1: Hero & Search Bar
        'home_hero_media_type' => in_array($_POST['home_hero_media_type'] ?? '', ['image', 'video'], true) ? $_POST['home_hero_media_type'] : 'image',
        'home_hero_image' => trim($_POST['home_hero_image'] ?? ''),
        'home_hero_video_url' => trim($_POST['home_hero_video_url'] ?? ''),
        'home_hero_overlay' => in_array($_POST['home_hero_overlay'] ?? '', ['soft', 'medium', 'dark', 'deep'], true) ? $_POST['home_hero_overlay'] : 'dark',
        'home_hero_badge' => trim($_POST['home_hero_badge'] ?? 'Boutique Luxury Sanctuary'),
        'home_hero_title' => trim($_POST['home_hero_title'] ?? 'Welcome to Indra Hotel'),
        'home_hero_subtitle' => trim($_POST['home_hero_subtitle'] ?? ''),
        'home_hero_ken_burns' => isset($_POST['home_hero_ken_burns']) ? '1' : '0',
        'home_hero_show_search' => isset($_POST['home_hero_show_search']) ? '1' : '0',
        'home_hero_search_btn_text' => trim($_POST['home_hero_search_btn_text'] ?? 'Check Rates'),

        // Tab 2: Philosophy, Story & Stats
        'home_stats_json' => trim($_POST['home_stats_json'] ?? '[]'),
        'home_amenities_json' => trim($_POST['home_amenities_json'] ?? '[]'),
        'home_story_badge' => trim($_POST['home_story_badge'] ?? 'The Indra Philosophy'),
        'home_story_title' => trim($_POST['home_story_title'] ?? 'A Serene Sanctuary in the Heart of Phnom Penh'),
        'home_story_desc1' => trim($_POST['home_story_desc1'] ?? ''),
        'home_story_desc2' => trim($_POST['home_story_desc2'] ?? ''),
        'home_story_image' => trim($_POST['home_story_image'] ?? ''),
        'home_story_image_badge' => trim($_POST['home_story_image_badge'] ?? 'Authentic Hospitality'),
        'home_story_image_title' => trim($_POST['home_story_image_title'] ?? 'Designed for Unrivaled Comfort & Tranquility'),
        
        // 3 Animated Statistics
        'home_stat1_val' => trim($_POST['home_stat1_val'] ?? '12'),
        'home_stat1_suffix' => trim($_POST['home_stat1_suffix'] ?? ' Suites'),
        'home_stat1_label' => trim($_POST['home_stat1_label'] ?? 'Boutique Sanctuary'),
        
        'home_stat2_val' => trim($_POST['home_stat2_val'] ?? '15'),
        'home_stat2_suffix' => trim($_POST['home_stat2_suffix'] ?? '% Off'),
        'home_stat2_label' => trim($_POST['home_stat2_label'] ?? 'Direct Privilege'),
        
        'home_stat3_val' => trim($_POST['home_stat3_val'] ?? '10'),
        'home_stat3_suffix' => trim($_POST['home_stat3_suffix'] ?? ' Mins'),
        'home_stat3_label' => trim($_POST['home_stat3_label'] ?? 'To Royal Palace'),

        // 4 Quick Amenities
        'home_amenity1_icon' => trim($_POST['home_amenity1_icon'] ?? 'pool'),
        'home_amenity1_text' => trim($_POST['home_amenity1_text'] ?? 'Saltwater Pool'),
        'home_amenity2_icon' => trim($_POST['home_amenity2_icon'] ?? 'fitness_center'),
        'home_amenity2_text' => trim($_POST['home_amenity2_text'] ?? 'Fitness Center'),
        'home_amenity3_icon' => trim($_POST['home_amenity3_icon'] ?? 'restaurant'),
        'home_amenity3_text' => trim($_POST['home_amenity3_text'] ?? 'Fine Bistro & Cafe'),
        'home_amenity4_icon' => trim($_POST['home_amenity4_icon'] ?? 'wifi'),
        'home_amenity4_text' => trim($_POST['home_amenity4_text'] ?? 'High-Speed Wi-Fi'),

        // Tab 3: Accommodations Showcase
        'home_rooms_badge' => trim($_POST['home_rooms_badge'] ?? 'Refined Living'),
        'home_rooms_title' => trim($_POST['home_rooms_title'] ?? 'Accommodations & Suites'),
        'home_rooms_subtitle' => trim($_POST['home_rooms_subtitle'] ?? ''),
        'home_rooms_limit' => in_array($_POST['home_rooms_limit'] ?? '', ['3', '6', '9', '12'], true) ? $_POST['home_rooms_limit'] : '3',
        'home_rooms_btn_text' => trim($_POST['home_rooms_btn_text'] ?? 'View All Accommodations'),

        // Tab 4: Dining & Wellness Highlights
        'home_dw_banners_json' => trim($_POST['home_dw_banners_json'] ?? '[]'),

        'home_dining_badge' => trim($_POST['home_dining_badge'] ?? 'Culinary Journey'),
        'home_dining_title' => trim($_POST['home_dining_title'] ?? 'The Bistro & Artisanal Cafe'),
        'home_dining_desc' => trim($_POST['home_dining_desc'] ?? ''),
        'home_dining_image' => trim($_POST['home_dining_image'] ?? ''),
        'home_dining_btn_text' => trim($_POST['home_dining_btn_text'] ?? 'Explore Dining Menus'),
        'home_dining_btn_url' => trim($_POST['home_dining_btn_url'] ?? 'eat-drink.php'),

        'home_wellness_badge' => trim($_POST['home_wellness_badge'] ?? 'Health & Vitality'),
        'home_wellness_title' => trim($_POST['home_wellness_title'] ?? 'Fitness Center, Pool & Spa'),
        'home_wellness_desc' => trim($_POST['home_wellness_desc'] ?? ''),
        'home_wellness_image' => trim($_POST['home_wellness_image'] ?? ''),
        'home_wellness_btn_text' => trim($_POST['home_wellness_btn_text'] ?? 'Discover Wellness'),
        'home_wellness_btn_url' => trim($_POST['home_wellness_btn_url'] ?? 'wellness.php'),

        // Tab 5: Location Section
        'location_badge' => trim($_POST['location_badge'] ?? 'Prime Location'),
        'location_title' => trim($_POST['location_title'] ?? 'Explore Phnom Penh from Tuol Kork'),
        'location_subtitle' => trim($_POST['location_subtitle'] ?? ''),
        'location_btn_text' => trim($_POST['location_btn_text'] ?? 'View Full City & Location Guide'),

        // Tab 6: Homepage SEO Meta
        'home_seo_title' => trim($_POST['home_seo_title'] ?? 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh'),
        'home_seo_desc' => trim($_POST['home_seo_desc'] ?? ''),
        'home_seo_keywords' => trim($_POST['home_seo_keywords'] ?? ''),
        'home_seo_og_image' => trim($_POST['home_seo_og_image'] ?? '')
    ];

    // Upsert into site_settings
    $stmtUpsert = (Database::getDriver() === 'sqlite')
        ? $pdo->prepare("INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)")
        : $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settingsMap as $k => $v) {
        $stmtUpsert->execute([$k, $v]);
    }

    get_setting('__refresh__');
    set_flash('success', 'Homepage content updated successfully! Changes are live on the website.');
    header('Location: ' . BASE_URL . '/admin/homepage.php#' . urlencode($activeTab));
    exit;
}

// Defaults for Initial Settings
$defaultHeroImage = 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq';
$defaultStoryImage = 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho';
$defaultDiningImage = 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho';
$defaultWellnessImage = 'https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">

    <!-- Header & Action Preview -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Visual Page Management</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Homepage Content Editor</h1>
            <p class="text-xs text-stone-500 mt-1">Customize the public homepage: Hero background (video/image), narrative story, statistics counters, room showcase, dining & wellness banners, and SEO metadata.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-xs font-bold bg-stone-100 hover:bg-stone-200 text-stone-700 transition border border-stone-300">
                <span class="material-symbols-outlined text-base">open_in_new</span>
                <span>View Live Homepage</span>
            </a>
            <button type="button" onclick="document.getElementById('homepage-content-form').submit()" class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">save</span>
                <span>Save All Changes</span>
            </button>
        </div>
    </div>

    <!-- Main Editor Form -->
    <form id="homepage-content-form" action="<?= BASE_URL ?>/admin/homepage.php" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="active_tab" id="active_tab_input" value="hero">

        <!-- Navigation Tabs Bar -->
        <div class="bg-white rounded-2xl border border-stone-200 shadow-sm p-2 flex flex-wrap gap-1">
            <button type="button" onclick="switchHomeTab('hero')" id="tab-btn-hero" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer bg-[#343c0a] text-white">
                <span class="material-symbols-outlined text-base">videocam</span>
                <span>1. Hero & Background Media</span>
            </button>

            <button type="button" onclick="switchHomeTab('story')" id="tab-btn-story" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer text-stone-600 hover:bg-stone-100">
                <span class="material-symbols-outlined text-base">auto_stories</span>
                <span>2. Story, Stats & Amenities</span>
            </button>

            <button type="button" onclick="switchHomeTab('rooms')" id="tab-btn-rooms" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer text-stone-600 hover:bg-stone-100">
                <span class="material-symbols-outlined text-base">bed</span>
                <span>3. Featured Accommodations</span>
            </button>

            <button type="button" onclick="switchHomeTab('experiences')" id="tab-btn-experiences" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer text-stone-600 hover:bg-stone-100">
                <span class="material-symbols-outlined text-base">restaurant</span>
                <span>4. Dining & Wellness Banners</span>
            </button>

            <button type="button" onclick="switchHomeTab('location')" id="tab-btn-location" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer text-stone-600 hover:bg-stone-100">
                <span class="material-symbols-outlined text-base">explore</span>
                <span>5. Prime Location</span>
            </button>

            <button type="button" onclick="switchHomeTab('seo')" id="tab-btn-seo" class="home-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer text-stone-600 hover:bg-stone-100">
                <span class="material-symbols-outlined text-base">search</span>
                <span>6. Homepage SEO Meta</span>
            </button>
        </div>

        <!-- =======================================================
             TAB 1: Hero Section & Background Media (Image / Video)
             ======================================================= -->
        <div id="tab-content-hero" class="home-tab-content bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="border-b border-stone-200 pb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320]">play_circle</span>
                        <span>Hero Section & Background Media</span>
                    </h2>
                    <p class="text-xs text-stone-500">Configure the top hero banner with high-resolution image or ambient luxury background video.</p>
                </div>
                <span class="text-xs font-bold px-3 py-1 rounded-full bg-[#dfe8a6]/40 text-[#343c0a]">Top Section</span>
            </div>

            <!-- Media Type Selector -->
            <div class="bg-stone-50 p-5 rounded-2xl border border-stone-200 space-y-3">
                <label class="block font-bold text-xs uppercase tracking-wider text-stone-700">Hero Background Media Type</label>
                <?php $mediaType = get_setting('home_hero_media_type', 'image'); ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition <?= $mediaType === 'image' ? 'border-[#343c0a] bg-white shadow-sm' : 'border-stone-200 bg-stone-100/60 hover:bg-white' ?>" id="label_media_image">
                        <input type="radio" name="home_hero_media_type" value="image" <?= $mediaType === 'image' ? 'checked' : '' ?> onchange="toggleHeroMediaType('image')" class="text-[#343c0a] focus:ring-[#343c0a]">
                        <div>
                            <span class="font-bold text-xs text-onyx-charcoal block">High-Resolution Background Image</span>
                            <span class="text-[11px] text-stone-500">Static photo with optional Ken Burns ambient zoom animation.</span>
                        </div>
                    </label>

                    <label class="relative flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition <?= $mediaType === 'video' ? 'border-[#343c0a] bg-white shadow-sm' : 'border-stone-200 bg-stone-100/60 hover:bg-white' ?>" id="label_media_video">
                        <input type="radio" name="home_hero_media_type" value="video" <?= $mediaType === 'video' ? 'checked' : '' ?> onchange="toggleHeroMediaType('video')" class="text-[#343c0a] focus:ring-[#343c0a]">
                        <div>
                            <span class="font-bold text-xs text-onyx-charcoal block">Ambient Luxury Video Background</span>
                            <span class="text-[11px] text-stone-500">Looping MP4/WebM video stream or YouTube embed with muted autoplay.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Video Configuration (Shown when Video is Selected) -->
            <div id="hero_video_box" class="<?= $mediaType === 'video' ? '' : 'hidden' ?> space-y-2">
                <?= render_video_uploader_field('home_hero_video_url', get_setting('home_hero_video_url', ''), 'Hero Video Background File / URL', 'videos', [
                    'helper' => 'Upload MP4, WebM, or MOV video file (up to 100MB) or paste direct video / YouTube URL'
                ]) ?>
            </div>

            <!-- Image Uploader (Used as Main Hero Image OR Video Fallback Poster) -->
            <div>
                <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-2">
                    <span id="hero_image_label_text"><?= $mediaType === 'video' ? 'Hero Video Fallback Poster Photo *' : 'Hero Background Image *' ?></span>
                </label>
                <?= render_image_uploader_field('home_hero_image', get_setting('home_hero_image', $defaultHeroImage), 'Hero Photo', 'general', [
                    'helper' => 'High-resolution photo (1920x1080px or higher recommended)'
                ]) ?>
            </div>

            <!-- Animation & Darkness Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                <!-- Overlay Darkness -->
                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Darkness Tint Gradient</label>
                    <?php $overlay = get_setting('home_hero_overlay', 'dark'); ?>
                    <select name="home_hero_overlay" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-semibold">
                        <option value="soft" <?= $overlay === 'soft' ? 'selected' : '' ?>>Soft (40% darkness) — Bright imagery</option>
                        <option value="medium" <?= $overlay === 'medium' ? 'selected' : '' ?>>Medium (60% darkness) — Balanced</option>
                        <option value="dark" <?= $overlay === 'dark' ? 'selected' : '' ?>>Dark (75% darkness) — Recommended for text readability</option>
                        <option value="deep" <?= $overlay === 'deep' ? 'selected' : '' ?>>Deep (85% darkness) — High contrast</option>
                    </select>
                </div>

                <!-- Ken Burns Effect -->
                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Ambient Motion</label>
                    <label class="flex items-center gap-3 p-2.5 rounded-lg border border-stone-300 bg-stone-50 cursor-pointer">
                        <input type="checkbox" name="home_hero_ken_burns" value="1" <?= get_setting('home_hero_ken_burns', '1') === '1' ? 'checked' : '' ?> class="rounded text-[#343c0a] focus:ring-[#343c0a]">
                        <span class="text-xs font-semibold text-stone-800">Enable Ken Burns Slow Zoom Motion</span>
                    </label>
                </div>
            </div>

            <!-- Hero Headlines & Copy -->
            <div class="space-y-4 pt-4 border-t border-stone-200">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1">
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Hero Pill Badge</label>
                        <input type="text" name="home_hero_badge" value="<?= e(get_setting('home_hero_badge', 'Boutique Luxury Sanctuary')) ?>" 
                               class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Main Heading (H1) *</label>
                        <input type="text" name="home_hero_title" value="<?= e(get_setting('home_hero_title', 'Welcome to Indra Hotel')) ?>" 
                               class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold focus:ring-2 focus:ring-[#343c0a]">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Hero Subtitle / Narrative Lead</label>
                    <textarea name="home_hero_subtitle" rows="3" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('home_hero_subtitle', 'Just minutes from bustling Phnom Penh, experience the refined elegance and tranquil comfort of our 12-room contemporary boutique retreat in Tuol Kork.')) ?></textarea>
                </div>
            </div>

            <!-- Quick Booking Bar Widget Options -->
            <div class="p-5 bg-stone-50 rounded-2xl border border-stone-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-bold text-xs text-onyx-charcoal block">Hero Quick Booking Bar Widget</span>
                        <span class="text-[11px] text-stone-500">Allow visitors to select dates and check real-time availability directly from the hero.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="home_hero_show_search" value="1" <?= get_setting('home_hero_show_search', '1') === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Search Button Label</label>
                    <input type="text" name="home_hero_search_btn_text" value="<?= e(get_setting('home_hero_search_btn_text', 'Check Rates')) ?>" class="w-full sm:w-80 border border-stone-300 rounded-lg p-2 text-xs">
                </div>
            </div>

        </div>

        <!-- =======================================================
             TAB 2: Philosophy, Story & Interactive Statistics
             ======================================================= -->
        <div id="tab-content-story" class="home-tab-content hidden bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-8">
            
            <div class="border-b border-stone-200 pb-4">
                <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">auto_stories</span>
                    <span>Philosophy Narrative & Interactive Counters</span>
                </h2>
                <p class="text-xs text-stone-500">Introduce the story of Indra Hotel, highlight key statistics, and showcase top amenities.</p>
            </div>

            <!-- Narrative Headings & Copy -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Badge</label>
                        <input type="text" name="home_story_badge" value="<?= e(get_setting('home_story_badge', 'The Indra Philosophy')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Title *</label>
                        <input type="text" name="home_story_title" value="<?= e(get_setting('home_story_title', 'A Serene Sanctuary in the Heart of Phnom Penh')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Paragraph 1 (Main Narrative)</label>
                        <textarea name="home_story_desc1" rows="4" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs"><?= e(get_setting('home_story_desc1', 'Nestled in a green and eco-friendly setting in Tuol Kork, Indra Hotel exudes sustainable charm and bespoke hospitality. Our dedicated team ensures top-notch personalized service and an intimate, cozy atmosphere.')) ?></textarea>
                    </div>
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Paragraph 2 (Amenities & Location)</label>
                        <textarea name="home_story_desc2" rows="4" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs"><?= e(get_setting('home_story_desc2', 'The hotel boasts a charming artisanal cafe, fully equipped fitness center, refreshing outdoor saltwater swimming pool, and fine dining experiences with exquisite city views. Shopping centers, local markets, Wat Phnom, and the Royal Palace are all just minutes away.')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Story Feature Photo & Overlay Badge -->
            <div class="p-6 bg-stone-50 rounded-2xl border border-stone-200 space-y-4">
                <span class="font-bold text-xs text-onyx-charcoal uppercase tracking-wider block">Story Feature Photo</span>
                <?= render_image_uploader_field('home_story_image', get_setting('home_story_image', $defaultStoryImage), 'Story Feature Image', 'general') ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Photo Overlay Badge</label>
                        <input type="text" name="home_story_image_badge" value="<?= e(get_setting('home_story_image_badge', 'Authentic Hospitality')) ?>" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Photo Overlay Caption</label>
                        <input type="text" name="home_story_image_title" value="<?= e(get_setting('home_story_image_title', 'Designed for Unrivaled Comfort & Tranquility')) ?>" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                    </div>
                </div>
            </div>

            <!-- Dynamic KPI Statistics Counters Builder -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320] text-lg">analytics</span>
                        <h3 class="font-headline font-bold text-base text-onyx-charcoal">KPI Statistics Counters</h3>
                    </div>
                    <button type="button" onclick="addHomeStatRow()" class="inline-flex items-center gap-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold px-3 py-1.5 rounded-lg transition shadow-2xs cursor-pointer">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Add Stat Counter</span>
                    </button>
                </div>

                <input type="hidden" name="home_stats_json" id="home_stats_json" value="<?= e(get_setting('home_stats_json', '[]')) ?>">

                <div id="home_stats_container" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Dynamic Stat Items rendered by JS -->
                </div>
            </div>

            <!-- Dynamic Quick Highlighted Amenities Builder -->
            <div class="space-y-4 pt-4 border-t border-stone-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320] text-lg">checklist</span>
                        <h3 class="font-headline font-bold text-base text-onyx-charcoal">Highlighted Quick Amenities</h3>
                    </div>
                    <button type="button" onclick="addHomeAmenityRow()" class="inline-flex items-center gap-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold px-3 py-1.5 rounded-lg transition shadow-2xs cursor-pointer">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Add Amenity</span>
                    </button>
                </div>

                <input type="hidden" name="home_amenities_json" id="home_amenities_json" value="<?= e(get_setting('home_amenities_json', '[]')) ?>">

                <div id="home_amenities_container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Dynamic Amenity Items rendered by JS -->
                </div>
            </div>

        </div>

        <!-- =======================================================
             TAB 3: Featured Accommodations Showcase
             ======================================================= -->
        <div id="tab-content-rooms" class="home-tab-content hidden bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="border-b border-stone-200 pb-4">
                <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">bed</span>
                    <span>Featured Accommodations Showcase</span>
                </h2>
                <p class="text-xs text-stone-500">Configure the rooms carousel/grid heading and how many suites are showcased on the homepage.</p>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Badge</label>
                        <input type="text" name="home_rooms_badge" value="<?= e(get_setting('home_rooms_badge', 'Refined Living')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Title *</label>
                        <input type="text" name="home_rooms_title" value="<?= e(get_setting('home_rooms_title', 'Accommodations & Suites')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Subtitle</label>
                    <textarea name="home_rooms_subtitle" rows="2" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs"><?= e(get_setting('home_rooms_subtitle', 'With modern and functional decor, our 12 spacious accommodations cater to the needs of all travelers.')) ?></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Number of Rooms to Display</label>
                        <?php $limit = get_setting('home_rooms_limit', '3'); ?>
                        <select name="home_rooms_limit" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-semibold">
                            <option value="3" <?= $limit === '3' ? 'selected' : '' ?>>3 Rooms (Standard Row)</option>
                            <option value="6" <?= $limit === '6' ? 'selected' : '' ?>>6 Rooms (2 Rows)</option>
                            <option value="9" <?= $limit === '9' ? 'selected' : '' ?>>9 Rooms</option>
                            <option value="12" <?= $limit === '12' ? 'selected' : '' ?>>12 Rooms (All Suites)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">"View All" Button Label</label>
                        <input type="text" name="home_rooms_btn_text" value="<?= e(get_setting('home_rooms_btn_text', 'View All Accommodations')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                    </div>
                </div>
            </div>

        </div>

        <!-- =======================================================
             TAB 4: Dining & Wellness Highlights Banners (Items Builder)
             ======================================================= -->
        <div id="tab-content-experiences" class="home-tab-content hidden bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                <div>
                    <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320]">restaurant</span>
                        <span>Dining & Wellness Experience Banners</span>
                    </h2>
                    <p class="text-xs text-stone-500 mt-0.5">Manage split feature banners promoting dining, spa, pool, and lifestyle experiences on the homepage.</p>
                </div>
                <button type="button" onclick="addHomeDwBannerRow()" class="inline-flex items-center gap-1.5 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-2xs cursor-pointer">
                    <span class="material-symbols-outlined text-sm">add</span>
                    <span>Add Experience Banner</span>
                </button>
            </div>

            <input type="hidden" name="home_dw_banners_json" id="home_dw_banners_json" value="<?= e(get_setting('home_dw_banners_json', '[]')) ?>">

            <div id="home_dw_banners_container" class="space-y-6">
                <!-- Dynamic Banner Cards rendered by JS -->
            </div>
        </div>

        <!-- =======================================================
             TAB 5: Prime Location Section
             ======================================================= -->
        <div id="tab-content-location" class="home-tab-content hidden bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="border-b border-stone-200 pb-4">
                <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">pin_drop</span>
                    <span>Prime Location Highlights</span>
                </h2>
                <p class="text-xs text-stone-500">Configure the location spotlight and guide banner on the homepage. Individual spots are managed in <a href="<?= BASE_URL ?>/admin/locations.php" class="underline font-bold text-[#343c0a]">Prime Location Manager</a>.</p>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Badge</label>
                        <input type="text" name="location_badge" value="<?= e(get_setting('location_badge', 'Prime Location')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Title *</label>
                        <input type="text" name="location_title" value="<?= e(get_setting('location_title', 'Explore Phnom Penh from Tuol Kork')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Section Subtitle</label>
                    <textarea name="location_subtitle" rows="3" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs"><?= e(get_setting('location_subtitle', 'Located in a quiet, green residential area with rapid access to historical landmarks, shopping centers, and top cafes.')) ?></textarea>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Explore Button Text</label>
                    <input type="text" name="location_btn_text" value="<?= e(get_setting('location_btn_text', 'View Full City & Location Guide')) ?>" class="w-full sm:w-96 border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
            </div>

        </div>

        <!-- =======================================================
             TAB 6: Homepage SEO Meta & OpenGraph
             ======================================================= -->
        <div id="tab-content-seo" class="home-tab-content hidden bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="border-b border-stone-200 pb-4">
                <h2 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">search</span>
                    <span>Homepage SEO Meta & Social Graph</span>
                </h2>
                <p class="text-xs text-stone-500">Fine-tune the browser title, search engine snippet, and social media preview for the homepage.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Homepage SEO Title Tag *</label>
                    <input type="text" name="home_seo_title" value="<?= e(get_setting('home_seo_title', 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-semibold">
                    <p class="text-[10px] text-stone-400 mt-1">Optimal length: 50–60 characters.</p>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Meta Description</label>
                    <textarea name="home_seo_desc" rows="3" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs"><?= e(get_setting('home_seo_desc', 'Just minutes from bustling Phnom Penh, Indra Hotel offers 12 contemporary luxury suites with private balconies, saltwater pool, fitness center, and fine dining.')) ?></textarea>
                    <p class="text-[10px] text-stone-400 mt-1">Optimal length: 140–160 characters.</p>
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-1">Target Search Keywords</label>
                    <input type="text" name="home_seo_keywords" value="<?= e(get_setting('home_seo_keywords', 'Indra Hotel Phnom Penh, boutique hotel Cambodia, luxury accommodation Tuol Kork, hotel with pool Phnom Penh, best suites Phnom Penh')) ?>" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>

                <div>
                    <label class="block font-bold text-xs uppercase tracking-wider text-stone-700 mb-2">Social Sharing Preview Image (Open Graph Image)</label>
                    <?= render_image_uploader_field('home_seo_og_image', get_setting('home_seo_og_image', $defaultHeroImage), 'OG Share Image', 'general') ?>
                </div>
            </div>

        </div>

        <!-- Sticky Footer Save Bar -->
        <div class="p-4 bg-stone-900 text-white rounded-2xl shadow-xl flex items-center justify-between">
            <div class="text-xs text-stone-400">
                <span>Remember to click <strong>Save All Changes</strong> to update your live homepage.</span>
            </div>
            <button type="submit" class="bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold px-7 py-3 rounded-lg text-xs tracking-wider uppercase transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">save</span>
                <span>Save Homepage Content</span>
            </button>
        </div>

    </form>

</div>

<script>
function switchHomeTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.home-tab-content').forEach(el => el.classList.add('hidden'));
    
    // Reset all tab button styles
    document.querySelectorAll('.home-tab-btn').forEach(btn => {
        btn.classList.remove('bg-[#343c0a]', 'text-white');
        btn.classList.add('text-stone-600', 'hover:bg-stone-100');
    });

    // Show target tab
    const targetContent = document.getElementById('tab-content-' + tabId);
    const targetBtn = document.getElementById('tab-btn-' + tabId);

    if (targetContent) targetContent.classList.remove('hidden');
    if (targetBtn) {
        targetBtn.classList.remove('text-stone-600', 'hover:bg-stone-100');
        targetBtn.classList.add('bg-[#343c0a]', 'text-white');
    }

    // Set active tab input
    const input = document.getElementById('active_tab_input');
    if (input) input.value = tabId;

    // Update location hash without page jump
    history.replaceState(null, null, '#' + tabId);
}

function toggleHeroMediaType(type) {
    const videoBox = document.getElementById('hero_video_box');
    const labelText = document.getElementById('hero_image_label_text');
    const labelImage = document.getElementById('label_media_image');
    const labelVideo = document.getElementById('label_media_video');

    if (type === 'video') {
        if (videoBox) videoBox.classList.remove('hidden');
        if (labelText) labelText.textContent = 'Hero Video Fallback Poster Photo *';
        if (labelVideo) {
            labelVideo.classList.add('border-[#343c0a]', 'bg-white', 'shadow-sm');
            labelVideo.classList.remove('border-stone-200', 'bg-stone-100/60');
        }
        if (labelImage) {
            labelImage.classList.remove('border-[#343c0a]', 'bg-white', 'shadow-sm');
            labelImage.classList.add('border-stone-200', 'bg-stone-100/60');
        }
    } else {
        if (videoBox) videoBox.classList.add('hidden');
        if (labelText) labelText.textContent = 'Hero Background Image *';
        if (labelImage) {
            labelImage.classList.add('border-[#343c0a]', 'bg-white', 'shadow-sm');
            labelImage.classList.remove('border-stone-200', 'bg-stone-100/60');
        }
        if (labelVideo) {
            labelVideo.classList.remove('border-[#343c0a]', 'bg-white', 'shadow-sm');
            labelVideo.classList.add('border-stone-200', 'bg-stone-100/60');
        }
    }
}

if (typeof escapeHtml !== 'function') {
    window.escapeHtml = function(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };
}

// Initialize Dining & Wellness Banners Builder
const defaultDwBanners = [
    {
        badge: '<?= e(addslashes(get_setting('home_dining_badge', 'Culinary Journey'))) ?>',
        title: '<?= e(addslashes(get_setting('home_dining_title', 'The Bistro & Artisanal Cafe'))) ?>',
        desc: '<?= e(addslashes(get_setting('home_dining_desc', 'Indulge in an exquisite culinary journey featuring international and Asian-fusion cuisine with a contemporary twist.'))) ?>',
        image_url: '<?= e(addslashes(get_setting('home_dining_image', 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho'))) ?>',
        btn_text: '<?= e(addslashes(get_setting('home_dining_btn_text', 'Explore Dining Menus'))) ?>',
        btn_url: '<?= e(addslashes(get_setting('home_dining_btn_url', '/eat-drink'))) ?>',
        image_pos: 'right'
    },
    {
        badge: '<?= e(addslashes(get_setting('home_wellness_badge', 'Health & Vitality'))) ?>',
        title: '<?= e(addslashes(get_setting('home_wellness_title', 'Fitness Center, Pool & Spa'))) ?>',
        desc: '<?= e(addslashes(get_setting('home_wellness_desc', 'Stay invigorated with our state-of-the-art training gear, workout machines, and our serene outdoor saltwater pool open daily from 7:00 AM to 9:00 PM. Unwind afterwards with authentic Khmer herbal massage treatments designed to soothe mind and body.'))) ?>',
        image_url: '<?= e(addslashes(get_setting('home_wellness_image', 'https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5'))) ?>',
        btn_text: '<?= e(addslashes(get_setting('home_wellness_btn_text', 'Discover Wellness'))) ?>',
        btn_url: '<?= e(addslashes(get_setting('home_wellness_btn_url', '/wellness'))) ?>',
        image_pos: 'left'
    }
];

function initHomeDwBanners() {
    const input = document.getElementById('home_dw_banners_json');
    if (!input) return;
    const jsonVal = input.value;
    let items = [];
    try {
        items = JSON.parse(jsonVal || '[]');
    } catch(e) { items = []; }

    if (!Array.isArray(items) || items.length === 0) {
        items = defaultDwBanners;
    }

    renderHomeDwBanners(items);
}

function renderHomeDwBanners(items) {
    const container = document.getElementById('home_dw_banners_container');
    if (!container) return;
    container.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-xs text-stone-400 border border-dashed border-stone-200 rounded-2xl bg-stone-50/50">No experience banners added. Click "+ Add Experience Banner" above to create one.</div>';
        syncHomeDwBannersJson();
        return;
    }

    items.forEach((item, idx) => {
        addHomeDwBannerRow(item, idx);
    });
}

function addHomeDwBannerRow(data = {}, index = null) {
    const container = document.getElementById('home_dw_banners_container');
    if (!container) return;
    const emptyMsg = container.querySelector('.text-center');
    if (emptyMsg) {
        container.innerHTML = '';
    }

    const rowIdx = index !== null ? index : container.querySelectorAll('.dw-banner-card').length;
    const badge = data.badge || 'New Experience';
    const title = data.title || '';
    const desc = data.desc || '';
    const image_url = data.image_url || '';
    const btn_text = data.btn_text || 'Learn More';
    const btn_url = data.btn_url || '/eat-drink';
    const image_pos = data.image_pos || (rowIdx % 2 === 1 ? 'left' : 'right');

    const card = document.createElement('div');
    card.className = 'p-6 bg-stone-50 border border-stone-200 rounded-2xl space-y-4 dw-banner-card relative group shadow-2xs';
    card.innerHTML = `
        <div class="flex items-center justify-between border-b border-stone-200/80 pb-3">
            <div class="flex items-center gap-2 text-stone-800 font-headline font-bold text-base">
                <span class="material-symbols-outlined text-[#4B5320]">auto_awesome</span>
                <span>Experience Banner #${rowIdx + 1}</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="moveHomeDwBannerRow(this, -1)" class="p-1.5 text-stone-500 hover:text-stone-900 hover:bg-stone-200 rounded-lg transition cursor-pointer" title="Move Up">
                    <span class="material-symbols-outlined text-base">arrow_upward</span>
                </button>
                <button type="button" onclick="moveHomeDwBannerRow(this, 1)" class="p-1.5 text-stone-500 hover:text-stone-900 hover:bg-stone-200 rounded-lg transition cursor-pointer" title="Move Down">
                    <span class="material-symbols-outlined text-base">arrow_downward</span>
                </button>
                <button type="button" onclick="removeHomeDwBannerRow(this)" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-100/60 rounded-lg transition cursor-pointer" title="Delete Banner">
                    <span class="material-symbols-outlined text-base">delete</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Banner Badge</label>
                <input type="text" value="${escapeHtml(badge)}" oninput="syncHomeDwBannersJson()" class="dw-banner-badge w-full border border-stone-300 rounded-lg p-2 text-xs font-semibold">
            </div>
            <div>
                <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Banner Title</label>
                <input type="text" value="${escapeHtml(title)}" oninput="syncHomeDwBannersJson()" class="dw-banner-title w-full border border-stone-300 rounded-lg p-2 text-xs font-bold text-onyx-charcoal">
            </div>
            <div>
                <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Image Layout Position</label>
                <select onchange="syncHomeDwBannersJson()" class="dw-banner-pos w-full border border-stone-300 rounded-lg p-2 text-xs font-bold text-stone-800 bg-white">
                    <option value="right" ${image_pos === 'right' ? 'selected' : ''}>Text Left / Image Right</option>
                    <option value="left" ${image_pos === 'left' ? 'selected' : ''}>Image Left / Text Right</option>
                    <option value="auto" ${image_pos === 'auto' ? 'selected' : ''}>Auto Alternating</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Description</label>
            <textarea rows="2" oninput="syncHomeDwBannersJson()" class="dw-banner-desc w-full border border-stone-300 rounded-lg p-2 text-xs">${escapeHtml(desc)}</textarea>
        </div>

        <div>
            <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Banner Photo URL</label>
            <div class="flex items-center gap-2">
                <input type="text" value="${escapeHtml(image_url)}" oninput="syncHomeDwBannersJson()" class="dw-banner-img flex-1 border border-stone-300 rounded-lg p-2 text-xs font-mono">
                <button type="button" onclick="openDwImagePicker(this)" class="px-3 py-2 bg-stone-200 hover:bg-stone-300 text-stone-800 text-xs font-bold rounded-lg transition shrink-0 cursor-pointer">Choose Image</button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Button Label</label>
                <input type="text" value="${escapeHtml(btn_text)}" oninput="syncHomeDwBannersJson()" class="dw-banner-btn-text w-full border border-stone-300 rounded-lg p-2 text-xs font-semibold">
            </div>
            <div>
                <label class="block font-bold text-[11px] uppercase tracking-wider text-stone-600 mb-1">Button Destination URL</label>
                <input type="text" value="${escapeHtml(btn_url)}" oninput="syncHomeDwBannersJson()" class="dw-banner-btn-url w-full border border-stone-300 rounded-lg p-2 text-xs font-mono">
            </div>
        </div>
    `;

    container.appendChild(card);
    syncHomeDwBannersJson();
}

function removeHomeDwBannerRow(btn) {
    const card = btn.closest('.dw-banner-card');
    if (card) {
        card.remove();
        syncHomeDwBannersJson();
        const container = document.getElementById('home_dw_banners_container');
        if (container && container.querySelectorAll('.dw-banner-card').length === 0) {
            container.innerHTML = '<div class="p-8 text-center text-xs text-stone-400 border border-dashed border-stone-200 rounded-2xl bg-stone-50/50">No experience banners added. Click "+ Add Experience Banner" above to create one.</div>';
        }
    }
}

function moveHomeDwBannerRow(btn, direction) {
    const card = btn.closest('.dw-banner-card');
    if (!card) return;
    if (direction === -1 && card.previousElementSibling && card.previousElementSibling.classList.contains('dw-banner-card')) {
        card.parentNode.insertBefore(card, card.previousElementSibling);
    } else if (direction === 1 && card.nextElementSibling && card.nextElementSibling.classList.contains('dw-banner-card')) {
        card.parentNode.insertBefore(card.nextElementSibling, card);
    }
    syncHomeDwBannersJson();
}

function syncHomeDwBannersJson() {
    const container = document.getElementById('home_dw_banners_container');
    if (!container) return;
    const cards = container.querySelectorAll('.dw-banner-card');
    const items = [];

    cards.forEach(c => {
        const badge = c.querySelector('.dw-banner-badge')?.value.trim() || '';
        const title = c.querySelector('.dw-banner-title')?.value.trim() || '';
        const desc = c.querySelector('.dw-banner-desc')?.value.trim() || '';
        const image_url = c.querySelector('.dw-banner-img')?.value.trim() || '';
        const btn_text = c.querySelector('.dw-banner-btn-text')?.value.trim() || '';
        const btn_url = c.querySelector('.dw-banner-btn-url')?.value.trim() || '';
        const image_pos = c.querySelector('.dw-banner-pos')?.value || 'auto';

        if (title || badge || desc || image_url) {
            items.push({ badge, title, desc, image_url, btn_text, btn_url, image_pos });
        }
    });

    const hiddenInput = document.getElementById('home_dw_banners_json');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(items);
    }
}

function openDwImagePicker(btn) {
    const input = btn.previousElementSibling;
    if (window.openMediaPicker) {
        window.openMediaPicker(url => {
            if (input) {
                input.value = url;
                syncHomeDwBannersJson();
            }
        });
    } else {
        const url = prompt('Enter Image URL:', input ? input.value : '');
        if (url !== null && input) {
            input.value = url;
            syncHomeDwBannersJson();
        }
    }
}

// Initialize Home Stats Builder
const defaultHomeStats = [
    { val: '<?= e(addslashes(get_setting('home_stat1_val', '12'))) ?>', suffix: '<?= e(addslashes(get_setting('home_stat1_suffix', ' Suites'))) ?>', label: '<?= e(addslashes(get_setting('home_stat1_label', 'Boutique Sanctuary'))) ?>' },
    { val: '<?= e(addslashes(get_setting('home_stat2_val', '15'))) ?>', suffix: '<?= e(addslashes(get_setting('home_stat2_suffix', '% Off'))) ?>', label: '<?= e(addslashes(get_setting('home_stat2_label', 'Direct Privilege'))) ?>' },
    { val: '<?= e(addslashes(get_setting('home_stat3_val', '10'))) ?>', suffix: '<?= e(addslashes(get_setting('home_stat3_suffix', ' Mins'))) ?>', label: '<?= e(addslashes(get_setting('home_stat3_label', 'To Royal Palace'))) ?>' }
];

function initHomeStats() {
    const input = document.getElementById('home_stats_json');
    if (!input) return;
    let items = [];
    try { items = JSON.parse(input.value || '[]'); } catch(e) { items = []; }
    if (!Array.isArray(items) || items.length === 0) items = defaultHomeStats;
    renderHomeStats(items);
}

function renderHomeStats(items) {
    const container = document.getElementById('home_stats_container');
    if (!container) return;
    container.innerHTML = '';
    items.forEach((item, idx) => addHomeStatRow(item, idx));
}

function addHomeStatRow(data = {}, index = null) {
    const container = document.getElementById('home_stats_container');
    if (!container) return;
    const rowIdx = index !== null ? index : container.querySelectorAll('.home-stat-card').length;
    const val = data.val || '0';
    const suffix = data.suffix || '';
    const label = data.label || '';

    const card = document.createElement('div');
    card.className = 'p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-2 home-stat-card relative group shadow-2xs';
    card.innerHTML = `
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#4B5320] uppercase">Counter #${rowIdx + 1}</span>
            <button type="button" onclick="removeHomeStatRow(this)" class="text-rose-600 hover:text-rose-800 p-0.5 rounded cursor-pointer" title="Delete Stat"><span class="material-symbols-outlined text-sm">delete</span></button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <input type="text" value="${escapeHtml(val)}" oninput="syncHomeStatsJson()" placeholder="Target (e.g. 12)" class="stat-val w-full border border-stone-300 rounded p-2 text-xs font-bold">
            <input type="text" value="${escapeHtml(suffix)}" oninput="syncHomeStatsJson()" placeholder="Suffix (e.g. Suites)" class="stat-suffix w-full border border-stone-300 rounded p-2 text-xs">
        </div>
        <input type="text" value="${escapeHtml(label)}" oninput="syncHomeStatsJson()" placeholder="Label" class="stat-label w-full border border-stone-300 rounded p-2 text-xs text-stone-600">
    `;
    container.appendChild(card);
    syncHomeStatsJson();
}

function removeHomeStatRow(btn) {
    const card = btn.closest('.home-stat-card');
    if (card) { card.remove(); syncHomeStatsJson(); }
}

function syncHomeStatsJson() {
    const container = document.getElementById('home_stats_container');
    if (!container) return;
    const cards = container.querySelectorAll('.home-stat-card');
    const items = [];
    cards.forEach(c => {
        const val = c.querySelector('.stat-val')?.value.trim() || '';
        const suffix = c.querySelector('.stat-suffix')?.value.trim() || '';
        const label = c.querySelector('.stat-label')?.value.trim() || '';
        if (val || label) items.push({ val, suffix, label });
    });
    const hidden = document.getElementById('home_stats_json');
    if (hidden) hidden.value = JSON.stringify(items);
}

// Initialize Home Amenities Builder
const defaultHomeAmenities = [
    { icon: '<?= e(addslashes(get_setting('home_amenity1_icon', 'pool'))) ?>', text: '<?= e(addslashes(get_setting('home_amenity1_text', 'Saltwater Pool'))) ?>' },
    { icon: '<?= e(addslashes(get_setting('home_amenity2_icon', 'fitness_center'))) ?>', text: '<?= e(addslashes(get_setting('home_amenity2_text', 'Fitness Center'))) ?>' },
    { icon: '<?= e(addslashes(get_setting('home_amenity3_icon', 'restaurant'))) ?>', text: '<?= e(addslashes(get_setting('home_amenity3_text', 'Fine Bistro & Cafe'))) ?>' },
    { icon: '<?= e(addslashes(get_setting('home_amenity4_icon', 'wifi'))) ?>', text: '<?= e(addslashes(get_setting('home_amenity4_text', 'High-Speed Wi-Fi'))) ?>' }
];

function initHomeAmenities() {
    const input = document.getElementById('home_amenities_json');
    if (!input) return;
    let items = [];
    try { items = JSON.parse(input.value || '[]'); } catch(e) { items = []; }
    if (!Array.isArray(items) || items.length === 0) items = defaultHomeAmenities;
    renderHomeAmenities(items);
}

function renderHomeAmenities(items) {
    const container = document.getElementById('home_amenities_container');
    if (!container) return;
    container.innerHTML = '';
    items.forEach((item, idx) => addHomeAmenityRow(item, idx));
}

function addHomeAmenityRow(data = {}, index = null) {
    const container = document.getElementById('home_amenities_container');
    if (!container) return;
    const rowIdx = index !== null ? index : container.querySelectorAll('.home-amenity-card').length;
    const icon = data.icon || 'star';
    const text = data.text || '';

    const card = document.createElement('div');
    card.className = 'p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2 home-amenity-card relative group shadow-2xs';
    card.innerHTML = `
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-stone-400 uppercase">Amenity #${rowIdx + 1}</span>
            <button type="button" onclick="removeHomeAmenityRow(this)" class="text-rose-600 hover:text-rose-800 p-0.5 rounded cursor-pointer" title="Delete Amenity"><span class="material-symbols-outlined text-sm">delete</span></button>
        </div>
        <input type="text" value="${escapeHtml(icon)}" oninput="syncHomeAmenitiesJson()" placeholder="Material Icon (pool)" class="amenity-icon w-full border border-stone-300 rounded p-2 text-xs font-mono">
        <input type="text" value="${escapeHtml(text)}" oninput="syncHomeAmenitiesJson()" placeholder="Label" class="amenity-text w-full border border-stone-300 rounded p-2 text-xs">
    `;
    container.appendChild(card);
    syncHomeAmenitiesJson();
}

function removeHomeAmenityRow(btn) {
    const card = btn.closest('.home-amenity-card');
    if (card) { card.remove(); syncHomeAmenitiesJson(); }
}

function syncHomeAmenitiesJson() {
    const container = document.getElementById('home_amenities_container');
    if (!container) return;
    const cards = container.querySelectorAll('.home-amenity-card');
    const items = [];
    cards.forEach(c => {
        const icon = c.querySelector('.amenity-icon')?.value.trim() || 'star';
        const text = c.querySelector('.amenity-text')?.value.trim() || '';
        if (text) items.push({ icon, text });
    });
    const hidden = document.getElementById('home_amenities_json');
    if (hidden) hidden.value = JSON.stringify(items);
}

// Auto-switch to hash tab on page load & init DW builder
document.addEventListener('DOMContentLoaded', () => {
    initHomeDwBanners();
    initHomeStats();
    initHomeAmenities();
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('tab-content-' + hash)) {
        switchHomeTab(hash);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
