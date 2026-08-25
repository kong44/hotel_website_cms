<?php
/**
 * Indra Hotel - About Us Page Content Management System
 * Comprehensive CMS for Hero Banner, Story Narrative, KPI Stats, Core Value Pillars, Leadership Team, Brand Milestones, Photo Gallery, CTA, i18n Translations, and SEO Metadata.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploader.php';
require_once __DIR__ . '/../includes/i18n.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'About Us Page Content Management';

// Handle POST Save Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try submitting again.');
        header('Location: ' . BASE_URL . '/admin/about.php');
        exit;
    }

    $activeTab = trim($_POST['active_tab'] ?? 'hero');

    // Save Hero Banner Settings via shared helper
    process_hero_settings_post();

    // Process Team Members JSON Array
    $teamInput = $_POST['team'] ?? [];
    $teamList = [];
    if (is_array($teamInput)) {
        foreach ($teamInput as $member) {
            $name = trim($member['name'] ?? '');
            if (!empty($name)) {
                $teamList[] = [
                    'name' => $name,
                    'role' => trim($member['role'] ?? ''),
                    'photo' => trim($member['photo'] ?? ''),
                    'bio' => trim($member['bio'] ?? ''),
                    'contact' => trim($member['contact'] ?? '')
                ];
            }
        }
    }
    $teamJson = json_encode($teamList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Process Timeline Milestones JSON Array
    $timelineInput = $_POST['timeline'] ?? [];
    $timelineList = [];
    if (is_array($timelineInput)) {
        foreach ($timelineInput as $event) {
            $title = trim($event['title'] ?? '');
            if (!empty($title)) {
                $timelineList[] = [
                    'year' => trim($event['year'] ?? date('Y')),
                    'title' => $title,
                    'desc' => trim($event['desc'] ?? ''),
                    'icon' => trim($event['icon'] ?? 'flag')
                ];
            }
        }
    }
    $timelineJson = json_encode($timelineList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Process Gallery Photos JSON Array
    $galleryInput = $_POST['gallery_photos'] ?? [];
    $galleryList = [];
    if (is_array($galleryInput)) {
        foreach ($galleryInput as $item) {
            $url = trim($item['url'] ?? '');
            if (!empty($url)) {
                $galleryList[] = [
                    'url' => $url,
                    'caption' => trim($item['caption'] ?? '')
                ];
            }
        }
    }
    $galleryJson = json_encode($galleryList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Process Multi-Language i18n Translations JSON
    $translationsPayload = [
        'km' => [
            'story_badge' => trim($_POST['trans_km_story_badge'] ?? ''),
            'story_title' => trim($_POST['trans_km_story_title'] ?? ''),
            'story_p1' => trim($_POST['trans_km_story_p1'] ?? ''),
            'pillars_title' => trim($_POST['trans_km_pillars_title'] ?? ''),
            'cta_title' => trim($_POST['trans_km_cta_title'] ?? '')
        ],
        'zh' => [
            'story_badge' => trim($_POST['trans_zh_story_badge'] ?? ''),
            'story_title' => trim($_POST['trans_zh_story_title'] ?? ''),
            'story_p1' => trim($_POST['trans_zh_story_p1'] ?? ''),
            'pillars_title' => trim($_POST['trans_zh_pillars_title'] ?? ''),
            'cta_title' => trim($_POST['trans_zh_cta_title'] ?? '')
        ],
        'ko' => [
            'story_badge' => trim($_POST['trans_ko_story_badge'] ?? ''),
            'story_title' => trim($_POST['trans_ko_story_title'] ?? ''),
            'story_p1' => trim($_POST['trans_ko_story_p1'] ?? ''),
            'pillars_title' => trim($_POST['trans_ko_pillars_title'] ?? ''),
            'cta_title' => trim($_POST['trans_ko_cta_title'] ?? '')
        ]
    ];
    $transJson = json_encode($translationsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Map of all About Us Page configurable settings
    $settingsMap = [
        // Tab 1: Story Narrative
        'about_story_badge' => trim($_POST['about_story_badge'] ?? 'The Indra Story'),
        'about_story_title' => trim($_POST['about_story_title'] ?? 'Reimagining Boutique Hospitality with Intimacy and Soul'),
        'about_story_p1' => trim($_POST['about_story_p1'] ?? ''),
        'about_story_p2' => trim($_POST['about_story_p2'] ?? ''),
        'about_story_p3' => trim($_POST['about_story_p3'] ?? ''),
        'about_story_image' => trim($_POST['about_story_image'] ?? ''),
        'about_story_card_title' => trim($_POST['about_story_card_title'] ?? 'Direct Booking Guarantee'),
        'about_story_card_desc' => trim($_POST['about_story_card_desc'] ?? 'Best rates, free upgrades & flexible cancellation.'),
        'about_story_card_icon' => trim($_POST['about_story_card_icon'] ?? 'verified'),

        // Tab 2: KPI Stats Counters
        'about_stat1_val' => trim($_POST['about_stat1_val'] ?? '12'),
        'about_stat1_label' => trim($_POST['about_stat1_label'] ?? 'Luxury Suites'),
        'about_stat2_val' => trim($_POST['about_stat2_val'] ?? '100%'),
        'about_stat2_label' => trim($_POST['about_stat2_label'] ?? 'Saltwater Pool'),
        'about_stat3_val' => trim($_POST['about_stat3_val'] ?? '24/7'),
        'about_stat3_label' => trim($_POST['about_stat3_label'] ?? 'Front Desk'),
        'about_stat4_val' => trim($_POST['about_stat4_val'] ?? '4.9★'),
        'about_stat4_label' => trim($_POST['about_stat4_label'] ?? 'Guest Rating'),

        // Tab 3: Core Value Pillars
        'about_pillars_json' => trim($_POST['about_pillars_json'] ?? '[]'),
        'about_pillars_badge' => trim($_POST['about_pillars_badge'] ?? 'Our Core Values'),
        'about_pillars_title' => trim($_POST['about_pillars_title'] ?? 'Crafted for Discerning Travelers'),
        
        'about_pillar1_icon' => trim($_POST['about_pillar1_icon'] ?? 'nature_people'),
        'about_pillar1_title' => trim($_POST['about_pillar1_title'] ?? 'Tranquil Urban Oasis'),
        'about_pillar1_desc' => trim($_POST['about_pillar1_desc'] ?? ''),

        'about_pillar2_icon' => trim($_POST['about_pillar2_icon'] ?? 'restaurant'),
        'about_pillar2_title' => trim($_POST['about_pillar2_title'] ?? 'Artisan Culinary Flavors'),
        'about_pillar2_desc' => trim($_POST['about_pillar2_desc'] ?? ''),

        'about_pillar3_icon' => trim($_POST['about_pillar3_icon'] ?? 'loyalty'),
        'about_pillar3_title' => trim($_POST['about_pillar3_title'] ?? 'Personalized Concierge'),
        'about_pillar3_desc' => trim($_POST['about_pillar3_desc'] ?? ''),

        // Tab 4: Team Section
        'about_show_team' => isset($_POST['about_show_team']) ? '1' : '0',
        'about_team_badge' => trim($_POST['about_team_badge'] ?? 'Hospitality Leadership'),
        'about_team_title' => trim($_POST['about_team_title'] ?? 'Meet Our Dedicated Concierge & Management'),
        'about_team_members_json' => $teamJson,

        // Tab 5: Timeline Section
        'about_show_timeline' => isset($_POST['about_show_timeline']) ? '1' : '0',
        'about_timeline_badge' => trim($_POST['about_timeline_badge'] ?? 'Our Journey'),
        'about_timeline_title' => trim($_POST['about_timeline_title'] ?? 'Milestones of Excellence'),
        'about_timeline_json' => $timelineJson,

        // Tab 6: Architectural Gallery Section
        'about_show_gallery' => isset($_POST['about_show_gallery']) ? '1' : '0',
        'about_gallery_badge' => trim($_POST['about_gallery_badge'] ?? 'Architectural Details'),
        'about_gallery_title' => trim($_POST['about_gallery_title'] ?? 'Designed for Serenity & Space'),
        'about_gallery_photos_json' => $galleryJson,

        // Tab 7: Call to Action & SEO Meta
        'about_cta_title' => trim($_POST['about_cta_title'] ?? ''),
        'about_cta_desc' => trim($_POST['about_cta_desc'] ?? ''),
        'about_cta_btn1_text' => trim($_POST['about_cta_btn1_text'] ?? 'Explore Rooms & Suites'),
        'about_cta_btn1_url' => trim($_POST['about_cta_btn1_url'] ?? '/rooms'),
        'about_cta_btn2_text' => trim($_POST['about_cta_btn2_text'] ?? 'Contact Concierge'),
        'about_cta_btn2_url' => trim($_POST['about_cta_btn2_url'] ?? '/contact'),

        'about_seo_title' => trim($_POST['about_seo_title'] ?? ''),
        'about_seo_description' => trim($_POST['about_seo_description'] ?? ''),
        'about_seo_keywords' => trim($_POST['about_seo_keywords'] ?? ''),
        
        'about_translations_json' => $transJson
    ];

    foreach ($settingsMap as $key => $value) {
        set_setting($key, $value);
    }

    set_flash('success', 'About Us page content updated successfully.');
    header('Location: ' . BASE_URL . '/admin/about.php?tab=' . urlencode($activeTab));
    exit;
}

$activeTab = $_GET['tab'] ?? 'hero';

// Load stored JSON arrays
$teamJsonSaved = get_setting('about_team_members_json', '');
$teamMembers = !empty($teamJsonSaved) ? json_decode($teamJsonSaved, true) : [];
if (!is_array($teamMembers) || empty($teamMembers)) {
    $teamMembers = [
        [
            'name' => 'Sophea Vann',
            'role' => 'General Manager & Founder',
            'photo' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=80',
            'bio' => '15+ years in boutique luxury hospitality across Southeast Asia, committed to authentic Khmer warmth and sustainable sanctuary management.',
            'contact' => 'sophea@indrahotel.asia'
        ],
        [
            'name' => 'Channary Sok',
            'role' => 'Head Chef & Culinary Director',
            'photo' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?auto=format&fit=crop&w=600&q=80',
            'bio' => 'Crafting refined Cambodian fusion gastronomy using organic local produce and Kampot peppercorns at The Bistro.',
            'contact' => 'dining@indrahotel.asia'
        ],
        [
            'name' => 'Dara Meas',
            'role' => 'Chief Concierge & Guest Experience',
            'photo' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=80',
            'bio' => 'Dedicated to curating bespoke itineraries, VIP chauffeured transfers, and private sunset river cruises across Phnom Penh.',
            'contact' => 'concierge@indrahotel.asia'
        ]
    ];
}

$timelineJsonSaved = get_setting('about_timeline_json', '');
$timelineMilestones = !empty($timelineJsonSaved) ? json_decode($timelineJsonSaved, true) : [];
if (!is_array($timelineMilestones) || empty($timelineMilestones)) {
    $timelineMilestones = [
        [
            'year' => '2018',
            'title' => 'Architectural Vision & Groundbreaking',
            'desc' => 'Conceived as an intimate modernist sanctuary combining natural teak wood, tropical foliage, and quiet acoustic insulation in Tuol Kork.',
            'icon' => 'architecture'
        ],
        [
            'year' => '2020',
            'title' => 'Grand Opening of Sanctuary',
            'desc' => 'Opened our doors with 12 luxury suites, welcoming international travelers seeking unhurried space away from the energetic city center.',
            'icon' => 'hotel'
        ],
        [
            'year' => '2022',
            'title' => 'The Bistro & Artisanal Cafe Launch',
            'desc' => 'Introduced organic Cambodian specialty coffee roasting and contemporary Asian-fusion fine dining menus.',
            'icon' => 'restaurant'
        ],
        [
            'year' => '2024',
            'title' => 'Saltwater Pool & Sustainability Certification',
            'desc' => 'Unveiled our eco-friendly saltwater pool and achieved 100% organic locally crafted amenity certification.',
            'icon' => 'pool'
        ]
    ];
}

$galleryJsonSaved = get_setting('about_gallery_photos_json', '');
$galleryPhotos = !empty($galleryJsonSaved) ? json_decode($galleryJsonSaved, true) : [];
if (!is_array($galleryPhotos) || empty($galleryPhotos)) {
    $galleryPhotos = [
        ['url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80', 'caption' => 'Teak Crafted Suite Balcony View'],
        ['url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80', 'caption' => 'Tropical Courtyard & Reflection Pool'],
        ['url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80', 'caption' => 'Artisanal Bistro Dining Lounge'],
        ['url' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=800&q=80', 'caption' => 'Serene Saltwater Swimming Pool']
    ];
}

$transSaved = get_setting('about_translations_json', '');
$translations = !empty($transSaved) ? json_decode($transSaved, true) : [];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">
    
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-stone-200">
        <div>
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Content Management</span>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">About Us Page Editor</h1>
            <p class="text-xs text-stone-500 mt-1">Full control over hero header, brand narrative, stats, core values, leadership team, timeline, photo gallery, CTA, and SEO metadata.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/about.php" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 border border-stone-300 hover:border-stone-400 rounded-lg text-xs font-semibold text-stone-700 bg-white transition shadow-xs">
                <span class="material-symbols-outlined text-base text-[#4B5320]">visibility</span>
                <span>View Live About Page</span>
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-stone-200 overflow-x-auto no-scrollbar pb-px">
        <button type="button" onclick="switchTab('hero')" id="tab-btn-hero" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">view_day</span>
            <span>1. Page Hero Banner</span>
        </button>
        <button type="button" onclick="switchTab('story')" id="tab-btn-story" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">auto_stories</span>
            <span>2. Story & Narrative</span>
        </button>
        <button type="button" onclick="switchTab('stats')" id="tab-btn-stats" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">monitoring</span>
            <span>3. Key Statistics</span>
        </button>
        <button type="button" onclick="switchTab('pillars')" id="tab-btn-pillars" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">diamond</span>
            <span>4. Core Values</span>
        </button>
        <button type="button" onclick="switchTab('team')" id="tab-btn-team" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">groups</span>
            <span>5. Leadership Team</span>
        </button>
        <button type="button" onclick="switchTab('timeline')" id="tab-btn-timeline" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">history_edu</span>
            <span>6. Journey Timeline</span>
        </button>
        <button type="button" onclick="switchTab('gallery')" id="tab-btn-gallery" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">photo_library</span>
            <span>7. Design Gallery</span>
        </button>
        <button type="button" onclick="switchTab('cta')" id="tab-btn-cta" class="tab-btn px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent">
            <span class="material-symbols-outlined text-base">call_to_action</span>
            <span>8. CTA & SEO</span>
        </button>
    </div>

    <!-- Main Editor Form -->
    <form action="<?= BASE_URL ?>/admin/about.php" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
        <input type="hidden" name="active_tab" id="active_tab_input" value="<?= e($activeTab) ?>">

        <!-- TAB 1: Hero Banner -->
        <div id="tab-content-hero" class="tab-content hidden space-y-6">
            <?= render_admin_hero_editor_card('about', 'About Us Page Hero Banner', [
                'badge' => 'Our Heritage & Philosophy',
                'title' => 'A Contemporary Sanctuary in Phnom Penh',
                'subtitle' => 'Designed as an intimate urban retreat, ' . hotel_name() . ' seamlessly merges serene modernist architecture with warm Khmer hospitality.',
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
                'icon' => 'spa'
            ]) ?>
        </div>

        <!-- TAB 2: Story & Narrative -->
        <div id="tab-content-story" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">auto_stories</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Main Heritage Story Narrative</h2>
                        <p class="text-xs text-stone-500">Brand story text, introduction paragraphs, and feature visual highlight.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Sub-Badge *</label>
                        <input type="text" name="about_story_badge" required
                               value="<?= e(get_setting('about_story_badge', 'The Indra Story')) ?>"
                               class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Main Section Headline *</label>
                        <input type="text" name="about_story_title" required
                               value="<?= e(get_setting('about_story_title', 'Reimagining Boutique Hospitality with Intimacy and Soul')) ?>"
                               class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Story Paragraph 1</label>
                        <textarea name="about_story_p1" rows="3" class="w-full text-xs sm:text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('about_story_p1', '')) ?></textarea>
                        <p class="text-[11px] text-stone-400 mt-1">Leave empty to use default paragraph featuring <?= e(hotel_name()) ?> and Tuol Kork location.</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Story Paragraph 2</label>
                        <textarea name="about_story_p2" rows="3" class="w-full text-xs sm:text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('about_story_p2', '')) ?></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Story Paragraph 3</label>
                        <textarea name="about_story_p3" rows="3" class="w-full text-xs sm:text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"><?= e(get_setting('about_story_p3', '')) ?></textarea>
                    </div>

                    <div class="md:col-span-2 border-t border-stone-100 pt-6">
                        <?= render_image_uploader_field('about_story_image', get_setting('about_story_image', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1000&q=80'), 'Atmosphere Feature Photo', 'about', [
                            'helper' => 'High quality vertical/portrait photo (e.g. 4:5 aspect ratio)'
                        ]) ?>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <h3 class="text-xs font-bold text-stone-800 uppercase tracking-wider">Floating Highlight Badge Title</h3>
                        <div class="flex items-center gap-2">
                            <input type="text" name="about_story_card_icon" value="<?= e(get_setting('about_story_card_icon', 'verified')) ?>" placeholder="verified" class="w-24 text-xs font-mono border border-stone-300 rounded p-2">
                            <input type="text" name="about_story_card_title" value="<?= e(get_setting('about_story_card_title', 'Direct Booking Guarantee')) ?>" class="flex-1 text-xs font-bold border border-stone-300 rounded p-2">
                        </div>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <h3 class="text-xs font-bold text-stone-800 uppercase tracking-wider">Floating Highlight Subtitle</h3>
                        <input type="text" name="about_story_card_desc" value="<?= e(get_setting('about_story_card_desc', 'Best rates, free upgrades & flexible cancellation.')) ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: Statistics Counters -->
        <div id="tab-content-stats" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">monitoring</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Key Highlights & Statistics</h2>
                        <p class="text-xs text-stone-500">4 counter metrics displayed under the main story narrative.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Metric 1</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Value / Number</label>
                            <input type="text" name="about_stat1_val" value="<?= e(get_setting('about_stat1_val', '12')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Label</label>
                            <input type="text" name="about_stat1_label" value="<?= e(get_setting('about_stat1_label', 'Luxury Suites')) ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                        </div>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Metric 2</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Value / Number</label>
                            <input type="text" name="about_stat2_val" value="<?= e(get_setting('about_stat2_val', '100%')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Label</label>
                            <input type="text" name="about_stat2_label" value="<?= e(get_setting('about_stat2_label', 'Saltwater Pool')) ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                        </div>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Metric 3</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Value / Number</label>
                            <input type="text" name="about_stat3_val" value="<?= e(get_setting('about_stat3_val', '24/7')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Label</label>
                            <input type="text" name="about_stat3_label" value="<?= e(get_setting('about_stat3_label', 'Front Desk')) ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                        </div>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Metric 4</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Value / Number</label>
                            <input type="text" name="about_stat4_val" value="<?= e(get_setting('about_stat4_val', '4.9★')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Label</label>
                            <input type="text" name="about_stat4_label" value="<?= e(get_setting('about_stat4_label', 'Guest Rating')) ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: Core Values & Pillars (Dynamic Builder) -->
        <div id="tab-content-pillars" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">diamond</span>
                        </div>
                        <div>
                            <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Core Values & Hospitality Pillars</h2>
                            <p class="text-xs text-stone-500">Manage feature cards highlighting the hotel's experiences & core values.</p>
                        </div>
                    </div>

                    <button type="button" onclick="addAboutPillarRow()" class="inline-flex items-center gap-1.5 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-2xs cursor-pointer self-start sm:self-auto">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Add Value Pillar</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Sub-Badge *</label>
                        <input type="text" name="about_pillars_badge" value="<?= e(get_setting('about_pillars_badge', 'Our Core Values')) ?>" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Headline *</label>
                        <input type="text" name="about_pillars_title" value="<?= e(get_setting('about_pillars_title', 'Crafted for Discerning Travelers')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <input type="hidden" name="about_pillars_json" id="about_pillars_json" value="<?= e(get_setting('about_pillars_json', '[]')) ?>">

                <div id="about_pillars_container" class="space-y-4 pt-2">
                    <!-- Dynamic Repeater Cards -->
                </div>
            </div>
        </div>

        <!-- TAB 5: Leadership Team -->
        <div id="tab-content-team" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">groups</span>
                        </div>
                        <div>
                            <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Hospitality Leadership & Concierge Team</h2>
                            <p class="text-xs text-stone-500">Showcase management, general manager, head chef, and concierge staff.</p>
                        </div>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="about_show_team" value="1" <?= get_setting('about_show_team', '1') === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                        <span class="ml-2 text-xs font-bold text-stone-700">Display Section</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Badge</label>
                        <input type="text" name="about_team_badge" value="<?= e(get_setting('about_team_badge', 'Hospitality Leadership')) ?>" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Title</label>
                        <input type="text" name="about_team_title" value="<?= e(get_setting('about_team_title', 'Meet Our Dedicated Concierge & Management')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div class="pt-4 space-y-4" id="team-members-container">
                    <?php foreach ($teamMembers as $idx => $m): ?>
                    <div class="team-member-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Team Member #<span class="row-num"><?= $idx + 1 ?></span></span>
                            <button type="button" onclick="this.closest('.team-member-row').remove(); updateRowNumbers('team-members-container', 'team-member-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">delete</span>
                                <span>Remove</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Full Name *</label>
                                <input type="text" name="team[<?= $idx ?>][name]" value="<?= e($m['name'] ?? '') ?>" required class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Role / Position</label>
                                <input type="text" name="team[<?= $idx ?>][role]" value="<?= e($m['role'] ?? '') ?>" class="w-full text-xs border border-stone-300 rounded p-2">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Photo URL</label>
                                <input type="text" name="team[<?= $idx ?>][photo]" value="<?= e($m['photo'] ?? '') ?>" placeholder="https://..." class="w-full text-xs border border-stone-300 rounded p-2 font-mono">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Short Bio</label>
                                <textarea name="team[<?= $idx ?>][bio]" rows="2" class="w-full text-xs border border-stone-300 rounded p-2"><?= e($m['bio'] ?? '') ?></textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Email / Contact Link</label>
                                <input type="text" name="team[<?= $idx ?>][contact]" value="<?= e($m['contact'] ?? '') ?>" placeholder="concierge@indrahotel.asia" class="w-full text-xs border border-stone-300 rounded p-2">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addTeamMemberRow()" class="px-4 py-2 border border-dashed border-stone-400 hover:border-[#343c0a] text-[#343c0a] rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer w-full">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>Add New Team Member</span>
                </button>
            </div>
        </div>

        <!-- TAB 6: Journey Timeline -->
        <div id="tab-content-timeline" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">history_edu</span>
                        </div>
                        <div>
                            <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Brand Heritage & Journey Timeline</h2>
                            <p class="text-xs text-stone-500">Historical milestones from foundation to present awards.</p>
                        </div>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="about_show_timeline" value="1" <?= get_setting('about_show_timeline', '1') === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                        <span class="ml-2 text-xs font-bold text-stone-700">Display Section</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Badge</label>
                        <input type="text" name="about_timeline_badge" value="<?= e(get_setting('about_timeline_badge', 'Our Journey')) ?>" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Title</label>
                        <input type="text" name="about_timeline_title" value="<?= e(get_setting('about_timeline_title', 'Milestones of Excellence')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div class="pt-4 space-y-4" id="timeline-milestones-container">
                    <?php foreach ($timelineMilestones as $idx => $t): ?>
                    <div class="timeline-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Milestone #<span class="row-num"><?= $idx + 1 ?></span></span>
                            <button type="button" onclick="this.closest('.timeline-row').remove(); updateRowNumbers('timeline-milestones-container', 'timeline-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">delete</span>
                                <span>Remove</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Year / Date</label>
                                <input type="text" name="timeline[<?= $idx ?>][year]" value="<?= e($t['year'] ?? '') ?>" placeholder="2018" class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Google Icon</label>
                                <input type="text" name="timeline[<?= $idx ?>][icon]" value="<?= e($t['icon'] ?? 'flag') ?>" placeholder="architecture" class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Milestone Title *</label>
                                <input type="text" name="timeline[<?= $idx ?>][title]" value="<?= e($t['title'] ?? '') ?>" required class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Description</label>
                                <textarea name="timeline[<?= $idx ?>][desc]" rows="2" class="w-full text-xs border border-stone-300 rounded p-2"><?= e($t['desc'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addTimelineRow()" class="px-4 py-2 border border-dashed border-stone-400 hover:border-[#343c0a] text-[#343c0a] rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer w-full">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>Add New Milestone</span>
                </button>
            </div>
        </div>

        <!-- TAB 7: Design Gallery -->
        <div id="tab-content-gallery" class="tab-content hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">photo_library</span>
                        </div>
                        <div>
                            <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Architectural & Atmosphere Gallery</h2>
                            <p class="text-xs text-stone-500">Showcase design craftsmanship, gardens, reflection pools, and teak wood details.</p>
                        </div>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="about_show_gallery" value="1" <?= get_setting('about_show_gallery', '1') === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                        <span class="ml-2 text-xs font-bold text-stone-700">Display Section</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Badge</label>
                        <input type="text" name="about_gallery_badge" value="<?= e(get_setting('about_gallery_badge', 'Architectural Details')) ?>" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Section Title</label>
                        <input type="text" name="about_gallery_title" value="<?= e(get_setting('about_gallery_title', 'Designed for Serenity & Space')) ?>" class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div class="pt-4 space-y-4" id="gallery-photos-container">
                    <?php foreach ($galleryPhotos as $idx => $g): ?>
                    <div class="gallery-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Photo #<span class="row-num"><?= $idx + 1 ?></span></span>
                            <button type="button" onclick="this.closest('.gallery-row').remove(); updateRowNumbers('gallery-photos-container', 'gallery-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">delete</span>
                                <span>Remove</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Image URL *</label>
                                <input type="text" name="gallery_photos[<?= $idx ?>][url]" value="<?= e($g['url'] ?? '') ?>" required placeholder="https://..." class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Caption / Subtitle</label>
                                <input type="text" name="gallery_photos[<?= $idx ?>][caption]" value="<?= e($g['caption'] ?? '') ?>" placeholder="Teak Crafted Suite Balcony View" class="w-full text-xs border border-stone-300 rounded p-2">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addGalleryRow()" class="px-4 py-2 border border-dashed border-stone-400 hover:border-[#343c0a] text-[#343c0a] rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer w-full">
                    <span class="material-symbols-outlined text-base">add_a_photo</span>
                    <span>Add Gallery Photo</span>
                </button>
            </div>
        </div>

        <!-- TAB 8: CTA Banner & SEO -->
        <div id="tab-content-cta" class="tab-content hidden space-y-6">
            <!-- CTA Section Settings -->
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">call_to_action</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Call To Action (CTA) Banner</h2>
                        <p class="text-xs text-stone-500">Banner title, promotional description, and action button links.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">CTA Section Title</label>
                        <input type="text" name="about_cta_title" value="<?= e(get_setting('about_cta_title', 'Experience the Refinement of ' . hotel_name())) ?>" class="w-full text-sm font-bold border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">CTA Promotional Description</label>
                        <textarea name="about_cta_desc" rows="3" class="w-full text-xs sm:text-sm border border-stone-300 rounded-lg p-3"><?= e(get_setting('about_cta_desc', 'Reserve your suite directly on our official website for complimentary airport pick-up on stays of 3+ nights, priority check-in, and exclusive seasonal rates.')) ?></textarea>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Button 1 (Primary Highlight)</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Button Text</label>
                            <input type="text" name="about_cta_btn1_text" value="<?= e(get_setting('about_cta_btn1_text', 'Explore Rooms & Suites')) ?>" class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">URL Path</label>
                            <input type="text" name="about_cta_btn1_url" value="<?= e(get_setting('about_cta_btn1_url', '/rooms')) ?>" class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                        </div>
                    </div>

                    <div class="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                        <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider block">Button 2 (Secondary Bordered)</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Button Text</label>
                            <input type="text" name="about_cta_btn2_text" value="<?= e(get_setting('about_cta_btn2_text', 'Contact Concierge')) ?>" class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">URL Path</label>
                            <input type="text" name="about_cta_btn2_url" value="<?= e(get_setting('about_cta_btn2_url', '/contact')) ?>" class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page SEO Meta -->
            <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xs space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                    <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">travel_explore</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">About Page SEO & Meta Tags</h2>
                        <p class="text-xs text-stone-500">Custom meta title, description, and keywords for search engines.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Meta Title Tag</label>
                        <input type="text" name="about_seo_title" value="<?= e(get_setting('about_seo_title', 'About Our Sanctuary | ' . hotel_name() . ' Phnom Penh')) ?>" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Meta Description</label>
                        <textarea name="about_seo_description" rows="2" class="w-full text-xs sm:text-sm border border-stone-300 rounded-lg p-2.5"><?= e(get_setting('about_seo_description', 'Discover the story, architecture, and tranquil hospitality of ' . hotel_name() . ' — an urban boutique sanctuary in Tuol Kork, Phnom Penh.')) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Meta Keywords</label>
                        <input type="text" name="about_seo_keywords" value="<?= e(get_setting('about_seo_keywords', hotel_name() . ', about boutique hotel phnom penh, tuol kork luxury hotel, cambodia boutique sanctuary')) ?>" class="w-full text-xs border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Floating Action Footer -->
        <div class="sticky bottom-4 z-20 bg-white/95 backdrop-blur-md rounded-2xl p-4 border border-stone-300 shadow-xl flex items-center justify-between gap-4">
            <div class="text-xs text-stone-500 hidden sm:block">
                <span class="font-bold text-stone-800">Ready to publish?</span> All edits take effect immediately on live About Us page.
            </div>
            <button type="submit" class="w-full sm:w-auto bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-3 rounded-xl font-bold text-xs uppercase tracking-wider shadow-md transition flex items-center justify-center gap-2 cursor-pointer btn-shimmer">
                <span class="material-symbols-outlined text-lg">save</span>
                <span>Save About Us Page Content</span>
            </button>
        </div>

    </form>
</div>

<script>
function switchTab(tabKey) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('bg-[#343c0a]', 'text-[#dfe8a6]', 'border-[#343c0a]');
        el.classList.add('text-stone-500', 'hover:bg-stone-100');
    });

    const activeContent = document.getElementById('tab-content-' + tabKey);
    const activeBtn = document.getElementById('tab-btn-' + tabKey);
    const inputActive = document.getElementById('active_tab_input');

    if (activeContent && activeBtn) {
        activeContent.classList.remove('hidden');
        activeBtn.classList.remove('text-stone-500', 'hover:bg-stone-100');
        activeBtn.classList.add('bg-[#343c0a]', 'text-[#dfe8a6]', 'border-[#343c0a]');
        if (inputActive) inputActive.value = tabKey;
    }
}

function updateRowNumbers(containerId, rowClass) {
    const rows = document.querySelectorAll('#' + containerId + ' .' + rowClass);
    rows.forEach((row, i) => {
        const numSpan = row.querySelector('.row-num');
        if (numSpan) numSpan.textContent = (i + 1);
    });
}

function addTeamMemberRow() {
    const container = document.getElementById('team-members-container');
    const idx = container.querySelectorAll('.team-member-row').length;
    const html = `
        <div class="team-member-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Team Member #<span class="row-num">${idx + 1}</span></span>
                <button type="button" onclick="this.closest('.team-member-row').remove(); updateRowNumbers('team-members-container', 'team-member-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">delete</span>
                    <span>Remove</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Full Name *</label>
                    <input type="text" name="team[${idx}][name]" required class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Role / Position</label>
                    <input type="text" name="team[${idx}][role]" placeholder="Manager" class="w-full text-xs border border-stone-300 rounded p-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Photo URL</label>
                    <input type="text" name="team[${idx}][photo]" placeholder="https://..." class="w-full text-xs border border-stone-300 rounded p-2 font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Short Bio</label>
                    <textarea name="team[${idx}][bio]" rows="2" class="w-full text-xs border border-stone-300 rounded p-2"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Email / Contact Link</label>
                    <input type="text" name="team[${idx}][contact]" placeholder="email@indrahotel.asia" class="w-full text-xs border border-stone-300 rounded p-2">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function addTimelineRow() {
    const container = document.getElementById('timeline-milestones-container');
    const idx = container.querySelectorAll('.timeline-row').length;
    const html = `
        <div class="timeline-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Milestone #<span class="row-num">${idx + 1}</span></span>
                <button type="button" onclick="this.closest('.timeline-row').remove(); updateRowNumbers('timeline-milestones-container', 'timeline-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">delete</span>
                    <span>Remove</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Year / Date</label>
                    <input type="text" name="timeline[${idx}][year]" placeholder="2025" class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Google Icon</label>
                    <input type="text" name="timeline[${idx}][icon]" placeholder="star" class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Milestone Title *</label>
                    <input type="text" name="timeline[${idx}][title]" required class="w-full text-xs font-bold border border-stone-300 rounded p-2">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Description</label>
                    <textarea name="timeline[${idx}][desc]" rows="2" class="w-full text-xs border border-stone-300 rounded p-2"></textarea>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function addGalleryRow() {
    const container = document.getElementById('gallery-photos-container');
    const idx = container.querySelectorAll('.gallery-row').length;
    const html = `
        <div class="gallery-row p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3 relative">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Photo #<span class="row-num">${idx + 1}</span></span>
                <button type="button" onclick="this.closest('.gallery-row').remove(); updateRowNumbers('gallery-photos-container', 'gallery-row');" class="text-rose-600 hover:text-rose-800 text-xs font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">delete</span>
                    <span>Remove</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Image URL *</label>
                    <input type="text" name="gallery_photos[${idx}][url]" required placeholder="https://..." class="w-full text-xs font-mono border border-stone-300 rounded p-2">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 mb-1">Caption / Subtitle</label>
                    <input type="text" name="gallery_photos[${idx}][caption]" placeholder="Sanctuary Atmosphere" class="w-full text-xs border border-stone-300 rounded p-2">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

if (typeof escapeHtml !== 'function') {
    window.escapeHtml = function(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };
}

const defaultAboutPillars = [
    {
        icon: '<?= e(addslashes(get_setting('about_pillar1_icon', 'nature_people'))) ?>',
        title: '<?= e(addslashes(get_setting('about_pillar1_title', 'Tranquil Urban Oasis'))) ?>',
        desc: '<?= e(addslashes(get_setting('about_pillar1_desc', 'Designed with lush tropical foliage, quiet courtyard reflection ponds, and natural acoustic insulation so you can unwind completely.'))) ?>'
    },
    {
        icon: '<?= e(addslashes(get_setting('about_pillar2_icon', 'restaurant'))) ?>',
        title: '<?= e(addslashes(get_setting('about_pillar2_title', 'Artisan Culinary Flavors'))) ?>',
        desc: '<?= e(addslashes(get_setting('about_pillar2_desc', 'Experience seasonal Khmer ingredients, organic roasted coffees, and bespoke cocktails crafted by master mixologists.'))) ?>'
    },
    {
        icon: '<?= e(addslashes(get_setting('about_pillar3_icon', 'loyalty'))) ?>',
        title: '<?= e(addslashes(get_setting('about_pillar3_title', 'Personalized Concierge'))) ?>',
        desc: '<?= e(addslashes(get_setting('about_pillar3_desc', 'From private airport transfers to curated Phnom Penh cultural tours, our team is committed to fulfilling every detail.'))) ?>'
    }
];

function initAboutPillars() {
    const input = document.getElementById('about_pillars_json');
    if (!input) return;
    let items = [];
    try { items = JSON.parse(input.value || '[]'); } catch(e) { items = []; }
    if (!Array.isArray(items) || items.length === 0) {
        items = defaultAboutPillars;
    }
    renderAboutPillars(items);
}

function renderAboutPillars(items) {
    const container = document.getElementById('about_pillars_container');
    if (!container) return;
    container.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0) {
        container.innerHTML = '<div class="p-6 text-center text-xs text-stone-400 border border-dashed border-stone-200 rounded-xl bg-stone-50/50">No value pillars added. Click "+ Add Value Pillar" above.</div>';
        syncAboutPillarsJson();
        return;
    }
    items.forEach((item, idx) => {
        addAboutPillarRow(item, idx);
    });
}

function addAboutPillarRow(data = {}, index = null) {
    const container = document.getElementById('about_pillars_container');
    if (!container) return;
    const emptyMsg = container.querySelector('.text-center');
    if (emptyMsg) container.innerHTML = '';

    const rowIdx = index !== null ? index : container.querySelectorAll('.about-pillar-card').length;
    const icon = data.icon || 'star';
    const title = data.title || '';
    const desc = data.desc || '';

    const card = document.createElement('div');
    card.className = 'p-5 bg-stone-50 border border-stone-200 rounded-xl space-y-3 about-pillar-card relative group shadow-2xs';
    card.innerHTML = `
        <div class="flex items-center justify-between border-b border-stone-200/80 pb-2">
            <span class="text-xs font-bold text-[#4B5320] uppercase tracking-wider">Value Pillar #${rowIdx + 1}</span>
            <div class="flex items-center gap-1">
                <button type="button" onclick="moveAboutPillarRow(this, -1)" class="p-1 text-stone-500 hover:text-stone-900 rounded cursor-pointer" title="Move Up"><span class="material-symbols-outlined text-base">arrow_upward</span></button>
                <button type="button" onclick="moveAboutPillarRow(this, 1)" class="p-1 text-stone-500 hover:text-stone-900 rounded cursor-pointer" title="Move Down"><span class="material-symbols-outlined text-base">arrow_downward</span></button>
                <button type="button" onclick="removeAboutPillarRow(this)" class="p-1 text-rose-600 hover:text-rose-800 rounded cursor-pointer" title="Delete Pillar"><span class="material-symbols-outlined text-base">delete</span></button>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Material Icon Name</label>
                <input type="text" value="${escapeHtml(icon)}" oninput="syncAboutPillarsJson()" class="pillar-icon w-full text-xs font-mono border border-stone-300 rounded p-2">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-stone-600 mb-1">Title</label>
                <input type="text" value="${escapeHtml(title)}" oninput="syncAboutPillarsJson()" class="pillar-title w-full text-sm font-bold border border-stone-300 rounded p-2">
            </div>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Description</label>
            <textarea rows="2" oninput="syncAboutPillarsJson()" class="pillar-desc w-full text-xs border border-stone-300 rounded p-2">${escapeHtml(desc)}</textarea>
        </div>
    `;
    container.appendChild(card);
    syncAboutPillarsJson();
}

function removeAboutPillarRow(btn) {
    const card = btn.closest('.about-pillar-card');
    if (card) {
        card.remove();
        syncAboutPillarsJson();
    }
}

function moveAboutPillarRow(btn, dir) {
    const card = btn.closest('.about-pillar-card');
    if (!card) return;
    if (dir === -1 && card.previousElementSibling && card.previousElementSibling.classList.contains('about-pillar-card')) {
        card.parentNode.insertBefore(card, card.previousElementSibling);
    } else if (dir === 1 && card.nextElementSibling && card.nextElementSibling.classList.contains('about-pillar-card')) {
        card.parentNode.insertBefore(card.nextElementSibling, card);
    }
    syncAboutPillarsJson();
}

function syncAboutPillarsJson() {
    const container = document.getElementById('about_pillars_container');
    if (!container) return;
    const cards = container.querySelectorAll('.about-pillar-card');
    const items = [];
    cards.forEach(c => {
        const icon = c.querySelector('.pillar-icon')?.value.trim() || 'star';
        const title = c.querySelector('.pillar-title')?.value.trim() || '';
        const desc = c.querySelector('.pillar-desc')?.value.trim() || '';
        if (title || desc) items.push({ icon, title, desc });
    });
    const hiddenInput = document.getElementById('about_pillars_json');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(items);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initAboutPillars();
    switchTab('<?= e($activeTab) ?>');
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
