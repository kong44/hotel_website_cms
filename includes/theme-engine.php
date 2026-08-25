<?php
/**
 * Indra Hotel - Modular Theme Engine & Dynamic Section Block Renderer
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Get all themes installed in themes/ directory
 */
function get_available_themes(): array {
    $themesDir = ROOT_PATH . '/themes';
    $themes = [];

    if (!is_dir($themesDir)) {
        return $themes;
    }

    $dirs = glob($themesDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $slug = basename($dir);
        $manifestPath = $dir . '/theme.json';
        if (file_exists($manifestPath)) {
            $json = file_get_contents($manifestPath);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $data['slug'] = $slug;
                $data['is_free'] = true;
                $data['is_purchased'] = true;
                $themes[$slug] = $data;
            }
        }
    }

    return $themes;
}

/**
 * Get single theme definition
 */
function get_theme_by_slug(string $slug): ?array {
    $all = get_available_themes();
    return $all[$slug] ?? $all['default'] ?? null;
}

/**
 * Resolve active theme for dynamic page or global fallback
 */
function get_active_theme_for_page(?string $pageThemeId = null): array {
    $all = get_available_themes();

    if (!empty($pageThemeId) && isset($all[$pageThemeId])) {
        return $all[$pageThemeId];
    }

    // Check site-wide active theme from site_settings
    $siteActiveTheme = get_setting('site_active_theme', 'default');
    if (isset($all[$siteActiveTheme])) {
        return $all[$siteActiveTheme];
    }


    return $all['default'] ?? [
        'slug' => 'default',
        'name' => 'Indra Heritage Gold',
        'colors' => [
            'primary' => '#343c0a',
            'primary_hover' => '#262b07',
            'accent' => '#dfe8a6',
            'dark' => '#191c1d',
            'bg' => '#f9f9f9',
            'card_bg' => '#ffffff'
        ],
        'fonts' => [
            'headline' => 'Cinzel, serif',
            'body' => 'Plus Jakarta Sans, sans-serif'
        ]
    ];
}

/**
 * Render Theme CSS Variables & Inline Font Imports
 */
