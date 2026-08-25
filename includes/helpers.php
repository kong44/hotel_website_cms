<?php
/**
 * Indra Hotel - Helper Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/router.php';

/**
 * Generate URL for a named route
 */
function route(string $name, array $params = [], array $query = []): string {
    return Router::route($name, $params, $query);
}

/**
 * Generate URL from direct path
 */
function url(string $path = '', array $query = []): string {
    return Router::url($path, $query);
}

/**
 * Escape HTML output safely (XSS prevention)
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Convert text into URL-friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) {
        $translit = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        if ($translit !== false) {
            $text = $translit;
        }
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'page-' . time();
    }
    return $text;
}

/**
 * Format currency with hotel symbol
 */
function format_price(float|int|string $amount): string {
    return HOTEL_CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format date for friendly display
 */
function format_date(?string $dateStr, string $format = 'M d, Y'): string {
    if (!$dateStr) return '';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return $dateStr;
    }
}

/**
 * Calculate difference in days between two dates
 */
function calculate_nights(string $checkIn, string $checkOut): int {
    try {
        $d1 = new DateTime($checkIn);
        $d2 = new DateTime($checkOut);
        $diff = $d1->diff($d2);
        return max(1, (int)$diff->days);
    } catch (Exception $e) {
        return 1;
    }
}

/**
 * Generate unique booking reference (e.g. IND-2026-8492)
 */
function generate_booking_ref(): string {
    $year = date('Y');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return "IND-{$year}-{$rand}";
}

/**
 * Flash message helper
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Active navigation helper supporting clean routes and legacy file names
 */
function is_active_nav(string|array $routes): string {
    return Router::isActive($routes) ? 'text-[#4B5320] font-semibold border-b-2 border-[#4B5320]' : 'text-stone-700 hover:text-[#4B5320]';
}

/**
 * Get site setting from database with fallback
 */
