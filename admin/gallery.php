<?php
/**
 * Indra Hotel - Gallery & Media CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Photo Gallery Manager';

// Handle Add Photo
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'rooms';
    $imageUrl = trim($_POST['image_url'] ?? '');
    $caption = trim($_POST['caption'] ?? '');

    if (!empty($title) && !empty($imageUrl)) {
        $stmt = $pdo->prepare("INSERT INTO gallery (title, category, image_url, caption, display_order) VALUES (?, ?, ?, ?, 99)");
        $stmt->execute([$title, $category, $imageUrl, $caption]);
        set_flash('success', 'New photograph added to gallery.');
    }
    header('Location: ' . BASE_URL . '/admin/gallery.php');
    exit;
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM gallery WHERE id = ?")->execute([$delId]);
    set_flash('success', 'Photo removed from gallery.');
    header('Location: ' . BASE_URL . '/admin/gallery.php');
    exit;
}

$stmtG = $pdo->query("SELECT * FROM gallery ORDER BY display_order ASC, id DESC");
$photos = $stmtG->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Media & Photo Gallery</h2>
            <p class="text-xs text-stone-500">Manage high-resolution imagery for public showcase.</p>
        </div>
        <button onclick="document.getElementById('add-photo-modal').classList.remove('hidden')" 
                class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">add_photo_alternate</span>
            <span>Add New Photo</span>
        </button>
    </div>

    <!-- Gallery Photos Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($photos as $photo): ?>
        <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden flex flex-col justify-between group">
            <div class="h-48 overflow-hidden relative">
                <img src="<?= e($photo['image_url']) ?>" alt="<?= e($photo['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <div class="absolute top-3 left-3 bg-black/70 backdrop-blur-md text-white text-[10px] font-bold uppercase px-2.5 py-1 rounded">
                    <?= e($photo['category']) ?>
                </div>
            </div>

            <div class="p-4 space-y-1">
                <h3 class="font-headline font-bold text-sm text-onyx-charcoal"><?= e($photo['title']) ?></h3>
                <?php if (!empty($photo['caption'])): ?>
                <p class="text-[11px] text-stone-500 truncate"><?= e($photo['caption']) ?></p>
                <?php endif; ?>
            </div>

            <div class="p-4 pt-0 border-t border-stone-100 flex justify-between items-center text-xs">
                <a href="<?= e($photo['image_url']) ?>" target="_blank" class="text-stone-500 hover:text-stone-900 text-[11px]">View Full Size</a>
                <a href="<?= BASE_URL ?>/admin/gallery.php?action=delete&id=<?= $photo['id'] ?>" onclick="return confirm('Delete this image?')" class="text-rose-600 hover:underline text-[11px] font-semibold">Delete</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Add Photo Modal -->
<div id="add-photo-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-8 border border-stone-200 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <h3 class="font-headline font-bold text-xl text-onyx-charcoal">Add Photo</h3>
            <button onclick="document.getElementById('add-photo-modal').classList.add('hidden')" class="text-stone-400 hover:text-stone-700">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/gallery.php" method="POST" class="space-y-4 text-xs">
            <div>
                <label class="block font-semibold text-stone-700 uppercase mb-1">Photo Title *</label>
                <input type="text" name="title" required placeholder="e.g. Deluxe Room Balcony" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
            </div>

            <div>
                <label class="block font-semibold text-stone-700 uppercase mb-1">Category</label>
                <select name="category" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                    <option value="rooms">Accommodations</option>
                    <option value="dining">Dining & Bistro</option>
                    <option value="wellness">Gym & Pool</option>
                    <option value="exterior">Architecture & Grounds</option>
                    <option value="interior">Interior Details</option>
                </select>
            </div>

            <div>
                <?= render_image_uploader_field('image_url', '', 'Photograph File *', 'gallery', [
                    'required' => true,
                    'helper' => 'Upload photo file or paste URL'
                ]) ?>
            </div>

            <div>
                <label class="block font-semibold text-stone-700 uppercase mb-1">Caption</label>
                <input type="text" name="caption" placeholder="Short description..." class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
            </div>

            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('add-photo-modal').classList.add('hidden')" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Add Photo</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
