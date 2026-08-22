<?php
/**
 * Indra Hotel - Prime Location & City Guide CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'Prime Location & City Guide';

// Handle Save Section Settings
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_location_settings'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/locations.php');
        exit;
    }

    $settings = [
        'location_badge' => trim($_POST['location_badge'] ?? 'Prime Location'),
        'location_title' => trim($_POST['location_title'] ?? 'Explore Phnom Penh from Tuol Kork'),
        'location_subtitle' => trim($_POST['location_subtitle'] ?? ''),
        'location_airport_info' => trim($_POST['location_airport_info'] ?? ''),
        'location_map_embed' => trim($_POST['location_map_embed'] ?? '')
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    process_hero_settings_post();

    get_setting('__refresh__');
    set_flash('success', 'Location overview settings updated successfully.');
    header('Location: ' . BASE_URL . '/admin/locations.php');

    exit;
}

// Handle Spot Actions (Save / Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['spot_action'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/locations.php');
        exit;
    }

    $action = $_POST['spot_action'];

    if ($action === 'delete') {
        $spotId = (int)($_POST['spot_id'] ?? 0);
        if ($spotId > 0) {
            $pdo->prepare("DELETE FROM location_spots WHERE id = ?")->execute([$spotId]);
            set_flash('success', 'Location spot removed.');
        }
        header('Location: ' . BASE_URL . '/admin/locations.php');
        exit;
    }

    if ($action === 'save') {
        $spotId = (int)($_POST['spot_id'] ?? 0);
        $titleEn = trim($_POST['title_en'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descEn = trim($_POST['description_en'] ?? '');
        $distance = trim($_POST['distance_time'] ?? '5 Minutes');
        $category = $_POST['category'] ?? 'attraction';
        $icon = trim($_POST['icon'] ?? 'location_on');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $openingHours = trim($_POST['opening_hours'] ?? '');
        $admissionFee = trim($_POST['admission_fee'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $tips = trim($_POST['tips'] ?? '');
        $mapEmbed = trim($_POST['map_embed'] ?? '');
        $order = (int)($_POST['display_order'] ?? 99);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        // Auto-generate slug if blank
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $titleEn), '-'));
        }

        // Multi-photo gallery images array
        $galleryImages = $_POST['gallery_images'] ?? [];
        $cleanGallery = [];
        if (is_array($galleryImages)) {
            foreach ($galleryImages as $img) {
                $img = trim($img);
                if (!empty($img) && !in_array($img, $cleanGallery)) {
                    $cleanGallery[] = $img;
                }
            }
        }
        $galleryJson = !empty($cleanGallery) ? json_encode($cleanGallery, JSON_UNESCAPED_SLASHES) : null;

        // Build Multi-language Translations JSON
        $translations = [
            'km' => [
                'title' => trim($_POST['title_km'] ?? ''),
                'description' => trim($_POST['description_km'] ?? '')
            ],
            'zh' => [
                'title' => trim($_POST['title_zh'] ?? ''),
                'description' => trim($_POST['description_zh'] ?? '')
            ],
            'ko' => [
                'title' => trim($_POST['title_ko'] ?? ''),
                'description' => trim($_POST['description_ko'] ?? '')
            ]
        ];
        $transJson = json_encode($translations, JSON_UNESCAPED_UNICODE);

        if (empty($titleEn)) {
            set_flash('error', 'Spot title (English) is required.');
            header('Location: ' . BASE_URL . '/admin/locations.php');
            exit;
        }

        if ($spotId > 0) {
            $stmt = $pdo->prepare("UPDATE location_spots SET slug = ?, title = ?, distance_time = ?, category = ?, icon = ?, image_url = ?, gallery_json = ?, opening_hours = ?, admission_fee = ?, address = ?, tips = ?, map_embed = ?, description = ?, translations_json = ?, display_order = ?, is_featured = ? WHERE id = ?");
            $stmt->execute([$slug, $titleEn, $distance, $category, $icon, $imageUrl, $galleryJson, $openingHours, $admissionFee, $address, $tips, $mapEmbed, $descEn, $transJson, $order, $isFeatured, $spotId]);
            set_flash('success', "Location spot '{$titleEn}' updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO location_spots (slug, title, distance_time, category, icon, image_url, gallery_json, opening_hours, admission_fee, address, tips, map_embed, description, translations_json, display_order, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$slug, $titleEn, $distance, $category, $icon, $imageUrl, $galleryJson, $openingHours, $admissionFee, $address, $tips, $mapEmbed, $descEn, $transJson, $order, $isFeatured]);
            set_flash('success', "New location spot '{$titleEn}' added.");
        }

        header('Location: ' . BASE_URL . '/admin/locations.php');
        exit;
    }
}

// Fetch Current Location Spots
$stmtSpots = $pdo->query("SELECT * FROM location_spots ORDER BY display_order ASC, id ASC");
$spots = $stmtSpots->fetchAll();

// Current Settings
$locBadge = get_setting('location_badge', 'Prime Location');
$locTitle = get_setting('location_title', 'Explore Phnom Penh from Tuol Kork');
$locSubtitle = get_setting('location_subtitle', 'Nestled in the upscale, tranquil district of Tuol Kork with effortless connectivity to Phnom Penh\'s premier cultural landmarks.');
$locAirportInfo = get_setting('location_airport_info', 'Phnom Penh International Airport (PNH) is approximately a 30-minute drive away. Private VIP chauffeured transfers can be arranged directly with our concierge team.');
$locMapEmbed = get_setting('location_map_embed', 'https://maps.google.com/maps?q=' . HOTEL_LATITUDE . ',' . HOTEL_LONGITUDE . '&hl=en&z=15&output=embed');

$categories = [
    'cultural' => ['name' => 'Historical & Cultural', 'color' => 'bg-amber-50 text-amber-800 border-amber-200'],
    'shopping' => ['name' => 'Shopping & Malls', 'color' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'attraction' => ['name' => 'City Attractions', 'color' => 'bg-blue-50 text-blue-800 border-blue-200'],
    'dining' => ['name' => 'Dining & Nightlife', 'color' => 'bg-rose-50 text-rose-800 border-rose-200'],
    'transit' => ['name' => 'Transit & Airport', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
    'general' => ['name' => 'General Spot', 'color' => 'bg-stone-100 text-stone-700 border-stone-200']
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8 max-w-6xl">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Content Management</span>
                <span class="text-xs text-stone-400">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Destination Guide</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Prime Location & City Guide</h1>
            <p class="text-xs text-stone-500 mt-1">Manage destination highlights, detail pages, gallery photos, travel times, and surrounding Phnom Penh landmarks.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/location.php" target="_blank" class="px-4 py-2.5 rounded-lg border border-stone-300 text-stone-700 text-xs font-semibold hover:bg-stone-50 transition flex items-center gap-1.5 shadow-2xs">
                <span class="material-symbols-outlined text-base">open_in_new</span>
                <span>View City Guide</span>
            </a>
            <button onclick="openSpotModal(0)" 
                    class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>Add Attraction Spot</span>
            </button>
        </div>
    </div>

    <!-- Section 1: Overview & Section Settings -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
        <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
            <div class="w-10 h-10 rounded-xl bg-stone-100 text-[#343c0a] flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">pin_drop</span>
            </div>
            <div>
                <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Location Section Overview</h2>
                <p class="text-xs text-stone-500">Configure the header text, airport transfer details, and interactive map embed displayed on the homepage and location guide.</p>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/admin/locations.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="save_location_settings" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Section Badge</label>
                    <input type="text" name="location_badge" value="<?= e($locBadge) ?>" 
                           class="w-full text-xs border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Section Heading Title</label>
                    <input type="text" name="location_title" value="<?= e($locTitle) ?>" 
                           class="w-full text-xs font-bold border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Section Narrative / Subtitle</label>
                    <textarea name="location_subtitle" rows="2" 
                              class="w-full text-xs border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e($locSubtitle) ?></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Airport & Transfer Guide</label>
                    <textarea name="location_airport_info" rows="2" 
                              class="w-full text-xs border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e($locAirportInfo) ?></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Google Maps Embed URL</label>
                    <input type="text" name="location_map_embed" value="<?= e($locMapEmbed) ?>" 
                           placeholder="https://maps.google.com/maps?q=...&output=embed"
                           class="w-full text-xs font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <input type="hidden" name="hero_page_keys[]" value="location">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-4 border-t border-stone-100">
                <div>
                    <?= render_image_uploader_field("hero_location_image", get_hero_setting('location', 'image', 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=1600&q=80'), 'Location Hero Background Image', 'brand', [
                        'required' => false,
                        'helper' => 'Banner image for Location & City Guide page'
                    ]) ?>
                </div>
                <div>
                    <?= render_video_uploader_field("hero_location_video", get_hero_setting('location', 'video', ''), 'Location Hero Background Video', 'videos', [
                        'required' => false,
                        'helper' => 'Optional video loop for Location & City Guide hero'
                    ]) ?>
                </div>
            </div>


            <div class="flex justify-end pt-2 border-t border-stone-100">
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider shadow transition cursor-pointer">
                    Save Overview Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Section 2: Surrounding Attractions & Points of Interest -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-headline font-bold text-xl text-onyx-charcoal">Surrounding Attractions & Landmarks</h2>
                <p class="text-xs text-stone-500">Points of interest with dedicated visitor detail pages.</p>
            </div>
            <span class="text-xs font-bold text-stone-400 bg-stone-100 px-3 py-1 rounded-full"><?= count($spots) ?> Total Spots</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($spots as $spot): 
                $catInfo = $categories[$spot['category']] ?? $categories['general'];
                $spotTrans = !empty($spot['translations_json']) ? json_decode($spot['translations_json'], true) : [];
                $spotSlug = !empty($spot['slug']) ? $spot['slug'] : $spot['id'];
            ?>
            <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-2xs hover:shadow-md transition flex flex-col justify-between group">
                <div class="space-y-3">
                    
                    <?php if (!empty($spot['image_url'])): ?>
                    <div class="h-32 -mx-5 -mt-5 mb-3 overflow-hidden rounded-t-2xl bg-stone-100 relative">
                        <img src="<?= e($spot['image_url']) ?>" alt="<?= e($spot['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <a href="<?= BASE_URL ?>/location-detail.php?slug=<?= e($spotSlug) ?>" target="_blank" class="absolute bottom-2 right-2 bg-black/70 hover:bg-black text-white text-[10px] font-bold px-2 py-1 rounded backdrop-blur-xs flex items-center gap-1 transition">
                            <span>View Detail</span>
                            <span class="material-symbols-outlined text-xs">open_in_new</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-start justify-between gap-2">
                        <div class="w-10 h-10 rounded-xl bg-stone-100 text-[#343c0a] group-hover:bg-[#dfe8a6]/40 flex items-center justify-center shrink-0 transition">
                            <span class="material-symbols-outlined text-2xl"><?= e($spot['icon']) ?></span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?= $catInfo['color'] ?> uppercase tracking-wider">
                            <?= e($catInfo['name']) ?>
                        </span>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-bold text-[#4B5320] mb-1">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <span><?= e($spot['distance_time']) ?></span>
                        </div>
                        <h3 class="font-headline font-bold text-base text-onyx-charcoal group-hover:text-[#343c0a] transition"><?= e($spot['title']) ?></h3>
                        <p class="text-xs text-stone-500 line-clamp-2 mt-1 leading-relaxed"><?= e($spot['description']) ?></p>
                    </div>

                    <!-- Multi-language Badges -->
                    <div class="flex items-center gap-1 pt-1">
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">EN</span>
                        <?php foreach (['km' => 'KM', 'zh' => 'ZH', 'ko' => 'KO'] as $lKey => $lLabel): ?>
                            <?php if (!empty($spotTrans[$lKey]['title'])): ?>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700"><?= $lLabel ?></span>
                            <?php else: ?>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-stone-100 text-stone-400"><?= $lLabel ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <?php if ($spot['is_featured']): ?>
                            <span class="text-[10px] font-bold text-[#343c0a] bg-[#dfe8a6]/40 px-2 py-0.5 rounded-full">Featured</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" 
                                onclick='editSpotModal(<?= json_encode($spot) ?>)'
                                class="text-stone-600 hover:text-stone-900 font-semibold inline-flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-sm">edit</span>
                            <span>Edit</span>
                        </button>

                        <form action="<?= BASE_URL ?>/admin/locations.php" method="POST" class="inline" onsubmit="return confirm('Delete <?= e(addslashes($spot['title'])) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                            <input type="hidden" name="spot_action" value="delete">
                            <input type="hidden" name="spot_id" value="<?= (int)$spot['id'] ?>">
                            <button type="submit" class="text-rose-600 hover:text-rose-800 p-1 cursor-pointer" title="Delete Spot">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Spot Create / Edit Modal -->
<div id="spot-modal" class="fixed inset-0 bg-black/60 z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-8">
    <div class="min-h-full flex items-start justify-center">
        <div class="bg-white rounded-2xl max-w-3xl w-full p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6 my-6 relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-stone-200">
                <div>
                    <h3 id="spot-modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Add Attraction Spot</h3>
                    <p class="text-[11px] text-stone-500">Configure title, details, gallery photos, travel distance, icon, and dynamic translations.</p>
                </div>
                <button type="button" onclick="closeSpotModal()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100 transition cursor-pointer">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

        <form action="<?= BASE_URL ?>/admin/locations.php" method="POST" class="space-y-5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="spot_action" value="save">
            <input type="hidden" name="spot_id" id="modal_spot_id" value="0">

            <!-- Spot Title (Multi-Language) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block font-bold text-stone-700 uppercase">Spot Title *</label>
                    <div class="flex gap-1 bg-stone-100 p-0.5 rounded-md">
                        <button type="button" onclick="switchSpotLang('title', 'en')" id="tab-spot-title-en" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs">EN</button>
                        <button type="button" onclick="switchSpotLang('title', 'km')" id="tab-spot-title-km" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KM</button>
                        <button type="button" onclick="switchSpotLang('title', 'zh')" id="tab-spot-title-zh" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">ZH</button>
                        <button type="button" onclick="switchSpotLang('title', 'ko')" id="tab-spot-title-ko" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KO</button>
                    </div>
                </div>
                <input type="text" name="title_en" id="modal_spot_title_en" required placeholder="e.g. Wat Phnom Sanctuary (English)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold">
                <input type="text" name="title_km" id="modal_spot_title_km" placeholder="e.g. វត្តភ្នំ (Khmer)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
                <input type="text" name="title_zh" id="modal_spot_title_zh" placeholder="e.g. 塔山寺 (Chinese)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
                <input type="text" name="title_ko" id="modal_spot_title_ko" placeholder="e.g. 왓 프놈 (Korean)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
            </div>

            <!-- Slug, Distance, Category -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">URL Slug</label>
                    <input type="text" name="slug" id="modal_spot_slug" placeholder="e.g. wat-phnom-sanctuary" 
                           class="w-full font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Travel Distance / Time *</label>
                    <input type="text" name="distance_time" id="modal_spot_distance" required placeholder="e.g. 10 Minutes Drive, 3 Minutes Walk" 
                           class="w-full border border-stone-300 rounded-lg p-2.5 font-semibold focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Category</label>
                    <select name="category" id="modal_spot_category" class="w-full border border-stone-300 rounded-lg p-2.5 font-semibold">
                        <option value="cultural">Historical & Cultural</option>
                        <option value="shopping">Shopping & Malls</option>
                        <option value="attraction">City Attractions</option>
                        <option value="dining">Dining & Nightlife</option>
                        <option value="transit">Transit & Airport</option>
                        <option value="general">General Spot</option>
                    </select>
                </div>
            </div>

            <!-- Additional Detail Specifications -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-stone-50 p-4 rounded-xl border border-stone-200">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Opening Hours</label>
                    <input type="text" name="opening_hours" id="modal_spot_hours" placeholder="e.g. 07:00 AM - 06:00 PM Daily" 
                           class="w-full bg-white border border-stone-300 rounded-lg p-2 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Admission Fee</label>
                    <input type="text" name="admission_fee" id="modal_spot_fee" placeholder="e.g. $1.00 USD / Free Admission" 
                           class="w-full bg-white border border-stone-300 rounded-lg p-2 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Physical Address</label>
                    <input type="text" name="address" id="modal_spot_address" placeholder="e.g. Norodom Blvd, Daun Penh" 
                           class="w-full bg-white border border-stone-300 rounded-lg p-2 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <!-- Spot Description (Multi-Language) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block font-bold text-stone-700 uppercase">Description Narrative *</label>
                    <div class="flex gap-1 bg-stone-100 p-0.5 rounded-md">
                        <button type="button" onclick="switchSpotLang('desc', 'en')" id="tab-spot-desc-en" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs">EN</button>
                        <button type="button" onclick="switchSpotLang('desc', 'km')" id="tab-spot-desc-km" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KM</button>
                        <button type="button" onclick="switchSpotLang('desc', 'zh')" id="tab-spot-desc-zh" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">ZH</button>
                        <button type="button" onclick="switchSpotLang('desc', 'ko')" id="tab-spot-desc-ko" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KO</button>
                    </div>
                </div>
                <textarea name="description_en" id="modal_spot_desc_en" rows="3" required placeholder="Description in English..." class="w-full border border-stone-300 rounded-lg p-2.5"></textarea>
                <textarea name="description_km" id="modal_spot_desc_km" rows="3" placeholder="Description in Khmer..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
                <textarea name="description_zh" id="modal_spot_desc_zh" rows="3" placeholder="Description in Chinese..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
                <textarea name="description_ko" id="modal_spot_desc_ko" rows="3" placeholder="Description in Korean..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
            </div>

            <!-- Concierge Insider Tips -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Concierge Insider Tips & Advice</label>
                <textarea name="tips" id="modal_spot_tips" rows="2" placeholder="e.g. Wear modest attire covering shoulders and knees. Best visited in the morning." 
                          class="w-full border border-stone-300 rounded-lg p-2.5"></textarea>
            </div>

            <!-- Material Icon Picker -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Material Icon Name</label>
                <div class="flex gap-2 items-center">
                    <div class="w-9 h-9 rounded-lg bg-stone-100 flex items-center justify-center text-[#343c0a] shrink-0 border border-stone-200">
                        <span id="modal-spot-icon-preview" class="material-symbols-outlined text-xl">location_on</span>
                    </div>
                    <input type="text" name="icon" id="modal_spot_icon" value="location_on" 
                           oninput="document.getElementById('modal-spot-icon-preview').textContent = this.value.trim() || 'location_on'"
                           class="w-full font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <!-- Icon Quick Chips -->
                <div class="flex flex-wrap gap-1.5 pt-2">
                    <?php 
                    $locIcons = ['temple_buddhist', 'account_balance', 'storefront', 'shopping_bag', 'flight', 'park', 'restaurant', 'local_cafe', 'museum', 'directions_walk', 'directions_car', 'explore', 'map', 'tour'];
                    foreach ($locIcons as $li): ?>
                        <button type="button" onclick="selectSpotIcon('<?= $li ?>')" class="px-2 py-1 rounded bg-stone-100 hover:bg-[#dfe8a6]/50 text-stone-700 text-[10px] font-mono transition cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs"><?= $li ?></span>
                            <span><?= $li ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Primary Featured Image Uploader -->
            <?= render_image_uploader_field('image_url', '', 'Primary Cover / Featured Image', 'locations', ['required' => false]) ?>

            <!-- Multi-Photo Gallery Uploader -->
            <div class="space-y-3 p-4 bg-stone-50 rounded-xl border border-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block font-bold text-stone-700 uppercase">Additional Gallery Photos</label>
                        <p class="text-[11px] text-stone-400">Upload multiple photos for the attraction photo gallery preview.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold cursor-pointer transition shadow-2xs">
                            <span class="material-symbols-outlined text-sm">cloud_upload</span>
                            <span>Upload File</span>
                            <input type="file" accept="image/*" class="hidden" onchange="uploadSpotGalleryPhoto(this)">
                        </label>
                        <button type="button" onclick="openMediaLibraryPicker('spot_gallery_append', 'image', 'locations')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 text-xs font-bold transition cursor-pointer shadow-2xs">
                            <span class="material-symbols-outlined text-sm text-[#343c0a]">photo_library</span>
                            <span>Choose from Library</span>
                        </button>
                    </div>

                </div>

                <div id="spot-gallery-container" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 pt-1">
                    <!-- Dynamic gallery cards injected via JS -->
                </div>
            </div>

            <!-- Featured & Order -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="flex items-center">
                    <label class="flex items-center gap-2 cursor-pointer font-bold text-stone-700">
                        <input type="checkbox" name="is_featured" id="modal_spot_featured" value="1" checked class="rounded text-[#343c0a] focus:ring-[#343c0a]">
                        <span>Feature on Home Page</span>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Display Sort Order</label>
                    <input type="number" name="display_order" id="modal_spot_order" value="99" min="1" max="999" class="w-full border border-stone-300 rounded-lg p-2.5">
                </div>
            </div>

            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeSpotModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save Spot</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
function openSpotModal(id) {
    document.getElementById('modal_spot_id').value = 0;
    document.getElementById('modal_spot_slug').value = '';
    document.getElementById('modal_spot_title_en').value = '';
    document.getElementById('modal_spot_title_km').value = '';
    document.getElementById('modal_spot_title_zh').value = '';
    document.getElementById('modal_spot_title_ko').value = '';
    
    document.getElementById('modal_spot_desc_en').value = '';
    document.getElementById('modal_spot_desc_km').value = '';
    document.getElementById('modal_spot_desc_zh').value = '';
    document.getElementById('modal_spot_desc_ko').value = '';

    document.getElementById('modal_spot_hours').value = '';
    document.getElementById('modal_spot_fee').value = '';
    document.getElementById('modal_spot_address').value = '';
    document.getElementById('modal_spot_tips').value = '';

    document.getElementById('modal_spot_distance').value = '10 Minutes Drive';
    document.getElementById('modal_spot_category').value = 'attraction';
    document.getElementById('modal_spot_icon').value = 'location_on';
    document.getElementById('modal-spot-icon-preview').textContent = 'location_on';
    document.getElementById('modal_spot_featured').checked = true;
    document.getElementById('modal_spot_order').value = 99;

    document.getElementById('spot-gallery-container').innerHTML = '';
    clearImageUpload('image_url');

    document.getElementById('spot-modal-title').textContent = 'Add Attraction Spot';
    switchSpotLang('title', 'en');
    switchSpotLang('desc', 'en');

    const modal = document.getElementById('spot-modal');
    modal.classList.remove('hidden');
    modal.scrollTop = 0;
}

function editSpotModal(spot) {
    document.getElementById('modal_spot_id').value = spot.id;
    document.getElementById('modal_spot_slug').value = spot.slug || '';
    document.getElementById('modal_spot_title_en').value = spot.title || '';
    document.getElementById('modal_spot_desc_en').value = spot.description || '';
    document.getElementById('modal_spot_distance').value = spot.distance_time || '';
    document.getElementById('modal_spot_category').value = spot.category || 'attraction';
    document.getElementById('modal_spot_icon').value = spot.icon || 'location_on';
    document.getElementById('modal-spot-icon-preview').textContent = spot.icon || 'location_on';
    document.getElementById('modal_spot_featured').checked = (spot.is_featured == 1);
    document.getElementById('modal_spot_order').value = spot.display_order || 99;

    document.getElementById('modal_spot_hours').value = spot.opening_hours || '';
    document.getElementById('modal_spot_fee').value = spot.admission_fee || '';
    document.getElementById('modal_spot_address').value = spot.address || '';
    document.getElementById('modal_spot_tips').value = spot.tips || '';

    // Translations
    let trans = {};
    try {
        if (spot.translations_json) {
            trans = JSON.parse(spot.translations_json);
        }
    } catch(e) {}

    document.getElementById('modal_spot_title_km').value = trans.km?.title || '';
    document.getElementById('modal_spot_title_zh').value = trans.zh?.title || '';
    document.getElementById('modal_spot_title_ko').value = trans.ko?.title || '';

    document.getElementById('modal_spot_desc_km').value = trans.km?.description || '';
    document.getElementById('modal_spot_desc_zh').value = trans.zh?.description || '';
    document.getElementById('modal_spot_desc_ko').value = trans.ko?.description || '';

    if (spot.image_url) {
        handleImageUrlInput(spot.image_url, 'image_url');
    } else {
        clearImageUpload('image_url');
    }

    // Populate Gallery Container
    const gContainer = document.getElementById('spot-gallery-container');
    gContainer.innerHTML = '';
    let galleryArr = [];
    try {
        if (spot.gallery_json) {
            galleryArr = JSON.parse(spot.gallery_json);
        }
    } catch(e) {}

    if (Array.isArray(galleryArr)) {
        galleryArr.forEach(url => {
            appendSpotGalleryCard(url);
        });
    }

    document.getElementById('spot-modal-title').textContent = 'Edit Attraction Spot';
    switchSpotLang('title', 'en');
    switchSpotLang('desc', 'en');

    const modal = document.getElementById('spot-modal');
    modal.classList.remove('hidden');
    modal.scrollTop = 0;
}

function closeSpotModal() {
    document.getElementById('spot-modal').classList.add('hidden');
}

function selectSpotIcon(iconName) {
    document.getElementById('modal_spot_icon').value = iconName;
    document.getElementById('modal-spot-icon-preview').textContent = iconName;
}

function switchSpotLang(field, lang) {
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const input = document.getElementById('modal_spot_' + field + '_' + l);
        const tab = document.getElementById('tab-spot-' + field + '-' + l);
        if (input && tab) {
            if (l === lang) {
                input.classList.remove('hidden');
                tab.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs';
            } else {
                input.classList.add('hidden');
                tab.className = 'px-2 py-0.5 rounded text-[10px] font-bold text-stone-500';
            }
        }
    });
}

window.appendSpotGalleryCard = function(url) {
    const container = document.getElementById('spot-gallery-container');
    if (!container) return;
    const card = document.createElement('div');
    card.className = 'relative bg-white rounded-lg border border-stone-200 overflow-hidden group h-20 shadow-2xs';
    card.innerHTML = `
        <img src="${url}" alt="Gallery Photo" class="w-full h-full object-cover">
        <input type="hidden" name="gallery_images[]" value="${url}">
        <button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/70 hover:bg-rose-600 text-white flex items-center justify-center text-[10px] transition cursor-pointer" title="Remove">
            <span class="material-symbols-outlined text-xs">close</span>
        </button>
    `;
    container.appendChild(card);
};


async function uploadSpotGalleryPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const formData = new FormData();
    formData.append('image', file);
    formData.append('folder', 'locations');

        let endpointUrl = window.getApiEndpoint ? window.getApiEndpoint('/api/upload.php') : '/api/upload.php';
        if (window.location.protocol === 'https:' && endpointUrl.startsWith('http:')) {
            endpointUrl = endpointUrl.replace(/^http:/, 'https:');
        }
        const res = await fetch(endpointUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success && data.url) {
            appendSpotGalleryCard(data.url);
        } else {
            alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
    } catch(e) {
        alert('Network error during upload: ' + e.message);
    }
    input.value = '';
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
