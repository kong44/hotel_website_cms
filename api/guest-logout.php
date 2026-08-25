<?php
/**
 * Indra Hotel - Guest Logout
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['guest_user']);
set_flash('info', 'You have been signed out of your guest account.');

$redirect = trim($_GET['redirect'] ?? '');
if (!empty($redirect)) {
    // Validate redirect path
    if (strpos($redirect, 'http://') === 0 || strpos($redirect, 'https://') === 0) {
        header('Location: ' . $redirect);
    } else {
        $path = '/' . ltrim($redirect, '/');
        header('Location: ' . BASE_URL . $path);
    }
} else {
    header('Location: ' . BASE_URL . '/my-booking.php');
}
exit;
