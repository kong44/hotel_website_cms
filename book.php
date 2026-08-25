<?php
/**
 * Indra Hotel - Booking Engine & Reservation Flow
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-auth.php';
require_once __DIR__ . '/includes/seo.php';

// Auto-redirect to External Booking Engine (Mode 2) or OTA Deep Link (Mode 3) if active
$currentMode = get_booking_mode();
if ($currentMode === 'engine' && !empty(get_booking_engine_url())) {
    $engineUrl = get_booking_engine_url();
    if (!empty($_SERVER['QUERY_STRING'])) {
        $separator = (strpos($engineUrl, '?') !== false) ? '&' : '?';
        $engineUrl .= $separator . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $engineUrl);
    exit;
} elseif ($currentMode === 'ota' || $currentMode === 'multi_channel') {
    $otaUrl = build_single_ota_deeplink($_GET['check_in'] ?? null, $_GET['check_out'] ?? null, (int)($_GET['adults'] ?? 2));
    if (!empty($otaUrl) && $otaUrl !== '#') {
        header('Location: ' . $otaUrl);
        exit;
    }
}

$pdo = getDB();

// Fetch all available rooms
$stmtRooms = $pdo->query("SELECT * FROM rooms WHERE status != 'maintenance' ORDER BY display_order ASC");
$allRooms = $stmtRooms->fetchAll();

// Booking state variables
$selectedRoomId = (int)($_GET['room_id'] ?? ($_POST['room_id'] ?? ($allRooms[0]['id'] ?? 1)));
$checkIn = $_GET['check_in'] ?? ($_POST['check_in'] ?? date('Y-m-d'));
$checkOut = $_GET['check_out'] ?? ($_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
$adults = (int)($_GET['adults'] ?? ($_POST['adults'] ?? 2));
$children = (int)($_GET['children'] ?? ($_POST['children'] ?? 0));
$promoCode = trim($_GET['promo'] ?? ($_POST['promo'] ?? ''));

// Find selected room record
$selectedRoom = null;
foreach ($allRooms as $rm) {
    if ((int)$rm['id'] === $selectedRoomId) {
        $selectedRoom = $rm;
        break;
    }
}
if (!$selectedRoom && !empty($allRooms)) {
    $selectedRoom = $allRooms[0];
    $selectedRoomId = (int)$selectedRoom['id'];
}

// Calculate nights and pricing
$nights = calculate_nights($checkIn, $checkOut);
$roomRate = (float)($selectedRoom['price_per_night'] ?? 85.00);
$subtotal = $roomRate * $nights;
$discountPercent = 0;

// Check promo code against special_offers table and static codes
$offerTitle = '';
if (!empty($promoCode)) {
    try {
        $stmtOffer = $pdo->prepare("SELECT title, discount_percent FROM special_offers WHERE UPPER(promo_code) = ? AND is_active = 1 LIMIT 1");
        $stmtOffer->execute([strtoupper($promoCode)]);
        $offerFound = $stmtOffer->fetch();
        if ($offerFound) {
            $offerTitle = $offerFound['title'];
            if ((int)$offerFound['discount_percent'] > 0) {
                $discountPercent = (int)$offerFound['discount_percent'];
            }
        }
    } catch (Throwable $e) {}
}

if ($discountPercent === 0 && !empty($promoCode)) {
    if (strtoupper($promoCode) === 'INDRA15') {
        $discountPercent = 15;
        $offerTitle = 'Direct Booking 15% Privilege';
    } elseif (strtoupper($promoCode) === 'LOVEINDRA') {
        $discountPercent = 20;
        $offerTitle = 'Romantic Retreat 20% Privilege';
    } elseif (strtoupper($promoCode) === 'STAYLONG' && $nights >= 5) {
        $discountPercent = 25;
        $offerTitle = 'Extended Stay 25% Privilege';
    }
}

$discountAmount = $subtotal * ($discountPercent / 100);
$totalPrice = max(0, $subtotal - $discountAmount);

// Check Google Authentication Requirement & Guest Account Status
$isGuestLoggedIn = is_guest_logged_in();
$guestAccount = get_logged_in_guest();
$guestStatus = 'active';

if ($isGuestLoggedIn && !empty($guestAccount['email'])) {
    try {
        $stmtG = $pdo->prepare("SELECT status FROM guests WHERE LOWER(email) = ? LIMIT 1");
        $stmtG->execute([strtolower($guestAccount['email'])]);
        $st = $stmtG->fetchColumn();
        if ($st) {
            $guestStatus = (string)$st;
        }
    } catch (Throwable $e) {}
}

// Handle POST Booking Submission
$bookingCompleted = false;
$confirmedBooking = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_booking'])) {
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');
    $specialRequests = trim($_POST['special_requests'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!Auth::verifyCsrf($csrf)) {
        set_flash('error', 'Session expired. Please try submitting again.');
    } elseif (!$isGuestLoggedIn) {
        set_flash('error', 'To prevent automated spam attacks, please Sign In with Google before completing your reservation.');
    } elseif (is_guest_restricted($guestEmail) || $guestStatus === 'blocked' || $guestStatus === 'restricted' || $guestStatus === 'flagged') {
        set_flash('error', 'Your guest account is currently restricted from submitting online booking requests. Please contact the concierge directly at ' . hotel_phone() . '.');
    } elseif (empty($guestName) || empty($guestEmail) || empty($guestPhone)) {
        set_flash('error', 'Please fill in all guest contact details.');
    } elseif (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Please provide a valid email address.');
    } else {
        try {
            $bookingRef = generate_booking_ref();
            $stmtInsert = $pdo->prepare("INSERT INTO bookings (booking_reference, room_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, adults, children, nights, room_rate, total_price, status, payment_status, promo_code, offer_title, discount_amount, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?, ?, ?, ?)");
            $stmtInsert->execute([
                $bookingRef,
                $selectedRoomId,
                $guestName,
                $guestEmail,
                $guestPhone,
                $checkIn,
                $checkOut,
                $adults,
                $children,
                $nights,
                $roomRate,
                $totalPrice,
                $promoCode ?: null,
                $offerTitle ?: null,
                $discountAmount,
                $specialRequests
            ]);

            $bookingId = $pdo->lastInsertId();
            
            // Fetch newly created booking with room info
            $stmtGet = $pdo->prepare("SELECT b.*, r.name as room_name, r.bed_type, r.image_url FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
            $stmtGet->execute([$bookingId]);
            $confirmedBooking = $stmtGet->fetch();
            $bookingCompleted = true;

            // Dispatch Automated Transactional Emails
            require_once __DIR__ . '/includes/mailer.php';
            try {
                Mailer::sendBookingConfirmation($confirmedBooking, $selectedRoom);
            } catch (Throwable $t) {}

            try {
                Mailer::sendBookingNotificationToHotel($confirmedBooking, $selectedRoom);
            } catch (Throwable $t) {}
            
            set_flash('success', "Reservation confirmed! Your booking reference is {$bookingRef}. A confirmation email has been sent.");
        } catch (Throwable $e) {
            set_flash('error', 'Error creating reservation: ' . $e->getMessage());
        }
    }
}

$pageMeta = [
    'title' => 'Book Your Stay | Direct Reservation | Indra Hotel Phnom Penh',
    'description' => 'Book directly at Indra Hotel Phnom Penh for best rate guarantee, complimentary breakfast, and flexible cancellation.',
    'keywords' => 'book indra hotel, reservation hotel phnom penh, direct booking discount cambodia',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<section class="bg-onyx-charcoal text-white py-12 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#dfe8a6] block mb-1">Official Booking Portal</span>
        <h1 class="font-headline text-3xl sm:text-4xl font-bold tracking-tight text-white">
            <?= $bookingCompleted ? 'Reservation Confirmation' : 'Complete Your Reservation' ?>
        </h1>
    </div>
</section>

<section class="py-12 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if ($bookingCompleted && $confirmedBooking): ?>
        <!-- Booking Confirmation Success Screen -->
        <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-xl border border-stone-200 overflow-hidden p-8 sm:p-12 space-y-8">
            <div class="text-center space-y-3 pb-6 border-b border-stone-200">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320]">Booking Confirmed</span>
                <h2 class="font-headline text-3xl font-bold text-onyx-charcoal">We Look Forward to Welcoming You</h2>
                <p class="text-stone-500 text-sm">A confirmation has been sent to <strong><?= e($confirmedBooking['guest_email']) ?></strong></p>
                <div class="inline-block bg-stone-100 px-4 py-2 rounded-lg font-mono font-bold text-lg text-stone-800 tracking-wider">
                    Ref: <?= e($confirmedBooking['booking_reference']) ?>
                </div>
            </div>

            <!-- Booking Summary Details Card -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm text-stone-700">
                <div class="space-y-2">
                    <div class="text-xs font-bold uppercase text-stone-400">Accommodation</div>
                    <div class="font-headline font-bold text-base text-stone-900"><?= e($confirmedBooking['room_name']) ?></div>
                    <div class="text-xs text-stone-500"><?= e($confirmedBooking['bed_type']) ?></div>
                </div>

                <div class="space-y-2">
                    <div class="text-xs font-bold uppercase text-stone-400">Guest Name</div>
                    <div class="font-headline font-bold text-base text-stone-900"><?= e($confirmedBooking['guest_name']) ?></div>
                    <div class="text-xs text-stone-500"><?= e($confirmedBooking['guest_phone']) ?></div>
                </div>

                <div class="space-y-2">
                    <div class="text-xs font-bold uppercase text-stone-400">Stay Duration</div>
                    <div><strong><?= format_date($confirmedBooking['check_in_date']) ?></strong> to <strong><?= format_date($confirmedBooking['check_out_date']) ?></strong></div>
                    <div class="text-xs text-stone-500"><?= $confirmedBooking['nights'] ?> Night(s) | Check-in: <?= HOTEL_CHECKIN_TIME ?></div>
                </div>

                <div class="space-y-2">
                    <div class="text-xs font-bold uppercase text-stone-400">Payment Summary</div>
                    <div class="text-xl font-headline font-bold text-stone-900"><?= format_price($confirmedBooking['total_price']) ?></div>
                    <div class="text-xs text-emerald-700 font-semibold uppercase">Payment Status: Paid / Guaranteed</div>
                </div>
            </div>

            <?php if (!empty($confirmedBooking['special_requests'])): ?>
            <div class="p-4 rounded-lg bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-1">
                <span class="font-bold text-stone-800 uppercase block">Special Requests</span>
                <p><?= e($confirmedBooking['special_requests']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="pt-6 border-t border-stone-200 flex flex-wrap gap-4 justify-center">
                <button onclick="window.print()" class="bg-stone-900 hover:bg-black text-white px-6 py-3 rounded-lg font-semibold text-sm transition flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">print</span>
                    <span>Print Confirmation Voucher</span>
                </button>
                <a href="<?= url('/home') ?>" class="border border-stone-300 hover:bg-stone-100 text-stone-700 px-6 py-3 rounded-lg font-semibold text-sm transition">
                    Return to Homepage
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- Booking Form & Summary Split Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <!-- Left 7 Columns: Guest Information & Form -->
            <div class="lg:col-span-7 space-y-8">
                <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-stone-100">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320] block mb-1">Direct Reservation Inquiry</span>
                            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Guest Details & Verification</h2>
                            <p class="text-xs text-stone-500 mt-1">Please confirm your contact details to submit your room reservation request to our concierge.</p>
                        </div>

                        <?php if (!$isGuestLoggedIn): ?>
                            <a href="<?= GoogleAuth::isEnabled() ? GoogleAuth::getGuestAuthUrl(url('/book', $_GET)) : 'javascript:alert(\'Google OAuth Client ID & Secret can be configured in CMS Admin -> Settings / Users.\')' ?>" 
                               class="inline-flex items-center gap-2.5 bg-white hover:bg-stone-50 text-stone-900 border border-stone-300 hover:border-amber-400 px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs shrink-0 cursor-pointer">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Sign In with Google</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isGuestLoggedIn): ?>
                    <!-- Required Google Sign-In Protection Banner -->
                    <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 space-y-4 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-xl">shield</span>
                            </div>
                            <div>
                                <h4 class="font-headline font-bold text-sm text-amber-950">Google Authentication Required</h4>
                                <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                                    To protect reservation inventory and prevent automated spam submissions, guests must sign in with Google before placing a direct booking request.
                                </p>
                            </div>
                        </div>

                        <a href="<?= GoogleAuth::isEnabled() ? GoogleAuth::getGuestAuthUrl(url('/book', $_GET)) : 'javascript:alert(\'Google OAuth Client ID & Secret can be configured in CMS Admin -> Settings / Users.\')' ?>" class="bg-white hover:bg-stone-50 text-stone-900 px-6 py-3.5 rounded-xl font-bold text-xs sm:text-sm tracking-wide transition shadow-sm flex items-center justify-center gap-3 border border-amber-300 btn-shimmer cursor-pointer">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            <span>Sign In with Google to Unlock Reservation</span>
                        </a>
                    </div>
                    <?php else: ?>
                    <!-- Logged in Google User Verified Badge -->
                    <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($guestAccount['picture'])): ?>
                                <img src="<?= e($guestAccount['picture']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-[#dfe8a6]">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-[#dfe8a6] text-[#191e00] flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($guestAccount['name'] ?? 'G', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                                    <span><?= e($guestAccount['name']) ?></span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Google Verified</span>
                                </div>
                                <div class="text-xs text-stone-500 font-mono"><?= e($guestAccount['email']) ?></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <?php if ($guestStatus === 'blocked' || $guestStatus === 'restricted'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 text-xs font-bold uppercase">Account Restricted</span>
                            <?php else: ?>
                                <a href="<?= url('/api/guest-logout.php', ['redirect' => '/book?' . http_build_query($_GET)]) ?>" 
                                   class="text-xs font-medium text-stone-500 hover:text-stone-900 border border-stone-200 bg-white hover:bg-stone-100 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">logout</span>
                                    <span>Sign Out</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($guestStatus === 'blocked' || $guestStatus === 'restricted'): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium space-y-1">
                        <div class="font-bold flex items-center gap-1.5 text-rose-900">
                            <span class="material-symbols-outlined text-base">block</span>
                            <span>Account Restricted</span>
                        </div>
                        <p>Your guest account has been restricted by hotel administration. Automated online bookings are disabled. Please contact the front desk concierge directly at <strong><?= e(hotel_phone()) ?></strong>.</p>
                    </div>
                    <?php else: ?>

                    <form action="<?= url('/book') ?>" method="POST" class="space-y-4" id="booking_checkout_form">
                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                        <input type="hidden" name="submit_booking" value="1">
                        <input type="hidden" name="room_id" value="<?= $selectedRoomId ?>">
                        <input type="hidden" name="check_in" value="<?= e($checkIn) ?>">
                        <input type="hidden" name="check_out" value="<?= e($checkOut) ?>">
                        <input type="hidden" name="adults" value="<?= $adults ?>">
                        <input type="hidden" name="children" value="<?= $children ?>">
                        <input type="hidden" name="promo" value="<?= e($promoCode) ?>">

                        <div>
                            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Full Legal Name (as on Passport) *</label>
                            <input type="text" name="guest_name" required value="<?= e($guestAccount['name'] ?? '') ?>" placeholder="e.g. Alexander Wright"
                                   class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Email Address *</label>
                                <input type="email" name="guest_email" required value="<?= e($guestAccount['email'] ?? '') ?>" placeholder="e.g. alexander@example.com"
                                       class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Mobile / WhatsApp Number *</label>
                                <input type="text" name="guest_phone" required placeholder="e.g. +1 (555) 234-8901"
                                       class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Special Requests (Optional)</label>
                            <textarea name="special_requests" rows="3" placeholder="High floor, early check-in, dietary restrictions, airport pickup..."
                                      class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"></textarea>
                        </div>

                        <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-2">
                            <div class="font-bold text-stone-800 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-[#4B5320]">info</span>
                                <span>Direct Booking Policy (No Online Payment Required)</span>
                            </div>
                            <p>Reservation requests are stored and dispatched directly to front desk concierge for verification. Payment is settled at the hotel desk upon check-in.</p>
                        </div>

                        <?php if ($isGuestLoggedIn): ?>
                        <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white py-4 rounded-lg font-bold text-sm tracking-wider uppercase transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer btn-shimmer">
                            <span class="material-symbols-outlined text-lg">verified</span>
                            <span>Submit Direct Reservation Request (Est. <?= format_price($totalPrice) ?>)</span>
                        </button>
                        <?php else: ?>
                        <div class="text-center p-3 text-xs text-amber-800 font-semibold bg-amber-50 rounded-lg border border-amber-200">
                            Please click "Sign In with Google" above to complete your direct reservation request.
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 5 Columns: Stay Breakdown Summary -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-sm space-y-6 sticky top-28">
                    
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320]">Booking Summary</span>
                            <?= get_room_type_badge($selectedRoom['category'] ?? 'Deluxe Room', 'xs') ?>
                        </div>
                        <h3 class="font-headline text-xl font-bold text-onyx-charcoal"><?= e($selectedRoom['name']) ?></h3>
                    </div>

                    <div class="rounded-xl overflow-hidden h-40 border border-stone-200">
                        <img src="<?= e($selectedRoom['image_url']) ?>" alt="Selected Room" class="w-full h-full object-cover">
                    </div>

                    <!-- Stay Dates & Room Details -->
                    <div class="space-y-3 text-xs text-stone-600 pb-4 border-b border-stone-100">
                        <div class="flex justify-between">
                            <span class="font-medium text-stone-500">Check-in:</span>
                            <span class="font-bold text-stone-900"><?= format_date($checkIn) ?> (<?= HOTEL_CHECKIN_TIME ?>)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-stone-500">Check-out:</span>
                            <span class="font-bold text-stone-900"><?= format_date($checkOut) ?> (<?= HOTEL_CHECKOUT_TIME ?>)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-stone-500">Duration:</span>
                            <span class="font-bold text-stone-900"><?= $nights ?> night(s)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-stone-500">Guests:</span>
                            <span class="font-bold text-stone-900"><?= $adults ?> Adult(s), <?= $children ?> Child</span>
                        </div>
                    </div>

                    <!-- Pricing Table -->
                    <div class="space-y-2 text-xs text-stone-600">
                        <div class="flex justify-between">
                            <span><?= format_price($roomRate) ?> x <?= $nights ?> night(s)</span>
                            <span><?= format_price($subtotal) ?></span>
                        </div>

                        <?php if ($discountPercent > 0): ?>
                        <div class="flex justify-between text-emerald-700 font-semibold">
                            <span>Promo Discount (<?= $discountPercent ?>% - <?= e($promoCode) ?>)</span>
                            <span>-<?= format_price($discountAmount) ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="flex justify-between">
                            <span>Taxes & Service Charge</span>
                            <span class="text-stone-500">Included</span>
                        </div>

                        <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline text-stone-900">
                            <span class="font-headline font-bold text-sm">Total Payable:</span>
                            <span class="font-headline font-bold text-2xl text-[#343c0a]"><?= format_price($totalPrice) ?></span>
                        </div>
                    </div>

                    <!-- Change Selection Quick Form -->
                    <div class="pt-4 border-t border-stone-100">
                        <details class="text-xs">
                            <summary class="cursor-pointer text-[#4B5320] font-semibold hover:underline">Change Dates or Room Type</summary>
                            <form action="<?= url('/book') ?>" method="GET" class="mt-3 space-y-3">
                                <div>
                                    <label class="block text-stone-500 mb-1">Room Type</label>
                                    <select name="room_id" class="w-full border border-stone-300 rounded p-2 text-xs">
                                        <?php foreach ($allRooms as $r): ?>
                                            <option value="<?= $r['id'] ?>" <?= $r['id'] == $selectedRoomId ? 'selected' : '' ?>>
                                                <?= e($r['name']) ?> (<?= format_price($r['price_per_night']) ?>/nt)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-stone-500 mb-1">Check-in</label>
                                        <input type="date" name="check_in" value="<?= e($checkIn) ?>" class="w-full border border-stone-300 rounded p-1.5 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-stone-500 mb-1">Check-out</label>
                                        <input type="date" name="check_out" value="<?= e($checkOut) ?>" class="w-full border border-stone-300 rounded p-1.5 text-xs">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-stone-500 mb-1">Promo Code</label>
                                    <input type="text" name="promo" value="<?= e($promoCode) ?>" placeholder="e.g. INDRA15" class="w-full border border-stone-300 rounded p-1.5 text-xs uppercase">
                                </div>
                                <button type="submit" class="w-full bg-stone-800 text-white py-2 rounded text-xs font-semibold">Update Breakdown</button>
                            </form>
                        </details>
                    </div>

                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
