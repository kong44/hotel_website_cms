<?php
/**
 * Indra Hotel - Public Dynamic Custom Page Router & Renderer
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/theme-engine.php';

$pdo = getDB();

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: ' . BASE_URL . '/home');
    exit;
}

// Fetch Page Record
$page = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
} catch (Throwable $e) {}

if (!$page) {
    // 404 Fallback
    header('HTTP/1.1 404 Not Found');
    $pageMeta = [
        'title' => 'Page Not Found | ' . hotel_name(),
        'description' => 'The requested page could not be found.',
        'type' => 'website'
    ];
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="py-24 bg-stone-50 text-center">
        <div class="max-w-md mx-auto px-4 space-y-4">
            <span class="material-symbols-outlined text-6xl text-stone-300">error_outline</span>
            <h1 class="font-headline font-bold text-3xl text-onyx-charcoal">404 - Page Not Found</h1>
            <p class="text-xs text-stone-500">The custom page you are looking for does not exist or has been unpublished.</p>
            <div>
                <a href="<?= BASE_URL ?>/home" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#343c0a] text-white text-xs font-bold">
                    <span class="material-symbols-outlined text-sm">home</span>
                    <span>Return to Home</span>
                </a>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Resolve Page Theme & Styles
$activeTheme = get_active_theme_for_page($page['theme_id'] ?? 'default');
$themeCss = render_theme_css_styles($activeTheme);

$pageMeta = [
    'title' => (!empty($page['meta_title']) ? $page['meta_title'] : $page['title']) . ' | ' . hotel_name(),
    'description' => !empty($page['meta_description']) ? $page['meta_description'] : ($page['hero_subtitle'] ?? ''),
    'image' => !empty($page['hero_image']) ? $page['hero_image'] : '',
    'type' => 'website'
];

// Decode Sections JSON
$sections = !empty($page['sections_json']) ? json_decode($page['sections_json'], true) : [];
if (!is_array($sections)) $sections = [];

require_once __DIR__ . '/includes/header.php';
echo $themeCss;
?>

<!-- Dynamic Page Hero Header -->
<?php
$heroDefaults = [
    'badge' => !empty($page['hero_badge']) ? $page['hero_badge'] : $page['title'],
    'title' => !empty($page['hero_title']) ? $page['hero_title'] : $page['title'],
    'subtitle' => $page['hero_subtitle'] ?? '',
    'image' => $page['hero_image'] ?? '',
    'video' => $page['hero_video'] ?? '',
    'height' => $page['hero_height'] ?? 'medium',
    'overlay' => $page['hero_overlay'] ?? 'medium'
];

echo render_public_page_hero('custom_' . $page['id'], $heroDefaults);
?>

<!-- Dynamic Building Blocks Loop -->
<div class="dynamic-page-content-wrapper">
    <?php if (empty($sections)): ?>
        <section class="py-16 bg-white text-center">
            <div class="max-w-xl mx-auto px-4 space-y-2">
                <h2 class="font-headline font-bold text-2xl text-onyx-charcoal"><?= e($page['title']) ?></h2>
                <p class="text-xs text-stone-500">Welcome to <?= e($page['title']) ?>. Content for this page is currently being compiled.</p>
            </div>
        </section>
    <?php else: ?>
        <?php foreach ($sections as $block): ?>
            <?= render_dynamic_section_block($block, $activeTheme) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
