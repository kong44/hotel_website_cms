<?php
/**
 * Indra Hotel - Prime Location Attraction Detail Guide
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDB();

$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

// Lookup Spot
$spot = null;
if (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM location_spots WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $spot = $stmt->fetch();
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM location_spots WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $spot = $stmt->fetch();
}

// Fallback to first spot if slug not found
if (!$spot) {
    $stmt = $pdo->query("SELECT * FROM location_spots ORDER BY display_order ASC, id ASC LIMIT 1");
    $spot = $stmt->fetch();
}

if (!$spot) {
    header('Location: ' . BASE_URL . '/location.php');
    exit;
}

$spotTitle = __td($spot, 'title', $spot['title']);
$spotDesc = __td($spot, 'description', $spot['description']);
$spotIcon = !empty($spot['icon']) ? $spot['icon'] : 'location_on';

$currentGallery = [];
if (!empty($spot['image_url'])) {
    $currentGallery[] = $spot['image_url'];
}
if (!empty($spot['gallery_json'])) {
    $decodedGallery = json_decode($spot['gallery_json'], true);
    if (is_array($decodedGallery)) {
        foreach ($decodedGallery as $g) {
            if (!empty($g) && !in_array($g, $currentGallery)) {
                $currentGallery[] = $g;
            }
        }
    }
}
if (empty($currentGallery)) {
    $currentGallery[] = 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=1200&q=80';
}

// Fetch other nearby attractions
$stmtOther = $pdo->prepare("SELECT * FROM location_spots WHERE id != ? ORDER BY display_order ASC, id ASC LIMIT 3");
$stmtOther->execute([$spot['id']]);
$otherSpots = $stmtOther->fetchAll();

$categories = [
    'cultural' => ['name' => 'Historical & Cultural', 'color' => 'bg-amber-50 text-amber-900 border-amber-200'],
    'shopping' => ['name' => 'Shopping & Malls', 'color' => 'bg-emerald-50 text-emerald-900 border-emerald-200'],
    'attraction' => ['name' => 'City Attractions', 'color' => 'bg-blue-50 text-blue-900 border-blue-200'],
    'dining' => ['name' => 'Dining & Nightlife', 'color' => 'bg-rose-50 text-rose-900 border-rose-200'],
    'transit' => ['name' => 'Transit & Airport', 'color' => 'bg-indigo-50 text-indigo-900 border-indigo-200'],
    'general' => ['name' => 'General Spot', 'color' => 'bg-stone-100 text-stone-800 border-stone-200']
];
$catInfo = $categories[$spot['category']] ?? $categories['general'];

$pageMeta = [
    'title' => $spotTitle . ' - Phnom Penh City Guide | ' . hotel_name(),
    'description' => substr(strip_tags($spotDesc), 0, 160),
    'image' => $currentGallery[0] ?? '',
    'keywords' => $spotTitle . ', phnom penh attractions, near indra hotel, tuol kork sightseeing',
    'type' => 'article'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Hero Header -->
<section class="bg-onyx-charcoal text-white pt-12 pb-16 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <!-- Breadcrumb Navigation -->
        <nav class="flex items-center gap-2 text-xs text-stone-400 mb-6">
            <a href="<?= BASE_URL ?>/index.php" class="hover:text-white transition">Home</a>
            <span>/</span>
            <a href="<?= BASE_URL ?>/location.php" class="hover:text-white transition">Prime Location</a>
            <span>/</span>
            <span class="text-[#dfe8a6] font-semibold"><?= e($spotTitle) ?></span>
        </nav>

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div class="space-y-3 max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-full border <?= $catInfo['color'] ?>">
                        <?= e($catInfo['name']) ?>
                    </span>
                    <span class="inline-flex items-center gap-1 bg-[#dfe8a6]/20 text-[#dfe8a6] text-xs font-bold px-3 py-1 rounded-full border border-[#dfe8a6]/30">
                        <span class="material-symbols-outlined text-sm">schedule</span>
                        <span><?= e($spot['distance_time']) ?> from Indra Hotel</span>
                    </span>
                </div>
                <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white">
                    <?= e($spotTitle) ?>
                </h1>
            </div>

            <div class="shrink-0 flex items-center gap-3">
                <a href="https://maps.google.com/?q=<?= urlencode($spotTitle . ' Phnom Penh Cambodia') ?>" target="_blank" rel="noopener noreferrer" 
                   class="bg-[#dfe8a6] hover:bg-white text-[#191e00] px-5 py-2.5 rounded-lg text-xs font-bold transition flex items-center gap-2 shadow cursor-pointer">
                    <span class="material-symbols-outlined text-base">directions</span>
                    <span>Get Directions</span>
                </a>
            </div>
        </div>

    </div>
</section>

<!-- Main Detail Content Grid -->
<section class="py-12 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

            <!-- Left 8 Columns: Photography & Detailed Story -->
            <div class="lg:col-span-8 space-y-10">
                
                <!-- Main Featured Photo with Lightbox Trigger -->
                <div class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-sm space-y-3 p-3">
                    <div class="relative h-[360px] sm:h-[460px] rounded-xl overflow-hidden cursor-pointer group"
                         onclick="openPhotoLightbox(<?= json_encode($currentGallery) ?>, 0, <?= json_encode($spotTitle) ?>)">
                        <img src="<?= e($currentGallery[0]) ?>" alt="<?= e($spotTitle) ?>" class="w-full h-full object-cover group-hover:scale-103 transition duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-6">
                            <span class="inline-flex items-center gap-2 bg-black/70 backdrop-blur-md text-white text-xs font-bold px-4 py-2 rounded-lg">
                                <span class="material-symbols-outlined text-base">fullscreen</span>
                                <span>Click to View Fullscreen Gallery (<?= count($currentGallery) ?> Photos)</span>
                            </span>
                        </div>
                    </div>

                    <!-- Photo Thumbnails Strip -->
                    <?php if (count($currentGallery) > 1): ?>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 pt-1">
                        <?php foreach ($currentGallery as $idx => $photo): ?>
                        <div class="h-20 rounded-lg overflow-hidden border border-stone-200 cursor-pointer hover:opacity-80 transition"
                             onclick="openPhotoLightbox(<?= json_encode($currentGallery) ?>, <?= (int)$idx ?>, <?= json_encode($spotTitle) ?>)">
                            <img src="<?= e($photo) ?>" alt="Thumb" class="w-full h-full object-cover">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comprehensive Description Narrative -->
                <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
                    <div class="flex items-center gap-3 border-b border-stone-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-stone-100 text-[#343c0a] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl"><?= e($spotIcon) ?></span>
                        </div>
                        <div>
                            <h2 class="font-headline font-bold text-2xl text-onyx-charcoal">About <?= e($spotTitle) ?></h2>
                            <p class="text-xs text-stone-500">Essential visitor background & cultural significance.</p>
                        </div>
                    </div>

                    <div class="prose max-w-none text-stone-600 text-sm sm:text-base leading-relaxed space-y-4">
                        <p><?= nl2br(e($spotDesc)) ?></p>
                    </div>
                </div>

                <!-- Concierge Insider Tips Card -->
                <?php if (!empty($spot['tips'])): ?>
                <div class="bg-gradient-to-br from-[#343c0a] to-[#191c1d] rounded-2xl p-6 sm:p-8 text-white shadow-md space-y-4 relative overflow-hidden">
                    <div class="flex items-center gap-2 text-[#dfe8a6] text-xs font-bold uppercase tracking-widest">
                        <span class="material-symbols-outlined text-base">lightbulb</span>
                        <span>Indra Hotel Concierge Recommendation</span>
                    </div>
                    <h3 class="font-headline font-bold text-xl text-white">Insider Tips for Hotel Guests</h3>
                    <p class="text-xs sm:text-sm text-stone-300 leading-relaxed font-light">
                        <?= nl2br(e($spot['tips'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Interactive Google Map Embed -->
                <?php 
                $mapUrl = !empty($spot['map_embed']) ? $spot['map_embed'] : 'https://maps.google.com/maps?q=' . urlencode($spotTitle . ' Phnom Penh') . '&hl=en&z=15&output=embed';
                ?>
                <div class="bg-white rounded-2xl p-6 border border-stone-200 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#343c0a]">map</span>
                            <span>Attraction Location Map</span>
                        </h3>
                        <a href="https://maps.google.com/?q=<?= urlencode($spotTitle . ' Phnom Penh Cambodia') ?>" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-[#343c0a] hover:underline flex items-center gap-1">
                            <span>Open Full Maps</span>
                            <span class="material-symbols-outlined text-sm">open_in_new</span>
                        </a>
                    </div>
                    <div class="rounded-xl overflow-hidden h-72 border border-stone-200 bg-stone-100">
                        <iframe title="Map Location" src="<?= e($mapUrl) ?>" class="w-full h-full border-0" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>

            </div>

            <!-- Right 4 Columns: Quick Facts & Concierge Booking Widget -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Quick Facts Card -->
                <div class="bg-white rounded-2xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-5">
                    <h3 class="font-headline font-bold text-base text-onyx-charcoal border-b border-stone-100 pb-3">Visitor Information</h3>

                    <div class="space-y-4 text-xs">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg mt-0.5">timer</span>
                            <div>
                                <span class="block text-[11px] font-bold uppercase text-stone-400">Travel Distance</span>
                                <span class="font-bold text-stone-900 text-sm"><?= e($spot['distance_time']) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($spot['opening_hours'])): ?>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg mt-0.5">schedule</span>
                            <div>
                                <span class="block text-[11px] font-bold uppercase text-stone-400">Opening Hours</span>
                                <span class="font-semibold text-stone-800"><?= e($spot['opening_hours']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($spot['admission_fee'])): ?>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg mt-0.5">payments</span>
                            <div>
                                <span class="block text-[11px] font-bold uppercase text-stone-400">Admission / Tickets</span>
                                <span class="font-semibold text-stone-800"><?= e($spot['admission_fee']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($spot['address'])): ?>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg mt-0.5">pin_drop</span>
                            <div>
                                <span class="block text-[11px] font-bold uppercase text-stone-400">Street Address</span>
                                <span class="text-stone-600"><?= e($spot['address']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-4 border-t border-stone-100">
                        <a href="https://maps.google.com/?q=<?= urlencode($spotTitle . ' ' . ($spot['address'] ?? 'Phnom Penh')) ?>" target="_blank" rel="noopener noreferrer" 
                           class="w-full text-center bg-[#343c0a] hover:bg-deep-olive text-white py-3 rounded-lg font-bold text-xs uppercase tracking-wider transition shadow flex items-center justify-center gap-2 cursor-pointer">
                            <span class="material-symbols-outlined text-sm">navigation</span>
                            <span>Navigate in Google Maps</span>
                        </a>
                    </div>
                </div>

                <!-- Concierge Transport & Assistance Card -->
                <div class="bg-white rounded-2xl p-6 sm:p-7 border border-stone-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/40 text-[#343c0a] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-2xl">local_taxi</span>
                        </div>
                        <div>
                            <h3 class="font-headline font-bold text-base text-onyx-charcoal">Need Hotel Transport?</h3>
                            <p class="text-[11px] text-stone-500">Private AC chauffeured SUV & Tuk-Tuk tours.</p>
                        </div>
                    </div>
                    
                    <p class="text-xs text-stone-600 leading-relaxed">
                        Our front desk concierge can arrange private English-speaking drivers, scheduled pickup, and bespoke day tours directly from Indra Hotel.
                    </p>

                    <div class="pt-2 flex flex-col gap-2">
                        <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="w-full text-center border border-stone-300 hover:bg-stone-50 text-stone-800 py-2.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">call</span>
                            <span>Call Concierge: <?= e(hotel_phone()) ?></span>
                        </a>
                        <a href="<?= BASE_URL ?>/contact.php" class="w-full text-center bg-stone-100 hover:bg-stone-200 text-stone-800 py-2.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">mail</span>
                            <span>Inquire Transportation</span>
                        </a>
                    </div>
                </div>

            </div>

        </div>

        <!-- Other Surrounding Attractions Grid -->
        <?php if (!empty($otherSpots)): ?>
        <div class="mt-16 pt-12 border-t border-stone-200 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">More to Explore</span>
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal mt-1">Other Phnom Penh Attractions</h2>
                </div>
                <a href="<?= BASE_URL ?>/location.php" class="text-xs font-bold text-[#343c0a] hover:underline flex items-center gap-1">
                    <span>View All Landmarks</span>
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($otherSpots as $os): 
                    $osTitle = __td($os, 'title', $os['title']);
                    $osDesc = __td($os, 'description', $os['description']);
                    $osIcon = !empty($os['icon']) ? $os['icon'] : 'location_on';
                    $osSlug = !empty($os['slug']) ? $os['slug'] : $os['id'];
                    $osImg = !empty($os['image_url']) ? $os['image_url'] : 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=800&q=80';
                ?>
                <a href="<?= BASE_URL ?>/location-detail.php?slug=<?= e($osSlug) ?>" 
                   class="bg-white rounded-2xl overflow-hidden border border-stone-200 shadow-2xs hover:shadow-xl hover:border-[#343c0a]/40 transition duration-300 flex flex-col justify-between group">
                    <div>
                        <!-- Photo on Top of Card -->
                        <div class="h-44 relative overflow-hidden bg-stone-100">
                            <img src="<?= e($osImg) ?>" alt="<?= e($osTitle) ?>" class="w-full h-full object-cover group-hover:scale-108 transition duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                            
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1 bg-black/60 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-1 rounded-full border border-white/20">
                                    <span class="material-symbols-outlined text-xs text-[#dfe8a6]"><?= e($osIcon) ?></span>
                                    <span><?= e($os['distance_time']) ?></span>
                                </span>
                            </div>

                            <div class="absolute bottom-3 left-3 right-3 text-white">
                                <h3 class="font-headline font-bold text-base text-white group-hover:text-[#dfe8a6] transition line-clamp-1"><?= e($osTitle) ?></h3>
                            </div>
                        </div>

                        <!-- Content Body -->
                        <div class="p-5 space-y-2">
                            <p class="text-xs text-stone-500 line-clamp-2 leading-relaxed"><?= e($osDesc) ?></p>
                        </div>
                    </div>

                    <div class="px-5 pb-5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs font-bold text-[#343c0a]">
                        <span>View Attraction Guide</span>
                        <span class="material-symbols-outlined text-sm group-hover:translate-x-1.5 transition">arrow_forward</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
