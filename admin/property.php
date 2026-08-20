<?php
/**
 * Indra Hotel - Property & Brand Management CMS
 * Manage Hotel Brand Name, Logo, Favicon, Brand Color Palettes, Contact Info, Map, and Socials
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = getDB();
$adminTitle = 'Property & Brand Identity';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_property'])) {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/property.php');
        exit;
    }

    $brandSettings = [
        'hotel_name' => trim($_POST['hotel_name'] ?? 'Indra Hotel'),
        'hotel_tagline' => trim($_POST['hotel_tagline'] ?? 'Contemporary Boutique Sanctuary'),
        'hotel_logo_url' => trim($_POST['hotel_logo_url'] ?? ''),
        'hotel_favicon_url' => trim($_POST['hotel_favicon_url'] ?? ''),
        
        // Brand Color Scheme
        'hotel_brand_accent' => trim($_POST['hotel_brand_accent'] ?? '#343c0a'),
        'hotel_brand_secondary' => trim($_POST['hotel_brand_secondary'] ?? '#4B5320'),
        'hotel_brand_highlight' => trim($_POST['hotel_brand_highlight'] ?? '#dfe8a6'),
        'hotel_brand_dark' => trim($_POST['hotel_brand_dark'] ?? '#191c1d'),

        // Contact & Property Details
        'hotel_email' => trim($_POST['hotel_email'] ?? ''),
        'hotel_phone' => trim($_POST['hotel_phone'] ?? '(+855) 16 889 066'),
        'hotel_whatsapp' => trim($_POST['hotel_whatsapp'] ?? '+85516889066'),
        'hotel_address' => trim($_POST['hotel_address'] ?? '#95, Street 592, Beungkak 2, Tuol Kork, Phnom Penh, Cambodia'),
        'hotel_checkin_time' => trim($_POST['hotel_checkin_time'] ?? '14:00'),
        'hotel_checkout_time' => trim($_POST['hotel_checkout_time'] ?? '12:00'),
        'hotel_currency_symbol' => trim($_POST['hotel_currency_symbol'] ?? '$'),
        'hotel_currency_code' => trim($_POST['hotel_currency_code'] ?? 'USD'),
        'hotel_google_maps_embed' => trim($_POST['hotel_google_maps_embed'] ?? ''),

        // Announcement Banner
        'hotel_notice_banner_active' => isset($_POST['hotel_notice_banner_active']) ? '1' : '0',
        'hotel_notice_banner' => trim($_POST['hotel_notice_banner'] ?? ''),

        // Social Media & OTAs
        'social_facebook' => trim($_POST['social_facebook'] ?? ''),
        'social_instagram' => trim($_POST['social_instagram'] ?? ''),
        'social_tripadvisor' => trim($_POST['social_tripadvisor'] ?? ''),
        'social_telegram' => trim($_POST['social_telegram'] ?? ''),
        'social_booking_com' => trim($_POST['social_booking_com'] ?? ''),
        'social_agoda' => trim($_POST['social_agoda'] ?? '')
    ];

    $isSqlite = (Database::getDriver() === 'sqlite');
    $query = $isSqlite
        ? "INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)"
        : "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

    $stmt = $pdo->prepare($query);
    foreach ($brandSettings as $key => $val) {
        $stmt->execute([$key, $val]);
    }

    set_flash('success', 'Property branding and hotel details saved successfully.');
    header('Location: ' . BASE_URL . '/admin/property.php');
    exit;
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="max-w-5xl space-y-8">
    
    <!-- Page Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-stone-200">
        <div>
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Heritage Management</span>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Property & Brand Identity</h1>
            <p class="text-xs text-stone-500 mt-1">Customize your hotel brand name, official logos, brand colors, contact coordinates, and social media channels.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 border border-stone-300 hover:border-stone-400 rounded-lg text-xs font-semibold text-stone-700 bg-white transition shadow-xs">
                <span class="material-symbols-outlined text-base text-[#4B5320]">visibility</span>
                <span>Live Preview Site</span>
            </a>
        </div>
    </div>

    <form action="<?= BASE_URL ?>/admin/property.php" method="POST" class="space-y-10">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
        <input type="hidden" name="save_property" value="1">

        <!-- ============================================== -->
        <!-- 1. Brand Identity & Logo                       -->
        <!-- ============================================== -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">badge</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Brand Identity & Visuals</h2>
                    <p class="text-xs text-stone-500">Official property name, slogan, logo asset and favicon.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Hotel Brand Name *</label>
                    <input type="text" name="hotel_name" id="prop_hotel_name" required
                           value="<?= e(get_setting('hotel_name', HOTEL_NAME)) ?>" 
                           oninput="updateBrandLivePreview()"
                           class="w-full text-sm font-medium border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Displayed across headers, emails, reservations, and SEO.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Hotel Tagline / Slogan</label>
                    <input type="text" name="hotel_tagline" id="prop_hotel_tagline"
                           value="<?= e(get_setting('hotel_tagline', 'Contemporary Boutique Sanctuary')) ?>" 
                           oninput="updateBrandLivePreview()"
                           class="w-full text-sm font-medium border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Subtitle displayed in hero sections, metadata, and branding.</p>
                </div>

                <div class="space-y-1">
                    <?= render_image_uploader_field('hotel_logo_url', hotel_logo_url(), 'Primary Brand Logo', 'brand', [
                        'helper' => 'PNG, SVG, or WEBP transparent logo'
                    ]) ?>
                </div>

                <div class="space-y-1">
                    <?= render_image_uploader_field('hotel_favicon_url', hotel_favicon_url(), 'Browser Tab Favicon', 'brand', [
                        'helper' => 'Small PNG or ICO (32x32 or 64x64)'
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 2. Brand Color Palette Customizer              -->
        <!-- ============================================== -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">palette</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Brand Color Palette</h2>
                        <p class="text-xs text-stone-500">Live dynamic colors for buttons, navigation, highlights, and dark modes.</p>
                    </div>
                </div>

                <!-- Palette Preset Chips -->
                <div class="flex items-center gap-1.5 flex-wrap text-xs">
                    <span class="text-stone-400 text-[11px] mr-1 font-semibold uppercase">Presets:</span>
                    <button type="button" onclick="applyColorPreset('#343c0a', '#4B5320', '#dfe8a6', '#191c1d')" class="px-2.5 py-1 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium transition cursor-pointer">Indra Olive</button>
                    <button type="button" onclick="applyColorPreset('#1a2930', '#0f1f2c', '#c5a059', '#0d1318')" class="px-2.5 py-1 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium transition cursor-pointer">Midnight Gold</button>
                    <button type="button" onclick="applyColorPreset('#1b4332', '#2d6a4f', '#d8f3dc', '#081c15')" class="px-2.5 py-1 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium transition cursor-pointer">Emerald Sanctuary</button>
                    <button type="button" onclick="applyColorPreset('#4a1525', '#6b2039', '#ffd6e0', '#200b12')" class="px-2.5 py-1 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium transition cursor-pointer">Royal Ruby</button>
                </div>
            </div>

            <!-- 4-Color Matrix -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Color 1: Primary Accent -->
                <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">Primary Accent</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="color_accent_picker" value="<?= e(get_setting('hotel_brand_accent', '#343c0a')) ?>"
                               oninput="syncColorInput('accent', this.value)"
                               class="w-9 h-9 p-0.5 rounded border border-stone-300 cursor-pointer bg-white">
                        <input type="text" name="hotel_brand_accent" id="color_accent_text"
                               value="<?= e(get_setting('hotel_brand_accent', '#343c0a')) ?>"
                               oninput="syncColorPicker('accent', this.value)"
                               class="flex-1 text-xs font-mono border border-stone-300 rounded p-2 focus:ring-2 focus:ring-[#343c0a]">
                    </div>
                    <p class="text-[10px] text-stone-400">Buttons, active headers, key callouts.</p>
                </div>

                <!-- Color 2: Secondary Olive -->
                <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">Secondary Accent</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="color_secondary_picker" value="<?= e(get_setting('hotel_brand_secondary', '#4B5320')) ?>"
                               oninput="syncColorInput('secondary', this.value)"
                               class="w-9 h-9 p-0.5 rounded border border-stone-300 cursor-pointer bg-white">
                        <input type="text" name="hotel_brand_secondary" id="color_secondary_text"
                               value="<?= e(get_setting('hotel_brand_secondary', '#4B5320')) ?>"
                               oninput="syncColorPicker('secondary', this.value)"
                               class="flex-1 text-xs font-mono border border-stone-300 rounded p-2 focus:ring-2 focus:ring-[#343c0a]">
                    </div>
                    <p class="text-[10px] text-stone-400">Badges, icon accents, section subtitles.</p>
                </div>

                <!-- Color 3: Champagne Highlight -->
                <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">Light Highlight</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="color_highlight_picker" value="<?= e(get_setting('hotel_brand_highlight', '#dfe8a6')) ?>"
                               oninput="syncColorInput('highlight', this.value)"
                               class="w-9 h-9 p-0.5 rounded border border-stone-300 cursor-pointer bg-white">
                        <input type="text" name="hotel_brand_highlight" id="color_highlight_text"
                               value="<?= e(get_setting('hotel_brand_highlight', '#dfe8a6')) ?>"
                               oninput="syncColorPicker('highlight', this.value)"
                               class="flex-1 text-xs font-mono border border-stone-300 rounded p-2 focus:ring-2 focus:ring-[#343c0a]">
                    </div>
                    <p class="text-[10px] text-stone-400">Pills, active language tab, warm glows.</p>
                </div>

                <!-- Color 4: Dark Onyx -->
                <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">Dark Shade</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="color_dark_picker" value="<?= e(get_setting('hotel_brand_dark', '#191c1d')) ?>"
                               oninput="syncColorInput('dark', this.value)"
                               class="w-9 h-9 p-0.5 rounded border border-stone-300 cursor-pointer bg-white">
                        <input type="text" name="hotel_brand_dark" id="color_dark_text"
                               value="<?= e(get_setting('hotel_brand_dark', '#191c1d')) ?>"
                               oninput="syncColorPicker('dark', this.value)"
                               class="flex-1 text-xs font-mono border border-stone-300 rounded p-2 focus:ring-2 focus:ring-[#343c0a]">
                    </div>
                    <p class="text-[10px] text-stone-400">Headings, dark cards, footer backgrounds.</p>
                </div>
            </div>

            <!-- Real-time Visual Interactive Preview Component -->
            <div class="pt-4">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500 block mb-2">Live UI Component Simulation:</span>
                <div id="brand-live-preview-box" class="rounded-xl p-6 border transition-all duration-300 shadow-sm" style="background-color: #191c1d; color: #ffffff;">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div id="preview-logo-box" class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm" style="background-color: #dfe8a6; color: #343c0a;">
                                IH
                            </div>
                            <div>
                                <div id="preview-brand-title" class="font-headline font-bold text-lg text-white">Indra Hotel</div>
                                <div id="preview-brand-slogan" class="text-xs text-stone-300">Contemporary Boutique Sanctuary</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <span id="preview-badge" class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase" style="background-color: rgba(223, 232, 166, 0.2); color: #dfe8a6; border: 1px solid rgba(223, 232, 166, 0.3);">
                                5-Star Luxury
                            </span>
                            <button type="button" id="preview-btn" class="px-4 py-2 rounded-lg text-xs font-bold tracking-wider uppercase shadow text-white" style="background-color: #343c0a;">
                                Book Suite
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 3. Property Location & Contact Coordinates     -->
        <!-- ============================================== -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">pin_drop</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Property Location & Contact</h2>
                    <p class="text-xs text-stone-500">Official contact numbers, emails, physical address, and Google Maps embed.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Official Email *</label>
                    <input type="email" name="hotel_email" required
                           value="<?= e(get_setting('hotel_email', HOTEL_EMAIL)) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Primary Phone Number *</label>
                    <input type="text" name="hotel_phone" required
                           value="<?= e(get_setting('hotel_phone', HOTEL_PHONE)) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">WhatsApp / Telegram Number</label>
                    <input type="text" name="hotel_whatsapp"
                           value="<?= e(get_setting('hotel_whatsapp', '+85516889066')) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Full Physical Address *</label>
                    <input type="text" name="hotel_address" required
                           value="<?= e(get_setting('hotel_address', '#95, Street 592, Beungkak 2, Tuol Kork, Phnom Penh, Cambodia')) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Standard Check-In Time</label>
                    <input type="text" name="hotel_checkin_time"
                           value="<?= e(get_setting('hotel_checkin_time', '14:00')) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Standard Check-Out Time</label>
                    <input type="text" name="hotel_checkout_time"
                           value="<?= e(get_setting('hotel_checkout_time', '12:00')) ?>" 
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Currency Symbol & Code</label>
                    <div class="flex gap-2">
                        <input type="text" name="hotel_currency_symbol" value="<?= e(get_setting('hotel_currency_symbol', '$')) ?>" class="w-16 text-sm text-center font-bold border border-stone-300 rounded-lg p-2.5">
                        <input type="text" name="hotel_currency_code" value="<?= e(get_setting('hotel_currency_code', 'USD')) ?>" class="flex-1 text-sm uppercase font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Google Maps Embed URL</label>
                    <textarea name="hotel_google_maps_embed" rows="2" 
                              placeholder="https://www.google.com/maps/embed?..."
                              class="w-full text-xs font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('hotel_google_maps_embed')) ?></textarea>
                    <p class="text-[11px] text-stone-400 mt-1">Paste the source URL from Google Maps -> Share -> Embed a map.</p>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 4. Announcement Top Notice Banner              -->
        <!-- ============================================== -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">campaign</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Header Announcement Bar</h2>
                        <p class="text-xs text-stone-500">Display special promotions, notice messages, or airport transfer privileges.</p>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="hotel_notice_banner_active" value="1" <?= get_setting('hotel_notice_banner_active', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 text-[#343c0a] rounded border-stone-300 focus:ring-[#343c0a]">
                    <span class="text-xs font-bold text-stone-700 uppercase">Enable Notice</span>
                </label>
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Notice Text</label>
                <input type="text" name="hotel_notice_banner" 
                       value="<?= e(get_setting('hotel_notice_banner', '✨ Direct Booking Privilege: Complimentary Airport Pick-up on stays of 3+ nights.')) ?>"
                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 5. Social Media Profiles & OTAs                -->
        <!-- ============================================== -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">share</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Social Profiles & OTAs</h2>
                    <p class="text-xs text-stone-500">Connect your hotel social handles and TripAdvisor / Booking profiles.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Facebook URL</label>
                    <input type="url" name="social_facebook" value="<?= e(get_setting('social_facebook', '')) ?>" placeholder="https://facebook.com/yourhotel" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Instagram URL</label>
                    <input type="url" name="social_instagram" value="<?= e(get_setting('social_instagram', '')) ?>" placeholder="https://instagram.com/yourhotel" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">TripAdvisor URL</label>
                    <input type="url" name="social_tripadvisor" value="<?= e(get_setting('social_tripadvisor')) ?>" placeholder="https://tripadvisor.com/..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Telegram URL / Channel</label>
                    <input type="url" name="social_telegram" value="<?= e(get_setting('social_telegram', '')) ?>" placeholder="https://t.me/yourhotel" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Booking.com Profile</label>
                    <input type="url" name="social_booking_com" value="<?= e(get_setting('social_booking_com')) ?>" placeholder="https://booking.com/hotel/..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Agoda Profile</label>
                    <input type="url" name="social_agoda" value="<?= e(get_setting('social_agoda')) ?>" placeholder="https://agoda.com/..." class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>
        </div>

        <!-- Sticky Save Floating Bar -->
        <div class="sticky bottom-4 z-40 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-stone-300 shadow-xl flex items-center justify-between">
            <div class="text-xs text-stone-500 hidden sm:block">
                All changes immediately update the live public website and metadata.
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <a href="<?= BASE_URL ?>/admin/index.php" class="px-5 py-2.5 rounded-lg border border-stone-300 hover:bg-stone-100 text-xs font-semibold text-stone-700 transition">
                    Cancel
                </a>
                <button type="submit" class="bg-[#343c0a] hover:bg-[#4B5320] text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wider uppercase transition shadow flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span>Save Brand & Property</span>
                </button>
            </div>
        </div>

    </form>
</div>

<script>
function syncColorInput(type, value) {
    document.getElementById('color_' + type + '_text').value = value;
    updateBrandLivePreview();
}

function syncColorPicker(type, value) {
    if (/^#[0-9A-F]{6}$/i.test(value)) {
        document.getElementById('color_' + type + '_picker').value = value;
        updateBrandLivePreview();
    }
}

function applyColorPreset(accent, secondary, highlight, dark) {
    document.getElementById('color_accent_picker').value = accent;
    document.getElementById('color_accent_text').value = accent;
    document.getElementById('color_secondary_picker').value = secondary;
    document.getElementById('color_secondary_text').value = secondary;
    document.getElementById('color_highlight_picker').value = highlight;
    document.getElementById('color_highlight_text').value = highlight;
    document.getElementById('color_dark_picker').value = dark;
    document.getElementById('color_dark_text').value = dark;
    updateBrandLivePreview();
}

function updateBrandLivePreview() {
    const hotelName = document.getElementById('prop_hotel_name')?.value || 'Indra Hotel';
    const hotelTagline = document.getElementById('prop_hotel_tagline')?.value || 'Contemporary Boutique Sanctuary';
    const logoInput = document.querySelector('input[name="hotel_logo_url"]');
    const logoUrl = logoInput ? logoInput.value : '';

    const accent = document.getElementById('color_accent_text')?.value || '#343c0a';
    const secondary = document.getElementById('color_secondary_text')?.value || '#4B5320';
    const highlight = document.getElementById('color_highlight_text')?.value || '#dfe8a6';
    const dark = document.getElementById('color_dark_text')?.value || '#191c1d';

    document.getElementById('preview-brand-title').textContent = hotelName;
    document.getElementById('preview-brand-slogan').textContent = hotelTagline;

    const box = document.getElementById('brand-live-preview-box');
    box.style.backgroundColor = dark;

    const logoBox = document.getElementById('preview-logo-box');
    if (logoUrl) {
        logoBox.innerHTML = `<img src="${logoUrl}" alt="Logo" class="w-full h-full object-contain">`;
        logoBox.style.backgroundColor = 'transparent';
    } else {
        const initials = hotelName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() || 'IH';
        logoBox.innerHTML = initials;
        logoBox.style.backgroundColor = highlight;
        logoBox.style.color = accent;
    }

    const badge = document.getElementById('preview-badge');
    badge.style.color = highlight;
    badge.style.borderColor = highlight;

    const btn = document.getElementById('preview-btn');
    btn.style.backgroundColor = accent;
}

// Initial trigger
document.addEventListener('DOMContentLoaded', updateBrandLivePreview);
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
