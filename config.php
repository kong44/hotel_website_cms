<?php
/**
 * Indra Hotel - Configuration File
 * Database, SEO & Application Settings
 */

// Prevent multiple inclusions
if (defined('INDRA_CONFIG_LOADED')) {
    return;
}
define('INDRA_CONFIG_LOADED', true);

define('ROOT_PATH', __DIR__);

// Database Configuration (Defined before session initialization)
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql'); // 'mysql' or 'sqlite'
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'hotel_website');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');
define('SQLITE_FILE', ROOT_PATH . '/database.sqlite');

// Application Settings
define('APP_NAME', 'The Hotel');
define('APP_TAGLINE', 'Contemporary Sanctuary in Phnom Penh');
define('APP_VERSION', '1.0.0');

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 604800); // 7 days
    ini_set('session.cookie_lifetime', 604800);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443) 
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 604800, // 7 days
        'path' => '/',
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax'
    ]);

    if (DB_DRIVER === 'mysql') {
        require_once __DIR__ . '/includes/db.php';
        require_once __DIR__ . '/includes/session_handler.php';
        if (class_exists('PdoSessionHandler')) {
            session_set_save_handler(new PdoSessionHandler(), true);
        }
    }
    session_start();
}

// Base URL detection & HTTPS protocol enforcement
$isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
    || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (strtolower($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '') === 'https')
    || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    || (($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '') == '443')
    || (strpos(strtolower($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https') !== false);

// Check if site_url setting is saved in database
$dbSiteUrl = null;
try {
    $driver = DB_DRIVER;
    $pdoTmp = null;
    $sqlitePath = SQLITE_FILE;
    if ($driver === 'sqlite' && file_exists($sqlitePath)) {
        $pdoTmp = new PDO('sqlite:' . $sqlitePath, null, null, [PDO::ATTR_TIMEOUT => 1]);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdoTmp = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_TIMEOUT => 1]);
    }
    if ($pdoTmp) {
        $st = $pdoTmp->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_url' LIMIT 1");
        $st->execute();
        $val = $st->fetchColumn();
        if (!empty($val)) {
            $dbSiteUrl = trim($val);
        }
    }
} catch (Throwable $e) {
    // Fall back smoothly if database is initializing
}

$envBaseUrl = !empty($dbSiteUrl) ? $dbSiteUrl : (getenv('BASE_URL') ?: getenv('APP_URL'));
if (!empty($envBaseUrl)) {
    $baseUrl = rtrim($envBaseUrl, '/');
    if ($isHttpsRequest && strpos(strtolower($baseUrl), 'http:') === 0) {
        $baseUrl = preg_replace('/^http:/i', 'https:', $baseUrl);
    }
    define('BASE_URL', $baseUrl);
} else {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $isLocalhost = (bool)preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host);
    $isHttps = $isHttpsRequest || !$isLocalhost;

    $protocol = $isHttps ? "https://" : "http://";
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $basePath = preg_replace('/(\/admin|\/api).*$/i', '', $scriptDir);
    $basePath = rtrim($basePath, '/');
    if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }
    define('BASE_URL', $protocol . $host . $basePath);
}

// Hotel Contact & Business Information (for Rich SEO Schema & Site)
define('HOTEL_NAME', 'The Hotel');
define('HOTEL_LEGAL_NAME', 'The Hotel Phnom Penh Co., Ltd.');
define('HOTEL_ADDRESS_STREET', '#95, Street 592, Beungkak 2');
define('HOTEL_ADDRESS_DISTRICT', 'Tuol Kork');
define('HOTEL_ADDRESS_CITY', 'Phnom Penh');
define('HOTEL_ADDRESS_COUNTRY', 'Cambodia');
define('HOTEL_POSTAL_CODE', '12152');
define('HOTEL_PHONE', '(+855) 16 889 066');
define('HOTEL_PHONE_RAW', '+85516889066');
define('HOTEL_EMAIL', '');
define('HOTEL_CHECKIN_TIME', '14:00');
define('HOTEL_CHECKOUT_TIME', '12:00');
define('HOTEL_CURRENCY', 'USD');
define('HOTEL_CURRENCY_SYMBOL', '$');
define('HOTEL_STAR_RATING', '4');
define('HOTEL_PRICE_RANGE', '$$');
define('HOTEL_LATITUDE', '11.5768');
define('HOTEL_LONGITUDE', '104.8985');
define('HOTEL_FACEBOOK', '');
define('HOTEL_INSTAGRAM', '');
define('HOTEL_TRIPADVISOR', '');

// Default Admin Credentials (for seed/fallback)
define('DEFAULT_ADMIN_NAME', 'Hotel Manager');
define('DEFAULT_ADMIN_EMAIL', 'admin@hotel.com');
define('DEFAULT_ADMIN_PASSWORD', 'admin123456');

// Error Reporting (Development mode)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
