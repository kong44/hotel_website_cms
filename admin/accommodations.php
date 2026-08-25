<?php
/**
 * Indra Hotel - Accommodations Manager CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Accommodations Inventory';

// Handle POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['hero_page_keys']) || isset($_POST['save_hero_settings'])) {
        process_hero_settings_post();
        set_flash('success', 'Rooms & Accommodations page hero updated.');
        header('Location: ' . BASE_URL . '/admin/accommodations.php');
        exit;
    }
}

// Handle Actions (Toggle status / Delete)
$action = $_GET['action'] ?? '';

$roomId = (int)($_GET['id'] ?? 0);

if ($action === 'toggle' && $roomId > 0) {
    $stmt = $pdo->prepare("SELECT status FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $curr = $stmt->fetch();
    if ($curr) {
        $newStatus = ($curr['status'] === 'available') ? 'maintenance' : 'available';
        $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ?")->execute([$newStatus, $roomId]);
        set_flash('success', "Room status updated to '{$newStatus}'.");
    }
    header('Location: ' . BASE_URL . '/admin/accommodations.php');
    exit;
} elseif ($action === 'delete' && $roomId > 0) {
    $pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$roomId]);
    set_flash('success', "Accommodation deleted from inventory.");
    header('Location: ' . BASE_URL . '/admin/accommodations.php');
    exit;
}

// Category filter
$selectedCategory = trim($_GET['category'] ?? 'all');
$availableTypes = get_all_room_types();

if ($selectedCategory !== 'all' && !empty($selectedCategory)) {
    $stmtRooms = $pdo->prepare("SELECT * FROM rooms WHERE category = ? ORDER BY display_order ASC, id ASC");
    $stmtRooms->execute([$selectedCategory]);
} else {
    $stmtRooms = $pdo->query("SELECT * FROM rooms ORDER BY display_order ASC, id ASC");
}
$rooms = $stmtRooms->fetchAll();

// Total count
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Accommodations</span>
                <span class="text-xs text-stone-400">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Inventory</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Room & Suite Inventory</h1>
            <p class="text-xs text-stone-500 mt-1">Manage suite categories, configurations, pricing, and live availability.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/room-types.php" class="px-4 py-2.5 rounded-lg border border-stone-300 text-stone-700 text-xs font-semibold hover:bg-stone-50 transition flex items-center gap-1.5 shadow-2xs">
                <span class="material-symbols-outlined text-base">category</span>
                <span><?= __t('admin_nav_room_types', 'Manage Room Types') ?></span>
            </a>
            <a href="<?= BASE_URL ?>/admin/room-form.php" class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-base">add</span>
                <span><?= __t('admin_action_new_room', 'Add Accommodation') ?></span>
            </a>
        </div>
    </div>

    <!-- Page Heroes Editor Collapsible Card -->
    <details class="bg-stone-900 text-white rounded-2xl border border-stone-800 shadow-md overflow-hidden">
        <summary class="px-6 py-4 font-bold text-xs uppercase tracking-wider text-[#dfe8a6] cursor-pointer flex items-center justify-between hover:bg-stone-800 transition">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base">view_day</span>
                <span>Edit Rooms & Suites Page Hero Banner</span>
            </div>
            <span class="text-[10px] text-stone-400 font-normal">Click to expand / collapse</span>
        </summary>
        <div class="p-6 bg-stone-50 text-stone-900 border-t border-stone-800 space-y-6">
            <form action="<?= BASE_URL ?>/admin/accommodations.php" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                <?= render_admin_hero_editor_card('rooms', 'Rooms & Suites Catalog', [
                    'badge' => 'Contemporary Sanctuary',
                    'title' => 'Rooms, Suites & Spaces',
                    'subtitle' => 'Thoughtfully designed accommodations, luxury suites, and executive conference spaces catering to discerning travelers.',
                    'image' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1600&q=80'
                ]) ?>
                <div class="flex justify-end">
                    <button type="submit" name="save_hero_settings" value="1" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded-lg text-xs font-bold transition shadow flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span><?= __t('admin_btn_save', 'Save Changes') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </details>


    <!-- Category Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2 pb-2">
        <a href="<?= BASE_URL ?>/admin/accommodations.php" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= ($selectedCategory === 'all') ? 'bg-[#343c0a] text-white shadow-xs' : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-50' ?>">
            <span>All Types</span>
            <span class="text-[10px] opacity-80 bg-black/20 px-1.5 py-0.5 rounded-full"><?= $totalCount ?></span>
        </a>

        <?php foreach ($availableTypes as $rt): 
            $isActive = (strcasecmp($selectedCategory, $rt['name']) === 0);
        ?>
        <a href="<?= BASE_URL ?>/admin/accommodations.php?category=<?= urlencode($rt['name']) ?>" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $isActive ? 'bg-[#343c0a] text-white shadow-xs' : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-50' ?>">
            <span class="material-symbols-outlined text-sm"><?= e($rt['icon']) ?></span>
            <span><?= e(__td($rt, 'name', $rt['name'])) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Accommodations Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                    <tr>
                        <th class="py-4 px-6 font-semibold"><?= __t('admin_col_room', 'Room & Type') ?></th>
                        <th class="py-4 px-6 font-semibold"><?= __t('rooms_capacity', 'Bed & Capacity') ?></th>
                        <th class="py-4 px-6 font-semibold"><?= __t('rooms_size', 'Size') ?> & <?= __t('rooms_view', 'View') ?></th>
                        <th class="py-4 px-6 font-semibold"><?= __t('admin_col_amount', 'Rate') ?> / <?= __t('rooms_per_night', 'night') ?></th>
                        <th class="py-4 px-6 font-semibold"><?= __t('admin_col_status', 'Availability') ?></th>
                        <th class="py-4 px-6 font-semibold text-right"><?= __t('admin_col_actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php if (!empty($rooms)): ?>
                        <?php foreach ($rooms as $room): ?>
                        <tr class="hover:bg-stone-50 transition">
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-16 h-12 rounded-lg overflow-hidden border border-stone-200 shrink-0 bg-stone-100">
                                        <img src="<?= e($room['image_url']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                                    </div>
                                    <div class="space-y-1">
                                        <div class="font-headline font-bold text-sm text-stone-900"><?= e(__td($room, 'name', $room['name'])) ?></div>
                                        <div class="flex items-center gap-2">
                                            <?= get_room_type_badge($room['category'] ?? 'Deluxe Room', 'xs') ?>
                                            <span class="text-[10px] text-stone-400 font-mono">/<?= e($room['slug']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-stone-700">
                                <div class="font-semibold"><?= e($room['bed_type']) ?></div>
                                <div class="text-[11px] text-stone-500"><?= (int)$room['capacity_adults'] ?> Adults, <?= (int)$room['capacity_children'] ?> Child</div>
                            </td>

                            <td class="py-4 px-6 text-stone-700">
                                <div class="font-semibold"><?= $room['size_sqm'] ?> m²</div>
                                <div class="text-[11px] text-stone-500"><?= e($room['view_type']) ?></div>
                            </td>

                            <td class="py-4 px-6">
                                <span class="font-headline font-bold text-sm text-onyx-charcoal"><?= format_price($room['price_per_night']) ?></span>
                            </td>

                            <td class="py-4 px-6">
                                <?php if ($room['status'] === 'available'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Available</span>
                                    </span>
                                <?php elseif ($room['status'] === 'booked'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        <span>Booked</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-stone-100 text-stone-600 border border-stone-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-stone-400"></span>
                                        <span>Maintenance</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="<?= BASE_URL ?>/admin/room-form.php?id=<?= (int)$room['id'] ?>" 
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-[#343c0a] text-stone-700 hover:text-white text-xs font-bold transition shadow-2xs cursor-pointer" title="Edit Accommodation">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                        <span><?= __t('admin_btn_edit', 'Edit') ?></span>
                                    </a>
                                    <a href="<?= BASE_URL ?>/room.php?slug=<?= e($room['slug']) ?>" target="_blank" 
                                       class="p-1.5 text-stone-400 hover:text-stone-700 rounded-lg hover:bg-stone-100 transition" title="Preview Public Page">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/accommodations.php?action=toggle&id=<?= (int)$room['id'] ?>" 
                                       class="p-1.5 text-stone-400 hover:text-stone-700 rounded-lg hover:bg-stone-100 transition" title="Toggle Maintenance Mode">
                                        <span class="material-symbols-outlined text-base">sync</span>
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/accommodations.php?action=delete&id=<?= (int)$room['id'] ?>" 
                                       onclick="return confirm('Delete <?= e(addslashes($room['name'])) ?> from inventory?');"
                                       class="p-1.5 text-stone-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition" title="Delete Accommodation">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-stone-400">
                                <span class="material-symbols-outlined text-4xl block mb-2">hotel</span>
                                <span>No accommodations found in this category.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
