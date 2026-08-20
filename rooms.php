<?php
/**
 * Indra Hotel - Rooms & Suites Catalog
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

$selectedCategory = trim($_GET['category'] ?? $_GET['filter'] ?? 'all');
$availableTypes = get_all_room_types();

$sql = "SELECT * FROM rooms WHERE status != 'maintenance'";
$params = [];

if ($selectedCategory !== 'all' && !empty($selectedCategory)) {
    if ($selectedCategory === 'featured') {
        $sql .= " AND is_featured = 1";
    } elseif ($selectedCategory === 'balcony') {
        $sql .= " AND LOWER(name) LIKE '%balcony%'";
    } else {
        $sql .= " AND (LOWER(category) = LOWER(?) OR LOWER(name) LIKE ?)";
        $params[] = $selectedCategory;
        $params[] = '%' . $selectedCategory . '%';
    }
}

$sql .= " ORDER BY display_order ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$pageMeta = [
    'title' => 'Luxury Accommodations & Conference Spaces | ' . hotel_name(),
    'description' => 'Explore premier accommodations, executive suites, and conference rooms at ' . hotel_name() . ' Phnom Penh. Book direct for the best rate guarantee.',
    'keywords' => 'hotel rooms phnom penh, suites with balcony phnom penh, conference room phnom penh, deluxe king room tuol kork, indra suite cambodia',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header Hero -->
<section class="bg-onyx-charcoal text-white py-16 sm:py-20 relative overflow-hidden">
    <div class="absolute inset-0 opacity-20">
        <img src="https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1600&q=80" 
             alt="Indra Hotel Rooms Background" class="w-full h-full object-cover">
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#dfe8a6] block mb-2"><?= __t('rooms_badge', 'Contemporary Sanctuary') ?></span>
        <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white mb-4">
            <?= __t('rooms_title', 'Rooms, Suites & Spaces') ?>
        </h1>
        <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto font-light leading-relaxed">
            <?= __t('rooms_subtitle', 'Thoughtfully designed accommodations, luxury suites, and executive conference spaces catering to the needs of discerning business and leisure guests.') ?>
        </p>
    </div>
</section>

<!-- Filter Tabs by Room Type -->
<section class="bg-white border-b border-stone-200 sticky top-20 z-30 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between py-4 overflow-x-auto gap-4">
            <div class="flex items-center gap-2 text-sm font-medium shrink-0">
                <a href="<?= BASE_URL ?>/rooms.php" 
                   class="px-4 py-2 rounded-full transition text-xs font-bold flex items-center gap-1.5 <?= ($selectedCategory === 'all') ? 'bg-[#343c0a] text-white shadow-xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                    <span class="material-symbols-outlined text-sm">apps</span>
                    <span><?= __t('rooms_view_all', 'All Accommodations') ?></span>
                </a>

                <?php foreach ($availableTypes as $rt): 
                    $isActive = (strcasecmp($selectedCategory, $rt['name']) === 0 || strcasecmp($selectedCategory, $rt['slug']) === 0);
                ?>
                <a href="<?= BASE_URL ?>/rooms.php?category=<?= urlencode($rt['name']) ?>" 
                   class="px-4 py-2 rounded-full transition text-xs font-bold flex items-center gap-1.5 <?= $isActive ? 'bg-[#343c0a] text-white shadow-xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                    <span class="material-symbols-outlined text-sm"><?= e($rt['icon']) ?></span>
                    <span><?= e($rt['name']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div class="text-xs text-stone-500 hidden sm:block shrink-0">
                Showing <strong><?= count($rooms) ?></strong> luxury choices
            </div>
        </div>
    </div>
</section>

<!-- Room Cards List -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <?php if (!empty($rooms)): ?>
            <?php 
            $k = 1;
            foreach ($rooms as $room): 
                $amenities = !empty($room['amenities_json']) ? json_decode($room['amenities_json'], true) : [];
                $roomTitle = __td($room, 'name', $room['name']);
                $roomTagline = __td($room, 'tagline', $room['tagline'] ?? 'Indra Hotel Collection');
                $roomDesc = __td($room, 'description', $room['description']);
            ?>
            <div class="bg-white rounded-3xl overflow-hidden border border-stone-200 shadow-sm hover:shadow-xl transition duration-500 luxury-card grid grid-cols-1 lg:grid-cols-12 group reveal reveal-up stagger-<?= ($k % 4) + 1; $k++; ?>">
                
                <!-- Room Image Column -->
                <div class="lg:col-span-6 relative h-72 sm:h-96 lg:h-auto overflow-hidden bg-stone-100">
                    <img src="<?= e($room['image_url']) ?>" 
                         alt="<?= e($roomTitle) ?> at Indra Hotel" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    
                    <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                        <?= get_room_type_badge($room['category'] ?? 'Deluxe Room') ?>
                        <span class="bg-black/60 backdrop-blur-md text-white text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider flex items-center gap-1 border border-white/20">
                            <span class="material-symbols-outlined text-xs">visibility</span>
                            <span><?= e($room['view_type'] ?? 'City View') ?></span>
                        </span>
                    </div>
                </div>

                <!-- Room Content Column -->
                <div class="lg:col-span-6 p-6 sm:p-8 lg:p-10 flex flex-col justify-between space-y-6">
                    <div>
                        <!-- Specs Summary Row -->
                        <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                            <div class="flex items-center gap-4 text-stone-600 text-xs font-semibold">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-base text-[#4B5320]">king_bed</span>
                                    <span><?= e($room['bed_type']) ?></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-base text-[#4B5320]">square_foot</span>
                                    <span><?= (int)$room['size_sqm'] ?> m²</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-base text-[#4B5320]">group</span>
                                    <span><?= (int)$room['capacity_adults'] ?> Adults</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-headline font-bold text-2xl sm:text-3xl text-[#343c0a]"><?= format_price($room['price_per_night']) ?></span>
                                <span class="text-xs text-stone-400 font-normal"> / <?= __t('rooms_per_night', 'night') ?></span>
                            </div>
                        </div>

                        <h2 class="font-headline font-bold text-2xl sm:text-3xl text-onyx-charcoal mt-4 mb-1.5">
                            <a href="<?= BASE_URL ?>/room.php?slug=<?= urlencode($room['slug']) ?>" class="hover:text-[#4B5320] transition">
                                <?= e($roomTitle) ?>
                            </a>
                        </h2>

                        <p class="text-xs font-semibold text-[#4B5320] uppercase tracking-wider mb-4">
                            <?= e($roomTagline) ?>
                        </p>

                        <p class="text-stone-600 text-sm leading-relaxed mb-6 line-clamp-3">
                            <?= e($roomDesc) ?>
                        </p>

                        <!-- Key Amenities Pills -->
                        <?php if (!empty($amenities)): ?>
                        <div class="space-y-2 mb-4">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-stone-400 block">Featured Amenities:</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach (array_slice($amenities, 0, 4) as $am): 
                                    $icon = get_amenity_icon($am);
                                ?>
                                    <span class="bg-stone-100 text-stone-700 text-xs px-2.5 py-1 rounded-lg font-medium flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs text-[#4B5320]"><?= e($icon) ?></span>
                                        <span><?= e($am) ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Price & Action Footer -->
                    <div class="pt-6 border-t border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <span class="text-xs text-stone-400 uppercase tracking-wider block">Direct Online Rate</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-2xl sm:text-3xl font-headline font-bold text-onyx-charcoal"><?= format_price($room['price_per_night']) ?></span>
                                <span class="text-xs text-stone-500 font-medium">/ night</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="<?= BASE_URL ?>/room.php?slug=<?= urlencode($room['slug']) ?>" 
                               class="flex-1 sm:flex-initial text-center px-4 py-2.5 border border-stone-300 hover:border-stone-400 text-stone-700 rounded-xl text-xs font-bold transition">
                                Details & Photos
                            </a>
                            <a href="<?= e(get_booking_url((int)$room['id'])) ?>" target="<?= e(get_booking_target()) ?>" 
                               class="flex-1 sm:flex-initial text-center px-6 py-2.5 bg-[#343c0a] hover:bg-deep-olive text-white rounded-xl text-xs font-bold tracking-wide transition shadow-sm cursor-pointer btn-shimmer">
                                <?= e(get_booking_button_text('Book Now')) ?>
                            </a>
                        </div>
                    </div>

                </div>

            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-white rounded-3xl p-16 text-center border border-stone-200 space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-stone-100 text-stone-400 flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-3xl">hotel</span>
                </div>
                <h3 class="font-headline font-bold text-xl text-onyx-charcoal">No Accommodations Found</h3>
                <p class="text-xs text-stone-500 max-w-md mx-auto">There are currently no accommodations listed under the "<?= e($selectedCategory) ?>" category.</p>
                <a href="<?= BASE_URL ?>/rooms.php" class="inline-flex items-center gap-1 text-xs font-bold text-[#343c0a] hover:underline">
                    <span>View All Accommodations</span>
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
