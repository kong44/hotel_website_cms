<?php
/**
 * Indra Hotel - Shared Public Footer
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/i18n.php';
?>
    </main>
    <!-- Main Page Content Ends -->

    <!-- Hotel Luxury Footer -->
    <footer class="bg-onyx-charcoal text-white pt-16 pb-12 border-t border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
                
                <!-- Brand Info -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <?php if ($logoImg = hotel_logo_url()): ?>
                            <img src="<?= e($logoImg) ?>" alt="<?= e(hotel_name()) ?> Logo" class="h-10 w-auto max-w-[160px] object-contain">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded text-[#191e00] flex items-center justify-center font-headline font-bold text-lg" style="background-color: <?= e(hotel_brand_color('highlight')) ?>; color: <?= e(hotel_brand_color('accent')) ?>;">
                                <?= strtoupper(substr(hotel_name(), 0, 2)) ?>
                            </div>
                            <span class="font-headline font-bold text-xl tracking-tight text-white"><?= strtoupper(e(hotel_name())) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-stone-400 text-sm leading-relaxed">
                        <?= e(hotel_tagline()) ?>
                    </p>
                    <div class="flex items-center space-x-3 pt-2">
                        <?php if ($fb = get_setting('social_facebook', HOTEL_FACEBOOK)): ?>
                        <a href="<?= e($fb) ?>" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded bg-stone-800 hover:bg-[#4B5320] flex items-center justify-center text-stone-300 hover:text-white transition" aria-label="Facebook">
                            <span class="text-sm font-bold">f</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($ig = get_setting('social_instagram', HOTEL_INSTAGRAM)): ?>
                        <a href="<?= e($ig) ?>" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded bg-stone-800 hover:bg-[#4B5320] flex items-center justify-center text-stone-300 hover:text-white transition" aria-label="Instagram">
                            <span class="text-sm font-bold">ig</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($ta = get_setting('social_tripadvisor', HOTEL_TRIPADVISOR)): ?>
                        <a href="<?= e($ta) ?>" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded bg-stone-800 hover:bg-[#4B5320] flex items-center justify-center text-stone-300 hover:text-white transition" aria-label="Tripadvisor">
                            <span class="material-symbols-outlined text-base">star</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($tg = get_setting('social_telegram')): ?>
                        <a href="<?= e($tg) ?>" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded bg-stone-800 hover:bg-[#4B5320] flex items-center justify-center text-stone-300 hover:text-white transition" aria-label="Telegram">
                            <span class="material-symbols-outlined text-base">send</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation Quick Links -->
                <div>
                    <h3 class="font-headline font-semibold text-base text-[#dfe8a6] uppercase tracking-wider mb-4" style="color: <?= e(hotel_brand_color('highlight')) ?>;"><?= __t('nav_rooms', 'Accommodations') ?></h3>
                    <ul class="space-y-2.5 text-sm text-stone-300">
                        <li><a href="<?= url('/rooms') ?>" class="hover:text-white transition">All Rooms & Suites</a></li>
                        <li><a href="<?= url('/room/deluxe-king') ?>" class="hover:text-white transition">Deluxe King Room</a></li>
                        <li><a href="<?= url('/room/junior-suite') ?>" class="hover:text-white transition">Junior Suite with Balcony</a></li>
                        <li><a href="<?= url('/room/indra-suite') ?>" class="hover:text-white transition">Indra Suite with Balcony</a></li>
                        <li><a href="<?= url('/offers') ?>" class="hover:text-white transition">Special Offers & Deals</a></li>
                    </ul>
                </div>

                <!-- Hotel Experiences -->
                <div>
                    <h3 class="font-headline font-semibold text-base text-[#dfe8a6] uppercase tracking-wider mb-4" style="color: <?= e(hotel_brand_color('highlight')) ?>;">Experiences</h3>
                    <ul class="space-y-2.5 text-sm text-stone-300">
                        <li><a href="<?= url('/about') ?>" class="hover:text-white transition">About Indra Sanctuary</a></li>
                        <li><a href="<?= url('/eat-drink') ?>" class="hover:text-white transition">The Bistro & Cafe</a></li>
                        <li><a href="<?= url('/wellness') ?>" class="hover:text-white transition">Fitness Center & Pool</a></li>
                        <li><a href="<?= url('/location') ?>" class="hover:text-white transition">Phnom Penh City Guide</a></li>
                        <li><a href="<?= url('/gallery') ?>" class="hover:text-white transition">Photo Gallery</a></li>
                    </ul>
                </div>

                <!-- Contact & Location -->
                <div>
                    <h3 class="font-headline font-semibold text-base text-[#dfe8a6] uppercase tracking-wider mb-4" style="color: <?= e(hotel_brand_color('highlight')) ?>;">Contact Sanctuary</h3>
                    <ul class="space-y-3 text-sm text-stone-300">
                        <li class="flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-lg mt-0.5" style="color: <?= e(hotel_brand_color('highlight')) ?>;">location_on</span>
                            <span><?= e(hotel_address()) ?></span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg" style="color: <?= e(hotel_brand_color('highlight')) ?>;">call</span>
                            <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="hover:text-white transition"><?= e(hotel_phone()) ?></a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg" style="color: <?= e(hotel_brand_color('highlight')) ?>;">mail</span>
                            <a href="mailto:<?= e(hotel_email()) ?>" class="hover:text-white transition"><?= e(hotel_email()) ?></a>
                        </li>
                        <li class="pt-2">
                            <a href="<?= url('/my-booking') ?>" class="inline-flex items-center gap-1.5 text-xs hover:underline font-medium" style="color: <?= e(hotel_brand_color('highlight')) ?>;">
                                <span class="material-symbols-outlined text-sm">manage_accounts</span>
                                Lookup Existing Reservation
                            </a>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Bottom Legal & Copyright -->
            <div class="pt-8 border-t border-stone-800 flex flex-col md:flex-row items-center justify-between gap-6 text-xs text-stone-400">
                <div class="flex flex-col sm:flex-row items-center gap-3 text-center sm:text-left">
                    <p>© <?= date('Y') ?> <?= e(hotel_name()) ?>. All Rights Reserved.</p>
                    <div class="hidden sm:inline text-stone-600">•</div>
                    <div class="flex items-center space-x-4">
                        <a href="<?= url('/sitemap.xml') ?>" class="hover:text-stone-200 transition">XML Sitemap</a>
                        <span class="text-stone-600">•</span>
                        <a href="<?= url('/contact') ?>" class="hover:text-stone-200 transition">Help & Support</a>
                    </div>
                </div>

                <!-- Footer Language Switcher -->
                <div class="flex items-center gap-2.5">
                    <span class="text-stone-400 text-xs flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm text-[#dfe8a6]">language</span>
                        <span>Language:</span>
                    </span>
                    <?= I18n::renderSwitcher('', 'dark', 'top') ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Universal Photo Lightbox Preview Modal -->
    <div id="photo-lightbox-modal" class="fixed inset-0 z-50 bg-black/92 backdrop-blur-md hidden flex flex-col justify-between p-4 sm:p-6 transition-all duration-300">
        
        <!-- Lightbox Top Controls -->
        <div class="flex items-center justify-between text-white pb-2 z-10">
            <div class="flex items-center gap-3">
                <span id="lightbox-counter" class="text-xs font-mono font-bold bg-white/10 px-3 py-1 rounded-full text-[#dfe8a6]">1 / 1</span>
                <div>
                    <h3 id="lightbox-title" class="font-headline font-bold text-sm sm:text-base text-white">Photo Preview</h3>
                    <span id="lightbox-caption" class="text-[11px] text-stone-400"></span>
                </div>
            </div>
            <button id="lightbox-close-btn" class="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition" title="Close Preview (Esc)">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>

        <!-- Main Display Center with Prev/Next Navigation -->
        <div class="flex-1 relative flex items-center justify-center my-auto min-h-0 overflow-hidden" id="lightbox-backdrop">
            <!-- Prev Button -->
            <button id="lightbox-prev-btn" class="absolute left-2 sm:left-6 z-20 w-12 h-12 rounded-full bg-black/50 hover:bg-[#343c0a] text-white flex items-center justify-center transition border border-white/10 shadow-lg" title="Previous (Left Arrow)">
                <span class="material-symbols-outlined text-2xl">chevron_left</span>
            </button>

            <!-- Image View Container -->
            <div class="max-w-5xl max-h-[72vh] flex items-center justify-center p-2">
                <img id="lightbox-image" src="" alt="Full Preview" class="max-h-[70vh] max-w-full object-contain rounded-xl shadow-2xl border border-white/10">
            </div>

            <!-- Next Button -->
            <button id="lightbox-next-btn" class="absolute right-2 sm:right-6 z-20 w-12 h-12 rounded-full bg-black/50 hover:bg-[#343c0a] text-white flex items-center justify-center transition border border-white/10 shadow-lg" title="Next (Right Arrow)">
                <span class="material-symbols-outlined text-2xl">chevron_right</span>
            </button>
        </div>

        <!-- Bottom Thumbnail Strip -->
        <div class="pt-3 border-t border-white/10 z-10">
            <div id="lightbox-thumbnails" class="flex items-center justify-center gap-2 overflow-x-auto py-1 max-w-2xl mx-auto"></div>
        </div>

    </div>

    <!-- Floating Back to Top Button -->
    <button id="back-to-top-btn" class="fixed bottom-[99px] right-6 z-40 w-11 h-11 rounded-full bg-[#343c0a] hover:bg-deep-olive text-white shadow-xl flex items-center justify-center btn-hidden cursor-pointer border border-white/20" aria-label="Back to Top" title="Back to Top">
        <span class="material-symbols-outlined text-2xl">arrow_upward</span>
    </button>

    <!-- Global JSON-LD Schema for Hotel -->
    <?= SEO::getHotelSchema() ?>

    <!-- Main Client-Side JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>

    <?php
    // Tawk.to Live Chat Widget
    $tawkEnabled = get_setting('tawkto_enabled', '0');
    $tawkPropertyId = trim(get_setting('tawkto_property_id', ''));
    $tawkWidgetId = trim(get_setting('tawkto_widget_id', 'default'));

    if ($tawkEnabled === '1' && !empty($tawkPropertyId)):
    ?>
    <!-- Start of Tawk.to Script -->
    <script type="text/javascript">
    var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
    (function(){
        var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
        s1.async = true;
        s1.src = 'https://embed.tawk.to/<?= e($tawkPropertyId) ?>/<?= e($tawkWidgetId ?: 'default') ?>';
        s1.charset = 'UTF-8';
        s1.setAttribute('crossorigin', '*');
        s0.parentNode.insertBefore(s1, s0);
    })();
    </script>
    <!-- End of Tawk.to Script -->
    <?php endif; ?>
</body>
</html>
