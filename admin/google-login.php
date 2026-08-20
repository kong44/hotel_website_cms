<?php
/**
 * Indra Hotel - Google OAuth 2.0 Redirect Initiator
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

if (!GoogleAuth::isEnabled()) {
    set_flash('error', 'Google Authentication is currently not configured or disabled by the administrator.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$redirectTarget = $_GET['redirect'] ?? (BASE_URL . '/admin/index.php');
$authUrl = GoogleAuth::getAuthUrl($redirectTarget);

header('Location: ' . $authUrl);
exit;
