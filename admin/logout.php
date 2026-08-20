<?php
/**
 * Indra Hotel - Admin Logout Handler
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::logout();
set_flash('info', 'You have been safely signed out.');
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
