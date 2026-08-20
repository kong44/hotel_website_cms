/**
 * Indra Hotel - Main Interactive Client-Side Script, i18n Dropdowns & Universal Lightbox Photo Preview
 */

// =======================================================
// 1. Global Language Dropdown Toggle (Immediate Scope)
// =======================================================
window.toggleLanguageDropdown = function (btn, event) {
    if (event) {
        event.stopPropagation();
    }
    const parent = btn.closest('.language-switcher-dropdown') || btn.parentElement;
    const menu = parent ? parent.querySelector('.lang-dropdown-menu') : null;
    
    // Close other dropdowns
    document.querySelectorAll('.lang-dropdown-menu').forEach(m => {
        if (m !== menu) m.classList.add('hidden');
    });

    if (menu) {
        menu.classList.toggle('hidden');
    }
};

// Global click to close dropdowns
document.addEventListener('click', function (e) {
    if (!e.target.closest('.language-switcher-dropdown')) {
        document.querySelectorAll('.lang-dropdown-menu').forEach(m => m.classList.add('hidden'));
    }
});

// =======================================================
// 2. Universal Lightbox Photo Preview Engine (Immediate Scope)
// =======================================================
let currentGalleryImages = [];
let currentPhotoIndex = 0;

window.openPhotoPreview = function (images, index = 0, defaultTitle = 'Indra Hotel Photo', defaultCategory = 'Gallery') {
    if (!images || images.length === 0) return;
    
    // Normalize images array
    currentGalleryImages = images.map(img => {
        if (typeof img === 'string') {
            return { url: img, title: defaultTitle, caption: defaultCategory };
        }
        return {
            url: img.url || img.image_url || '',
            title: img.title || defaultTitle,
            caption: img.caption || img.category || defaultCategory
        };
    });

    currentPhotoIndex = Math.max(0, Math.min(index, currentGalleryImages.length - 1));
    renderLightboxActivePhoto();

    const lightboxModal = document.getElementById('photo-lightbox-modal');
    if (lightboxModal) {
        lightboxModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
};

window.openPhotoLightbox = window.openPhotoPreview;


function renderLightboxActivePhoto() {
    if (!currentGalleryImages || currentGalleryImages.length === 0) return;
    const photo = currentGalleryImages[currentPhotoIndex];
    if (!photo) return;

    const lightboxImg = document.getElementById('lightbox-image');
    const lightboxTitle = document.getElementById('lightbox-title');
    const lightboxCaption = document.getElementById('lightbox-caption');
    const lightboxCounter = document.getElementById('lightbox-counter');
    const lightboxThumbStrip = document.getElementById('lightbox-thumbnails');

    if (lightboxImg) {
        lightboxImg.classList.remove('lightbox-img-zoom');
        void lightboxImg.offsetWidth; // Trigger reflow
        lightboxImg.src = photo.url;
        lightboxImg.alt = photo.title || 'Preview Photo';
        lightboxImg.classList.add('lightbox-img-zoom');
    }

    if (lightboxTitle) lightboxTitle.textContent = photo.title || '';
    if (lightboxCaption) lightboxCaption.textContent = photo.caption || '';
    if (lightboxCounter) lightboxCounter.textContent = `${currentPhotoIndex + 1} / ${currentGalleryImages.length}`;

    // Render Thumbnail Navigation Strip
    if (lightboxThumbStrip) {
        lightboxThumbStrip.innerHTML = '';
        if (currentGalleryImages.length > 1) {
            currentGalleryImages.forEach((item, idx) => {
                const thumb = document.createElement('button');
                thumb.className = `w-14 h-10 rounded-lg overflow-hidden border-2 transition-all shrink-0 cursor-pointer ${idx === currentPhotoIndex ? 'border-[#dfe8a6] scale-105 opacity-100 shadow-md' : 'border-transparent opacity-50 hover:opacity-80'}`;
                thumb.innerHTML = `<img src="${item.url}" alt="Thumbnail" class="w-full h-full object-cover">`;
                thumb.onclick = (e) => {
                    e.stopPropagation();
                    currentPhotoIndex = idx;
                    renderLightboxActivePhoto();
                };
                lightboxThumbStrip.appendChild(thumb);
            });
        }
    }
}

window.closePhotoPreview = function () {
    const lightboxModal = document.getElementById('photo-lightbox-modal');
    if (lightboxModal) {
        lightboxModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
};

window.lightboxNext = function () {
    if (currentGalleryImages.length === 0) return;
    currentPhotoIndex = (currentPhotoIndex + 1) % currentGalleryImages.length;
    renderLightboxActivePhoto();
};

window.lightboxPrev = function () {
    if (currentGalleryImages.length === 0) return;
    currentPhotoIndex = (currentPhotoIndex - 1 + currentGalleryImages.length) % currentGalleryImages.length;
    renderLightboxActivePhoto();
};

// Global Lightbox Keyboard Shortcuts
document.addEventListener('keydown', function (e) {
    const lightboxModal = document.getElementById('photo-lightbox-modal');
    if (!lightboxModal || lightboxModal.classList.contains('hidden')) return;
    if (e.key === 'Escape') {
        window.closePhotoPreview();
    } else if (e.key === 'ArrowRight') {
        window.lightboxNext();
    } else if (e.key === 'ArrowLeft') {
        window.lightboxPrev();
    }
});

// =======================================================
// 3. Page Lifecycle Bindings (DOM Ready)
// =======================================================
document.addEventListener('DOMContentLoaded', function () {
    // Lightbox Control Buttons
    const lightboxCloseBtn = document.getElementById('lightbox-close-btn');
    const lightboxPrevBtn = document.getElementById('lightbox-prev-btn');
    const lightboxNextBtn = document.getElementById('lightbox-next-btn');
    const lightboxModal = document.getElementById('photo-lightbox-modal');

    if (lightboxCloseBtn) lightboxCloseBtn.addEventListener('click', window.closePhotoPreview);
    if (lightboxNextBtn) lightboxNextBtn.addEventListener('click', window.lightboxNext);
    if (lightboxPrevBtn) lightboxPrevBtn.addEventListener('click', window.lightboxPrev);

    if (lightboxModal) {
        lightboxModal.addEventListener('click', function (e) {
            if (e.target === lightboxModal || e.target.id === 'lightbox-backdrop') {
                window.closePhotoPreview();
            }
        });
    }

    // Mobile Menu Drawer Toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileDrawer = document.getElementById('mobile-drawer');
    const closeDrawerBtn = document.getElementById('close-drawer-btn');
    const drawerOverlay = document.getElementById('drawer-overlay');

    if (mobileMenuBtn && mobileDrawer) {
        mobileMenuBtn.addEventListener('click', function () {
            mobileDrawer.classList.remove('translate-x-full');
            if (drawerOverlay) drawerOverlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    }

    function closeDrawer() {
        if (mobileDrawer) {
            mobileDrawer.classList.add('translate-x-full');
            if (drawerOverlay) drawerOverlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);

    // Toast Flash Auto-dismiss
    const toast = document.getElementById('flash-toast');
    if (toast) {
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    }

    // Datepicker Auto Constraints & Night Calculation for Booking
    const checkInInput = document.getElementById('booking_check_in');
    const checkOutInput = document.getElementById('booking_check_out');
    const nightsDisplay = document.getElementById('calculated_nights');
    const totalPriceDisplay = document.getElementById('calculated_total_price');
    const roomSelect = document.getElementById('booking_room_select');
    const promoCodeInput = document.getElementById('booking_promo_code');
    const discountRow = document.getElementById('booking_discount_row');
    const discountDisplay = document.getElementById('calculated_discount');

    // Set minimum check-in date to today
    const today = new Date().toISOString().split('T')[0];
    if (checkInInput && !checkInInput.value) {
        checkInInput.min = today;
        checkInInput.value = today;
    }

    if (checkOutInput && checkInInput) {
        function updateCheckOutMin() {
            if (checkInInput.value) {
                const nextDay = new Date(checkInInput.value);
                nextDay.setDate(nextDay.getDate() + 1);
                const nextDayStr = nextDay.toISOString().split('T')[0];
                checkOutInput.min = nextDayStr;
                if (!checkOutInput.value || checkOutInput.value <= checkInInput.value) {
                    checkOutInput.value = nextDayStr;
                }
            }
            recalculateBookingTotals();
        }

        checkInInput.addEventListener('change', updateCheckOutMin);
        checkOutInput.addEventListener('change', recalculateBookingTotals);
        if (roomSelect) roomSelect.addEventListener('change', recalculateBookingTotals);
        if (promoCodeInput) promoCodeInput.addEventListener('input', recalculateBookingTotals);

        updateCheckOutMin();
    }

    function recalculateBookingTotals() {
        if (!checkInInput || !checkOutInput) return;
        const d1 = new Date(checkInInput.value);
        const d2 = new Date(checkOutInput.value);
        if (isNaN(d1) || isNaN(d2) || d2 <= d1) return;

        const diffTime = Math.abs(d2 - d1);
        const nights = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;

        if (nightsDisplay) nightsDisplay.textContent = nights + (nights === 1 ? ' night' : ' nights');

        let pricePerNight = 85.00;
        if (roomSelect) {
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.price) {
                pricePerNight = parseFloat(selectedOption.dataset.price) || 85.00;
            }
        }

        let total = nights * pricePerNight;
        let discountPercent = 0;

        if (promoCodeInput && promoCodeInput.value.trim().toUpperCase() === 'INDRA15') {
            discountPercent = 15;
        } else if (promoCodeInput && promoCodeInput.value.trim().toUpperCase() === 'LOVEINDRA') {
            discountPercent = 20;
        } else if (promoCodeInput && promoCodeInput.value.trim().toUpperCase() === 'STAYLONG' && nights >= 5) {
            discountPercent = 25;
        }

        if (discountPercent > 0) {
            const discountAmt = total * (discountPercent / 100);
            total = total - discountAmt;
            if (discountRow) discountRow.classList.remove('hidden');
            if (discountDisplay) discountDisplay.textContent = '-$' + discountAmt.toFixed(2) + ` (${discountPercent}% OFF)`;
        } else {
            if (discountRow) discountRow.classList.add('hidden');
        }

        if (totalPriceDisplay) totalPriceDisplay.textContent = '$' + total.toFixed(2);
    }

    // Filter Gallery Categories
    const filterButtons = document.querySelectorAll('.gallery-filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');

    if (filterButtons.length > 0) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                filterButtons.forEach(b => b.classList.remove('bg-[#343c0a]', 'text-white'));
                filterButtons.forEach(b => b.classList.add('bg-stone-200', 'text-stone-700'));
                this.classList.remove('bg-stone-200', 'text-stone-700');
                this.classList.add('bg-[#343c0a]', 'text-white');

                const category = this.dataset.category;
                galleryItems.forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    }

    // =======================================================
    // 4. Luxury Scroll Animations & Micro-Interactions
    // =======================================================
    initScrollReveal();
    initGlassHeaderScroll();
    initBackToTopButton();
    initCounterAnimation();
});

/**
 * Scroll Reveal Engine via Intersection Observer
 */
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.reveal');
    if (revealElements.length === 0) return;

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    obs.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: '0px 0px -50px 0px',
            threshold: 0.1
        });

        revealElements.forEach(el => observer.observe(el));
    } else {
        revealElements.forEach(el => el.classList.add('is-revealed'));
    }
}