function get_setting(string $key, ?string $default = ''): string {
    static $settingsCache = null;
    if ($settingsCache === null || $key === '__refresh__') {
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $settingsCache = $rows ?: [];
        } catch (Throwable $e) {
            $settingsCache = [];
        }
        if ($key === '__refresh__') {
            return '';
        }
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Persist site setting to database with cross-DB upsert
 */
function set_setting(string $key, string $value): bool {
    try {
        $pdo = getDB();
        if (Database::getDriver() === 'sqlite') {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        }
        $res = $stmt->execute([$key, $value]);
        get_setting('__refresh__');
        return $res;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Brand & Property Settings Helpers
 */
function hotel_name(): string {
    return get_setting('hotel_name', HOTEL_NAME);
}

function hotel_tagline(): string {
    return get_setting('hotel_tagline', APP_TAGLINE);
}

function hotel_logo_url(): string {
    return get_setting('hotel_logo_url', '');
}

function hotel_favicon_url(): string {
    return get_setting('hotel_favicon_url', '');
}

function hotel_phone(): string {
    return get_setting('hotel_phone', HOTEL_PHONE);
}

function hotel_email(): string {
    return get_setting('hotel_email', HOTEL_EMAIL);
}

function hotel_address(): string {
    return get_setting('hotel_address', '#95, Street 592, Beungkak 2, Tuol Kork, Phnom Penh, Cambodia');
}

function hotel_whatsapp(): string {
    return get_setting('hotel_whatsapp', '+85516889066');
}

function hotel_brand_color(string $type = 'accent'): string {
    $colors = [
        'accent' => get_setting('hotel_brand_accent', '#343c0a'),
        'secondary' => get_setting('hotel_brand_secondary', '#4B5320'),
        'highlight' => get_setting('hotel_brand_highlight', '#dfe8a6'),
        'dark' => get_setting('hotel_brand_dark', '#191c1d'),
    ];
    return $colors[$type] ?? $colors['accent'];
}

/**
 * Return JSON response and exit
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Render universal interactive image uploader component
 *
 * @param string $inputName Form field name (e.g. 'image_url')
 * @param string $currentValue Current URL or path
 * @param string $label Visible input label
 * @param string $folder Upload subfolder (e.g. 'rooms', 'brand', 'gallery')
 * @param array $options ['required' => bool, 'helper' => string, 'aspectRatio' => string]
 */
function render_image_uploader_field(string $inputName, string $currentValue = '', string $label = 'Image', string $folder = 'general', array $options = []): string {
    $uniqueId = 'uploader_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $inputName) . '_' . substr(md5($inputName . $folder), 0, 6);
    $required = !empty($options['required']) ? 'required' : '';
    $helper = $options['helper'] ?? 'JPG, PNG, WEBP, or SVG up to 10MB.';
    $currentValEsc = e($currentValue);
    $hasImage = !empty($currentValue);
    
    ob_start();
    ?>
    <div class="image-uploader-widget space-y-2" id="<?= $uniqueId ?>" data-folder="<?= e($folder) ?>">
        <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-stone-700 uppercase tracking-wider"><?= e($label) ?> <?= !empty($options['required']) ? '*' : '' ?></label>
            <span class="text-[11px] text-stone-400 font-normal"><?= e($helper) ?></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 p-3 bg-stone-50 rounded-xl border border-stone-200">
            <!-- Preview Box -->
            <div class="sm:col-span-4 lg:col-span-3 flex flex-col items-center justify-center bg-white rounded-lg border border-stone-200 p-2 min-h-[110px] relative overflow-hidden group">
                <img src="<?= $hasImage ? $currentValEsc : '' ?>" 
                     alt="Preview" 
                     class="image-preview-thumb max-h-24 max-w-full object-contain rounded <?= $hasImage ? '' : 'hidden' ?>" 
                     id="<?= $uniqueId ?>_preview">
                
                <div class="no-image-placeholder text-center text-stone-400 <?= $hasImage ? 'hidden' : '' ?>" id="<?= $uniqueId ?>_placeholder">
                    <span class="material-symbols-outlined text-3xl">image</span>
                    <span class="block text-[10px] font-semibold uppercase mt-0.5">No Image</span>
                </div>

                <div class="upload-progress-bar hidden absolute inset-0 bg-black/75 flex flex-col items-center justify-center text-white" id="<?= $uniqueId ?>_progress">
                    <span class="material-symbols-outlined animate-spin text-2xl">autorenew</span>
                    <span class="text-[10px] font-bold mt-1">Uploading...</span>
                </div>
            </div>

            <!-- Controls Column -->
            <div class="sm:col-span-8 lg:col-span-9 flex flex-col justify-between space-y-2">
                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- File Upload Input Trigger -->
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold cursor-pointer transition shadow-2xs">
                        <span class="material-symbols-outlined text-base">cloud_upload</span>
                        <span>Upload File</span>
                        <input type="file" accept="image/*,.svg,.ico" class="hidden image-file-input" onchange="handleImageFileSelect(this, '<?= $uniqueId ?>', '<?= e($folder) ?>')">
                    </label>

                    <!-- Select from Media Library Button -->
                    <button type="button" onclick="openMediaLibraryPicker('<?= $uniqueId ?>', 'image', '<?= e($folder) ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 text-xs font-bold transition cursor-pointer shadow-2xs">
                        <span class="material-symbols-outlined text-base text-[#343c0a]">photo_library</span>
                        <span>Choose from Library</span>
                    </button>

                    <!-- Remove / Clear Button -->
                    <button type="button" onclick="clearImageUpload('<?= $uniqueId ?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-600 text-xs font-semibold transition cursor-pointer">
                        <span class="material-symbols-outlined text-sm text-rose-500">delete</span>
                        <span>Clear</span>
                    </button>
                </div>


                <!-- URL Direct Input -->
                <div class="relative">
                    <input type="text" 
                           name="<?= e($inputName) ?>" 
                           id="<?= $uniqueId ?>_input" 
                           value="<?= $currentValEsc ?>" 
                           placeholder="Or paste direct image URL (https://...)" 
                           <?= $required ?> 
                           oninput="handleImageUrlInput(this.value, '<?= $uniqueId ?>')"
                           class="w-full text-xs font-mono border border-stone-300 rounded-lg py-1.5 px-2.5 bg-white focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Universal Video Uploader & Direct URL Input Field
 *
 * @param string $inputName Form input name (e.g. 'home_hero_video_url')
 * @param string $currentValue Existing video URL
 * @param string $label Visible input label
 * @param string $folder Upload subfolder (e.g. 'videos')
 * @param array $options ['required' => bool, 'helper' => string]
 */
function render_video_uploader_field(string $inputName, string $currentValue = '', string $label = 'Background Video', string $folder = 'videos', array $options = []): string {
    $uniqueId = 'video_uploader_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $inputName) . '_' . substr(md5($inputName . $folder), 0, 6);
    $required = !empty($options['required']) ? 'required' : '';
    $helper = $options['helper'] ?? 'MP4, WebM, or MOV file (up to 100MB) or direct video URL.';
    $currentValEsc = e($currentValue);
    $hasVideo = !empty($currentValue);

    ob_start();
    ?>
    <div class="video-uploader-widget space-y-2" id="<?= $uniqueId ?>" data-folder="<?= e($folder) ?>">
        <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-stone-700 uppercase tracking-wider"><?= e($label) ?> <?= !empty($options['required']) ? '*' : '' ?></label>
            <span class="text-[11px] text-stone-400 font-normal"><?= e($helper) ?></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 p-3 bg-stone-50 rounded-xl border border-stone-200">
            <!-- Video Preview Player Box -->
            <div class="sm:col-span-4 lg:col-span-3 flex flex-col items-center justify-center bg-stone-900 rounded-lg border border-stone-800 p-2 min-h-[110px] relative overflow-hidden group">
                <video src="<?= $hasVideo ? $currentValEsc : '' ?>" 
                       controls muted playsinline
                       class="video-preview-player max-h-24 max-w-full rounded <?= $hasVideo ? '' : 'hidden' ?>" 
                       id="<?= $uniqueId ?>_preview"></video>
                
                <div class="no-video-placeholder text-center text-stone-400 <?= $hasVideo ? 'hidden' : '' ?>" id="<?= $uniqueId ?>_placeholder">
                    <span class="material-symbols-outlined text-3xl text-stone-500">videocam</span>
                    <span class="block text-[10px] font-semibold uppercase mt-0.5 text-stone-400">No Video</span>
                </div>

                <div class="upload-progress-bar hidden absolute inset-0 bg-black/85 flex flex-col items-center justify-center text-white z-10" id="<?= $uniqueId ?>_progress">
                    <span class="material-symbols-outlined animate-spin text-2xl text-[#dfe8a6]">autorenew</span>
                    <span class="text-[10px] font-bold mt-1 text-[#dfe8a6]">Uploading Video...</span>
                </div>
            </div>

            <!-- Controls Column -->
            <div class="sm:col-span-8 lg:col-span-9 flex flex-col justify-between space-y-2">
                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- File Upload Input Trigger -->
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold cursor-pointer transition shadow-2xs">
                        <span class="material-symbols-outlined text-base">cloud_upload</span>
                        <span>Upload Video</span>
                        <input type="file" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.mov" class="hidden video-file-input" onchange="handleVideoFileSelect(this, '<?= $uniqueId ?>', '<?= e($folder) ?>')">
                    </label>

                    <!-- Select from Media Library Button -->
                    <button type="button" onclick="openMediaLibraryPicker('<?= $uniqueId ?>', 'video', '<?= e($folder) ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 text-xs font-bold transition cursor-pointer shadow-2xs">
                        <span class="material-symbols-outlined text-base text-[#343c0a]">perm_media</span>
                        <span>Choose from Library</span>
                    </button>

                    <!-- Remove / Clear Button -->
                    <button type="button" onclick="clearVideoUpload('<?= $uniqueId ?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-600 text-xs font-semibold transition cursor-pointer">
                        <span class="material-symbols-outlined text-sm text-rose-500">delete</span>
                        <span>Clear</span>
                    </button>
                </div>


                <!-- URL Direct Input -->
                <div class="relative">
                    <input type="text" 
                           name="<?= e($inputName) ?>" 
                           id="<?= $uniqueId ?>_input" 
                           value="<?= $currentValEsc ?>" 
                           placeholder="Or paste direct video URL (.mp4, .webm, or YouTube link)" 
                           <?= $required ?> 
                           oninput="handleVideoUrlInput(this.value, '<?= $uniqueId ?>')"
                           class="w-full text-xs font-mono border border-stone-300 rounded-lg py-1.5 px-2.5 bg-white focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Fetch list of all active master amenities from DB or fallback
 */
function get_all_master_amenities(): array {
    static $cached = null;
    if ($cached !== null) return $cached;

    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT name FROM amenities ORDER BY display_order ASC, id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($rows)) {
            $cached = $rows;
            return $cached;
        }
    } catch (Throwable $e) {}

    // Fallback default list
    $cached = [
        'High-Speed Wi-Fi',
        '55" Smart Cable TV',
        '65" 4K Smart TV',
        'Artisan Coffee Maker',
        'Espresso Machine',
        'Signature Nespresso Station',
        'Minibar & Refrigerator',
        'Complimentary Premium Minibar',
        'In-Room Electronic Safe',
        'Air Conditioning',
        'Organic Bath Amenities',
        'Rainfall Shower',
        'Deep Soaking Tub & Rain Shower',
        'Marble Bathroom with Free-standing Tub',
        'Work Desk & Ergonomic Chair',
        'Daily Housekeeping',
        'Private Balcony with Seating',
        'Expansive Private Panoramic Balcony',
        'Plush Bathrobes & Slippers',
        'Lounge Seating Area',
        'Separate Living Room',
        'Walk-in Dressing Closet',
        'VIP Airport Transfer Option'
    ];
    return $cached;
}

/**
 * Resolve material icon name for any amenity string
 */
function get_amenity_icon(string $amenity): string {
    $a = strtolower($amenity);
    if (strpos($a, 'wi-fi') !== false || strpos($a, 'wifi') !== false || strpos($a, 'internet') !== false) return 'wifi';
    if (strpos($a, 'tv') !== false || strpos($a, 'television') !== false || strpos($a, 'cable') !== false) return 'tv';
    if (strpos($a, 'coffee') !== false || strpos($a, 'nespresso') !== false || strpos($a, 'espresso') !== false) return 'coffee';
    if (strpos($a, 'bar') !== false || strpos($a, 'refrigerator') !== false || strpos($a, 'fridge') !== false) return 'kitchen';
    if (strpos($a, 'safe') !== false || strpos($a, 'lock') !== false || strpos($a, 'security') !== false) return 'lock';
    if (strpos($a, 'air') !== false || strpos($a, 'conditioning') !== false || strpos($a, 'ac') !== false) return 'ac_unit';
    if (strpos($a, 'shower') !== false) return 'shower';
    if (strpos($a, 'tub') !== false || strpos($a, 'bath') !== false) return 'bathtub';
    if (strpos($a, 'balcony') !== false || strpos($a, 'terrace') !== false || strpos($a, 'patio') !== false) return 'balcony';
    if (strpos($a, 'robe') !== false || strpos($a, 'slipper') !== false || strpos($a, 'linen') !== false) return 'apparel';
    if (strpos($a, 'desk') !== false || strpos($a, 'chair') !== false || strpos($a, 'work') !== false) return 'desk';
    if (strpos($a, 'housekeeping') !== false || strpos($a, 'clean') !== false) return 'cleaning_services';
    if (strpos($a, 'living') !== false || strpos($a, 'lounge') !== false || strpos($a, 'sofa') !== false) return 'weekend';
    if (strpos($a, 'closet') !== false || strpos($a, 'wardrobe') !== false) return 'checkroom';
    if (strpos($a, 'pool') !== false || strpos($a, 'swim') !== false) return 'pool';
    if (strpos($a, 'transfer') !== false || strpos($a, 'airport') !== false || strpos($a, 'shuttle') !== false) return 'airport_shuttle';
    if (strpos($a, 'spa') !== false || strpos($a, 'massage') !== false) return 'spa';
    if (strpos($a, 'soap') !== false || strpos($a, 'amenities') !== false || strpos($a, 'toiletries') !== false) return 'soap';
    return 'check_circle';
}

/**
 * Retrieve all active room types / categories
 */
function get_all_room_types(): array {
    static $cachedTypes = null;
    if ($cachedTypes !== null) {
        return $cachedTypes;
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM room_types WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        $cachedTypes = $stmt->fetchAll();
        if (!empty($cachedTypes)) {
            return $cachedTypes;
        }
    } catch (Throwable $e) {}

    return [
        ['id' => 1, 'name' => 'Deluxe Room', 'slug' => 'deluxe-room', 'icon' => 'hotel', 'badge_color' => 'emerald'],
        ['id' => 2, 'name' => 'Suite Room', 'slug' => 'suite-room', 'icon' => 'apartment', 'badge_color' => 'amber'],
        ['id' => 3, 'name' => 'Conference Room', 'slug' => 'conference-room', 'icon' => 'meeting_room', 'badge_color' => 'indigo'],
        ['id' => 4, 'name' => 'City View Room', 'slug' => 'city-view-room', 'icon' => 'location_city', 'badge_color' => 'blue']
    ];
}

/**
 * Return formatted HTML badge for a Room Type category
 */
function get_room_type_badge(?string $category, string $size = 'sm'): string {
    $catName = !empty($category) ? trim($category) : 'Deluxe Room';
    $types = get_all_room_types();
    $found = null;
    foreach ($types as $t) {
        if (strcasecmp($t['name'], $catName) === 0 || strcasecmp($t['slug'], $catName) === 0) {
            $found = $t;
            break;
        }
    }

    $icon = $found['icon'] ?? 'hotel';
    $color = $found['badge_color'] ?? 'emerald';
    $displayName = $found['name'] ?? $catName;

    $colorStyles = [
        'emerald' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'amber'   => 'bg-amber-50 text-amber-900 border-amber-200',
        'indigo'  => 'bg-indigo-50 text-indigo-800 border-indigo-200',
        'blue'    => 'bg-blue-50 text-blue-800 border-blue-200',
        'purple'  => 'bg-purple-50 text-purple-800 border-purple-200',
        'rose'    => 'bg-rose-50 text-rose-800 border-rose-200',
        'olive'   => 'bg-[#dfe8a6]/40 text-[#191e00] border-[#dfe8a6]',
        'stone'   => 'bg-stone-100 text-stone-700 border-stone-200'
    ];

    $style = $colorStyles[$color] ?? $colorStyles['emerald'];
    $iconSize = ($size === 'xs') ? 'text-xs' : 'text-sm';
    $textSize = ($size === 'xs') ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1';

    return '<span class="inline-flex items-center gap-1 rounded-full border font-bold uppercase tracking-wider ' . $style . ' ' . $textSize . '">
        <span class="material-symbols-outlined ' . $iconSize . '">' . htmlspecialchars($icon) . '</span>
        <span>' . htmlspecialchars($displayName) . '</span>
    </span>';
}

/**
 * Get Booking Mode ('internal' or 'engine')
 */
function get_booking_mode(): string {
    return get_setting('booking_mode', 'internal');
}

/**
 * Get External Booking Engine URL
 */
function get_booking_engine_url(): string {
    return trim(get_setting('booking_engine_url', ''));
}

/**
 * Get Booking Link Target Attribute ('_blank' or '_self')
 */
function get_booking_target(): string {
    if (get_booking_mode() === 'engine') {
        return get_setting('booking_engine_target', '_blank') ?: '_blank';
    }
    return '_self';
}

/**
 * Get Booking Button Custom Label
 */
function get_booking_button_text(string $default = ''): string {
    $custom = trim(get_setting('booking_button_text', ''));
    if (!empty($custom)) {
        return $custom;
    }
    
    $mode = get_booking_mode();
    if ($mode === 'ota' || $mode === 'multi_channel') {
        return 'Search Booking';
    } elseif ($mode === 'engine') {
        return 'Book Now';
    }
    
    return !empty($default) ? $default : 'Direct Booking';
}

/**
 * Build dynamic single OTA Deep Link URL for Mode 3
 */
function build_single_ota_deeplink(?string $checkIn = null, ?string $checkOut = null, int $adults = 2): string {
    $baseUrl = trim(get_setting('ota_deeplink_url', ''));
    if (empty($baseUrl)) {
        $baseUrl = trim(get_setting('ota_bookingcom_url', ''));
    }
    if (empty($baseUrl)) {
        return '#';
    }

    $platform = trim(get_setting('ota_platform_type', 'auto'));
    if ($platform === 'auto' || empty($platform)) {
        if (strpos($baseUrl, 'booking.com') !== false) {
            $platform = 'bookingcom';
        } elseif (strpos($baseUrl, 'traveloka.com') !== false) {
            $platform = 'traveloka';
        } elseif (strpos($baseUrl, 'trip.com') !== false) {
            $platform = 'tripcom';
        } elseif (strpos($baseUrl, 'agoda.com') !== false) {
            $platform = 'agoda';
        } else {
            $platform = 'generic';
        }
    }

    return build_ota_deep_link($platform, $baseUrl, $checkIn, $checkOut, $adults);
}

/**
 * Get Dynamic Booking URL based on configured mode
 */
function get_booking_url(?int $roomId = null, ?string $checkIn = null, ?string $checkOut = null, ?int $adults = null, ?string $promo = null): string {
    $mode = get_booking_mode();
    if ($mode === 'engine') {
        $engineUrl = get_booking_engine_url();
        if (!empty($engineUrl)) {
            $params = [];
            if ($roomId) $params['room_id'] = $roomId;
            if ($checkIn) $params['check_in'] = $checkIn;
            if ($checkOut) $params['check_out'] = $checkOut;
            if ($adults) $params['adults'] = $adults;
            if ($promo) $params['promo'] = $promo;
            
            if (!empty($params)) {
                $separator = (strpos($engineUrl, '?') !== false) ? '&' : '?';
                return $engineUrl . $separator . http_build_query($params);
            }
            return $engineUrl;
        }
    } elseif ($mode === 'ota' || $mode === 'multi_channel') {
        return build_single_ota_deeplink($checkIn, $checkOut, $adults ?? 2);
    }

    $params = [];
    if ($roomId) $params['room_id'] = $roomId;
    if ($checkIn) $params['check_in'] = $checkIn;
    if ($checkOut) $params['check_out'] = $checkOut;
    if ($adults) $params['adults'] = $adults;
    if ($promo) $params['promo'] = $promo;
    
    return url('/book', $params);
}

/**
 * Get Specific Booking URL for a Special Offer
 * Prioritizes the offer's individual custom external link if configured,
 * otherwise falls back to the system booking URL with promo code attached.
 */
function get_offer_booking_url(array|object|null $offer, ?int $roomId = null, ?string $checkIn = null, ?string $checkOut = null, ?int $adults = null): string {
    if (empty($offer)) {
        return get_booking_url($roomId, $checkIn, $checkOut, $adults);
    }
    $offerArr = (array)$offer;
    $customExternalUrl = trim((string)($offerArr['external_url'] ?? ''));
    
    // 1. If offer has its own specific external URL configured, use it directly!
    if (!empty($customExternalUrl)) {
        return $customExternalUrl;
    }
    
    // 2. Otherwise fall back to system booking URL with offer's promo code
    $promoCode = $offerArr['promo_code'] ?? null;
    return get_booking_url($roomId, $checkIn, $checkOut, $adults, $promoCode);
}

/**
 * Helper to parse and sanitize comma-separated email lists
 */
function parse_email_list(string $raw): array {
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    $valid = [];
    foreach ($parts as $p) {
        if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
            $valid[] = strtolower($p);
        }
    }
    return array_values(array_unique($valid));
}

/**
 * Get Primary Booking Notification Email(s) for Hotel Frontdesk / Reservations
 */
function get_booking_receiver_emails(): array {
    $raw = get_setting('booking_receiver_email', '');
    if (empty($raw)) {
        $raw = get_setting('mail_notification_email', hotel_email() ?: 'admin@hotel.com');
    }
    $list = parse_email_list($raw);
    return !empty($list) ? $list : [hotel_email() ?: 'admin@hotel.com'];
}

/**
 * Get Sales Team Booking Notification Email(s)
 */
function get_booking_sales_emails(): array {
    $raw = get_setting('booking_sales_email', '');
    return parse_email_list($raw);
}

/**
 * Get Management / GM Booking Notification Email(s)
 */
function get_booking_management_emails(): array {
    $raw = get_setting('booking_management_email', '');
    return parse_email_list($raw);
}

/**
 * Get Other Custom Setup Email(s) for Bookings
 */
function get_booking_other_emails(): array {
    $raw = get_setting('booking_other_emails', '');
    return parse_email_list($raw);
}

/**
 * Get ALL combined recipient emails for new reservations (Frontdesk, Sales, Management, Others)
 */
function get_booking_all_notification_emails(): array {
    $all = array_merge(
        get_booking_receiver_emails(),
        get_booking_sales_emails(),
        get_booking_management_emails(),
        get_booking_other_emails()
    );
    $unique = array_values(array_unique($all));
    return !empty($unique) ? $unique : [hotel_email() ?: 'admin@hotel.com'];
}

/**
 * Get Primary Contact Us Inquiry Notification Email(s)
 */
function get_contact_receiver_emails(): array {
    $raw = get_setting('contact_receiver_email', '');
    if (empty($raw)) {
        $raw = get_setting('mail_notification_email', hotel_email() ?: 'admin@hotel.com');
    }
    $list = parse_email_list($raw);
    return !empty($list) ? $list : [hotel_email() ?: 'admin@hotel.com'];
}

/**
 * Get Sales & Event Team Contact Notification Email(s)
 */
function get_contact_sales_emails(): array {
    $raw = get_setting('contact_sales_email', '');
    return parse_email_list($raw);
}

/**
 * Get Management / GM Contact Notification Email(s)
 */
function get_contact_management_emails(): array {
    $raw = get_setting('contact_management_email', '');
    return parse_email_list($raw);
}

/**
 * Get Other Custom Setup Email(s) for Contact Inquiries
 */
function get_contact_other_emails(): array {
    $raw = get_setting('contact_other_emails', '');
    return parse_email_list($raw);
}

/**
 * Get ALL combined recipient emails for Contact Us inquiries (Support, Sales, Management, Others)
 */
function get_contact_all_notification_emails(): array {
    $all = array_merge(
        get_contact_receiver_emails(),
        get_contact_sales_emails(),
        get_contact_management_emails(),
        get_contact_other_emails()
    );
    $unique = array_values(array_unique($all));
    return !empty($unique) ? $unique : [hotel_email() ?: 'admin@hotel.com'];
}

/**
 * Check if Contact Auto-Reply is enabled
 */
function is_contact_auto_reply_enabled(): bool {
    return get_setting('contact_auto_reply', '1') === '1';
}

/**
 * Check if outbound email activity logging is enabled
 */
function is_mail_log_enabled(): bool {
    return get_setting('mail_log_activity', '1') === '1';
}

/**
 * Check if a public guest user is signed in via Google Auth
 */
function is_guest_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['guest_user']) && !empty($_SESSION['guest_user']['email']);
}

