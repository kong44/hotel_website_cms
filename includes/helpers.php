<?php
/**
 * Indra Hotel - Helper Functions
 */

require_once __DIR__ . '/../config.php';

/**
 * Escape HTML output safely (XSS prevention)
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
 * Active navigation helper
 */
function is_active_nav(string $path): string {
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    return ($current === $path) ? 'text-[#4B5320] font-semibold border-b-2 border-[#4B5320]' : 'text-stone-700 hover:text-[#4B5320]';
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
function get_booking_button_text(string $default = 'Book Now'): string {
    $custom = trim(get_setting('booking_button_text', ''));
    return !empty($custom) ? $custom : $default;
}

/**
 * Get Dynamic Booking URL based on configured mode
 */
function get_booking_url(?int $roomId = null, ?string $checkIn = null, ?string $checkOut = null, ?int $adults = null, ?string $promo = null): string {
    if (get_booking_mode() === 'engine') {
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
    }

    $params = [];
    if ($roomId) $params['room_id'] = $roomId;
    if ($checkIn) $params['check_in'] = $checkIn;
    if ($checkOut) $params['check_out'] = $checkOut;
    if ($adults) $params['adults'] = $adults;
    if ($promo) $params['promo'] = $promo;
    
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    return BASE_URL . '/book.php' . $query;
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





