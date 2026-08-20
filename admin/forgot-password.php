<?php
/**
 * Indra Hotel - Staff Forgot Password Request (SoftBook CMS)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

$error = '';
$sent = false;
$emailSubmitted = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please retry.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $emailSubmitted = $email;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity

                // Invalidate existing pending tokens for this email
                $pdo->prepare("UPDATE password_resets SET status = 'expired' WHERE LOWER(email) = ? AND status = 'pending'")->execute([$email]);

                // Insert new reset token
                $stmtInsert = $pdo->prepare("INSERT INTO password_resets (email, token, status, expires_at) VALUES (?, ?, 'pending', ?)");
                $stmtInsert->execute([$email, $token, $expiresAt]);

                // Dispatch reset email
                Mailer::sendPasswordReset($email, $user['name'], $token);
            }

            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Forgot Password - SoftBook CMS</title>
    
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
            <p class="text-stone-400 text-xs tracking-wider uppercase">Portal Password Recovery</p>
        </div>

        <!-- Main Card -->
        <div class="bg-[#262626] border border-stone-800 rounded-2xl p-8 shadow-2xl space-y-6">
            
            <?php if ($sent): ?>
                <!-- Success State -->
                <div class="text-center space-y-4 py-2">
                    <div class="w-12 h-12 rounded-full bg-emerald-950 border border-emerald-700 text-emerald-300 flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">mark_email_read</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-white">Check Your Inbox</h2>
                        <p class="text-xs text-stone-400 mt-2 leading-relaxed">
                            If an account is associated with <strong class="text-white font-mono"><?= e($emailSubmitted) ?></strong>, we have sent a secure password reset link.
                        </p>
                    </div>
                    <div class="p-3.5 bg-stone-900 border border-stone-800 rounded-xl text-[11px] text-stone-400 text-left space-y-1">
                        <div class="font-semibold text-stone-300 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-[#dfe8a6]">info</span>
                            <span>Instructions:</span>
                        </div>
                        <p>1. Open the link in the email within 60 minutes.</p>
                        <p>2. Choose your new confidential password.</p>
                        <p>3. If you don't see it, check your spam/junk folder.</p>
                    </div>

                    <div class="pt-4 border-t border-stone-800">
                        <a href="<?= BASE_URL ?>/admin/login.php" class="inline-flex items-center gap-2 bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold px-6 py-2.5 rounded-lg text-xs tracking-wide transition shadow">
                            <span class="material-symbols-outlined text-sm">arrow_back</span>
                            <span>Return to Sign In</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Reset Request Form -->
                <div>
                    <h2 class="font-headline font-bold text-xl text-white">Reset Portal Password</h2>
                    <p class="text-xs text-stone-400 mt-1">Enter your registered staff email address to receive password reset instructions.</p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-lg bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span><?= e($error) ?></span>
                </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/admin/forgot-password.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Email Address *</label>
                        <input type="email" name="email" required placeholder="e.g. staff@hotel.com" value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>

                    <button type="submit" class="w-full bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold py-3.5 rounded-lg text-sm tracking-wide transition shadow-lg mt-2 flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-lg">send</span>
                        <span>Send Password Reset Link</span>
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
