<?php
/**
 * Indra Hotel - Gym & Wellness Showcase
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM dining_wellness WHERE type = 'wellness' AND is_published = 1 ORDER BY display_order ASC");
$wellnessVenues = $stmt->fetchAll();

$pageMeta = [
    'title' => 'Gym & Wellness | Fitness Center, Saltwater Pool & Spa | Indra Hotel',
    'description' => 'Rejuvenate your body and mind at Indra Hotel Phnom Penh. Fully equipped fitness center, saltwater pool, and traditional Khmer herbal massage treatments.',
    'keywords' => 'hotel gym phnom penh, saltwater pool tuol kork, khmer massage phnom penh, wellness hotel cambodia',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<section class="bg-onyx-charcoal text-white py-16 sm:py-20 relative overflow-hidden">
    <div class="absolute inset-0 opacity-30">
        <img src="https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5" 
             alt="Fitness and Wellness at Indra Hotel" class="w-full h-full object-cover">
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#dfe8a6] block mb-2">Restoration & Vitality</span>
        <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white mb-4">
            Gym & Wellness
        </h1>
        <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto font-light leading-relaxed">
            Stay fit with our state-of-the-art gym, take a dip in our serene saltwater pool, and soothe tension with traditional Khmer herbal therapy.
        </p>
    </div>
</section>

<!-- Facilities & Spa Menu Section -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <?php 
        $w = 1;
        foreach ($wellnessVenues as $item): 
            $treatments = !empty($item['menu_items_json']) ? json_decode($item['menu_items_json'], true) : [];
        ?>
        <div class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 reveal reveal-up stagger-<?= ($w % 2) + 1; $w++; ?>">
            
            <div class="lg:col-span-6 h-80 lg:h-auto min-h-[400px] relative overflow-hidden">
                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-700">
                <div class="absolute bottom-4 left-4 right-4 bg-black/70 backdrop-blur-md p-4 rounded-xl text-white text-xs flex justify-between items-center">
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-[#dfe8a6]">schedule</span>Open Daily: <?= e($item['hours'] ?? '07:00 AM - 09:00 PM') ?></span>
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-[#dfe8a6]">info</span><?= e($item['price_range'] ?? 'Complimentary for Guests') ?></span>
                </div>
            </div>

            <div class="lg:col-span-6 p-8 sm:p-10 flex flex-col justify-between space-y-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#4B5320] block mb-1">Wellness Sanctuary</span>
                    <h2 class="font-headline text-3xl font-bold text-onyx-charcoal mb-3"><?= e($item['title']) ?></h2>
                    <p class="text-stone-600 text-sm leading-relaxed mb-6"><?= e($item['description']) ?></p>

                    <!-- Treatment & Services Menu -->
                    <?php if (!empty($treatments)): ?>
                    <div id="spa" class="space-y-4 pt-4 border-t border-stone-100">
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4B5320]">spa</span>
                            <span>Spa & Fitness Services</span>
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($treatments as $trt): ?>
                            <div class="p-3.5 rounded-lg bg-stone-50 border border-stone-100 flex justify-between items-start gap-4">
                                <div>
                                    <div class="font-headline font-semibold text-stone-900 text-sm"><?= e($trt['name']) ?></div>
                                    <div class="text-xs text-stone-500 mt-0.5 leading-relaxed"><?= e($trt['desc'] ?? '') ?></div>
                                </div>
                                <span class="font-headline font-bold text-sm text-[#343c0a] whitespace-nowrap"><?= e($trt['price']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="pt-6 border-t border-stone-200 flex flex-wrap gap-4">
                    <a href="<?= BASE_URL ?>/contact.php?subject=Spa+Booking" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded text-sm font-semibold transition btn-shimmer">
                        Book a Treatment
                    </a>
                    <a href="<?= e(get_booking_url()) ?>" target="<?= e(get_booking_target()) ?>" class="border border-stone-300 hover:border-stone-400 text-stone-700 px-5 py-2.5 rounded text-sm font-medium transition flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">hotel</span>
                        <span><?= e(get_booking_button_text('Book Room & Spa')) ?></span>
                    </a>
                </div>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
