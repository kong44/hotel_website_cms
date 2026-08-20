<?php
/**
 * Indra Hotel - Single Room Detail Page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$slug = $_GET['slug'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $room = $stmt->fetch();
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $room = $stmt->fetch();
} else {
    // Default to first room
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY display_order ASC LIMIT 1");
    $room = $stmt->fetch();
}

if (!$room) {
    header('Location: ' . url('/rooms'));
    exit;
}

$amenities = !empty($room['amenities_json']) ? json_decode($room['amenities_json'], true) : [];
$gallery = !empty($room['gallery_json']) ? json_decode($room['gallery_json'], true) : [$room['image_url']];

// Fetch other rooms for recommendation
$stmtOther = $pdo->prepare("SELECT * FROM rooms WHERE id != ? AND status != 'maintenance' ORDER BY display_order ASC LIMIT 2");
$stmtOther->execute([$room['id']]);
$otherRooms = $stmtOther->fetchAll();

$pageMeta = [
    'title' => $room['name'] . ' | Indra Hotel Phnom Penh',
    'description' => $room['description'],
    'keywords' => $room['name'] . ', Indra Hotel suite, boutique luxury room Phnom Penh, Tuol Kork accommodation',
    'image' => $room['image_url'],
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Schema.org HotelRoom Structured Data -->
<?= SEO::getRoomSchema($room) ?>

<!-- Breadcrumb Navigation -->
<div class="bg-white border-b border-stone-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex items-center space-x-2 text-xs font-medium text-stone-500">
            <a href="<?= url('/home') ?>" class="hover:text-[#343c0a]">Home</a>
            <span class="text-stone-300">/</span>
            <a href="<?= url('/rooms') ?>" class="hover:text-[#343c0a]">Accommodations</a>
            <span class="text-stone-300">/</span>
            <span class="text-stone-900 font-semibold"><?= e($room['name']) ?></span>
        </nav>
    </div>
</div>

<!-- Main Room Detail Section -->
<section class="py-10 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Room Title & Key Badges Header -->
        <div class="mb-8">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <?= get_room_type_badge($room['category'] ?? 'Deluxe Room') ?>
                <span class="text-xs font-bold uppercase tracking-wider text-[#4B5320] bg-[#dfe8a6]/40 px-3 py-1 rounded-full border border-[#dfe8a6]">
                    <?= e($room['view_type'] ?? 'City View') ?>
                </span>
                <span class="text-xs font-medium text-stone-500 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm text-[#4B5320]">verified</span> Official Best Rate Guarantee
                </span>
            </div>
            <h1 class="font-headline text-3xl sm:text-4xl lg:text-5xl font-bold text-onyx-charcoal tracking-tight">
                <?= e(__td($room, 'name', $room['name'])) ?>
            </h1>
            <p class="text-stone-500 text-base mt-2 font-normal">
                <?= e(__td($room, 'tagline', $room['tagline'] ?? 'Indra Hotel Luxury Sanctuary')) ?>
            </p>
        </div>

        <script>
        var activePhotoIndex = 0;
        var roomGalleryData = <?= json_encode(array_map(function($url) use ($room) {
            return [
                'url' => $url,
                'title' => __td($room, 'name', $room['name']),
                'caption' => __td($room, 'tagline', $room['tagline'] ?: 'Indra Hotel Accommodations')
            ];
        }, $gallery)) ?>;

        function selectRoomThumbnail(imgUrl, index) {
            var mainImg = document.getElementById('main-room-photo');
            if (mainImg) mainImg.src = imgUrl;
            activePhotoIndex = index;
        }

        function openRoomPhotoPreview(index) {
            if (typeof index === 'undefined') {
                index = activePhotoIndex || 0;
            }
            if (window.openPhotoPreview) {
                window.openPhotoPreview(roomGalleryData, index, <?= json_encode(__td($room, 'name', $room['name'])) ?>);
            }
        }
        </script>

        <!-- Room Photos Grid Gallery with Interactive Preview -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-12">
            <!-- Main Featured Image (Clickable for Lightbox) -->
            <div class="lg:col-span-8 rounded-2xl overflow-hidden shadow-sm border border-stone-200 h-80 sm:h-[480px] relative group cursor-pointer"
                 onclick="openRoomPhotoPreview(activePhotoIndex)">
                <img id="main-room-photo" src="<?= e($gallery[0] ?? $room['image_url']) ?>" 
                     alt="<?= e(__td($room, 'name', $room['name'])) ?> Primary View" 
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                
                <!-- Floating Expand Button -->
                <button type="button" onclick="event.stopPropagation(); openRoomPhotoPreview(0);" class="absolute bottom-4 right-4 bg-black/75 hover:bg-[#343c0a] backdrop-blur-md text-white text-xs font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-lg border border-white/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">photo_library</span>
                    <span>View All Photos (<?= count($gallery) ?>)</span>
                </button>

                <!-- Hover Zoom Overlay Indicator -->
                <div class="absolute inset-0 bg-black/15 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                    <div class="w-12 h-12 rounded-full bg-black/60 text-white flex items-center justify-center backdrop-blur-md">
                        <span class="material-symbols-outlined text-2xl">zoom_in</span>
                    </div>
                </div>
            </div>

            <!-- Thumbnail Side Column -->
            <div class="lg:col-span-4 grid grid-cols-2 lg:grid-cols-1 gap-4">
                <?php foreach (array_slice($gallery, 1, 2) as $thumbIdx => $img): ?>
                <div class="rounded-xl overflow-hidden shadow-xs border border-stone-200 h-36 sm:h-[232px] cursor-pointer group relative"
                     onclick="selectRoomThumbnail('<?= e($img) ?>', <?= $thumbIdx + 1 ?>); openRoomPhotoPreview(<?= $thumbIdx + 1 ?>);">
                    <img src="<?= e($img) ?>" alt="Suite Interior" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-black/25 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
                        <div class="w-10 h-10 rounded-full bg-black/60 flex items-center justify-center backdrop-blur-md">
                            <span class="material-symbols-outlined text-xl">zoom_in</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content & Sticky Booking Form Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            <!-- Left 8 Columns: Details & Amenities -->
            <div class="lg:col-span-8 space-y-10">
                
                <!-- Quick Specs Strip -->
                <div class="bg-white rounded-xl p-6 border border-stone-200 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                    <div class="p-2">
                        <span class="material-symbols-outlined text-[#4B5320] text-2xl mb-1">square_foot</span>
                        <div class="text-xs text-stone-400 font-medium uppercase"><?= __t('rooms_size', 'Room Size') ?></div>
                        <div class="font-headline font-bold text-base text-stone-800"><?= $room['size_sqm'] ?> m²</div>
                    </div>
                    <div class="p-2 border-l border-stone-100">
                        <span class="material-symbols-outlined text-[#4B5320] text-2xl mb-1">bed</span>
                        <div class="text-xs text-stone-400 font-medium uppercase"><?= __t('rooms_bed', 'Bed Type') ?></div>
                        <div class="font-headline font-bold text-base text-stone-800"><?= e($room['bed_type']) ?></div>
                    </div>
                    <div class="p-2 border-l border-stone-100">
                        <span class="material-symbols-outlined text-[#4B5320] text-2xl mb-1">group</span>
                        <div class="text-xs text-stone-400 font-medium uppercase"><?= __t('rooms_capacity', 'Capacity') ?></div>
                        <div class="font-headline font-bold text-base text-stone-800"><?= (int)$room['capacity_adults'] ?> Adults, <?= (int)$room['capacity_children'] ?> Child</div>
                    </div>
                    <div class="p-2 border-l border-stone-100">
                        <span class="material-symbols-outlined text-[#4B5320] text-2xl mb-1">visibility</span>
                        <div class="text-xs text-stone-400 font-medium uppercase"><?= __t('rooms_view', 'View') ?></div>
                        <div class="font-headline font-bold text-base text-stone-800"><?= e($room['view_type']) ?></div>
                    </div>
                </div>

                <!-- Description Narrative -->
                <div class="space-y-4">
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Suite Overview</h2>
                    <p class="text-stone-600 text-base leading-relaxed">
                        <?= nl2br(e(__td($room, 'description', $room['description']))) ?>
                    </p>
                </div>

                <!-- Comprehensive Amenities List -->
                <?php if (!empty($amenities)): ?>
                <div class="space-y-6 pt-6 border-t border-stone-200">
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Room Amenities & Features</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($amenities as $amenity): 
                            $aIcon = get_amenity_icon($amenity);
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-white border border-stone-100 shadow-2xs hover:border-[#343c0a]/30 transition">
                            <div class="w-8 h-8 rounded-lg bg-[#dfe8a6]/40 text-[#343c0a] flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-base"><?= e($aIcon) ?></span>
                            </div>
                            <span class="text-sm font-medium text-stone-800"><?= e($amenity) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hotel Policies -->
                <div class="space-y-4 pt-6 border-t border-stone-200">
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Policies & House Rules</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-stone-600">
                        <div class="p-4 rounded-lg bg-white border border-stone-200 space-y-1">
                            <span class="font-bold text-stone-800 block">Check-in / Check-out</span>
                            <div>Check-in: <strong><?= HOTEL_CHECKIN_TIME ?></strong></div>
                            <div>Check-out: <strong><?= HOTEL_CHECKOUT_TIME ?></strong></div>
                        </div>
                        <div class="p-4 rounded-lg bg-white border border-stone-200 space-y-1">
                            <span class="font-bold text-stone-800 block">Cancellation Policy</span>
                            <div>Free cancellation up to 24 hours prior to check-in.</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right 4 Columns: Sticky Live Reservation Widget -->
            <div class="lg:col-span-4">
                <div class="sticky top-28 bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-xl space-y-6">
                    
                    <div class="flex items-baseline justify-between pb-4 border-b border-stone-100">
                        <div>
                            <span class="text-xs text-stone-400 font-medium uppercase tracking-wider block">Nightly Rate</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-headline font-bold text-onyx-charcoal"><?= format_price($room['price_per_night']) ?></span>
                                <span class="text-xs text-stone-500 font-medium">/ night</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded">
                            Available
                        </span>
                    </div>

                    <!-- Direct Reservation Form -->
                    <form action="<?= url('/book') ?>" method="GET" target="<?= e(get_booking_target()) ?>" class="space-y-4">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>" id="booking_room_select" data-price="<?= $room['price_per_night'] ?>">
                        
                        <div>
                            <label class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Check-in Date</label>
                            <input type="date" name="check_in" id="booking_check_in" required
                                   class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Check-out Date</label>
                            <input type="date" name="check_out" id="booking_check_out" required
                                   class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Adults</label>
                                <select name="adults" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                                    <option value="1">1 Adult</option>
                                    <option value="2" selected>2 Adults</option>
                                    <option value="3">3 Adults</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Children</label>
                                <select name="children" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                                    <option value="0" selected>0 Child</option>
                                    <option value="1">1 Child</option>
                                    <option value="2">2 Children</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Promo Code</label>
                            <input type="text" name="promo" id="booking_promo_code" placeholder="e.g. INDRA15"
                                   class="w-full text-sm uppercase border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        </div>

                        <!-- Live Price Breakdown -->
                        <div class="p-4 rounded-lg bg-stone-50 border border-stone-100 text-xs space-y-2 text-stone-600">
                            <div class="flex justify-between">
                                <span>Duration:</span>
                                <strong id="calculated_nights" class="text-stone-800">1 night</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Room Rate:</span>
                                <span><?= format_price($room['price_per_night']) ?> / night</span>
                            </div>
                            <div id="booking_discount_row" class="flex justify-between text-emerald-700 hidden">
                                <span>Discount applied:</span>
                                <strong id="calculated_discount">-</strong>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-stone-200 text-sm font-bold text-stone-900">
                                <span>Estimated Total:</span>
                                <span id="calculated_total_price"><?= format_price($room['price_per_night']) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white py-3.5 rounded-lg font-bold text-sm tracking-wide transition shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">calendar_month</span>
                            <span><?= e(get_booking_button_text('Reserve This Suite')) ?></span>
                        </button>
                    </form>

                    <div class="text-[11px] text-stone-400 text-center flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-xs text-stone-400">lock</span>
                        <span>Direct Booking Guarantee & Instant Confirmation</span>
                    </div>

                </div>
            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
