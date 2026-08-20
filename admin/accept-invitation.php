<?php
/**
 * Indra Hotel - Accept Staff Invitation & Initial Password Setup (SoftBook CMS)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$invitation = null;
$isValid = false;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM user_invitations WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $invitation = $stmt->fetch();

    if ($invitation) {
        $now = date('Y-m-d H:i:s');
        if ($invitation['status'] === 'accepted') {
            $error = 'This invitation has already been accepted and your account is active. Please proceed to the login page.';
        } elseif ($invitation['status'] === 'revoked') {
            $error = 'This invitation was revoked by the administrator. Please contact your administrator for a new invite.';
        } elseif ($invitation['expires_at'] < $now) {
            $error = 'This invitation link has expired (validity period was 48 hours). Please contact your administrator to resend the invite.';
        } else {
            $isValid = true;
        }
    } else {
        $error = 'Invalid invitation token. The link may be broken or corrupted.';
    }
} else {
    $error = 'No invitation token provided. Please use the link sent to your email address.';
}

// Handle Password Submission
if ($isValid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid or expired. Please retry.';
    } else {
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (empty($password) || strlen($password) < 6) {
            $error = 'Password is required and must be at least 6 characters in length.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter your password.';
        } else {
            $email = strtolower(trim($invitation['email']));
            $name = trim($invitation['name']);
            $role = in_array($invitation['role'], ['admin', 'editor'], true) ? $invitation['role'] : 'editor';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            // 1. Check if user already exists
            $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
            $stmtCheck->execute([$email]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // Update existing user credentials & role
                $stmtUpdate = $pdo->prepare("UPDATE users SET name = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmtUpdate->execute([$name, $hash, $role, $existing['id']]);
                $userId = (int)$existing['id'];
            } else {
                // Create new user record
                $stmtInsert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$name, $email, $hash, $role]);
                $userId = (int)$pdo->lastInsertId();
            }

            // 2. Mark invitation accepted
            $stmtAccept = $pdo->prepare("UPDATE user_invitations SET status = 'accepted', accepted_at = ? WHERE id = ?");
            $stmtAccept->execute([$now, $invitation['id']]);

            // 3. Fetch active user record and initialize session
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmtUser->execute([$userId]);
            $activeUser = $stmtUser->fetch();

            if ($activeUser) {
                Auth::loginUser($activeUser);
                set_flash('success', "Welcome to SoftBook, {$activeUser['name']}! Your account has been activated.");
                header('Location: ' . BASE_URL . '/admin/index.php');
                exit;
            } else {
                set_flash('success', 'Your account has been activated! You can now log in.');
                header('Location: ' . BASE_URL . '/admin/login.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Accept Staff Invitation - SoftBook CMS</title>
    
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
            <p class="text-stone-400 text-xs tracking-wider uppercase"><?= e(hotel_name()) ?> Staff Onboarding</p>
        </div>

        <!-- Main Card -->
        <div class="bg-[#262626] border border-stone-800 rounded-2xl p-8 shadow-2xl space-y-6">
            
            <?php if (!$isValid): ?>
                <!-- Invalid / Expired State -->
                <div class="text-center space-y-4 py-4">
                    <div class="w-12 h-12 rounded-full bg-rose-950/80 border border-rose-800 text-rose-300 flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">error</span>
                    </div>
                    <h2 class="font-headline font-bold text-lg text-white">Invitation Unavailable</h2>
                    <p class="text-xs text-stone-400 leading-relaxed"><?= e($error) ?></p>

                    <div class="pt-4 border-t border-stone-800">
                        <a href="<?= BASE_URL ?>/admin/login.php" class="inline-flex items-center gap-2 bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold px-6 py-2.5 rounded-lg text-xs tracking-wide transition shadow">
                            <span class="material-symbols-outlined text-sm">login</span>
                            <span>Go to Portal Login</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Valid Invitation Form -->
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-[#dfe8a6] block mb-1">Welcome Aboard</span>
                    <h2 class="font-headline font-bold text-xl text-white">Create Your Account Password</h2>
                    <p class="text-xs text-stone-400 mt-1">Set your confidential password to activate your staff portal account.</p>
                </div>

                <!-- Invitee Info Pill -->
                <div class="bg-stone-900/80 border border-stone-800 rounded-xl p-4 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-stone-500 font-semibold uppercase text-[10px]">Invited User</span>
                        <span class="font-bold text-white"><?= e($invitation['name']) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-stone-500 font-semibold uppercase text-[10px]">Email Address</span>
                        <span class="font-mono text-stone-300 text-[11px]"><?= e($invitation['email']) ?></span>
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-stone-800">
                        <span class="text-stone-500 font-semibold uppercase text-[10px]">Assigned Role</span>
                        <?php if ($invitation['role'] === 'admin'): ?>
                            <span class="bg-[#343c0a] text-[#dfe8a6] text-[10px] font-bold px-2 py-0.5 rounded uppercase">Administrator</span>
                        <?php else: ?>
                            <span class="bg-amber-950 text-amber-300 border border-amber-800 text-[10px] font-bold px-2 py-0.5 rounded uppercase">Content Editor</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-lg bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span><?= e($error) ?></span>
                </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/admin/accept-invitation.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Create Password *</label>
                        <input type="password" name="password" required minlength="6" placeholder="Minimum 6 characters"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Confirm Password *</label>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Re-type your password"
                               class="w-full bg-stone-900 border border-stone-700 text-white rounded-lg p-3 text-sm focus:ring-2 focus:ring-[#dfe8a6] focus:border-transparent">
                    </div>

                    <button type="submit" class="w-full bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold py-3.5 rounded-lg text-sm tracking-wide transition shadow-lg mt-2 flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        <span>Activate Account & Sign In</span>
                    </button>
                </form>

            <?php endif; ?>

        </div>

        <div class="text-center mt-6">
            <a href="<?= BASE_URL ?>/admin/login.php" class="text-xs text-stone-500 hover:text-stone-300 transition flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Return to Login</span>
            </a>
        </div>

    </div>

</body>
</html>