/**
 * Get current signed-in public guest details
 */
function get_logged_in_guest(): ?array {
    return is_guest_logged_in() ? $_SESSION['guest_user'] : null;
}

/**
 * Get guest account status from database ('active', 'restricted', 'blocked', 'flagged', etc.)
 */
function get_guest_status(?string $email = null): string {
    if (empty($email)) {
        $guest = get_logged_in_guest();
        $email = $guest['email'] ?? '';
    }
    if (empty($email)) {
        return 'active';
    }

    $cleanEmail = strtolower(trim($email));

    try {
        $pdo = getDB();

        // 1. Check guest_users table status
        $stmtGu = $pdo->prepare("SELECT status FROM guest_users WHERE LOWER(email) = ? LIMIT 1");
        $stmtGu->execute([$cleanEmail]);
        $statusGu = $stmtGu->fetchColumn();
        if ($statusGu && in_array(strtolower((string)$statusGu), ['blocked', 'restricted', 'flagged'], true)) {
            return strtolower((string)$statusGu);
        }

        // 2. Check guests table status
        $stmtG = $pdo->prepare("SELECT status FROM guests WHERE LOWER(email) = ? LIMIT 1");
        $stmtG->execute([$cleanEmail]);
        $statusG = $stmtG->fetchColumn();
        if ($statusG && in_array(strtolower((string)$statusG), ['blocked', 'restricted', 'flagged'], true)) {
            return strtolower((string)$statusG);
        }

        if ($statusGu) return strtolower((string)$statusGu);
        if ($statusG) return strtolower((string)$statusG);

        return 'active';
    } catch (Throwable $e) {
        return 'active';
    }
}