function render_theme_css_styles(array $theme): string {
    $colors = $theme['colors'] ?? [];
    $fonts = $theme['fonts'] ?? [];

    $pColor = $colors['primary'] ?? '#343c0a';
    $pHover = $colors['primary_hover'] ?? '#262b07';
    $aColor = $colors['accent'] ?? '#dfe8a6';
    $dColor = $colors['dark'] ?? '#191c1d';
    $bgColor = $colors['bg'] ?? '#f9f9f9';

    ob_start();
    ?>
    <style id="theme-dynamic-styles">
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,500;0,700;1,400&family=Montserrat:wght@600;700;800&family=Open+Sans:wght@400;600&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap');

        :root {
            --theme-primary: <?= e($pColor) ?>;
            --theme-primary-hover: <?= e($pHover) ?>;
            --theme-accent: <?= e($aColor) ?>;
            --theme-dark: <?= e($dColor) ?>;
            --theme-bg: <?= e($bgColor) ?>;
            --theme-font-headline: <?= e($fonts['headline'] ?? 'Cinzel, serif') ?>;
            --theme-font-body: <?= e($fonts['body'] ?? 'Plus Jakarta Sans, sans-serif') ?>;
        }


        .theme-primary-bg { background-color: var(--theme-primary) !important; }
        .theme-primary-text { color: var(--theme-primary) !important; }
        .theme-primary-border { border-color: var(--theme-primary) !important; }
        .theme-accent-bg { background-color: var(--theme-accent) !important; }
        .theme-accent-text { color: var(--theme-accent) !important; }
        .theme-font-headline { font-family: var(--theme-font-headline) !important; }
        .theme-btn-primary {
            background-color: var(--theme-primary);
            color: #ffffff;
            transition: all 0.3s ease;
        }
        .theme-btn-primary:hover {
            background-color: var(--theme-primary-hover);
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Render Dynamic Section Block
 */
function render_dynamic_section_block(array $block, array $theme = []): string {
    $type = $block['type'] ?? 'rich_text';
    $title = $block['title'] ?? '';
    $subtitle = $block['subtitle'] ?? '';

    ob_start();
    switch ($type) {

        // ==========================================
        // 1. Rich Text / HTML Section
        // ==========================================
        case 'rich_text':
            $content = $block['content'] ?? '';
            ?>
            <section class="py-16 bg-white border-b border-stone-100">
                <div class="max-w-4xl mx-auto px-4 sm:px-6">
                    <?php if (!empty($title)): ?>
                    <div class="text-center mb-8">
                        <h2 class="theme-font-headline text-3xl sm:text-4xl font-bold text-onyx-charcoal mb-2"><?= e($title) ?></h2>
                        <?php if (!empty($subtitle)): ?>
                            <p class="text-stone-500 text-sm max-w-xl mx-auto"><?= e($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="prose max-w-none text-stone-600 leading-relaxed text-sm sm:text-base space-y-4">
                        <?= $content ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 2. Feature Cards & Highlights Grid
        // ==========================================
        case 'features_grid':
            $items = is_array($block['items'] ?? null) ? $block['items'] : [];
            $cols = (int)($block['columns'] ?? 3);
            $gridClass = $cols == 2 ? 'sm:grid-cols-2' : ($cols == 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3');
            ?>
            <section class="py-16 bg-stone-50 border-b border-stone-200/60">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <?php if (!empty($title)): ?>
                    <div class="text-center max-w-2xl mx-auto mb-12">
                        <span class="text-xs font-bold uppercase tracking-widest theme-primary-text">Highlights</span>
                        <h2 class="theme-font-headline text-3xl font-bold text-onyx-charcoal mt-1"><?= e($title) ?></h2>
                        <?php if (!empty($subtitle)): ?>
                            <p class="text-stone-500 text-sm mt-2"><?= e($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 <?= $gridClass ?> gap-6">
                        <?php foreach ($items as $item): ?>
                        <div class="bg-white rounded-2xl p-6 border border-stone-200 shadow-2xs hover:shadow-md transition space-y-3 group">
                            <?php if (!empty($item['icon'])): ?>
                            <div class="w-12 h-12 rounded-xl theme-accent-bg theme-primary-text flex items-center justify-center font-bold text-2xl group-hover:scale-105 transition">
                                <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                            </div>
                            <?php elseif (!empty($item['image'])): ?>
                            <div class="h-40 -mx-6 -mt-6 mb-3 overflow-hidden rounded-t-2xl bg-stone-100">
                                <img src="<?= e($item['image']) ?>" alt="<?= e($item['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition">
                            </div>
                            <?php endif; ?>

                            <h3 class="theme-font-headline font-bold text-lg text-onyx-charcoal"><?= e($item['title'] ?? '') ?></h3>
                            <p class="text-xs text-stone-500 leading-relaxed"><?= e($item['description'] ?? '') ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 3. Photo Gallery & Lightbox Grid
        // ==========================================
        case 'gallery':
            $photos = is_array($block['photos'] ?? null) ? $block['photos'] : [];
            ?>
            <section class="py-16 bg-white border-b border-stone-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <?php if (!empty($title)): ?>
                    <div class="text-center max-w-2xl mx-auto mb-10">
                        <h2 class="theme-font-headline text-3xl font-bold text-onyx-charcoal"><?= e($title) ?></h2>
                        <?php if (!empty($subtitle)): ?>
                            <p class="text-stone-500 text-sm mt-2"><?= e($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($photos as $pIdx => $photoUrl): ?>
                        <div class="relative h-48 sm:h-56 rounded-xl overflow-hidden cursor-pointer group shadow-2xs"
                             onclick="openPhotoPreview(<?= e(json_encode($photos)) ?>, <?= (int)$pIdx ?>, <?= e(json_encode($title ?: 'Photo Gallery')) ?>)">
                            <img src="<?= e($photoUrl) ?>" alt="Gallery Image" class="w-full h-full object-cover group-hover:scale-108 transition duration-500">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                                <span class="material-symbols-outlined text-3xl">fullscreen</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 4. Call-To-Action (CTA) Banner
        // ==========================================
        case 'cta':
            $bgImg = $block['image'] ?? 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1600&q=80';
            $btnText = $block['btn_text'] ?? 'Contact Concierge';
            $btnLink = $block['btn_link'] ?? BASE_URL . '/contact.php';
            ?>
            <section class="py-20 relative overflow-hidden text-white bg-stone-900">
                <img src="<?= e($bgImg) ?>" alt="CTA Background" class="absolute inset-0 w-full h-full object-cover opacity-35">
                <div class="absolute inset-0 bg-gradient-to-r from-stone-950/90 via-stone-950/70 to-transparent"></div>
                <div class="max-w-5xl mx-auto px-4 sm:px-6 relative z-10 text-center space-y-6">
                    <h2 class="theme-font-headline text-3xl sm:text-5xl font-bold text-white"><?= e($title ?: 'Experience Extraordinary Hospitality') ?></h2>
                    <?php if (!empty($subtitle)): ?>
                        <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed"><?= e($subtitle) ?></p>
                    <?php endif; ?>
                    <div class="pt-2">
                        <a href="<?= e($btnLink) ?>" class="inline-flex items-center gap-2 px-8 py-3.5 rounded-lg theme-btn-primary font-bold text-sm tracking-wide shadow-lg hover:scale-105 transition">
                            <span><?= e($btnText) ?></span>
                            <span class="material-symbols-outlined text-base">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 5. Testimonials & Guest Reviews Carousel
        // ==========================================
        case 'testimonials':
            $reviews = is_array($block['reviews'] ?? null) ? $block['reviews'] : [];
            ?>
            <section class="py-16 bg-stone-50 border-b border-stone-200/60">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <?php if (!empty($title)): ?>
                    <div class="text-center max-w-2xl mx-auto mb-12">
                        <span class="text-xs font-bold uppercase tracking-widest theme-primary-text">Guest Voices</span>
                        <h2 class="theme-font-headline text-3xl font-bold text-onyx-charcoal mt-1"><?= e($title) ?></h2>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($reviews as $rev): ?>
                        <div class="bg-white rounded-2xl p-6 border border-stone-200 shadow-2xs space-y-4 flex flex-col justify-between">
                            <div class="space-y-3">
                                <div class="flex items-center text-amber-400 text-sm">
                                    <span class="material-symbols-outlined text-base">star</span>
                                    <span class="material-symbols-outlined text-base">star</span>
                                    <span class="material-symbols-outlined text-base">star</span>
                                    <span class="material-symbols-outlined text-base">star</span>
                                    <span class="material-symbols-outlined text-base">star</span>
                                </div>
                                <p class="text-xs text-stone-600 leading-relaxed italic">"<?= e($rev['quote'] ?? '') ?>"</p>
                            </div>
                            <div class="pt-3 border-t border-stone-100 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full theme-accent-bg theme-primary-text font-bold text-sm flex items-center justify-center">
                                    <?= e(strtoupper(substr($rev['author'] ?? 'G', 0, 1))) ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-xs text-onyx-charcoal"><?= e($rev['author'] ?? 'Guest') ?></h4>
                                    <span class="text-[10px] text-stone-400"><?= e($rev['location'] ?? 'Verified Guest') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 6. FAQ & Accordion List
        // ==========================================
        case 'faq':
            $faqs = is_array($block['faqs'] ?? null) ? $block['faqs'] : [];
            ?>
            <section class="py-16 bg-white border-b border-stone-100">
                <div class="max-w-4xl mx-auto px-4 sm:px-6">
                    <?php if (!empty($title)): ?>
                    <div class="text-center mb-10">
                        <h2 class="theme-font-headline text-3xl font-bold text-onyx-charcoal"><?= e($title) ?></h2>
                        <?php if (!empty($subtitle)): ?>
                            <p class="text-stone-500 text-sm mt-2"><?= e($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php foreach ($faqs as $fIdx => $faq): ?>
                        <details class="bg-stone-50 rounded-xl border border-stone-200 p-4 group [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex items-center justify-between font-bold text-sm text-onyx-charcoal cursor-pointer">
                                <span><?= e($faq['q'] ?? '') ?></span>
                                <span class="material-symbols-outlined text-stone-400 group-open:rotate-180 transition">expand_more</span>
                            </summary>
                            <div class="pt-3 text-xs text-stone-600 leading-relaxed border-t border-stone-200/60 mt-3">
                                <?= e($faq['a'] ?? '') ?>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 7. Experience Feature Banners
        // ==========================================
        case 'experience_banners':
            $banners = is_array($block['banners'] ?? null) ? $block['banners'] : [];
            ?>
            <section class="py-20 bg-[#f3f3f4]">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                    <?php 
                    foreach ($banners as $idx => $b): 
                        $pos = strtolower($b['image_pos'] ?? 'auto');
                        if ($pos === 'auto') {
                            $pos = ($idx % 2 === 1) ? 'left' : 'right';
                        }
                        $isImgLeft = ($pos === 'left');
                        $imgOrderClass = $isImgLeft ? 'order-2 lg:order-1' : 'order-2 lg:order-2';
                        $textOrderClass = $isImgLeft ? 'order-1 lg:order-2' : 'order-1 lg:order-1';
                        $btnBg = ($idx % 2 === 0) ? 'bg-onyx-charcoal hover:bg-black' : 'bg-[#343c0a] hover:bg-deep-olive btn-shimmer';
                        $targetUrl = BASE_URL . '/' . ltrim(e($b['btn_url'] ?? '#'), '/');
                    ?>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-stone-200 grid grid-cols-1 lg:grid-cols-12 items-center">
                        <div class="lg:col-span-6 <?= $textOrderClass ?> p-8 sm:p-12 space-y-4">
                            <?php if (!empty($b['badge'])): ?>
                                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]"><?= e($b['badge']) ?></span>
                            <?php endif; ?>
                            <h2 class="font-headline text-3xl font-bold text-onyx-charcoal"><?= e($b['title'] ?? '') ?></h2>
                            <p class="text-stone-600 leading-relaxed text-sm sm:text-base">
                                <?= e($b['desc'] ?? '') ?>
                            </p>
                            <?php if (!empty($b['btn_text'])): ?>
                            <div class="pt-2">
                                <a href="<?= $targetUrl ?>" class="inline-flex items-center gap-2 <?= $btnBg ?> text-white px-5 py-2.5 rounded text-sm font-semibold transition">
                                    <span><?= e($b['btn_text']) ?></span>
                                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="lg:col-span-6 <?= $imgOrderClass ?> h-80 lg:h-full min-h-[340px] overflow-hidden">
                            <img src="<?= e($b['image_url'] ?? '') ?>" alt="<?= e($b['title'] ?? '') ?>" class="w-full h-full object-cover">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 8. Core Value Pillars
        // ==========================================
        case 'value_pillars':
            $pillars = is_array($block['pillars'] ?? null) ? $block['pillars'] : [];
            $colsClass = (count($pillars) === 2) ? 'md:grid-cols-2' : ((count($pillars) >= 4) ? 'md:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3');
            ?>
            <section class="py-16 bg-white border-b border-stone-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <?php if (!empty($title)): ?>
                    <div class="text-center max-w-3xl mx-auto mb-12">
                        <?php if (!empty($subtitle)): ?>
                            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#4B5320] block mb-2"><?= e($subtitle) ?></span>
                        <?php endif; ?>
                        <h2 class="font-headline text-3xl font-bold text-stone-900"><?= e($title) ?></h2>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 <?= $colsClass ?> gap-8">
                        <?php foreach ($pillars as $p): ?>
                        <div class="p-8 rounded-2xl bg-stone-50 border border-stone-200 space-y-4 transition hover:shadow-md">
                            <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl"><?= e($p['icon'] ?? 'star') ?></span>
                            </div>
                            <h3 class="font-headline font-bold text-lg text-stone-900"><?= e($p['title'] ?? '') ?></h3>
                            <p class="text-stone-600 text-xs sm:text-sm leading-relaxed"><?= e($p['desc'] ?? '') ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        // ==========================================
        // 9. KPI Statistics Counters
        // ==========================================
        case 'kpi_stats':
            $stats = is_array($block['stats'] ?? null) ? $block['stats'] : [];
            ?>
            <section class="py-12 bg-stone-900 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <?php if (!empty($title)): ?>
                    <div class="text-center mb-8">
                        <h2 class="font-headline text-2xl font-bold text-white"><?= e($title) ?></h2>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                        <?php foreach ($stats as $st): ?>
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <div class="font-headline text-3xl font-bold text-[#dfe8a6]"><?= e($st['val'] ?? '') ?></div>
                            <div class="text-xs text-stone-300 font-medium"><?= e($st['label'] ?? '') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php
            break;

        default:
            break;
    }

    return ob_get_clean();
}
