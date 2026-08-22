<?php
/**
 * Indra Hotel - Room Create / Edit Form with Multi-Language (i18n) Content Management
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();

$roomId = (int)($_POST['room_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
$isEdit = $roomId > 0;
$adminTitle = $isEdit ? 'Edit Accommodation' : 'New Accommodation';

$room = [
    'name' => '',
    'slug' => '',
    'category' => 'Deluxe Room',
    'tagline' => '',
    'description' => '',
    'price_per_night' => '85.00',
    'size_sqm' => 38,
    'bed_type' => '1 King Bed',
    'capacity_adults' => 2,
    'capacity_children' => 1,
    'view_type' => 'City View',
    'image_url' => '',
    'amenities_json' => '[]',
    'status' => 'available',
    'is_featured' => 0,
    'translations_json' => '{}'
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $room = $existing;
    } else {
        set_flash('error', 'Accommodation not found.');
        header('Location: ' . BASE_URL . '/admin/accommodations.php');
        exit;
    }
}

$currentAmenities = !empty($room['amenities_json']) ? json_decode($room['amenities_json'], true) : [];
$masterAmenities = get_all_master_amenities();
$allAmenitiesList = array_unique(array_merge($masterAmenities, $currentAmenities));
$transData = !empty($room['translations_json']) ? json_decode($room['translations_json'], true) : [];
if (!is_array($transData)) {
    $transData = [];
}
$availableRoomTypes = get_all_room_types();

// English values with safe fallback to main room properties
$enNameVal = !empty($transData['en']['name']) ? $transData['en']['name'] : ($room['name'] ?? '');
$enTaglineVal = !empty($transData['en']['tagline']) ? $transData['en']['tagline'] : ($room['tagline'] ?? '');
$enDescVal = !empty($transData['en']['description']) ? $transData['en']['description'] : ($room['description'] ?? '');

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please submit again.');
        header('Location: ' . BASE_URL . '/admin/room-form.php' . ($isEdit ? '?id=' . $roomId : ''));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'Deluxe Room');
    $tagline = trim($_POST['tagline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price_per_night'] ?? 85.00);
    $size = (int)($_POST['size_sqm'] ?? 35);
    $bedType = trim($_POST['bed_type'] ?? '1 King Bed');
    $adults = (int)($_POST['capacity_adults'] ?? 2);
    $children = (int)($_POST['capacity_children'] ?? 1);
    $viewType = trim($_POST['view_type'] ?? 'City View');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $selectedAmenities = $_POST['amenities'] ?? [];
    
    // Multi-Language Translations Payload
    $translationsPayload = [
        'en' => [
            'name' => !empty($_POST['trans_en_name']) ? trim($_POST['trans_en_name']) : $name,
            'tagline' => !empty($_POST['trans_en_tagline']) ? trim($_POST['trans_en_tagline']) : $tagline,
            'description' => !empty($_POST['trans_en_desc']) ? trim($_POST['trans_en_desc']) : $description
        ],
        'km' => [
            'name' => trim($_POST['trans_km_name'] ?? ''),
            'tagline' => trim($_POST['trans_km_tagline'] ?? ''),
            'description' => trim($_POST['trans_km_desc'] ?? '')
        ],
        'zh' => [
            'name' => trim($_POST['trans_zh_name'] ?? ''),
            'tagline' => trim($_POST['trans_zh_tagline'] ?? ''),
            'description' => trim($_POST['trans_zh_desc'] ?? '')
        ],
        'ko' => [
            'name' => trim($_POST['trans_ko_name'] ?? ''),
            'tagline' => trim($_POST['trans_ko_tagline'] ?? ''),
            'description' => trim($_POST['trans_ko_desc'] ?? '')
        ]
    ];
    $transJson = json_encode($translationsPayload, JSON_UNESCAPED_UNICODE);


    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
    }

    if (empty($name) || empty($description)) {
        set_flash('error', 'Please provide a name and description.');
    } else {
        $galleryImages = $_POST['gallery_images'] ?? [];
        // If gallery is empty but main image exists, include main image
        if (empty($galleryImages) && !empty($imageUrl)) {
            $galleryImages = [$imageUrl];
        }
        $galleryJson = json_encode(array_values($galleryImages));
        $amenitiesJson = json_encode(array_values($selectedAmenities));
        
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE rooms SET name = ?, slug = ?, category = ?, tagline = ?, description = ?, price_per_night = ?, size_sqm = ?, bed_type = ?, capacity_adults = ?, capacity_children = ?, view_type = ?, image_url = ?, gallery_json = ?, amenities_json = ?, translations_json = ?, status = ?, is_featured = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $category, $tagline, $description, $price, $size, $bedType, $adults, $children, $viewType, $imageUrl, $galleryJson, $amenitiesJson, $transJson, $status, $isFeatured, $roomId]);
                set_flash('success', "Accommodation '{$name}' updated successfully.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO rooms (name, slug, category, tagline, description, price_per_night, size_sqm, bed_type, capacity_adults, capacity_children, view_type, image_url, gallery_json, amenities_json, translations_json, status, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $category, $tagline, $description, $price, $size, $bedType, $adults, $children, $viewType, $imageUrl, $galleryJson, $amenitiesJson, $transJson, $status, $isFeatured]);
                set_flash('success', "New accommodation '{$name}' created successfully.");
            }
            header('Location: ' . BASE_URL . '/admin/accommodations.php');
            exit;
        } catch (Throwable $e) {
            set_flash('error', 'Error saving accommodation: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="max-w-4xl space-y-6">
    
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal"><?= $isEdit ? 'Edit Suite Details' : 'Add New Accommodation' ?></h2>
            <p class="text-xs text-stone-500">Configure multi-language content, pricing, specifications, imagery, and amenities.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/accommodations.php" class="text-xs font-semibold text-stone-600 hover:text-stone-900">
            ← Back to Accommodations
        </a>
    </div>

    <form id="room-edit-form" action="<?= BASE_URL ?>/admin/room-form.php<?= $isEdit ? '?id=' . $roomId : '' ?>" method="POST" onsubmit="return validateRoomForm();" class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
        <input type="hidden" name="room_id" value="<?= $roomId ?>">

        <!-- Multi-Language Dynamic Content Section -->
        <div class="space-y-6 p-6 sm:p-8 rounded-2xl bg-stone-50 border border-stone-200">
            
            <!-- Section Header & Master Global Language Switcher -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
                <div>
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320]">translate</span>
                        <span>Multi-Language Content (Dynamic Localization)</span>
                    </h3>
                    <p class="text-xs text-stone-500 mt-0.5">Switch tabs on each input box to provide localized content. If a language is left empty, it directly defaults to English.</p>
                </div>

                <!-- Master Quick Switcher -->
                <div class="flex items-center gap-1.5 p-1 bg-white rounded-xl border border-stone-200 shadow-2xs">
                    <span class="text-[10px] font-bold text-stone-400 uppercase px-2">Switch All:</span>
                    <button type="button" onclick="switchAllFieldsLang('en')" id="master-tab-en" class="master-lang-btn px-2.5 py-1 rounded-lg text-xs font-bold bg-[#343c0a] text-white">
                        🇬🇧 EN
                    </button>
                    <button type="button" onclick="switchAllFieldsLang('km')" id="master-tab-km" class="master-lang-btn px-2.5 py-1 rounded-lg text-xs font-bold text-stone-600 hover:bg-stone-100">
                        🇰🇭 KM
                    </button>
                    <button type="button" onclick="switchAllFieldsLang('zh')" id="master-tab-zh" class="master-lang-btn px-2.5 py-1 rounded-lg text-xs font-bold text-stone-600 hover:bg-stone-100">
                        🇨🇳 ZH
                    </button>
                    <button type="button" onclick="switchAllFieldsLang('ko')" id="master-tab-ko" class="master-lang-btn px-2.5 py-1 rounded-lg text-xs font-bold text-stone-600 hover:bg-stone-100">
                        🇰🇷 KO
                    </button>
                </div>
            </div>

            <!-- 1. Room Name (Per-Input Tabbed Box) -->
            <div class="field-lang-group bg-white p-4 sm:p-5 rounded-xl border border-stone-200 space-y-3" data-field="name">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Room Name *</label>
                        <span class="text-[11px] text-stone-400 font-normal">(Title displayed on room cards & bookings)</span>
                    </div>
                    
                    <!-- Language Switch Pills for Name -->
                    <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded-lg border border-stone-200 text-xs">
                        <button type="button" onclick="switchFieldLang('name', 'en')" class="field-tab-btn-name px-2.5 py-1 rounded-md font-bold text-xs bg-[#343c0a] text-white" data-lang="en">
                            🇬🇧 EN
                        </button>
                        <button type="button" onclick="switchFieldLang('name', 'km')" class="field-tab-btn-name px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="km">
                            🇰🇭 KM <?= !empty($transData['km']['name']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('name', 'zh')" class="field-tab-btn-name px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="zh">
                            🇨🇳 ZH <?= !empty($transData['zh']['name']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('name', 'ko')" class="field-tab-btn-name px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="ko">
                            🇰🇷 KO <?= !empty($transData['ko']['name']) ? '●' : '' ?>
                        </button>
                    </div>
                </div>

                <!-- Input EN (Primary) -->
                <div class="field-input-box-name" id="field-box-name-en">
                    <input type="text" name="name" value="<?= e($enNameVal) ?>" id="en_room_name" placeholder="e.g. Deluxe King Room"
                           class="w-full text-sm font-medium border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Primary English name. Required.</p>
                </div>


                <!-- Input KM -->
                <div class="field-input-box-name hidden" id="field-box-name-km">
                    <input type="text" name="trans_km_name" value="<?= e($transData['km']['name'] ?? '') ?>" placeholder="ឧ. បន្ទប់ ឌីឡាក់ឃីង (Deluxe King)"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 font-khmer focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">info</span>
                        <span>Khmer translation. If left empty, the site automatically uses the English name.</span>
                    </p>
                </div>

                <!-- Input ZH -->
                <div class="field-input-box-name hidden" id="field-box-name-zh">
                    <input type="text" name="trans_zh_name" value="<?= e($transData['zh']['name'] ?? '') ?>" placeholder="例如：豪华大床房 (Deluxe King)"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">info</span>
                        <span>Chinese translation. If left empty, the site automatically uses the English name.</span>
                    </p>
                </div>

                <!-- Input KO -->
                <div class="field-input-box-name hidden" id="field-box-name-ko">
                    <input type="text" name="trans_ko_name" value="<?= e($transData['ko']['name'] ?? '') ?>" placeholder="예: 디럭스 킹 룸 (Deluxe King)"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">info</span>
                        <span>Korean translation. If left empty, the site automatically uses the English name.</span>
                    </p>
                </div>
            </div>

            <!-- 2. Room Tagline (Per-Input Tabbed Box) -->
            <div class="field-lang-group bg-white p-4 sm:p-5 rounded-xl border border-stone-200 space-y-3" data-field="tagline">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Room Tagline / Slogan</label>
                        <span class="text-[11px] text-stone-400 font-normal">(Key highlights subtitle)</span>
                    </div>
                    
                    <!-- Language Switch Pills for Tagline -->
                    <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded-lg border border-stone-200 text-xs">
                        <button type="button" onclick="switchFieldLang('tagline', 'en')" class="field-tab-btn-tagline px-2.5 py-1 rounded-md font-bold text-xs bg-[#343c0a] text-white" data-lang="en">
                            🇬🇧 EN
                        </button>
                        <button type="button" onclick="switchFieldLang('tagline', 'km')" class="field-tab-btn-tagline px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="km">
                            🇰🇭 KM <?= !empty($transData['km']['tagline']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('tagline', 'zh')" class="field-tab-btn-tagline px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="zh">
                            🇨🇳 ZH <?= !empty($transData['zh']['tagline']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('tagline', 'ko')" class="field-tab-btn-tagline px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="ko">
                            🇰🇷 KO <?= !empty($transData['ko']['tagline']) ? '●' : '' ?>
                        </button>
                    </div>
                </div>

                <!-- Input EN (Primary) -->
                <div class="field-input-box-tagline" id="field-box-tagline-en">
                    <input type="text" name="tagline" value="<?= e($enTaglineVal) ?>" placeholder="e.g. Contemporary Sanctuary for Modern Travelers"
                           class="w-full text-sm font-medium border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>


                <!-- Input KM -->
                <div class="field-input-box-tagline hidden" id="field-box-tagline-km">
                    <input type="text" name="trans_km_tagline" value="<?= e($transData['km']['tagline'] ?? '') ?>" placeholder="ឧ. ទីជម្រកដ៏ស្ងប់ស្ងាត់សម្រាប់អ្នកដំណើរទាន់សម័យ"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 font-khmer focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1">Khmer tagline. If left blank, automatically uses English.</p>
                </div>

                <!-- Input ZH -->
                <div class="field-input-box-tagline hidden" id="field-box-tagline-zh">
                    <input type="text" name="trans_zh_tagline" value="<?= e($transData['zh']['tagline'] ?? '') ?>" placeholder="例如：专为现代商务与休闲旅客打造的当代庇护所"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1">Chinese tagline. If left blank, automatically uses English.</p>
                </div>

                <!-- Input KO -->
                <div class="field-input-box-tagline hidden" id="field-box-tagline-ko">
                    <input type="text" name="trans_ko_tagline" value="<?= e($transData['ko']['tagline'] ?? '') ?>" placeholder="예: 현대 여행자를 위한 도심 속 안식처"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-amber-700 mt-1">Korean tagline. If left blank, automatically uses English.</p>
                </div>
            </div>

            <!-- 3. Room Description Narrative (Per-Input Tabbed Box) -->
            <div class="field-lang-group bg-white p-4 sm:p-5 rounded-xl border border-stone-200 space-y-3" data-field="desc">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Description Narrative *</label>
                        <span class="text-[11px] text-stone-400 font-normal">(Full overview of room experience)</span>
                    </div>
                    
                    <!-- Language Switch Pills for Description -->
                    <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded-lg border border-stone-200 text-xs">
                        <button type="button" onclick="switchFieldLang('desc', 'en')" class="field-tab-btn-desc px-2.5 py-1 rounded-md font-bold text-xs bg-[#343c0a] text-white" data-lang="en">
                            🇬🇧 EN
                        </button>
                        <button type="button" onclick="switchFieldLang('desc', 'km')" class="field-tab-btn-desc px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="km">
                            🇰🇭 KM <?= !empty($transData['km']['description']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('desc', 'zh')" class="field-tab-btn-desc px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="zh">
                            🇨🇳 ZH <?= !empty($transData['zh']['description']) ? '●' : '' ?>
                        </button>
                        <button type="button" onclick="switchFieldLang('desc', 'ko')" class="field-tab-btn-desc px-2.5 py-1 rounded-md font-bold text-xs text-stone-600 hover:bg-white" data-lang="ko">
                            🇰🇷 KO <?= !empty($transData['ko']['description']) ? '●' : '' ?>
                        </button>
                    </div>
                </div>

                <!-- Input EN (Primary) -->
                <div class="field-input-box-desc" id="field-box-desc-en">
                    <textarea name="description" id="en_room_desc" rows="4" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e($enDescVal) ?></textarea>
                    <p class="text-[11px] text-stone-400 mt-1">Primary English description. Required.</p>
                </div>


                <!-- Input KM -->
                <div class="field-input-box-desc hidden" id="field-box-desc-km">
                    <textarea name="trans_km_desc" rows="4" placeholder="សរសេរការពិពណ៌នាជាភាសាខ្មែរនៅទីនេះ..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 font-khmer focus:ring-2 focus:ring-[#343c0a]"><?= e($transData['km']['description'] ?? '') ?></textarea>
                    <p class="text-[11px] text-amber-700 mt-1">Khmer narrative. If left blank, automatically uses English description.</p>
                </div>

                <!-- Input ZH -->
                <div class="field-input-box-desc hidden" id="field-box-desc-zh">
                    <textarea name="trans_zh_desc" rows="4" placeholder="在此输入中文客房描述..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e($transData['zh']['description'] ?? '') ?></textarea>
                    <p class="text-[11px] text-amber-700 mt-1">Chinese narrative. If left blank, automatically uses English description.</p>
                </div>

                <!-- Input KO -->
                <div class="field-input-box-desc hidden" id="field-box-desc-ko">
                    <textarea name="trans_ko_desc" rows="4" placeholder="여기에 한국어 객실 설명을 입력하세요..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e($transData['ko']['description'] ?? '') ?></textarea>
                    <p class="text-[11px] text-amber-700 mt-1">Korean narrative. If left blank, automatically uses English description.</p>
                </div>
            </div>

        </div>

        <!-- System Specifications & Pricing -->
        <div class="space-y-6">
            <h3 class="font-headline font-bold text-base text-onyx-charcoal border-b border-stone-100 pb-2">Room Specifications & Pricing</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Nightly Rate (USD) *</label>
                    <input type="number" step="0.01" name="price_per_night" required value="<?= e($room['price_per_night']) ?>"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Room Size (sqm) *</label>
                    <input type="number" name="size_sqm" required value="<?= e($room['size_sqm']) ?>"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Bed Configuration *</label>
                    <input type="text" name="bed_type" required value="<?= e($room['bed_type']) ?>" placeholder="e.g. 1 King Bed"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">Room Type / Category *</label>
                        <a href="<?= BASE_URL ?>/admin/room-types.php" target="_blank" class="text-[11px] font-bold text-[#343c0a] hover:underline flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-xs">settings</span>
                            <span>Manage Types</span>
                        </a>
                    </div>
                    <select name="category" id="room_category_select" class="w-full text-sm font-semibold border border-stone-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-[#343c0a]">
                        <?php foreach ($availableRoomTypes as $rt): 
                            $isSelected = (strcasecmp($rt['name'], $room['category'] ?? '') === 0);
                        ?>
                            <option value="<?= e($rt['name']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= e($rt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Max Adults</label>
                    <input type="number" name="capacity_adults" value="<?= e($room['capacity_adults']) ?>" min="1" max="6"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Max Children</label>
                    <input type="number" name="capacity_children" value="<?= e($room['capacity_children']) ?>" min="0" max="4"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <!-- View Type & Status Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">View Type</label>
                    <input type="text" name="view_type" value="<?= e($room['view_type']) ?>" placeholder="e.g. City View, Pool View, Courtyard View"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Accommodation Status</label>
                    <select name="status" class="w-full text-sm font-semibold border border-stone-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-[#343c0a]">
                        <option value="available" <?= ($room['status'] === 'available') ? 'selected' : '' ?>>Available / Bookable</option>
                        <option value="booked" <?= ($room['status'] === 'booked') ? 'selected' : '' ?>>Booked / Occupied</option>
                        <option value="maintenance" <?= ($room['status'] === 'maintenance') ? 'selected' : '' ?>>Under Maintenance</option>
                    </select>
                </div>
            </div>

            <!-- Main Featured Image with Universal Uploader -->
            <div class="space-y-2">
                <?= render_image_uploader_field('image_url', $room['image_url'], 'Featured Primary Image', 'rooms', [
                    'required' => false,
                    'helper' => 'High resolution room image for cards, search results & hero banner'
                ]) ?>
            </div>

            <!-- Additional Room Gallery Photos Manager -->
            <?php
            $currentGallery = !empty($room['gallery_json']) ? json_decode($room['gallery_json'], true) : [];
            ?>
            <div class="space-y-3 bg-stone-50 p-5 rounded-2xl border border-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-stone-800 uppercase tracking-wider">Room Gallery Photos</label>
                        <p class="text-[11px] text-stone-500">Upload multiple photos for the room's interactive lightbox gallery.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold cursor-pointer transition shadow-2xs">
                            <span class="material-symbols-outlined text-sm">cloud_upload</span>
                            <span>Upload File</span>
                            <input type="file" accept="image/*" class="hidden" onchange="uploadRoomGalleryPhoto(this)">
                        </label>
                        <button type="button" onclick="openMediaLibraryPicker('room_gallery_append', 'image', 'rooms')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 text-xs font-bold transition cursor-pointer shadow-2xs">
                            <span class="material-symbols-outlined text-sm text-[#343c0a]">photo_library</span>
                            <span>Choose from Library</span>
                        </button>
                    </div>

                </div>

                <div id="room-gallery-container" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    <?php foreach ($currentGallery as $gIdx => $gUrl): ?>
                    <div class="gallery-item-card relative bg-white rounded-lg border border-stone-200 overflow-hidden group h-24">
                        <img src="<?= e($gUrl) ?>" alt="Gallery" class="w-full h-full object-cover">
                        <input type="hidden" name="gallery_images[]" value="<?= e($gUrl) ?>">
                        <button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/70 hover:bg-rose-600 text-white flex items-center justify-center text-xs transition cursor-pointer" title="Remove Photo">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">URL Slug (SEO Permalink)</label>
                    <input type="text" name="slug" value="<?= e($room['slug']) ?>" placeholder="e.g. deluxe-king-room"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div class="flex items-center pt-5 sm:pt-4">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-stone-700 uppercase tracking-wider">
                        <input type="checkbox" name="is_featured" value="1" <?= $room['is_featured'] ? 'checked' : '' ?> class="rounded text-[#343c0a] focus:ring-[#343c0a]">
                        <span>Feature prominently on Home Page</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Amenities Multi-Select & Custom Feature Creator -->
        <div class="space-y-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-stone-100 pb-3">
                <div>
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal">Room Amenities & Features</h3>
                    <p class="text-xs text-stone-500">Select standard hotel amenities or create custom features specific to this suite.</p>
                </div>
                <a href="<?= BASE_URL ?>/admin/amenities.php" target="_blank" class="text-xs font-bold text-[#343c0a] hover:underline inline-flex items-center gap-1">
                    <span>Manage Master Catalog</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>

            <!-- Add Custom Amenity Input Bar -->
            <div class="flex items-center gap-2 p-2.5 bg-stone-50 rounded-xl border border-stone-200">
                <div class="w-8 h-8 rounded-lg bg-white border border-stone-200 text-[#343c0a] flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-lg">add_circle</span>
                </div>
                <input type="text" id="new-custom-amenity-input" 
                       placeholder="Type custom amenity (e.g. Dyson Supersonic Hair Dryer, Private Plunge Pool, Marshall Speaker)..." 
                       class="flex-1 text-xs border border-stone-300 rounded-lg p-2 bg-white focus:ring-2 focus:ring-[#343c0a]" 
                       onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomAmenityToForm();}">
                <button type="button" onclick="addCustomAmenityToForm()" 
                        class="bg-[#343c0a] hover:bg-deep-olive text-white px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                    <span class="material-symbols-outlined text-sm">add</span>
                    <span>Add Custom Feature</span>
                </button>
            </div>

            <!-- Amenities Checkbox Grid -->
            <div id="amenities-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 pt-1">
                <?php foreach ($allAmenitiesList as $amenity): 
                    $icon = get_amenity_icon($amenity);
                    $isChecked = in_array($amenity, $currentAmenities);
                ?>
                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-stone-200 hover:bg-stone-50 cursor-pointer transition text-xs group <?= $isChecked ? 'bg-[#dfe8a6]/15 border-[#343c0a]/30' : 'bg-white' ?>">
                    <input type="checkbox" name="amenities[]" value="<?= e($amenity) ?>" 
                           <?= $isChecked ? 'checked' : '' ?>
                           class="rounded text-[#343c0a] focus:ring-[#343c0a] shrink-0">
                    <span class="material-symbols-outlined text-base text-stone-400 group-hover:text-[#343c0a] shrink-0"><?= e($icon) ?></span>
                    <span class="text-stone-800 font-medium truncate"><?= e($amenity) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pt-6 border-t border-stone-200 flex justify-end gap-3">
            <a href="<?= BASE_URL ?>/admin/accommodations.php" class="px-6 py-2.5 border border-stone-300 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-50 transition">Cancel</a>
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow cursor-pointer">
                <?= $isEdit ? 'Save Changes' : 'Create Accommodation' ?>
            </button>
        </div>
    </form>

</div>

<script>
// Client-side form validation before submission
function validateRoomForm() {
    const nameInput = document.getElementById('en_room_name');
    if (!nameInput || !nameInput.value.trim()) {
        switchAllFieldsLang('en');
        if (nameInput) {
            nameInput.focus();
            nameInput.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
        }
        alert('Please enter the Accommodation Name (English).');
        return false;
    }

    const descInput = document.getElementById('en_room_desc');
    if (!descInput || !descInput.value.trim()) {
        switchAllFieldsLang('en');
        if (descInput) {
            descInput.focus();
            descInput.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
        }
        alert('Please enter the Accommodation Description (English).');
        return false;
    }

    return true;
}

// Switch individual field language tab
function switchFieldLang(fieldName, lang) {
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const box = document.getElementById('field-box-' + fieldName + '-' + l);
        if (box) {
            if (l === lang) {
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        }
    });

    // Update active tab buttons for this field
    document.querySelectorAll('.field-tab-btn-' + fieldName).forEach(btn => {
        if (btn.dataset.lang === lang) {
            btn.classList.add('bg-[#343c0a]', 'text-white');
            btn.classList.remove('text-stone-600', 'hover:bg-white');
        } else {
            btn.classList.remove('bg-[#343c0a]', 'text-white');
            btn.classList.add('text-stone-600', 'hover:bg-white');
        }
    });
}

// Master quick switcher for all fields
function switchAllFieldsLang(lang) {
    const fields = ['name', 'tagline', 'desc'];
    fields.forEach(f => {
        switchFieldLang(f, lang);
    });

    // Update Master buttons
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const masterBtn = document.getElementById('master-tab-' + l);
        if (masterBtn) {
            if (l === lang) {
                masterBtn.classList.add('bg-[#343c0a]', 'text-white');
                masterBtn.classList.remove('text-stone-600', 'hover:bg-stone-100');
            } else {
                masterBtn.classList.remove('bg-[#343c0a]', 'text-white');
                masterBtn.classList.add('text-stone-600', 'hover:bg-stone-100');
            }
        }
    });
}

window.appendRoomGalleryCard = function(url) {
    const container = document.getElementById('room-gallery-container');
    if (!container) return;
    const card = document.createElement('div');
    card.className = 'gallery-item-card relative bg-white rounded-lg border border-stone-200 overflow-hidden group h-24';
    card.innerHTML = `
        <img src="${url}" alt="Gallery" class="w-full h-full object-cover">
        <input type="hidden" name="gallery_images[]" value="${url}">
        <button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/70 hover:bg-rose-600 text-white flex items-center justify-center text-xs transition cursor-pointer" title="Remove Photo">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    `;
    container.appendChild(card);
};

// Upload Room Gallery Photo
async function uploadRoomGalleryPhoto(fileInput) {
    if (!fileInput.files || fileInput.files.length === 0) return;
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('image', file);
    formData.append('folder', 'rooms');

        const endpointUrl = window.getApiEndpoint ? window.getApiEndpoint('/api/upload.php') : '/api/upload.php';
        const res = await fetch(endpointUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success && data.url) {
            window.appendRoomGalleryCard(data.url);
        } else {
            alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
    fileInput.value = '';
}

// Add Custom Amenity to Form Grid
function addCustomAmenityToForm() {
    const input = document.getElementById('new-custom-amenity-input');
    const val = input.value.trim();
    if (!val) {
        input.focus();
        return;
    }

    // Check if already exists in checkboxes
    const existing = Array.from(document.querySelectorAll('input[name="amenities[]"]')).map(el => el.value.toLowerCase());
    if (existing.includes(val.toLowerCase())) {
        // Find existing checkbox and check it
        const target = Array.from(document.querySelectorAll('input[name="amenities[]"]')).find(el => el.value.toLowerCase() === val.toLowerCase());
        if (target) {
            target.checked = true;
            target.closest('label').classList.add('bg-[#dfe8a6]/15', 'border-[#343c0a]/30');
            target.closest('label').classList.remove('bg-white');
        }
        input.value = '';
        input.focus();
        return;
    }

    const container = document.getElementById('amenities-container');
    const label = document.createElement('label');
    label.className = 'flex items-center gap-2.5 p-2.5 rounded-xl border border-[#343c0a]/30 bg-[#dfe8a6]/15 hover:bg-stone-50 cursor-pointer transition text-xs group animate-fadeIn';
    label.innerHTML = `
        <input type="checkbox" name="amenities[]" value="${val.replace(/"/g, '&quot;')}" checked class="rounded text-[#343c0a] focus:ring-[#343c0a] shrink-0">
        <span class="material-symbols-outlined text-base text-[#343c0a] shrink-0">stars</span>
        <span class="text-stone-800 font-medium truncate flex-1">${val.replace(/</g, '&lt;')}</span>
        <button type="button" onclick="this.closest('label').remove()" class="text-stone-400 hover:text-rose-600 p-0.5 rounded transition cursor-pointer" title="Remove">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    `;
    container.prepend(label);

    input.value = '';
    input.focus();
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
