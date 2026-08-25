<?php
/**
 * Indra Hotel - Reservation Detail & Guest Invoice Voucher
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();

$bookingId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.*, r.name as room_name, r.bed_type, r.size_sqm, r.view_type FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
$stmt->execute([$bookingId]);
$b = $stmt->fetch();

if (!$b) {
    set_flash('error', 'Booking record not found.');
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$adminTitle = 'Reservation ' . $b['booking_reference'];

// Handle status update from detail view
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_detail_status'])) {
    $status = $_POST['status'] ?? $b['status'];
    $payment = $_POST['payment_status'] ?? $b['payment_status'];
    $notes = $_POST['special_requests'] ?? $b['special_requests'];

    $pdo->prepare("UPDATE bookings SET status = ?, payment_status = ?, special_requests = ? WHERE id = ?")
        ->execute([$status, $payment, $notes, $bookingId]);
    set_flash('success', 'Reservation folio updated.');
    header('Location: ' . BASE_URL . '/admin/booking-detail.php?id=' . $bookingId);
    exit;
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="max-w-4xl space-y-6">
    
    <div class="flex items-center justify-between no-print">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Folio #<?= e($b['booking_reference']) ?></h2>
            <p class="text-xs text-stone-500">Official Guest Reservation Voucher & Billing Statement</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-stone-900 hover:bg-black text-white px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">print</span>
                <span>Print Invoice</span>
            </button>
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-xs font-semibold text-stone-600 hover:text-stone-900">
                ← Back to Ledger
            </a>
        </div>
    </div>

    <!-- Printable Invoice Voucher Card -->
    <div class="bg-white rounded-2xl p-8 sm:p-12 border border-stone-200 shadow-sm space-y-8" id="printable-invoice">
        
        <!-- Voucher Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-6 pb-8 border-b border-stone-200">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded bg-[#343c0a] text-white flex items-center justify-center font-bold text-sm">IH</div>
                    <span class="font-headline font-bold text-xl text-onyx-charcoal">INDRA HOTEL</span>
                </div>
                <p class="text-xs text-stone-500 max-w-sm leading-relaxed">
                    <?= e(hotel_address()) ?><br>
                    Tel: <?= e(hotel_phone()) ?><?= hotel_email() ? ' | ' . e(hotel_email()) : '' ?>
                </p>
            </div>

            <div class="text-left sm:text-right space-y-1">
                <div class="text-xs uppercase font-bold text-stone-400">Invoice Voucher</div>
                <div class="font-mono font-bold text-xl text-[#343c0a]"><?= e($b['booking_reference']) ?></div>
                <div class="text-xs text-stone-500">Issued: <?= format_date($b['created_at'], 'M d, Y H:i') ?></div>
                <div class="pt-1">
                    <?php
                    $badge = match($b['status']) {
                        'confirmed' => 'bg-emerald-100 text-emerald-800',
                        'checked_in' => 'bg-blue-100 text-blue-800',
                        'checked_out' => 'bg-stone-100 text-stone-800',
                        'cancelled' => 'bg-rose-100 text-rose-800',
                        default => 'bg-amber-100 text-amber-800'
                    };
                    ?>
                    <span class="inline-block px-2.5 py-1 rounded text-xs font-bold uppercase tracking-wider <?= $badge ?>">
                        <?= str_replace('_', ' ', $b['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Guest & Room Information Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-xs text-stone-700">
            
            <div class="space-y-2 p-4 rounded-xl bg-stone-50 border border-stone-100">
                <span class="font-bold uppercase tracking-wider text-stone-400 block">Guest Information</span>
                <div class="font-headline font-bold text-base text-stone-900"><?= e($b['guest_name']) ?></div>
                <div>Email: <strong class="text-stone-800"><?= e($b['guest_email']) ?></strong></div>
                <div>Phone: <strong class="text-stone-800"><?= e($b['guest_phone']) ?></strong></div>
            </div>

            <div class="space-y-2 p-4 rounded-xl bg-stone-50 border border-stone-100">
                <span class="font-bold uppercase tracking-wider text-stone-400 block">Stay Schedule</span>
                <div>Check-in: <strong class="text-stone-900"><?= format_date($b['check_in_date']) ?></strong> (<?= HOTEL_CHECKIN_TIME ?>)</div>
                <div>Check-out: <strong class="text-stone-900"><?= format_date($b['check_out_date']) ?></strong> (<?= HOTEL_CHECKOUT_TIME ?>)</div>
                <div>Duration: <strong><?= $b['nights'] ?> Night(s)</strong> • Guests: <strong><?= $b['adults'] ?>A, <?= $b['children'] ?>C</strong></div>
            </div>

        </div>

        <!-- Billing Folio Breakdown -->
        <div class="space-y-4">
            <h3 class="font-headline font-bold text-base text-onyx-charcoal">Folio Breakdown</h3>
            <table class="w-full text-left text-xs border border-stone-200 rounded-lg overflow-hidden">
                <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                    <tr>
                        <th class="py-3 px-4 font-semibold">Description</th>
                        <th class="py-3 px-4 font-semibold text-center">Nights</th>
                        <th class="py-3 px-4 font-semibold text-right">Nightly Rate</th>
                        <th class="py-3 px-4 font-semibold text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr>
                        <td class="py-3.5 px-4 font-medium text-stone-900">
                            <?= e($b['room_name']) ?>
                            <div class="text-[11px] text-stone-500"><?= e($b['bed_type']) ?> • <?= $b['size_sqm'] ?> m² • <?= e($b['view_type']) ?></div>
                        </td>
                        <td class="py-3.5 px-4 text-center font-semibold"><?= $b['nights'] ?></td>
                        <td class="py-3.5 px-4 text-right font-medium"><?= format_price($b['room_rate']) ?></td>
                        <td class="py-3.5 px-4 text-right font-headline font-bold text-sm text-stone-900">
                            <?= format_price($b['total_price']) ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-stone-50 border-t border-stone-200">
                    <tr>
                        <td colspan="3" class="py-3 px-4 font-bold text-right text-stone-800">Total Billed:</td>
                        <td class="py-3 px-4 font-headline font-bold text-base text-right text-[#343c0a]"><?= format_price($b['total_price']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($b['special_requests'])): ?>
        <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 text-xs text-stone-600 space-y-1">
            <span class="font-bold text-stone-800 uppercase block">Special Guest Requests</span>
            <p><?= nl2br(e($b['special_requests'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Quick Status Update Form (Hidden in Print) -->
        <div class="pt-6 border-t border-stone-200 no-print">
            <h4 class="font-headline font-bold text-sm text-onyx-charcoal mb-3">Update Folio Status</h4>
            <form action="<?= BASE_URL ?>/admin/booking-detail.php?id=<?= $b['id'] ?>" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <input type="hidden" name="update_detail_status" value="1">
                
                <div>
                    <label class="block font-semibold text-stone-600 mb-1">Reservation Status</label>
                    <select name="status" class="w-full border border-stone-300 rounded p-2 text-xs">
                        <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $b['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="checked_in" <?= $b['status'] === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                        <option value="checked_out" <?= $b['status'] === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                        <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-stone-600 mb-1">Payment Status</label>
                    <select name="payment_status" class="w-full border border-stone-300 rounded p-2 text-xs">
                        <option value="paid" <?= $b['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= $b['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="refunded" <?= $b['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white py-2 px-4 rounded font-bold text-xs transition">
                        Update Status
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

<style>
@media print {
    .no-print, header, aside, footer {
        display: none !important;
    }
    body, .flex-1 {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    #printable-invoice {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
