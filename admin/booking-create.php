<?php
/**
 * Indra Hotel - Front Desk Manual Reservation Creator
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Add New Booking';

// Fetch available rooms
$stmtRooms = $pdo->query("SELECT * FROM rooms WHERE status != 'maintenance' ORDER BY display_order ASC");
$rooms = $stmtRooms->fetchAll();

// Handle Manual Booking Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');
    $roomId = (int)($_POST['room_id'] ?? 0);
    $checkIn = $_POST['check_in_date'] ?? date('Y-m-d');
    $checkOut = $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day'));
    $adults = (int)($_POST['adults'] ?? 2);
    $children = (int)($_POST['children'] ?? 0);
    $status = $_POST['status'] ?? 'confirmed';
    $paymentStatus = $_POST['payment_status'] ?? 'paid';
    $specialRequests = trim($_POST['special_requests'] ?? '');

    if (empty($guestName) || empty($guestEmail) || $roomId <= 0) {
        set_flash('error', 'Please complete all required fields.');
    } else {
        // Fetch room rate
        $stmtR = $pdo->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
        $stmtR->execute([$roomId]);
        $r = $stmtR->fetch();
        $roomRate = (float)($r['price_per_night'] ?? 85.00);
        $nights = calculate_nights($checkIn, $checkOut);
        $totalPrice = $roomRate * $nights;
        $bookingRef = generate_booking_ref();

        try {
            $stmtInsert = $pdo->prepare("INSERT INTO bookings (booking_reference, room_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, adults, children, nights, room_rate, total_price, status, payment_status, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $bookingRef,
                $roomId,
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
                $status,
                $paymentStatus,
                $specialRequests
            ]);
            $newId = $pdo->lastInsertId();
            set_flash('success', "New reservation {$bookingRef} created successfully.");
            header('Location: ' . BASE_URL . '/admin/booking-detail.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            set_flash('error', 'Error creating booking: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="max-w-4xl space-y-6">
    
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Manual Reservation Entry</h2>
            <p class="text-xs text-stone-500">Front desk walk-in and telephone booking registration.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-xs font-semibold text-stone-600 hover:text-stone-900">
            ← Back to Ledger
        </a>
    </div>

    <form action="<?= BASE_URL ?>/admin/booking-create.php" method="POST" class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-6">
        
        <div class="space-y-4">
            <h3 class="font-headline font-bold text-base text-onyx-charcoal pb-2 border-b border-stone-100">Guest Information</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Guest Full Name *</label>
                    <input type="text" name="guest_name" required placeholder="e.g. Dr. Sophal Meas"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Email Address *</label>
                    <input type="email" name="guest_email" required placeholder="e.g. sophal.meas@example.com"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Phone / Mobile</label>
                    <input type="text" name="guest_phone" placeholder="e.g. +855 12 345 678"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>
        </div>

        <div class="space-y-4 pt-4 border-t border-stone-100">
            <h3 class="font-headline font-bold text-base text-onyx-charcoal pb-2 border-b border-stone-100">Stay & Accommodation Selection</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Suite Assigned *</label>
                    <select name="room_id" id="booking_room_select" required class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?= $r['id'] ?>" data-price="<?= $r['price_per_night'] ?>">
                                <?= e($r['name']) ?> (<?= format_price($r['price_per_night']) ?>/nt)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Check-in Date *</label>
                    <input type="date" name="check_in_date" id="booking_check_in" value="<?= date('Y-m-d') ?>" required
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Check-out Date *</label>
                    <input type="date" name="check_out_date" id="booking_check_out" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Adults</label>
                    <select name="adults" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                        <option value="1">1 Adult</option>
                        <option value="2" selected>2 Adults</option>
                        <option value="3">3 Adults</option>
                        <option value="4">4 Adults</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Children</label>
                    <select name="children" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                        <option value="0" selected>0 Child</option>
                        <option value="1">1 Child</option>
                        <option value="2">2 Children</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Folio Status</label>
                    <select name="status" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                        <option value="confirmed" selected>Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Payment</label>
                    <select name="payment_status" class="w-full text-sm border border-stone-300 rounded-lg p-2.5">
                        <option value="paid" selected>Paid / Guaranteed</option>
                        <option value="unpaid">Unpaid / Pay at Desk</option>
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Guest Special Requests & Notes</label>
            <textarea name="special_requests" rows="3" placeholder="Dietary restrictions, flight arrivals, late check-in notes..."
                      class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"></textarea>
        </div>

        <!-- Price live computation preview -->
        <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 flex justify-between items-center text-xs">
            <div>
                <span class="text-stone-500">Estimated Duration:</span>
                <strong id="calculated_nights" class="text-stone-900 ml-1">1 night</strong>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-stone-500 font-medium">Estimated Folio:</span>
                <span id="calculated_total_price" class="font-headline font-bold text-xl text-[#343c0a]">$85.00</span>
            </div>
        </div>

        <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="px-5 py-2.5 border border-stone-300 rounded-lg text-xs font-semibold text-stone-700 hover:bg-stone-100 transition">Cancel</a>
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow">
                Save & Generate Reservation
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
