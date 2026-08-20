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
set_flash('info', 'You have been signed out of your guest reservation portal.');
header('Location: ' . BASE_URL . '/my-booking.php');
exit;
