<?php
/**
 * Indra Hotel - Special Offers & Packages
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM special_offers WHERE is_active = 1 ORDER BY display_order ASC");
$offers = $stmt->fetchAll();

$pageMeta = [
    'title' => 'Special Offers & Packages | Indra Hotel Phnom Penh',
    'description' => 'Unlock exclusive promotional rates, romantic packages, and extended stay discounts at Indra Hotel Phnom Penh. Best rate guarantee when booking direct.',
    'keywords' => 'hotel promotions phnom penh, indra hotel discounts, direct booking promo code cambodia, hotel packages tuol kork',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<?= render_public_page_hero('offers', [
    'badge' => 'Exclusive Privilege',
    'title' => 'Special Offers & Packages',
    'subtitle' => 'Curated stay packages, seasonal discounts, and exclusive direct booking privileges at Indra Hotel.',
    'image' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1600&q=80',
    'icon' => 'local_offer'
]) ?>


<!-- Offers Grid -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php 
            $m = 1;
            foreach ($offers as $offer): 
            ?>

            <div class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-sm luxury-card flex flex-col justify-between reveal reveal-up stagger-<?= ($m % 3) + 1; $m++; ?>">
                <div>
                    <a href="<?= url('/offer/' . $offer['id']) ?>" class="block relative h-60 overflow-hidden group">
                        <img src="<?= e($offer['image_url']) ?>" alt="<?= e(__td($offer, 'title', $offer['title'])) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute top-4 left-4 bg-[#343c0a] text-[#dfe8a6] text-xs font-bold px-3 py-1.5 rounded uppercase tracking-wider shadow">
                            <?= e(__td($offer, 'badge_text', $offer['badge_text'] ?: 'Special Deal')) ?>
                        </div>
                        <?php if ($offer['discount_percent'] > 0): ?>
                        <div class="absolute top-4 right-4 bg-rose-600 text-white text-xs font-bold px-2.5 py-1.5 rounded shadow">
                            <?= $offer['discount_percent'] ?>% OFF
                        </div>
                        <?php endif; ?>
                    </a>






                    <div class="p-6 sm:p-8 space-y-4">
                        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal hover:text-[#343c0a] transition">
                            <a href="<?= url('/offer/' . $offer['id']) ?>">
                                <?= e(__td($offer, 'title', $offer['title'])) ?>
                            </a>
                        </h2>
                        <p class="text-stone-600 text-sm leading-relaxed"><?= e(__td($offer, 'description', $offer['description'])) ?></p>
                        
                        <?php if (!empty($offer['valid_to'])): ?>
                        <div class="text-xs text-stone-500 flex items-center gap-1.5 pt-2">
                            <span class="material-symbols-outlined text-sm text-[#4B5320]">event</span>
                            <span>Valid until <?= format_date($offer['valid_to']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-6 sm:p-8 pt-0 border-t border-stone-100 flex flex-col gap-3">
                    <?php if (!empty($offer['promo_code'])): ?>
                    <div class="flex items-center justify-between bg-stone-50 p-3 rounded-lg border border-stone-200">
                        <div>
                            <span class="text-[10px] uppercase tracking-wider text-stone-400 block">Promo Code</span>
                            <span class="font-headline font-bold text-sm text-stone-800 tracking-wider"><?= e($offer['promo_code']) ?></span>
                        </div>
                        <button onclick="copyPromoCode('<?= e($offer['promo_code']) ?>')" class="text-xs bg-white hover:bg-stone-100 border border-stone-300 text-stone-700 px-2.5 py-1.5 rounded font-semibold transition flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-xs">content_copy</span>
                            <span>Copy</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-2">
                        <a href="<?= url('/offer/' . $offer['id']) ?>" class="flex-1 text-center px-4 py-3 border border-stone-300 hover:border-stone-400 text-stone-700 rounded font-bold text-xs tracking-wide transition">
                            Offer Details
                        </a>
                        <a href="<?= e(get_offer_booking_url($offer)) ?>" target="<?= e(get_booking_target()) ?>" class="flex-1 text-center bg-[#343c0a] hover:bg-deep-olive text-white py-3 rounded font-bold text-xs tracking-wide transition shadow-sm btn-shimmer">
                            <?= e(get_booking_button_text('Book Offer')) ?>
                        </a>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
