<?php
/**
 * Indra Hotel - Dynamic Homepage (SoftBook CMS)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/router.php';

// If this request was rewritten to index.php for a route other than home, dispatch it
$reqPath = Router::getRequestPath();
if ($reqPath !== '/' && $reqPath !== '/home' && $reqPath !== '/index.php') {
    Router::dispatch();
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();

// 1. Featured Rooms (Dynamic limit configured in CMS)
$roomsLimit = (int)get_setting('home_rooms_limit', '3');
$stmtRooms = $pdo->prepare("SELECT * FROM rooms WHERE status != 'maintenance' ORDER BY display_order ASC LIMIT ?");
$stmtRooms->bindValue(1, $roomsLimit > 0 ? $roomsLimit : 3, PDO::PARAM_INT);
$stmtRooms->execute();
$rooms = $stmtRooms->fetchAll();

// 2. Fetch Dining & Wellness Highlights
$stmtDW = $pdo->query("SELECT * FROM dining_wellness WHERE is_published = 1 ORDER BY display_order ASC");
$experiences = $stmtDW->fetchAll();

// 3. Fetch Active Special Offer Banner
$stmtOffer = $pdo->query("SELECT * FROM special_offers WHERE is_active = 1 ORDER BY display_order ASC LIMIT 1");
$featuredOffer = $stmtOffer->fetch();

// 4. Hero Configuration & Background Media (Image / Video)
$heroMediaType = get_setting('home_hero_media_type', 'image');
$heroImage = get_setting('home_hero_image', 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq');
$heroVideoUrl = get_setting('home_hero_video_url', '');
$heroKenBurns = (get_setting('home_hero_ken_burns', '1') === '1');
$heroOverlay = get_setting('home_hero_overlay', 'dark');

// Overlay darkness styles
$overlayClasses = [
    'soft' => 'bg-gradient-to-r from-black/55 via-black/35 to-black/50',
    'medium' => 'bg-gradient-to-r from-black/70 via-black/50 to-black/65',
    'dark' => 'bg-gradient-to-r from-black/85 via-black/60 to-black/75',
    'deep' => 'bg-gradient-to-r from-black/92 via-black/75 to-black/88'
];
$overlayClass = $overlayClasses[$heroOverlay] ?? $overlayClasses['dark'];

// Helper to detect YouTube Embed vs Direct MP4/WebM Video
$isYouTube = false;
$youTubeId = '';
if ($heroMediaType === 'video' && !empty($heroVideoUrl)) {
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $heroVideoUrl, $match)) {
        $isYouTube = true;
        $youTubeId = $match[1];
    }
}

// 5. Page SEO Metadata
$pageMeta = [
    'title' => get_setting('home_seo_title', 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh'),
    'description' => get_setting('home_seo_desc', 'Just minutes from bustling Phnom Penh, Indra Hotel offers 12 contemporary luxury suites with private balconies, saltwater pool, fitness center, and fine dining.'),
    'keywords' => get_setting('home_seo_keywords', 'Indra Hotel Phnom Penh, boutique hotel Cambodia, luxury accommodation Tuol Kork, hotel with pool Phnom Penh, best suites Phnom Penh'),
    'image' => get_setting('home_seo_og_image', $heroImage),
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- =======================================================
     Hero Section with Image / Video Background Support
     ======================================================= -->
<section class="relative bg-onyx-charcoal text-white min-h-[88vh] flex items-center justify-center overflow-hidden">
    
    <!-- Background Media (Video or Image) -->
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <?php if ($heroMediaType === 'video' && !empty($heroVideoUrl)): ?>
            <?php if ($isYouTube): ?>
                <!-- YouTube Embedded Background Player -->
                <div class="w-full h-full overflow-hidden relative pointer-events-none">
                    <iframe class="w-[300%] h-[300%] absolute -top-[100%] -left-[100%] pointer-events-none" 
                            src="https://www.youtube-nocookie.com/embed/<?= e($youTubeId) ?>?autoplay=1&mute=1&controls=0&loop=1&playlist=<?= e($youTubeId) ?>&playsinline=1&showinfo=0&rel=0&modestbranding=1" 
                            frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                </div>
            <?php else: ?>
                <!-- Direct HTML5 Video Background (MP4 / WebM) -->
                <video autoplay muted loop playsinline poster="<?= e($heroImage) ?>" class="w-full h-full object-cover object-center">
                    <source src="<?= e($heroVideoUrl) ?>">
                    <!-- Fallback image -->
                    <img src="<?= e($heroImage) ?>" alt="<?= e(hotel_name()) ?>" class="w-full h-full object-cover object-center">
                </video>
            <?php endif; ?>
        <?php else: ?>
            <!-- High-Resolution Static Image with Ken Burns Zoom -->
            <img src="<?= e($heroImage) ?>" 
                 alt="<?= e(hotel_name()) ?> Luxury Sanctuary in Phnom Penh" 
                 class="w-full h-full object-cover object-center <?= $heroKenBurns ? 'ken-burns' : '' ?>">
        <?php endif; ?>

        <!-- Configurable Darkness Gradient Overlay -->
        <div class="absolute inset-0 <?= $overlayClass ?>"></div>
    </div>

    <!-- Hero Content Overlay -->
    <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        
        <!-- Hero Pill Badge -->
        <?php if ($heroBadge = get_setting('home_hero_badge', __t('hero_badge', 'Boutique Luxury Sanctuary'))): ?>
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[#dfe8a6] text-xs font-semibold uppercase tracking-widest mb-6 reveal reveal-fade">
            <span class="w-2 h-2 rounded-full bg-[#dfe8a6] animate-pulse"></span>
            <span><?= e($heroBadge) ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Main Headline -->
        <h1 class="font-headline text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight text-white mb-6 leading-tight reveal reveal-up">
            <?= e(get_setting('home_hero_title', __t('hero_title', 'Welcome to Indra Hotel'))) ?>
        </h1>
        
        <!-- Hero Subtitle -->
        <?php if ($heroSubtitle = get_setting('home_hero_subtitle', __t('hero_subtitle', 'Just minutes from bustling Phnom Penh, experience the refined elegance and tranquil comfort of our 12-room contemporary boutique retreat in Tuol Kork.'))): ?>
        <p class="text-lg sm:text-xl text-stone-200 max-w-3xl mx-auto font-light leading-relaxed mb-10 reveal reveal-up stagger-1">
            <?= e($heroSubtitle) ?>
        </p>
        <?php endif; ?>

        <!-- Quick Booking Bar Widget -->
        <?php if (get_setting('home_hero_show_search', '1') === '1'): ?>
        <div class="bg-white text-stone-900 rounded-xl shadow-2xl p-4 sm:p-6 max-w-4xl mx-auto border border-stone-200 reveal reveal-scale stagger-2">
            <form action="<?= url('/book') ?>" method="GET" target="<?= e(get_booking_target()) ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-left">
                <!-- Check In -->
                <div>
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-1"><?= __t('search_checkin', 'Check-in') ?></label>
                    <div class="relative">
                        <input type="date" name="check_in" id="booking_check_in" required
                                class="w-full text-sm font-medium border border-stone-300 rounded px-3 py-2.5 focus:ring-2 focus:ring-[#343c0a] focus:border-transparent">
                    </div>
                </div>

                <!-- Check Out -->
                <div>
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-1"><?= __t('search_checkout', 'Check-out') ?></label>
                    <div class="relative">
                        <input type="date" name="check_out" id="booking_check_out" required
                                class="w-full text-sm font-medium border border-stone-300 rounded px-3 py-2.5 focus:ring-2 focus:ring-[#343c0a] focus:border-transparent">
                    </div>
                </div>

                <!-- Room Type Selection -->
                <div>
                    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-1"><?= __t('search_room', 'Accommodation') ?></label>
                    <select name="room_id" id="booking_room_select" class="w-full text-sm font-medium border border-stone-300 rounded px-3 py-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <?php foreach ($rooms as $rm): ?>
                            <option value="<?= $rm['id'] ?>" data-price="<?= $rm['price_per_night'] ?>">
                                <?= e(__td($rm, 'name', $rm['name'])) ?> (<?= format_price($rm['price_per_night']) ?>/nt)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit Action Button -->
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white py-2.5 px-4 rounded font-semibold text-sm tracking-wide transition shadow flex items-center justify-center gap-2 btn-shimmer cursor-pointer">
                        <span class="material-symbols-outlined text-lg">search</span>
                        <span><?= e(get_booking_button_text(get_setting('home_hero_search_btn_text', __t('search_check_rates', 'Check Rates')))) ?></span>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- =======================================================
     Philosophy Narrative & Interactive Statistics Section
     ======================================================= -->
<?php
$storyBadge = get_setting('home_story_badge', __t('phil_badge', 'The Indra Philosophy'));
$storyTitle = get_setting('home_story_title', __t('phil_title', 'A Serene Sanctuary in the Heart of Phnom Penh'));
$storyDesc1 = get_setting('home_story_desc1', __t('phil_desc1', 'Nestled in a green and eco-friendly setting in Tuol Kork, Indra Hotel exudes sustainable charm and bespoke hospitality. Our dedicated team ensures top-notch personalized service and an intimate, cozy atmosphere.'));
$storyDesc2 = get_setting('home_story_desc2', __t('phil_desc2', 'The hotel boasts a charming artisanal cafe, fully equipped fitness center, refreshing outdoor saltwater swimming pool, and fine dining experiences with exquisite city views. Shopping centers, local markets, Wat Phnom, and the Royal Palace are all just minutes away.'));
$storyImage = get_setting('home_story_image', 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho');
$storyImgBadge = get_setting('home_story_image_badge', 'Authentic Hospitality');
$storyImgTitle = get_setting('home_story_image_title', 'Designed for Unrivaled Comfort & Tranquility');

// KPI Counters
$stat1Val = (int)get_setting('home_stat1_val', '12');
$stat1Suffix = get_setting('home_stat1_suffix', ' Suites');
$stat1Label = get_setting('home_stat1_label', 'Boutique Sanctuary');

$stat2Val = (int)get_setting('home_stat2_val', '15');
$stat2Suffix = get_setting('home_stat2_suffix', '% Off');
$stat2Label = get_setting('home_stat2_label', 'Direct Privilege');

$stat3Val = (int)get_setting('home_stat3_val', '10');
$stat3Suffix = get_setting('home_stat3_suffix', ' Mins');
$stat3Label = get_setting('home_stat3_label', 'To Royal Palace');

// 4 Quick Amenities
$amenitiesList = [
    ['icon' => get_setting('home_amenity1_icon', 'pool'), 'text' => get_setting('home_amenity1_text', __t('amenity_pool', 'Saltwater Pool'))],
    ['icon' => get_setting('home_amenity2_icon', 'fitness_center'), 'text' => get_setting('home_amenity2_text', __t('amenity_fitness', 'Fitness Center'))],
    ['icon' => get_setting('home_amenity3_icon', 'restaurant'), 'text' => get_setting('home_amenity3_text', __t('amenity_dining', 'Fine Bistro & Cafe'))],
    ['icon' => get_setting('home_amenity4_icon', 'wifi'), 'text' => get_setting('home_amenity4_text', __t('amenity_wifi', 'High-Speed Wi-Fi'))],
];
?>
<section class="py-20 bg-[#f9f9f9] border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Left Text Content -->
            <div class="lg:col-span-6 space-y-6 reveal reveal-left">
                <?php if (!empty($storyBadge)): ?>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($storyBadge) ?></span>
                <?php endif; ?>

                <h2 class="font-headline text-3xl sm:text-4xl font-bold text-onyx-charcoal tracking-tight leading-snug">
                    <?= e($storyTitle) ?>
                </h2>

                <?php if (!empty($storyDesc1)): ?>
                <p class="text-stone-600 text-base sm:text-lg leading-relaxed">
                    <?= e($storyDesc1) ?>
                </p>
                <?php endif; ?>

                <?php if (!empty($storyDesc2)): ?>
                <p class="text-stone-600 text-base leading-relaxed">
                    <?= e($storyDesc2) ?>
                </p>
                <?php endif; ?>
                
                <!-- Animated Statistics Counters -->
                <div class="grid grid-cols-3 gap-4 pt-2 border-t border-stone-200">
                    <div>
                        <div class="font-headline font-bold text-2xl sm:text-3xl text-[#343c0a] animate-counter" data-target="<?= $stat1Val ?>" data-suffix="<?= e($stat1Suffix) ?>"><?= $stat1Val . e($stat1Suffix) ?></div>
                        <span class="text-[11px] text-stone-500 font-medium"><?= e($stat1Label) ?></span>
                    </div>
                    <div>
                        <div class="font-headline font-bold text-2xl sm:text-3xl text-[#343c0a] animate-counter" data-target="<?= $stat2Val ?>" data-suffix="<?= e($stat2Suffix) ?>"><?= $stat2Val . e($stat2Suffix) ?></div>
                        <span class="text-[11px] text-stone-500 font-medium"><?= e($stat2Label) ?></span>
                    </div>
                    <div>
                        <div class="font-headline font-bold text-2xl sm:text-3xl text-[#343c0a] animate-counter" data-target="<?= $stat3Val ?>" data-suffix="<?= e($stat3Suffix) ?>"><?= $stat3Val . e($stat3Suffix) ?></div>
                        <span class="text-[11px] text-stone-500 font-medium"><?= e($stat3Label) ?></span>
                    </div>
                </div>

                <!-- 4 Amenities Badges -->
                <div class="pt-2 flex flex-wrap gap-6 text-sm font-semibold text-stone-800">
                    <?php foreach ($amenitiesList as $am): ?>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320]"><?= e($am['icon']) ?></span>
                        <span><?= e($am['text']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Story Photo with Floating Overlay -->
            <div class="lg:col-span-6 reveal reveal-right">
                <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-stone-200 group">
                    <img src="<?= e($storyImage) ?>" 
                         alt="<?= e($storyTitle) ?>" 
                         class="w-full h-[450px] object-cover group-hover:scale-105 transition-transform duration-700">
                    
                    <?php if (!empty($storyImgBadge) || !empty($storyImgTitle)): ?>
                    <div class="absolute bottom-6 left-6 right-6 p-6 rounded-xl bg-black/60 backdrop-blur-md text-white border border-white/10">
                        <?php if (!empty($storyImgBadge)): ?>
                            <div class="text-xs font-semibold uppercase tracking-widest text-[#dfe8a6] mb-1"><?= e($storyImgBadge) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($storyImgTitle)): ?>
                            <div class="font-headline font-bold text-lg"><?= e($storyImgTitle) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- =======================================================
     Featured Accommodations Showcase Section
     ======================================================= -->
<?php
$roomsBadge = get_setting('home_rooms_badge', __t('rooms_badge', 'Refined Living'));
$roomsTitle = get_setting('home_rooms_title', __t('rooms_title', 'Accommodations & Suites'));
$roomsSubtitle = get_setting('home_rooms_subtitle', __t('rooms_subtitle', 'With modern and functional decor, our 12 spacious accommodations cater to the needs of all travelers.'));
$roomsBtnText = get_setting('home_rooms_btn_text', __t('rooms_view_all', 'View All Accommodations'));
?>
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 reveal reveal-up">
            <div>
                <?php if (!empty($roomsBadge)): ?>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($roomsBadge) ?></span>
                <?php endif; ?>
                <h2 class="font-headline text-3xl sm:text-4xl font-bold text-onyx-charcoal tracking-tight mt-2">
                    <?= e($roomsTitle) ?>
                </h2>
                <?php if (!empty($roomsSubtitle)): ?>
                <p class="text-stone-500 text-base mt-2 max-w-xl">
                    <?= e($roomsSubtitle) ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="mt-6 md:mt-0">
                <a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-[#343c0a] hover:text-deep-olive group">
                    <span><?= e($roomsBtnText) ?></span>
                    <span class="material-symbols-outlined text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php 
            $i = 1;
            foreach ($rooms as $room): 
            ?>
            <div class="bg-[#f9f9f9] rounded-xl overflow-hidden border border-stone-200 luxury-card flex flex-col group reveal reveal-up stagger-<?= $i++ ?>">
                <div class="relative h-64 overflow-hidden">
                    <img src="<?= e($room['image_url']) ?>" 
                         alt="<?= e(__td($room, 'name', $room['name'])) ?> at Indra Hotel Phnom Penh" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div class="absolute top-4 left-4">
                        <?= get_room_type_badge($room['category'] ?? 'Deluxe Room', 'xs') ?>
                    </div>
                    <div class="absolute top-4 right-4 bg-black/70 backdrop-blur-md text-white px-3 py-1.5 rounded-md text-sm font-bold border border-white/10">
                        <?= format_price($room['price_per_night']) ?> <span class="text-xs font-normal text-stone-300">/ <?= __t('rooms_per_night', 'night') ?></span>
                    </div>
                </div>

                <div class="p-6 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-4 text-xs font-medium text-stone-500 mb-3">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">square_foot</span><?= $room['size_sqm'] ?> m²</span>
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">bed</span><?= e($room['bed_type']) ?></span>
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">group</span>Up to <?= (int)$room['capacity_adults'] + (int)$room['capacity_children'] ?></span>
                        </div>

                        <h3 class="font-headline font-bold text-xl text-onyx-charcoal mb-2">
                            <a href="<?= url('/room/' . urlencode($room['slug'])) ?>" class="hover:text-[#4B5320] transition">
                                <?= e(__td($room, 'name', $room['name'])) ?>
                            </a>
                        </h3>
                        
                        <p class="text-stone-600 text-sm line-clamp-3 leading-relaxed mb-6">
                            <?= e(__td($room, 'description', $room['description'])) ?>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-200 flex items-center justify-between">
                        <a href="<?= url('/room/' . urlencode($room['slug'])) ?>" class="text-sm font-semibold text-stone-700 hover:text-[#343c0a] transition flex items-center gap-1">
                            <span><?= __t('rooms_details', 'Details') ?></span>
                            <span class="material-symbols-outlined text-base">arrow_forward</span>
                        </a>
                        <a href="<?= e(get_booking_url((int)$room['id'])) ?>" target="<?= e(get_booking_target()) ?>" class="bg-[#343c0a] hover:bg-deep-olive text-white px-4 py-2 rounded text-xs font-semibold tracking-wider uppercase transition btn-shimmer">
                            <?= e(get_booking_button_text(__t('rooms_book_suite', 'Book Suite'))) ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =======================================================
     Eat & Drink + Gym & Wellness Feature Split Banners
     ======================================================= -->
<?php
$diningBadge = get_setting('home_dining_badge', __t('dining_badge', 'Culinary Journey'));
$diningTitle = get_setting('home_dining_title', __t('dining_title', 'The Bistro & Artisanal Cafe'));
$diningDesc = get_setting('home_dining_desc', __t('dining_desc', 'Indulge in an exquisite culinary journey featuring international and Asian-fusion cuisine with a contemporary twist.'));
$diningImg = get_setting('home_dining_image', 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho');
$diningBtnText = get_setting('home_dining_btn_text', __t('dining_explore', 'Explore Dining Menus'));
$diningBtnUrl = get_setting('home_dining_btn_url', '/eat-drink');

$wellnessBadge = get_setting('home_wellness_badge', 'Health & Vitality');
$wellnessTitle = get_setting('home_wellness_title', 'Fitness Center, Pool & Spa');
$wellnessDesc = get_setting('home_wellness_desc', 'Stay invigorated with our state-of-the-art training gear, workout machines, and our serene outdoor saltwater pool open daily from 7:00 AM to 9:00 PM. Unwind afterwards with authentic Khmer herbal massage treatments designed to soothe mind and body.');
$wellnessImg = get_setting('home_wellness_image', 'https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5');
$wellnessBtnText = get_setting('home_wellness_btn_text', 'Discover Wellness');
$wellnessBtnUrl = get_setting('home_wellness_btn_url', '/wellness');
?>
<section class="py-20 bg-[#f3f3f4]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Dining Banner -->
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-stone-200 mb-12 grid grid-cols-1 lg:grid-cols-12 items-center reveal reveal-up">
            <div class="lg:col-span-6 p-8 sm:p-12 space-y-4">
                <?php if (!empty($diningBadge)): ?>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($diningBadge) ?></span>
                <?php endif; ?>
                <h2 class="font-headline text-3xl font-bold text-onyx-charcoal"><?= e($diningTitle) ?></h2>
                <p class="text-stone-600 leading-relaxed text-sm sm:text-base">
                    <?= e($diningDesc) ?>
                </p>
                <div class="pt-2">
                    <a href="<?= BASE_URL . '/' . ltrim(e($diningBtnUrl), '/') ?>" class="inline-flex items-center gap-2 bg-onyx-charcoal hover:bg-black text-white px-5 py-2.5 rounded text-sm font-semibold transition">
                        <span><?= e($diningBtnText) ?></span>
                        <span class="material-symbols-outlined text-base">restaurant_menu</span>
                    </a>
                </div>
            </div>
            <div class="lg:col-span-6 h-80 lg:h-full min-h-[340px] overflow-hidden">
                <img src="<?= e($diningImg) ?>" 
                     alt="<?= e($diningTitle) ?>" 
                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-700">
            </div>
        </div>

        <!-- Gym & Wellness Banner -->
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-stone-200 grid grid-cols-1 lg:grid-cols-12 items-center reveal reveal-up stagger-1">
            <div class="lg:col-span-6 order-2 lg:order-1 h-80 lg:h-full min-h-[340px] overflow-hidden">
                <img src="<?= e($wellnessImg) ?>" 
                     alt="<?= e($wellnessTitle) ?>" 
                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-700">
            </div>
            <div class="lg:col-span-6 order-1 lg:order-2 p-8 sm:p-12 space-y-4">
                <?php if (!empty($wellnessBadge)): ?>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($wellnessBadge) ?></span>
                <?php endif; ?>
                <h2 class="font-headline text-3xl font-bold text-onyx-charcoal"><?= e($wellnessTitle) ?></h2>
                <p class="text-stone-600 leading-relaxed text-sm sm:text-base">
                    <?= e($wellnessDesc) ?>
                </p>
                <div class="pt-2">
                    <a href="<?= BASE_URL . '/' . ltrim(e($wellnessBtnUrl), '/') ?>" class="inline-flex items-center gap-2 bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded text-sm font-semibold transition btn-shimmer">
                        <span><?= e($wellnessBtnText) ?></span>
                        <span class="material-symbols-outlined text-base">spa</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- =======================================================
     Direct Booking Privilege Banner
     ======================================================= -->
<?php if ($featuredOffer): ?>
<section class="py-16 bg-[#343c0a] text-white reveal reveal-fade">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center sm:text-left flex flex-col md:flex-row items-center justify-between gap-8">
        <div class="space-y-2 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-[#dfe8a6]"><?= e($featuredOffer['badge_text'] ?? 'Special Privilege') ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-white"><?= e($featuredOffer['title']) ?></h2>
            <p class="text-stone-300 text-sm sm:text-base leading-relaxed">
                <?= e($featuredOffer['description']) ?>
            </p>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-4">
            <?php if (!empty($featuredOffer['promo_code'])): ?>
            <button onclick="copyPromoCode('<?= e($featuredOffer['promo_code']) ?>')" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white px-5 py-3 rounded text-sm font-semibold transition flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">content_copy</span>
                <span>Code: <strong><?= e($featuredOffer['promo_code']) ?></strong></span>
            </button>
            <?php endif; ?>
            <a href="<?= e(get_offer_booking_url($featuredOffer)) ?>" target="<?= e(get_booking_target()) ?>" class="bg-[#dfe8a6] hover:bg-white text-[#191e00] px-6 py-3 rounded text-sm font-bold tracking-wide transition shadow-lg btn-shimmer">
                <?= e(get_booking_button_text('Book with Discount')) ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =======================================================
     Prime Location Highlights Section
     ======================================================= -->
<?php
$locBadge = get_setting('location_badge', 'Prime Location');
$locTitle = get_setting('location_title', 'Explore Phnom Penh from Tuol Kork');
$locSubtitle = get_setting('location_subtitle', 'Located in a quiet, green residential area with rapid access to historical landmarks, shopping centers, and top cafes.');
$locBtnText = get_setting('location_btn_text', 'View Full City & Location Guide');

try {
    $stmtLoc = $pdo->query("SELECT * FROM location_spots WHERE is_featured = 1 ORDER BY display_order ASC, id ASC LIMIT 4");
    $homeLocationSpots = $stmtLoc->fetchAll();
} catch (Throwable $e) {
    $homeLocationSpots = [];
}
?>
<section class="py-20 bg-white border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 reveal reveal-up">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($locBadge) ?></span>
            <h2 class="font-headline text-3xl sm:text-4xl font-bold text-onyx-charcoal mt-2">
                <?= e($locTitle) ?>
            </h2>
            <p class="text-stone-500 text-base mt-3">
                <?= e($locSubtitle) ?>
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php if (!empty($homeLocationSpots)): ?>
                <?php 
                $j = 1;
                foreach ($homeLocationSpots as $spot): 
                    $spotTitle = __td($spot, 'title', $spot['title']);
                    $spotDesc = __td($spot, 'description', $spot['description']);
                    $spotIcon = !empty($spot['icon']) ? $spot['icon'] : 'location_on';
                    $spotSlug = !empty($spot['slug']) ? $spot['slug'] : $spot['id'];
                    $spotImg = !empty($spot['image_url']) ? $spot['image_url'] : 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=800&q=80';
                ?>
                <a href="<?= url('/location/' . urlencode($spotSlug)) ?>" 
                   class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-2xs hover:shadow-xl hover:border-[#343c0a]/40 transition duration-300 flex flex-col justify-between group reveal reveal-scale stagger-<?= $j++ ?>">
                    <div>
                        <!-- Photo on Top of Card -->
                        <div class="h-48 relative overflow-hidden bg-stone-100">
                            <img src="<?= e($spotImg) ?>" alt="<?= e($spotTitle) ?>" class="w-full h-full object-cover group-hover:scale-108 transition duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                            
                            <!-- Badges on Image -->
                            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 bg-black/60 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-1 rounded-full border border-white/20">
                                    <span class="material-symbols-outlined text-xs text-[#dfe8a6]"><?= e($spotIcon) ?></span>
                                    <span><?= e($spot['distance_time']) ?></span>
                                </span>
                            </div>

                            <div class="absolute bottom-3 left-3 right-3 text-white">
                                <h3 class="font-headline font-bold text-base sm:text-lg text-white group-hover:text-[#dfe8a6] transition line-clamp-1"><?= e($spotTitle) ?></h3>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-5">
                            <p class="text-xs text-stone-500 line-clamp-2 leading-relaxed">
                                <?= e($spotDesc) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Footer Action Link -->
                    <div class="px-5 pb-5 pt-0 flex items-center justify-between text-xs font-bold text-[#343c0a] group-hover:text-deep-olive border-t border-stone-100 pt-3 mt-auto">
                        <span>Attraction Guide</span>
                        <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center mt-12 reveal reveal-up">
            <a href="<?= url('/location') ?>" class="inline-flex items-center gap-2 bg-[#343c0a] hover:bg-deep-olive text-white px-7 py-3 rounded text-sm font-semibold tracking-wide transition shadow-sm hover:shadow btn-shimmer">
                <span><?= e($locBtnText) ?></span>
                <span class="material-symbols-outlined text-base">explore</span>
            </a>
        </div>
    </div>
</section>

<script>
function copyPromoCode(code) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            alert('Promo Code "' + code + '" copied to clipboard!');
        });
    } else {
        const temp = document.createElement('textarea');
        temp.value = code;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        alert('Promo Code "' + code + '" copied to clipboard!');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
