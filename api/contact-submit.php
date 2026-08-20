<?php
/**
 * Indra Hotel - API: AJAX Contact Form Submission
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
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? 'General Inquiry');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($message)) {
    json_response(['success' => false, 'message' => 'Please fill in all required fields.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'unread')");
    $stmt->execute([$name, $email, $phone, $subject, $message]);

    // Send Contact Notification & Courtesy Auto-Reply (non-blocking)
    try {
        require_once __DIR__ . '/../includes/mailer.php';
        $contactData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message
        ];

        // 1. Send notification to hotel staff
        Mailer::sendContactNotificationToHotel($contactData);

        // 2. Send courtesy auto-reply to guest
        Mailer::sendContactAutoReply($contactData);
    } catch (Throwable $mailEx) {
        error_log('Mailer error on contact submission: ' . $mailEx->getMessage());
    }

    json_response([
        'success' => true,
        'message' => 'Thank you! Your message has been received by our concierge team and an email acknowledgment was sent.'
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Failed to save message: ' . $e->getMessage()], 500);
}

