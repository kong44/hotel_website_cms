<?php
/**
 * Indra Hotel - Guest Google OAuth 2.0 Callback Handler
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/google-auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle errors returned by Google
if (isset($_GET['error'])) {
    $errorMsg = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    set_flash('error', 'Google Sign-in was cancelled or encountered an error: ' . $errorMsg);
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

$code = $_GET['code'] ?? '';
$stateRaw = $_GET['state'] ?? '';

if (empty($code) || empty($stateRaw)) {
    set_flash('error', 'Missing authorization code from Google.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

// Decode state payload
$stateData = json_decode(base64_decode($stateRaw), true);
$receivedCsrf = $stateData['csrf'] ?? '';
$redirectTarget = $stateData['redirect'] ?? (BASE_URL . '/my-booking.php');
$expectedCsrf = $_SESSION['guest_google_oauth_state'] ?? '';

unset($_SESSION['guest_google_oauth_state']);

if (empty($receivedCsrf) || empty($expectedCsrf) || !hash_equals($expectedCsrf, $receivedCsrf)) {
    set_flash('error', 'Security state validation failed. Please try signing in with Google again.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

// 1. Exchange authorization code for token
$tokenData = GoogleAuth::exchangeCodeForToken($code, GoogleAuth::getGuestRedirectUri());
if (!$tokenData || empty($tokenData['access_token'])) {
    set_flash('error', 'Failed to retrieve access token from Google. Please try again.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

// 2. Fetch Google User Profile
$profile = GoogleAuth::getUserProfile($tokenData['access_token']);
if (!$profile || empty($profile['email'])) {
    set_flash('error', 'Failed to retrieve profile information from Google.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}

// 3. Login Guest in Session
$loginResult = GoogleAuth::loginGuest($profile);

if ($loginResult['success']) {
    $guest = $loginResult['guest'];
    set_flash('success', "Welcome back, {$guest['name']}! Signed in with Google. Here is your complete booking history.");
    header('Location: ' . $redirectTarget);
    exit;
} else {
    set_flash('error', $loginResult['message'] ?? 'Authentication failed.');
    header('Location: ' . BASE_URL . '/my-booking.php');
    exit;
}
