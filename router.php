<?php
/**
 * Indra Hotel - Built-in PHP CLI Web Server Router
 * Usage: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// 1. If physical file exists (static asset, image, CSS, JS, etc.), serve directly
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    // Return false to let PHP CLI server serve the static file directly
    return false;
}

// 2. Dispatch all application requests through the Router
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/router.php';

Router::dispatch();
