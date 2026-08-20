<?php
/**
 * Indra Hotel - Special Offer & Promotional Package Detail Page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);
$code = trim($_GET['code'] ?? '');

// Lookup Offer
$offer = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM special_offers WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $offer = $stmt->fetch();
} elseif (!empty($code)) {
    $stmt = $pdo->prepare("SELECT * FROM special_offers WHERE promo_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $offer = $stmt->fetch();
}

// Fallback to first active offer if not found
if (!$offer) {
    $stmt = $pdo->query("SELECT * FROM special_offers WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT 1");
    $offer = $stmt->fetch();
}

if (!$offer) {
    header('Location: ' . url('/offers'));
    exit;
}

$offerTitle = __td($offer, 'title', $offer['title']);
$offerBadge = __td($offer, 'badge_text', $offer['badge_text'] ?: 'Special Offer');
$offerDesc = __td($offer, 'description', $offer['description']);
$promoCode = $offer['promo_code'] ?? '';
$discount = (int)($offer['discount_percent'] ?? 0);
$validTo = !empty($offer['valid_to']) ? $offer['valid_to'] : null;
$imageUrl = !empty($offer['image_url']) ? $offer['image_url'] : 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1200&q=80';
$bookingUrl = get_offer_booking_url($offer);
$bookingTarget = get_booking_target();

// Dynamic Inclusions & Terms
$inclusionsText = __td($offer, 'inclusions', '');
$inclusionsList = [];
if (!empty($inclusionsText)) {
    $lines = explode("\n", $inclusionsText);
    foreach ($lines as $line) {
        $clean = trim($line);
        if (!empty($clean)) $inclusionsList[] = $clean;
    }
}
if (empty($inclusionsList)) {
    $inclusionsList = [
        'Daily Gourmet Breakfast for all registered guests',
        'Signature Welcome Drink & chilled refreshment upon arrival',
        '15% Privilege Savings at Khmer Spa & Wellness Center',
        'Late Check-out until 2:00 PM (subject to availability)',
        'Saltwater Pool & Gym unlimited private sanctuary access',
        'High-Speed Fiber Wi-Fi in all suites and public grounds'
    ];
}

$termsText = __td($offer, 'terms', '');
$termsList = [];
if (!empty($termsText)) {
    $lines = explode("\n", $termsText);
    foreach ($lines as $line) {
        $clean = trim($line);
        if (!empty($clean)) $termsList[] = $clean;
    }
}
if (empty($termsList)) {
    $termsList = [
        'Offer is valid for direct online bookings made via our official portal or authorized engine.',
        'Promotion cannot be combined with other ongoing vouchers or group tour rates.',
        'Subject to room category availability at the time of reservation.',
        'Free cancellation up to 48 hours prior to 2:00 PM check-in date.'
    ];
}

// Fetch other active offers
$stmtOther = $pdo->prepare("SELECT * FROM special_offers WHERE is_active = 1 AND id != ? ORDER BY display_order ASC, id ASC LIMIT 2");
$stmtOther->execute([$offer['id']]);
$otherOffers = $stmtOther->fetchAll();

$pageMeta = [
    'title' => $offerTitle . ' - Special Offers | ' . hotel_name(),
    'description' => substr(strip_tags($offerDesc), 0, 160),
    'image' => $imageUrl,
    'keywords' => $offerTitle . ', hotel discount phnom penh, ' . $promoCode . ', indra hotel promotions, tuol kork boutique stay',
    'type' => 'article'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs Navigation -->
<div class="bg-stone-100 border-b border-stone-200 py-3">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex items-center text-xs font-medium text-stone-500 space-x-2">
            <a href="<?= url('/home') ?>" class="hover:text-[#343c0a] transition"><?= __t('nav_home', 'Home') ?></a>
            <span class="material-symbols-outlined text-xs">chevron_right</span>
            <a href="<?= url('/offers') ?>" class="hover:text-[#343c0a] transition"><?= __t('nav_offers', 'Special Offers') ?></a>
            <span class="material-symbols-outlined text-xs">chevron_right</span>
            <span class="text-stone-800 font-semibold truncate max-w-[200px] sm:max-w-md"><?= e($offerTitle) ?></span>
        </nav>
    </div>
</div>

<!-- Hero Banner Header -->
<section class="relative bg-onyx-charcoal text-white py-16 sm:py-20 overflow-hidden">
    <div class="absolute inset-0 opacity-25">
        <img src="<?= e($imageUrl) ?>" alt="<?= e($offerTitle) ?> Background" class="w-full h-full object-cover">
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl space-y-4">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#dfe8a6] text-[#191e00]">
                    <?= e($offerBadge) ?>
                </span>
                <?php if ($discount > 0): ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500 text-white shadow-xs">
                    <?= $discount ?>% Discount Savings
                </span>
                <?php endif; ?>
                <?php if (!$offer['is_active']): ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-stone-600 text-stone-200">
                    Archived Promotion
                </span>
                <?php endif; ?>
            </div>

            <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white leading-tight">
                <?= e($offerTitle) ?>
            </h1>

            <div class="flex flex-wrap items-center gap-4 text-xs sm:text-sm text-stone-300 font-light pt-2">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-[#dfe8a6]">calendar_today</span>
                    <span><?= !empty($validTo) ? 'Valid until ' . format_date($validTo) : 'Ongoing Seasonal Privilege' ?></span>
                </div>
                <span class="hidden sm:inline text-stone-500">•</span>
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-[#dfe8a6]">verified</span>
                    <span>Best Direct Rate Guarantee</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Offer Details & Reservation Sidebar -->
<section class="py-14 sm:py-18 bg-[#fbfbf9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 sm:gap-12 items-start">
            
            <!-- Left Column: Image, Narrative & Package Inclusions -->
            <div class="lg:col-span-8 space-y-10 reveal reveal-left">
                
                <?php
                $offerImageFit = 'object-contain';
                if (!empty($offer['translations_json'])) {
                    $tData = json_decode($offer['translations_json'], true);
                    if (($tData['en']['image_fit'] ?? '') === 'cover') {
                        $offerImageFit = 'object-cover';
                    }
                }
                ?>
                <!-- Main Featured Photo Banner with Lightbox Trigger -->
                <div class="bg-transparent rounded-3xl overflow-hidden cursor-pointer group relative p-0 border-0 shadow-none"
                     onclick="openPhotoPreview([<?= e(json_encode($imageUrl)) ?>], 0, <?= e(json_encode($offerTitle)) ?>)">
                    <div class="relative w-full h-64 sm:h-[480px] flex items-center justify-center bg-transparent rounded-2xl overflow-hidden border-0 shadow-none">
                        <img src="<?= e($imageUrl) ?>" alt="<?= e($offerTitle) ?>" class="w-full h-full <?= $offerImageFit ?> rounded-2xl group-hover:scale-102 transition-transform duration-500 border-0 shadow-none">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition rounded-2xl flex items-end p-6">
                            <span class="inline-flex items-center gap-2 bg-black/75 backdrop-blur-md text-white text-xs font-bold px-4 py-2 rounded-lg border border-white/20">
                                <span class="material-symbols-outlined text-base">fullscreen</span>
                                <span>Click to View Fullscreen Photo Banner</span>
                            </span>
                        </div>
                    </div>
                </div>




                <!-- Package Narrative & Overview -->
                <div class="bg-white rounded-3xl p-6 sm:p-10 border border-stone-200 shadow-sm space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320] block mb-1">Package Overview</span>
                        <h2 class="font-headline text-2xl sm:text-3xl font-bold text-onyx-charcoal">About This Exclusive Offer</h2>
                    </div>

                    <div class="text-stone-600 text-sm sm:text-base leading-relaxed space-y-4">
                        <p><?= nl2br(e($offerDesc)) ?></p>
                    </div>

                    <!-- Package Highlights / Inclusions -->
                    <div class="pt-6 border-t border-stone-100">
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#343c0a]">stars</span>
                            <span>What's Included in This Package</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs sm:text-sm">
                            <?php foreach ($inclusionsList as $inc): ?>
                            <div class="flex items-start gap-2.5 p-3 rounded-xl bg-stone-50 border border-stone-100">
                                <span class="material-symbols-outlined text-base text-emerald-700 mt-0.5">check_circle</span>
                                <span class="text-stone-700"><?= e($inc) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Terms & Conditions Card -->
                    <div class="pt-6 border-t border-stone-100">
                        <h3 class="font-headline font-bold text-base text-onyx-charcoal mb-2">Terms & Reservation Policy</h3>
                        <ul class="list-disc list-inside text-xs text-stone-500 space-y-1.5 leading-relaxed">
                            <?php foreach ($termsList as $term): ?>
                                <li><?= e($term) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </div>

            </div>


            <!-- Right Column: Booking Widget & Promo Code Sidebar -->
            <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-28 reveal reveal-right">
                
                <!-- Action Booking Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-stone-200 shadow-xl space-y-6">
                    
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-widest font-bold text-[#4B5320]">Direct Privilege</span>
                            <?php if ($discount > 0): ?>
                            <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">
                                Save <?= $discount ?>%
                            </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-headline font-bold text-xl text-onyx-charcoal"><?= e($offerTitle) ?></h3>
                    </div>

                    <?php if (!empty($promoCode)): ?>
                    <!-- Promo Code Box with Copy Button -->
                    <div class="bg-amber-50/70 border border-amber-200/80 rounded-2xl p-4 space-y-2">
                        <span class="text-[10px] uppercase font-bold text-amber-900 tracking-wider block">Official Promotional Code</span>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-lg font-extrabold text-[#343c0a] tracking-wider"><?= e($promoCode) ?></span>
                            <button onclick="copyPromoCode('<?= e($promoCode) ?>')" id="copy-btn-<?= e($promoCode) ?>" 
                                    class="bg-white hover:bg-stone-50 border border-amber-300 text-stone-800 text-xs px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1 cursor-pointer shadow-2xs">
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                <span>Copy Code</span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Direct Booking CTA Button -->
                    <div class="space-y-3 pt-2">
                        <a href="<?= e($bookingUrl) ?>" target="<?= e($bookingTarget) ?>" 
                           class="w-full text-center bg-[#343c0a] hover:bg-deep-olive text-white py-4 rounded-xl font-bold text-sm tracking-wide transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer btn-shimmer">
                            <span class="material-symbols-outlined text-lg">calendar_month</span>
                            <span><?= e(get_booking_button_text('Book This Package Now')) ?></span>
                        </a>

                        <p class="text-[11px] text-stone-400 text-center flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-xs">lock</span>
                            <span>Direct Booking Guarantee • Instant Confirmation</span>
                        </p>
                    </div>

                    <!-- Concierge Assistance -->
                    <div class="pt-6 border-t border-stone-100 space-y-3">
                        <span class="text-xs font-bold uppercase text-stone-400 tracking-wider block">Need Assistance?</span>
                        <div class="space-y-2 text-xs text-stone-600">
                            <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="flex items-center gap-2 hover:text-[#343c0a] transition">
                                <span class="material-symbols-outlined text-base text-[#4B5320]">call</span>
                                <span><?= e(hotel_phone()) ?></span>
                            </a>
                            <a href="mailto:<?= e(hotel_email()) ?>" class="flex items-center gap-2 hover:text-[#343c0a] transition">
                                <span class="material-symbols-outlined text-base text-[#4B5320]">mail</span>
                                <span><?= e(hotel_email()) ?></span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</section>

<!-- Other Available Special Offers -->
<?php if (!empty($otherOffers)): ?>
<section class="py-16 bg-white border-t border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320] block mb-1">More Privileges</span>
                <h2 class="font-headline text-2xl sm:text-3xl font-bold text-onyx-charcoal">Other Special Offers</h2>
            </div>
            <a href="<?= url('/offers') ?>" class="text-xs font-bold text-[#343c0a] hover:underline flex items-center gap-1">
                <span>View All Offers</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <?php foreach ($otherOffers as $other): ?>
            <div class="bg-[#fbfbf9] rounded-2xl overflow-hidden border border-stone-200 shadow-sm flex flex-col sm:flex-row justify-between">
                <div class="sm:w-2/5 h-48 sm:h-auto overflow-hidden">
                    <img src="<?= e($other['image_url']) ?>" alt="<?= e($other['title']) ?>" class="w-full h-full object-cover">
                </div>
                <div class="p-6 sm:w-3/5 flex flex-col justify-between space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#4B5320]"><?= e(__td($other, 'badge_text', $other['badge_text'])) ?></span>
                            <?php if ($other['discount_percent'] > 0): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"><?= $other['discount_percent'] ?>% Off</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal"><?= e(__td($other, 'title', $other['title'])) ?></h3>
                        <p class="text-xs text-stone-600 line-clamp-2"><?= e(__td($other, 'description', $other['description'])) ?></p>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <a href="<?= url('/offer/' . $other['id']) ?>" class="flex-1 text-center px-3 py-2 border border-stone-300 hover:border-stone-400 text-stone-700 rounded-lg text-xs font-bold transition">
                            View Details
                        </a>
                        <a href="<?= e(get_offer_booking_url($other)) ?>" target="<?= e(get_booking_target()) ?>" class="flex-1 text-center px-3 py-2 bg-[#343c0a] hover:bg-deep-olive text-white rounded-lg text-xs font-bold transition">
                            Book
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
function copyPromoCode(code) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            alert('Promo Code "' + code + '" copied to clipboard!');
        });
    } else {
        const temp = document.createElement('textarea');
        temp.value = code;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        alert('Promo Code "' + code + '" copied to clipboard!');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
