<?php
/**
 * Indra Hotel - Location & Phnom Penh City Guide
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

// Location Settings
$locBadge = get_setting('location_badge', 'Prime Tuol Kork Setting');
$locTitle = get_setting('location_title', 'Location & City Guide');
$locSubtitle = get_setting('location_subtitle', 'Nestled in the upscale, tranquil district of Tuol Kork with effortless connectivity to Phnom Penh\'s premier cultural landmarks.');
$locAirportInfo = get_setting('location_airport_info', 'Phnom Penh International Airport (PNH) is approximately a 30-minute drive away. Private VIP chauffeured transfers can be arranged directly with our concierge team.');
$locMapEmbed = get_setting('location_map_embed', 'https://maps.google.com/maps?q=' . HOTEL_LATITUDE . ',' . HOTEL_LONGITUDE . '&hl=en&z=15&output=embed');

// Query All Location Spots
try {
    $stmtSpots = $pdo->query("SELECT * FROM location_spots ORDER BY display_order ASC, id ASC");
    $allSpots = $stmtSpots->fetchAll();
} catch (Throwable $e) {
    $allSpots = [];
}

$pageMeta = [
    'title' => $locTitle . ' | ' . hotel_name() . ' Phnom Penh',
    'description' => $locSubtitle,
    'keywords' => 'indra hotel location, where is indra hotel phnom penh, tuol kork hotels, attractions near indra hotel',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<?= render_public_page_hero('location', [
    'badge' => $locBadge,
    'title' => $locTitle,
    'subtitle' => $locSubtitle,
    'image' => 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=1600&q=80',
    'icon' => 'pin_drop'
]) ?>


<!-- Location Information & Interactive Map Section -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mb-16">
            
            <!-- Address & Transportation Card -->
            <div class="lg:col-span-5 space-y-6 reveal reveal-left">
                <div class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-6">
                    <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320] block">Hotel Address</span>
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal"><?= e(hotel_name()) ?></h2>
                    
                    <div class="space-y-3 text-sm text-stone-600">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">location_on</span>
                            <span><?= e(hotel_address()) ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl">phone</span>
                            <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="hover:text-stone-900 font-medium"><?= e(hotel_phone()) ?></a>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl">mail</span>
                            <a href="mailto:<?= e(hotel_email()) ?>" class="hover:text-stone-900 font-medium"><?= e(hotel_email()) ?></a>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-stone-100 space-y-3">
                        <h3 class="font-headline font-bold text-sm text-stone-900">Airport & Transfer Options</h3>
                        <p class="text-xs text-stone-500 leading-relaxed">
                            <?= nl2br(e($locAirportInfo)) ?>
                        </p>
                    </div>

                    <a href="https://maps.google.com/?q=<?= urlencode(hotel_name() . ' ' . hotel_address()) ?>" target="_blank" rel="noopener noreferrer" 
                       class="w-full text-center bg-[#343c0a] hover:bg-deep-olive text-white py-3 rounded-lg font-bold text-sm tracking-wide transition shadow-sm flex items-center justify-center gap-2 cursor-pointer btn-shimmer">
                        <span class="material-symbols-outlined text-base">directions</span>
                        <span>Open in Google Maps</span>
                    </a>
                </div>
            </div>

            <!-- Map View Container -->
            <div class="lg:col-span-7 rounded-2xl overflow-hidden shadow-sm border border-stone-200 min-h-[420px] bg-stone-200 reveal reveal-right">
                <iframe 
                    title="Indra Hotel Map Location"
                    class="w-full h-full min-h-[420px]"
                    src="<?= e($locMapEmbed) ?>"
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>

        </div>

        <!-- Neighborhood Attractions Grid -->
        <div class="space-y-8">
            <div class="text-center max-w-2xl mx-auto reveal reveal-up">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">What's Nearby</span>
                <h2 class="font-headline text-3xl font-bold text-onyx-charcoal mt-1">Phnom Penh Attractions</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (!empty($allSpots)): ?>
                    <?php 
                    $n = 1;
                    foreach ($allSpots as $spot): 
                        $spotTitle = __td($spot, 'title', $spot['title']);
                        $spotDesc = __td($spot, 'description', $spot['description']);
                        $spotIcon = !empty($spot['icon']) ? $spot['icon'] : 'location_on';
                        $spotSlug = !empty($spot['slug']) ? $spot['slug'] : $spot['id'];
                        $spotImg = !empty($spot['image_url']) ? $spot['image_url'] : 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=800&q=80';
                    ?>
                    <a href="<?= url('/location/' . urlencode($spotSlug)) ?>" 
                       class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-2xs hover:shadow-xl hover:border-[#343c0a]/40 transition duration-300 flex flex-col justify-between group reveal reveal-scale stagger-<?= ($n % 3) + 1; $n++; ?>">
                        <div>
                            <!-- Photo on Top of Card -->
                            <div class="h-52 relative overflow-hidden bg-stone-100">
                                <img src="<?= e($spotImg) ?>" alt="<?= e($spotTitle) ?>" class="w-full h-full object-cover group-hover:scale-108 transition duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent"></div>
                                
                                <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-1.5">
                                    <span class="inline-flex items-center gap-1 bg-black/60 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full border border-white/20">
                                        <span class="material-symbols-outlined text-sm text-[#dfe8a6]"><?= e($spotIcon) ?></span>
                                        <span><?= e($spot['distance_time']) ?></span>
                                    </span>
                                    <?php 
                                    $spotGallery = [$spotImg];
                                    if (!empty($spot['gallery_json'])) {
                                        $gDecoded = json_decode($spot['gallery_json'], true);
                                        if (is_array($gDecoded)) {
                                            foreach ($gDecoded as $gUrl) {
                                                if (!empty($gUrl) && !in_array($gUrl, $spotGallery)) $spotGallery[] = $gUrl;
                                            }
                                        }
                                    }
                                    ?>
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); openPhotoPreview(<?= e(json_encode($spotGallery)) ?>, 0, <?= e(json_encode($spotTitle)) ?>)"
                                            class="bg-black/60 hover:bg-black text-white text-xs font-bold px-2.5 py-1 rounded-full border border-white/20 backdrop-blur-md flex items-center gap-1 transition cursor-pointer"
                                            title="View Fullscreen Photos">
                                        <span class="material-symbols-outlined text-xs">fullscreen</span>
                                        <span>View Photo</span>
                                    </button>
                                </div>

                                <div class="absolute bottom-3 left-4 right-4 text-white">
                                    <h3 class="font-headline font-bold text-xl text-white group-hover:text-[#dfe8a6] transition"><?= e($spotTitle) ?></h3>
                                </div>
                            </div>


                            <!-- Content Body -->
                            <div class="p-6 space-y-3">
                                <p class="text-xs sm:text-sm text-stone-500 line-clamp-3 leading-relaxed">
                                    <?= e($spotDesc) ?>
                                </p>
                            </div>
                        </div>

                        <div class="px-6 pb-6 pt-2 border-t border-stone-100 flex items-center justify-between text-xs font-bold text-[#343c0a]">
                            <span>Explore Landmark Guide</span>
                            <span class="material-symbols-outlined text-sm group-hover:translate-x-1.5 transition">arrow_forward</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-xl p-6 border border-stone-200 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="material-symbols-outlined text-3xl text-[#4B5320]">temple_buddhist</span>
                            <span class="text-xs font-bold bg-stone-100 text-stone-700 px-2.5 py-1 rounded">10 mins drive</span>
                        </div>
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal">Wat Phnom Sanctuary</h3>
                        <p class="text-xs text-stone-500 leading-relaxed">
                            A revered 14th-century Buddhist pagoda standing at 27 meters tall, Wat Phnom is the city's historical heart and legendary founding site.
                        </p>
                    </div>

                    <div class="bg-white rounded-xl p-6 border border-stone-200 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="material-symbols-outlined text-3xl text-[#4B5320]">account_balance</span>
                            <span class="text-xs font-bold bg-stone-100 text-stone-700 px-2.5 py-1 rounded">15 mins drive</span>
                        </div>
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal">Royal Palace & Silver Pagoda</h3>
                        <p class="text-xs text-stone-500 leading-relaxed">
                            The magnificent royal residence featuring classic Khmer architecture, manicured French-style gardens, and the famed Emerald Buddha.
                        </p>
                    </div>

                    <div class="bg-white rounded-xl p-6 border border-stone-200 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="material-symbols-outlined text-3xl text-[#4B5320]">storefront</span>
                            <span class="text-xs font-bold bg-stone-100 text-stone-700 px-2.5 py-1 rounded">12 mins drive</span>
                        </div>
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal">Phnom Penh Night Market</h3>
                        <p class="text-xs text-stone-500 leading-relaxed">
                            Lively open-air bazaar along the Tonle Sap riverside, offering local silk textiles, handcrafted souvenirs, live music, and street gastronomy.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
