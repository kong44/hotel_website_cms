<?php
/**
 * Indra Hotel - Guest Google OAuth 2.0 Login Redirect Initiator
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/google-auth.php';

if (!GoogleAuth::isEnabled()) {
    set_flash('error', 'Google Sign-In is currently disabled. You can still search your reservation by reference number.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

$redirectTarget = $_GET['redirect'] ?? (BASE_URL . '/my-booking.php');
$authUrl = GoogleAuth::getGuestAuthUrl($redirectTarget);

header('Location: ' . $authUrl);
exit;
