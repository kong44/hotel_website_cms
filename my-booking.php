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

    // Fetch all contact inquiries/messages submitted by guest
    $stmtM = $pdo->prepare("
        SELECT * 
        FROM messages 
        WHERE LOWER(email) = ? 
        ORDER BY created_at DESC
    ");
    $stmtM->execute([strtolower($guest['email'])]);
    $guestMessages = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}
$totalGuestMessages = count($guestMessages);

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

                        <a href="<?= url('/api/guest-logout.php') ?>" 
                           class="ml-2 px-3 py-1.5 rounded-lg bg-black/40 hover:bg-black text-stone-200 hover:text-white text-xs font-semibold transition border border-white/10 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">logout</span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                <?php elseif (GoogleAuth::isEnabled()): ?>
                    <a href="<?= url('/api/guest-google-login.php') ?>" 
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
             CASE 1: Guest is Logged In via Google (Full Profile & Dashboard)
             ======================================================= -->
        <?php if ($isGuest): ?>

            <!-- Guest Profile Overview Card -->
            <div class="bg-white rounded-3xl border border-stone-200 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-6 border-b border-stone-100">
                    <div class="flex items-center gap-4 sm:gap-6">
                        <?php if (!empty($guest['picture'])): ?>
                            <img src="<?= e($guest['picture']) ?>" alt="<?= e($guest['name']) ?>" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover shadow-md border-2 border-[#dfe8a6]">
                        <?php else: ?>
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-[#343c0a] text-white flex items-center justify-center font-bold text-2xl shadow-md">
                                <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h2 class="font-headline text-xl sm:text-2xl font-bold text-onyx-charcoal"><?= e($guest['name']) ?></h2>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                    <span class="material-symbols-outlined text-[13px]">verified</span> Verified Profile
                                </span>
                            </div>
                            <div class="text-xs sm:text-sm text-stone-500 font-mono flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-stone-400">mail</span>
                                <span><?= e($guest['email']) ?></span>
                            </div>
                            <div class="text-[11px] text-stone-400 flex items-center gap-3">
                                <span>Provider: <strong class="text-stone-600">Google SSO</strong></span>
                                <span>•</span>
                                <span>Status: <strong class="text-emerald-700">Active Account</strong></span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= url('/api/guest-logout.php', ['redirect' => url('/home')]) ?>" 
                       class="px-4 py-2.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-bold transition flex items-center gap-2 border border-stone-200">
                        <span class="material-symbols-outlined text-base">logout</span>
                        <span>Sign Out Account</span>
                    </a>
                </div>

                <!-- Account Information Summary Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-4 rounded-xl bg-stone-50 border border-stone-100 space-y-1">
                        <span class="text-stone-400 uppercase font-semibold text-[10px] tracking-wider block">Full Name</span>
                        <div class="font-bold text-stone-900 text-sm truncate"><?= e($guest['name']) ?></div>
                        <div class="text-stone-500 text-[11px]">Verified Google Name</div>
                    </div>

                    <div class="p-4 rounded-xl bg-stone-50 border border-stone-100 space-y-1">
                        <span class="text-stone-400 uppercase font-semibold text-[10px] tracking-wider block">Email Identity</span>
                        <div class="font-mono font-bold text-stone-900 text-sm truncate"><?= e($guest['email']) ?></div>
                        <div class="text-emerald-700 text-[11px] font-medium">✓ Single Sign-On Verified</div>
                    </div>

                    <div class="p-4 rounded-xl bg-stone-50 border border-stone-100 space-y-1">
                        <span class="text-stone-400 uppercase font-semibold text-[10px] tracking-wider block">Guest Account Status</span>
                        <div class="font-bold text-stone-900 text-sm flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Authenticated</span>
                        </div>
                        <div class="text-stone-500 text-[11px]">Anti-Spam Protected</div>
                    </div>
                </div>
            </div>

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
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 block">Messages Sent</span>
                    <div class="font-headline text-2xl font-bold text-indigo-800 mt-1"><?= $totalGuestMessages ?></div>
                    <span class="text-[10px] text-stone-500">Contact Inquiries</span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#343c0a] block">Account Status</span>
                    <div class="font-headline text-2xl font-bold text-[#343c0a] mt-1">Verified</div>
                    <span class="text-[10px] text-stone-500">Google SSO Secured</span>
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
                        <a href="<?= url('/my-booking', ['filter' => 'all']) ?>" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'all' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            All (<?= $totalGuestBookings ?>)
                        </a>
                        <a href="<?= url('/my-booking', ['filter' => 'upcoming']) ?>" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'upcoming' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            Upcoming (<?= $upcomingGuestBookings ?>)
                        </a>
                        <a href="<?= url('/my-booking', ['filter' => 'completed']) ?>" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'completed' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
                            Completed (<?= $completedGuestBookings ?>)
                        </a>
                        <?php if ($cancelledGuestBookings > 0): ?>
                        <a href="<?= url('/my-booking', ['filter' => 'cancelled']) ?>" class="px-3.5 py-2 rounded-xl transition <?= $filter === 'cancelled' ? 'bg-[#343c0a] text-white shadow-2xs' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">
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
                                        <a href="<?= url('/room/' . urlencode($b['room_slug'])) ?>" class="hover:text-[#4B5320] transition">
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
                                    <a href="<?= url('/my-booking', ['ref' => $b['booking_reference']]) ?>" 
                                       class="px-4 py-2 rounded-xl text-xs font-bold bg-[#343c0a] hover:bg-deep-olive text-white transition flex items-center gap-1 shadow-2xs btn-shimmer">
                                        <span class="material-symbols-outlined text-sm">receipt_long</span>
                                        <span>View Voucher</span>
                                    </a>

                                    <a href="<?= url('/book', ['room_id' => (int)$b['room_id']]) ?>" 
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
                        <a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-1 text-xs font-bold text-[#343c0a] hover:underline pt-2">
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
        <?php if ($singleBooking): 
            $b = $singleBooking;
            $isPaid = ($b['payment_status'] === 'paid');
            $statusColors = [
                'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'pending' => 'bg-amber-100 text-amber-800 border-amber-300',
                'checked_in' => 'bg-blue-100 text-blue-800 border-blue-300',
                'checked_out' => 'bg-stone-100 text-stone-800 border-stone-300',
                'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300'
            ];
            $statusBadge = $statusColors[$b['status']] ?? 'bg-stone-100 text-stone-800 border-stone-200';
        ?>
        <div class="bg-white rounded-3xl border border-stone-200 shadow-xl overflow-hidden p-6 sm:p-10 space-y-8 print:shadow-none print:border-none print:p-0">
            
            <!-- Voucher Header Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-6 border-b border-stone-200">
                <div class="flex items-center gap-4">
                    <?php if ($logoImg = hotel_logo_url()): ?>
                        <img src="<?= e($logoImg) ?>" alt="<?= e(hotel_name()) ?> Logo" class="h-12 w-auto object-contain">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-xl bg-[#343c0a] text-white flex items-center justify-center font-headline font-bold text-xl">
                            <?= strtoupper(substr(hotel_name(), 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-[#4B5320] block">Official Reservation Voucher</span>
                        <h2 class="font-headline font-bold text-2xl text-onyx-charcoal"><?= e(hotel_name()) ?></h2>
                    </div>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-xs text-stone-400 uppercase tracking-wider block">Booking Reference</span>
                    <span class="font-mono font-bold text-xl sm:text-2xl text-[#343c0a] tracking-wider"><?= e($b['booking_reference']) ?></span>
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $statusBadge ?> capitalize">
                            <?= str_replace('_', ' ', e($b['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Booking Overview Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Guest & Stay Specs -->
                <div class="space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400 block mb-2">Guest Information</span>
                        <div class="bg-stone-50 rounded-2xl p-5 border border-stone-200 space-y-2 text-xs">
                            <div class="flex justify-between"><span class="text-stone-500">Guest Name:</span><strong class="text-stone-900"><?= e($b['guest_name']) ?></strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Email:</span><strong class="text-stone-900"><?= e($b['guest_email']) ?></strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Phone:</span><strong class="text-stone-900"><?= e($b['guest_phone']) ?: 'N/A' ?></strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Country / Origin:</span><strong class="text-stone-900"><?= e($b['guest_country']) ?: 'N/A' ?></strong></div>
                        </div>
                    </div>

                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400 block mb-2">Stay Details</span>
                        <div class="bg-stone-50 rounded-2xl p-5 border border-stone-200 space-y-2 text-xs">
                            <div class="flex justify-between"><span class="text-stone-500">Check-in:</span><strong class="text-stone-900"><?= format_date($b['check_in_date']) ?> (From 14:00)</strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Check-out:</span><strong class="text-stone-900"><?= format_date($b['check_out_date']) ?> (Until 12:00)</strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Total Duration:</span><strong class="text-stone-900"><?= (int)$b['nights'] ?> Night(s)</strong></div>
                            <div class="flex justify-between"><span class="text-stone-500">Guests:</span><strong class="text-stone-900"><?= (int)$b['adults'] ?> Adults, <?= (int)$b['children'] ?> Children</strong></div>
                        </div>
                    </div>
                </div>

                <!-- Suite & Financial Breakdown -->
                <div class="space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400 block mb-2">Reserved Accommodation</span>
                        <div class="bg-stone-50 rounded-2xl p-5 border border-stone-200 flex gap-4 items-center">
                            <img src="<?= e($b['room_image']) ?>" alt="<?= e($b['room_name']) ?>" class="w-24 h-20 rounded-xl object-cover">
                            <div>
                                <h4 class="font-headline font-bold text-base text-onyx-charcoal"><?= e($b['room_name']) ?></h4>
                                <span class="text-xs text-stone-500"><?= e($b['bed_type']) ?> • <?= (int)$b['size_sqm'] ?> m²</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400 block mb-2">Billing Summary</span>
                        <div class="bg-stone-50 rounded-2xl p-5 border border-stone-200 space-y-2.5 text-xs">
                            <div class="flex justify-between"><span class="text-stone-500">Room Rate:</span><span class="font-mono"><?= format_price($b['room_price']) ?> / night</span></div>
                            <?php if ($b['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-emerald-700"><span>Special Promo Applied:</span><span class="font-mono font-bold">-<?= format_price($b['discount_amount']) ?></span></div>
                            <?php endif; ?>
                            <div class="flex justify-between text-stone-500"><span>Taxes & Service Charges:</span><span>Included</span></div>
                            <div class="flex justify-between pt-2 border-t border-stone-200 text-base font-headline font-bold text-stone-900">
                                <span>Grand Total:</span>
                                <span class="text-[#343c0a] font-mono text-xl"><?= format_price($b['total_price']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Action buttons -->
            <div class="pt-6 border-t border-stone-200 flex flex-wrap gap-4 justify-between items-center print:hidden">
                <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="text-xs font-semibold text-stone-600 hover:text-stone-900 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">phone</span>
                    <span>Questions? Call Front Desk: <?= e(hotel_phone()) ?></span>
                </a>
                <div class="flex items-center gap-3">
                    <?php if ($isGuest): ?>
                        <a href="<?= url('/my-booking') ?>" class="px-5 py-2.5 border border-stone-300 hover:bg-stone-50 text-stone-700 rounded-xl text-xs font-bold transition">
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

        <?php if ($isGuest && empty($selectedBooking)): ?>
        <!-- =======================================================
             Section: Guest Contact Messages & Inquiries History
             ======================================================= -->
        <div class="bg-white rounded-3xl border border-stone-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-stone-100">
                <div>
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#4B5320]">mail</span>
                        <span>My Inquiry & Message History</span>
                    </h2>
                    <p class="text-xs text-stone-500 mt-1">Submitted contact inquiries linked to <strong><?= e($guest['email']) ?></strong></p>
                </div>

                <a href="<?= url('/contact') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold transition shadow-2xs self-start sm:self-auto">
                    <span class="material-symbols-outlined text-sm">send</span>
                    <span>Send New Inquiry</span>
                </a>
            </div>

            <?php if (empty($guestMessages)): ?>
                <div class="p-12 text-center text-stone-400 space-y-3 bg-stone-50/50 rounded-2xl border border-stone-100">
                    <span class="material-symbols-outlined text-4xl text-stone-300">chat_bubble_outline</span>
                    <h3 class="font-headline font-bold text-base text-stone-700">No Messages Sent Yet</h3>
                    <p class="text-xs text-stone-500 max-w-sm mx-auto">Have questions for our concierge? You can submit inquiries directly through our contact portal.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($guestMessages as $msg): 
                        $statusBg = 'bg-amber-50 text-amber-900 border-amber-200';
                        $statusLabel = 'Pending Review';
                        if ($msg['status'] === 'read') {
                            $statusBg = 'bg-blue-50 text-blue-800 border-blue-200';
                            $statusLabel = 'Received by Hotel';
                        } elseif ($msg['status'] === 'replied') {
                            $statusBg = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                            $statusLabel = 'Replied by Staff';
                        }
                    ?>
                    <div class="p-5 rounded-2xl bg-stone-50 border border-stone-200 space-y-3 hover:border-stone-300 transition">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-stone-200/60">
                            <div>
                                <div class="font-headline font-bold text-base text-stone-900 flex items-center gap-2">
                                    <span><?= e($msg['subject'] ?: 'General Inquiry') ?></span>
                                </div>
                                <div class="text-[11px] text-stone-400 font-mono mt-0.5">
                                    Sent: <?= date('M d, Y • g:i A', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full border self-start sm:self-auto <?= $statusBg ?>">
                                <span class="material-symbols-outlined text-xs">mark_email_read</span>
                                <span><?= $statusLabel ?></span>
                            </span>
                        </div>

                        <div class="text-xs text-stone-700 leading-relaxed bg-white p-4 rounded-xl border border-stone-100 whitespace-pre-line font-normal">
                            <?= e($msg['message']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                
                <form action="<?= url('/my-booking') ?>" method="GET" class="flex flex-col sm:flex-row gap-3">
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

                <a href="<?= url('/api/guest-google-login.php') ?>" 
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
