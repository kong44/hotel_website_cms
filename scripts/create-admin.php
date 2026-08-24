<?php
/**
 * Indra Hotel CMS - Admin Account Generator / Password Resetter
 * Usage via CLI: php scripts/create-admin.php <email> <password> <name>
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$email = $argv[1] ?? 'admin@local.com';
$password = $argv[2] ?? 'TempAdmin2026!';
$name = $argv[3] ?? 'Temp Administrator';

echo "\n============================================\n";
echo "  INDRA HOTEL - ADMIN ACCOUNT GENERATOR\n";
echo "============================================\n";

try {
    $pdo = getDB();
    $driver = Database::getDriver();
    echo "Active Database Driver: [{$driver}]\n";

    $email = trim(strtolower($email));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if user already exists
    $stmtCheck = $pdo->prepare("SELECT id, name FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmtCheck->execute([$email]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        // Update existing user password & promote to admin
        $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', name = ? WHERE id = ?");
        $stmtUpdate->execute([$hash, $name, $existing['id']]);
        echo "[SUCCESS] Updated existing user '{$existing['name']}' ({$email}) to Admin with new password.\n";
    } else {
        // Create new admin user
        $stmtInsert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmtInsert->execute([$name, $email, $hash]);
        echo "[SUCCESS] Created brand new Admin account: {$email}\n";
    }

    echo "\n--------------------------------------------\n";
    echo "🔑 LOGIN CREDENTIALS:\n";
    echo "  Login Page: " . BASE_URL . "/admin/login.php\n";
    echo "  Email:      {$email}\n";
    echo "  Password:   {$password}\n";
    echo "  Role:       admin\n";
    echo "--------------------------------------------\n\n";
} catch (Throwable $e) {
    echo "[ERROR] Failed to create/update admin account: " . $e->getMessage() . "\n\n";
    exit(1);
}
