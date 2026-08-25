<?php
/**
 * Indra Hotel - Admin Shared Header with Multi-Language Switcher
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

Auth::requireAuth();
$currentUser = Auth::user();
$user = $currentUser;
$pdo = getDB();

// Fetch live pending counter
$pendingBookingsCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$unreadMessagesCount = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status = 'unread'")->fetchColumn();
$flash = get_flash();
?>
<!DOCTYPE html>
<html class="light" lang="<?= e(I18n::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title><?= e($adminTitle ?? 'Dashboard') ?> - SoftBook | <?= e(hotel_name()) ?></title>
    
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/softbook_favicon.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/softbook_logo.png">
    
    <!-- Multi-Language Dynamic Google Fonts Loader -->
    <?= render_google_font_head() ?>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#343c0a",
                        "primary-container": "#4b5320",
                        "deep-olive": "#4B5320",
                        "onyx-charcoal": "#262626",
                        "surface": "#f9f9f9",
                        "surface-bright": "#f9f9f9",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f3f3f4",
                        "surface-container": "#eeeeee",
                        "surface-container-high": "#e8e8e8",
                        "surface-container-highest": "#e2e2e2",
                        "on-surface": "#1a1c1c",
                        "on-surface-variant": "#47483c",
                        "secondary": "#5f5e5e",
                        "outline": "#77786b",
                        "pure-black": "#000000"
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    fontFamily: {
                        "headline": ["DM Sans", "Noto Sans Khmer", "Noto Sans SC", "Noto Sans KR", "sans-serif"],
                        "body": ["Be Vietnam Pro", "Noto Sans Khmer", "Noto Sans SC", "Noto Sans KR", "sans-serif"]
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>
        window.BASE_URL = <?= json_encode(BASE_URL) ?>;
        if (window.location.protocol === 'https:' && window.BASE_URL.startsWith('http:')) {
            window.BASE_URL = window.BASE_URL.replace(/^http:/, 'https:');
        }
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/uploader.js?v=<?= filemtime(ROOT_PATH . '/assets/js/uploader.js') ?>"></script>
    <style>
        .flat-card {
            background-color: #ffffff;
            border: 1px solid rgba(38, 38, 38, 0.1);
            transition: all 0.2s ease-in-out;
        }
        .flat-card:hover {
            border-color: rgba(38, 38, 38, 0.2);
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-[#f9f9f9] text-[#1a1c1c] antialiased selection:bg-deep-olive selection:text-white flex">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 md:ml-64 min-h-screen flex flex-col">
        
        <!-- Admin Topbar -->
        <header class="bg-white border-b border-stone-200 sticky top-0 z-30 px-4 sm:px-6 py-3.5 sm:py-4 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <button type="button" 
                        onclick="toggleAdminMobileSidebar()" 
                        class="md:hidden p-2 rounded-xl text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition cursor-pointer" 
                        title="Toggle Navigation Menu">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
                <h1 class="font-headline font-bold text-lg sm:text-xl text-onyx-charcoal truncate"><?= e($adminTitle ?? __t('admin_nav_dashboard', 'Dashboard Overview')) ?></h1>
            </div>

            <div class="flex items-center space-x-3 sm:space-x-4">
                
                <!-- Admin Language Switcher -->
                <?= I18n::renderSwitcher('', 'light', 'bottom') ?>

                <a href="<?= BASE_URL ?>/admin/booking-create.php" class="hidden sm:inline-flex items-center gap-1.5 bg-[#343c0a] hover:bg-deep-olive text-white px-3.5 py-2 rounded text-xs font-semibold tracking-wide transition shadow-xs">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span><?= __t('admin_new_booking_btn', 'New Booking') ?></span>
                </a>

                <!-- Booking Ledger Notification Badge -->
                <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="relative p-2 rounded-xl text-stone-500 hover:text-stone-900 hover:bg-stone-100 transition" title="<?= e(__t('admin_kpi_pending_bookings', 'Pending Bookings')) ?>">
                    <span class="material-symbols-outlined text-xl">receipt_long</span>
                    <?php if ($pendingBookingsCount > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-amber-500 text-white font-bold text-[10px] min-w-4 h-4 px-1 rounded-full flex items-center justify-center animate-pulse shadow-xs"><?= $pendingBookingsCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?= BASE_URL ?>/index.php" target="_blank" class="p-2 rounded text-stone-500 hover:text-stone-900 hover:bg-stone-100 transition" title="<?= e(__t('admin_nav_view_public', 'View Public Website')) ?>">
                    <span class="material-symbols-outlined text-xl">visibility</span>
                </a>

                <!-- User Profile Header Dropdown -->
                <div class="relative border-l border-stone-200 pl-3 sm:pl-4" id="header-user-profile-menu">
                    <button type="button" 
                            onclick="toggleHeaderProfileDropdown()" 
                            class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-stone-100 transition text-left cursor-pointer group focus:outline-none focus:ring-2 focus:ring-[#343c0a]/20">
                        <div class="relative shrink-0">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['name']) ?>" class="w-8 h-8 rounded-full object-cover border border-stone-300">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-[#343c0a] text-[#dfe8a6] flex items-center justify-center font-bold text-xs shadow-2xs">
                                    <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                        </div>

                        <div class="hidden md:block text-left">
                            <div class="text-xs font-bold text-stone-900 leading-none group-hover:text-[#343c0a] transition"><?= e($user['name']) ?></div>
                            <div class="text-[10px] text-stone-400 font-medium capitalize mt-0.5 flex items-center gap-1">
                                <?php if (($user['role'] ?? '') === 'admin'): ?>
                                    <span class="text-[9px] font-bold text-[#343c0a] bg-[#dfe8a6]/50 px-1 rounded"><?= __t('admin_role_administrator', 'Admin') ?></span>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold text-amber-800 bg-amber-100 px-1 rounded"><?= __t('admin_role_editor', 'Editor') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <span class="material-symbols-outlined text-stone-400 text-sm group-hover:text-stone-700 transition">expand_more</span>
                    </button>

                    <!-- Dropdown Card -->
                    <div id="header-profile-dropdown" 
                         class="hidden absolute right-0 mt-2 w-64 bg-white rounded-2xl border border-stone-200 shadow-xl py-2 z-50 animate-fadeIn">
                        <div class="px-4 py-3 border-b border-stone-100 bg-stone-50/50 rounded-t-2xl">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['name']) ?>" class="w-10 h-10 rounded-full object-cover border border-stone-300">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-[#343c0a] text-[#dfe8a6] flex items-center justify-center font-bold text-sm">
                                        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="overflow-hidden">
                                    <div class="text-xs font-bold text-onyx-charcoal truncate"><?= e($user['name']) ?></div>
                                    <div class="text-[11px] text-stone-500 truncate"><?= e($user['email']) ?></div>
                                    <div class="mt-1">
                                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span><?= __t('admin_role_administrator', 'Administrator') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                <span><?= __t('admin_role_editor', 'Content Editor') ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="py-1 text-xs">
                            <?php if (Auth::isAdmin()): ?>
                            <a href="<?= BASE_URL ?>/admin/users.php" class="flex items-center gap-2.5 px-4 py-2 text-stone-700 hover:bg-stone-50 hover:text-[#343c0a] font-medium transition">
                                <span class="material-symbols-outlined text-base text-stone-400">manage_accounts</span>
                                <span><?= __t('admin_nav_users', 'User Management') ?></span>
                            </a>
                            <a href="<?= BASE_URL ?>/admin/property.php" class="flex items-center gap-2.5 px-4 py-2 text-stone-700 hover:bg-stone-50 hover:text-[#343c0a] font-medium transition">
                                <span class="material-symbols-outlined text-base text-stone-400">palette</span>
                                <span><?= __t('admin_nav_property', 'Property & Brand') ?></span>
                            </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-stone-700 hover:bg-stone-50 hover:text-[#343c0a] font-medium transition">
                                <span class="material-symbols-outlined text-base text-stone-400">open_in_new</span>
                                <span><?= __t('admin_nav_view_public', 'View Public Website') ?></span>
                            </a>
                        </div>

                        <div class="pt-1 border-t border-stone-100">
                            <a href="<?= BASE_URL ?>/admin/logout.php" class="flex items-center gap-2.5 px-4 py-2 text-rose-600 hover:bg-rose-50 font-semibold transition text-xs">
                                <span class="material-symbols-outlined text-base text-rose-500">logout</span>
                                <span><?= __t('admin_nav_signout', 'Sign Out') ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <script>
        function toggleHeaderProfileDropdown() {
            const dropdown = document.getElementById('header-profile-dropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }

        function toggleAdminMobileSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');
            if (sidebar && backdrop) {
                sidebar.classList.toggle('-translate-x-full');
                backdrop.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            const menu = document.getElementById('header-user-profile-menu');
            const dropdown = document.getElementById('header-profile-dropdown');
            if (menu && dropdown && !menu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
        </script>

        <!-- Flash Toast Notification in Admin -->
        <?php if ($flash): ?>
        <div class="px-6 pt-4">
            <div class="p-4 rounded-lg flex items-center justify-between border <?= $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : ($flash['type'] === 'error' ? 'bg-rose-50 border-rose-300 text-rose-800' : 'bg-stone-100 border-stone-300 text-stone-800') ?>">
                <div class="flex items-center gap-2 text-xs font-medium">
                    <span class="material-symbols-outlined text-base"><?= $flash['type'] === 'success' ? 'check_circle' : 'info' ?></span>
                    <span><?= e($flash['message']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-stone-400 hover:text-stone-700">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Admin View Body -->
        <main class="p-6 sm:p-8 flex-1">