/**
 * Check if public guest is blocked by Admin
 */
function is_guest_blocked(?string $email = null): bool {
    $status = get_guest_status($email);
    return $status === 'blocked';
}

/**
 * Check if public guest is restricted/blocked from performing actions (booking, messaging)
 */
function is_guest_restricted(?string $email = null): bool {
    $status = get_guest_status($email);
    return in_array($status, ['blocked', 'restricted', 'flagged'], true);
}

/**
 * Retrieve Page Hero Setting with Fallbacks
 */
function get_hero_setting(string $pageKey, string $field, string $default = ''): string {
    $settingKey = "hero_{$pageKey}_{$field}";
    
    // Backward compatibility mapping
    if ($pageKey === 'home') {
        if ($field === 'badge') $settingKey = 'home_hero_badge';
        if ($field === 'title') $settingKey = 'home_hero_title';
        if ($field === 'subtitle') $settingKey = 'home_hero_subtitle';
        if ($field === 'image') $settingKey = 'home_hero_image';
        if ($field === 'video') $settingKey = 'home_hero_video_url';
    } elseif ($pageKey === 'location') {
        if ($field === 'badge') $settingKey = 'location_badge';
        if ($field === 'title') $settingKey = 'location_title';
        if ($field === 'subtitle') $settingKey = 'location_subtitle';
    }

    return get_setting($settingKey, $default);
}

