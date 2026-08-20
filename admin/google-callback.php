<?php
/**
 * Indra Hotel - Google OAuth 2.0 Authorization Callback
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle errors returned by Google
if (isset($_GET['error'])) {
    $errorMsg = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    set_flash('error', 'Google Sign-in was cancelled or encountered an error: ' . $errorMsg);
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$code = $_GET['code'] ?? '';
$stateRaw = $_GET['state'] ?? '';

if (empty($code) || empty($stateRaw)) {
    set_flash('error', 'Missing authorization code or state token from Google.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// Decode state payload
$stateData = json_decode(base64_decode($stateRaw), true);
$receivedCsrf = $stateData['csrf'] ?? '';
$redirectTarget = $stateData['redirect'] ?? (BASE_URL . '/admin/index.php');
$expectedCsrf = $_SESSION['google_oauth_state'] ?? '';

unset($_SESSION['google_oauth_state']);

if (empty($receivedCsrf) || empty($expectedCsrf) || !hash_equals($expectedCsrf, $receivedCsrf)) {
    set_flash('error', 'Security state validation failed. Please try signing in with Google again.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// 1. Exchange authorization code for token
$tokenData = GoogleAuth::exchangeCodeForToken($code);
if (!$tokenData || empty($tokenData['access_token'])) {
    set_flash('error', 'Failed to retrieve access token from Google. Please verify your Client Secret in CMS Settings.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// 2. Fetch Google User Profile
$profile = GoogleAuth::getUserProfile($tokenData['access_token']);
if (!$profile || empty($profile['email'])) {
    set_flash('error', 'Failed to retrieve user profile information from Google.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// 3. Authenticate or Provision User in SoftBook CMS
$authResult = GoogleAuth::authenticate($profile);

if ($authResult['success']) {
    $user = $authResult['user'];
    $isNew = !empty($authResult['is_new']);
    
    if ($isNew) {
        set_flash('success', "Welcome to SoftBook, {$user['name']}! Your portal account has been registered with {$user['role']} privileges.");
    } else {
        set_flash('success', "Welcome back, {$user['name']}! Signed in via Google.");
    }
    
    header('Location: ' . $redirectTarget);
    exit;
} else {
    set_flash('error', $authResult['message'] ?? 'Authentication failed.');
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}
