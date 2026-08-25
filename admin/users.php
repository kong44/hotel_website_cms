<?php
/**
 * Indra Hotel - User Management, Email Invitations & Access Control CMS (SoftBook CMS)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/google-auth.php';

// Strict Role Enforcement: Administrator privileges required
Auth::requireAdmin();

$pdo = getDB();
$currentUser = Auth::user();
$adminTitle = 'User Management & Invitations';

// Handle Add / Edit / Delete / Invite / Resend / Revoke / Google SSO POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token. Please try again.');
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? 'save';

    // 1. Send Email Invitation to New Staff Member
    if ($action === 'invite') {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';

        if (empty($name) || empty($email)) {
            set_flash('error', 'Name and email are required to send an invitation.');
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please provide a valid email address.');
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        // Check if user already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            set_flash('error', "A portal account with the email '{$email}' already exists. You can edit their role directly in the table below.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        // Invalidate older pending invites for this email
        $pdo->prepare("UPDATE user_invitations SET status = 'expired' WHERE LOWER(email) = ? AND status = 'pending'")->execute([$email]);

        // Generate secure 48h invitation token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 172800); // 48 hours validity

        $stmtInsert = $pdo->prepare("INSERT INTO user_invitations (name, email, role, token, invited_by, status, expires_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmtInsert->execute([$name, $email, $role, $token, (int)$currentUser['id'], $expiresAt]);

        // Dispatch invitation email via SMTP Mailer
        $mailRes = Mailer::sendUserInvitation($email, $name, $role, $token, $currentUser['name']);

        if ($mailRes['success']) {
            set_flash('success', "Invitation sent to {$email} ({$role}). The recipient has 48 hours to activate their account and set their password.");
        } else {
            set_flash('warning', "Invitation created for {$email}, but the email could not be delivered automatically: " . e($mailRes['message']) . ". You can copy the invitation link directly from the Pending Invitations table below.");
        }

        header('Location: ' . BASE_URL . '/admin/users.php#invitations-ledger');
        exit;
    }

    // 2. Resend Invitation Email
    if ($action === 'resend_invitation') {
        $inviteId = (int)($_POST['invitation_id'] ?? 0);
        $stmtInv = $pdo->prepare("SELECT * FROM user_invitations WHERE id = ? LIMIT 1");
        $stmtInv->execute([$inviteId]);
        $inv = $stmtInv->fetch();

        if ($inv) {
            $newToken = bin2hex(random_bytes(32));
            $newExpiresAt = date('Y-m-d H:i:s', time() + 172800);

            $stmtUpdate = $pdo->prepare("UPDATE user_invitations SET token = ?, expires_at = ?, status = 'pending' WHERE id = ?");
            $stmtUpdate->execute([$newToken, $newExpiresAt, $inviteId]);

            $mailRes = Mailer::sendUserInvitation($inv['email'], $inv['name'], $inv['role'], $newToken, $currentUser['name']);

            if ($mailRes['success']) {
                set_flash('success', "Invitation re-sent successfully to {$inv['email']}.");
            } else {
                set_flash('warning', "Invitation refreshed for {$inv['email']}, but email delivery failed: " . e($mailRes['message']));
            }
        }
        header('Location: ' . BASE_URL . '/admin/users.php#invitations-ledger');
        exit;
    }

    // 3. Revoke / Cancel Invitation
    if ($action === 'revoke_invitation') {
        $inviteId = (int)($_POST['invitation_id'] ?? 0);
        $stmtRevoke = $pdo->prepare("UPDATE user_invitations SET status = 'revoked' WHERE id = ?");
        $stmtRevoke->execute([$inviteId]);

        set_flash('success', 'The invitation has been revoked.');
        header('Location: ' . BASE_URL . '/admin/users.php#invitations-ledger');
        exit;
    }

    // 4. Save Google OAuth SSO Configuration
    if ($action === 'save_google_oauth') {
        $googleSettings = [
            'google_oauth_enabled' => isset($_POST['google_oauth_enabled']) ? '1' : '0',
            'google_oauth_client_id' => trim($_POST['google_oauth_client_id'] ?? ''),
            'google_oauth_client_secret' => trim($_POST['google_oauth_client_secret'] ?? ''),
            'google_oauth_allowed_domain' => trim(strtolower($_POST['google_oauth_allowed_domain'] ?? '')),
            'google_oauth_auto_create_user' => isset($_POST['google_oauth_auto_create_user']) ? '1' : '0',
            'google_oauth_default_role' => in_array($_POST['google_oauth_default_role'] ?? '', ['editor', 'admin'], true) ? $_POST['google_oauth_default_role'] : 'editor'
        ];

        $stmtUpsert = (Database::getDriver() === 'sqlite')
            ? $pdo->prepare("INSERT OR REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)")
            : $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($googleSettings as $k => $v) {
            $stmtUpsert->execute([$k, $v]);
        }

        get_setting('__refresh__');
        set_flash('success', 'Google OAuth 2.0 configuration updated successfully.');
        header('Location: ' . BASE_URL . '/admin/users.php#google-oauth');
        exit;
    }

    // 5. Delete User
    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId === (int)$currentUser['id']) {
            set_flash('error', 'You cannot delete your own active administrator account.');
        } else {
            // Ensure we don't delete the last admin
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $targetUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $targetUser->execute([$userId]);
            $target = $targetUser->fetch();

            if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
                set_flash('error', 'Cannot delete the only remaining Administrator account.');
            } elseif ($target) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                set_flash('success', "User '{$target['name']}' has been permanently deleted.");
            }
        }
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    // 6. Direct Create or Update User (with Password)
    if ($action === 'save') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';
        $password = trim($_POST['password'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if (empty($name) || empty($email)) {
            set_flash('error', 'Name and email are required fields.');
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please provide a valid email address.');
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        // Check for duplicate email
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$email, $userId]);
        if ($checkStmt->fetch()) {
            set_flash('error', "A user with the email '{$email}' already exists.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }

        if ($userId > 0) {
            // Update Existing User
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    set_flash('error', 'Password must be at least 6 characters.');
                    header('Location: ' . BASE_URL . '/admin/users.php');
                    exit;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, avatar = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $avatar, $hash, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $avatar, $userId]);
            }
            set_flash('success', "User '{$name}' details updated successfully.");
        } else {
            // Create New User Directly
            if (empty($password) || strlen($password) < 6) {
                set_flash('error', 'Password is required and must be at least 6 characters for direct account creation.');
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, avatar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role, $avatar]);
            set_flash('success', "New user '{$name}' created with {$role} level access.");
        }

        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }
}

// Fetch All Users
$stmtUsers = $pdo->query("SELECT * FROM users ORDER BY role ASC, id ASC");
$users = $stmtUsers->fetchAll();

// Fetch Pending & Recent Invitations
$stmtInv = $pdo->query("SELECT i.*, u.name as inviter_name FROM user_invitations i LEFT JOIN users u ON i.invited_by = u.id ORDER BY i.id DESC LIMIT 25");
$invitations = $stmtInv ? $stmtInv->fetchAll() : [];
$pendingCount = count(array_filter($invitations, fn($i) => ($i['status'] ?? '') === 'pending' && ($i['expires_at'] ?? '') >= date('Y-m-d H:i:s')));

// Calculate Stats
$totalUsers = count($users);
$totalAdmins = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'));
$totalEditors = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'editor'));
$googleUsers = count(array_filter($users, fn($u) => !empty($u['google_id'])));

$googleOauthEnabled = (get_setting('google_oauth_enabled', '0') === '1');
$googleClientId = get_setting('google_oauth_client_id', '');
$googleClientSecret = get_setting('google_oauth_client_secret', '');
$googleAllowedDomain = get_setting('google_oauth_allowed_domain', '');
$googleAutoCreate = (get_setting('google_oauth_auto_create_user', '0') === '1');
$googleDefaultRole = get_setting('google_oauth_default_role', 'editor');
$googleReady = GoogleAuth::isEnabled();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8">

    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Access Control & Security</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">User Management & Invitations</h1>
            <p class="text-xs text-stone-500 mt-1">Invite staff via email, manage roles & permissions, and configure public Google sign-in.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Public Guest Directory Link -->
            <a href="<?= BASE_URL ?>/admin/guests.php" 
               class="inline-flex items-center gap-2 bg-white hover:bg-stone-50 text-stone-700 border border-stone-300 px-4 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow-2xs">
                <span class="material-symbols-outlined text-base text-[#343c0a]">groups</span>
                <span>Public Guest Directory</span>
            </a>

            <!-- Invite via Email Button (Primary) -->
            <button onclick="openInviteModal()" 
                    class="inline-flex items-center gap-2 bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow cursor-pointer">
                <span class="material-symbols-outlined text-base">forward_to_inbox</span>
                <span>Invite Staff via Email</span>
            </button>

            <!-- Direct Add User Button -->
            <button onclick="openUserModal(0, '', '', 'editor', '')" 
                    class="inline-flex items-center gap-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 px-4 py-2.5 rounded-lg text-xs font-bold tracking-wide transition border border-stone-300 cursor-pointer">
                <span class="material-symbols-outlined text-base">person_add</span>
                <span>Direct Add</span>
            </button>
        </div>
    </div>

    <!-- Role KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-stone-400">Total Accounts</span>
                <div class="font-headline text-2xl font-bold text-onyx-charcoal mt-1"><?= $totalUsers ?></div>
                <span class="text-[10px] text-stone-500">Active staff members</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">group</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#343c0a]">Administrators</span>
                <div class="font-headline text-2xl font-bold text-[#343c0a] mt-1"><?= $totalAdmins ?></div>
                <span class="text-[10px] text-stone-500">Full system & brand control</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-[#dfe8a6]/40 text-[#343c0a] flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Content Editors</span>
                <div class="font-headline text-2xl font-bold text-amber-800 mt-1"><?= $totalEditors ?></div>
                <span class="text-[10px] text-stone-500">Rooms, bookings & media</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Pending Invites</span>
                <div class="font-headline text-2xl font-bold text-blue-800 mt-1"><?= $pendingCount ?></div>
                <span class="text-[10px] text-stone-500">Awaiting user setup</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">mail</span>
            </div>
        </div>
    </div>

    <!-- Active User Ledger Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-stone-100 flex items-center justify-between">
            <div>
                <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Active Portal Staff</h2>
                <p class="text-xs text-stone-500">Registered users who can sign in to SoftBook CMS.</p>
            </div>
            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-stone-100 text-stone-700">
                <?= count($users) ?> Active Members
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                    <tr>
                        <th class="py-4 px-6 font-semibold">Staff Member</th>
                        <th class="py-4 px-6 font-semibold">Email Address</th>
                        <th class="py-4 px-6 font-semibold">Role Level</th>
                        <th class="py-4 px-6 font-semibold">Joined Date</th>
                        <th class="py-4 px-6 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($users as $u): 
                        $isAdminRole = ($u['role'] ?? '') === 'admin';
                        $isSelf = (int)$u['id'] === (int)$currentUser['id'];
                    ?>
                    <tr class="hover:bg-stone-50/80 transition">
                        <!-- User Name & Avatar -->
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($u['avatar'])): ?>
                                    <img src="<?= e($u['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-stone-200 shadow-2xs">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full text-white flex items-center justify-center font-bold text-sm shadow-2xs" style="background-color: <?= $isAdminRole ? '#343c0a' : '#4a2d53' ?>;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="font-headline font-bold text-stone-900 flex items-center gap-2">
                                        <span><?= e($u['name']) ?></span>
                                        <?php if ($isSelf): ?>
                                            <span class="bg-stone-100 text-stone-600 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-stone-200">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[10px] text-stone-400">ID: #<?= (int)$u['id'] ?></span>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td class="py-4 px-6 font-mono text-stone-600">
                            <?= e($u['email']) ?>
                        </td>

                        <!-- Role Level Badge -->
                        <td class="py-4 px-6">
                            <?php if ($isAdminRole): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-[#343c0a] text-white shadow-2xs uppercase tracking-wide">
                                    <span class="material-symbols-outlined text-xs">shield_person</span>
                                    <span>Administrator</span>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wide">
                                    <span class="material-symbols-outlined text-xs">edit_note</span>
                                    <span>Content Editor</span>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Date -->
                        <td class="py-4 px-6 text-stone-500">
                            <?= !empty($u['created_at']) ? format_date($u['created_at']) : 'Active' ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" 
                                        onclick='openUserModal(<?= (int)$u['id'] ?>, <?= json_encode($u['name']) ?>, <?= json_encode($u['email']) ?>, <?= json_encode($u['role']) ?>, <?= json_encode($u['avatar'] ?? "") ?>)'
                                        class="p-1.5 text-stone-600 hover:text-stone-900 rounded hover:bg-stone-100 inline-flex items-center gap-1 transition cursor-pointer"
                                        title="Edit User">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                    <span class="text-xs font-semibold">Edit</span>
                                </button>

                                <?php if (!$isSelf): ?>
                                <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete user <?= e(addslashes($u['name'])) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="p-1.5 text-rose-600 hover:text-rose-800 rounded hover:bg-rose-50 inline-flex items-center gap-1 transition cursor-pointer" title="Delete User">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- =======================================================
         Staff Email Invitations & Onboarding Ledger
         ======================================================= -->
    <div id="invitations-ledger" class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden scroll-mt-6">
        <div class="p-6 border-b border-stone-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span>Staff Email Invitations</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                            <?= $pendingCount ?> Pending
                        </span>
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-stone-500">Track sent invitations, copy direct setup links, and resend expiring onboarding emails.</p>
            </div>

            <button onclick="openInviteModal()" class="inline-flex items-center gap-1.5 bg-[#343c0a] hover:bg-deep-olive text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow-xs cursor-pointer">
                <span class="material-symbols-outlined text-base">add</span>
                <span>Send New Invite</span>
            </button>
        </div>

        <?php if (!empty($invitations)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 text-stone-500 uppercase tracking-wider border-b border-stone-200">
                        <tr>
                            <th class="py-3.5 px-6 font-semibold">Invited User</th>
                            <th class="py-3.5 px-6 font-semibold">Email</th>
                            <th class="py-3.5 px-6 font-semibold">Role</th>
                            <th class="py-3.5 px-6 font-semibold">Status & Expiration</th>
                            <th class="py-3.5 px-6 font-semibold">Invited By</th>
                            <th class="py-3.5 px-6 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php 
                        $nowTime = time();
                        foreach ($invitations as $inv): 
                            $isPending = ($inv['status'] === 'pending');
                            $expTime = strtotime($inv['expires_at']);
                            $isExpired = $isPending && ($expTime < $nowTime);
                            $inviteLink = BASE_URL . '/admin/accept-invitation.php?token=' . urlencode($inv['token']);
                        ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-6 font-bold text-stone-900">
                                <?= e($inv['name']) ?>
                            </td>

                            <td class="py-3.5 px-6 font-mono text-stone-600">
                                <?= e($inv['email']) ?>
                            </td>

                            <td class="py-3.5 px-6">
                                <?php if ($inv['role'] === 'admin'): ?>
                                    <span class="bg-[#343c0a] text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase">Admin</span>
                                <?php else: ?>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded uppercase">Editor</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-6">
                                <?php if ($inv['status'] === 'accepted'): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="material-symbols-outlined text-xs">check_circle</span>
                                        <span>Accepted (Active)</span>
                                    </span>
                                <?php elseif ($inv['status'] === 'revoked'): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-stone-100 text-stone-600">
                                        <span>Revoked</span>
                                    </span>
                                <?php elseif ($isExpired): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 border border-rose-200">
                                        <span class="material-symbols-outlined text-xs">timer_off</span>
                                        <span>Expired</span>
                                    </span>
                                <?php else: 
                                    $hrsLeft = max(1, round(($expTime - $nowTime) / 3600));
                                ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1 rounded-full bg-blue-50 text-blue-800 border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-pulse"></span>
                                        <span>Pending (<?= $hrsLeft ?>h left)</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-6 text-stone-500 text-[11px]">
                                <?= e($inv['inviter_name'] ?: 'Administrator') ?><br>
                                <span class="text-[10px] text-stone-400"><?= format_date($inv['created_at']) ?></span>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if ($isPending && !$isExpired): ?>
                                        <!-- Copy Direct Link Button -->
                                        <button type="button" 
                                                onclick="copyCustomText('<?= e($inviteLink) ?>', this)"
                                                class="px-2 py-1 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded text-[11px] font-semibold inline-flex items-center gap-1 transition cursor-pointer"
                                                title="Copy setup URL">
                                            <span class="material-symbols-outlined text-sm">content_copy</span>
                                            <span>Copy Link</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($inv['status'] !== 'accepted'): ?>
                                        <!-- Resend Email -->
                                        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                            <input type="hidden" name="action" value="resend_invitation">
                                            <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                            <button type="submit" 
                                                    class="px-2 py-1 text-blue-700 hover:text-blue-900 hover:bg-blue-50 rounded text-[11px] font-semibold inline-flex items-center gap-1 transition cursor-pointer"
                                                    title="Resend invitation email">
                                                <span class="material-symbols-outlined text-sm">forward_to_inbox</span>
                                                <span>Resend</span>
                                            </button>
                                        </form>

                                        <?php if ($inv['status'] !== 'revoked'): ?>
                                        <!-- Revoke Invite -->
                                        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="inline" onsubmit="return confirm('Revoke invitation for <?= e(addslashes($inv['email'])) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                            <input type="hidden" name="action" value="revoke_invitation">
                                            <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                            <button type="submit" 
                                                    class="p-1 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded inline-flex items-center transition cursor-pointer"
                                                    title="Revoke Invitation">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-stone-500 text-xs">
                <span class="material-symbols-outlined text-2xl text-stone-400 block mb-1">outgoing_mail</span>
                <span>No staff invitations have been sent yet. Click "Invite User via Email" to send an onboarding link.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- =======================================================
         Google Sign-in Configuration Link Card
         ======================================================= -->
    <div id="google-oauth" class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-6 scroll-mt-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 p-2.5 flex items-center justify-center shrink-0 border border-blue-100">
                <svg class="w-full h-full" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-headline font-bold text-lg text-onyx-charcoal flex items-center gap-2">
                    <span>Google Sign-In for Public Guest Portal</span>
                    <?php if (get_setting('google_oauth_enabled', '0') === '1' && !empty(get_setting('google_oauth_client_id'))): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Active</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-stone-100 text-stone-600">Configurable in Settings</span>
                    <?php endif; ?>
                </h3>
                <p class="text-xs text-stone-500">Google Sign-In parameters (Client ID & Secret) are configured under Admin Settings.</p>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/admin/settings.php#google-oauth" class="inline-flex items-center gap-2 bg-[#343c0a] hover:bg-deep-olive text-white px-5 py-2.5 rounded-lg text-xs font-bold transition shrink-0 shadow-sm">
            <span>Configure Google Sign-in in Settings</span>
            <span class="material-symbols-outlined text-sm">settings</span>
        </a>
    </div>

</div>

<!-- =======================================================
     Invite Staff via Email Modal
     ======================================================= -->
<div id="invite-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6">
        
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div>
                <h3 class="font-headline font-bold text-xl text-onyx-charcoal flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#343c0a]">forward_to_inbox</span>
                    <span>Invite Staff Member</span>
                </h3>
                <p class="text-[11px] text-stone-500">Send an email invitation with a secure 48-hour password setup link.</p>
            </div>
            <button onclick="closeInviteModal()" class="text-stone-400 hover:text-stone-700 p-1 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="action" value="invite">

            <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-xl text-stone-600 text-[11px] leading-relaxed">
                <strong class="text-stone-800">How it works:</strong> An invitation email will be dispatched via your configured SMTP server. The recipient will click the link to create their first confidential password and activate their account.
            </div>

            <!-- Full Name -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Recipient Name *</label>
                <input type="text" name="name" required placeholder="e.g. Johnathan Doe" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <!-- Email Address -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Email Address (Recipient) *</label>
                <input type="email" name="email" required placeholder="e.g. staff@domain.com" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <!-- Role Level Selector -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Permission Level *</label>
                <select name="role" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                    <option value="editor">Content Editor — Accommodations, Dining, Offers, Media & Bookings</option>
                    <option value="admin">Administrator — Full System, Brand, SEO & User Control</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeInviteModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">send</span>
                    <span>Send Invitation Email</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =======================================================
     Direct Add / Edit User Modal
     ======================================================= -->
<div id="user-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 border border-stone-200 shadow-2xl space-y-6">
        
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <div>
                <h3 id="user-modal-title" class="font-headline font-bold text-xl text-onyx-charcoal">Add Staff User Directly</h3>
                <p class="text-[11px] text-stone-500">Configure login credentials and permission level directly.</p>
            </div>
            <button onclick="closeUserModal()" class="text-stone-400 hover:text-stone-700 p-1 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="space-y-5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="user_id" id="modal_user_id" value="0">

            <!-- Full Name -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Full Name *</label>
                <input type="text" name="name" id="modal_user_name" required placeholder="e.g. Johnathan Doe" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <!-- Email Address -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Email Address (Login) *</label>
                <input type="email" name="email" id="modal_user_email" required placeholder="e.g. staff@domain.com" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <!-- Role Level Selector -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">Permission Level *</label>
                <select name="role" id="modal_user_role" class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-bold">
                    <option value="editor">Editor — Manage Accommodations, Dining, Offers, Gallery & Bookings</option>
                    <option value="admin">Administrator — Full System, Brand, SEO & User Control</option>
                </select>
            </div>

            <!-- Password -->
            <div>
                <label class="block font-bold text-stone-700 uppercase mb-1">
                    <span id="modal_password_label">Password *</span>
                </label>
                <input type="password" name="password" id="modal_user_password" placeholder="Minimum 6 characters" 
                       class="w-full border border-stone-300 rounded-lg p-2.5 text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
                <p id="modal_password_hint" class="text-[11px] text-stone-400 mt-1">Required for new accounts. Leave blank to keep current password when editing.</p>
            </div>

            <!-- Avatar Image Uploader -->
            <div>
                <?= render_image_uploader_field('avatar', '', 'Profile Avatar Photo', 'brand', [
                    'helper' => 'Square profile photo (PNG/JPG)'
                ]) ?>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-stone-200 flex justify-end gap-3">
                <button type="button" onclick="closeUserModal()" class="px-5 py-2 border border-stone-300 rounded-lg text-stone-600 font-semibold cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-6 py-2 rounded-lg font-bold shadow cursor-pointer">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openInviteModal() {
    document.getElementById('invite-modal').classList.remove('hidden');
}

function closeInviteModal() {
    document.getElementById('invite-modal').classList.add('hidden');
}

function openUserModal(id, name, email, role, avatar) {
    document.getElementById('modal_user_id').value = id || 0;
    document.getElementById('modal_user_name').value = name || '';
    document.getElementById('modal_user_email').value = email || '';
    document.getElementById('modal_user_role').value = role || 'editor';
    document.getElementById('modal_user_password').value = '';

    const avatarInput = document.querySelector('#user-modal input[name="avatar"]');
    if (avatarInput) {
        avatarInput.value = avatar || '';
        avatarInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    if (id > 0) {
        document.getElementById('user-modal-title').textContent = 'Edit Staff User';
        document.getElementById('modal_password_label').textContent = 'New Password (Optional)';
        document.getElementById('modal_user_password').removeAttribute('required');
    } else {
        document.getElementById('user-modal-title').textContent = 'Add Staff User Directly';
        document.getElementById('modal_password_label').textContent = 'Account Password *';
        document.getElementById('modal_user_password').setAttribute('required', 'required');
    }

    document.getElementById('user-modal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

function copyCustomText(text, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span><span>Copied!</span>';
            setTimeout(() => btn.innerHTML = original, 2000);
        });
    }
}

function copyToClip(elementId, btn) {
    const text = document.getElementById(elementId).textContent.trim();
    copyCustomText(text, btn);
}

function toggleSecretVisibility() {
    const input = document.getElementById('google_oauth_client_secret');
    const icon = document.getElementById('secret_eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