/**
 * Dynamic Glassmorphism Header on Scroll
 */
function initGlassHeaderScroll() {
    const header = document.querySelector('header');
    if (!header) return;

    function handleScroll() {
        if (window.scrollY > 30) {
            header.classList.add('header-scrolled');
        } else {
            header.classList.remove('header-scrolled');
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
}

/**
 * Floating Back to Top Button
 */
function initBackToTopButton() {
    const btn = document.getElementById('back-to-top-btn');
    if (!btn) return;

    window.addEventListener('scroll', function () {
        if (window.scrollY > 300) {
            btn.classList.remove('btn-hidden');
            btn.classList.add('btn-visible');
        } else {
            btn.classList.remove('btn-visible');
            btn.classList.add('btn-hidden');
        }
    }, { passive: true });

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * Numeric Counter Animation on Viewport Ingress
 */
function initCounterAnimation() {
    const counters = document.querySelectorAll('.animate-counter');
    if (counters.length === 0) return;

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.getAttribute('data-target') || el.textContent, 10);
                    const suffix = el.getAttribute('data-suffix') || '';
                    const prefix = el.getAttribute('data-prefix') || '';
                    const duration = parseInt(el.getAttribute('data-duration') || '1400', 10);

                    if (!isNaN(target)) {
                        const startTime = performance.now();

                        function updateCounter(currentTime) {
                            const elapsed = currentTime - startTime;
                            const progress = Math.min(elapsed / duration, 1);
                            // Ease out cubic
                            const easedProgress = 1 - Math.pow(1 - progress, 3);
                            const currentVal = Math.floor(easedProgress * target);

                            el.textContent = prefix + currentVal + suffix;

                            if (progress < 1) {
                                requestAnimationFrame(updateCounter);
                            } else {
                                el.textContent = prefix + target + suffix;
                            }
                        }

                        requestAnimationFrame(updateCounter);
                    }
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(c => observer.observe(c));
    }
}
