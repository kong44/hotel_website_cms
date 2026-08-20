<?php
/**
 * Indra Hotel - API: Check Availability & Calculate Rates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$roomId = (int)($_GET['room_id'] ?? ($_POST['room_id'] ?? 0));
$checkIn = $_GET['check_in'] ?? ($_POST['check_in'] ?? date('Y-m-d'));
$checkOut = $_GET['check_out'] ?? ($_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
$promoCode = trim($_GET['promo'] ?? ($_POST['promo'] ?? ''));

if ($roomId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid room specified.'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND status != 'maintenance'");
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    json_response(['success' => false, 'message' => 'Room is currently unavailable.'], 404);
}

$nights = calculate_nights($checkIn, $checkOut);
$roomRate = (float)$room['price_per_night'];
$subtotal = $roomRate * $nights;
$discountPercent = 0;

if (strtoupper($promoCode) === 'INDRA15') {
    $discountPercent = 15;
} elseif (strtoupper($promoCode) === 'LOVEINDRA') {
    $discountPercent = 20;
} elseif (strtoupper($promoCode) === 'STAYLONG' && $nights >= 5) {
    $discountPercent = 25;
}

$discountAmount = $subtotal * ($discountPercent / 100);
$total = max(0, $subtotal - $discountAmount);

json_response([
    'success' => true,
    'room_id' => $room['id'],
    'room_name' => $room['name'],
    'rate_per_night' => $roomRate,
    'nights' => $nights,
    'subtotal' => $subtotal,
    'discount_percent' => $discountPercent,
    'discount_amount' => $discountAmount,
    'total_price' => $total,
    'formatted_total' => format_price($total),
    'available' => true
]);
