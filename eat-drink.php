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
<?= render_public_page_hero('dining', [
    'badge' => 'Culinary Artistry',
    'title' => 'Eat & Drink',
    'subtitle' => 'From artisanal morning coffee to sunset cocktails and fine Asian-fusion dining, indulge in an elevated gastronomic atmosphere.',
    'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80',
    'icon' => 'restaurant'
]) ?>


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
                    <a href="<?= url('/contact', ['subject' => 'Table Reservation']) ?>" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2.5 rounded text-sm font-semibold transition btn-shimmer">
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
