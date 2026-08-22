<?php
/**
 * Indra Hotel - Email & SMTP Server Configuration CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

// Strict Role Enforcement: Administrator privileges required
Auth::requireAdmin();

$pdo = getDB();
$adminTitle = 'Email & SMTP Configuration';

// Handle POST Requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/email-settings.php');
        exit;
    }

    // 1. Handle Clear Outbox Activity Log
    if (isset($_POST['clear_email_logs'])) {
        Mailer::clearLogs();
        set_flash('success', 'Outbound email activity log has been cleared successfully.');
        header('Location: ' . BASE_URL . '/admin/email-settings.php');
        exit;
    }

    // 2. Handle Toggle Activity Logging (Standalone)
    if (isset($_POST['toggle_mail_log'])) {
        $newLogState = ($_POST['mail_log_activity'] ?? '0') === '1' ? '1' : '0';
        set_setting('mail_log_activity', $newLogState);
        get_setting('__refresh__');
        set_flash('success', $newLogState === '1' ? 'Email activity logging has been enabled.' : 'Email activity logging has been disabled.');
        header('Location: ' . BASE_URL . '/admin/email-settings.php');
        exit;
    }

    // 3. Handle Save Email Configuration Form
    if (isset($_POST['save_email_settings'])) {
        $emailSettings = [
            'mail_driver' => $_POST['mail_driver'] ?? 'mail',
            'mail_host' => trim($_POST['mail_host'] ?? ''),
            'mail_port' => trim($_POST['mail_port'] ?? '587'),
            'mail_encryption' => $_POST['mail_encryption'] ?? 'tls',
            'mail_username' => trim($_POST['mail_username'] ?? ''),
            'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
            'mail_from_name' => trim($_POST['mail_from_name'] ?? ''),
            'mail_reply_to' => trim($_POST['mail_reply_to'] ?? ''),
            'mail_notification_email' => trim($_POST['mail_notification_email'] ?? ''),
            'booking_receiver_email' => trim($_POST['booking_receiver_email'] ?? ''),
            'booking_sales_email' => trim($_POST['booking_sales_email'] ?? ''),
            'booking_management_email' => trim($_POST['booking_management_email'] ?? ''),
            'booking_other_emails' => trim($_POST['booking_other_emails'] ?? ''),
            'contact_receiver_email' => trim($_POST['contact_receiver_email'] ?? ''),
            'contact_sales_email' => trim($_POST['contact_sales_email'] ?? ''),
            'contact_management_email' => trim($_POST['contact_management_email'] ?? ''),
            'contact_other_emails' => trim($_POST['contact_other_emails'] ?? ''),
            'contact_auto_reply' => isset($_POST['contact_auto_reply']) ? '1' : '0'
        ];

        // Only update password if a new one is typed
        if (!empty($_POST['mail_password'])) {
            $emailSettings['mail_password'] = trim($_POST['mail_password']);
        }

        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($emailSettings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Refresh memory cache
        get_setting('__refresh__');
        set_flash('success', 'Email delivery, multi-department routing, and notification settings saved successfully.');
        header('Location: ' . BASE_URL . '/admin/email-settings.php');
        exit;
    }
}

// Current Values
$mailDriver = get_setting('mail_driver', 'mail');
$mailHost = get_setting('mail_host', 'smtp.gmail.com');
$mailPort = get_setting('mail_port', '587');
$mailEncryption = get_setting('mail_encryption', 'tls');
$mailUsername = get_setting('mail_username', '');
$mailPassword = get_setting('mail_password', '');
$mailFromAddress = get_setting('mail_from_address', hotel_email() ?: '');
$mailFromName = get_setting('mail_from_name', hotel_name() ?: '');
$mailReplyTo = get_setting('mail_reply_to', hotel_email() ?: '');
$mailNotificationEmail = get_setting('mail_notification_email', hotel_email() ?: '');
$bookingReceiverEmail = get_setting('booking_receiver_email', hotel_email() ?: '');
$bookingSalesEmail = get_setting('booking_sales_email', '');
$bookingManagementEmail = get_setting('booking_management_email', '');
$bookingOtherEmails = get_setting('booking_other_emails', '');
$contactReceiverEmail = get_setting('contact_receiver_email', hotel_email() ?: '');
$contactSalesEmail = get_setting('contact_sales_email', '');
$contactManagementEmail = get_setting('contact_management_email', '');
$contactOtherEmails = get_setting('contact_other_emails', '');
$contactAutoReply = get_setting('contact_auto_reply', '1');
$mailLogActivity = get_setting('mail_log_activity', '1');

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8 max-w-5xl">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">System & Delivery</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Email & SMTP Configuration</h1>
            <p class="text-xs text-stone-500 mt-1">Configure transactional email servers for booking receipts, inquiries, and staff notifications.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-stone-500">Active Protocol:</span>
            <?php if ($mailDriver === 'smtp'): ?>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-[#343c0a] text-white">
                    <span class="material-symbols-outlined text-sm">mark_email_read</span>
                    <span>SMTP Client</span>
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-stone-200 text-stone-800">
                    <span class="material-symbols-outlined text-sm">mail</span>
                    <span>PHP mail()</span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick SMTP Service Presets Bar -->
    <div class="bg-stone-50 p-4 sm:p-5 rounded-2xl border border-stone-200 space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-stone-700">
                <span class="material-symbols-outlined text-[#343c0a] text-lg">bolt</span>
                <span>1-Click Popular Provider Presets</span>
            </div>
            <span class="text-[11px] text-stone-400">Click to autofill recommended Host, Port & Encryption</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
            <button type="button" onclick="applySmtpPreset('smtp.gmail.com', '587', 'tls')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-rose-600">G</span> Gmail (Google Workspace)
            </button>
            <button type="button" onclick="applySmtpPreset('smtp.office365.com', '587', 'tls')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-blue-600">O</span> Microsoft 365 / Outlook
            </button>
            <button type="button" onclick="applySmtpPreset('smtp.sendgrid.net', '587', 'tls')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-indigo-600">S</span> SendGrid
            </button>
            <button type="button" onclick="applySmtpPreset('smtp.mailgun.org', '587', 'tls')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-amber-600">M</span> Mailgun
            </button>
            <button type="button" onclick="applySmtpPreset('email-smtp.us-east-1.amazonaws.com', '587', 'tls')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-orange-600">A</span> Amazon SES
            </button>
            <button type="button" onclick="applySmtpPreset('mail.yourdomain.com', '465', 'ssl')" class="px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-200 shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                <span class="font-bold text-[#343c0a]">C</span> Custom Domain / cPanel SSL
            </button>
        </div>
    </div>

    <!-- Delivery Health Diagnostic Banner -->
    <?php if ($mailDriver === 'mail'): ?>
    <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 flex items-start gap-3.5 shadow-2xs">
        <div class="w-9 h-9 rounded-xl bg-amber-200/70 text-amber-900 flex items-center justify-center shrink-0 mt-0.5">
            <span class="material-symbols-outlined text-xl">warning</span>
        </div>
        <div class="text-xs space-y-1">
            <h4 class="font-bold text-amber-950 text-sm flex items-center gap-1.5">
                <span>Why Emails Might Not Be Received (Native PHP mail active)</span>
            </h4>
            <p class="text-amber-900/90 leading-relaxed">
                You are currently using <strong>Native PHP mail()</strong>. On local development environments (macOS/Windows) and cloud servers without a running Postfix/Sendmail service, PHP cannot route emails directly to recipient mailboxes (Gmail, Yahoo, Outlook).
            </p>
            <p class="text-amber-900 font-semibold pt-1">
                💡 <strong>Solution:</strong> Select <strong>"SMTP Server (Recommended)"</strong> below, click one of the <strong>1-Click Presets</strong> (e.g. Gmail, Outlook, Brevo, or Custom cPanel Webmail), enter your credentials, and click <em>Save Email Configuration</em>.
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 flex items-start gap-3.5 shadow-2xs">
        <div class="w-9 h-9 rounded-xl bg-emerald-200/70 text-emerald-900 flex items-center justify-center shrink-0 mt-0.5">
            <span class="material-symbols-outlined text-xl">verified</span>
        </div>
        <div class="text-xs space-y-1">
            <h4 class="font-bold text-emerald-950 text-sm">SMTP Server Protocol Active</h4>
            <p class="text-emerald-900/90 leading-relaxed">
                Configured to dispatch via <strong><?= e($mailHost) ?>:<?= e($mailPort) ?></strong> (<?= strtoupper(e($mailEncryption ?: 'NONE')) ?>). All booking alerts, guest confirmations, and contact inquiries are routed through this gateway.
            </p>
            <p class="text-emerald-900 font-semibold pt-0.5">
                Tip: Use the <strong>Live Connection Diagnostic Tester</strong> at the bottom of the page to verify your credentials.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Configuration Form -->
    <form action="<?= BASE_URL ?>/admin/email-settings.php" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
        <input type="hidden" name="save_email_settings" value="1">

        <!-- 1. Delivery Protocol & Driver Selection -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">dns</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Mail Dispatch Method</h2>
                    <p class="text-xs text-stone-500">Choose how outgoing transactional emails are transmitted.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition <?= $mailDriver === 'smtp' ? 'border-[#343c0a] bg-stone-50' : 'border-stone-200 hover:border-stone-300' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-sm text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#343c0a]">mark_email_read</span>
                            <span>SMTP Server (Recommended)</span>
                        </span>
                        <input type="radio" name="mail_driver" value="smtp" <?= $mailDriver === 'smtp' ? 'checked' : '' ?> onchange="toggleDriverFields('smtp')" class="text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500">Connect to an authenticated SMTP mail server (Gmail, SendGrid, Amazon SES, or private email hosting). Ensures optimal inbox deliverability.</p>
                </label>

                <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition <?= $mailDriver === 'mail' ? 'border-[#343c0a] bg-stone-50' : 'border-stone-200 hover:border-stone-300' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-sm text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-stone-600">mail</span>
                            <span>Native PHP mail()</span>
                        </span>
                        <input type="radio" name="mail_driver" value="mail" <?= $mailDriver === 'mail' ? 'checked' : '' ?> onchange="toggleDriverFields('mail')" class="text-[#343c0a] focus:ring-[#343c0a]">
                    </div>
                    <p class="text-xs text-stone-500">Uses the host server's local sendmail/postfix service. Does not require SMTP credentials, but may land in spam on shared servers.</p>
                </label>
            </div>
        </div>

        <!-- 2. SMTP Server Credentials -->
        <div id="smtp-credentials-card" class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6 <?= $mailDriver === 'mail' ? 'opacity-50 pointer-events-none' : '' ?>">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">key</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">SMTP Server Credentials</h2>
                    <p class="text-xs text-stone-500">Host, port, encryption method, and authentication details.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">SMTP Host Address *</label>
                    <input type="text" name="mail_host" id="input_mail_host" value="<?= e($mailHost) ?>" placeholder="e.g. smtp.gmail.com"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">SMTP Port *</label>
                    <input type="number" name="mail_port" id="input_mail_port" value="<?= e($mailPort) ?>" placeholder="587"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Encryption Protocol</label>
                    <select name="mail_encryption" id="input_mail_encryption" class="w-full text-sm font-medium border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <option value="tls" <?= $mailEncryption === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Port 587 - Standard)</option>
                        <option value="ssl" <?= $mailEncryption === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (Port 465)</option>
                        <option value="" <?= empty($mailEncryption) ? 'selected' : '' ?>>None / Plain (Port 25)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">SMTP Username / Email</label>
                    <input type="text" name="mail_username" id="input_mail_username" value="<?= e($mailUsername) ?>" placeholder="e.g. your_email@domain.com"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">SMTP Password / App Key</label>
                    <div class="relative">
                        <input type="password" name="mail_password" id="input_mail_password" value="<?= !empty($mailPassword) ? '********' : '' ?>" placeholder="••••••••"
                               class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 pr-10 focus:ring-2 focus:ring-[#343c0a]">
                        <button type="button" onclick="togglePasswordVisibility('input_mail_password')" class="absolute right-2.5 top-2.5 text-stone-400 hover:text-stone-700">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Sender Identity & Automated Routing Inboxes -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">person_pin</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Sender Identity & Notification Inboxes</h2>
                    <p class="text-xs text-stone-500">Configure outbound email identity and inboxes that receive booking alerts and guest inquiries.</p>
                </div>
            </div>

            <!-- Outbound Identity -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pb-6 border-b border-stone-100">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">From Sender Email Address *</label>
                    <input type="email" name="mail_from_address" required value="<?= e($mailFromAddress) ?>" placeholder="reservations@yourdomain.com"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">The email address guests see as the sender.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">From Sender Name *</label>
                    <input type="text" name="mail_from_name" required value="<?= e($mailFromName) ?>" placeholder="Hotel Reservations"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Display name shown in the guest's inbox.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Reply-To Email Address</label>
                    <input type="email" name="mail_reply_to" value="<?= e($mailReplyTo) ?>" placeholder="reception@yourdomain.com"
                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Where direct guest replies will be delivered.</p>
                </div>
            </div>

            <!-- Inbound Routing Inboxes -->
            <div class="space-y-6 pt-2">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <div>
                        <h3 class="font-headline font-bold text-sm text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg">forward_to_inbox</span>
                            <span>Hotel Department Inboxes (New Booking Alerts)</span>
                        </h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Configure which departments and setup email addresses receive copies of every guest booking.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 1. Primary Booking Notification Receiver -->
                    <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded bg-[#343c0a] text-white flex items-center justify-center text-xs">
                                <span class="material-symbols-outlined text-sm">hotel</span>
                            </div>
                            <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Reservations & Front Desk *</label>
                        </div>
                        <input type="text" name="booking_receiver_email" value="<?= e($bookingReceiverEmail) ?>" placeholder="reservations@yourdomain.com, frontdesk@yourdomain.com"
                               class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <p class="text-[11px] text-stone-500">Primary inboxes for front desk check-in, key management, and room dispatch.</p>
                    </div>

                    <!-- 2. Sales & Commercial Team -->
                    <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded bg-emerald-700 text-white flex items-center justify-center text-xs">
                                <span class="material-symbols-outlined text-sm">payments</span>
                            </div>
                            <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Sales & Commercial Team</label>
                        </div>
                        <input type="text" name="booking_sales_email" value="<?= e($bookingSalesEmail) ?>" placeholder="sales@yourdomain.com, revenue@yourdomain.com"
                               class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <p class="text-[11px] text-stone-500">Sales and revenue team inboxes to track corporate bookings, room revenue, and upsells.</p>
                    </div>

                    <!-- 3. Management / General Manager -->
                    <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded bg-indigo-700 text-white flex items-center justify-center text-xs">
                                <span class="material-symbols-outlined text-sm">shield_person</span>
                            </div>
                            <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Management / GM / Owners</label>
                        </div>
                        <input type="text" name="booking_management_email" value="<?= e($bookingManagementEmail) ?>" placeholder="gm@yourdomain.com, manager@yourdomain.com"
                                class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <p class="text-[11px] text-stone-500">Hotel general management, duty managers, and owner oversight inboxes.</p>
                    </div>

                    <!-- 4. Other Custom Setup Alert Inboxes -->
                    <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded bg-stone-700 text-white flex items-center justify-center text-xs">
                                <span class="material-symbols-outlined text-sm">alternate_email</span>
                            </div>
                            <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Other Setup Inboxes (Accounting, Concierge)</label>
                        </div>
                        <input type="text" name="booking_other_emails" value="<?= e($bookingOtherEmails) ?>" placeholder="concierge@yourdomain.com, accounting@yourdomain.com"
                               class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                        <p class="text-[11px] text-stone-500">Any additional custom setup inboxes (comma-separated).</p>
                    </div>
                </div>

                <!-- Contact Us Section -->
                <div class="pt-6 border-t border-stone-200/80 space-y-6">
                    <div>
                        <h3 class="font-headline font-bold text-sm text-onyx-charcoal flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#4B5320] text-lg">contact_mail</span>
                            <span>Hotel Department Inboxes (Contact Us Inquiries)</span>
                        </h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Configure which department inboxes receive notifications when visitors submit messages via the Contact Us form.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- 1. Primary Contact Receiver -->
                        <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-[#343c0a] text-white flex items-center justify-center text-xs">
                                    <span class="material-symbols-outlined text-sm">support_agent</span>
                                </div>
                                <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">General Inquiries & Concierge *</label>
                            </div>
                            <input type="text" name="contact_receiver_email" value="<?= e($contactReceiverEmail) ?>" placeholder="info@yourdomain.com, general@yourdomain.com"
                                   class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            <p class="text-[11px] text-stone-500">Main reception and customer relations team inboxes.</p>
                        </div>

                        <!-- 2. Sales & Events Team -->
                        <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-emerald-700 text-white flex items-center justify-center text-xs">
                                    <span class="material-symbols-outlined text-sm">celebration</span>
                                </div>
                                <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Sales & Event Inquiries</label>
                            </div>
                            <input type="text" name="contact_sales_email" value="<?= e($contactSalesEmail) ?>" placeholder="sales@yourdomain.com, events@yourdomain.com"
                                   class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            <p class="text-[11px] text-stone-500">Sales, banquet, wedding, and group meeting inquiry inboxes.</p>
                        </div>

                        <!-- 3. Management / GM Escalations -->
                        <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-indigo-700 text-white flex items-center justify-center text-xs">
                                    <span class="material-symbols-outlined text-sm">supervisor_account</span>
                                </div>
                                <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Management & GM Oversight</label>
                            </div>
                            <input type="text" name="contact_management_email" value="<?= e($contactManagementEmail) ?>" placeholder="gm@yourdomain.com, director@yourdomain.com"
                                   class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            <p class="text-[11px] text-stone-500">Executive management and VIP inquiry routing inboxes.</p>
                        </div>

                        <!-- 4. Other Custom Setup Inboxes -->
                        <div class="bg-stone-50 p-5 rounded-xl border border-stone-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-stone-700 text-white flex items-center justify-center text-xs">
                                    <span class="material-symbols-outlined text-sm">mail_lock</span>
                                </div>
                                <label class="block text-xs font-bold text-stone-900 uppercase tracking-wider">Other Custom Setup Inboxes</label>
                            </div>
                            <input type="text" name="contact_other_emails" value="<?= e($contactOtherEmails) ?>" placeholder="feedback@yourdomain.com, custom@yourdomain.com"
                                   class="w-full text-sm bg-white border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            <p class="text-[11px] text-stone-500">Any additional custom setup inboxes for message alerts (comma-separated).</p>
                        </div>
                    </div>

                    <!-- Contact Auto-Reply Toggle -->
                    <div class="flex items-center justify-between p-4 bg-stone-50 rounded-xl border border-stone-200">
                        <div>
                            <div class="text-xs font-bold text-stone-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm text-[#4B5320]">mark_email_read</span>
                                <span>Enable Courtesy Auto-Reply to Contact Form Submitters</span>
                            </div>
                            <p class="text-[11px] text-stone-500 mt-0.5">Automatically send a polite confirmation email to the guest's email address confirming their message was received.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                            <input type="checkbox" name="contact_auto_reply" value="1" <?= $contactAutoReply === '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#343c0a]"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow cursor-pointer transition">
                Save Email Configuration
            </button>
        </div>
    </form>

    <!-- ============================================== -->
    <!-- 4. Interactive Live Test Email Dispatcher       -->
    <!-- ============================================== -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-4">
        <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">send</span>
            </div>
            <div>
                <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Live Connection Diagnostic Tester</h2>
                <p class="text-xs text-stone-500">Send an instant test email to verify your SMTP connection and server handshake.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-8 lg:col-span-9">
                <input type="email" id="test_email_recipient" value="<?= e($currentUser['email'] ?? '') ?>" placeholder="Enter test recipient email address..."
                       class="w-full text-xs border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
            </div>
            <div class="sm:col-span-4 lg:col-span-3">
                <button type="button" onclick="sendDiagnosticTestEmail()" id="btn-test-send"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2.5 rounded-lg text-xs font-bold tracking-wide transition shadow flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-base">outgoing_mail</span>
                    <span>Send Test Email</span>
                </button>
            </div>
        </div>

        <!-- Live Response Output -->
        <div id="test-result-box" class="hidden p-4 rounded-xl border text-xs space-y-2">
            <div id="test-result-message" class="font-bold flex items-center gap-2"></div>
            <pre id="test-result-log" class="p-3 bg-stone-900 text-stone-200 rounded-lg text-[11px] font-mono overflow-x-auto max-h-48"></pre>
        </div>
    </div>

    <!-- 5. Recent Outbound Email Activity Logs -->
    <?php $recentLogs = Mailer::getRecentLogs(20); ?>
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">history_toggle_off</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Recent Outbound Email Activity</h2>
                        <?php if ($mailLogActivity === '1'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 tracking-wider">
                                ENABLED
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-stone-200 text-stone-700 tracking-wider">
                                DISABLED
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-stone-500 mt-0.5">Live audit log of all booking alerts, contact inquiries, and guest confirmations.</p>
                </div>
            </div>

            <!-- Action Controls: Swipe Toggle & Clear Logs -->
            <div class="flex items-center gap-3 self-end sm:self-center">
                <?php if ($mailLogActivity === '1' && !empty($recentLogs)): ?>
                <form action="<?= BASE_URL ?>/admin/email-settings.php" method="POST" onsubmit="return confirm('Are you sure you want to clear all outbound email logs?');" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                    <input type="hidden" name="clear_email_logs" value="1">
                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-semibold px-2.5 py-1 rounded-lg border border-rose-200 hover:bg-rose-50 transition flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">delete_sweep</span>
                        <span>Clear Logs</span>
                    </button>
                </form>
                <?php endif; ?>

                <!-- Standalone Swipe Toggle Form -->
                <form id="form-toggle-mail-log" action="<?= BASE_URL ?>/admin/email-settings.php" method="POST" class="inline-flex items-center gap-2 m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                    <input type="hidden" name="toggle_mail_log" value="1">
                    <input type="hidden" name="mail_log_activity" id="input_toggle_mail_log" value="<?= $mailLogActivity === '1' ? '0' : '1' ?>">
                    
                    <span class="text-xs font-semibold text-stone-600">Logging:</span>
                    <button type="submit" 
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $mailLogActivity === '1' ? 'bg-[#343c0a]' : 'bg-stone-300' ?>"
                            role="switch" aria-checked="<?= $mailLogActivity === '1' ? 'true' : 'false' ?>" title="Click to <?= $mailLogActivity === '1' ? 'disable' : 'enable' ?> email activity logging">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out <?= $mailLogActivity === '1' ? 'translate-x-5' : 'translate-x-0' ?> flex items-center justify-center">
                            <?php if ($mailLogActivity === '1'): ?>
                                <span class="text-[9px] font-bold text-[#343c0a]">✓</span>
                            <?php else: ?>
                                <span class="text-[9px] font-bold text-stone-400">✕</span>
                            <?php endif; ?>
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($mailLogActivity === '1'): ?>
            <!-- Active Log Table -->
            <?php if (!empty($recentLogs)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-stone-200 text-stone-400 uppercase tracking-wider text-[10px] font-bold bg-stone-50">
                            <th class="py-2.5 px-3">Date / Time</th>
                            <th class="py-2.5 px-3">Recipient</th>
                            <th class="py-2.5 px-3">Subject</th>
                            <th class="py-2.5 px-3">Driver</th>
                            <th class="py-2.5 px-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 font-medium text-stone-700">
                        <?php foreach ($recentLogs as $logEntry): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-2.5 px-3 text-stone-500 whitespace-nowrap text-[11px]">
                                <?= e(date('M d, Y H:i:s', strtotime($logEntry['created_at'] ?? 'now'))) ?>
                            </td>
                            <td class="py-2.5 px-3 font-semibold text-onyx-charcoal">
                                <?= e($logEntry['recipient']) ?>
                            </td>
                            <td class="py-2.5 px-3 max-w-xs truncate text-stone-600">
                                <?= e($logEntry['subject']) ?>
                            </td>
                            <td class="py-2.5 px-3 uppercase text-[10px] text-stone-400 font-bold">
                                <?= e($logEntry['driver']) ?>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <?php if (($logEntry['status'] ?? '') === 'delivered'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        <span class="material-symbols-outlined text-xs">check_circle</span>
                                        <span>Sent</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800" title="<?= e($logEntry['message'] ?? 'Failed') ?>">
                                        <span class="material-symbols-outlined text-xs">error</span>
                                        <span>Failed</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (($logEntry['status'] ?? '') !== 'delivered' && !empty($logEntry['message'])): ?>
                        <tr class="bg-rose-50/50 text-[11px] text-rose-700">
                            <td colspan="5" class="py-1.5 px-3">
                                <strong>Reason:</strong> <?= e($logEntry['message']) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-8 text-center bg-stone-50 rounded-xl text-stone-400 space-y-2">
                <span class="material-symbols-outlined text-3xl">mark_email_unread</span>
                <p class="text-xs font-semibold">No emails recorded yet. Submit a booking or send a test email to see logs.</p>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Disabled Notice -->
            <div class="p-6 rounded-xl bg-stone-50 border border-stone-200 text-center space-y-2">
                <span class="material-symbols-outlined text-3xl text-stone-400">visibility_off</span>
                <h4 class="font-headline font-bold text-sm text-stone-800">Email Activity Logging is Disabled</h4>
                <p class="text-xs text-stone-500 max-w-md mx-auto">
                    Outbound emails are transmitted directly without saving audit logs to the database. Toggle the switch above anytime to resume activity tracking.
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function submitToggleMailLog() {
    const form = document.getElementById('form-toggle-mail-log');
    if (form) {
        form.submit();
    }
}

function toggleDriverFields(driver) {
    const card = document.getElementById('smtp-credentials-card');
    if (driver === 'smtp') {
        card.classList.remove('opacity-50', 'pointer-events-none');
    } else {
        card.classList.add('opacity-50', 'pointer-events-none');
    }
}

function applySmtpPreset(host, port, enc) {
    document.getElementById('input_mail_host').value = host;
    document.getElementById('input_mail_port').value = port;
    document.getElementById('input_mail_encryption').value = enc;

    // Check the SMTP radio button if not checked
    const radio = document.querySelector('input[name="mail_driver"][value="smtp"]');
    if (radio) {
        radio.checked = true;
        toggleDriverFields('smtp');
    }
}

function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

async function sendDiagnosticTestEmail() {
    const emailInput = document.getElementById('test_email_recipient');
    const recipient = emailInput.value.trim();
    const btn = document.getElementById('btn-test-send');
    const resultBox = document.getElementById('test-result-box');
    const resultMsg = document.getElementById('test-result-message');
    const resultLog = document.getElementById('test-result-log');

    if (!recipient) {
        alert('Please enter a valid recipient email address.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">autorenew</span> <span>Sending...</span>';
    resultBox.classList.add('hidden');

    try {
        const formData = new FormData();
        formData.append('email', recipient);

        const endpointUrl = window.getApiEndpoint ? window.getApiEndpoint('/api/send-test-email.php') : '/api/send-test-email.php';
        const response = await fetch(endpointUrl, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        resultBox.classList.remove('hidden');

        if (data.success) {
            resultBox.className = 'p-4 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-900 text-xs space-y-2';
            resultMsg.innerHTML = '<span class="material-symbols-outlined text-emerald-700">check_circle</span> <span>' + data.message + '</span>';
        } else {
            resultBox.className = 'p-4 rounded-xl border border-rose-300 bg-rose-50 text-rose-900 text-xs space-y-2';
            resultMsg.innerHTML = '<span class="material-symbols-outlined text-rose-700">error</span> <span>' + data.message + '</span>';
        }

        if (data.log) {
            resultLog.textContent = data.log;
            resultLog.classList.remove('hidden');
        } else {
            resultLog.classList.add('hidden');
        }

    } catch (err) {
        resultBox.classList.remove('hidden');
        resultBox.className = 'p-4 rounded-xl border border-rose-300 bg-rose-50 text-rose-900 text-xs space-y-2';
        resultMsg.innerHTML = '<span class="material-symbols-outlined text-rose-700">error</span> <span>Network error: ' + err.message + '</span>';
        resultLog.classList.add('hidden');
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-base">outgoing_mail</span> <span>Send Test Email</span>';
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
