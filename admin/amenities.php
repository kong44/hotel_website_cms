<?php
/**
 * Indra Hotel - Master Room Amenities & Features CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'Room Amenities Manager';

// Handle Add / Edit / Delete POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/amenities.php');
        exit;
    }

    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $amenityId = (int)($_POST['amenity_id'] ?? 0);
        if ($amenityId > 0) {
            $pdo->prepare("DELETE FROM amenities WHERE id = ?")->execute([$amenityId]);
            set_flash('success', 'Amenity removed from catalog.');
        }
        header('Location: ' . BASE_URL . '/admin/amenities.php');
        exit;
    }

    if ($action === 'save') {
        $amenityId = (int)($_POST['amenity_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'check_circle');
        $category = $_POST['category'] ?? 'comfort';
        $order = (int)($_POST['display_order'] ?? 99);

        if (empty($name)) {
            set_flash('error', 'Amenity name is required.');
            header('Location: ' . BASE_URL . '/admin/amenities.php');
            exit;
        }

        if ($amenityId > 0) {
            $stmt = $pdo->prepare("UPDATE amenities SET name = ?, icon = ?, category = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $icon, $category, $order, $amenityId]);
            set_flash('success', "Amenity '{$name}' updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO amenities (name, icon, category, display_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE icon = VALUES(icon), category = VALUES(category)");
            $stmt->execute([$name, $icon, $category, $order]);
            set_flash('success', "New amenity '{$name}' added to catalog.");
        }

        header('Location: ' . BASE_URL . '/admin/amenities.php');
        exit;
    }
}

// Fetch All Master Amenities
$stmtAmenities = $pdo->query("SELECT * FROM amenities ORDER BY display_order ASC, id ASC");
$amenitiesList = $stmtAmenities->fetchAll();

$categories = [
    'comfort' => ['name' => 'Room & Comfort', 'color' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'bathroom' => ['name' => 'Bathroom & Wellness', 'color' => 'bg-blue-50 text-blue-800 border-blue-200'],
    'technology' => ['name' => 'Media & Tech', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
    'dining' => ['name' => 'Dining & Drinks', 'color' => 'bg-amber-50 text-amber-800 border-amber-200'],
    'luxury' => ['name' => 'Exclusive & VIP', 'color' => 'bg-purple-50 text-purple-800 border-purple-200'],
    'general' => ['name' => 'General Feature', 'color' => 'bg-stone-100 text-stone-700 border-stone-200'],
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/admin/accommodations.php" class="text-xs font-bold uppercase tracking-wider text-stone-500 hover:text-[#343c0a]">Accommodations</a>
                <span class="text-xs text-stone-400">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-[#4B5320]">Amenities Catalog</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Room Amenities & Features</h1>
            <p class="text-xs text-stone-500 mt-1">Manage global standard room amenities, assigned icons, and categories available across all suites.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/accommodations.php" class="px-4 py-2.5 rounded-lg border border-stone-300 text-stone-700 text-xs font-semibold hover:bg-stone-50 transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                <span>Back to Rooms</span>
            </a>
            <button onclick="openAmenityModal(0, '', 'check_circle', 'comfort', 99)" 
                    class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>Create New Amenity</span>
            </button>
        </div>
    </div>

    <!-- Amenities Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($amenitiesList as $amenity): 
            $catInfo = $categories[$amenity['category']] ?? $categories['general'];
            $icon = !empty($amenity['icon']) ? $amenity['icon'] : get_amenity_icon($amenity['name']);
        ?>
        <div class="bg-white rounded-xl border border-stone-200 p-4 shadow-2xs hover:shadow-sm transition flex flex-col justify-between group">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="w-10 h-10 rounded-lg bg-stone-100 text-[#343c0a] group-hover:bg-[#dfe8a6]/40 flex items-center justify-center transition shrink-0">
                        <span class="material-symbols-outlined text-2xl"><?= e($icon) ?></span>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?= $catInfo['color'] ?> uppercase tracking-wider">
                        <?= e($catInfo['name']) ?>
                    </span>
                </div>

                <div>
                    <h3 class="font-headline font-bold text-sm text-onyx-charcoal group-hover:text-[#343c0a] transition"><?= e($amenity['name']) ?></h3>
                    <span class="text-[10px] text-stone-400 font-mono">Icon: <?= e($icon) ?></span>
                </div>
            </div>

            <div class="pt-3 mt-3 border-t border-stone-100 flex items-center justify-between text-xs">
                <button type="button" 
                        onclick='openAmenityModal(<?= (int)$amenity['id'] ?>, <?= json_encode($amenity['name']) ?>, <?= json_encode($icon) ?>, <?= json_encode($amenity['category']) ?>, <?= (int)$amenity['display_order'] ?>)'
                        class="text-stone-600 hover:text-stone-900 font-semibold inline-flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    <span>Edit</span>
                </button>

                <form action="<?= BASE_URL ?>/admin/amenities.php" method="POST" class="inline" onsubmit="return confirm('Remove amenity <?= e(addslashes($amenity['name'])) ?> from catalog?');">
                    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="amenity_id" value="<?= (int)$amenity['id'] ?>">
                    <button type="submit" class="text-rose-600 hover:text-rose-800 p-1 cursor-pointer" title="Delete Amenity">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Create / Edit Amenity Modal -->
<div id="amenity-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6">
        
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div>
                <h3 id="amenity-modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Add Room Amenity</h3>
                <p class="text-[11px] text-stone-500">Define feature name, category, and visual icon.</p>
            </div>
            <button onclick="closeAmenityModal()" class="text-stone-400 hover:text-stone-700 p-1 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/amenities.php" method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="amenity_id" id="modal_amenity_id" value="0">

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Amenity Name *</label>
                <input type="text" name="name" id="modal_amenity_name" required placeholder="e.g. Dyson Supersonic Hair Dryer" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Category</label>
                <select name="category" id="modal_amenity_category" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-semibold">
                    <option value="comfort">Room & Comfort (Bedding, Balcony, Work Desk)</option>
                    <option value="bathroom">Bathroom & Wellness (Bath, Shower, Toiletries)</option>
                    <option value="technology">Media & Technology (Wi-Fi, 4K TV, Audio)</option>
                    <option value="dining">Dining & Refreshments (Nespresso, Minibar, Tea)</option>
                    <option value="luxury">Exclusive & VIP (Transfers, Concierge, Butler)</option>
                    <option value="general">General Feature</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Google Material Icon Name</label>
                <div class="flex gap-2 items-center">
                    <div class="w-9 h-9 rounded-lg bg-stone-100 flex items-center justify-center text-[#343c0a] shrink-0 border border-stone-200">
                        <span id="modal-icon-preview" class="material-symbols-outlined text-xl">check_circle</span>
                    </div>
                    <input type="text" name="icon" id="modal_amenity_icon" value="check_circle" placeholder="e.g. wifi, tv, coffee"
                           oninput="document.getElementById('modal-icon-preview').textContent = this.value.trim() || 'check_circle'"
                           class="w-full font-mono border border-stone-300 rounded-lg p-2.5 text-xs focus:ring-2 focus:ring-[#343c0a]">
                </div>
                
                <!-- Quick Icon Picker Chips -->
                <div class="flex flex-wrap gap-1.5 pt-2">
                    <?php 
                    $sampleIcons = ['wifi', 'tv', 'coffee', 'kitchen', 'ac_unit', 'shower', 'bathtub', 'hot_tub', 'balcony', 'lock', 'desk', 'cleaning_services', 'apparel', 'weekend', 'airport_shuttle', 'soap', 'spa'];
                    foreach ($sampleIcons as $si): ?>
                        <button type="button" onclick="selectAmenityIcon('<?= $si ?>')" class="px-2 py-1 rounded bg-stone-100 hover:bg-[#dfe8a6]/50 text-stone-700 text-[10px] font-mono transition cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs"><?= $si ?></span>
                            <span><?= $si ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Display Sort Order</label>
                <input type="number" name="display_order" id="modal_amenity_order" value="99" min="1" max="999"
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeAmenityModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save Amenity</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAmenityModal(id, name, icon, category, order) {
    document.getElementById('modal_amenity_id').value = id || 0;
    document.getElementById('modal_amenity_name').value = name || '';
    document.getElementById('modal_amenity_icon').value = icon || 'check_circle';
    document.getElementById('modal_amenity_category').value = category || 'comfort';
    document.getElementById('modal_amenity_order').value = order || 99;
    document.getElementById('modal-icon-preview').textContent = icon || 'check_circle';

    if (id > 0) {
        document.getElementById('amenity-modal-title').textContent = 'Edit Room Amenity';
    } else {
        document.getElementById('amenity-modal-title').textContent = 'Add Room Amenity';
    }

    document.getElementById('amenity-modal').classList.remove('hidden');
}

function closeAmenityModal() {
    document.getElementById('amenity-modal').classList.add('hidden');
}

function selectAmenityIcon(iconName) {
    document.getElementById('modal_amenity_icon').value = iconName;
    document.getElementById('modal-icon-preview').textContent = iconName;
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
