<?php
/**
 * Indra Hotel - Shared Admin Sidebar
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$pdo = getDB();

// Dynamic Counters
$pendingBookingsCount = 0;
$unreadMessagesCount = 0;
try {
    $c1 = $pdo->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")->fetch();
    $pendingBookingsCount = (int)($c1['c'] ?? 0);

    $c2 = $pdo->query("SELECT COUNT(*) as c FROM messages WHERE status = 'unread'")->fetch();
    $unreadMessagesCount = (int)($c2['c'] ?? 0);
} catch (Throwable $e) {}

if (!function_exists('is_admin_active')) {
    function is_admin_active(string|array $files, string $current): string {
        $fileList = is_array($files) ? $files : [$files];
        $isActive = in_array($current, $fileList, true);
        if (!$isActive && class_exists('Router')) {
            $isActive = Router::isActive($fileList);
        }
        return $isActive 
            ? 'bg-[#343c0a] text-[#dfe8a6] font-semibold shadow-sm' 
            : 'text-stone-300 hover:bg-stone-800/80 hover:text-white';
    }
}
?>
<!-- Mobile Drawer Backdrop -->
<div id="admin-sidebar-backdrop" onclick="toggleAdminMobileSidebar()" class="fixed inset-0 bg-black/60 z-40 hidden md:hidden transition-opacity"></div>

<!-- SideNavBar -->
<aside id="admin-sidebar" class="bg-onyx-charcoal h-screen w-64 fixed left-0 top-0 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col py-4 z-50 border-r border-stone-800">
    
    <!-- Brand Header -->
    <div class="px-6 py-4 mb-2 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl overflow-hidden bg-white/95 p-1 border border-[#dfe8a6]/40 flex items-center justify-center shadow-sm shrink-0">
                <img src="<?= BASE_URL ?>/assets/images/softbook_logo.png" alt="SoftBook Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <h1 class="font-headline text-lg font-bold text-white leading-tight tracking-tight">SoftBook</h1>
                    <span class="text-[9px] font-bold bg-[#dfe8a6]/20 text-[#dfe8a6] px-1.5 py-0.5 rounded uppercase tracking-wider">CMS</span>
                </div>
                <span class="text-[10px] tracking-wider uppercase text-stone-400 font-medium"><?= e(hotel_name()) ?></span>
            </div>
        </div>
        <button type="button" onclick="toggleAdminMobileSidebar()" class="md:hidden text-stone-400 hover:text-white p-1 rounded-lg hover:bg-stone-800 transition" title="Close Menu">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    <!-- Navigation List -->
    <nav class="flex-1 px-3 space-y-1.5 overflow-y-auto text-xs font-medium">
        
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-stone-500">Core Operations</div>
        
        <a href="<?= BASE_URL ?>/admin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('index.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">dashboard</span>
            <span>Dashboard Overview</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/accommodations.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active(['accommodations.php', 'room-form.php'], $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">bed</span>
            <span>Accommodations</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/room-types.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('room-types.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">category</span>
            <span>Room Types & Categories</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/amenities.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('amenities.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">checklist</span>
            <span>Room Amenities</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/bookings.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?= is_admin_active(['bookings.php', 'booking-detail.php'], $currentScript) ?>">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lg">receipt_long</span>
                <span>Booking Ledger</span>
            </div>
            <?php if ($pendingBookingsCount > 0): ?>
                <span class="bg-[#dfe8a6] text-[#191e00] font-bold text-[10px] px-1.5 py-0.5 rounded-full"><?= $pendingBookingsCount ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= BASE_URL ?>/admin/booking-create.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('booking-create.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">add_circle</span>
            <span>Add New Booking</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/guests.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('guests.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">groups</span>
            <span>Public Guests</span>
        </a>

        <div class="px-3 pt-4 pb-2 text-[10px] font-bold uppercase tracking-wider text-stone-500">Content Management</div>

        <a href="<?= BASE_URL ?>/admin/homepage.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('homepage.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">home</span>
            <span>Homepage Content</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/dining-wellness.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('dining-wellness.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">restaurant</span>
            <span>Dining & Wellness</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/offers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('offers.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">local_offer</span>
            <span>Special Offers</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/gallery.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('gallery.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">photo_library</span>
            <span>Photo Gallery</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/media.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('media.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">perm_media</span>
            <span>Media Library</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/page-heroes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('page-heroes.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">view_day</span>
            <span>Page Heroes & Banners</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/pages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active(['pages.php', 'page-builder.php'], $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">auto_stories</span>
            <span>Custom Dynamic Pages</span>
        </a>





        <a href="<?= BASE_URL ?>/admin/locations.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('locations.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">pin_drop</span>
            <span>Prime Location</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/messages.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?= is_admin_active('messages.php', $currentScript) ?>">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-lg">mail</span>
                <span>Guest Messages</span>
            </div>
            <?php if ($unreadMessagesCount > 0): ?>
                <span class="bg-rose-500 text-white font-bold text-[10px] px-1.5 py-0.5 rounded-full"><?= $unreadMessagesCount ?></span>
            <?php endif; ?>
        </a>

        <?php if (Auth::isAdmin()): ?>
        <div class="px-3 pt-4 pb-2 text-[10px] font-bold uppercase tracking-wider text-stone-500">Brand & System</div>

        <a href="<?= BASE_URL ?>/admin/users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('users.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">manage_accounts</span>
            <span>User Management</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/property.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('property.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">palette</span>
            <span>Property & Brand</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/email-settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('email-settings.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">forward_to_inbox</span>
            <span>Email & SMTP Settings</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/livechat.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('livechat.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">chat</span>
            <span>Live Chat (Tawk.to)</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= is_admin_active('settings.php', $currentScript) ?>">
            <span class="material-symbols-outlined text-lg">tune</span>
            <span>Site & SEO Settings</span>
        </a>
        <?php endif; ?>

        <div class="pt-2"></div>

        <a href="<?= BASE_URL ?>/index.php" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-stone-400 hover:text-white hover:bg-stone-800">
            <span class="material-symbols-outlined text-lg">open_in_new</span>
            <span>View Public Website</span>
        </a>
    </nav>

    <!-- Bottom User Profile Card -->
    <div class="p-4 border-t border-stone-800 bg-stone-900/60">
        <div class="bg-stone-900 border border-stone-800/80 rounded-xl p-3 shadow-inner space-y-3">
            <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?= e($currentUser['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-stone-700">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-[#4B5320] text-[#dfe8a6] flex items-center justify-center font-bold text-sm shadow-2xs">
                            <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-stone-900"></span>
                </div>

                <div class="overflow-hidden flex-1">
                    <div class="text-xs font-bold text-white truncate flex items-center gap-1">
                        <span><?= e($currentUser['name'] ?? 'Staff') ?></span>
                    </div>
                    <div class="text-[10px] text-stone-400 truncate mt-0.5"><?= e($currentUser['email'] ?? '') ?></div>
                    <div class="mt-1">
                        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                            <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-[#343c0a] text-[#dfe8a6] border border-[#4B5320]/60 px-2 py-0.5 rounded-md">
                                <span class="material-symbols-outlined text-[11px]">shield_person</span>
                                <span>Admin</span>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-amber-950/80 text-amber-300 border border-amber-800/60 px-2 py-0.5 rounded-md">
                                <span class="material-symbols-outlined text-[11px]">edit_note</span>
                                <span>Editor</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Toolbar -->
            <div class="pt-2 border-t border-stone-800 flex items-center justify-between text-xs">
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= BASE_URL ?>/admin/users.php" class="text-stone-400 hover:text-stone-200 text-[11px] font-semibold flex items-center gap-1 transition">
                    <span class="material-symbols-outlined text-sm">settings</span>
                    <span>Account</span>
                </a>
                <?php else: ?>
                <span class="text-[11px] text-stone-500 font-medium">SoftBook Active</span>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/admin/logout.php" title="Sign out of SoftBook" class="text-stone-400 hover:text-rose-400 text-[11px] font-semibold flex items-center gap-1 transition cursor-pointer">
                    <span>Sign Out</span>
                    <span class="material-symbols-outlined text-sm">logout</span>
                </a>
            </div>
        </div>
    </div>

</aside>
