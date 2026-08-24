<?php
/**
 * Indra Hotel - Database Installer & Migration Utility
 * Run via CLI: php install.php
 * Or via browser: http://localhost:8000/install.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$isCli = (php_sapi_name() === 'cli');
$success = false;
$message = '';
$driver = 'unknown';

try {
    $pdo = getDB();
    $driver = Database::getDriver();
    Database::initializeDatabase($pdo, $driver);
    $success = true;
    $message = "Database initialized and seeded successfully using driver: [{$driver}].";
} catch (Throwable $e) {
    $message = "Installation Error: " . $e->getMessage();
}

if ($isCli) {
    echo "\n============================================\n";
    echo "  INDRA HOTEL - DATABASE INSTALLER\n";
    echo "============================================\n";
    echo ($success ? "[SUCCESS] " : "[ERROR] ") . $message . "\n";
    echo "Active Driver: " . $driver . "\n";
    echo "============================================\n\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Indra Hotel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="bg-white max-w-lg w-full rounded-xl shadow-lg p-8 border border-stone-200">
        <div class="flex items-center space-x-3 mb-6">
            <div class="w-10 h-10 rounded bg-[#343c0a] text-white flex items-center justify-center font-bold text-lg">IH</div>
            <div>
                <h1 class="text-2xl font-bold text-stone-900">Indra Hotel Setup</h1>
                <p class="text-sm text-stone-500">Database Initializer & Health Check</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-lg mb-6">
                <div class="font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Database Ready
                </div>
                <p class="text-sm mt-1"><?= htmlspecialchars($message) ?></p>
            </div>

            <div class="bg-stone-50 rounded-lg p-4 mb-6 text-sm text-stone-700 space-y-2 border border-stone-200">
                <div><span class="font-medium">Database Name:</span> <?= htmlspecialchars(DB_NAME) ?></div>
                <div><span class="font-medium">Active Driver:</span> <span class="uppercase font-semibold text-[#343c0a]"><?= htmlspecialchars($driver) ?></span></div>
            </div>

            <div class="flex gap-4">
                <a href="<?= BASE_URL ?>/index.php" class="flex-1 text-center bg-[#343c0a] text-white py-3 rounded-lg font-medium hover:bg-[#4b5320] transition">Visit Website</a>
                <a href="<?= BASE_URL ?>/admin/login.php" class="flex-1 text-center bg-stone-900 text-white py-3 rounded-lg font-medium hover:bg-stone-800 transition">Admin Dashboard</a>
            </div>
        <?php else: ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-lg mb-6">
                <div class="font-semibold">Setup Failed</div>
                <p class="text-sm mt-1"><?= htmlspecialchars($message) ?></p>
            </div>
            <a href="install.php" class="block text-center bg-stone-900 text-white py-3 rounded-lg font-medium hover:bg-stone-800 transition">Retry Setup</a>
        <?php endif; ?>
    </div>
</body>
</html>
