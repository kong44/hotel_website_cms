<?php
/**
 * Indra Hotel - Booking Ledger CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Booking Ledger';

// Handle Inline Status Change
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_booking_status'])) {
    $bId = (int)$_POST['booking_id'];
    $newStatus = $_POST['status'] ?? 'confirmed';
    $newPayment = $_POST['payment_status'] ?? 'paid';
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $newPayment, $bId]);
    set_flash('success', "Booking status updated successfully.");
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

// Handle Delete Booking
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$delId]);
    set_flash('success', "Booking deleted from ledger.");
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

// Filters & Search
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

$sql = "SELECT b.*, r.name as room_name FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (UPPER(b.booking_reference) LIKE ? OR LOWER(b.guest_name) LIKE ? OR LOWER(b.guest_email) LIKE ? OR b.guest_phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($statusFilter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Reservation Ledger</h2>
            <p class="text-xs text-stone-500">Monitor arrivals, departures, live guest folios, and payment status.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/booking-create.php" class="bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">add_circle</span>
            <span>Add New Booking</span>
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-2xl p-4 sm:p-6 border border-stone-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        
        <!-- Status Filter Chips -->
        <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto text-xs font-semibold">
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="px-3.5 py-2 rounded-lg transition <?= $statusFilter === 'all' ? 'bg-[#343c0a] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">All</a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=confirmed" class="px-3.5 py-2 rounded-lg transition <?= $statusFilter === 'confirmed' ? 'bg-[#343c0a] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">Confirmed</a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=checked_in" class="px-3.5 py-2 rounded-lg transition <?= $statusFilter === 'checked_in' ? 'bg-[#343c0a] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">Checked In</a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="px-3.5 py-2 rounded-lg transition <?= $statusFilter === 'pending' ? 'bg-[#343c0a] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">Pending</a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=checked_out" class="px-3.5 py-2 rounded-lg transition <?= $statusFilter === 'checked_out' ? 'bg-[#343c0a] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' ?>">Checked Out</a>
        </div>

        <!-- Search Input -->
        <form action="<?= BASE_URL ?>/admin/bookings.php" method="GET" class="w-full sm:w-72 flex items-center">
            <div class="relative w-full">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search reference, guest..."
                       class="w-full text-xs bg-stone-50 border border-stone-300 rounded-lg pl-8 pr-3 py-2 focus:ring-2 focus:ring-[#343c0a]">
                <span class="material-symbols-outlined text-stone-400 text-base absolute left-2.5 top-2">search</span>
            </div>
        </form>

    </div>

    <!-- Booking Ledger Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                    <tr>
                        <th class="py-4 px-4 font-semibold">Reference</th>
                        <th class="py-4 px-4 font-semibold">Guest Details</th>
                        <th class="py-4 px-4 font-semibold">Suite</th>
                        <th class="py-4 px-4 font-semibold">Dates & Nights</th>
                        <th class="py-4 px-4 font-semibold">Amount</th>
                        <th class="py-4 px-4 font-semibold">Status / Payment</th>
                        <th class="py-4 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-stone-400">
                            No reservations found matching your criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                        <tr class="hover:bg-stone-50 transition">
                            
                            <td class="py-4 px-4 font-mono font-bold text-onyx-charcoal">
                                <a href="<?= BASE_URL ?>/admin/booking-detail.php?id=<?= $b['id'] ?>" class="hover:underline text-[#343c0a]">
                                    <?= e($b['booking_reference']) ?>
                                </a>
                                <div class="text-[10px] text-stone-400 font-sans font-normal"><?= format_date($b['created_at'], 'M d, H:i') ?></div>
                            </td>

                            <td class="py-4 px-4">
                                <div class="font-bold text-stone-900"><?= e($b['guest_name']) ?></div>
                                <div class="text-[11px] text-stone-500"><?= e($b['guest_email']) ?></div>
                                <div class="text-[10px] text-stone-400"><?= e($b['guest_phone']) ?></div>
                            </td>

                            <td class="py-4 px-4 font-medium text-stone-800">
                                <?= e($b['room_name']) ?>
                            </td>

                            <td class="py-4 px-4 text-stone-600">
                                <div class="font-semibold"><?= format_date($b['check_in_date'], 'M d, Y') ?> - <?= format_date($b['check_out_date'], 'M d, Y') ?></div>
                                <div class="text-[10px] text-stone-400"><?= $b['nights'] ?> Night(s) • <?= $b['adults'] ?>A, <?= $b['children'] ?>C</div>
                            </td>

                            <td class="py-4 px-4 font-headline font-bold text-sm text-onyx-charcoal">
                                <?= format_price($b['total_price']) ?>
                            </td>

                            <td class="py-4 px-4">
                                <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" class="flex items-center gap-1.5">
                                    <input type="hidden" name="update_booking_status" value="1">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    
                                    <select name="status" onchange="this.form.submit()" class="text-[10px] font-bold uppercase rounded border border-stone-300 p-1 bg-white cursor-pointer">
                                        <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $b['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="checked_in" <?= $b['status'] === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                                        <option value="checked_out" <?= $b['status'] === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                                        <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>

                                    <select name="payment_status" onchange="this.form.submit()" class="text-[10px] font-semibold uppercase rounded border border-stone-300 p-1 bg-white cursor-pointer">
                                        <option value="paid" <?= $b['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="unpaid" <?= $b['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                        <option value="refunded" <?= $b['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                    </select>
                                </form>
                            </td>

                            <td class="py-4 px-4 text-right space-x-1.5 whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/admin/booking-detail.php?id=<?= $b['id'] ?>" class="p-1.5 text-stone-600 hover:text-stone-900 rounded hover:bg-stone-100 inline-block" title="View Folio & Voucher">
                                    <span class="material-symbols-outlined text-base">receipt</span>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/bookings.php?action=delete&id=<?= $b['id'] ?>" onclick="return confirm('Delete this booking record?')" class="p-1.5 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50 inline-block" title="Delete">
                                    <span class="material-symbols-outlined text-base">delete</span>
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

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
