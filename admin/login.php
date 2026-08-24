<?php
/**
 * Indra Hotel - SoftBook CMS Login
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

$error = '';
$redirect = $_GET['redirect'] ?? (BASE_URL . '/admin/index.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!Auth::verifyCsrf($csrf)) {
        $error = 'Security token invalid or expired. Please retry.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } elseif (Auth::attempt($email, $password)) {
        set_flash('success', 'Welcome back to SoftBook.');
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid credentials. Please verify your email and password.';
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>SoftBook - <?= e(hotel_name()) ?> Management Portal</title>
    
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/softbook_favicon.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/softbook_logo.png">
    
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
            <p class="text-stone-400 text-xs tracking-wider uppercase"><?= e(hotel_name()) ?> Management Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-[#262626] border border-stone-800 rounded-2xl p-8 shadow-2xl space-y-6">
            
            <?php if (!empty($error)): ?>
            <div class="p-3.5 rounded-lg bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-center gap-2">
                <span class="material-symbols-outlined text-base">error</span>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/login.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Email Address</label>
                    <div class="relative">
                        <input type="email" name="email" id="login_email" required 
                               value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@hotel.com"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400">Password</label>
                        <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="text-[11px] text-[#dfe8a6] hover:text-white hover:underline transition">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" id="login_password" required 
                               placeholder="••••••••"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-stone-400">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded bg-stone-900 border-stone-700 text-[#4B5320]">
                        <span>Remember session</span>
                    </label>
                    <span class="text-stone-500 text-[11px]">Protected Portal</span>
                </div>

                <button type="submit" class="w-full bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold py-3.5 rounded-lg text-sm tracking-wide transition shadow-lg mt-2 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">lock_open</span>
                    <span>Sign In to Dashboard</span>
                </button>
            </form>

        </div>

        <div class="text-center mt-6">
            <a href="<?= BASE_URL ?>/index.php" class="text-xs text-stone-500 hover:text-stone-300 transition flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Return to Public Website</span>
            </a>
        </div>

    </div>

</body>
</html>
