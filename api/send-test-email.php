<?php
/**
 * Indra Hotel - AJAX Test Email Dispatcher
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

// Require Admin Privileges
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

$recipient = trim($_POST['email'] ?? '');

if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid recipient email address.']);
    exit;
}

$result = Mailer::sendTestEmail($recipient);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?: "Test email successfully sent to {$recipient}.",
        'log' => $result['log'] ?? ''
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?: 'Failed to transmit test email.',
        'log' => $result['log'] ?? ''
    ]);
}
