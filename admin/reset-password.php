<?php
/**
 * Indra Hotel - Reset Password with Token (SoftBook CMS)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$reset = null;
$isValid = false;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $now = date('Y-m-d H:i:s');
        if ($reset['status'] === 'used') {
            $error = 'This password reset link has already been used. Please request a new one if needed.';
        } elseif ($reset['status'] === 'expired' || $reset['expires_at'] < $now) {
            $error = 'This password reset link has expired. For security reasons, links are only valid for 60 minutes.';
        } else {
            $isValid = true;
        }
    } else {
        $error = 'Invalid password reset token. The link may be broken or corrupted.';
    }
} else {
    $error = 'No reset token provided. Please use the link sent to your email address.';
}

// Handle Password Submission
if ($isValid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid or expired. Please retry.';
    } else {
        $password = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (empty($password) || strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter your password.';
        } else {
            $email = strtolower(trim($reset['email']));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            // 1. Update user password
            $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE LOWER(email) = ?");
            $stmtUpdate->execute([$hash, $email]);

            // 2. Mark reset token as used
            $stmtUsed = $pdo->prepare("UPDATE password_resets SET status = 'used', used_at = ? WHERE id = ?");
            $stmtUsed->execute([$now, $reset['id']]);

            set_flash('success', 'Your password has been successfully reset. You can now sign in.');
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Set New Password - SoftBook CMS</title>
    
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/softbook_favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=DM+Sans:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#343c0a",
                        "deep-olive": "#4B5320",
                        "onyx-charcoal": "#262626",
                        "surface": "#f9f9f9"
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-[#1a1c1c] text-white min-h-screen flex items-center justify-center p-4 font-sans selection:bg-[#4B5320] selection:text-white">

    <div class="max-w-md w-full">
        
        <!-- Brand Header -->
        <div class="text-center mb-8 space-y-2">
            <div class="w-16 h-16 rounded-2xl overflow-hidden bg-white/95 p-1.5 border border-[#dfe8a6]/40 flex items-center justify-center mx-auto shadow-2xl tracking-tight">
                <img src="<?= BASE_URL ?>/assets/images/softbook_logo.png" alt="SoftBook Logo" class="w-full h-full object-contain">
            </div>
            <div class="flex items-center justify-center gap-2 mt-4">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">SoftBook</h1>
                <span class="text-[10px] font-bold bg-[#dfe8a6]/20 text-[#dfe8a6] px-2 py-0.5 rounded uppercase tracking-wider">CMS</span>
            </div>
            <p class="text-stone-400 text-xs tracking-wider uppercase">Set New Password</p>
        </div>

        <!-- Main Card -->
        <div class="bg-[#262626] border border-stone-800 rounded-2xl p-8 shadow-2xl space-y-6">
            
            <?php if (!$isValid): ?>
                <!-- Invalid / Expired State -->
                <div class="text-center space-y-4 py-4">
                    <div class="w-12 h-12 rounded-full bg-rose-950/80 border border-rose-800 text-rose-300 flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">link_off</span>
                    </div>
                    <h2 class="font-headline font-bold text-lg text-white">Reset Link Invalid</h2>
                    <p class="text-xs text-stone-400 leading-relaxed"><?= e($error) ?></p>

                    <div class="pt-4 border-t border-stone-800 flex flex-col gap-2">
                        <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold py-2.5 rounded-lg text-xs tracking-wide transition shadow text-center">
                            Request New Reset Link
                        </a>
                        <a href="<?= BASE_URL ?>/admin/login.php" class="text-xs text-stone-400 hover:text-white transition py-1 text-center">
                            Return to Login
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Valid Reset Form -->
                <div>
                    <h2 class="font-headline font-bold text-xl text-white">Choose a New Password</h2>
                    <p class="text-xs text-stone-400 mt-1">
                        Resetting password for: <strong class="text-white font-mono"><?= e($reset['email']) ?></strong>
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-lg bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span><?= e($error) ?></span>
                </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/admin/reset-password.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">New Password *</label>
                        <input type="password" name="new_password" required minlength="6" placeholder="Minimum 6 characters"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Confirm New Password *</label>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Re-enter your new password"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>

                    <button type="submit" class="w-full bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold py-3.5 rounded-lg text-sm tracking-wide transition shadow-lg mt-2 flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-lg">lock_reset</span>
                        <span>Update Password & Sign In</span>
                    </button>
                </form>

            <?php endif; ?>

        </div>

        <div class="text-center mt-6">
            <a href="<?= BASE_URL ?>/admin/login.php" class="text-xs text-stone-500 hover:text-stone-300 transition flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Back to Login</span>
            </a>
        </div>

    </div>

</body>
</html>
