<?php
/**
 * Indra Hotel - Photo Gallery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM gallery ORDER BY display_order ASC");
$photos = $stmt->fetchAll();

$pageMeta = [
    'title' => 'Photo Gallery | Indra Hotel Phnom Penh',
    'description' => 'Browse our high-resolution photo gallery showcasing Indra Hotel luxury suites, restaurant dining, fitness center, saltwater pool, and serene architecture.',
    'keywords' => 'hotel photos phnom penh, indra hotel gallery, luxury suites pictures cambodia',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<section class="bg-onyx-charcoal text-white py-16 sm:py-20 relative overflow-hidden">
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#dfe8a6] block mb-2">Visual Showcase</span>
        <h1 class="font-headline text-3xl sm:text-5xl font-bold tracking-tight text-white mb-4">
            Photo Gallery
        </h1>
        <p class="text-stone-300 text-sm sm:text-base max-w-2xl mx-auto font-light leading-relaxed">
            Immerse yourself in the serene architecture, contemporary rooms, and world-class amenities of Indra Hotel.
        </p>
    </div>
</section>

<!-- Category Tabs -->
<section class="bg-white border-b border-stone-200 sticky top-20 z-30 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-center py-4 space-x-2 overflow-x-auto">
            <button data-category="all" class="gallery-filter-btn px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition bg-[#343c0a] text-white">
                All Photos
            </button>
            <button data-category="rooms" class="gallery-filter-btn px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition bg-stone-100 text-stone-700 hover:bg-stone-200">
                Accommodations
            </button>
            <button data-category="dining" class="gallery-filter-btn px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition bg-stone-100 text-stone-700 hover:bg-stone-200">
                The Bistro & Dining
            </button>
            <button data-category="wellness" class="gallery-filter-btn px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition bg-stone-100 text-stone-700 hover:bg-stone-200">
                Fitness & Pool
            </button>
            <button data-category="exterior" class="gallery-filter-btn px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition bg-stone-100 text-stone-700 hover:bg-stone-200">
                Architecture & Grounds
            </button>
        </div>
    </div>
</section>

<!-- Photos Grid -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" id="gallery-container">
            <?php 
            $g = 1;
            foreach ($photos as $idx => $photo): 
            ?>
            <div class="gallery-item group bg-white rounded-xl overflow-hidden border border-stone-200 shadow-sm luxury-card relative cursor-pointer reveal reveal-scale stagger-<?= ($g % 3) + 1; $g++; ?>"
                 data-category="<?= e($photo['category']) ?>"
                 onclick='openGalleryPreview(<?= $idx ?>)'>
                <div class="h-72 overflow-hidden relative">
                    <img src="<?= e($photo['image_url']) ?>" alt="<?= e($photo['title']) ?>" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    
                    <!-- Hover Zoom Badge -->
                    <div class="absolute top-4 right-4 bg-black/60 backdrop-blur-md text-white w-9 h-9 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="material-symbols-outlined text-lg">zoom_in</span>
                    </div>

                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6 text-white">
                        <span class="text-xs uppercase tracking-widest text-[#dfe8a6] font-semibold"><?= e($photo['category']) ?></span>
                        <h3 class="font-headline font-bold text-lg"><?= e($photo['title']) ?></h3>
                        <?php if (!empty($photo['caption'])): ?>
                        <p class="text-xs text-stone-300 mt-1"><?= e($photo['caption']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
const galleryDataset = <?= json_encode(array_map(function($p) {
    return [
        'url' => $p['image_url'],
        'title' => $p['title'],
        'caption' => $p['caption'] ?: ucfirst($p['category'])
    ];
}, $photos)) ?>;

function openGalleryPreview(index) {
    if (window.openPhotoPreview) {
        window.openPhotoPreview(galleryDataset, index, 'Indra Hotel Gallery');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
