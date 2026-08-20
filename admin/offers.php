<?php
/**
 * Indra Hotel - Special Offers & Promo Codes CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Special Offers & Promotions';

// Handle Save
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $offerId = (int)($_POST['offer_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $promoCode = trim($_POST['promo_code'] ?? '');
    $discount = (int)($_POST['discount_percent'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $badge = trim($_POST['badge_text'] ?? 'Special Offer');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $externalUrl = trim($_POST['external_url'] ?? '');
    $validTo = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;

    $translationsPayload = [
        'en' => [
            'title' => $title,
            'badge_text' => $badge,
            'description' => $description
        ],
        'km' => [
            'title' => trim($_POST['trans_km_title'] ?? ''),
            'badge_text' => trim($_POST['trans_km_badge'] ?? ''),
            'description' => trim($_POST['trans_km_desc'] ?? '')
        ],
        'zh' => [
            'title' => trim($_POST['trans_zh_title'] ?? ''),
            'badge_text' => trim($_POST['trans_zh_badge'] ?? ''),
            'description' => trim($_POST['trans_zh_desc'] ?? '')
        ],
        'ko' => [
            'title' => trim($_POST['trans_ko_title'] ?? ''),
            'badge_text' => trim($_POST['trans_ko_badge'] ?? ''),
            'description' => trim($_POST['trans_ko_desc'] ?? '')
        ]
    ];
    $transJson = json_encode($translationsPayload, JSON_UNESCAPED_UNICODE);

    if (!empty($title)) {
        if ($offerId > 0) {
            $stmt = $pdo->prepare("UPDATE special_offers SET title = ?, promo_code = ?, discount_percent = ?, description = ?, badge_text = ?, image_url = ?, external_url = ?, valid_to = ?, translations_json = ? WHERE id = ?");
            $stmt->execute([$title, $promoCode, $discount, $description, $badge, $imageUrl, $externalUrl, $validTo, $transJson, $offerId]);
            set_flash('success', "Offer '{$title}' updated.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO special_offers (title, promo_code, discount_percent, description, badge_text, image_url, external_url, valid_to, translations_json, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$title, $promoCode, $discount, $description, $badge, $imageUrl, $externalUrl, $validTo, $transJson]);
            set_flash('success', "New offer '{$title}' created.");
        }
    }
    header('Location: ' . BASE_URL . '/admin/offers.php');
    exit;
}

// Handle Delete or Toggle
if (isset($_GET['action'])) {
    $targetId = (int)($_GET['id'] ?? 0);
    if ($_GET['action'] === 'delete' && $targetId > 0) {
        $pdo->prepare("DELETE FROM special_offers WHERE id = ?")->execute([$targetId]);
        set_flash('success', 'Offer deleted.');
    } elseif ($_GET['action'] === 'toggle' && $targetId > 0) {
        $stmtCurr = $pdo->prepare("SELECT title, is_active FROM special_offers WHERE id = ?");
        $stmtCurr->execute([$targetId]);
        $curr = $stmtCurr->fetch();
        if ($curr) {
            $newStatus = $curr['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE special_offers SET is_active = ? WHERE id = ?")->execute([$newStatus, $targetId]);
            if ($newStatus) {
                set_flash('success', "Promotion '{$curr['title']}' is now ACTIVE and visible on the website.");
            } else {
                set_flash('success', "Promotion '{$curr['title']}' is now DEACTIVATED and hidden from public pages.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/admin/offers.php');
    exit;
}

$stmtOffers = $pdo->query("SELECT * FROM special_offers ORDER BY display_order ASC");
$offers = $stmtOffers->fetchAll();

$activeCount = count(array_filter($offers, fn($o) => (bool)$o['is_active']));
$inactiveCount = count($offers) - $activeCount;

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Promotional Offers & Codes</h2>
            <p class="text-xs text-stone-500">Create promotional discount codes and special stay packages with active/inactive visibility controls.</p>
        </div>
        <button onclick="openOfferModal(0, '', '', 15, '', 'Best Rate Guarantee', '', '', '{}', '')" 
                class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2 cursor-pointer">
            <span class="material-symbols-outlined text-base">add</span>
            <span>Create New Offer</span>
        </button>
    </div>

    <!-- Quick Status Filter Pills -->
    <div class="flex items-center gap-2 text-xs font-semibold">
        <span class="px-3 py-1.5 rounded-lg bg-stone-100 text-stone-800 border border-stone-200">
            Total Offers: <strong><?= count($offers) ?></strong>
        </span>
        <span class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
            <span>Active: <strong><?= $activeCount ?></strong></span>
        </span>
        <span class="px-3 py-1.5 rounded-lg bg-stone-50 text-stone-600 border border-stone-200 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-stone-400"></span>
            <span>Deactivated: <strong><?= $inactiveCount ?></strong></span>
        </span>
    </div>

    <!-- Offers List Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                    <tr>
                        <th class="py-4 px-6 font-semibold">Offer & Banner</th>
                        <th class="py-4 px-6 font-semibold">Promo Code / Link</th>
                        <th class="py-4 px-6 font-semibold">Discount</th>
                        <th class="py-4 px-6 font-semibold">Validity</th>
                        <th class="py-4 px-6 font-semibold">Visibility Status</th>
                        <th class="py-4 px-6 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($offers as $offer): ?>
                    <tr class="hover:bg-stone-50 transition">
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-10 rounded-lg overflow-hidden border border-stone-200 shrink-0">
                                    <img src="<?= e($offer['image_url']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <div class="font-headline font-bold text-stone-900"><?= e($offer['title']) ?></div>
                                    <div class="text-[10px] text-[#4B5320] font-semibold uppercase"><?= e($offer['badge_text']) ?></div>
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-6 space-y-1">
                            <div class="font-mono font-bold text-[#343c0a]">
                                <?= e($offer['promo_code'] ?: 'N/A') ?>
                            </div>
                            <?php if (!empty($offer['external_url'])): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded font-semibold truncate max-w-[180px]" title="Custom Link: <?= e($offer['external_url']) ?>">
                                    <span class="material-symbols-outlined text-xs shrink-0">link</span>
                                    <span class="truncate">Custom Link</span>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="py-4 px-6 font-bold text-stone-900">
                            <?= $offer['discount_percent'] ?>%
                        </td>

                        <td class="py-4 px-6 text-stone-500">
                            <?= !empty($offer['valid_to']) ? format_date($offer['valid_to']) : 'Ongoing' ?>
                        </td>

                        <td class="py-4 px-6">
                            <?php if ($offer['is_active']): ?>
                                <a href="<?= BASE_URL ?>/admin/offers.php?action=toggle&id=<?= $offer['id'] ?>" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200 transition cursor-pointer" 
                                   title="Status: Active on site. Click to Deactivate.">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                    <span>Active</span>
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/admin/offers.php?action=toggle&id=<?= $offer['id'] ?>" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-stone-100 text-stone-600 border border-stone-300 hover:bg-stone-200 transition cursor-pointer" 
                                   title="Status: Deactivated / Hidden. Click to Activate.">
                                    <span class="w-2 h-2 rounded-full bg-stone-400"></span>
                                    <span>Deactivated</span>
                                </a>
                            <?php endif; ?>
                        </td>

                        <td class="py-4 px-6 text-right space-x-1.5">
                            <a href="<?= BASE_URL ?>/offer-detail.php?id=<?= (int)$offer['id'] ?>" 
                               target="_blank" 
                               class="p-1.5 text-stone-600 hover:text-[#343c0a] rounded hover:bg-stone-100 inline-block cursor-pointer" 
                               title="View Public Detail Page">
                                <span class="material-symbols-outlined text-base">visibility</span>
                            </a>
                            <button type="button" onclick='openOfferModal(<?= (int)$offer['id'] ?>, <?= json_encode($offer['title']) ?>, <?= json_encode($offer['promo_code']) ?>, <?= (int)$offer['discount_percent'] ?>, <?= json_encode($offer['description']) ?>, <?= json_encode($offer['badge_text']) ?>, <?= json_encode($offer['image_url']) ?>, <?= json_encode($offer['valid_to']) ?>, <?= json_encode($offer['translations_json'] ?? "{}") ?>, <?= json_encode($offer['external_url'] ?? '') ?>)' 
                                    class="p-1.5 text-stone-600 hover:text-stone-900 rounded hover:bg-stone-100 inline-block cursor-pointer" title="Edit Offer">
                                <span class="material-symbols-outlined text-base">edit</span>
                            </button>
                            <a href="<?= BASE_URL ?>/admin/offers.php?action=delete&id=<?= (int)$offer['id'] ?>" 
                               onclick="return confirm('Delete this promotion?')" 
                               class="p-1.5 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50 inline-block cursor-pointer" title="Delete Offer">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Offer Modal with Per-Input Language Tabs -->
<div id="offer-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div>
                <h3 id="offer-modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Edit Special Offer</h3>
                <p class="text-[11px] text-stone-500">Provide multi-language title, badge, and description with automatic English fallback.</p>
            </div>
            <button onclick="closeOfferModal()" class="text-stone-400 hover:text-stone-700 p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/offers.php" method="POST" class="space-y-5 text-xs">
            <input type="hidden" name="offer_id" id="modal_offer_id" value="0">

            <!-- Multi-Language Section for Offer -->
            <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 space-y-4">
                
                <!-- Title Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Offer Title *</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchOfferLang('title', 'en')" id="offer-tab-title-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchOfferLang('title', 'km')" id="offer-tab-title-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchOfferLang('title', 'zh')" id="offer-tab-title-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchOfferLang('title', 'ko')" id="offer-tab-title-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="offer-box-title-en"><input type="text" name="title" id="modal_offer_title" required placeholder="e.g. Advance Purchase Saver" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="offer-box-title-km" class="hidden"><input type="text" name="trans_km_title" id="modal_trans_km_offer_title" placeholder="ចំណងជើងជាភាសាខ្មែរ (Khmer Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></div>
                    <div id="offer-box-title-zh" class="hidden"><input type="text" name="trans_zh_title" id="modal_trans_zh_offer_title" placeholder="中文标题 (Chinese Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="offer-box-title-ko" class="hidden"><input type="text" name="trans_ko_title" id="modal_trans_ko_offer_title" placeholder="한국어 제목 (Korean Title - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                </div>

                <!-- Badge Text Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Badge Label</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchOfferLang('badge', 'en')" id="offer-tab-badge-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchOfferLang('badge', 'km')" id="offer-tab-badge-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchOfferLang('badge', 'zh')" id="offer-tab-badge-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchOfferLang('badge', 'ko')" id="offer-tab-badge-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="offer-box-badge-en"><input type="text" name="badge_text" id="modal_offer_badge" placeholder="e.g. Best Rate Guarantee" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="offer-box-badge-km" class="hidden"><input type="text" name="trans_km_badge" id="modal_trans_km_offer_badge" placeholder="ផ្លាកជាភាសាខ្មែរ (Khmer Badge - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></div>
                    <div id="offer-box-badge-zh" class="hidden"><input type="text" name="trans_zh_badge" id="modal_trans_zh_offer_badge" placeholder="中文标签 (Chinese Badge - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                    <div id="offer-box-badge-ko" class="hidden"><input type="text" name="trans_ko_badge" id="modal_trans_ko_offer_badge" placeholder="한국어 배지 (Korean Badge - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></div>
                </div>

                <!-- Description Field with Per-Input Language Tabs -->
                <div class="bg-white p-3.5 rounded-lg border border-stone-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-stone-800 uppercase">Offer Description</label>
                        <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded text-[11px]">
                            <button type="button" onclick="switchOfferLang('desc', 'en')" id="offer-tab-desc-en" class="px-2 py-0.5 rounded font-bold bg-[#343c0a] text-white">🇬🇧 EN</button>
                            <button type="button" onclick="switchOfferLang('desc', 'km')" id="offer-tab-desc-km" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇭 KM</button>
                            <button type="button" onclick="switchOfferLang('desc', 'zh')" id="offer-tab-desc-zh" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇨🇳 ZH</button>
                            <button type="button" onclick="switchOfferLang('desc', 'ko')" id="offer-tab-desc-ko" class="px-2 py-0.5 rounded font-bold text-stone-600 hover:bg-white">🇰🇷 KO</button>
                        </div>
                    </div>
                    <div id="offer-box-desc-en"><textarea name="description" id="modal_offer_description" rows="3" placeholder="English description..." class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                    <div id="offer-box-desc-km" class="hidden"><textarea name="trans_km_desc" id="modal_trans_km_offer_desc" rows="3" placeholder="ការពិពណ៌នាជាភាសាខ្មែរ (Khmer Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs font-khmer"></textarea></div>
                    <div id="offer-box-desc-zh" class="hidden"><textarea name="trans_zh_desc" id="modal_trans_zh_offer_desc" rows="3" placeholder="中文描述 (Chinese Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                    <div id="offer-box-desc-ko" class="hidden"><textarea name="trans_ko_desc" id="modal_trans_ko_offer_desc" rows="3" placeholder="한국어 설명 (Korean Description - Optional)" class="w-full border border-stone-300 rounded p-2 text-xs"></textarea></div>
                </div>

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Promo Code</label>
                    <input type="text" name="promo_code" id="modal_offer_code" placeholder="e.g. INDRA15" class="w-full uppercase font-mono border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Discount %</label>
                    <input type="number" name="discount_percent" id="modal_offer_discount" value="15" min="0" max="100" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
            </div>

            <!-- Custom External Booking URL for this Offer -->
            <div class="p-3.5 bg-blue-50/70 rounded-xl border border-blue-200/80 space-y-1">
                <label class="block font-bold text-blue-950 uppercase text-[11px] flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-blue-700">open_in_new</span>
                    <span>Dedicated External Booking URL (Optional)</span>
                </label>
                <input type="url" name="external_url" id="modal_offer_external_url" placeholder="https://hotels.cloudbeds.com/reservation/XXXXX?promo=SPECIAL or leave blank" class="w-full font-mono border border-blue-300 rounded-lg p-2.5 text-xs bg-white focus:ring-2 focus:ring-blue-500">
                <p class="text-[10px] text-blue-800 leading-tight">
                    <strong>Note:</strong> If provided, clicking "Book With Offer" redirects directly to this specific link. If left blank, it defaults to your hotel's standard external booking engine or direct on-site booking.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 uppercase mb-1">Valid Until</label>
                    <input type="date" name="valid_to" id="modal_offer_valid_to" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs">
                </div>
                <div>
                    <?= render_image_uploader_field('image_url', '', 'Banner Image File / URL', 'offers', [
                        'required' => true,
                        'helper' => 'Upload image file or paste URL'
                    ]) ?>
                </div>
            </div>

            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeOfferModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save Offer</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchOfferLang(field, lang) {
    const langs = ['en', 'km', 'zh', 'ko'];
    langs.forEach(l => {
        const box = document.getElementById('offer-box-' + field + '-' + l);
        const btn = document.getElementById('offer-tab-' + field + '-' + l);
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

function openOfferModal(id, title, code, disc, desc, badge, img, validTo, transJson, externalUrl) {
    document.getElementById('modal_offer_id').value = id;
    document.getElementById('modal_offer_title').value = title || '';
    document.getElementById('modal_offer_code').value = code || '';
    document.getElementById('modal_offer_discount').value = disc || 0;
    document.getElementById('modal_offer_description').value = desc || '';
    document.getElementById('modal_offer_badge').value = badge || '';
    document.getElementById('modal_offer_external_url').value = externalUrl || '';
    
    const imgInput = document.querySelector('#offer-modal input[name="image_url"]');
    if (imgInput) {
        imgInput.value = img || '';
        imgInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.getElementById('modal_offer_valid_to').value = validTo || '';
    document.getElementById('offer-modal-title').textContent = (id > 0) ? 'Edit Special Offer' : 'Create Special Offer';

    // Parse Translations JSON
    let trans = {};
    try {
        trans = typeof transJson === 'string' ? JSON.parse(transJson || '{}') : (transJson || {});
    } catch(e) { trans = {}; }

    document.getElementById('modal_trans_km_offer_title').value = trans.km?.title || '';
    document.getElementById('modal_trans_zh_offer_title').value = trans.zh?.title || '';
    document.getElementById('modal_trans_ko_offer_title').value = trans.ko?.title || '';

    document.getElementById('modal_trans_km_offer_badge').value = trans.km?.badge_text || '';
    document.getElementById('modal_trans_zh_offer_badge').value = trans.zh?.badge_text || '';
    document.getElementById('modal_trans_ko_offer_badge').value = trans.ko?.badge_text || '';

    document.getElementById('modal_trans_km_offer_desc').value = trans.km?.description || '';
    document.getElementById('modal_trans_zh_offer_desc').value = trans.zh?.description || '';
    document.getElementById('modal_trans_ko_offer_desc').value = trans.ko?.description || '';

    // Reset tabs to EN
    switchOfferLang('title', 'en');
    switchOfferLang('badge', 'en');
    switchOfferLang('desc', 'en');

    document.getElementById('offer-modal').classList.remove('hidden');
}

function closeOfferModal() {
    document.getElementById('offer-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
