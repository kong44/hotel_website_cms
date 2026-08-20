<?php
/**
 * Indra Hotel - Guest Portal & Booking History (Public User Google Auth)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/google-auth.php';

$pdo = getDB();
$isGuest = is_guest_logged_in();
$guest = get_logged_in_guest();

$ref = trim($_GET['ref'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
$singleBooking = null;
$error = '';
$guestBookings = [];

// 1. If searching a specific reference number
if (!empty($ref)) {
    $stmt = $pdo->prepare("SELECT b.*, r.name as room_name, r.bed_type, r.image_url, r.size_sqm, r.slug as room_slug FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE UPPER(b.booking_reference) = UPPER(?) LIMIT 1");
    $stmt->execute([$ref]);
    $singleBooking = $stmt->fetch();

    if (!$singleBooking) {
        $error = "No reservation found matching reference '{$ref}'. Please verify your confirmation code.";
    }
}

// 2. If guest is logged in with Google, fetch all their bookings
if ($isGuest && !empty($guest['email'])) {
    $stmtG = $pdo->prepare("
        SELECT b.*, r.name as room_name, r.bed_type, r.image_url, r.size_sqm, r.slug as room_slug 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE LOWER(b.guest_email) = ? 
        ORDER BY b.check_in_date DESC
    ");
    $stmtG->execute([strtolower($guest['email'])]);
    $guestBookings = $stmtG->fetchAll();
}

// Calculate guest metrics if logged in
$totalGuestBookings = count($guestBookings);
$upcomingGuestBookings = 0;
$completedGuestBookings = 0;
$cancelledGuestBookings = 0;
$totalSpent = 0;

$today = date('Y-m-d');
foreach ($guestBookings as $b) {
    if ($b['status'] === 'cancelled') {
        $cancelledGuestBookings++;
    } elseif ($b['check_out_date'] < $today || $b['status'] === 'checked_out') {
        $completedGuestBookings++;
        $totalSpent += (float)$b['total_price'];
    } else {
        $upcomingGuestBookings++;
        $totalSpent += (float)$b['total_price'];
    }
}

// Filter bookings list
$filteredBookings = array_filter($guestBookings, function($b) use ($filter, $today) {
    if ($filter === 'upcoming') {
        return $b['status'] !== 'cancelled' && $b['status'] !== 'checked_out' && $b['check_out_date'] >= $today;
    }
    if ($filter === 'completed') {
        return $b['status'] === 'checked_out' || ($b['status'] !== 'cancelled' && $b['check_out_date'] < $today);
    }
    if ($filter === 'cancelled') {
        return $b['status'] === 'cancelled';
    }
    return true;
});

$pageMeta = [
    'title' => 'Guest Portal & Booking History | ' . hotel_name(),
    'description' => 'Sign in with Google to view and track your complete reservation history, upcoming luxury stays, check-in instructions, and booking receipts.',
    'keywords' => 'hotel booking history, track reservation phnom penh, indra hotel guest portal',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<section class="bg-onyx-charcoal text-white py-14 relative overflow-hidden">
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-[#dfe8a6] text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="material-symbols-outlined text-sm">luggage</span>
                    <span>Guest Reservation Portal</span>
                </div>
                <h1 class="font-headline text-3xl sm:text-4xl font-bold tracking-tight text-white">
                    <?= $isGuest ? 'Welcome, ' . e($guest['name']) : 'Find & Track Your Bookings' ?>
                </h1>
                <p class="text-stone-300 text-sm max-w-2xl font-light mt-1">
                    <?= $isGuest 
                        ? 'Manage your luxury stay details, upcoming check-ins, payment receipts, and booking vouchers.' 
                        : 'Sign in with Google to access your complete stay history or search by reservation code.' ?>
                </p>
            </div>

            <!-- Guest Profile Status / Actions in Hero -->
            <div>
                <?php if ($isGuest): ?>
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 p-3.5 rounded-2xl flex items-center gap-4">
                        <?php if (!empty($guest['picture'])): ?>
                            <img src="<?= e($guest['picture']) ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover border border-[#dfe8a6] shadow-sm">
                        <?php else: ?>
                            <div class="w-11 h-11 rounded-full bg-[#dfe8a6] text-[#191e00] flex items-center justify-center font-bold text-base shadow-sm">
                                <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-left">
                            <div class="text-xs font-bold text-white"><?= e($guest['name']) ?></div>
                            <div class="text-[11px] text-[#dfe8a6] font-mono"><?= e($guest['email']) ?></div>
                        </div>

                        <a href="<?= BASE_URL ?>/api/guest-logout.php" 
                           class="ml-2 px-3 py-1.5 rounded-lg bg-black/40 hover:bg-black text-stone-200 hover:text-white text-xs font-semibold transition border border-white/10 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">logout</span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                <?php elseif (GoogleAuth::isEnabled()): ?>
                    <a href="<?= BASE_URL ?>/api/guest-google-login.php" 
                       class="bg-white hover:bg-stone-100 text-stone-900 px-5 py-3 rounded-xl font-bold text-xs sm:text-sm tracking-wide transition shadow-lg flex items-center gap-3 border border-white/20 btn-shimmer">
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Sign In with Google</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="py-12 bg-[#f9f9f9] min-h-[60vh]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- =======================================================
             CASE 1: Guest is Logged In via Google (Full Dashboard)
             ======================================================= -->
        <?php if ($isGuest): ?>

            <!-- Guest Metrics KPI Ribbon -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-stone-400 block">Total Stays</span>
                    <div class="font-headline text-2xl font-bold text-onyx-charcoal mt-1"><?= $totalGuestBookings ?></div>
                    <span class="text-[10px] text-stone-500">Reservations on file</span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 block">Upcoming</span>
                    <div class="font-headline text-2xl font-bold text-emerald-800 mt-1"><?= $upcomingGuestBookings ?></div>
                    <span class="text-[10px] text-stone-500">Ready for check-in</span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-stone-500 block">Completed</span>
                    <div class="font-headline text-2xl font-bold text-stone-700 mt-1"><?= $completedGuestBookings ?></div>
                    <span class="text-[10px] text-stone-500">Past experiences</span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#343c0a] block">Total Spent</span>
                    <div class="font-headline text-2xl font-bold text-[#343c0a] mt-1"><?= format_price($totalSpent) ?></div>
                    <span class="text-[10px] text-stone-500">Lifetime value</span>
                </div>
            </div>

            <!-- Booking History Controls & Tabs -->
            <div class="bg-white rounded-3xl border border-stone-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-stone-100">
                    <div>
                        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Your Booking History</h2>
                        <p class="text-xs text-stone-500">Linked to verified email: <strong><?= e($guest['email']) ?></strong></p>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex items-center gap-1.5 text-xs font-semibold overflow-x-auto">
                        <a href="<?= BASE_URL ?>/my-booking.php?filter=all" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'all' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            All (<?= $totalGuestBookings ?>)
                        </a>
                        <a href="<?= BASE_URL ?>/my-booking.php?filter=upcoming" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'upcoming' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            Upcoming (<?= $upcomingGuestBookings ?>)
                        </a>
                        <a href="<?= BASE_URL ?>/my-booking.php?filter=completed" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'completed' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            Completed (<?= $completedGuestBookings ?>)
                        </a>
                        <?php if ($cancelledGuestBookings > 0): ?>
                        <a href="<?= BASE_URL ?>/my-booking.php?filter=cancelled" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'cancelled' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            Cancelled (<?= $cancelledGuestBookings ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bookings Cards List -->
                <?php if (!empty($filteredBookings)): ?>
                    <div class="space-y-6">
                        <?php foreach ($filteredBookings as $b): 
                            $statusStyle = match($b['status']) {
                                'confirmed' => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                                'checked_in' => 'bg-blue-50 text-blue-800 border-blue-300',
                                'checked_out' => 'bg-stone-100 text-stone-700 border-stone-300',
                                'cancelled' => 'bg-rose-50 text-rose-800 border-rose-300',
                                default => 'bg-amber-50 text-amber-800 border-amber-300'
                            };
                            $isUpcoming = ($b['status'] !== 'cancelled' && $b['status'] !== 'checked_out' && $b['check_out_date'] >= $today);
                        ?>
                        <div class="bg-[#fbfbf9] rounded-2xl border border-stone-200 p-6 sm:p-7 hover:border-stone-300 transition shadow-2xs flex flex-col lg:flex-row gap-6 items-start lg:items-center justify-between">
                            
                            <div class="flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                                <!-- Room Photo Thumbnail -->
                                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden border border-stone-200 shrink-0 bg-stone-200">
                                    <img src="<?= e($b['image_url']) ?>" alt="<?= e($b['room_name']) ?>" class="w-full h-full object-cover">
                                </div>

                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border <?= $statusStyle ?>">
                                            <?= strtoupper(str_replace('_', ' ', $b['status'])) ?>
                                        </span>
                                        <span class="text-xs font-mono font-bold text-stone-500 bg-white px-2.5 py-0.5 rounded border border-stone-200">
                                            #<?= e($b['booking_reference']) ?>
                                        </span>
                                        <?php if ($isUpcoming): ?>
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                                <span>Upcoming Stay</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="font-headline font-bold text-xl text-onyx-charcoal">
                                        <a href="<?= BASE_URL ?>/room.php?slug=<?= urlencode($b['room_slug']) ?>" class="hover:text-[#4B5320] transition">
                                            <?= e($b['room_name']) ?>
                                        </a>
                                    </h3>

                                    <div class="flex flex-wrap items-center gap-4 text-xs text-stone-600 font-medium">
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm text-[#4B5320]">calendar_today</span>
                                            <span><strong>Check-in:</strong> <?= format_date($b['check_in_date']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm text-[#4B5320]">event_available</span>
                                            <span><strong>Check-out:</strong> <?= format_date($b['check_out_date']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm text-[#4B5320]">bedtime</span>
                                            <span><?= (int)$b['nights'] ?> Night(s)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Details & Actions -->
                            <div class="flex flex-col sm:flex-row lg:flex-col items-start lg:items-end justify-between gap-4 w-full lg:w-auto pt-4 lg:pt-0 border-t lg:border-t-0 border-stone-200">
                                <div class="text-left lg:text-right">
                                    <div class="font-headline font-bold text-xl sm:text-2xl text-[#343c0a]">
                                        <?= format_price($b['total_price']) ?>
                                    </div>
                                    <span class="text-[11px] text-stone-500 block">Payment Status: <strong class="capitalize"><?= e($b['payment_status']) ?></strong></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a href="<?= BASE_URL ?>/my-booking.php?ref=<?= urlencode($b['booking_reference']) ?>" 
                                       class="px-4 py-2 rounded-xl text-xs font-bold bg-[#343c0a] hover:bg-deep-olive text-white transition flex items-center gap-1 shadow-2xs btn-shimmer">
                                        <span class="material-symbols-outlined text-sm">receipt_long</span>
                                        <span>View Voucher</span>
                                    </a>

                                    <a href="<?= BASE_URL ?>/book.php?room_id=<?= (int)$b['room_id'] ?>" 
                                       class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-stone-100 text-stone-700 transition border border-stone-300 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">repeat</span>
                                        <span>Re-book</span>
                                    </a>
                                </div>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-stone-50 rounded-2xl p-12 text-center border border-stone-200 space-y-3">
                        <div class="w-14 h-14 rounded-2xl bg-stone-200 text-stone-500 flex items-center justify-center mx-auto">
                            <span class="material-symbols-outlined text-3xl">hotel</span>
                        </div>
                        <h3 class="font-headline font-bold text-lg text-onyx-charcoal">No Bookings Found</h3>
                        <p class="text-xs text-stone-500 max-w-md mx-auto">
                            There are currently no reservations under <strong><?= e($guest['email']) ?></strong> for the "<?= e($filter) ?>" filter.
                        </p>
                        <a href="<?= BASE_URL ?>/rooms.php" class="inline-flex items-center gap-1 text-xs font-bold text-[#343c0a] hover:underline pt-2">
                            <span>Browse Accommodations & Book Your Next Stay</span>
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>

        <!-- =======================================================
             CASE 2: Single Booking Voucher View (Searched via Ref)
             ======================================================= -->
        <?php if ($singleBooking): ?>
        <div class="bg-white rounded-3xl shadow-xl border border-stone-200 overflow-hidden p-8 sm:p-12 space-y-8 print:shadow-none print:border-none">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-stone-200">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320]">Official Reservation Voucher</span>
                    <h2 class="font-headline text-3xl font-bold text-onyx-charcoal mt-1"><?= e($singleBooking['room_name']) ?></h2>
                    <div class="text-xs text-stone-500 mt-1 font-mono">Confirmation Reference: <strong class="text-stone-900"><?= e($singleBooking['booking_reference']) ?></strong></div>
                </div>

                <div>
                    <?php
                    $statusBadge = match($singleBooking['status']) {
                        'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                        'checked_in' => 'bg-blue-100 text-blue-800 border-blue-300',
                        'checked_out' => 'bg-stone-100 text-stone-800 border-stone-300',
                        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300',
                        default => 'bg-amber-100 text-amber-800 border-amber-300'
                    };
                    ?>
                    <span class="inline-block px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider border <?= $statusBadge ?>">
                        <?= strtoupper(str_replace('_', ' ', $singleBooking['status'])) ?>
                    </span>
                </div>
            </div>

            <!-- Booking Specifications Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 text-xs text-stone-600 bg-stone-50 p-6 rounded-2xl border border-stone-200">
                <div>
                    <span class="font-bold uppercase text-stone-400 block mb-1">Check-in Date</span>
                    <div class="font-headline font-bold text-sm text-stone-900"><?= format_date($singleBooking['check_in_date']) ?></div>
                    <div class="text-stone-500">From <?= HOTEL_CHECKIN_TIME ?></div>
                </div>
                <div>
                    <span class="font-bold uppercase text-stone-400 block mb-1">Check-out Date</span>
                    <div class="font-headline font-bold text-sm text-stone-900"><?= format_date($singleBooking['check_out_date']) ?></div>
                    <div class="text-stone-500">Until <?= HOTEL_CHECKOUT_TIME ?></div>
                </div>
                <div>
                    <span class="font-bold uppercase text-stone-400 block mb-1">Duration & Guests</span>
                    <div class="font-headline font-bold text-sm text-stone-900"><?= $singleBooking['nights'] ?> Night(s)</div>
                    <div class="text-stone-500"><?= $singleBooking['adults'] ?> Adults, <?= $singleBooking['children'] ?> Child</div>
                </div>
                <div>
                    <span class="font-bold uppercase text-stone-400 block mb-1">Total Rate</span>
                    <div class="font-headline font-bold text-base text-[#343c0a]"><?= format_price($singleBooking['total_price']) ?></div>
                    <div class="text-stone-500 capitalize font-medium">Payment: <?= e($singleBooking['payment_status']) ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm text-stone-700">
                <div class="space-y-1">
                    <span class="text-xs font-bold uppercase text-stone-400">Guest Information</span>
                    <div class="font-bold text-stone-900"><?= e($singleBooking['guest_name']) ?></div>
                    <div class="text-xs text-stone-500 font-mono"><?= e($singleBooking['guest_email']) ?> | <?= e($singleBooking['guest_phone']) ?></div>
                </div>
                <div class="space-y-1">
                    <span class="text-xs font-bold uppercase text-stone-400">Sanctuary Address</span>
                    <div class="font-bold text-stone-900"><?= HOTEL_NAME ?></div>
                    <div class="text-xs text-stone-500"><?= HOTEL_ADDRESS_STREET ?>, <?= HOTEL_ADDRESS_DISTRICT ?>, Phnom Penh, Cambodia</div>
                </div>
            </div>

            <?php if (!empty($singleBooking['special_requests'])): ?>
            <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-1">
                <span class="font-bold text-stone-800 uppercase block">Special Requests</span>
                <p><?= e($singleBooking['special_requests']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="pt-6 border-t border-stone-200 flex flex-wrap gap-4 justify-between items-center print:hidden">
                <a href="tel:<?= HOTEL_PHONE_RAW ?>" class="text-xs font-semibold text-stone-600 hover:text-stone-900 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">phone</span>
                    <span>Questions? Call Front Desk: <?= HOTEL_PHONE ?></span>
                </a>
                <div class="flex items-center gap-3">
                    <?php if ($isGuest): ?>
                        <a href="<?= BASE_URL ?>/my-booking.php" class="px-5 py-2.5 border border-stone-300 hover:bg-stone-50 text-stone-700 rounded-xl text-xs font-bold transition">
                            Back to All Stays
                        </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="bg-stone-900 hover:bg-black text-white px-6 py-2.5 rounded-xl text-xs font-bold tracking-wider uppercase transition flex items-center gap-2 cursor-pointer shadow">
                        <span class="material-symbols-outlined text-sm">print</span>
                        <span>Print Voucher</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- =======================================================
             CASE 3: Manual Reference Search Bar & Sign-In Callout
             ======================================================= -->
        <div class="grid grid-cols-1 <?= !$isGuest ? 'md:grid-cols-2' : '' ?> gap-8">
            
            <!-- Manual Reference Search Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-stone-200 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#343c0a]">search</span>
                    <h3 class="font-headline font-bold text-lg text-onyx-charcoal">Lookup Specific Booking Code</h3>
                </div>
                <p class="text-xs text-stone-500">Have a reservation confirmation number? Enter it below to retrieve the voucher.</p>
                
                <form action="<?= BASE_URL ?>/my-booking.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="ref" value="<?= e($ref) ?>" required placeholder="e.g. IND-2026-8492"
                           class="flex-1 uppercase font-mono text-xs border border-stone-300 rounded-xl p-3 focus:ring-2 focus:ring-[#343c0a]">
                    <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-3 rounded-xl font-bold text-xs tracking-wide transition shadow btn-shimmer cursor-pointer">
                        Lookup
                    </button>
                </form>

                <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">error</span>
                    <span><?= e($error) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Public Sign-in with Google Promotion (If not logged in) -->
            <?php if (!$isGuest && GoogleAuth::isEnabled()): ?>
            <div class="bg-gradient-to-br from-[#343c0a] to-[#202506] rounded-3xl p-6 sm:p-8 text-white space-y-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-[#dfe8a6] text-[10px] font-bold uppercase tracking-wider mb-2">
                        <span>1-Click Access</span>
                    </div>
                    <h3 class="font-headline font-bold text-xl text-white">Track All Your Bookings in One Place</h3>
                    <p class="text-xs text-stone-300 mt-1 leading-relaxed">
                        Sign in with your Google account to automatically track your current, upcoming, and past stays without needing to search confirmation codes.
                    </p>
                </div>

                <a href="<?= BASE_URL ?>/api/guest-google-login.php" 
                   class="bg-white hover:bg-stone-100 text-stone-900 px-6 py-3.5 rounded-xl font-bold text-xs tracking-wide transition shadow-lg flex items-center justify-center gap-3 border border-white/20 btn-shimmer cursor-pointer">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>
            </div>
            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