/**
 * Persist Page Hero Setting
 */
function set_hero_setting(string $pageKey, string $field, string $value): bool {
    $settingKey = "hero_{$pageKey}_{$field}";
    
    if ($pageKey === 'home') {
        if ($field === 'badge') $settingKey = 'home_hero_badge';
        if ($field === 'title') $settingKey = 'home_hero_title';
        if ($field === 'subtitle') $settingKey = 'home_hero_subtitle';
        if ($field === 'image') $settingKey = 'home_hero_image';
        if ($field === 'video') $settingKey = 'home_hero_video_url';
    } elseif ($pageKey === 'location') {
        if ($field === 'badge') $settingKey = 'location_badge';
        if ($field === 'title') $settingKey = 'location_title';
        if ($field === 'subtitle') $settingKey = 'location_subtitle';
    }

    return set_setting($settingKey, $value);
}

/**
 * Render Public Page Hero Banner Component with Dynamic Height & Overlay Opacity Support
 */
function render_public_page_hero(string $pageKey, array $defaults = []): string {
    $badge = get_hero_setting($pageKey, 'badge', $defaults['badge'] ?? '');
    $title = get_hero_setting($pageKey, 'title', $defaults['title'] ?? 'Welcome');
    $subtitle = get_hero_setting($pageKey, 'subtitle', $defaults['subtitle'] ?? '');
    $image = get_hero_setting($pageKey, 'image', $defaults['image'] ?? '');
    $video = get_hero_setting($pageKey, 'video', $defaults['video'] ?? '');
    $height = get_hero_setting($pageKey, 'height', $defaults['height'] ?? 'medium');
    $customHeight = get_hero_setting($pageKey, 'custom_height', $defaults['custom_height'] ?? '');
    $overlay = get_hero_setting($pageKey, 'overlay', $defaults['overlay'] ?? 'medium');
    $customOverlay = get_hero_setting($pageKey, 'custom_overlay', $defaults['custom_overlay'] ?? '');
    $icon = $defaults['icon'] ?? 'sparkles';

    // Dynamic Height calculation
    $heightClass = 'py-16 sm:py-24 min-h-[50vh]';
    $styleAttr = '';

    if ($height === 'compact') {
        $heightClass = 'py-12 sm:py-16 min-h-[35vh]';
    } elseif ($height === 'medium') {
        $heightClass = 'py-16 sm:py-24 min-h-[50vh]';
    } elseif ($height === 'tall') {
        $heightClass = 'py-24 sm:py-36 min-h-[70vh]';
    } elseif ($height === 'fullscreen') {
        $heightClass = 'min-h-screen py-24 sm:py-32';
    } elseif ($height === 'custom' && !empty($customHeight)) {
        $heightClass = 'py-12 sm:py-16';
        $styleAttr = ' style="min-height: ' . e($customHeight) . ';"';
    }

    // Dynamic Overlay Opacity calculation
    $overlayClass = 'bg-gradient-to-b from-stone-950/75 via-stone-900/50 to-onyx-charcoal';
    $overlayStyle = '';

    if ($overlay === 'none') {
        $overlayClass = 'bg-black/15';
    } elseif ($overlay === 'light') {
        $overlayClass = 'bg-gradient-to-b from-stone-950/40 via-stone-900/25 to-stone-950/40';
    } elseif ($overlay === 'medium') {
        $overlayClass = 'bg-gradient-to-b from-stone-950/75 via-stone-900/50 to-onyx-charcoal';
    } elseif ($overlay === 'dark') {
        $overlayClass = 'bg-gradient-to-b from-stone-950/90 via-stone-900/80 to-stone-950/90';
    } elseif ($overlay === 'deep') {
        $overlayClass = 'bg-gradient-to-b from-black/95 via-black/90 to-black/95';
    } elseif ($overlay === 'custom' && is_numeric($customOverlay)) {
        $alpha = min(100, max(0, (float)$customOverlay)) / 100;
        $overlayClass = '';
        $overlayStyle = ' style="background-color: rgba(12, 12, 12, ' . $alpha . ');"';
    }

    ob_start();
    ?>
    <section class="bg-onyx-charcoal text-white relative overflow-hidden flex flex-col justify-center <?= $heightClass ?>"<?= $styleAttr ?>>
        <!-- Background Video Loop -->
        <?php if (!empty($video)): ?>
            <div class="absolute inset-0 opacity-40 z-0 overflow-hidden">
                <video src="<?= e($video) ?>" autoplay loop muted playsinline class="w-full h-full object-cover"></video>
            </div>
        <?php elseif (!empty($image)): ?>
            <div class="absolute inset-0 opacity-30 z-0">
                <img src="<?= e($image) ?>" alt="<?= e($title) ?>" class="w-full h-full object-cover">
            </div>
        <?php endif; ?>

        <!-- Dynamic Gradient Dark Overlay -->
        <div class="absolute inset-0 z-0 <?= $overlayClass ?>"<?= $overlayStyle ?>></div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center my-auto">
            <?php if (!empty($badge)): ?>
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-[0.2em] bg-white/10 backdrop-blur-md border border-white/20 text-[#dfe8a6] mb-4">
                    <span class="material-symbols-outlined text-sm"><?= e($icon) ?></span>
                    <span><?= e($badge) ?></span>
                </span>
            <?php endif; ?>

            <?php if (!empty($title)): ?>
                <h1 class="font-headline text-3xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white mb-4 leading-tight">
                    <?= e($title) ?>
                </h1>
            <?php endif; ?>

            <?php if (!empty($subtitle)): ?>
                <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto font-light leading-relaxed">
                    <?= e($subtitle) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Render Reusable Admin Hero Form Box for Content Pages
 */
function render_admin_hero_editor_card(string $pageKey, string $pageTitle, array $defaults = []): string {
    $badge = get_hero_setting($pageKey, 'badge', $defaults['badge'] ?? '');
    $title = get_hero_setting($pageKey, 'title', $defaults['title'] ?? '');
    $subtitle = get_hero_setting($pageKey, 'subtitle', $defaults['subtitle'] ?? '');
    $image = get_hero_setting($pageKey, 'image', $defaults['image'] ?? '');
    $video = get_hero_setting($pageKey, 'video', $defaults['video'] ?? '');
    $height = get_hero_setting($pageKey, 'height', $defaults['height'] ?? 'medium');
    $customHeight = get_hero_setting($pageKey, 'custom_height', $defaults['custom_height'] ?? '');
    $overlay = get_hero_setting($pageKey, 'overlay', $defaults['overlay'] ?? 'medium');
    $customOverlay = get_hero_setting($pageKey, 'custom_overlay', $defaults['custom_overlay'] ?? '');

    ob_start();
    ?>
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/30 text-[#343c0a] flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-xl">view_day</span>
                </div>
                <div>
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal"><?= e($pageTitle) ?> Hero Banner & Header</h3>
                    <p class="text-xs text-stone-500">Configure title, tagline, banner height, overlay opacity, background imagery, and video loop for this page.</p>
                </div>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full">Page Hero</span>
        </div>

        <input type="hidden" name="hero_page_keys[]" value="<?= e($pageKey) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Hero Badge / Slogan</label>
                <input type="text" name="hero_<?= e($pageKey) ?>_badge" value="<?= e($badge) ?>" placeholder="e.g. Contemporary Sanctuary" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Hero Headline Title *</label>
                <input type="text" name="hero_<?= e($pageKey) ?>_title" value="<?= e($title) ?>" placeholder="e.g. Rooms, Suites & Spaces" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
            </div>
        </div>

        <div>
            <label class="block font-bold text-stone-700 uppercase mb-1 text-xs">Hero Subtitle / Description Narrative</label>
            <textarea name="hero_<?= e($pageKey) ?>_subtitle" rows="2" placeholder="Write a short subhead narrative..." class="w-full text-xs border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]"><?= e($subtitle) ?></textarea>
        </div>

        <!-- Dynamic Height & Overlay Controls -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 text-xs pt-2 border-t border-stone-100">
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Hero Banner Height</label>
                <select name="hero_<?= e($pageKey) ?>_height" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
                    <option value="compact" <?= $height === 'compact' ? 'selected' : '' ?>>Compact (35vh)</option>
                    <option value="medium" <?= $height === 'medium' ? 'selected' : '' ?>>Medium (50vh - Default)</option>
                    <option value="tall" <?= $height === 'tall' ? 'selected' : '' ?>>Tall (70vh)</option>
                    <option value="fullscreen" <?= $height === 'fullscreen' ? 'selected' : '' ?>>Fullscreen (100vh)</option>
                    <option value="custom" <?= $height === 'custom' ? 'selected' : '' ?>>Custom CSS Height</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Custom Height (px / vh)</label>
                <input type="text" name="hero_<?= e($pageKey) ?>_custom_height" value="<?= e($customHeight) ?>" placeholder="e.g. 450px or 60vh" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Dark Overlay Opacity</label>
                <select name="hero_<?= e($pageKey) ?>_overlay" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
                    <option value="none" <?= $overlay === 'none' ? 'selected' : '' ?>>Minimal Tint (15%)</option>
                    <option value="light" <?= $overlay === 'light' ? 'selected' : '' ?>>Light Overlay (35%)</option>
                    <option value="medium" <?= $overlay === 'medium' ? 'selected' : '' ?>>Medium Standard (60%)</option>
                    <option value="dark" <?= $overlay === 'dark' ? 'selected' : '' ?>>High Contrast Dark (80%)</option>
                    <option value="deep" <?= $overlay === 'deep' ? 'selected' : '' ?>>Deep Blackout (95%)</option>
                    <option value="custom" <?= $overlay === 'custom' ? 'selected' : '' ?>>Custom Opacity %</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Custom Opacity (0-100%)</label>
                <input type="number" name="hero_<?= e($pageKey) ?>_custom_overlay" value="<?= e($customOverlay) ?>" min="0" max="100" placeholder="e.g. 45 or 75" class="w-full border border-stone-300 rounded-lg p-2.5 bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-2 border-t border-stone-100">
            <div>
                <?= render_image_uploader_field("hero_{$pageKey}_image", $image, 'Hero Background Image', 'brand', [
                    'required' => false,
                    'helper' => 'High-res image banner with Media Library picker'
                ]) ?>
            </div>
            <div>
                <?= render_video_uploader_field("hero_{$pageKey}_video", $video, 'Hero Background Video', 'videos', [
                    'required' => false,
                    'helper' => 'Optional ambient video loop (MP4/WebM)'
                ]) ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Process POST request for Hero settings
 */
function process_hero_settings_post(): void {
    $keys = $_POST['hero_page_keys'] ?? [];
    foreach ($keys as $pk) {
        $pk = preg_replace('/[^a-zA-Z0-9_-]/', '', $pk);
        if (empty($pk)) continue;

        if (isset($_POST["hero_{$pk}_badge"])) set_hero_setting($pk, 'badge', trim($_POST["hero_{$pk}_badge"]));
        if (isset($_POST["hero_{$pk}_title"])) set_hero_setting($pk, 'title', trim($_POST["hero_{$pk}_title"]));
        if (isset($_POST["hero_{$pk}_subtitle"])) set_hero_setting($pk, 'subtitle', trim($_POST["hero_{$pk}_subtitle"]));
        if (isset($_POST["hero_{$pk}_image"])) set_hero_setting($pk, 'image', trim($_POST["hero_{$pk}_image"]));
        if (isset($_POST["hero_{$pk}_video"])) set_hero_setting($pk, 'video', trim($_POST["hero_{$pk}_video"]));
        if (isset($_POST["hero_{$pk}_height"])) set_hero_setting($pk, 'height', trim($_POST["hero_{$pk}_height"]));
        if (isset($_POST["hero_{$pk}_custom_height"])) set_hero_setting($pk, 'custom_height', trim($_POST["hero_{$pk}_custom_height"]));
        if (isset($_POST["hero_{$pk}_overlay"])) set_hero_setting($pk, 'overlay', trim($_POST["hero_{$pk}_overlay"]));
        if (isset($_POST["hero_{$pk}_custom_overlay"])) set_hero_setting($pk, 'custom_overlay', trim($_POST["hero_{$pk}_custom_overlay"]));
    }
}

/**
 * Retrieve active Font Family name configured in site settings
 */
function get_active_font_family(): string {
    $font = get_setting('site_font_family', 'DM Sans');
    if ($font === 'custom') {
        $custom = get_setting('site_custom_font', '');
        if (!empty($custom)) {
            return trim($custom);
        }
        return 'DM Sans';
    }
    return !empty($font) ? trim($font) : 'DM Sans';
}

/**
 * Render dynamic Google Font link and CSS typography overrides in HTML <head>
 */
function render_google_font_head(): string {
    $font = get_active_font_family();
    $fontSlug = urlencode($font);
    
    $googleFontUrl = "https://fonts.googleapis.com/css2?family=" . $fontSlug . ":ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=Noto+Sans+Khmer:wght@400;500;600;700&family=Noto+Sans+SC:wght@400;500;700&family=Noto+Sans+KR:wght@400;500;700&display=swap";
    
    $html = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    $html .= '<link href="' . e($googleFontUrl) . '" rel="stylesheet">' . "\n";
    $html .= '<style>' . "\n";
    $html .= '  :root {' . "\n";
    $html .= '    --font-active-google: "' . e($font) . '", "Noto Sans Khmer", "Noto Sans SC", "Noto Sans KR", "Be Vietnam Pro", sans-serif;' . "\n";
    $html .= '  }' . "\n";
    $html .= '  body, h1, h2, h3, h4, h5, h6, .font-headline {' . "\n";
    $html .= '    font-family: var(--font-active-google) !important;' . "\n";
    $html .= '  }' . "\n";
    $html .= '</style>' . "\n";

    return $html;
}

/**
 * Format dynamic 3rd-Party OTA Deep Link URL with check-in, check-out, and guest counts
 */
function build_ota_deep_link(string $platform, ?string $baseUrl = null, ?string $checkIn = null, ?string $checkOut = null, int $adults = 2, int $children = 0): string {
    if (empty($baseUrl)) {
        $baseUrl = get_setting("ota_{$platform}_url", '');
    }
    if (empty($baseUrl)) {
        return '#';
    }

    // Default dates fallback if empty
    if (empty($checkIn)) {
        $checkIn = date('Y-m-d', strtotime('+1 day'));
    }
    if (empty($checkOut)) {
        $checkOut = date('Y-m-d', strtotime('+2 days'));
    }

    try {
        $cInDt = new DateTime($checkIn);
        $cOutDt = new DateTime($checkOut);
    } catch (Throwable $e) {
        $cInDt = new DateTime('now');
        $cOutDt = new DateTime('+1 day');
    }

    if ($cOutDt <= $cInDt) {
        $cOutDt = (clone $cInDt)->modify('+1 day');
    }

    $nights = max(1, $cInDt->diff($cOutDt)->days);
    $yIn = $cInDt->format('Y-m-d');
    $yOut = $cOutDt->format('Y-m-d');

    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';

    switch (strtolower($platform)) {
        case 'bookingcom':
        case 'booking':
            $params = [
                'checkin' => $yIn,
                'checkout' => $yOut,
                'group_adults' => max(1, $adults),
                'group_children' => max(0, $children),
                'no_rooms' => 1
            ];
            break;

        case 'traveloka':
            $params = [
                'checkIn' => $cInDt->format('d-m-Y'),
                'checkOut' => $cOutDt->format('d-m-Y'),
                'adult' => max(1, $adults),
                'room' => 1
            ];
            break;

        case 'tripcom':
        case 'trip':
            $params = [
                'checkIn' => $yIn,
                'checkOut' => $yOut,
                'adult' => max(1, $adults),
                'children' => max(0, $children)
            ];
            break;

        case 'agoda':
            $params = [
                'checkIn' => $yIn,
                'los' => $nights,
                'adults' => max(1, $adults),
                'children' => max(0, $children),
                'rooms' => 1
            ];
            break;

        case 'expedia':
            $params = [
                'chkin' => $yIn,
                'chkout' => $yOut,
                'adults' => max(1, $adults)
            ];
            break;

        default:
            $params = [
                'check_in' => $yIn,
                'check_out' => $yOut,
                'adults' => max(1, $adults)
            ];
            break;
    }

    return $baseUrl . $separator . http_build_query($params);
}

/**
 * Retrieve list of active 3rd-party OTA platform channels configured in CMS (Mode 3 Only)
 */
function get_active_ota_channels(): array {
    $mode = get_booking_mode();
    if ($mode !== 'ota' && $mode !== 'multi_channel') {
        return [];
    }

    $platforms = [
        'bookingcom' => [
            'key' => 'bookingcom',
            'name' => 'Booking.com',
            'bg' => 'bg-[#003580]',
            'text' => 'text-white',
            'url' => get_setting('ota_bookingcom_url', ''),
            'enabled' => get_setting('ota_bookingcom_enabled', '0') === '1'
        ],
        'traveloka' => [
            'key' => 'traveloka',
            'name' => 'Traveloka',
            'bg' => 'bg-[#1BA0E2]',
            'text' => 'text-white',
            'url' => get_setting('ota_traveloka_url', ''),
            'enabled' => get_setting('ota_traveloka_enabled', '0') === '1'
        ],
        'tripcom' => [
            'key' => 'tripcom',
            'name' => 'Trip.com',
            'bg' => 'bg-[#2577E3]',
            'text' => 'text-white',
            'url' => get_setting('ota_tripcom_url', ''),
            'enabled' => get_setting('ota_tripcom_enabled', '0') === '1'
        ],
        'agoda' => [
            'key' => 'agoda',
            'name' => 'Agoda',
            'bg' => 'bg-[#5863F8]',
            'text' => 'text-white',
            'url' => get_setting('ota_agoda_url', ''),
            'enabled' => get_setting('ota_agoda_enabled', '0') === '1'
        ]
    ];

    $active = [];
    foreach ($platforms as $key => $p) {
        if ($p['enabled'] || !empty($p['url'])) {
            $active[$key] = $p;
        }
    }

    return $active;
}









