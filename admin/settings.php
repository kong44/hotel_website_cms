<?php
/**
 * Indra Hotel - Site & SEO Settings CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = getDB();
$adminTitle = 'Site & SEO Settings';

// Handle Settings Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_url' => trim($_POST['site_url'] ?? ''),
        'site_meta_description' => trim($_POST['site_meta_description'] ?? ''),
        'site_meta_keywords' => trim($_POST['site_meta_keywords'] ?? ''),
        'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
        'google_site_verification' => trim($_POST['google_site_verification'] ?? ''),
        'hotel_notice_banner' => trim($_POST['hotel_notice_banner'] ?? ''),
        'booking_mode' => in_array($_POST['booking_mode'] ?? '', ['internal', 'engine'], true) ? $_POST['booking_mode'] : 'internal',
        'booking_engine_url' => trim($_POST['booking_engine_url'] ?? ''),
        'booking_engine_target' => in_array($_POST['booking_engine_target'] ?? '', ['_blank', '_self'], true) ? $_POST['booking_engine_target'] : '_blank',
        'booking_button_text' => trim($_POST['booking_button_text'] ?? 'Book Now')
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

        <!-- Hotel Reservation & Booking Engine Mode Selector -->
        <div class="pt-6 border-t border-stone-100 space-y-5">
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#4B5320]">calendar_month</span>
                    <span>Hotel Booking System Modes (Two Options)</span>
                </h3>
                <p class="text-xs text-stone-500 mt-0.5">Select how guests make room reservations on the hotel website.</p>
            </div>

            <!-- Two Mode Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Option 1: External Booking Engine URL Link -->
                <label class="relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none <?= (get_booking_mode() === 'engine') ? 'border-[#343c0a] bg-stone-50/70 shadow-xs' : 'border-stone-200 hover:border-stone-300 bg-white' ?>" id="mode-card-engine">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-xl">open_in_new</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-800 block">Option 1 (External)</span>
                                <h4 class="font-headline font-bold text-sm text-onyx-charcoal">External Booking Engine URL</h4>
                            </div>
                        </div>
                        <input type="radio" name="booking_mode" value="engine" <?= (get_booking_mode() === 'engine') ? 'checked' : '' ?> onchange="toggleBookingModeUI('engine')" class="w-4 h-4 text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500 mt-3 leading-relaxed">
                        Guests clicking "Book Now" or "Reserve" are directly redirected to your 3rd-party hotel reservation system (e.g. Cloudbeds, Booking.com, Agoda, Sirvoy, Guesty, etc.).
                    </p>
                    <div class="mt-3 pt-3 border-t border-stone-200/60 flex items-center gap-1.5 text-[11px] text-blue-800 font-semibold">
                        <span class="material-symbols-outlined text-sm">link</span>
                        <span>Redirects to your custom Booking Engine Link</span>
                    </div>
                </label>

                <!-- Option 2: Direct Built-in Booking (On-site & Email Dispatch) -->
                <label class="relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none <?= (get_booking_mode() === 'internal') ? 'border-[#343c0a] bg-stone-50/70 shadow-xs' : 'border-stone-200 hover:border-stone-300 bg-white' ?>" id="mode-card-internal">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-xl">mark_email_read</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Option 2 (Built-in)</span>
                                <h4 class="font-headline font-bold text-sm text-onyx-charcoal">Direct On-Site Reservation</h4>
                            </div>
                        </div>
                        <input type="radio" name="booking_mode" value="internal" <?= (get_booking_mode() === 'internal') ? 'checked' : '' ?> onchange="toggleBookingModeUI('internal')" class="w-4 h-4 text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500 mt-3 leading-relaxed">
                        Guests fill out the reservation form directly on your website. Bookings are saved in the CMS, and automated confirmation emails are sent to the guest & hotel staff.
                    </p>
                    <div class="mt-3 pt-3 border-t border-stone-200/60 flex items-center gap-1.5 text-[11px] text-[#4B5320] font-semibold">
                        <span class="material-symbols-outlined text-sm">forward_to_inbox</span>
                        <span>Sends emails to Guest & Hotel Multi-Department Inboxes</span>
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
        const engineBox = document.getElementById('engine-config-box');

        if (mode === 'internal') {
            internalCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-[#343c0a] bg-stone-50/70 shadow-xs';
            engineCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-stone-200 hover:border-stone-300 bg-white';
            if (engineBox) engineBox.classList.add('hidden');
        } else {
            engineCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-[#343c0a] bg-stone-50/70 shadow-xs';
            internalCard.className = 'relative flex flex-col p-5 rounded-xl border-2 cursor-pointer transition select-none border-stone-200 hover:border-stone-300 bg-white';
            if (engineBox) engineBox.classList.remove('hidden');
        }
    }
    </script>

    <!-- Google Sign-In for Public Guest Portal Quick Link Card -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 p-2.5 flex items-center justify-center shrink-0 border border-blue-100">
                <svg class="w-full h-full" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span>Google Sign-In for Public Guest Portal</span>
                    <?php if (get_setting('google_oauth_enabled', '0') === '1' && !empty(get_setting('google_oauth_client_id'))): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Active</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-stone-100 text-stone-600">Configurable</span>
                    <?php endif; ?>
                </h3>
                <p class="text-xs text-stone-500">Configure Client ID and Secret to let public guests sign in and view their full booking history.</p>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/admin/users.php#google-oauth" class="inline-flex items-center gap-2 bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold transition shrink-0 shadow-sm">
            <span>Configure Guest Google Auth</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>

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
