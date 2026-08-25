<?php
/**
 * Indra Hotel - SoftBook CMS Dashboard
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Dashboard Overview';

// Calculate KPIs
$totalRooms = 12;
try {
    $rCount = $pdo->query("SELECT COUNT(*) as c FROM rooms WHERE status != 'maintenance'")->fetch();
    $totalRooms = max(1, (int)($rCount['c'] ?? 12));
} catch (Throwable $e) {}

$activeCheckins = 0;
try {
    $rIn = $pdo->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'checked_in'")->fetch();
    $activeCheckins = (int)($rIn['c'] ?? 0);
} catch (Throwable $e) {}

$pendingBookings = 0;
try {
    $rPend = $pdo->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")->fetch();
    $pendingBookings = (int)($rPend['c'] ?? 0);
} catch (Throwable $e) {}

$totalBookingsCount = 0;
try {
    $rTot = $pdo->query("SELECT COUNT(*) as c FROM bookings")->fetch();
    $totalBookingsCount = (int)($rTot['c'] ?? 0);
} catch (Throwable $e) {}

$occupancyRate = round(($activeCheckins / $totalRooms) * 100);

// Fetch recent 6 bookings
$stmtRecent = $pdo->query("SELECT b.*, r.name as room_name FROM bookings b JOIN rooms r ON b.room_id = r.id ORDER BY b.created_at DESC LIMIT 6");
$recentBookings = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">
    
    <!-- Top Welcome Banner -->
    <div class="bg-onyx-charcoal text-white rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-sm">
        <div class="space-y-1 text-center sm:text-left">
            <span class="text-xs font-semibold uppercase tracking-widest text-[#dfe8a6]"><?= __t('admin_welcome_sub', 'Heritage Hospitality Control') ?></span>
            <h2 class="font-headline text-2xl sm:text-3xl font-bold text-white"><?= __t('admin_welcome_title', 'Welcome back') ?>, <?= e($_SESSION['user_name'] ?? 'Manager') ?></h2>
            <p class="text-stone-300 text-xs sm:text-sm">Today is <?= date('l, F j, Y') ?>. Hotel operations are running smoothly.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/booking-create.php" class="bg-[#dfe8a6] hover:bg-white text-[#191e00] px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow">
                <?= __t('admin_create_walkin', '+ Create Walk-In Booking') ?>
            </a>
            <a href="<?= BASE_URL ?>/admin/accommodations.php" class="bg-stone-800 hover:bg-stone-700 text-white px-4 py-2.5 rounded-lg text-xs font-semibold transition">
                <?= __t('admin_nav_accommodations', 'Accommodations') ?>
            </a>
        </div>
    </div>

    <!-- 4 Core Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Occupancy Rate -->
        <div class="flat-card rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between text-stone-500">
                <span class="text-xs font-bold uppercase tracking-wider"><?= __t('admin_kpi_occupancy', 'Occupancy Rate') ?></span>
                <div class="w-8 h-8 rounded-lg bg-[#dfe8a6]/40 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">percent</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-headline font-bold text-3xl text-onyx-charcoal"><?= $occupancyRate ?>%</span>
                <span class="text-xs text-stone-500 font-medium"><?= $activeCheckins ?> / <?= $totalRooms ?> suites active</span>
            </div>
            <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                <div class="bg-[#343c0a] h-full rounded-full" style="width: <?= min(100, $occupancyRate) ?>%"></div>
            </div>
        </div>

        <!-- Total Reservations / Inquiries -->
        <div class="flat-card rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between text-stone-500">
                <span class="text-xs font-bold uppercase tracking-wider"><?= __t('admin_kpi_total_bookings', 'Total Reservations') ?></span>
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">view_list</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-headline font-bold text-3xl text-onyx-charcoal"><?= number_format($totalBookingsCount) ?></span>
                <span class="text-xs text-stone-500 font-medium">total requests</span>
            </div>
            <p class="text-[11px] text-stone-500 flex items-center gap-1">
                <span class="material-symbols-outlined text-xs text-emerald-700">task_alt</span>
                <span>Direct stored inquiries</span>
            </p>
        </div>

        <!-- Pending Bookings -->
        <div class="flat-card rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between text-stone-500">
                <span class="text-xs font-bold uppercase tracking-wider"><?= __t('admin_kpi_pending_bookings', 'Pending Bookings') ?></span>
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">pending_actions</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-headline font-bold text-3xl text-onyx-charcoal"><?= $pendingBookings ?></span>
                <span class="text-xs text-stone-500">require review</span>
            </div>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="text-xs text-[#4B5320] font-semibold hover:underline block">
                Review pending ledger →
            </a>
        </div>

        <!-- In-House Guests -->
        <div class="flat-card rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between text-stone-500">
                <span class="text-xs font-bold uppercase tracking-wider"><?= __t('admin_kpi_active_checkins', 'Active Check-ins') ?></span>
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">hotel</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-headline font-bold text-3xl text-onyx-charcoal"><?= $activeCheckins ?></span>
                <span class="text-xs text-stone-500">checked in</span>
            </div>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=checked_in" class="text-xs text-[#4B5320] font-semibold hover:underline block">
                View guest ledger →
            </a>
        </div>

    </div>

    <!-- Quick Operations & Recent Ledger Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left 8 Columns: Recent Bookings Ledger Table -->
        <div class="lg:col-span-8 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-headline font-bold text-xl text-onyx-charcoal"><?= __t('admin_recent_bookings', 'Recent Booking Requests') ?></h3>
                    <p class="text-xs text-stone-500">Real-time bookings from public website and front desk.</p>
                </div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-xs font-semibold text-[#4B5320] hover:underline">
                    View Complete Ledger →
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                            <tr>
                                <th class="py-3.5 px-4 font-semibold"><?= __t('admin_col_guest', 'Guest Name') ?></th>
                                <th class="py-3.5 px-4 font-semibold"><?= __t('admin_col_room', 'Room') ?></th>
                                <th class="py-3.5 px-4 font-semibold"><?= __t('admin_col_dates', 'Check-in / Check-out') ?></th>
                                <th class="py-3.5 px-4 font-semibold"><?= __t('admin_col_amount', 'Total Price') ?></th>
                                <th class="py-3.5 px-4 font-semibold"><?= __t('admin_col_status', 'Status') ?></th>
                                <th class="py-3.5 px-4 font-semibold text-right"><?= __t('admin_col_actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <?php if (empty($recentBookings)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-stone-400">No reservations found.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentBookings as $b): ?>
                                <tr class="hover:bg-stone-50 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="font-mono font-bold text-stone-900"><?= e($b['booking_reference']) ?></div>
                                        <div class="text-stone-500 font-medium"><?= e($b['guest_name']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium text-stone-800">
                                        <?= e($b['room_name']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-stone-600">
                                        <div><?= format_date($b['check_in_date'], 'M d') ?> - <?= format_date($b['check_out_date'], 'M d') ?></div>
                                        <div class="text-[10px] text-stone-400"><?= $b['nights'] ?> night(s)</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-headline font-bold text-stone-900">
                                        <?= format_price($b['total_price']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php
                                        $pill = match($b['status']) {
                                            'confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'checked_in' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'checked_out' => 'bg-stone-100 text-stone-700 border-stone-200',
                                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default => 'bg-amber-50 text-amber-700 border-amber-200'
                                        };
                                        $statusKey = 'admin_status_' . $b['status'];
                                        $statusLabel = __t($statusKey, str_replace('_', ' ', $b['status']));
                                        ?>
                                        <span class="inline-block px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border <?= $pill ?>">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <a href="<?= BASE_URL ?>/admin/booking-detail.php?id=<?= $b['id'] ?>" class="text-[#343c0a] hover:underline font-semibold text-xs">
                                            <?= __t('admin_btn_view', 'View') ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right 4 Columns: Fast Actions & CMS Shortcuts -->
        <div class="lg:col-span-4 space-y-6">
            
            <div class="bg-white rounded-2xl p-6 border border-stone-200 shadow-sm space-y-4">
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal"><?= __t('admin_quick_actions', 'Quick Operational Actions') ?></h3>
                
                <div class="space-y-2.5">
                    <a href="<?= BASE_URL ?>/admin/accommodations.php" class="p-3 rounded-xl bg-stone-50 hover:bg-stone-100 border border-stone-200 flex items-center justify-between text-xs font-medium text-stone-800 transition">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320]">bed</span>
                            <span><?= __t('admin_nav_accommodations', 'Accommodations') ?></span>
                        </div>
                        <span class="material-symbols-outlined text-stone-400 text-base">chevron_right</span>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/dining-wellness.php" class="p-3 rounded-xl bg-stone-50 hover:bg-stone-100 border border-stone-200 flex items-center justify-between text-xs font-medium text-stone-800 transition">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320]">restaurant</span>
                            <span><?= __t('admin_nav_dining', 'Dining & Wellness') ?></span>
                        </div>
                        <span class="material-symbols-outlined text-stone-400 text-base">chevron_right</span>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/offers.php" class="p-3 rounded-xl bg-stone-50 hover:bg-stone-100 border border-stone-200 flex items-center justify-between text-xs font-medium text-stone-800 transition">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320]">local_offer</span>
                            <span><?= __t('admin_nav_offers', 'Special Offers') ?></span>
                        </div>
                        <span class="material-symbols-outlined text-stone-400 text-base">chevron_right</span>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/messages.php" class="p-3 rounded-xl bg-stone-50 hover:bg-stone-100 border border-stone-200 flex items-center justify-between text-xs font-medium text-stone-800 transition">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320]">mail</span>
                            <span><?= __t('admin_action_inbox', 'Guest Messages Inbox') ?></span>
                        </div>
                        <span class="material-symbols-outlined text-stone-400 text-base">chevron_right</span>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/settings.php" class="p-3 rounded-xl bg-stone-50 hover:bg-stone-100 border border-stone-200 flex items-center justify-between text-xs font-medium text-stone-800 transition">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#4B5320]">tune</span>
                            <span><?= __t('admin_nav_settings', 'Site & SEO Settings') ?></span>
                        </div>
                        <span class="material-symbols-outlined text-stone-400 text-base">chevron_right</span>
                    </a>
                </div>
            </div>

            <!-- Hotel Quick Contact Status -->
            <div class="bg-[#343c0a] text-white rounded-2xl p-6 space-y-3">
                <span class="text-[10px] font-bold uppercase tracking-widest text-[#dfe8a6]">Concierge Hotline</span>
                <div class="font-headline font-bold text-lg"><?= e(hotel_name()) ?></div>
                <div class="text-xs text-stone-300"><?= e(hotel_phone()) ?><?= hotel_email() ? ' | ' . e(hotel_email()) : '' ?></div>
                <a href="<?= BASE_URL ?>/index.php" target="_blank" class="inline-block text-xs text-[#dfe8a6] hover:underline font-semibold pt-1">
                    <?= __t('admin_nav_view_public', 'View Public Website') ?> →
                </a>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
