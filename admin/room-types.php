<?php
/**
 * Indra Hotel - Room Types & Categories CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'Room Types & Categories';

// Handle Actions (Save / Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['type_action'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/room-types.php');
        exit;
    }

    $action = $_POST['type_action'];

    if ($action === 'delete') {
        $typeId = (int)($_POST['type_id'] ?? 0);
        if ($typeId > 0) {
            $stmtCheck = $pdo->prepare("SELECT name FROM room_types WHERE id = ?");
            $stmtCheck->execute([$typeId]);
            $typeRow = $stmtCheck->fetch();

            if ($typeRow) {
                // Check if rooms currently use this category
                $stmtRoomCount = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE category = ? OR room_type_id = ?");
                $stmtRoomCount->execute([$typeRow['name'], $typeId]);
                $assignedRooms = (int)$stmtRoomCount->fetchColumn();

                if ($assignedRooms > 0) {
                    set_flash('error', "Cannot delete '{$typeRow['name']}' because {$assignedRooms} accommodation(s) are currently assigned to it.");
                } else {
                    $pdo->prepare("DELETE FROM room_types WHERE id = ?")->execute([$typeId]);
                    set_flash('success', "Room type '{$typeRow['name']}' removed.");
                }
            }
        }
        header('Location: ' . BASE_URL . '/admin/room-types.php');
        exit;
    }

    if ($action === 'save') {
        $typeId = (int)($_POST['type_id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? 'hotel');
        $color = trim($_POST['badge_color'] ?? 'emerald');
        $descEn = trim($_POST['description_en'] ?? '');
        $order = (int)($_POST['display_order'] ?? 99);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($nameEn)) {
            set_flash('error', 'Room type name (English) is required.');
            header('Location: ' . BASE_URL . '/admin/room-types.php');
            exit;
        }

        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nameEn), '-'));
        }

        // Multi-Language Translations
        $translations = [
            'km' => [
                'name' => trim($_POST['name_km'] ?? ''),
                'description' => trim($_POST['description_km'] ?? '')
            ],
            'zh' => [
                'name' => trim($_POST['name_zh'] ?? ''),
                'description' => trim($_POST['description_zh'] ?? '')
            ],
            'ko' => [
                'name' => trim($_POST['name_ko'] ?? ''),
                'description' => trim($_POST['description_ko'] ?? '')
            ]
        ];
        $transJson = json_encode($translations, JSON_UNESCAPED_UNICODE);

        if ($typeId > 0) {
            // Get original name for updating existing assigned rooms
            $origName = $pdo->prepare("SELECT name FROM room_types WHERE id = ?");
            $origName->execute([$typeId]);
            $oldName = $origName->fetchColumn();

            $stmt = $pdo->prepare("UPDATE room_types SET name = ?, slug = ?, icon = ?, badge_color = ?, description = ?, translations_json = ?, display_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$nameEn, $slug, $icon, $color, $descEn, $transJson, $order, $isActive, $typeId]);

            // Sync updated category name to rooms
            if ($oldName && $oldName !== $nameEn) {
                $pdo->prepare("UPDATE rooms SET category = ? WHERE category = ?")->execute([$nameEn, $oldName]);
            }

            set_flash('success', "Room type '{$nameEn}' updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO room_types (name, slug, icon, badge_color, description, translations_json, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nameEn, $slug, $icon, $color, $descEn, $transJson, $order, $isActive]);
            set_flash('success', "New room type '{$nameEn}' created.");
        }

        header('Location: ' . BASE_URL . '/admin/room-types.php');
        exit;
    }
}

// Fetch all Room Types with count of rooms
$stmtTypes = $pdo->query("SELECT rt.*, (SELECT COUNT(*) FROM rooms WHERE rooms.category = rt.name OR rooms.room_type_id = rt.id) as room_count FROM room_types rt ORDER BY rt.display_order ASC, rt.id ASC");
$roomTypes = $stmtTypes->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8 max-w-6xl">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Accommodations</span>
                <span class="text-xs text-stone-400">/</span>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Categories</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Room Types & Categories</h1>
            <p class="text-xs text-stone-500 mt-1">Configure hotel accommodation types (e.g. Deluxe Room, Suite Room, Conference Hall, City View, Villas).</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/accommodations.php" class="px-4 py-2.5 rounded-lg border border-stone-300 text-stone-700 text-xs font-semibold hover:bg-stone-50 transition flex items-center gap-1.5 shadow-2xs">
                <span class="material-symbols-outlined text-base">bed</span>
                <span>View Accommodations</span>
            </a>
            <button onclick="openTypeModal(0)" 
                    class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">add_circle</span>
                <span>Add Room Type</span>
            </button>
        </div>
    </div>

    <!-- Room Types Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($roomTypes as $rt): 
            $trans = !empty($rt['translations_json']) ? json_decode($rt['translations_json'], true) : [];
        ?>
        <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-2xs hover:shadow-md transition flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-stone-100 text-[#343c0a] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-2xl"><?= e($rt['icon']) ?></span>
                        </div>
                        <div>
                            <h3 class="font-headline font-bold text-lg text-onyx-charcoal"><?= e($rt['name']) ?></h3>
                            <span class="text-xs font-mono text-stone-400">/<?= e($rt['slug']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Badge Preview -->
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-stone-400 uppercase">Live Badge:</span>
                    <?= get_room_type_badge($rt['name']) ?>
                </div>

                <p class="text-xs text-stone-600 leading-relaxed min-h-[36px]">
                    <?= e($rt['description'] ?: 'No description provided.') ?>
                </p>

                <!-- Translation Tags & Room Count -->
                <div class="flex items-center justify-between pt-2 border-t border-stone-100 text-xs">
                    <div class="flex items-center gap-1">
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">EN</span>
                        <?php foreach (['km' => 'KM', 'zh' => 'ZH', 'ko' => 'KO'] as $lKey => $lLabel): ?>
                            <?php if (!empty($trans[$lKey]['name'])): ?>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700"><?= $lLabel ?></span>
                            <?php else: ?>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-stone-100 text-stone-400"><?= $lLabel ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-stone-600 bg-stone-100 px-2.5 py-1 rounded-full">
                        <span class="material-symbols-outlined text-xs">bed</span>
                        <span><?= (int)$rt['room_count'] ?> Room(s)</span>
                    </span>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                <a href="<?= BASE_URL ?>/rooms.php?category=<?= urlencode($rt['name']) ?>" target="_blank" class="text-[#343c0a] hover:underline font-semibold flex items-center gap-1">
                    <span>View Rooms</span>
                    <span class="material-symbols-outlined text-xs">open_in_new</span>
                </a>

                <div class="flex items-center gap-2">
                    <button type="button" 
                            onclick='editTypeModal(<?= json_encode($rt) ?>)'
                            class="text-stone-600 hover:text-stone-900 font-semibold inline-flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">edit</span>
                        <span>Edit</span>
                    </button>

                    <form action="<?= BASE_URL ?>/admin/room-types.php" method="POST" class="inline" onsubmit="return confirm('Delete room type <?= e(addslashes($rt['name'])) ?>?');">
                        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                        <input type="hidden" name="type_action" value="delete">
                        <input type="hidden" name="type_id" value="<?= (int)$rt['id'] ?>">
                        <button type="submit" class="text-rose-600 hover:text-rose-800 p-1 cursor-pointer" title="Delete Room Type">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Room Type Create / Edit Modal -->
<div id="type-modal" class="fixed inset-0 bg-black/60 z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-8">
    <div class="min-h-full flex items-start justify-center">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6 my-6 relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-stone-200">
                <div>
                    <h3 id="type-modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Add Room Type</h3>
                    <p class="text-[11px] text-stone-500">Configure type name, icon, color badge, and localized translations.</p>
                </div>
                <button type="button" onclick="closeTypeModal()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100 transition cursor-pointer">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <form action="<?= BASE_URL ?>/admin/room-types.php" method="POST" class="space-y-5 text-xs">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                <input type="hidden" name="type_action" value="save">
                <input type="hidden" name="type_id" id="modal_type_id" value="0">

                <!-- Type Name (Multi-Language) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-bold text-stone-700 uppercase">Room Type Name *</label>
                        <div class="flex gap-1 bg-stone-100 p-0.5 rounded-md">
                            <button type="button" onclick="switchTypeLang('name', 'en')" id="tab-type-name-en" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs">EN</button>
                            <button type="button" onclick="switchTypeLang('name', 'km')" id="tab-type-name-km" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KM</button>
                            <button type="button" onclick="switchTypeLang('name', 'zh')" id="tab-type-name-zh" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">ZH</button>
                            <button type="button" onclick="switchTypeLang('name', 'ko')" id="tab-type-name-ko" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KO</button>
                        </div>
                    </div>
                    <input type="text" name="name_en" id="modal_type_name_en" required placeholder="e.g. Conference Room / Executive Suite (English)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold">
                    <input type="text" name="name_km" id="modal_type_name_km" placeholder="e.g. បន្ទប់សន្និសីទ (Khmer)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
                    <input type="text" name="name_zh" id="modal_type_name_zh" placeholder="e.g. 会议室 (Chinese)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
                    <input type="text" name="name_ko" id="modal_type_name_ko" placeholder="e.g. 컨퍼런스 룸 (Korean)" class="w-full border border-stone-300 rounded-lg p-2.5 font-bold hidden">
                </div>

                <!-- Slug & Badge Color -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-stone-700 uppercase mb-1">URL Slug</label>
                        <input type="text" name="slug" id="modal_type_slug" placeholder="e.g. conference-room" 
                               class="w-full font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 uppercase mb-1">Badge Color Theme</label>
                        <select name="badge_color" id="modal_type_color" class="w-full border border-stone-300 rounded-lg p-2.5 font-semibold">
                            <option value="emerald">Emerald (Green)</option>
                            <option value="amber">Amber (Gold / Luxury)</option>
                            <option value="indigo">Indigo (Executive / Conference)</option>
                            <option value="blue">Blue (City View / Modern)</option>
                            <option value="purple">Purple (Presidential / VIP)</option>
                            <option value="rose">Rose (Romantic / Villa)</option>
                            <option value="olive">Olive (Signature)</option>
                            <option value="stone">Stone (Neutral)</option>
                        </select>
                    </div>
                </div>

                <!-- Material Icon Picker -->
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Material Icon Name</label>
                    <div class="flex gap-2 items-center">
                        <div class="w-9 h-9 rounded-lg bg-stone-100 flex items-center justify-center text-[#343c0a] shrink-0 border border-stone-200">
                            <span id="modal-type-icon-preview" class="material-symbols-outlined text-xl">hotel</span>
                        </div>
                        <input type="text" name="icon" id="modal_type_icon" value="hotel" 
                               oninput="document.getElementById('modal-type-icon-preview').textContent = this.value.trim() || 'hotel'"
                               class="w-full font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    </div>

                    <!-- Icon Quick Chips -->
                    <div class="flex flex-wrap gap-1.5 pt-2">
                        <?php 
                        $typeIcons = ['hotel', 'apartment', 'meeting_room', 'location_city', 'corporate_fare', 'bed', 'king_bed', 'villa', 'groups', 'event_seat', 'domain', 'chair'];
                        foreach ($typeIcons as $ti): ?>
                            <button type="button" onclick="selectTypeIcon('<?= $ti ?>')" class="px-2 py-1 rounded bg-stone-100 hover:bg-[#dfe8a6]/50 text-stone-700 text-[10px] font-mono transition cursor-pointer flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs"><?= $ti ?></span>
                                <span><?= $ti ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Description (Multi-Language) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-bold text-stone-700 uppercase">Description Narrative</label>
                        <div class="flex gap-1 bg-stone-100 p-0.5 rounded-md">
                            <button type="button" onclick="switchTypeLang('desc', 'en')" id="tab-type-desc-en" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs">EN</button>
                            <button type="button" onclick="switchTypeLang('desc', 'km')" id="tab-type-desc-km" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KM</button>
                            <button type="button" onclick="switchTypeLang('desc', 'zh')" id="tab-type-desc-zh" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">ZH</button>
                            <button type="button" onclick="switchTypeLang('desc', 'ko')" id="tab-type-desc-ko" class="px-2 py-0.5 rounded text-[10px] font-bold text-stone-500">KO</button>
                        </div>
                    </div>
                    <textarea name="description_en" id="modal_type_desc_en" rows="2" placeholder="Description in English..." class="w-full border border-stone-300 rounded-lg p-2.5"></textarea>
                    <textarea name="description_km" id="modal_type_desc_km" rows="2" placeholder="Description in Khmer..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
                    <textarea name="description_zh" id="modal_type_desc_zh" rows="2" placeholder="Description in Chinese..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
                    <textarea name="description_ko" id="modal_type_desc_ko" rows="2" placeholder="Description in Korean..." class="w-full border border-stone-300 rounded-lg p-2.5 hidden"></textarea>
                </div>

                <!-- Display Order & Active Toggle -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div class="flex items-center">
                        <label class="flex items-center gap-2 cursor-pointer font-bold text-stone-700">
                            <input type="checkbox" name="is_active" id="modal_type_active" value="1" checked class="rounded text-[#343c0a] focus:ring-[#343c0a]">
                            <span>Active & Visible</span>
                        </label>
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 uppercase mb-1">Display Sort Order</label>
                        <input type="number" name="display_order" id="modal_type_order" value="99" min="1" max="999" class="w-full border border-stone-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                    <button type="button" onclick="closeTypeModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                    <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save Room Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTypeModal(id) {
    document.getElementById('modal_type_id').value = 0;
    document.getElementById('modal_type_slug').value = '';
    document.getElementById('modal_type_name_en').value = '';
    document.getElementById('modal_type_name_km').value = '';
    document.getElementById('modal_type_name_zh').value = '';
    document.getElementById('modal_type_name_ko').value = '';

    document.getElementById('modal_type_desc_en').value = '';
    document.getElementById('modal_type_desc_km').value = '';
    document.getElementById('modal_type_desc_zh').value = '';
    document.getElementById('modal_type_desc_ko').value = '';

    document.getElementById('modal_type_icon').value = 'hotel';
    document.getElementById('modal-type-icon-preview').textContent = 'hotel';
    document.getElementById('modal_type_color').value = 'emerald';
    document.getElementById('modal_type_active').checked = true;
    document.getElementById('modal_type_order').value = 99;

    document.getElementById('type-modal-title').textContent = 'Add Room Type';
    switchTypeLang('name', 'en');
    switchTypeLang('desc', 'en');

    const modal = document.getElementById('type-modal');
    modal.classList.remove('hidden');
    modal.scrollTop = 0;
}

function editTypeModal(rt) {
    document.getElementById('modal_type_id').value = rt.id;
    document.getElementById('modal_type_slug').value = rt.slug || '';
    document.getElementById('modal_type_name_en').value = rt.name || '';
    document.getElementById('modal_type_desc_en').value = rt.description || '';
    document.getElementById('modal_type_icon').value = rt.icon || 'hotel';
    document.getElementById('modal-type-icon-preview').textContent = rt.icon || 'hotel';
    document.getElementById('modal_type_color').value = rt.badge_color || 'emerald';
    document.getElementById('modal_type_active').checked = (rt.is_active == 1);
    document.getElementById('modal_type_order').value = rt.display_order || 99;

    let trans = {};
    try {
        if (rt.translations_json) {
            trans = JSON.parse(rt.translations_json);
        }
    } catch(e) {}

    document.getElementById('modal_type_name_km').value = trans.km?.name || '';
    document.getElementById('modal_type_name_zh').value = trans.zh?.name || '';
    document.getElementById('modal_type_name_ko').value = trans.ko?.name || '';

    document.getElementById('modal_type_desc_km').value = trans.km?.description || '';
    document.getElementById('modal_type_desc_zh').value = trans.zh?.description || '';
    document.getElementById('modal_type_desc_ko').value = trans.ko?.description || '';

    document.getElementById('type-modal-title').textContent = 'Edit Room Type';
    switchTypeLang('name', 'en');
    switchTypeLang('desc', 'en');

    const modal = document.getElementById('type-modal');
    modal.classList.remove('hidden');
    modal.scrollTop = 0;
}

function closeTypeModal() {
    document.getElementById('type-modal').classList.add('hidden');
}

function selectTypeIcon(iconName) {
    document.getElementById('modal_type_icon').value = iconName;
    document.getElementById('modal-type-icon-preview').textContent = iconName;
}

function switchTypeLang(field, lang) {
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const input = document.getElementById('modal_type_' + field + '_' + l);
        const tab = document.getElementById('tab-type-' + field + '-' + l);
        if (input && tab) {
            if (l === lang) {
                input.classList.remove('hidden');
                tab.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-white text-stone-900 shadow-2xs';
            } else {
                input.classList.add('hidden');
                tab.className = 'px-2 py-0.5 rounded text-[10px] font-bold text-stone-500';
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
