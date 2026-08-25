<?php
/**
 * Indra Hotel - Site & SEO Settings CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';

Auth::requireAdmin();
$pdo = getDB();
$adminTitle = 'Site & SEO Settings';

// Handle Settings Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_url' => trim($_POST['site_url'] ?? ''),
        'site_font_family' => trim($_POST['site_font_family'] ?? 'DM Sans'),
        'site_custom_font' => trim($_POST['site_custom_font'] ?? ''),
        'site_meta_description' => trim($_POST['site_meta_description'] ?? ''),
        'site_meta_keywords' => trim($_POST['site_meta_keywords'] ?? ''),
        'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
        'google_site_verification' => trim($_POST['google_site_verification'] ?? ''),
        'hotel_notice_banner' => trim($_POST['hotel_notice_banner'] ?? ''),
        'booking_mode' => in_array($_POST['booking_mode'] ?? '', ['internal', 'engine', 'ota', 'multi_channel'], true) ? $_POST['booking_mode'] : 'internal',
        'booking_engine_url' => trim($_POST['booking_engine_url'] ?? ''),
        'booking_engine_target' => in_array($_POST['booking_engine_target'] ?? '', ['_blank', '_self'], true) ? $_POST['booking_engine_target'] : '_blank',
        'booking_button_text' => trim($_POST['booking_button_text'] ?? 'Book Now'),
        'ota_bookingcom_url' => trim($_POST['ota_bookingcom_url'] ?? ''),
        'ota_bookingcom_enabled' => isset($_POST['ota_bookingcom_enabled']) ? '1' : '0',
        'ota_traveloka_url' => trim($_POST['ota_traveloka_url'] ?? ''),
        'ota_traveloka_enabled' => isset($_POST['ota_traveloka_enabled']) ? '1' : '0',
        'ota_tripcom_url' => trim($_POST['ota_tripcom_url'] ?? ''),
        'ota_tripcom_enabled' => isset($_POST['ota_tripcom_enabled']) ? '1' : '0',
        'ota_agoda_url' => trim($_POST['ota_agoda_url'] ?? ''),
        'ota_agoda_enabled' => isset($_POST['ota_agoda_enabled']) ? '1' : '0',
        'ota_deeplink_url' => trim($_POST['ota_deeplink_url'] ?? ''),
        'ota_platform_type' => trim($_POST['ota_platform_type'] ?? 'auto')
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    // SQLite compatible upsert
    if (Database::getDriver() === 'sqlite') {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
    }

    foreach ($settings as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    
    // Refresh memory cache
    get_setting('__refresh__');
    set_flash('success', 'Site, SEO & Booking Mode settings updated successfully.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

// Handle Google OAuth Settings Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['save_google_oauth']) || (($_POST['action'] ?? '') === 'save_google_oauth'))) {
    $googleSettings = [
        'google_oauth_enabled' => isset($_POST['google_oauth_enabled']) ? '1' : '0',
        'google_oauth_client_id' => trim($_POST['google_oauth_client_id'] ?? ''),
        'google_oauth_client_secret' => trim($_POST['google_oauth_client_secret'] ?? '')
    ];

    $stmtUpsert = (Database::getDriver() === 'sqlite')
        ? $pdo->prepare("INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)")
        : $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($googleSettings as $k => $v) {
        $stmtUpsert->execute([$k, $v]);
    }

    get_setting('__refresh__');
    set_flash('success', 'Google OAuth 2.0 configuration updated successfully.');
    header('Location: ' . BASE_URL . '/admin/settings.php#google-oauth');
    exit;
}

// Handle Admin Password Change
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $user = Auth::user();
    $stmtU = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmtU->execute([$user['id']]);
    $u = $stmtU->fetch();

    if (!$u || !password_verify($currentPass, $u['password_hash'])) {
        set_flash('error', 'Current password incorrect.');
    } elseif (strlen($newPass) < 6) {
        set_flash('error', 'New password must be at least 6 characters long.');
    } elseif ($newPass !== $confirmPass) {
        set_flash('error', 'New passwords do not match.');
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        set_flash('success', 'Password updated successfully.');
    }
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="max-w-4xl space-y-10">
    
    <div>
        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">System & SEO Configuration</h2>
        <p class="text-xs text-stone-500">Configure global search engine optimization tags, announcements, and admin security.</p>
    </div>

    <!-- SEO & General Meta Form -->
    <form action="<?= BASE_URL ?>/admin/settings.php" method="POST" class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-6">
        <input type="hidden" name="save_settings" value="1">
        
        <div>
            <h3 class="font-headline font-bold text-lg text-onyx-charcoal pb-2 border-b border-stone-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#4B5320]">travel_explore</span>
                <span>Search Engine Optimization (SEO)</span>
            </h3>
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Global Meta Title</label>
            <input type="text" name="site_title" value="<?= e(get_setting('site_title')) ?>" 
                   class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
            <p class="text-[11px] text-stone-400 mt-1">Recommended length: 50-60 characters.</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Global Meta Description</label>
            <textarea name="site_meta_description" rows="3" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('site_meta_description')) ?></textarea>
            <p class="text-[11px] text-stone-400 mt-1">Recommended length: 150-160 characters for optimal Google snippet display.</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Meta Keywords (Comma separated)</label>
            <input type="text" name="site_meta_keywords" value="<?= e(get_setting('site_meta_keywords')) ?>" 
                   class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Google Search Console Verification Tag</label>
                <input type="text" name="google_site_verification" value="<?= e(get_setting('google_site_verification')) ?>" placeholder="google-site-verification code"
                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Google Analytics Tracking ID</label>
                <input type="text" name="google_analytics_id" value="<?= e(get_setting('google_analytics_id')) ?>" placeholder="e.g. G-XXXXXXX"
                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
            </div>
        </div>

        <!-- Primary Domain & Base URL Setting -->
        <div class="pt-6 border-t border-stone-100 space-y-3">
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">dns</span>
                    <span>Primary Domain & Base URL</span>
                </h3>
                <p class="text-xs text-stone-500 mt-0.5">Configure your official site address used across the CMS for media uploads, API calls, and public links.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Primary Site Base URL</label>
                <input type="url" name="site_url" value="<?= e(get_setting('site_url')) ?>" placeholder="e.g. https://hotel.kong41.com"
                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                <p class="text-[11px] text-stone-400 mt-1">Leave empty to auto-detect domain dynamically from incoming requests. Protocol (https://) is enforced automatically on SSL connections.</p>
            </div>
        </div>

        <!-- Typography & Google Fonts Style Selector -->
        <div class="pt-6 border-t border-stone-100 space-y-4">
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">font_download</span>
                    <span>Typography & Google Fonts Style</span>
                </h3>
                <p class="text-xs text-stone-500 mt-0.5">Select the primary font family for body text and headlines across the website, dynamically loaded from Google Fonts.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Font Family Style</label>
                    <?php 
                    $selectedFont = get_setting('site_font_family', 'DM Sans');
                    $popularFonts = [
                        'DM Sans' => 'DM Sans (Modern Clean Sans-Serif - Default)',
                        'Inter' => 'Inter (Modern Minimalist)',
                        'Plus Jakarta Sans' => 'Plus Jakarta Sans (Contemporary Geometric)',
                        'Montserrat' => 'Montserrat (Geometric Display)',
                        'Outfit' => 'Outfit (Clean High-Contrast)',
                        'Poppins' => 'Poppins (Soft Geometric)',
                        'Roboto' => 'Roboto (Standard Sans-Serif)',
                        'Be Vietnam Pro' => 'Be Vietnam Pro (Boutique Luxury)',
                        'Playfair Display' => 'Playfair Display (Luxury Editorial Serif)',
                        'Cormorant Garamond' => 'Cormorant Garamond (High-End Heritage Serif)',
                        'Cinzel' => 'Cinzel (Classical Elegant Serif)',
                        'Lora' => 'Lora (Refined Contemporary Serif)',
                        'Merriweather' => 'Merriweather (Classic Editorial Serif)',
                        'custom' => '✨ Custom Google Font (Write below)'
                    ];
                    ?>
                    <select name="site_font_family" id="site_font_family_select" onchange="toggleCustomFontInput(this.value)" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <?php foreach ($popularFonts as $fontVal => $fontLabel): ?>
                            <option value="<?= e($fontVal) ?>" <?= ($selectedFont === $fontVal) ? 'selected' : '' ?>>
                                <?= e($fontLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="custom-font-wrapper" class="<?= ($selectedFont === 'custom') ? '' : 'hidden' ?>">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Custom Google Font Name *</label>
                    <input type="text" name="site_custom_font" id="site_custom_font_input" value="<?= e(get_setting('site_custom_font')) ?>" placeholder="e.g. Syne, Bodoni Moda, Oswald"
                           oninput="updateFontPreview(this.value)"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Enter exact font name from <a href="https://fonts.google.com" target="_blank" class="underline text-[#4B5320]">fonts.google.com</a>.</p>
                </div>
            </div>

            <!-- Live Font Preview Box -->
            <div class="p-5 rounded-xl bg-stone-50 border border-stone-200 text-stone-800 space-y-1.5 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400">Live Typography Preview</span>
                    <span class="text-[10px] font-mono text-[#4B5320] font-bold" id="font-preview-name"><?= e(get_active_font_family()) ?></span>
                </div>
                <div class="font-headline font-bold text-xl text-onyx-charcoal" id="font-preview-headline">Boutique Luxury Sanctuary & Hospitality</div>
                <div class="text-xs text-stone-600 font-light leading-relaxed" id="font-preview-body">Experience contemporary elegance and serene comfort at <?= e(hotel_name()) ?> Phnom Penh.</div>
            </div>
        </div>

        <!-- Hotel Reservation & Booking Engine Mode Selector (3 Distinct Options) -->
        <div class="pt-6 border-t border-stone-100 space-y-5">
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">calendar_month</span>
                    <span>Hotel Booking System Modes (3 Distinct Options)</span>
                </h3>
                <p class="text-xs text-stone-500 mt-0.5">Select how guests make room reservations on the hotel website.</p>
            </div>

            <!-- Three Mode Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Mode 1: Direct Built-in Reservation Inquiry -->
                <label class="relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none <?= (get_booking_mode() === 'internal') ? 'border-[#343c0a] bg-stone-50/70 shadow-xs' : 'border-stone-200 hover:border-stone-300 bg-white' ?>" id="mode-card-internal">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">mark_email_read</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Mode 1</span>
                                <h4 class="font-headline font-bold text-sm text-onyx-charcoal">Direct Reservation</h4>
                            </div>
                        </div>
                        <input type="radio" name="booking_mode" value="internal" <?= (get_booking_mode() === 'internal') ? 'checked' : '' ?> onchange="toggleBookingModeUI('internal')" class="w-4 h-4 text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500 mt-3 leading-relaxed">
                        Built-in inquiry form with Google Sign-in verification, email dispatches, and guest portal tracking.
                    </p>
                    <div class="mt-3 pt-3 border-t border-stone-200/60 flex items-center gap-1.5 text-[11px] text-[#4B5320] font-semibold">
                        <span class="material-symbols-outlined text-sm">mark_email_read</span>
                        <span>Direct On-Site Reservation</span>
                    </div>
                </label>

                <!-- Mode 2: Single External Booking Engine URL -->
                <label class="relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none <?= (get_booking_mode() === 'engine') ? 'border-[#343c0a] bg-stone-50/70 shadow-xs' : 'border-stone-200 hover:border-stone-300 bg-white' ?>" id="mode-card-engine">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">open_in_new</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-800 block">Mode 2</span>
                                <h4 class="font-headline font-bold text-sm text-onyx-charcoal">External Engine Link</h4>
                            </div>
                        </div>
                        <input type="radio" name="booking_mode" value="engine" <?= (get_booking_mode() === 'engine') ? 'checked' : '' ?> onchange="toggleBookingModeUI('engine')" class="w-4 h-4 text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500 mt-3 leading-relaxed">
                        Redirects all "Book Now" clicks directly to your primary 3rd-party reservation engine (e.g. Cloudbeds, Sirvoy, Guesty).
                    </p>
                    <div class="mt-3 pt-3 border-t border-stone-200/60 flex items-center gap-1.5 text-[11px] text-blue-800 font-semibold">
                        <span class="material-symbols-outlined text-sm">link</span>
                        <span>Redirects to External Engine URL</span>
                    </div>
                </label>

                <!-- Mode 3: Multi-Channel 3rd-Party OTA Partner Deep Links -->
                <label class="relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none <?= (in_array(get_booking_mode(), ['ota', 'multi_channel'], true)) ? 'border-[#343c0a] bg-stone-50/70 shadow-xs' : 'border-stone-200 hover:border-stone-300 bg-white' ?>" id="mode-card-ota">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-800 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg">travel_explore</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-purple-800 block">Mode 3</span>
                                <h4 class="font-headline font-bold text-sm text-onyx-charcoal">3rd-Party OTA Links</h4>
                            </div>
                        </div>
                        <input type="radio" name="booking_mode" value="ota" <?= (in_array(get_booking_mode(), ['ota', 'multi_channel'], true)) ? 'checked' : '' ?> onchange="toggleBookingModeUI('ota')" class="w-4 h-4 text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500 mt-3 leading-relaxed">
                        Presents guests with partner OTA channels (Booking.com, Traveloka, Trip.com, Agoda) with dynamic date filter deep linking.
                    </p>
                    <div class="mt-3 pt-3 border-t border-stone-200/60 flex items-center gap-1.5 text-[11px] text-purple-800 font-semibold">
                        <span class="material-symbols-outlined text-sm">travel_explore</span>
                        <span>Multi-Channel OTA Deep Links</span>
                    </div>
                </label>
            </div>

            <!-- External Engine Configuration Parameters -->
            <div id="engine-config-box" class="p-5 rounded-xl bg-stone-50 border border-stone-200 space-y-4 <?= (get_booking_mode() === 'engine') ? '' : 'hidden' ?>">
                <h4 class="text-xs font-bold uppercase tracking-wider text-stone-800 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-[#343c0a]">settings_ethernet</span>
                    <span>External Booking Engine Link Settings</span>
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">External Booking Engine URL *</label>
                        <input type="url" name="booking_engine_url" value="<?= e(get_setting('booking_engine_url')) ?>" placeholder="https://hotels.cloudbeds.com/reservation/XXXXX or https://www.booking.com/hotel/..."
                               class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                        <p class="text-[11px] text-stone-500 mt-1">Full URL to your external reservation gateway.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Link Target Behavior</label>
                        <select name="booking_engine_target" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                            <option value="_blank" <?= (get_setting('booking_engine_target', '_blank') === '_blank') ? 'selected' : '' ?>>Open in New Tab / Window (_blank)</option>
                            <option value="_self" <?= (get_setting('booking_engine_target', '_blank') === '_self') ? 'selected' : '' ?>>Open in Same Tab (_self)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Custom Button Label</label>
                        <input type="text" name="booking_button_text" value="<?= e(get_setting('booking_button_text', 'Book Now')) ?>" placeholder="Book Now"
                               class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                    </div>
                </div>
            </div>
        </div>

        <!-- Mode 3: 3rd-Party OTA Property Deep Link Settings -->
        <div id="ota-config-box" class="p-5 rounded-xl bg-stone-50 border border-stone-200 space-y-4 <?= (in_array(get_booking_mode(), ['ota', 'multi_channel'], true)) ? '' : 'hidden' ?>">
            <h4 class="text-xs font-bold uppercase tracking-wider text-stone-800 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-[#343c0a]">travel_explore</span>
                <span>Mode 3: 3rd-Party OTA Property Deep Link Settings</span>
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">3rd-Party OTA Property Deep Link URL *</label>
                    <input type="url" name="ota_deeplink_url" value="<?= e(get_setting('ota_deeplink_url', get_setting('ota_bookingcom_url'))) ?>" placeholder="https://www.booking.com/hotel/kh/your-hotel-name.html or https://www.traveloka.com/en-ph/hotel/cambodia/your-hotel-name"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                    <p class="text-[11px] text-stone-500 mt-1">Enter your hotel property URL from Booking.com, Traveloka, Trip.com, Agoda, etc. Check-in and check-out dates selected on the homepage search bar will be appended dynamically.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Platform URL Format</label>
                    <?php $pType = get_setting('ota_platform_type', 'auto'); ?>
                    <select name="ota_platform_type" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                        <option value="auto" <?= ($pType === 'auto') ? 'selected' : '' ?>>✨ Auto-Detect Platform from URL</option>
                        <option value="bookingcom" <?= ($pType === 'bookingcom') ? 'selected' : '' ?>>Booking.com (checkin=YYYY-MM-DD & checkout=YYYY-MM-DD)</option>
                        <option value="traveloka" <?= ($pType === 'traveloka') ? 'selected' : '' ?>>Traveloka (checkIn=DD-MM-YYYY & checkOut=DD-MM-YYYY)</option>
                        <option value="tripcom" <?= ($pType === 'tripcom') ? 'selected' : '' ?>>Trip.com (checkIn=YYYY-MM-DD & checkOut=YYYY-MM-DD)</option>
                        <option value="agoda" <?= ($pType === 'agoda') ? 'selected' : '' ?>>Agoda (checkIn=YYYY-MM-DD & los=NIGHTS)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Search Button Label</label>
                    <input type="text" name="booking_button_text" value="<?= e(get_setting('booking_button_text', 'Search Booking')) ?>" placeholder="Search Booking"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a] bg-white">
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-stone-100">
            <h3 class="font-headline font-bold text-base text-onyx-charcoal mb-3">Notice Banner</h3>
            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Top Announcement Bar Text</label>
            <input type="text" name="hotel_notice_banner" value="<?= e(get_setting('hotel_notice_banner')) ?>" 
                   class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
        </div>

        <div class="pt-4 border-t border-stone-200 flex justify-end">
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow">
                Save Settings
            </button>
        </div>
    </form>

    <script>
    function toggleBookingModeUI(mode) {
        const internalCard = document.getElementById('mode-card-internal');
        const engineCard = document.getElementById('mode-card-engine');
        const otaCard = document.getElementById('mode-card-ota');
        const engineBox = document.getElementById('engine-config-box');
        const otaBox = document.getElementById('ota-config-box');

        [internalCard, engineCard, otaCard].forEach(c => {
            if (c) c.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-stone-200 hover:border-stone-300 bg-white';
        });

        if (mode === 'internal' && internalCard) {
            internalCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-[#343c0a] bg-stone-50/70 shadow-xs';
            if (engineBox) engineBox.classList.add('hidden');
            if (otaBox) otaBox.classList.add('hidden');
        } else if (mode === 'engine' && engineCard) {
            engineCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-[#343c0a] bg-stone-50/70 shadow-xs';
            if (engineBox) engineBox.classList.remove('hidden');
            if (otaBox) otaBox.classList.add('hidden');
        } else if ((mode === 'ota' || mode === 'multi_channel') && otaCard) {
            otaCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-[#343c0a] bg-stone-50/70 shadow-xs';
            if (engineBox) engineBox.classList.add('hidden');
            if (otaBox) otaBox.classList.remove('hidden');
        }
    }

    function toggleCustomFontInput(val) {
        const customWrapper = document.getElementById('custom-font-wrapper');
        const customInput = document.getElementById('site_custom_font_input');
        if (val === 'custom') {
            if (customWrapper) customWrapper.classList.remove('hidden');
            if (customInput) updateFontPreview(customInput.value || 'DM Sans');
        } else {
            if (customWrapper) customWrapper.classList.add('hidden');
            updateFontPreview(val);
        }
    }

    function updateFontPreview(fontName) {
        if (!fontName || fontName.trim() === '') fontName = 'DM Sans';
        const previewName = document.getElementById('font-preview-name');
        const previewHead = document.getElementById('font-preview-headline');
        const previewBody = document.getElementById('font-preview-body');
        
        if (previewName) previewName.textContent = fontName;
        
        // Dynamically load Google Font for live preview
        const fontSlug = encodeURIComponent(fontName.trim());
        const fontUrl = `https://fonts.googleapis.com/css2?family=${fontSlug}:wght@400;600;700&display=swap`;
        
        let link = document.getElementById('dynamic-google-font-preview-link');
        if (!link) {
            link = document.createElement('link');
            link.id = 'dynamic-google-font-preview-link';
            link.rel = 'stylesheet';
            document.head.appendChild(link);
        }
        link.href = fontUrl;

        if (previewHead) previewHead.style.fontFamily = `"${fontName}", sans-serif`;
        if (previewBody) previewBody.style.fontFamily = `"${fontName}", sans-serif`;
    }
    </script>

    <!-- =======================================================
         Google OAuth 2.0 Single Sign-On (SSO) Configuration Card
         ======================================================= -->
    <?php
    $googleOauthEnabled = (get_setting('google_oauth_enabled', '0') === '1');
    $googleClientId = get_setting('google_oauth_client_id', '');
    $googleClientSecret = get_setting('google_oauth_client_secret', '');
    $googleReady = GoogleAuth::isEnabled();
    ?>
    <div id="google-oauth" class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden scroll-mt-6">
        
        <!-- Header -->
        <div class="p-6 sm:p-8 bg-gradient-to-r from-stone-900 to-[#1e2307] text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white p-2.5 flex items-center justify-center shrink-0 shadow-md">
                    <svg class="w-full h-full" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-xl text-white">Google Sign-in for Public Guest Portal</h2>
                    <p class="text-xs text-stone-300">Allow public visitors and guests to sign in with Google to prevent spam and track their booking requests.</p>
                </div>
            </div>

            <div>
                <?php if ($googleReady): ?>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/40">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Active for Public Guests</span>
                    </span>
                <?php elseif ($googleOauthEnabled): ?>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-400/40">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <span>Enabled (Missing Keys)</span>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-stone-700 text-stone-300">
                        <span class="w-2 h-2 rounded-full bg-stone-500"></span>
                        <span>Disabled</span>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-8">
            
            <!-- Setup Guide Alert Box -->
            <div class="bg-blue-50/90 rounded-2xl p-6 border border-blue-200 text-xs text-blue-950 space-y-4">
                <div class="flex items-center gap-2 font-bold text-sm text-blue-900">
                    <span class="material-symbols-outlined text-lg text-blue-700">integration_instructions</span>
                    <span>Google Cloud Console Credentials Setup Guide</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <!-- Origins Box -->
                    <div class="bg-white p-4 rounded-xl border border-blue-200 space-y-1.5">
                        <span class="font-bold text-stone-700 uppercase tracking-wider text-[10px] block">1. Authorized JavaScript Origins</span>
                        <div class="flex items-center justify-between gap-2 bg-stone-100 p-2.5 rounded font-mono text-xs text-stone-800">
                            <span id="origin_val" class="truncate"><?= e(BASE_URL) ?></span>
                            <button type="button" onclick="copyToClip('origin_val', this)" class="text-blue-700 hover:text-blue-900 font-bold shrink-0 flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                <span>Copy</span>
                            </button>
                        </div>
                    </div>

                    <!-- Redirect URI Box -->
                    <div class="bg-white p-4 rounded-xl border border-blue-200 space-y-1.5">
                        <span class="font-bold text-stone-700 uppercase tracking-wider text-[10px] block">2. Authorized Redirect URI (Required)</span>
                        <div class="flex items-center justify-between gap-2 bg-stone-100 p-2.5 rounded font-mono text-xs text-stone-800">
                            <span id="redirect_val" class="truncate"><?= e(BASE_URL) ?>/api/guest-google-callback.php</span>
                            <button type="button" onclick="copyToClip('redirect_val', this)" class="text-blue-700 hover:text-blue-900 font-bold shrink-0 flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                <span>Copy</span>
                            </button>
                        </div>
                    </div>
                </div>

                <ol class="list-decimal list-inside space-y-1.5 text-[11px] text-blue-900 pt-1">
                    <li>Visit the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" class="underline font-bold text-blue-700 hover:text-blue-900 inline-flex items-center gap-0.5">Google Cloud Console Credentials Page <span class="material-symbols-outlined text-xs">open_in_new</span></a>.</li>
                    <li>Click your OAuth 2.0 Client ID (or <strong>+ Create Credentials ➔ OAuth Client ID</strong> for Web application).</li>
                    <li>Add the <strong>Authorized JavaScript Origin</strong> and <strong>Authorized redirect URI</strong> listed above.</li>
                    <li>Click <strong>Save</strong> in Google Cloud Console, then paste your Client ID and Client Secret below and click <strong>Save Google OAuth Settings</strong>.</li>
                </ol>
            </div>

            <!-- Configuration Form -->
            <form action="<?= BASE_URL ?>/admin/settings.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="save_google_oauth">

                <!-- Toggle Switch -->
                <div class="flex items-center justify-between p-4 bg-stone-50 rounded-xl border border-stone-200">
                    <div>
                        <label for="google_oauth_enabled" class="font-bold text-sm text-onyx-charcoal cursor-pointer">Enable Google Sign-in for Guest Portal & Booking</label>
                        <p class="text-xs text-stone-500">When enabled, visitors can click "Sign in with Google" on the booking page (<a href="<?= BASE_URL ?>/book.php" target="_blank" class="underline font-semibold text-[#343c0a]">book.php</a>) and guest portal.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="google_oauth_enabled" id="google_oauth_enabled" value="1" <?= $googleOauthEnabled ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                    <!-- Client ID -->
                    <div>
                        <label class="block font-bold text-stone-700 uppercase tracking-wider mb-1">Google OAuth Client ID *</label>
                        <input type="text" name="google_oauth_client_id" value="<?= e($googleClientId) ?>" 
                               placeholder="e.g. 1234567890-abcdefg.apps.googleusercontent.com"
                               class="w-full border border-stone-300 rounded-lg p-2.5 font-mono text-xs focus:ring-2 focus:ring-[#343c0a]">
                        <p class="text-[10px] text-stone-400 mt-1">Found in Google Cloud Console under OAuth 2.0 Client IDs.</p>
                    </div>

                    <!-- Client Secret -->
                    <div>
                        <label class="block font-bold text-stone-700 uppercase tracking-wider mb-1">Google OAuth Client Secret *</label>
                        <div class="relative">
                            <input type="password" name="google_oauth_client_secret" id="google_oauth_client_secret" value="<?= e($googleClientSecret) ?>" 
                                   placeholder="e.g. GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx"
                                   class="w-full border border-stone-300 rounded-lg p-2.5 pr-10 font-mono text-xs focus:ring-2 focus:ring-[#343c0a]">
                            <button type="button" onclick="toggleSecretVisibility()" class="absolute right-2 top-2.5 text-stone-400 hover:text-stone-700 p-1 cursor-pointer">
                                <span class="material-symbols-outlined text-base" id="secret_eye_icon">visibility</span>
                            </button>
                        </div>
                        <p class="text-[10px] text-stone-400 mt-1">Kept securely on the server.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-stone-200 flex justify-end">
                    <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow cursor-pointer">
                        Save Google OAuth Settings
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
    function copyToClip(elementId, btn) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(() => {
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span><span>Copied!</span>';
            btn.classList.add('text-emerald-700');
            setTimeout(() => {
                btn.innerHTML = origHTML;
                btn.classList.remove('text-emerald-700');
            }, 2000);
        });
    }

    function toggleSecretVisibility() {
        const input = document.getElementById('google_oauth_client_secret');
        const icon = document.getElementById('secret_eye_icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }
    </script>

    <!-- Admin Password Change Form -->
    <form action="<?= BASE_URL ?>/admin/settings.php" method="POST" class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-6">
        <input type="hidden" name="change_password" value="1">
        
        <div>
            <h3 class="font-headline font-bold text-lg text-onyx-charcoal pb-2 border-b border-stone-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#4B5320]">lock</span>
                <span>Change Admin Password</span>
            </h3>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Current Password *</label>
                <input type="password" name="current_password" required class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">New Password *</label>
                <input type="password" name="new_password" required minlength="6" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Confirm New Password *</label>
                <input type="password" name="confirm_password" required minlength="6" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
            </div>
        </div>

        <div class="pt-4 border-t border-stone-200 flex justify-end">
            <button type="submit" class="bg-stone-900 hover:bg-black text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow">
                Update Password
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
