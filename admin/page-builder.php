<?php
/**
 * Indra Hotel - Dynamic Visual Page Builder
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/theme-engine.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = getDB();

$pageId = (int)($_GET['id'] ?? 0);
$page = null;

if ($pageId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $page = $stmt->fetch();
}

$message = '';
$messageType = '';

// Handle Form Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (Auth::verifyCsrf($csrf)) {
        $title = trim($_POST['title'] ?? '');
        $rawSlug = trim($_POST['slug'] ?? '');
        $slug = slugify(!empty($rawSlug) ? $rawSlug : $title);
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');

        $heroBadge = trim($_POST['hero_badge'] ?? '');
        $heroTitle = trim($_POST['hero_title'] ?? '');
        $heroSubtitle = trim($_POST['hero_subtitle'] ?? '');
        $heroImage = trim($_POST['hero_image'] ?? '');
        $heroVideo = trim($_POST['hero_video'] ?? '');
        $heroHeight = trim($_POST['hero_height'] ?? 'medium');
        $heroOverlay = trim($_POST['hero_overlay'] ?? 'medium');

        $themeId = trim($_POST['theme_id'] ?? 'default');
        $layoutType = trim($_POST['layout_type'] ?? 'full_width');
        $status = trim($_POST['status'] ?? 'published');
        $isInNav = isset($_POST['is_in_nav']) ? 1 : 0;
        $isInFooter = isset($_POST['is_in_footer']) ? 1 : 0;

        $sectionsRaw = $_POST['sections_json'] ?? '[]';
        // Validate JSON
        $decodedSec = json_decode($sectionsRaw, true);
        $sectionsJson = is_array($decodedSec) ? json_encode($decodedSec) : '[]';

        if (empty($title)) {
            $message = 'Page title cannot be empty.';
            $messageType = 'error';
        } else {
            try {
                if ($pageId > 0 && $page) {
                    $stmt = $pdo->prepare("UPDATE custom_pages SET slug = ?, title = ?, meta_title = ?, meta_description = ?, hero_badge = ?, hero_title = ?, hero_subtitle = ?, hero_image = ?, hero_video = ?, hero_height = ?, hero_overlay = ?, theme_id = ?, layout_type = ?, sections_json = ?, status = ?, is_in_nav = ?, is_in_footer = ? WHERE id = ?");
                    $stmt->execute([$slug, $title, $metaTitle, $metaDesc, $heroBadge, $heroTitle, $heroSubtitle, $heroImage, $heroVideo, $heroHeight, $heroOverlay, $themeId, $layoutType, $sectionsJson, $status, $isInNav, $isInFooter, $pageId]);
                    $message = 'Dynamic page updated successfully!';
                    $messageType = 'success';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO custom_pages (slug, title, meta_title, meta_description, hero_badge, hero_title, hero_subtitle, hero_image, hero_video, hero_height, hero_overlay, theme_id, layout_type, sections_json, status, is_in_nav, is_in_footer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$slug, $title, $metaTitle, $metaDesc, $heroBadge, $heroTitle, $heroSubtitle, $heroImage, $heroVideo, $heroHeight, $heroOverlay, $themeId, $layoutType, $sectionsJson, $status, $isInNav, $isInFooter]);
                    $pageId = (int)$pdo->lastInsertId();
                    $message = 'New dynamic page created successfully!';
                    $messageType = 'success';
                }

                // Refresh Page Data
                $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
                $stmt->execute([$pageId]);
                $page = $stmt->fetch();
            } catch (Throwable $e) {
                $message = 'Error saving page: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

$availableThemes = get_available_themes();
$existingSections = !empty($page['sections_json']) ? json_decode($page['sections_json'], true) : [];

$adminTitle = ($page ? 'Edit Page: ' . e($page['title']) : 'Create Custom Dynamic Page') . ' | CMS Admin';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<form action="<?= BASE_URL ?>/admin/page-builder.php<?= $pageId > 0 ? '?id=' . $pageId : '' ?>" method="POST" id="page-builder-form" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
    <input type="hidden" name="sections_json" id="input_sections_json" value="<?= e(json_encode($existingSections)) ?>">

    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#343c0a]">
                <a href="<?= BASE_URL ?>/admin/pages.php" class="hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    <span>All Pages</span>
                </a>
                <span>/</span>
                <span>Visual Builder</span>
            </div>
            <h1 class="font-headline font-bold text-2xl text-onyx-charcoal mt-1">
                <?= $page ? 'Edit Page: ' . e($page['title']) : 'Create Custom Dynamic Page' ?>
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($page): ?>
                <a href="<?= BASE_URL ?>/page.php?slug=<?= e($page['slug']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 text-xs font-bold transition">
                    <span class="material-symbols-outlined text-base">open_in_new</span>
                    <span>Preview Page</span>
                </a>
            <?php endif; ?>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold transition shadow-md btn-shimmer cursor-pointer">
                <span class="material-symbols-outlined text-base">save</span>
                <span>Save Changes</span>
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="p-4 rounded-xl text-xs font-bold <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left 8 Cols: Hero Banner & Dynamic Block Builder -->
        <div class="lg:col-span-8 space-y-6">

            <!-- 1. Page Title & Slug Card -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
                <h3 class="font-headline font-bold text-base text-onyx-charcoal border-b border-stone-100 pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#343c0a]">title</span>
                    <span>Page Title & URL Permalink</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Page Title *</label>
                        <input type="text" name="title" id="page_title" value="<?= e($page['title'] ?? '') ?>" required placeholder="e.g. Sustainability & Green Living" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">URL Slug</label>
                        <div class="flex items-center">
                            <span class="bg-stone-100 border border-r-0 border-stone-300 rounded-l-lg px-2.5 py-2.5 text-[11px] text-stone-500 font-mono">/page.php?slug=</span>
                            <input type="text" name="slug" value="<?= e($page['slug'] ?? '') ?>" placeholder="sustainability" class="w-full border border-stone-300 rounded-r-lg p-2.5 text-xs font-mono focus:ring-2 focus:ring-[#343c0a]">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Hero Banner Settings Card -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
                <h3 class="font-headline font-bold text-base text-onyx-charcoal border-b border-stone-100 pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#343c0a]">add_photo_alternate</span>
                    <span>Page Hero Banner Header</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Badge Slogan</label>
                        <input type="text" name="hero_badge" value="<?= e($page['hero_badge'] ?? '') ?>" placeholder="e.g. Environmental Commitment" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Headline Title</label>
                        <input type="text" name="hero_title" value="<?= e($page['hero_title'] ?? '') ?>" placeholder="e.g. Eco-Luxury & Green Practices" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Subtitle Description</label>
                    <textarea name="hero_subtitle" rows="2" placeholder="Brief narrative paragraph under headline..." class="w-full border border-stone-300 rounded-lg p-2 text-xs"><?= e($page['hero_subtitle'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <?= render_image_uploader_field('hero_image', $page['hero_image'] ?? '', 'Hero Background Image', 'general', ['required' => false]) ?>
                    </div>
                    <div>
                        <?= render_video_uploader_field('hero_video', $page['hero_video'] ?? '', 'Hero Ambient Video Loop', 'videos', ['required' => false]) ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Banner Height Preset</label>
                        <select name="hero_height" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                            <option value="compact" <?= ($page['hero_height'] ?? '') === 'compact' ? 'selected' : '' ?>>Compact (35vh)</option>
                            <option value="medium" <?= ($page['hero_height'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium Standard (50vh)</option>
                            <option value="tall" <?= ($page['hero_height'] ?? '') === 'tall' ? 'selected' : '' ?>>Tall (70vh)</option>
                            <option value="fullscreen" <?= ($page['hero_height'] ?? '') === 'fullscreen' ? 'selected' : '' ?>>Fullscreen (100vh)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Dark Overlay Opacity</label>
                        <select name="hero_overlay" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                            <option value="none" <?= ($page['hero_overlay'] ?? '') === 'none' ? 'selected' : '' ?>>Minimal Tint (15%)</option>
                            <option value="light" <?= ($page['hero_overlay'] ?? '') === 'light' ? 'selected' : '' ?>>Light Overlay (35%)</option>
                            <option value="medium" <?= ($page['hero_overlay'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium Standard (60%)</option>
                            <option value="dark" <?= ($page['hero_overlay'] ?? '') === 'dark' ? 'selected' : '' ?>>High Contrast Dark (80%)</option>
                            <option value="deep" <?= ($page['hero_overlay'] ?? '') === 'deep' ? 'selected' : '' ?>>Deep Blackout (95%)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Dynamic Section Building Blocks Engine -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#343c0a]">view_quilt</span>
                        <span>Content Building Blocks</span>
                    </h3>

                    <!-- Add Block Dropdown -->
                    <div class="flex items-center gap-2">
                        <select id="add-block-type-select" class="border border-stone-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold">
                            <option value="rich_text">📝 Rich Text / HTML</option>
                            <option value="features_grid">🎨 Feature Highlights Grid</option>
                            <option value="experience_banners">✨ Experience Feature Banners</option>
                            <option value="value_pillars">💎 Core Value Pillars</option>
                            <option value="kpi_stats">📊 KPI Statistics Counters</option>
                            <option value="gallery">🖼️ Photo Gallery & Lightbox</option>
                            <option value="cta">📢 Call-to-Action Banner</option>
                            <option value="testimonials">💬 Testimonials & Reviews</option>
                            <option value="faq">❓ FAQ Accordion List</option>
                        </select>
                        <button type="button" onclick="addNewSectionBlock()" class="px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold transition cursor-pointer flex items-center gap-1 shadow-2xs">
                            <span class="material-symbols-outlined text-sm">add</span>
                            <span>Add Block</span>
                        </button>
                    </div>
                </div>

                <!-- Active Blocks Container -->
                <div id="section-blocks-container" class="space-y-4 pt-2">
                    <!-- Dynamic Blocks Injected Via JS -->
                </div>
            </div>

        </div>

        <!-- Right 4 Cols: Theme Customizer & Publishing Settings -->
        <div class="lg:col-span-4 space-y-6">

            <!-- Theme Customizer & Palette Selector -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#343c0a]">palette</span>
                        <span>Page Theme & Styling</span>
                    </h3>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Select Theme Style</label>
                    <select name="theme_id" id="theme_id_select" onchange="updateThemePreviewCard()" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                        <?php foreach ($availableThemes as $tSlug => $tData): ?>
                            <option value="<?= e($tSlug) ?>" <?= ($page['theme_id'] ?? 'default') === $tSlug ? 'selected' : '' ?>>
                                <?= e($tData['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <!-- Live Theme Card Preview -->
                <div id="theme-card-preview-box" class="p-4 rounded-xl border border-stone-200 bg-stone-50 space-y-3">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Page Meta & SEO Settings Card -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
                <h3 class="font-headline font-bold text-base text-onyx-charcoal border-b border-stone-100 pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#343c0a]">search</span>
                    <span>SEO & Publishing</span>
                </h3>

                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Publication Status</label>
                    <select name="status" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold">
                        <option value="published" <?= ($page['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published (Live on site)</option>
                        <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">SEO Meta Title</label>
                    <input type="text" name="meta_title" value="<?= e($page['meta_title'] ?? '') ?>" placeholder="e.g. Green Living | Indra Hotel" class="w-full border border-stone-300 rounded-lg p-2 text-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">SEO Meta Description</label>
                    <textarea name="meta_description" rows="3" placeholder="Search engine description preview..." class="w-full border border-stone-300 rounded-lg p-2 text-xs"><?= e($page['meta_description'] ?? '') ?></textarea>
                </div>

                <div class="space-y-2 pt-3 border-t border-stone-100">
                    <label class="block text-xs font-bold text-stone-700 uppercase">Navigation Visibility</label>
                    <label class="flex items-center gap-2 text-xs font-medium text-stone-700 cursor-pointer">
                        <input type="checkbox" name="is_in_nav" value="1" <?= (!empty($page['is_in_nav'])) ? 'checked' : '' ?> class="rounded text-[#343c0a]">
                        <span>Show in Top Header Navigation</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-stone-700 cursor-pointer">
                        <input type="checkbox" name="is_in_footer" value="1" <?= (!empty($page['is_in_footer'])) ? 'checked' : '' ?> class="rounded text-[#343c0a]">
                        <span>Show in Footer Navigation Links</span>
                    </label>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
let availableThemesData = <?= json_encode($availableThemes) ?>;
let activeSections = <?= json_encode($existingSections) ?>;

function renderAllSectionBlocks() {
    const container = document.getElementById('section-blocks-container');
    container.innerHTML = '';

    if (!activeSections || activeSections.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center border-2 border-dashed border-stone-200 rounded-xl text-stone-400">
                <span class="material-symbols-outlined text-3xl">post_add</span>
                <p class="text-xs font-bold text-stone-600 mt-1">No content building blocks added yet.</p>
                <p class="text-[11px]">Select a block type above and click "Add Block" to construct your page.</p>
            </div>
        `;
        return;
    }

    activeSections.forEach((block, index) => {
        const card = document.createElement('div');
        card.className = 'bg-stone-50 border border-stone-200 rounded-xl p-4 space-y-3 relative group';
        
        let blockTitle = block.type.replace('_', ' ').toUpperCase();
        let fieldsHTML = '';

        if (block.type === 'rich_text') {
            fieldsHTML = `
                <div class="space-y-2">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Section Title (Optional)" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <input type="text" value="${escapeHtml(block.subtitle || '')}" oninput="activeSections[${index}].subtitle = this.value; syncSectionsJSON();" placeholder="Section Subtitle (Optional)" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    <textarea rows="5" oninput="activeSections[${index}].content = this.value; syncSectionsJSON();" placeholder="Enter HTML content or paragraph text..." class="w-full border border-stone-300 rounded-lg p-2 text-xs font-mono bg-white">${escapeHtml(block.content || '')}</textarea>
                </div>
            `;
        } else if (block.type === 'cta') {
            fieldsHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="CTA Headline Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <input type="text" value="${escapeHtml(block.subtitle || '')}" oninput="activeSections[${index}].subtitle = this.value; syncSectionsJSON();" placeholder="CTA Subtitle Narrative" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    <input type="text" value="${escapeHtml(block.btn_text || '')}" oninput="activeSections[${index}].btn_text = this.value; syncSectionsJSON();" placeholder="Button Text (e.g. Contact Us)" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    <input type="text" value="${escapeHtml(block.btn_link || '')}" oninput="activeSections[${index}].btn_link = this.value; syncSectionsJSON();" placeholder="Button Link URL (/contact.php)" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    <div class="sm:col-span-2 space-y-1 bg-white p-2.5 rounded-lg border border-stone-200">
                        <label class="block text-[11px] font-bold text-stone-600 uppercase">Background Image</label>
                        <div class="flex items-center gap-2">
                            <input type="text" value="${escapeHtml(block.image || '')}" oninput="activeSections[${index}].image = this.value; syncSectionsJSON();" placeholder="Image URL (https://...)" class="w-full border border-stone-300 rounded p-1.5 text-xs font-mono">
                            <button type="button" onclick="triggerBlockFileUpload(this.previousElementSibling, url => { activeSections[${index}].image = url; syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-sm">cloud_upload</span>
                                <span>Upload</span>
                            </button>
                            <button type="button" onclick="triggerBlockMediaPicker(this.previousElementSibling.previousElementSibling, url => { activeSections[${index}].image = url; syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 border border-stone-300 bg-stone-50 hover:bg-stone-100 text-stone-700 text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-sm text-[#343c0a]">photo_library</span>
                                <span>Library</span>
                            </button>
                        </div>
                        ${block.image ? `<div class="mt-1 w-24 h-14 rounded border border-stone-200 overflow-hidden bg-stone-100"><img src="${escapeHtml(block.image)}" class="w-full h-full object-cover"></div>` : ''}
                    </div>
                </div>
            `;
        } else if (block.type === 'experience_banners') {
            const banners = Array.isArray(block.banners) ? block.banners : [];
            let itemsHTML = banners.map((b, bIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>Banner #${bIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'banners', ${bIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" value="${escapeHtml(b.badge || '')}" oninput="activeSections[${index}].banners[${bIdx}].badge = this.value; syncSectionsJSON();" placeholder="Badge (e.g. Culinary)" class="border border-stone-300 rounded p-1.5 text-xs font-semibold">
                        <input type="text" value="${escapeHtml(b.title || '')}" oninput="activeSections[${index}].banners[${bIdx}].title = this.value; syncSectionsJSON();" placeholder="Banner Title" class="border border-stone-300 rounded p-1.5 text-xs font-bold sm:col-span-2">
                    </div>
                    <textarea rows="2" oninput="activeSections[${index}].banners[${bIdx}].desc = this.value; syncSectionsJSON();" placeholder="Description narrative..." class="w-full border border-stone-300 rounded p-1.5 text-xs">${escapeHtml(b.desc || '')}</textarea>
                    
                    <div class="space-y-1 bg-stone-50/70 p-2.5 rounded-lg border border-stone-200">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-stone-600 uppercase">Banner Image</label>
                            <select onchange="activeSections[${index}].banners[${bIdx}].image_pos = this.value; syncSectionsJSON();" class="border border-stone-300 rounded p-1 text-[11px] font-bold bg-white">
                                <option value="right" ${(b.image_pos || 'right') === 'right' ? 'selected' : ''}>Text Left / Image Right</option>
                                <option value="left" ${(b.image_pos || '') === 'left' ? 'selected' : ''}>Image Left / Text Right</option>
                                <option value="auto" ${(b.image_pos || '') === 'auto' ? 'selected' : ''}>Auto Alternating</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" value="${escapeHtml(b.image_url || '')}" oninput="activeSections[${index}].banners[${bIdx}].image_url = this.value; syncSectionsJSON();" placeholder="Image URL (https://...)" class="w-full border border-stone-300 rounded p-1.5 text-xs font-mono bg-white">
                            <button type="button" onclick="triggerBlockFileUpload(this.previousElementSibling, url => { activeSections[${index}].banners[${bIdx}].image_url = url; syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-sm">cloud_upload</span>
                                <span>Upload</span>
                            </button>
                            <button type="button" onclick="triggerBlockMediaPicker(this.previousElementSibling.previousElementSibling, url => { activeSections[${index}].banners[${bIdx}].image_url = url; syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-sm text-[#343c0a]">photo_library</span>
                                <span>Library</span>
                            </button>
                        </div>
                        ${b.image_url ? `<div class="mt-1 w-20 h-12 rounded border border-stone-200 overflow-hidden bg-white"><img src="${escapeHtml(b.image_url)}" class="w-full h-full object-cover"></div>` : ''}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" value="${escapeHtml(b.btn_text || '')}" oninput="activeSections[${index}].banners[${bIdx}].btn_text = this.value; syncSectionsJSON();" placeholder="Button Label" class="border border-stone-300 rounded p-1.5 text-xs">
                        <input type="text" value="${escapeHtml(b.btn_url || '')}" oninput="activeSections[${index}].banners[${bIdx}].btn_url = this.value; syncSectionsJSON();" placeholder="Button Destination URL" class="border border-stone-300 rounded p-1.5 text-xs font-mono">
                    </div>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Section Title (e.g. Experience Banners)" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">Experience Feature Banners (${banners.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'banners', {badge:'Culinary', title:'New Feature', desc:'', image_url:'', btn_text:'Explore', btn_url:'/eat-drink', image_pos:'right'})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Banner</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No feature banners added yet. Click "+ Add Banner" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'value_pillars') {
            const pillars = Array.isArray(block.pillars) ? block.pillars : [];
            let itemsHTML = pillars.map((p, pIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>Value Pillar #${pIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'pillars', ${pIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" value="${escapeHtml(p.icon || 'star')}" oninput="activeSections[${index}].pillars[${pIdx}].icon = this.value; syncSectionsJSON();" placeholder="Icon (nature_people)" class="border border-stone-300 rounded p-1.5 text-xs font-mono">
                        <input type="text" value="${escapeHtml(p.title || '')}" oninput="activeSections[${index}].pillars[${pIdx}].title = this.value; syncSectionsJSON();" placeholder="Pillar Title" class="border border-stone-300 rounded p-1.5 text-xs font-bold sm:col-span-2">
                    </div>
                    <textarea rows="2" oninput="activeSections[${index}].pillars[${pIdx}].desc = this.value; syncSectionsJSON();" placeholder="Pillar Description narrative..." class="w-full border border-stone-300 rounded p-1.5 text-xs">${escapeHtml(p.desc || '')}</textarea>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Section Headline Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                        <input type="text" value="${escapeHtml(block.subtitle || '')}" oninput="activeSections[${index}].subtitle = this.value; syncSectionsJSON();" placeholder="Section Sub-Badge (Our Core Values)" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">Core Value Pillars (${pillars.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'pillars', {icon:'star', title:'New Value', desc:''})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Pillar</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No pillars added yet. Click "+ Add Pillar" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'kpi_stats') {
            const stats = Array.isArray(block.stats) ? block.stats : [];
            let itemsHTML = stats.map((s, sIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>Stat Counter #${sIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'stats', ${sIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" value="${escapeHtml(s.val || '')}" oninput="activeSections[${index}].stats[${sIdx}].val = this.value; syncSectionsJSON();" placeholder="Target (12)" class="border border-stone-300 rounded p-1.5 text-xs font-bold">
                        <input type="text" value="${escapeHtml(s.suffix || '')}" oninput="activeSections[${index}].stats[${sIdx}].suffix = this.value; syncSectionsJSON();" placeholder="Suffix (Suites)" class="border border-stone-300 rounded p-1.5 text-xs">
                        <input type="text" value="${escapeHtml(s.label || '')}" oninput="activeSections[${index}].stats[${sIdx}].label = this.value; syncSectionsJSON();" placeholder="Label Description" class="border border-stone-300 rounded p-1.5 text-xs text-stone-600">
                    </div>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Section Title (e.g. Key Accomplishments)" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">KPI Counter Items (${stats.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'stats', {val:'100', suffix:'%', label:'Satisfaction'})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Stat</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No stat counters added yet. Click "+ Add Stat" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'features_grid') {
            const items = Array.isArray(block.items) ? block.items : [];
            let itemsHTML = items.map((it, itIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>Feature Item #${itIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'items', ${itIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" value="${escapeHtml(it.icon || 'star')}" oninput="activeSections[${index}].items[${itIdx}].icon = this.value; syncSectionsJSON();" placeholder="Icon (star)" class="border border-stone-300 rounded p-1.5 text-xs font-mono">
                        <input type="text" value="${escapeHtml(it.title || '')}" oninput="activeSections[${index}].items[${itIdx}].title = this.value; syncSectionsJSON();" placeholder="Feature Title" class="border border-stone-300 rounded p-1.5 text-xs font-bold sm:col-span-2">
                    </div>
                    <textarea rows="2" oninput="activeSections[${index}].items[${itIdx}].description = this.value; syncSectionsJSON();" placeholder="Feature Detail Description..." class="w-full border border-stone-300 rounded p-1.5 text-xs">${escapeHtml(it.description || '')}</textarea>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Grid Section Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                        <input type="text" value="${escapeHtml(block.subtitle || '')}" oninput="activeSections[${index}].subtitle = this.value; syncSectionsJSON();" placeholder="Grid Subtitle" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">Grid Items (${items.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'items', {icon:'star', title:'New Highlight', description:''})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Item</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No grid items added yet. Click "+ Add Item" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'gallery') {
            const photos = Array.isArray(block.photos) ? block.photos : [];
            let itemsHTML = photos.map((ph, phIdx) => {
                const url = typeof ph === 'string' ? ph : (ph.url || '');
                const cap = typeof ph === 'object' ? (ph.caption || '') : '';
                return `
                    <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                        <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                            <span>Photo #${phIdx + 1}</span>
                            <button type="button" onclick="removeSubItemFromBlock(${index}, 'photos', ${phIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-stone-600 uppercase">Gallery Photo URL *</label>
                            <div class="flex items-center gap-2">
                                <input type="text" value="${escapeHtml(url)}" oninput="if(typeof activeSections[${index}].photos[${phIdx}] === 'object'){ activeSections[${index}].photos[${phIdx}].url = this.value; } else { activeSections[${index}].photos[${phIdx}] = this.value; } syncSectionsJSON();" placeholder="Image URL (https://...)" class="w-full border border-stone-300 rounded p-1.5 text-xs font-mono">
                                <button type="button" onclick="triggerBlockFileUpload(this.previousElementSibling, newUrl => { if(typeof activeSections[${index}].photos[${phIdx}] === 'object'){ activeSections[${index}].photos[${phIdx}].url = newUrl; } else { activeSections[${index}].photos[${phIdx}] = newUrl; } syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                    <span class="material-symbols-outlined text-sm">cloud_upload</span>
                                    <span>Upload</span>
                                </button>
                                <button type="button" onclick="triggerBlockMediaPicker(this.previousElementSibling.previousElementSibling, newUrl => { if(typeof activeSections[${index}].photos[${phIdx}] === 'object'){ activeSections[${index}].photos[${phIdx}].url = newUrl; } else { activeSections[${index}].photos[${phIdx}] = newUrl; } syncSectionsJSON(); renderAllSectionBlocks(); })" class="px-2.5 py-1.5 border border-stone-300 bg-stone-50 hover:bg-stone-100 text-stone-700 text-xs font-bold rounded flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                    <span class="material-symbols-outlined text-sm text-[#343c0a]">photo_library</span>
                                    <span>Library</span>
                                </button>
                            </div>
                            ${url ? `<div class="mt-1 w-20 h-12 rounded border border-stone-200 overflow-hidden bg-stone-50"><img src="${escapeHtml(url)}" class="w-full h-full object-cover"></div>` : ''}
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-stone-600 mb-1">Caption / Subtitle</label>
                            <input type="text" value="${escapeHtml(cap)}" oninput="if(typeof activeSections[${index}].photos[${phIdx}] !== 'object'){ activeSections[${index}].photos[${phIdx}] = {url: activeSections[${index}].photos[${phIdx}], caption: this.value}; } else { activeSections[${index}].photos[${phIdx}].caption = this.value; } syncSectionsJSON();" placeholder="Caption / Subtitle" class="w-full border border-stone-300 rounded p-1.5 text-xs">
                        </div>
                    </div>
                `;
            }).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Gallery Section Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">Gallery Photos (${photos.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'photos', {url:'', caption:''})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Photo</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No photos added yet. Click "+ Add Photo" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'testimonials') {
            const reviews = Array.isArray(block.reviews) ? block.reviews : [];
            let itemsHTML = reviews.map((r, rIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>Review #${rIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'reviews', ${rIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <textarea rows="2" oninput="activeSections[${index}].reviews[${rIdx}].quote = this.value; syncSectionsJSON();" placeholder="Guest quote narrative..." class="w-full border border-stone-300 rounded p-1.5 text-xs">${escapeHtml(r.quote || '')}</textarea>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" value="${escapeHtml(r.author || '')}" oninput="activeSections[${index}].reviews[${rIdx}].author = this.value; syncSectionsJSON();" placeholder="Guest Author Name" class="border border-stone-300 rounded p-1.5 text-xs font-bold">
                        <input type="text" value="${escapeHtml(r.location || '')}" oninput="activeSections[${index}].reviews[${rIdx}].location = this.value; syncSectionsJSON();" placeholder="Guest Location / Tag" class="border border-stone-300 rounded p-1.5 text-xs">
                    </div>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Testimonials Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">Guest Reviews (${reviews.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'reviews', {quote:'', author:'', location:'Verified Guest'})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add Review</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No reviews added yet. Click "+ Add Review" above.</p>'}</div>
                </div>
            `;
        } else if (block.type === 'faq') {
            const faqs = Array.isArray(block.faqs) ? block.faqs : [];
            let itemsHTML = faqs.map((f, fIdx) => `
                <div class="p-3 bg-white border border-stone-200 rounded-lg space-y-2 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-[#4B5320]">
                        <span>FAQ Item #${fIdx + 1}</span>
                        <button type="button" onclick="removeSubItemFromBlock(${index}, 'faqs', ${fIdx})" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer font-bold">Remove</button>
                    </div>
                    <input type="text" value="${escapeHtml(f.q || '')}" oninput="activeSections[${index}].faqs[${fIdx}].q = this.value; syncSectionsJSON();" placeholder="Question Title..." class="w-full border border-stone-300 rounded p-1.5 text-xs font-bold">
                    <textarea rows="2" oninput="activeSections[${index}].faqs[${fIdx}].a = this.value; syncSectionsJSON();" placeholder="Answer text..." class="w-full border border-stone-300 rounded p-1.5 text-xs">${escapeHtml(f.a || '')}</textarea>
                </div>
            `).join('');

            fieldsHTML = `
                <div class="space-y-3">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="FAQ Section Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <div class="flex items-center justify-between pt-1 border-t border-stone-200/80">
                        <span class="text-xs font-bold text-stone-700">FAQ Items (${faqs.length})</span>
                        <button type="button" onclick="addSubItemToBlock(${index}, 'faqs', {q:'', a:''})" class="px-2.5 py-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold rounded-lg cursor-pointer transition">+ Add FAQ Item</button>
                    </div>
                    <div class="space-y-2">${itemsHTML || '<p class="text-xs text-stone-400 italic">No FAQ items added yet. Click "+ Add FAQ Item" above.</p>'}</div>
                </div>
            `;
        } else {
            fieldsHTML = `
                <div class="space-y-2">
                    <input type="text" value="${escapeHtml(block.title || '')}" oninput="activeSections[${index}].title = this.value; syncSectionsJSON();" placeholder="Section Title" class="w-full border border-stone-300 rounded-lg p-2 text-xs font-bold bg-white">
                    <input type="text" value="${escapeHtml(block.subtitle || '')}" oninput="activeSections[${index}].subtitle = this.value; syncSectionsJSON();" placeholder="Section Subtitle" class="w-full border border-stone-300 rounded-lg p-2 text-xs bg-white">
                </div>
            `;
        }

        card.innerHTML = `
            <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-[#343c0a] flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">view_compact</span>
                    <span>Block ${index + 1}: ${blockTitle}</span>
                </span>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="moveBlock(${index}, -1)" ${index === 0 ? 'disabled' : ''} class="p-1 rounded text-stone-400 hover:text-stone-700 disabled:opacity-30">
                        <span class="material-symbols-outlined text-base">arrow_upward</span>
                    </button>
                    <button type="button" onclick="moveBlock(${index}, 1)" ${index === activeSections.length - 1 ? 'disabled' : ''} class="p-1 rounded text-stone-400 hover:text-stone-700 disabled:opacity-30">
                        <span class="material-symbols-outlined text-base">arrow_downward</span>
                    </button>
                    <button type="button" onclick="removeBlock(${index})" class="p-1 rounded text-rose-500 hover:bg-rose-50">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </div>
            </div>
            ${fieldsHTML}
        `;
        container.appendChild(card);
    });
}

function addNewSectionBlock() {
    const select = document.getElementById('add-block-type-select');
    const type = select.value;
    let newBlock = { type: type, title: '', subtitle: '' };

    if (type === 'rich_text') {
        newBlock.content = '<p>Enter your content narrative here...</p>';
    } else if (type === 'cta') {
        newBlock.title = 'Ready to Reserve Your Stay?';
        newBlock.subtitle = 'Experience personalized Cambodian luxury at Indra Hotel.';
        newBlock.btn_text = 'Book Now';
        newBlock.btn_link = '<?= BASE_URL ?>/rooms.php';
    } else if (type === 'features_grid') {
        newBlock.title = 'Key Features & Amenities';
        newBlock.columns = 3;
        newBlock.items = [
            { title: 'Feature 1', description: 'Description detail text...', icon: 'star' },
            { title: 'Feature 2', description: 'Description detail text...', icon: 'park' },
            { title: 'Feature 3', description: 'Description detail text...', icon: 'pool' }
        ];
    } else if (type === 'gallery') {
        newBlock.title = 'Photo Showcase';
        newBlock.photos = [
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80'
        ];
    } else if (type === 'testimonials') {
        newBlock.title = 'Guest Testimonials';
        newBlock.reviews = [
            { quote: 'Exceptional hospitality and peaceful ambiance.', author: 'Sarah Jenkins', location: 'Singapore' }
        ];
    } else if (type === 'faq') {
        newBlock.title = 'Frequently Asked Questions';
        newBlock.faqs = [
            { q: 'What is the check-in time?', a: 'Standard check-in is from 2:00 PM.' }
        ];
    } else if (type === 'experience_banners') {
        newBlock.title = 'Experience Feature Banners';
        newBlock.banners = [
            { badge: 'Culinary Journey', title: 'The Bistro Cafe', desc: 'Indulge in artisanal dishes.', image_url: 'https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?auto=format&fit=crop&w=800&q=80', btn_text: 'Explore Dining', btn_url: '/eat-drink', image_pos: 'right' },
            { badge: 'Health & Vitality', title: 'Fitness Center & Spa', desc: 'Serene outdoor saltwater pool.', image_url: 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80', btn_text: 'Discover Spa', btn_url: '/wellness', image_pos: 'left' }
        ];
    } else if (type === 'value_pillars') {
        newBlock.title = 'Crafted for Discerning Travelers';
        newBlock.subtitle = 'Our Core Values';
        newBlock.pillars = [
            { icon: 'nature_people', title: 'Tranquil Urban Oasis', desc: 'Designed with lush tropical foliage.' },
            { icon: 'restaurant', title: 'Artisan Culinary Flavors', desc: 'Organic Cambodian specialty coffee.' },
            { icon: 'loyalty', title: 'Personalized Concierge', desc: 'Tailored itineraries across Phnom Penh.' }
        ];
    } else if (type === 'kpi_stats') {
        newBlock.title = 'Key Statistics & Accomplishments';
        newBlock.stats = [
            { val: '12', label: 'Luxury Suites' },
            { val: '100%', label: 'Saltwater Pool' },
            { val: '24/7', label: 'Front Desk Concierge' },
            { val: '4.9★', label: 'Guest Satisfaction' }
        ];
    }

    activeSections.push(newBlock);
    syncSectionsJSON();
    renderAllSectionBlocks();
}

function removeBlock(index) {
    activeSections.splice(index, 1);
    syncSectionsJSON();
    renderAllSectionBlocks();
}

function moveBlock(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= activeSections.length) return;
    const temp = activeSections[index];
    activeSections[index] = activeSections[target];
    activeSections[target] = temp;
    syncSectionsJSON();
    renderAllSectionBlocks();
}

function addSubItemToBlock(blockIdx, arrayKey, defaultObj) {
    if (!activeSections[blockIdx]) return;
    if (!Array.isArray(activeSections[blockIdx][arrayKey])) {
        activeSections[blockIdx][arrayKey] = [];
    }
    activeSections[blockIdx][arrayKey].push(defaultObj);
    syncSectionsJSON();
    renderAllSectionBlocks();
}

function removeSubItemFromBlock(blockIdx, arrayKey, itemIdx) {
    if (!activeSections[blockIdx] || !Array.isArray(activeSections[blockIdx][arrayKey])) return;
    activeSections[blockIdx][arrayKey].splice(itemIdx, 1);
    syncSectionsJSON();
    renderAllSectionBlocks();
}

function syncSectionsJSON() {
    document.getElementById('input_sections_json').value = JSON.stringify(activeSections);
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function updateThemePreviewCard() {
    const select = document.getElementById('theme_id_select');
    const slug = select.value;
    const t = availableThemesData[slug] || availableThemesData['default'];
    const box = document.getElementById('theme-card-preview-box');

    box.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 border border-stone-200">
                <img src="${t.preview_thumbnail || ''}" class="w-full h-full object-cover">
            </div>
            <div>
                <h4 class="font-bold text-xs text-onyx-charcoal">${t.name}</h4>
                <p class="text-[10px] text-stone-400">${t.author || 'Design Studio'}</p>
            </div>
        </div>
        <p class="text-[11px] text-stone-500 leading-relaxed">${t.description}</p>
        <div class="flex items-center gap-2 pt-1 border-t border-stone-200">
            <span class="text-[10px] font-bold text-stone-400">Palette:</span>
            <span class="w-4 h-4 rounded-full inline-block border border-stone-300" style="background-color: ${t.colors.primary}"></span>
            <span class="w-4 h-4 rounded-full inline-block border border-stone-300" style="background-color: ${t.colors.accent}"></span>
            <span class="w-4 h-4 rounded-full inline-block border border-stone-300" style="background-color: ${t.colors.dark}"></span>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    renderAllSectionBlocks();
    updateThemePreviewCard();
});

let currentPickerCallback = null;

function triggerBlockFileUpload(inputEl, onComplete) {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*,.svg,.ico,.webp,.gif';
    fileInput.onchange = e => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder', 'pages');

        fetch(BASE_URL + '/api/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.url) {
                if (inputEl) {
                    inputEl.value = data.url;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (typeof onComplete === 'function') onComplete(data.url);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Upload error: ' + err.message);
        });
    };
    fileInput.click();
}

function triggerBlockMediaPicker(inputEl, onComplete) {
    openPbMediaPicker(url => {
        if (inputEl) {
            inputEl.value = url;
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (typeof onComplete === 'function') onComplete(url);
    });
}

function openPbMediaPicker(onSelectCallback) {
    currentPickerCallback = onSelectCallback;
    const modal = document.getElementById('pb-media-picker-modal');
    const grid = document.getElementById('pb-media-picker-grid');
    if (!modal || !grid) return;

    modal.classList.remove('hidden');
    grid.innerHTML = '<div class="col-span-full text-center py-12 text-stone-400 font-bold text-xs"><span class="material-symbols-outlined animate-spin text-2xl">sync</span><p class="mt-1">Loading media files...</p></div>';

    fetch(BASE_URL + '/api/media-api.php?action=list')
        .then(res => res.json())
        .then(data => {
            const files = Array.isArray(data) ? data : (data.data || []);
            if (files.length === 0) {
                grid.innerHTML = '<div class="col-span-full text-center py-12 text-stone-400 font-bold text-xs">No media files found in library. Use "Upload" button to add photos.</div>';
                return;
            }
            grid.innerHTML = files.map(item => `
                <div onclick="selectMediaItem('${escapeHtml(item.url)}')" class="aspect-square bg-stone-100 rounded-lg overflow-hidden border-2 border-transparent hover:border-[#343c0a] cursor-pointer group relative shadow-2xs">
                    <img src="${escapeHtml(item.url)}" class="w-full h-full object-cover group-hover:scale-105 transition duration-200" alt="${escapeHtml(item.original_name || 'Media')}">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold gap-1">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span>Select</span>
                    </div>
                </div>
            `).join('');
        })
        .catch(err => {
            grid.innerHTML = '<div class="col-span-full text-center py-12 text-rose-500 font-bold text-xs">Failed to load media library: ' + escapeHtml(err.message) + '</div>';
        });
}

function selectMediaItem(url) {
    if (typeof currentPickerCallback === 'function') {
        currentPickerCallback(url);
    }
    closePbMediaPicker();
}

function closePbMediaPicker() {
    const modal = document.getElementById('pb-media-picker-modal');
    if (modal) modal.classList.add('hidden');
    currentPickerCallback = null;
}
</script>

<!-- Centralized Media Library Picker Modal -->
<div id="pb-media-picker-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
        <div class="p-4 border-b border-stone-200 flex items-center justify-between bg-stone-50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#343c0a]">photo_library</span>
                <h3 class="font-bold text-sm text-onyx-charcoal">Select Image from Media Library</h3>
            </div>
            <button type="button" onclick="closePbMediaPicker()" class="p-1 rounded text-stone-400 hover:text-stone-700">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
        <div class="p-4 flex-1 overflow-y-auto min-h-[300px]">
            <div id="pb-media-picker-grid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3">
                <div class="col-span-full text-center py-12 text-stone-400 font-bold text-xs">Loading media library...</div>
            </div>
        </div>
        <div class="p-4 border-t border-stone-200 bg-stone-50 flex items-center justify-between text-xs text-stone-500">
            <span>Click any image to choose it for your content block.</span>
            <button type="button" onclick="closePbMediaPicker()" class="px-4 py-2 rounded-lg bg-stone-200 font-bold text-stone-700 hover:bg-stone-300">Cancel</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
