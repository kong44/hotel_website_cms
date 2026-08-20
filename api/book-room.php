<?php
/**
 * Indra Hotel - API: AJAX Booking Processor
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDB();

$guestName = trim($_POST['guest_name'] ?? '');
$guestEmail = trim($_POST['guest_email'] ?? '');
$guestPhone = trim($_POST['guest_phone'] ?? '');
$roomId = (int)($_POST['room_id'] ?? 0);
$checkIn = $_POST['check_in'] ?? date('Y-m-d');
$checkOut = $_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
$adults = (int)($_POST['adults'] ?? 2);
$children = (int)($_POST['children'] ?? 0);
$promo = trim($_POST['promo'] ?? '');
$specialRequests = trim($_POST['special_requests'] ?? '');

if (empty($guestName) || empty($guestEmail) || empty($guestPhone) || $roomId <= 0) {
    json_response(['success' => false, 'message' => 'Please fill in all required fields.'], 400);
}

if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Invalid email address.'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    json_response(['success' => false, 'message' => 'Room not found.'], 404);
}

$nights = calculate_nights($checkIn, $checkOut);
$roomRate = (float)$room['price_per_night'];
$subtotal = $roomRate * $nights;
$discountPercent = 0;

// Check database special_offers
$offerTitle = '';
if (!empty($promo)) {
    try {
        $stmtOffer = $pdo->prepare("SELECT title, discount_percent FROM special_offers WHERE UPPER(promo_code) = ? AND is_active = 1 LIMIT 1");
        $stmtOffer->execute([strtoupper($promo)]);
        $offerFound = $stmtOffer->fetch();
        if ($offerFound) {
            $offerTitle = $offerFound['title'];
            if ((int)$offerFound['discount_percent'] > 0) {
                $discountPercent = (int)$offerFound['discount_percent'];
            }
        }
    } catch (Throwable $e) {}
}

if ($discountPercent === 0 && !empty($promo)) {
    if (strtoupper($promo) === 'INDRA15') {
        $discountPercent = 15;
        $offerTitle = 'Direct Booking 15% Privilege';
    } elseif (strtoupper($promo) === 'LOVEINDRA') {
        $discountPercent = 20;
        $offerTitle = 'Romantic Retreat 20% Privilege';
    } elseif (strtoupper($promo) === 'STAYLONG' && $nights >= 5) {
        $discountPercent = 25;
        $offerTitle = 'Extended Stay 25% Privilege';
    }
}

$discountAmount = $subtotal * ($discountPercent / 100);
$total = max(0, $subtotal - $discountAmount);
$bookingRef = generate_booking_ref();

try {
    $stmtInsert = $pdo->prepare("INSERT INTO bookings (booking_reference, room_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, adults, children, nights, room_rate, total_price, status, payment_status, promo_code, offer_title, discount_amount, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, ?, ?)");
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
        $total,
        $promo ?: null,
        $offerTitle ?: null,
        $discountAmount,
        $specialRequests
    ]);

    // Auto-record / update Guest in guest_users CRM table
    try {
        $stmtGuest = $pdo->prepare("SELECT id, name, phone FROM guest_users WHERE LOWER(email) = ? LIMIT 1");
        $stmtGuest->execute([strtolower($guestEmail)]);
        $existingGuest = $stmtGuest->fetch();
        if ($existingGuest) {
            $stmtUp = $pdo->prepare("UPDATE guest_users SET name = ?, phone = COALESCE(NULLIF(?, ''), phone), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUp->execute([$guestName, $guestPhone, (int)$existingGuest['id']]);
        } else {
            $stmtIn = $pdo->prepare("INSERT INTO guest_users (name, email, phone, auth_provider, status, created_at) VALUES (?, ?, ?, 'direct_booking', 'active', CURRENT_TIMESTAMP)");
            $stmtIn->execute([$guestName, strtolower($guestEmail), $guestPhone]);
        }
    } catch (Throwable $e) {}

    // Send Branded Confirmation & Hotel Notification Emails (non-blocking)
    try {
        require_once __DIR__ . '/../includes/mailer.php';
        $bookingData = [
            'booking_reference' => $bookingRef,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => $adults,
            'children' => $children,
            'nights' => $nights,
            'room_rate' => $roomRate,
            'special_requests' => $specialRequests,
            'promo_code' => $promo,
            'offer_title' => $offerTitle,
            'discount_amount' => $discountAmount,
            'total_price' => $total
        ];
        
        // 1. Send confirmation to Guest
        Mailer::sendBookingConfirmation($bookingData, $room);

        // 2. Send immediate notification to Hotel Staff / Reservations
        Mailer::sendBookingNotificationToHotel($bookingData, $room);
    } catch (Throwable $mailEx) {
        // Mail error logged silently without failing booking
        error_log('Mailer error on booking: ' . $mailEx->getMessage());
    }

    json_response([
        'success' => true,
        'booking_reference' => $bookingRef,
        'guest_name' => $guestName,
        'room_name' => $room['name'],
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'nights' => $nights,
        'total_price' => $total,
        'formatted_total' => format_price($total),
        'redirect_url' => BASE_URL . '/my-booking.php?ref=' . urlencode($bookingRef)
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
