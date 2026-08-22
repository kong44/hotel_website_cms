<?php
/**
 * Indra Hotel - Shared Public Header with Dynamic Multi-Language (i18n)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/i18n.php';

$pageTitle = $pageTitle ?? get_setting('site_title', 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh');
$metaDescription = $metaDescription ?? get_setting('site_meta_description', 'Just minutes from bustling Phnom Penh, Indra Hotel offers 12 contemporary luxury suites with private balconies, saltwater pool, fitness center, and fine dining.');
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$flash = get_flash();
$noticeBanner = get_setting('hotel_notice_banner', '✨ Direct Booking Privilege: Complimentary Airport Pick-up on stays of 3+ nights.');
$currentLocale = I18n::getLocale();
?>
<!DOCTYPE html>
<html class="light" lang="<?= e($currentLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    
    <!-- SEO & Social Meta Generator -->
    <?= SEO::renderTags([
        'title' => $pageTitle,
        'description' => $metaDescription,
        'canonical' => $canonicalUrl ?? null,
        'image' => $ogImage ?? null,
        'type' => $ogType ?? 'website'
    ]) ?>

    <?php if ($favIcon = hotel_favicon_url()): ?>
    <link rel="icon" href="<?= e($favIcon) ?>">
    <?php endif; ?>

    <!-- Multi-Language Typography Fonts (Google Sans / DM Sans + Khmer + Chinese + Korean) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=Noto+Sans+Khmer:wght@400;500;600;700&family=Noto+Sans+SC:wght@400;500;700&family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Dynamic Brand Identity Custom Properties -->
    <style>
        :root {
            --color-brand-accent: <?= e(hotel_brand_color('accent')) ?>;
            --color-brand-secondary: <?= e(hotel_brand_color('secondary')) ?>;
            --color-brand-highlight: <?= e(hotel_brand_color('highlight')) ?>;
            --color-brand-dark: <?= e(hotel_brand_color('dark')) ?>;
        }
    </style>

    <!-- Tailwind CSS with Dynamic Brand Palette -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "<?= e(hotel_brand_color('accent')) ?>",
                        "primary-container": "<?= e(hotel_brand_color('secondary')) ?>",
                        "deep-olive": "<?= e(hotel_brand_color('secondary')) ?>",
                        "onyx-charcoal": "<?= e(hotel_brand_color('dark')) ?>",
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
                        "secondary-container": "#e4e2e1",
                        "outline": "#77786b",
                        "outline-variant": "#c8c7b8",
                        "tertiary": "#4a2d53",
                        "tertiary-container": "#62446b",
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
    </script>
</head>
<body class="bg-[#f9f9f9] text-[#1a1c1c] antialiased selection:bg-deep-olive selection:text-white flex flex-col min-h-screen">

    <!-- Top Announcement Bar -->
    <?php 
    $bannerActive = (get_setting('hotel_notice_banner_active', '1') === '1');
    $noticeBannerText = get_setting('hotel_notice_banner', $noticeBanner ?? '');
    if ($bannerActive && !empty($noticeBannerText)): 
    ?>
    <div class="bg-onyx-charcoal text-white text-xs py-2 px-4 text-center tracking-wide flex items-center justify-center gap-2" style="background-color: <?= e(hotel_brand_color('dark')) ?>;">
        <span class="material-symbols-outlined text-sm" style="color: <?= e(hotel_brand_color('highlight')) ?>;">stars</span>
        <span><?= e($noticeBannerText) ?></span>
        <a href="<?= url('/offers') ?>" class="underline ml-1 font-medium hover:opacity-80"><?= __t('nav_offers', 'Special Offers') ?></a>
    </div>
    <?php endif; ?>

    <!-- Flash Message Toast -->
    <?php if ($flash): ?>
    <div id="flash-toast" class="fixed top-6 right-6 z-50 transition-all duration-300 transform max-w-md">
        <div class="flex items-center gap-3 p-4 rounded shadow-xl border <?= $flash['type'] === 'success' ? 'bg-emerald-900 text-white border-emerald-700' : ($flash['type'] === 'error' ? 'bg-rose-900 text-white border-rose-700' : 'bg-onyx-charcoal text-white border-stone-700') ?>">
            <span class="material-symbols-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : ($flash['type'] === 'error' ? 'error' : 'info') ?></span>
            <div class="text-sm font-medium"><?= e($flash['message']) ?></div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-white/70 hover:text-white">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200 shadow-sm header-transition">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Brand Logo & Name -->
                <a href="<?= url('/home') ?>" class="flex items-center gap-3 group">
                    <?php if ($logoImg = hotel_logo_url()): ?>
                        <img src="<?= e($logoImg) ?>" alt="<?= e(hotel_name()) ?> Logo" class="h-10 w-auto max-w-[160px] object-contain">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded text-white flex items-center justify-center font-headline font-bold text-lg tracking-wider transition" style="background-color: <?= e(hotel_brand_color('accent')) ?>;">
                            <?= strtoupper(substr(hotel_name(), 0, 2)) ?>
                        </div>
                        <div>
                            <span class="font-headline font-bold text-xl tracking-tight text-onyx-charcoal block leading-none"><?= strtoupper(e(hotel_name())) ?></span>
                            <span class="text-[10px] tracking-[0.2em] uppercase text-stone-500 font-medium"><?= e(hotel_tagline()) ?></span>
                        </div>
                    <?php endif; ?>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center space-x-6 text-sm font-medium">
                    <a href="<?= url('/rooms') ?>" class="py-2 transition <?= is_active_nav(['rooms', 'room']) ?>"><?= __t('nav_rooms', 'Rooms & Suites') ?></a>
                    <a href="<?= url('/about') ?>" class="py-2 transition <?= is_active_nav('about') ?>">About Us</a>
                    <a href="<?= url('/eat-drink') ?>" class="py-2 transition <?= is_active_nav('eat-drink') ?>"><?= __t('nav_dining', 'Eat & Drink') ?></a>
                    <a href="<?= url('/wellness') ?>" class="py-2 transition <?= is_active_nav('wellness') ?>"><?= __t('nav_wellness', 'Gym & Wellness') ?></a>
                    <a href="<?= url('/offers') ?>" class="py-2 transition <?= is_active_nav('offers') ?>"><?= __t('nav_offers', 'Special Offers') ?></a>
                    <a href="<?= url('/location') ?>" class="py-2 transition <?= is_active_nav('location') ?>"><?= __t('nav_location', 'Location') ?></a>
                    <a href="<?= url('/gallery') ?>" class="py-2 transition <?= is_active_nav('gallery') ?>"><?= __t('nav_gallery', 'Gallery') ?></a>
                    <a href="<?= url('/contact') ?>" class="py-2 transition <?= is_active_nav('contact') ?>"><?= __t('nav_contact', 'Contact') ?></a>
                </nav>

                <!-- Action CTA & Guest Navigation -->
                <div class="flex items-center gap-2 sm:gap-3">

                    <?php if (is_guest_logged_in()): 
                        $curGuest = get_logged_in_guest();
                    ?>
                    <a href="<?= url('/my-booking') ?>" class="hidden xl:flex items-center gap-2 text-xs font-semibold text-stone-800 bg-stone-100 hover:bg-stone-200 px-3 py-1.5 rounded-full transition border border-stone-200" title="Signed in as <?= e($curGuest['name']) ?>">
                        <?php if (!empty($curGuest['picture'])): ?>
                            <img src="<?= e($curGuest['picture']) ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-sm text-[#4B5320]">account_circle</span>
                        <?php endif; ?>
                        <span>My Bookings</span>
                    </a>
                    <?php else: ?>
                    <a href="<?= url('/my-booking') ?>" class="hidden xl:flex items-center gap-1 text-xs font-medium text-stone-600 hover:text-stone-900 px-2.5 py-2 rounded hover:bg-stone-100 transition">
                        <span class="material-symbols-outlined text-base">receipt_long</span>
                        <span><?= __t('nav_find_booking', 'Find Booking') ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= e(get_booking_url()) ?>" target="<?= e(get_booking_target()) ?>" class="bg-[#343c0a] hover:bg-deep-olive text-white px-4 sm:px-5 py-2 rounded text-xs sm:text-sm font-semibold tracking-wide transition shadow-sm hover:shadow flex items-center gap-1.5 sm:gap-2 btn-shimmer">
                        <span class="material-symbols-outlined text-base">calendar_month</span>
                        <span><?= e(get_booking_button_text(__t('nav_book_now', 'Book Now'))) ?></span>
                    </a>

                    <!-- Mobile Hamburger Button -->
                    <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 rounded text-stone-700 hover:text-stone-900 hover:bg-stone-100" aria-label="Open Mobile Menu">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Overlay -->
    <div id="drawer-overlay" class="fixed inset-0 bg-black/50 z-50 hidden transition-opacity"></div>

    <!-- Mobile Navigation Drawer -->
    <div id="mobile-drawer" class="fixed top-0 right-0 w-80 max-w-[85vw] h-full bg-white z-50 transform translate-x-full transition-transform duration-300 ease-in-out shadow-2xl flex flex-col">
        <div class="p-5 border-b border-stone-100 flex items-center justify-between">
            <a href="<?= url('/home') ?>" class="flex items-center gap-2.5">
                <?php if (!empty($logoImg)): ?>
                    <img src="<?= e($logoImg) ?>" alt="<?= e(hotel_name()) ?> Logo" class="h-9 w-auto max-w-[140px] object-contain">
                <?php else: ?>
                    <div class="w-9 h-9 rounded text-white flex items-center justify-center font-headline font-bold text-base tracking-wider transition shrink-0" style="background-color: <?= e(hotel_brand_color('accent')) ?>;">
                        <?= strtoupper(substr(hotel_name(), 0, 2)) ?>
                    </div>
                    <div>
                        <span class="font-headline font-bold text-base tracking-tight text-onyx-charcoal block leading-none"><?= strtoupper(e(hotel_name())) ?></span>
                        <span class="text-[9px] tracking-[0.18em] uppercase text-stone-500 font-medium"><?= e(hotel_tagline()) ?></span>
                    </div>
                <?php endif; ?>
            </a>
            <button id="close-drawer-btn" class="p-1.5 rounded-lg text-stone-500 hover:text-stone-900 hover:bg-stone-100 transition cursor-pointer" title="Close Menu">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <nav class="p-6 flex-1 overflow-y-auto space-y-3 text-base font-medium">
            <div class="pb-3 mb-2 border-b border-stone-100">
                <span class="text-xs text-stone-400 uppercase font-bold block mb-2">Language / ភាសា</span>
                <?= I18n::renderSwitcher('w-full', 'light', 'bottom') ?>
            </div>
            <a href="<?= url('/rooms') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_rooms', 'Rooms & Suites') ?></a>
            <a href="<?= url('/about') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50">About Us</a>
            <a href="<?= url('/eat-drink') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_dining', 'Eat & Drink') ?></a>
            <a href="<?= url('/wellness') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_wellness', 'Gym & Wellness') ?></a>
            <a href="<?= url('/offers') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_offers', 'Special Offers') ?></a>
            <a href="<?= url('/location') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_location', 'Location') ?></a>
            <a href="<?= url('/gallery') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_gallery', 'Gallery') ?></a>
            <a href="<?= url('/contact') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_contact', 'Contact') ?></a>
            <a href="<?= url('/my-booking') ?>" class="block py-2 text-stone-800 hover:text-[#343c0a] border-b border-stone-50"><?= __t('nav_find_booking', 'Find Booking') ?></a>
        </nav>

        <div class="p-6 bg-stone-50 border-t border-stone-100 text-sm text-stone-600 space-y-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#4B5320] text-sm">phone</span>
                <a href="tel:<?= HOTEL_PHONE_RAW ?>" class="hover:underline"><?= HOTEL_PHONE ?></a>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#4B5320] text-sm">mail</span>
                <a href="mailto:<?= HOTEL_EMAIL ?>" class="hover:underline"><?= HOTEL_EMAIL ?></a>
            </div>
            <a href="<?= e(get_booking_url()) ?>" target="<?= e(get_booking_target()) ?>" class="block text-center w-full bg-[#343c0a] text-white py-3 rounded font-medium mt-2">
                <?= e(get_booking_button_text('Instant Reservation')) ?>
            </a>
        </div>
    </div>

    <!-- Main Page Content Starts -->
    <main class="flex-grow">
