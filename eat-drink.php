<?php
/**
 * Indra Hotel - Eat & Drink Showcase
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM dining_wellness WHERE type = 'dining' AND is_published = 1 ORDER BY display_order ASC");
$diningVenues = $stmt->fetchAll();

$pageMeta = [
    'title' => 'Eat & Drink | The Bistro & Cafe at Indra Hotel Phnom Penh',
    'description' => 'Indulge in a range of culinary experiences at Indra Hotel dining venues, featuring international and Asian-fusion cuisine with a contemporary twist.',
    'keywords' => 'restaurant tuol kork, dining phnom penh, bistro cafe indra hotel, asian fusion khmer food',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<section class="bg-onyx-charcoal text-white py-16 sm:py-20 relative overflow-hidden">
    <div class="absolute inset-0 opacity-30">
        <img src="https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho" 
             alt="Indra Hotel Bistro Background" class="w-full h-full object-cover">
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#dfe8a6] block mb-2">Culinary Artistry</span>
        <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white mb-4">
            Eat & Drink
        </h1>
        <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto font-light leading-relaxed">
            From artisanal morning coffee to sunset cocktails and fine Asian-fusion dining, indulge in an elevated gastronomic atmosphere.
        </p>
    </div>
</section>

<!-- Dining Venues & Menu Lists -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <?php 
        $d = 1;
        foreach ($diningVenues as $venue): 
            $menuItems = !empty($venue['menu_items_json']) ? json_decode($venue['menu_items_json'], true) : [];
        ?>
        <div class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 reveal reveal-up stagger-<?= ($d % 2) + 1; $d++; ?>">
            
            <div class="lg:col-span-6 h-80 lg:h-auto min-h-[400px] relative overflow-hidden">
                <img src="<?= e($venue['image_url']) ?>" alt="<?= e($venue['title']) ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-700">
                <div class="absolute bottom-4 left-4 right-4 bg-black/70 backdrop-blur-md p-4 rounded-xl text-white text-xs flex justify-between items-center">
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-[#dfe8a6]">schedule</span><?= e($venue['hours'] ?? '06:30 AM - 10:30 PM') ?></span>
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-[#dfe8a6]">payments</span><?= e($venue['price_range'] ?? '$$ - $$$') ?></span>
                </div>
            </div>

            <div class="lg:col-span-6 p-8 sm:p-10 flex flex-col justify-between space-y-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#4B5320] block mb-1">Featured Venue</span>
                    <h2 class="font-headline text-3xl font-bold text-onyx-charcoal mb-3"><?= e($venue['title']) ?></h2>
                    <p class="text-stone-600 text-sm leading-relaxed mb-6"><?= e($venue['description']) ?></p>

                    <!-- Signature Menu Items -->
                    <?php if (!empty($menuItems)): ?>
                    <div class="space-y-4 pt-4 border-t border-stone-100">
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4B5320]">restaurant_menu</span>
                            <span>Chef's Signature Dishes</span>
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($menuItems as $dish): ?>
                            <div class="p-3.5 rounded-lg bg-stone-50 border border-stone-100 flex justify-between items-start gap-4">
                                <div>
                                    <div class="font-headline font-semibold text-stone-900 text-sm"><?= e($dish['name']) ?></div>
                                    <div class="text-xs text-stone-500 mt-0.5 leading-relaxed"><?= e($dish['desc'] ?? '') ?></div>
                                </div>
                                <span class="font-headline font-bold text-sm text-[#343c0a] whitespace-nowrap"><?= e($dish['price']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="pt-6 border-t border-stone-200 flex flex-wrap gap-4">
                    <a href="<?= BASE_URL ?>/contact.php?subject=Table+Reservation" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded text-sm font-semibold transition btn-shimmer">
                        Reserve a Table
                    </a>
                    <a href="tel:<?= HOTEL_PHONE_RAW ?>" class="border border-stone-300 hover:border-stone-400 text-stone-700 px-5 py-2.5 rounded text-sm font-medium transition flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">call</span>
                        <span>Direct Dial Dining</span>
                    </a>
                </div>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
