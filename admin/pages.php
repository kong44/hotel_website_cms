<?php
/**
 * Indra Hotel - Dynamic Custom Pages CMS Manager
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/theme-engine.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = getDB();

$message = '';
$messageType = '';

// Handle Actions: Delete, Toggle Status, Toggle Nav
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (Auth::verifyCsrf($csrf)) {
        if ($action === 'delete') {
            $pageId = (int)($_POST['page_id'] ?? 0);
            if ($pageId > 0) {
                $stmt = $pdo->prepare("DELETE FROM custom_pages WHERE id = ?");
                $stmt->execute([$pageId]);
                $message = 'Dynamic page deleted successfully.';
                $messageType = 'success';
            }
        } elseif ($action === 'toggle_status') {
            $pageId = (int)($_POST['page_id'] ?? 0);
            $newStatus = ($_POST['status'] ?? 'published') === 'published' ? 'draft' : 'published';
            $stmt = $pdo->prepare("UPDATE custom_pages SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $pageId]);
            $message = 'Page status updated to ' . ucfirst($newStatus) . '.';
            $messageType = 'success';
        }
    }
}

// Fetch all custom pages
try {
    $stmt = $pdo->query("SELECT * FROM custom_pages ORDER BY id DESC");
    $pages = $stmt->fetchAll();
} catch (Throwable $e) {
    $pages = [];
}

$availableThemes = get_available_themes();

$adminTitle = 'Custom Dynamic Pages | CMS Admin';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">
    <!-- Header Title Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-2xs">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#343c0a]">
                <span class="material-symbols-outlined text-base">auto_stories</span>
                <span>Content Engine</span>
            </div>
            <h1 class="font-headline font-bold text-2xl text-onyx-charcoal mt-1">Custom Dynamic Pages</h1>
            <p class="text-xs text-stone-500">Create, customize, and manage custom landing pages and theme styles.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/page-builder.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold transition shadow-sm btn-shimmer">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>Create New Page</span>
            </a>
        </div>

    </div>

    <?php if (!empty($message)): ?>
    <div class="p-4 rounded-xl text-xs font-bold <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <!-- Pages List Table / Cards -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-2xs overflow-hidden">
        <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between bg-stone-50/50">
            <h3 class="font-bold text-sm text-onyx-charcoal uppercase tracking-wider">All Dynamic Pages (<?= count($pages) ?>)</h3>
            <span class="text-xs text-stone-400 font-normal">Click "Edit Builder" to modify section blocks & themes</span>
        </div>

        <?php if (empty($pages)): ?>
        <div class="py-16 text-center text-stone-400 space-y-3">
            <span class="material-symbols-outlined text-5xl">note_add</span>
            <p class="text-sm font-bold text-stone-600">No custom pages created yet.</p>
            <p class="text-xs text-stone-400 max-w-sm mx-auto">Create landing pages for sustainability, private dining, wedding packages, or seasonal events.</p>
            <div class="pt-2">
                <a href="<?= BASE_URL ?>/admin/page-builder.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#343c0a] text-white text-xs font-bold">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>Build Your First Page</span>
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-stone-600">
                <thead class="bg-stone-100/70 uppercase tracking-wider font-bold text-stone-500 border-b border-stone-200">
                    <tr>
                        <th class="px-6 py-3.5">Page Title & Slug</th>
                        <th class="px-6 py-3.5">Theme</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Sections</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($pages as $p): 
                        $pTheme = $availableThemes[$p['theme_id']] ?? $availableThemes['default'] ?? null;
                        $sections = !empty($p['sections_json']) ? json_decode($p['sections_json'], true) : [];
                        $secCount = is_array($sections) ? count($sections) : 0;
                    ?>
                    <tr class="hover:bg-stone-50/80 transition">
                        <td class="px-6 py-4">
                            <div class="space-y-0.5">
                                <h4 class="font-bold text-sm text-onyx-charcoal"><?= e($p['title']) ?></h4>
                                <div class="flex items-center gap-2 text-[11px] font-mono text-stone-400">
                                    <span>/page.php?slug=<?= e($p['slug']) ?></span>
                                    <a href="<?= BASE_URL ?>/page.php?slug=<?= e($p['slug']) ?>" target="_blank" class="text-[#343c0a] hover:underline flex items-center gap-0.5">
                                        <span>View</span>
                                        <span class="material-symbols-outlined text-xs">open_in_new</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-stone-100 text-stone-700 border border-stone-200">
                                <span class="w-2 h-2 rounded-full" style="background-color: <?= e($pTheme['colors']['primary'] ?? '#343c0a') ?>"></span>
                                <span><?= e($pTheme['name'] ?? 'Default Theme') ?></span>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <form action="<?= BASE_URL ?>/admin/pages.php" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="status" value="<?= e($p['status']) ?>">
                                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold cursor-pointer transition <?= $p['status'] === 'published' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $p['status'] === 'published' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                                    <span><?= ucfirst($p['status']) ?></span>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 font-semibold text-stone-500">
                            <?= $secCount ?> Content Block<?= $secCount === 1 ? '' : 's' ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= BASE_URL ?>/admin/page-builder.php?id=<?= (int)$p['id'] ?>" class="px-3 py-1.5 rounded-lg bg-stone-100 hover:bg-[#dfe8a6]/40 text-stone-800 text-xs font-bold transition inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-[#343c0a]">edit_note</span>
                                    <span>Edit Builder</span>
                                </a>

                                <form action="<?= BASE_URL ?>/admin/pages.php" method="POST" class="inline" onsubmit="return confirm('Delete <?= e(addslashes($p['title'])) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition cursor-pointer" title="Delete Page">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
