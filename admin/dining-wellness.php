<?php
/**
 * Indra Hotel - Dining & Wellness CMS Editor
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Dining & Wellness Editor';

// Handle Save / Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['hero_page_keys']) || isset($_POST['save_hero_settings'])) {
        process_hero_settings_post();
        set_flash('success', 'Dining & Wellness page heroes updated.');
        header('Location: ' . BASE_URL . '/admin/dining-wellness.php');
        exit;
    }

    $itemId = (int)($_POST['item_id'] ?? 0);

    $type = $_POST['type'] ?? 'dining';
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $hours = trim($_POST['hours'] ?? '');
    $priceRange = trim($_POST['price_range'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $menuJson = trim($_POST['menu_items_json'] ?? '[]');

    $translationsPayload = [
        'en' => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description
        ],
        'km' => [
            'title' => trim($_POST['trans_km_title'] ?? ''),
            'subtitle' => trim($_POST['trans_km_subtitle'] ?? ''),
            'description' => trim($_POST['trans_km_desc'] ?? '')
        ],
        'zh' => [
            'title' => trim($_POST['trans_zh_title'] ?? ''),
            'subtitle' => trim($_POST['trans_zh_subtitle'] ?? ''),
            'description' => trim($_POST['trans_zh_desc'] ?? '')
        ],
        'ko' => [
            'title' => trim($_POST['trans_ko_title'] ?? ''),
            'subtitle' => trim($_POST['trans_ko_subtitle'] ?? ''),
            'description' => trim($_POST['trans_ko_desc'] ?? '')
        ]
    ];
    $transJson = json_encode($translationsPayload, JSON_UNESCAPED_UNICODE);

    if (!empty($title)) {
        if ($itemId > 0) {
            $stmt = $pdo->prepare("UPDATE dining_wellness SET type = ?, title = ?, subtitle = ?, description = ?, hours = ?, price_range = ?, image_url = ?, menu_items_json = ?, translations_json = ? WHERE id = ?");
            $stmt->execute([$type, $title, $subtitle, $description, $hours, $priceRange, $imageUrl, $menuJson, $transJson, $itemId]);
            set_flash('success', "Item '{$title}' updated.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO dining_wellness (type, title, subtitle, description, hours, price_range, image_url, menu_items_json, translations_json, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$type, $title, $subtitle, $description, $hours, $priceRange, $imageUrl, $menuJson, $transJson]);
            set_flash('success', "New item '{$title}' created.");
        }
    }
    header('Location: ' . BASE_URL . '/admin/dining-wellness.php');
    exit;
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM dining_wellness WHERE id = ?")->execute([$delId]);
    set_flash('success', 'Item deleted.');
    header('Location: ' . BASE_URL . '/admin/dining-wellness.php');
    exit;
}

$stmtDW = $pdo->query("SELECT * FROM dining_wellness ORDER BY type ASC, display_order ASC");
$items = $stmtDW->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Dining & Wellness Experiences</h2>
            <p class="text-xs text-stone-500">Manage restaurant offerings, cafe hours, fitness amenities, and spa treatments.</p>
        </div>
        <button onclick="openEditModal(0, 'dining', '', '', '', '', '', '', '[]')" 
                class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">add</span>
            <span>Add Venue / Service</span>
        </button>
    </div>

    <!-- Page Heroes Editor Collapsible Card -->
    <details class="bg-stone-900 text-white rounded-2xl border border-stone-800 shadow-md overflow-hidden">
        <summary class="px-6 py-4 font-bold text-xs uppercase tracking-wider text-[#dfe8a6] cursor-pointer flex items-center justify-between hover:bg-stone-800 transition">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base">view_day</span>
                <span>Edit Dining & Wellness Page Hero Banners</span>
            </div>
            <span class="text-[10px] text-stone-400 font-normal">Click to expand / collapse</span>
        </summary>
        <div class="p-6 bg-stone-50 text-stone-900 border-t border-stone-800 space-y-6">
            <form action="<?= BASE_URL ?>/admin/dining-wellness.php" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                <?= render_admin_hero_editor_card('dining', 'Dining & Bistro', [
                    'badge' => 'Culinary Artistry',
                    'title' => 'Eat & Drink',
                    'subtitle' => 'From artisanal morning coffee to sunset cocktails and fine Asian-fusion dining, indulge in an elevated gastronomic atmosphere.',
                    'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80'
                ]) ?>
                <?= render_admin_hero_editor_card('wellness', 'Gym, Pool & Wellness', [
                    'badge' => 'Rejuvenation & Mindful Living',
                    'title' => 'Gym, Pool & Wellness',
                    'subtitle' => 'Revitalize your body and mind in our state-of-the-art fitness center and serene outdoor saltwater swimming pool.',
                    'image' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1600&q=80'
                ]) ?>
                <div class="flex justify-end">
                    <button type="submit" name="save_hero_settings" value="1" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded-lg text-xs font-bold transition shadow flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>Save Hero Banners</span>
                    </button>
                </div>
            </form>
        </div>
    </details>


    <!-- Items Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <?php foreach ($items as $item): 
            $menu = !empty($item['menu_items_json']) ? json_decode($item['menu_items_json'], true) : [];
            $trans = !empty($item['translations_json']) ? json_decode($item['translations_json'], true) : [];
        ?>
        <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="relative h-56 overflow-hidden">
                    <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" class="w-full h-full object-cover">
                    <div class="absolute top-4 left-4 bg-onyx-charcoal/80 backdrop-blur-md text-white text-[10px] font-bold uppercase px-3 py-1.5 rounded tracking-wider">
                        <?= strtoupper($item['type']) ?>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <h3 class="font-headline font-bold text-xl text-onyx-charcoal"><?= e($item['title']) ?></h3>
                        <p class="text-xs text-[#4B5320] font-semibold mt-0.5"><?= e($item['subtitle']) ?></p>
                    </div>

                    <p class="text-xs text-stone-600 leading-relaxed line-clamp-3">
                        <?= e($item['description']) ?>
                    </p>

                    <div class="text-xs text-stone-500 space-y-1 pt-2 border-t border-stone-100">
                        <div><strong>Hours:</strong> <?= e($item['hours']) ?></div>
                        <div><strong>Price:</strong> <?= e($item['price_range']) ?></div>
                        <div><strong>Signature Items:</strong> <?= count($menu) ?> items configured</div>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 flex items-center justify-end gap-3 border-t border-stone-100 pt-4">
                <button type="button" 
                        onclick='openEditModal(<?= (int)$item['id'] ?>, <?= json_encode($item['type']) ?>, <?= json_encode($item['title']) ?>, <?= json_encode($item['subtitle']) ?>, <?= json_encode($item['description']) ?>, <?= json_encode($item['hours']) ?>, <?= json_encode($item['price_range']) ?>, <?= json_encode($item['image_url']) ?>, <?= json_encode($item['menu_items_json']) ?>, <?= json_encode($item['translations_json'] ?? "{}") ?>)'
                        class="text-xs font-semibold text-[#4B5320] hover:underline flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    Edit Experience
                </button>
                <span class="text-stone-300">|</span>
                <a href="<?= BASE_URL ?>/admin/dining-wellness.php?action=delete&id=<?= (int)$item['id'] ?>" 
                   onclick="return confirm('Are you sure you want to delete this venue / service?')"
                   class="text-xs font-semibold text-rose-600 hover:underline">
                    Delete
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Edit / Add Modal with Multi-Language Tabbed Inputs -->
<div id="dw-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div>
                <h3 id="modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Edit Experience</h3>
                <p class="text-[11px] text-stone-500">Provide multi-language title, subtitle, and description with automatic English fallback.</p>
            </div>
            <button onclick="closeEditModal()" class="text-stone-400 hover:text-stone-700 p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/dining-wellness.php" method="POST" class="space-y-5 text-xs">
            <input type="hidden" name="item_id" id="modal_item_id" value="0">

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Experience Type *</label>
                    <select name="type" id="modal_type" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium">
                        <option value="dining">Dining & Cafe</option>
                        <option value="wellness">Gym & Wellness</option>
                    </select>
                </div>

                <div>
                    <?= render_image_uploader_field('image_url', '', 'Venue / Spa Cover Image', 'dining', [
                        'required' => true,
                        'helper' => 'Upload image file or paste URL'
                    ]) ?>
                </div>
            </div>

            <!-- Multi-Language Section for Venue -->
            <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 space-y-4">
                
                <!-- Title Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Venue / Service Title *</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchDWLang('title', 'en')" id="dw-tab-title-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchDWLang('title', 'km')" id="dw-tab-title-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchDWLang('title', 'zh')" id="dw-tab-title-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchDWLang('title', 'ko')" id="dw-tab-title-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="dw-box-title-en"><input type="text" name="title" id="modal_title_input" required placeholder="English Title" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="dw-box-title-km" class="hidden"><input type="text" name="trans_km_title" id="modal_trans_km_title" placeholder="ចំណងជើងជាភាសាខ្មែរ (Khmer Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></div>
                    <div id="dw-box-title-zh" class="hidden"><input type="text" name="trans_zh_title" id="modal_trans_zh_title" placeholder="中文标题 (Chinese Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="dw-box-title-ko" class="hidden"><input type="text" name="trans_ko_title" id="modal_trans_ko_title" placeholder="한국어 제목 (Korean Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                </div>

                <!-- Subtitle Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Subtitle / Cuisine Type</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchDWLang('sub', 'en')" id="dw-tab-sub-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchDWLang('sub', 'km')" id="dw-tab-sub-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchDWLang('sub', 'zh')" id="dw-tab-sub-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchDWLang('sub', 'ko')" id="dw-tab-sub-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="dw-box-sub-en"><input type="text" name="subtitle" id="modal_subtitle" placeholder="e.g. All-Day Dining & French-Khmer Fusion" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="dw-box-sub-km" class="hidden"><input type="text" name="trans_km_subtitle" id="modal_trans_km_sub" placeholder="ពាក្យរងជាភាសាខ្មែរ (Khmer Subtitle - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></div>
                    <div id="dw-box-sub-zh" class="hidden"><input type="text" name="trans_zh_subtitle" id="modal_trans_zh_sub" placeholder="中文副标题 (Chinese Subtitle - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="dw-box-sub-ko" class="hidden"><input type="text" name="trans_ko_subtitle" id="modal_trans_ko_sub" placeholder="한국어 부제목 (Korean Subtitle - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                </div>

                <!-- Description Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Description Narrative</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchDWLang('desc', 'en')" id="dw-tab-desc-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchDWLang('desc', 'km')" id="dw-tab-desc-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchDWLang('desc', 'zh')" id="dw-tab-desc-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchDWLang('desc', 'ko')" id="dw-tab-desc-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="dw-box-desc-en"><textarea name="description" id="modal_description" rows="3" placeholder="English description..." class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                    <div id="dw-box-desc-km" class="hidden"><textarea name="trans_km_desc" id="modal_trans_km_desc" rows="3" placeholder="ការពិពណ៌នាជាភាសាខ្មែរ (Khmer Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></textarea></div>
                    <div id="dw-box-desc-zh" class="hidden"><textarea name="trans_zh_desc" id="modal_trans_zh_desc" rows="3" placeholder="中文描述 (Chinese Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                    <div id="dw-box-desc-ko" class="hidden"><textarea name="trans_ko_desc" id="modal_trans_ko_desc" rows="3" placeholder="한국어 설명 (Korean Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                </div>

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Opening Hours</label>
                    <input type="text" name="hours" id="modal_hours" placeholder="e.g. 06:30 AM - 10:30 PM" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Price Range / Pricing Note</label>
                    <input type="text" name="price_range" id="modal_price" placeholder="e.g. $$ - $$$" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
            </div>

            <!-- Menu / Service Items Dynamic Repeater Builder -->
            <div class="border-t border-stone-200 pt-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <label class="block font-bold text-stone-700 uppercase text-xs tracking-wider">Menu / Service Items List</label>
                        <span class="text-[10px] text-stone-400">Add signature dishes, spa treatments, or service prices shown in the guest modal.</span>
                    </div>
                    <button type="button" onclick="addMenuItemRow()" class="inline-flex items-center gap-1 bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold px-3 py-1.5 rounded-lg transition shadow-2xs cursor-pointer">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Add Item</span>
                    </button>
                </div>
                
                <input type="hidden" name="menu_items_json" id="modal_menu_json" value="[]">
                
                <div id="menu_items_container" class="space-y-2.5 max-h-[240px] overflow-y-auto pr-1">
                    <!-- Dynamic Repeater Rows rendered here by JS -->
                </div>
            </div>

            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save Experience</button>
            </div>
        </form>
    </div>
</div>

<script>
function renderMenuItems(items) {
    const container = document.getElementById('menu_items_container');
    container.innerHTML = '';
    
    let parsed = [];
    if (typeof items === 'string') {
        try { parsed = JSON.parse(items || '[]'); } catch(e) { parsed = []; }
    } else if (Array.isArray(items)) {
        parsed = items;
    }

    if (!Array.isArray(parsed) || parsed.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-xs text-stone-400 border border-dashed border-stone-200 rounded-xl bg-stone-50/50">No menu/service items added yet. Click "+ Add Item" to add an item.</div>';
        syncMenuItemsJson();
        return;
    }

    parsed.forEach(item => {
        addMenuItemRow(item.name || '', item.price || '', item.desc || '');
    });
}

function addMenuItemRow(name = '', price = '', desc = '') {
    const container = document.getElementById('menu_items_container');
    const emptyMsg = container.querySelector('.text-center');
    if (emptyMsg) {
        container.innerHTML = '';
    }

    const row = document.createElement('div');
    row.className = 'p-3 bg-stone-50 border border-stone-200 rounded-xl space-y-2 relative group';
    row.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div class="sm:col-span-2">
                <input type="text" value="${escapeHtml(name)}" placeholder="Item Name (e.g. Signature Cocktail)" oninput="syncMenuItemsJson()" class="menu-item-name w-full border border-stone-300 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>
            <div>
                <input type="text" value="${escapeHtml(price)}" placeholder="Price (e.g. $14.00)" oninput="syncMenuItemsJson()" class="menu-item-price w-full border border-stone-300 rounded-lg p-2 text-xs font-bold text-emerald-800 focus:ring-2 focus:ring-[#343c0a]">
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" value="${escapeHtml(desc)}" placeholder="Short description or ingredients..." oninput="syncMenuItemsJson()" class="menu-item-desc flex-1 border border-stone-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-[#343c0a]">
            <button type="button" onclick="removeMenuItemRow(this)" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition shrink-0 cursor-pointer" title="Delete Item">
                <span class="material-symbols-outlined text-lg">delete</span>
            </button>
        </div>
    `;
    container.appendChild(row);
    syncMenuItemsJson();
}

function removeMenuItemRow(btn) {
    const row = btn.closest('.p-3');
    if (row) {
        row.remove();
        syncMenuItemsJson();
        const container = document.getElementById('menu_items_container');
        if (container.children.length === 0) {
            container.innerHTML = '<div class="p-4 text-center text-xs text-stone-400 border border-dashed border-stone-200 rounded-xl bg-stone-50/50">No menu/service items added yet. Click "+ Add Item" to add an item.</div>';
        }
    }
}

function syncMenuItemsJson() {
    const container = document.getElementById('menu_items_container');
    const rows = container.querySelectorAll('.p-3');
    const items = [];

    rows.forEach(r => {
        const nameInput = r.querySelector('.menu-item-name');
        const priceInput = r.querySelector('.menu-item-price');
        const descInput = r.querySelector('.menu-item-desc');
        if (nameInput) {
            const name = nameInput.value.trim();
            const price = priceInput ? priceInput.value.trim() : '';
            const desc = descInput ? descInput.value.trim() : '';
            if (name || price || desc) {
                items.push({ name, price, desc });
            }
        }
    });

    document.getElementById('modal_menu_json').value = JSON.stringify(items);
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function switchDWLang(field, lang) {
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const box = document.getElementById('dw-box-' + field + '-' + l);
        const btn = document.getElementById('dw-tab-' + field + '-' + l);
        if (box) {
            if (l === lang) {
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        }
        if (btn) {
            if (l === lang) {
                btn.className = 'px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white';
            } else {
                btn.className = 'px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white';
            }
        }
    });
}

function openEditModal(id, type, title, subtitle, desc, hours, price, img, menu, transJson) {
    document.getElementById('modal_item_id').value = id;
    document.getElementById('modal_type').value = type;
    document.getElementById('modal_title_input').value = title || '';
    document.getElementById('modal_subtitle').value = subtitle || '';
    document.getElementById('modal_description').value = desc || '';
    document.getElementById('modal_hours').value = hours || '';
    document.getElementById('modal_price').value = price || '';
    
    const imgInput = document.querySelector('#dw-modal input[name="image_url"]');
    if (imgInput) {
        imgInput.value = img || '';
        imgInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    renderMenuItems(menu || '[]');
    document.getElementById('modal-title').textContent = (id > 0) ? 'Edit Experience' : 'Add Experience';

    // Parse Translations JSON
    let trans = {};
    try {
        trans = typeof transJson === 'string' ? JSON.parse(transJson || '{}') : (transJson || {});
    } catch(e) { trans = {}; }

    document.getElementById('modal_trans_km_title').value = trans.km?.title || '';
    document.getElementById('modal_trans_zh_title').value = trans.zh?.title || '';
    document.getElementById('modal_trans_ko_title').value = trans.ko?.title || '';

    document.getElementById('modal_trans_km_sub').value = trans.km?.subtitle || '';
    document.getElementById('modal_trans_zh_sub').value = trans.zh?.subtitle || '';
    document.getElementById('modal_trans_ko_sub').value = trans.ko?.subtitle || '';

    document.getElementById('modal_trans_km_desc').value = trans.km?.description || '';
    document.getElementById('modal_trans_zh_desc').value = trans.zh?.description || '';
    document.getElementById('modal_trans_ko_desc').value = trans.ko?.description || '';

    // Reset tabs to EN
    switchDWLang('title', 'en');
    switchDWLang('sub', 'en');
    switchDWLang('desc', 'en');

    document.getElementById('dw-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('dw-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
