<?php
/**
 * Indra Hotel - Enterprise Email & SMTP Dispatch Engine
 * Supports Pure PHP Socket SMTP (TLS / SSL / Plain) & PHP mail() with Luxury HTML Templates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Mailer {

    /**
     * Send an email with HTML and optional plain text
    /**
     * Main dispatch method - intelligently selects configured mail driver
     *
     * @param string|array $to Single email or ['email' => '...', 'name' => '...']
     * @param string $subject Email subject line
     * @param string $htmlBody Rendered HTML content
     * @param string $plainText Fallback plain text content
     * @param array $cc Array of CC recipient emails
     * @param array $bcc Array of BCC recipient emails
     * @return array ['success' => bool, 'message' => string, 'log' => string]
     */
    public static function send(string|array $to, string $subject, string $htmlBody, string $plainText = '', array $cc = [], array $bcc = []): array {
        $toEmail = is_array($to) ? ($to['email'] ?? ($to[0] ?? '')) : $to;
        $toName = is_array($to) ? ($to['name'] ?? ($to[1] ?? '')) : '';
        $toEmail = trim((string)$toEmail);

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "Invalid recipient email address: '{$toEmail}'", 'log' => ''];
        }

        // Sanitize & dedup CC addresses
        $validCc = [];
        foreach ($cc as $c) {
            $cEmail = is_array($c) ? ($c['email'] ?? ($c[0] ?? '')) : $c;
            $cEmail = trim((string)$cEmail);
            if (filter_var($cEmail, FILTER_VALIDATE_EMAIL) && strtolower($cEmail) !== strtolower($toEmail)) {
                $validCc[] = $cEmail;
            }
        }
        $validCc = array_values(array_unique($validCc));

        // Sanitize & dedup BCC addresses
        $validBcc = [];
        foreach ($bcc as $b) {
            $bEmail = is_array($b) ? ($b['email'] ?? ($b[0] ?? '')) : $b;
            $bEmail = trim((string)$bEmail);
            if (filter_var($bEmail, FILTER_VALIDATE_EMAIL) && strtolower($bEmail) !== strtolower($toEmail) && !in_array(strtolower($bEmail), array_map('strtolower', $validCc))) {
                $validBcc[] = $bEmail;
            }
        }
        $validBcc = array_values(array_unique($validBcc));

        $driver = get_setting('mail_driver', 'mail');
        $fromEmail = get_setting('mail_from_address', hotel_email() ?: 'admin@hotel.com');
        $fromName = get_setting('mail_from_name', hotel_name() ?: 'Hotel Reservations');
        $replyTo = get_setting('mail_reply_to', $fromEmail);

        if (empty($plainText)) {
            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        }

        if ($driver === 'smtp') {
            $res = self::sendViaSmtp($toEmail, $toName, $fromEmail, $fromName, $replyTo, $subject, $htmlBody, $plainText, $validCc, $validBcc);
        } else {
            $res = self::sendViaPhpMail($toEmail, $toName, $fromEmail, $fromName, $replyTo, $subject, $htmlBody, $plainText, $validCc, $validBcc);
        }

        // Build descriptive recipient log string
        $logRecipient = $toEmail;
        if (!empty($validCc)) {
            $logRecipient .= ' (CC: ' . implode(', ', $validCc) . ')';
        }
        if (!empty($validBcc)) {
            $logRecipient .= ' (BCC: ' . implode(', ', $validBcc) . ')';
        }

        // Record in Outbox Activity Log
        self::logDispatch($logRecipient, $subject, $driver, $res['success'] ? 'delivered' : 'failed', $res['message'] ?? '', $res['log'] ?? '');

        return $res;
    }

    /**
     * Log email transmission attempt into database
     */
    public static function logDispatch(string $recipient, string $subject, string $driver, string $status, string $message, string $log = ''): void {
        if (!is_mail_log_enabled()) {
            return;
        }
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO email_logs (recipient, subject, driver, status, message, log) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$recipient, $subject, $driver, $status, $message, $log]);
        } catch (Throwable $e) {
            // Ignore log write failure
        }
    }

    /**
     * Retrieve recent outbound email dispatch logs
     */
    public static function getRecentLogs(int $limit = 20): array {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM email_logs ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Clear all outbound email activity logs from database
     */
    public static function clearLogs(): bool {
        try {
            $pdo = getDB();
            $pdo->exec("DELETE FROM email_logs");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Native PHP mail() dispatcher
     */
    private static function sendViaPhpMail(string $toEmail, string $toName, string $fromEmail, string $fromName, string $replyTo, string $subject, string $htmlBody, string $plainText, array $cc = [], array $bcc = []): array {
        $boundary = "==Multipart_Boundary_x" . md5((string)time()) . "x";

        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: " . self::formatAddress($fromEmail, $fromName);
        $headers[] = "Reply-To: {$replyTo}";
        if (!empty($cc)) {
            $headers[] = "Cc: " . implode(', ', $cc);
        }
        if (!empty($bcc)) {
            $headers[] = "Bcc: " . implode(', ', $bcc);
        }
        $headers[] = "X-Mailer: SoftBook-Indra-Hotel-Mailer/1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $plainText . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";

        $toFormatted = self::formatAddress($toEmail, $toName);
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $sent = @mail($toFormatted, $encodedSubject, $body, implode("\r\n", $headers));

        if ($sent) {
            return ['success' => true, 'message' => 'Email accepted for delivery via PHP mail().', 'log' => 'PHP mail() dispatched successfully.'];
        }

        return ['success' => false, 'message' => 'PHP mail() function returned false. Please configure SMTP in Email Settings.', 'log' => 'mail() failed. Check server sendmail/postfix configuration or switch to SMTP.'];
    }

    /**
     * Pure PHP Socket SMTP Client (RFC 5321 compliant)
     */
    private static function sendViaSmtp(string $toEmail, string $toName, string $fromEmail, string $fromName, string $replyTo, string $subject, string $htmlBody, string $plainText, array $cc = [], array $bcc = []): array {
        $host = trim(get_setting('mail_host', 'localhost'));
        $port = (int)get_setting('mail_port', '587');
        $encryption = strtolower(trim(get_setting('mail_encryption', 'tls')));
        $username = trim(get_setting('mail_username', ''));
        $password = get_setting('mail_password', '');
        $timeout = 15;

        $log = [];
        $log[] = "Connecting to SMTP server {$host}:{$port} (encryption: " . ($encryption ?: 'none') . ")...";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $targetHost = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
        $socket = @stream_socket_client("{$targetHost}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $errorMsg = "Failed to connect to SMTP host {$host}:{$port}. Error: {$errstr} ({$errno})";
            $log[] = $errorMsg;
            return ['success' => false, 'message' => $errorMsg, 'log' => implode("\n", $log)];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket, &$log) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (preg_match('/^\d{3}( |$)/', $str) || (strlen($str) >= 4 && $str[3] === ' ')) {
                    break;
                }
            }
            $log[] = "< " . trim($data);
            return $data;
        };

        $sendCommand = function(string $cmd) use ($socket, &$log, $readResponse) {
            $log[] = "> " . (preg_match('/^(AUTH PLAIN|PASS|[a-zA-Z0-9+\/]{20,})/i', $cmd) ? '[CREDENTIAL_DATA]' : $cmd);
            fputs($socket, $cmd . "\r\n");
            return $readResponse();
        };

        $resp = $readResponse();
        if (substr($resp, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => "Unexpected greeting from SMTP server: {$resp}", 'log' => implode("\n", $log)];
        }

        // Send EHLO
        $heloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $ehloResp = $sendCommand("EHLO {$heloHost}");

        // Handle STARTTLS for TLS encryption
        if ($encryption === 'tls') {
            $log[] = "Initiating STARTTLS handshake...";
            $tlsResp = $sendCommand("STARTTLS");
            if (substr($tlsResp, 0, 3) !== '220') {
                fclose($socket);
                return ['success' => false, 'message' => "STARTTLS rejected by {$host}: {$tlsResp}", 'log' => implode("\n", $log)];
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                fclose($socket);
                return ['success' => false, 'message' => "TLS encryption handshake failed on {$host}.", 'log' => implode("\n", $log)];
            }
            $log[] = "TLS encryption established successfully.";
            // Re-send EHLO after TLS
            $sendCommand("EHLO {$heloHost}");
        }

        // Authentication
        if (!empty($username)) {
            $authResp = $sendCommand("AUTH LOGIN");
            if (substr($authResp, 0, 3) !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "AUTH LOGIN rejected by {$host}: {$authResp}", 'log' => implode("\n", $log)];
            }

            $userResp = $sendCommand(base64_encode($username));
            if (substr($userResp, 0, 3) !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP Username rejected: {$userResp}", 'log' => implode("\n", $log)];
            }

            $passResp = $sendCommand(base64_encode($password));
            if (substr($passResp, 0, 3) !== '235') {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP Authentication failed. Please check username/password or app password. Server responded: {$passResp}", 'log' => implode("\n", $log)];
            }
            $log[] = "SMTP Authentication succeeded.";
        }

        // Envelope Recipients: Combine To, CC, and BCC
        $allEnvelopeRcpt = array_values(array_unique(array_merge([$toEmail], $cc, $bcc)));

        // MAIL FROM
        $mailFromResp = $sendCommand("MAIL FROM:<{$fromEmail}>");
        if (substr($mailFromResp, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => "MAIL FROM rejected for <{$fromEmail}>: {$mailFromResp}", 'log' => implode("\n", $log)];
        }

        // Issue RCPT TO for each recipient in the envelope
        $acceptedCount = 0;
        foreach ($allEnvelopeRcpt as $rcptEmail) {
            $rcptResp = $sendCommand("RCPT TO:<{$rcptEmail}>");
            if (substr($rcptResp, 0, 3) === '250' || substr($rcptResp, 0, 3) === '251') {
                $acceptedCount++;
            } else {
                $log[] = "Warning: RCPT TO rejected for <{$rcptEmail}>: {$rcptResp}";
            }
        }

        if ($acceptedCount === 0) {
            fclose($socket);
            return ['success' => false, 'message' => "All recipient addresses were rejected by the SMTP server.", 'log' => implode("\n", $log)];
        }

        // DATA
        $dataResp = $sendCommand("DATA");
        if (substr($dataResp, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'message' => "DATA command rejected: {$dataResp}", 'log' => implode("\n", $log)];
        }

        // Construct MIME Message
        $boundary = "==Multipart_Boundary_x" . md5((string)time()) . "x";
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $toHeader = self::formatAddress($toEmail, $toName);
        $fromHeader = self::formatAddress($fromEmail, $fromName);

        $msg = "Date: " . date('r') . "\r\n";
        $msg .= "To: {$toHeader}\r\n";
        if (!empty($cc)) {
            $msg .= "Cc: " . implode(', ', $cc) . "\r\n";
        }
        $msg .= "From: {$fromHeader}\r\n";
        $msg .= "Reply-To: {$replyTo}\r\n";
        $msg .= "Subject: {$encodedSubject}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "X-Mailer: SoftBook-Indra-Hotel-SMTP/1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $plainText . "\r\n\r\n";

        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $htmlBody . "\r\n\r\n";
        $msg .= "--{$boundary}--\r\n";

        // Dot-stuffing according to RFC 5321
        $msgStuffed = preg_replace('/^\./m', '..', $msg);
        $msgStuffed .= "\r\n.";

        $sendResp = $sendCommand($msgStuffed);
        $sendCommand("QUIT");
        fclose($socket);

        if (substr($sendResp, 0, 3) === '250') {
            return [
                'success' => true,
                'message' => 'Email successfully transmitted via SMTP server to ' . $acceptedCount . ' recipient(s).',
                'log' => implode("\n", $log)
            ];
        }

        return [
            'success' => false,
            'message' => "Message rejected by SMTP server: {$sendResp}",
            'log' => implode("\n", $log)
        ];
    }

    /**
     * Helper to format RFC 822 email address
     */
    private static function formatAddress(string $email, string $name = ''): string {
        if (empty($name)) {
            return $email;
        }
        $encodedName = '=?UTF-8?B?' . base64_encode($name) . '?=';
        return "{$encodedName} <{$email}>";
    }

    /**
     * Render Luxury Hotel Branded HTML Email Template
     */
    public static function renderTemplate(string $headline, string $contentHtml, array $button = []): string {
        $hotelName = hotel_name();
        $hotelTagline = hotel_tagline();
        $hotelPhone = hotel_phone();
        $hotelEmail = hotel_email();
        $hotelAddress = hotel_address();
        $logoUrl = hotel_logo_url();
        $brandAccent = hotel_brand_color('accent');
        $brandHighlight = hotel_brand_color('highlight');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($headline) ?></title>
<style>
    body { margin: 0; padding: 0; background-color: #f6f6f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
    .wrapper { width: 100%; table-layout: fixed; background-color: #f6f6f5; padding: 40px 0; }
    .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-radius: 12px; overflow: hidden; border: 1px solid #e7e5e4; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .header { background-color: #191c1d; padding: 32px 30px; text-align: center; color: #ffffff; }
    .logo-text { font-size: 22px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #dfe8a6; margin: 0; }
    .tagline-text { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #a8a29e; margin-top: 6px; }
    .content { padding: 36px 32px; color: #292524; line-height: 1.6; font-size: 14px; }
    .headline { font-size: 22px; font-weight: 700; color: #1c1917; margin: 0 0 16px 0; }
    .btn { display: inline-block; padding: 14px 28px; background-color: #343c0a; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 13px; letter-spacing: 1px; text-transform: uppercase; border-radius: 6px; margin: 20px 0; }
    .footer { background-color: #f5f5f4; padding: 24px 32px; text-align: center; font-size: 11px; color: #78716c; border-top: 1px solid #e7e5e4; }
    .footer a { color: #343c0a; text-decoration: none; font-weight: 600; }
    .card { background-color: #fafaf9; border: 1px solid #e7e5e4; border-radius: 8px; padding: 18px; margin: 20px 0; }
</style>
</head>
<body>
<div class="wrapper">
    <table class="main" align="center" cellpadding="0" cellspacing="0">
        <!-- Header -->
        <tr>
            <td class="header">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= e($logoUrl) ?>" alt="<?= e($hotelName) ?>" style="max-height: 48px; max-width: 200px; margin-bottom: 8px;">
                <?php else: ?>
                    <h1 class="logo-text"><?= e($hotelName) ?></h1>
                <?php endif; ?>
                <div class="tagline-text"><?= e($hotelTagline) ?></div>
            </td>
        </tr>

        <!-- Body Content -->
        <tr>
            <td class="content">
                <h2 class="headline"><?= e($headline) ?></h2>
                <div>
                    <?= $contentHtml ?>
                </div>
                <?php if (!empty($button) && !empty($button['url'])): ?>
                    <div style="text-align: center; margin-top: 25px;">
                        <a href="<?= e($button['url']) ?>" class="btn"><?= e($button['label'] ?? 'View Details') ?></a>
                    </div>
                <?php endif; ?>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td class="footer">
                <p style="margin: 0 0 6px 0;"><strong><?= e($hotelName) ?></strong> • <?= e($hotelAddress) ?></p>
                <p style="margin: 0 0 10px 0;">Phone: <a href="tel:<?= e($hotelPhone) ?>"><?= e($hotelPhone) ?></a> | Email: <a href="mailto:<?= e($hotelEmail) ?>"><?= e($hotelEmail) ?></a></p>
                <p style="margin: 0; color: #a8a29e;">&copy; <?= date('Y') ?> <?= e($hotelName) ?>. All rights reserved.</p>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send Transactional Booking Confirmation Email to Guest
     */
    public static function sendBookingConfirmation(array $booking, array $room): array {
        $guestName = $booking['guest_name'] ?? 'Valued Guest';
        $guestEmail = $booking['guest_email'] ?? '';
        $ref = $booking['booking_reference'] ?? 'IND-CONFIRMED';
        $roomName = $room['name'] ?? 'Luxury Room';
        $checkIn = format_date($booking['check_in_date']);
        $checkOut = format_date($booking['check_out_date']);
        $nights = calculate_nights($booking['check_in_date'], $booking['check_out_date']);
        $totalPrice = format_price($booking['total_price']);
        
        $promoCode = $booking['promo_code'] ?? '';
        $offerTitle = $booking['offer_title'] ?? '';
        $discountAmount = (float)($booking['discount_amount'] ?? 0);
        $roomRate = (float)($booking['room_rate'] ?? ($room['price_per_night'] ?? 0));
        $subtotal = $roomRate * $nights;
        $isOffer = !empty($offerTitle) || !empty($promoCode) || ($discountAmount > 0);

        if ($isOffer) {
            if (empty($offerTitle)) {
                $offerTitle = !empty($promoCode) ? "Special Promo Package ({$promoCode})" : "Exclusive Promotional Package";
            }
            
            // SPECIAL OFFER CONFIRMATION TEMPLATE FOR GUEST
            $headline = "Special Offer Package Confirmed: #{$ref}";
            $subject = "🎁 Special Offer Package Confirmation - #{$ref} [{$offerTitle}] | " . hotel_name();

            $body = "
                <p>Dear <strong>" . e($guestName) . "</strong>,</p>
                <p>Thank you for choosing <strong>" . e(hotel_name()) . "</strong>! We are delighted to confirm your special promotional package reservation.</p>

                <!-- Prominent Special Offer Highlight Banner -->
                <div style=\"background: linear-gradient(135deg, #343c0a 0%, #4a5416 100%); color: #ffffff; border-radius: 10px; padding: 18px 20px; margin: 20px 0; border: 1px solid #282e07; box-shadow: 0 4px 12px rgba(52, 60, 10, 0.15);\">
                    <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #dfe8a6;\">
                        ★ SPECIAL PROMOTIONAL PACKAGE
                    </div>
                    <div style=\"font-size: 18px; font-weight: 800; margin-top: 4px; color: #ffffff;\">
                        " . e($offerTitle) . "
                    </div>
                    <div style=\"margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 12px; color: #f5f5f4;\">
                        " . (!empty($promoCode) ? "<strong>Promo Code:</strong> <span style=\"font-family: monospace; background: rgba(255,255,255,0.2); padding: 2px 7px; border-radius: 4px; font-weight: bold; color: #dfe8a6;\">" . e($promoCode) . "</span>" : "") . "
                        " . ($discountAmount > 0 ? " • <strong>Promotional Savings:</strong> <span style=\"color: #86efac; font-weight: bold;\">-" . e(format_price($discountAmount)) . "</span>" : "") . "
                    </div>
                </div>

                <div class=\"card\">
                    <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #78716c; margin-bottom: 10px; border-bottom: 1px solid #e7e5e4; padding-bottom: 6px;\">
                        Reservation & Package Details
                    </div>
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr><td style=\"padding: 6px 0; color: #78716c; width: 35%;\">Booking Reference:</td><td style=\"padding: 6px 0; font-weight: bold; font-family: monospace; color: #343c0a; font-size: 15px;\">" . e($ref) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Package Name:</td><td style=\"padding: 6px 0; font-weight: bold; color: #343c0a;\">" . e($offerTitle) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Accommodation:</td><td style=\"padding: 6px 0; font-weight: bold;\">" . e($roomName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Check-in Date:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($checkIn) . " (From 14:00)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Check-out Date:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($checkOut) . " (Until 12:00)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Duration of Stay:</td><td style=\"padding: 6px 0;\">" . $nights . " Night(s)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guests:</td><td style=\"padding: 6px 0;\">" . (int)$booking['adults'] . " Adults, " . (int)$booking['children'] . " Children</td></tr>
                        " . ($discountAmount > 0 ? "
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Standard Subtotal:</td><td style=\"padding: 6px 0; text-decoration: line-through; color: #a8a29e;\">" . e(format_price($subtotal)) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #15803d; font-weight: 600;\">Special Offer Discount:</td><td style=\"padding: 6px 0; color: #15803d; font-weight: 600;\">-" . e(format_price($discountAmount)) . "</td></tr>
                        " : "") . "
                        <tr><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; color: #1c1917; font-weight: bold;\">Total Amount:</td><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; font-weight: bold; color: #343c0a; font-size: 16px;\">" . e($totalPrice) . "</td></tr>
                    </table>
                </div>

                <p>All special privileges and inclusions corresponding to your promotional package will be prepared for your arrival. If you require airport transfers or personalized concierge arrangements, please reply to this email or reach our 24/7 reception desk.</p>
                <p>We look forward to welcoming you to " . e(hotel_name()) . ".</p>
            ";

            $html = self::renderTemplate($headline, $body, [
                'label' => 'View Your Special Offer Reservation',
                'url' => BASE_URL . "/my-booking.php?reference=" . urlencode($ref)
            ]);

            return self::send(['email' => $guestEmail, 'name' => $guestName], $subject, $html);
        } else {
            // STANDARD DIRECT RESERVATION CONFIRMATION TEMPLATE FOR GUEST
            $headline = "Reservation Confirmed: #{$ref}";
            $subject = "Booking Confirmation - #{$ref} | " . hotel_name();

            $body = "
                <p>Dear <strong>" . e($guestName) . "</strong>,</p>
                <p>Thank you for choosing <strong>" . e(hotel_name()) . "</strong>. We are delighted to confirm your upcoming reservation.</p>

                <div style=\"display: inline-block; background-color: #f5f5f4; color: #57534e; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;\">
                    🌐 Direct Standard Website Booking
                </div>
                
                <div class=\"card\">
                    <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #78716c; margin-bottom: 10px; border-bottom: 1px solid #e7e5e4; padding-bottom: 6px;\">
                        Reservation Summary
                    </div>
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr><td style=\"padding: 6px 0; color: #78716c; width: 35%;\">Booking Reference:</td><td style=\"padding: 6px 0; font-weight: bold; font-family: monospace; color: #343c0a; font-size: 15px;\">" . e($ref) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Accommodation:</td><td style=\"padding: 6px 0; font-weight: bold;\">" . e($roomName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Check-in Date:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($checkIn) . " (From 14:00)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Check-out Date:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($checkOut) . " (Until 12:00)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Duration of Stay:</td><td style=\"padding: 6px 0;\">" . $nights . " Night(s)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guests:</td><td style=\"padding: 6px 0;\">" . (int)$booking['adults'] . " Adults, " . (int)$booking['children'] . " Children</td></tr>
                        <tr><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; color: #1c1917; font-weight: bold;\">Total Amount:</td><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; font-weight: bold; color: #343c0a; font-size: 16px;\">" . e($totalPrice) . "</td></tr>
                    </table>
                </div>

                <p>If you require airport transfers, early check-in, or personalized concierge arrangements, please reply to this email or reach our 24/7 reception desk.</p>
                <p>We look forward to welcoming you to Phnom Penh.</p>
            ";

            $html = self::renderTemplate($headline, $body, [
                'label' => 'View Your Reservation Online',
                'url' => BASE_URL . "/my-booking.php?reference=" . urlencode($ref)
            ]);

            return self::send(['email' => $guestEmail, 'name' => $guestName], $subject, $html);
        }
    }

    /**
     * Send Immediate Booking Notification to Hotel Reservations, Sales Team & Setup Inboxes
     */
    public static function sendBookingNotificationToHotel(array $booking, array $room): array {
        $receivers = get_booking_all_notification_emails();
        if (empty($receivers)) {
            return ['success' => false, 'message' => 'No booking receiver emails configured.', 'log' => ''];
        }

        $guestName = $booking['guest_name'] ?? 'Guest';
        $guestEmail = $booking['guest_email'] ?? 'N/A';
        $guestPhone = $booking['guest_phone'] ?? 'N/A';
        $ref = $booking['booking_reference'] ?? 'IND-NEW';
        $roomName = $room['name'] ?? 'Accommodation';
        $checkIn = format_date($booking['check_in_date']);
        $checkOut = format_date($booking['check_out_date']);
        $nights = calculate_nights($booking['check_in_date'], $booking['check_out_date']);
        $totalPrice = format_price($booking['total_price']);
        $specialRequests = !empty($booking['special_requests']) ? nl2br(e($booking['special_requests'])) : '<em style="color: #a8a29e;">None requested</em>';

        $promoCode = $booking['promo_code'] ?? '';
        $offerTitle = $booking['offer_title'] ?? '';
        $discountAmount = (float)($booking['discount_amount'] ?? 0);
        $roomRate = (float)($booking['room_rate'] ?? ($room['price_per_night'] ?? 0));
        $subtotal = $roomRate * $nights;
        $isOffer = !empty($offerTitle) || !empty($promoCode) || ($discountAmount > 0);

        // Department breakdown info
        $deptList = [];
        if (!empty(get_booking_receiver_emails())) $deptList[] = 'Front Desk & Reservations';
        if (!empty(get_booking_sales_emails())) $deptList[] = 'Sales & Commercial Team';
        if (!empty(get_booking_management_emails())) $deptList[] = 'Management / GM';
        if (!empty(get_booking_other_emails())) $deptList[] = 'Custom Setup Alert Inboxes';
        $deptText = !empty($deptList) ? implode(' • ', $deptList) : 'Hotel Staff';

        if ($isOffer) {
            if (empty($offerTitle)) {
                $offerTitle = !empty($promoCode) ? "Special Promo ({$promoCode})" : "Promotional Package";
            }

            // SPECIAL OFFER STAFF NOTIFICATION
            $headline = "🎁 Special Offer Booking: #{$ref}";
            $subject = "🎁 Special Offer Booking: #{$ref} [{$offerTitle}] - {$guestName} | " . hotel_name();

            $body = "
                <p><strong style=\"color: #713f12;\">🎁 Special Promotional Offer Booking:</strong> A guest has reserved an exclusive promotional package through the website.</p>
                
                <!-- High-visibility Origin Alert Banner -->
                <div style=\"background-color: #fefce8; border: 2px solid #facc15; border-radius: 8px; padding: 14px 16px; margin-bottom: 18px; font-size: 12px; color: #854d0e;\">
                    <div style=\"font-weight: 800; font-size: 13px; color: #713f12; text-transform: uppercase; letter-spacing: 0.5px;\">
                        🎁 BOOKING ORIGIN: SPECIAL PROMOTIONAL PACKAGE
                    </div>
                    <div style=\"margin-top: 6px; font-size: 14px; font-weight: 700; color: #343c0a;\">
                        Package / Campaign: " . e($offerTitle) . "
                    </div>
                    <div style=\"margin-top: 4px; font-size: 12px; color: #78350f;\">
                        " . (!empty($promoCode) ? "<strong>Code:</strong> <span style=\"font-family: monospace; background: #fef08a; padding: 2px 6px; border-radius: 3px; font-weight: bold;\">" . e($promoCode) . "</span> • " : "") . "
                        <strong>Discount Applied:</strong> <span style=\"color: #15803d; font-weight: bold;\">-" . e(format_price($discountAmount)) . "</span>
                    </div>
                    <div style=\"margin-top: 6px; padding-top: 6px; border-top: 1px dashed #fde047; font-size: 11px; color: #a16207;\">
                        <strong>Notification Distribution:</strong> " . e($deptText) . "
                    </div>
                </div>

                <div class=\"card\">
                    <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #78716c; margin-bottom: 10px; border-bottom: 1px solid #e7e5e4; padding-bottom: 6px;\">
                        Guest & Stay Details
                    </div>
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr><td style=\"padding: 6px 0; color: #78716c; width: 35%;\">Booking Reference:</td><td style=\"padding: 6px 0; font-weight: bold; font-family: monospace; color: #343c0a; font-size: 15px;\">" . e($ref) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Booking Origin:</td><td style=\"padding: 6px 0; font-weight: bold; color: #b45309;\">🎁 Special Offer (" . e($offerTitle) . ")</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Full Name:</td><td style=\"padding: 6px 0; font-weight: bold;\">" . e($guestName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Email:</td><td style=\"padding: 6px 0;\"><a href=\"mailto:" . e($guestEmail) . "\" style=\"color: #343c0a;\">" . e($guestEmail) . "</a></td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Phone:</td><td style=\"padding: 6px 0;\"><a href=\"tel:" . e($guestPhone) . "\" style=\"color: #343c0a;\">" . e($guestPhone) . "</a></td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Accommodation:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($roomName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Dates:</td><td style=\"padding: 6px 0;\">" . e($checkIn) . " &rarr; " . e($checkOut) . " (" . $nights . " nights)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Party Size:</td><td style=\"padding: 6px 0;\">" . (int)$booking['adults'] . " Adults, " . (int)$booking['children'] . " Children</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Special Requests:</td><td style=\"padding: 6px 0;\">" . $specialRequests . "</td></tr>
                        " . ($discountAmount > 0 ? "
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Standard Subtotal:</td><td style=\"padding: 6px 0; text-decoration: line-through; color: #a8a29e;\">" . e(format_price($subtotal)) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #15803d; font-weight: 600;\">Special Offer Discount:</td><td style=\"padding: 6px 0; color: #15803d; font-weight: 600;\">-" . e(format_price($discountAmount)) . "</td></tr>
                        " : "") . "
                        <tr><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; color: #1c1917; font-weight: bold;\">Final Rate Payable:</td><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; font-weight: bold; color: #343c0a; font-size: 16px;\">" . e($totalPrice) . "</td></tr>
                    </table>
                </div>

                <p style=\"font-size: 12px; color: #78716c;\">Submitted on " . date('Y-m-d H:i:s T') . " from public website Special Offers booking channel.</p>
            ";

            $html = self::renderTemplate($headline, $body, [
                'label' => 'Open Bookings Manager in Admin',
                'url' => BASE_URL . '/admin/bookings.php?search=' . urlencode($ref)
            ]);
        } else {
            // STANDARD DIRECT RESERVATION STAFF NOTIFICATION
            $headline = "🔔 New Direct Booking: #{$ref}";
            $subject = "🔔 New Direct Booking: #{$ref} - {$guestName} | " . hotel_name();

            $body = "
                <p><strong style=\"color: #343c0a;\">🔔 New Direct Reservation:</strong> A new guest booking has been submitted through the hotel website.</p>
                
                <!-- Standard Origin Alert Banner -->
                <div style=\"background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; margin-bottom: 15px; font-size: 12px; color: #166534;\">
                    <strong>Booking Origin:</strong> 🌐 Standard Direct Website Booking<br>
                    <span style=\"font-size: 11px; color: #15803d;\">Notification Distribution: " . e($deptText) . "</span>
                </div>

                <div class=\"card\">
                    <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #78716c; margin-bottom: 10px; border-bottom: 1px solid #e7e5e4; padding-bottom: 6px;\">
                        Guest & Stay Details
                    </div>
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr><td style=\"padding: 6px 0; color: #78716c; width: 35%;\">Booking Reference:</td><td style=\"padding: 6px 0; font-weight: bold; font-family: monospace; color: #343c0a; font-size: 15px;\">" . e($ref) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Booking Origin:</td><td style=\"padding: 6px 0; font-weight: 600;\">🌐 Standard Direct Reservation</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Full Name:</td><td style=\"padding: 6px 0; font-weight: bold;\">" . e($guestName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Email:</td><td style=\"padding: 6px 0;\"><a href=\"mailto:" . e($guestEmail) . "\" style=\"color: #343c0a;\">" . e($guestEmail) . "</a></td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Guest Phone:</td><td style=\"padding: 6px 0;\"><a href=\"tel:" . e($guestPhone) . "\" style=\"color: #343c0a;\">" . e($guestPhone) . "</a></td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Accommodation:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($roomName) . "</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Dates:</td><td style=\"padding: 6px 0;\">" . e($checkIn) . " &rarr; " . e($checkOut) . " (" . $nights . " nights)</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Party Size:</td><td style=\"padding: 6px 0;\">" . (int)$booking['adults'] . " Adults, " . (int)$booking['children'] . " Children</td></tr>
                        <tr><td style=\"padding: 6px 0; color: #78716c;\">Special Requests:</td><td style=\"padding: 6px 0;\">" . $specialRequests . "</td></tr>
                        <tr><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; color: #1c1917; font-weight: bold;\">Total Rate:</td><td style=\"padding: 8px 0; border-top: 1px solid #e7e5e4; font-weight: bold; color: #343c0a; font-size: 16px;\">" . e($totalPrice) . "</td></tr>
                    </table>
                </div>

                <p style=\"font-size: 12px; color: #78716c;\">Submitted on " . date('Y-m-d H:i:s T') . " from public website reservation form.</p>
            ";

            $html = self::renderTemplate($headline, $body, [
                'label' => 'Open Bookings Manager in Admin',
                'url' => BASE_URL . '/admin/bookings.php?search=' . urlencode($ref)
            ]);
        }

        $receivers = array_values(array_unique(array_filter($receivers)));
        $primaryEmail = array_shift($receivers);
        $ccEmails = $receivers;

        return self::send($primaryEmail, $subject, $html, '', $ccEmails);
    }

    /**
     * Send Contact Us Inquiry Notification to Hotel Staff, Sales & Setup Inboxes
     */
    public static function sendContactNotificationToHotel(array $contact): array {
        $receivers = get_contact_all_notification_emails();
        if (empty($receivers)) {
            return ['success' => false, 'message' => 'No contact receiver emails configured.', 'log' => ''];
        }

        $senderName = $contact['name'] ?? 'Website Visitor';
        $senderEmail = $contact['email'] ?? '';
        $senderPhone = $contact['phone'] ?? 'N/A';
        $subject = $contact['subject'] ?? 'Website Inquiry';
        $message = nl2br(e($contact['message'] ?? ''));

        // Department breakdown info
        $deptList = [];
        if (!empty(get_contact_receiver_emails())) $deptList[] = 'General Inquiries & Reception';
        if (!empty(get_contact_sales_emails())) $deptList[] = 'Sales & Events Team';
        if (!empty(get_contact_management_emails())) $deptList[] = 'Management / GM';
        if (!empty(get_contact_other_emails())) $deptList[] = 'Custom Setup Alert Inboxes';
        $deptText = !empty($deptList) ? implode(' • ', $deptList) : 'Hotel Staff';

        $body = "
            <p><strong style=\"color: #343c0a;\">✉️ New Contact Form Message:</strong> A visitor has submitted a new inquiry via the website Contact Us page.</p>
            
            <div style=\"background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 12px; margin-bottom: 15px; font-size: 11px; color: #166534;\">
                <strong>Notification Distribution:</strong> " . e($deptText) . "
            </div>

            <div class=\"card\">
                <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                    <tr><td style=\"padding: 6px 0; color: #78716c; width: 30%;\">From:</td><td style=\"padding: 6px 0; font-weight: bold;\">" . e($senderName) . "</td></tr>
                    <tr><td style=\"padding: 6px 0; color: #78716c;\">Email Address:</td><td style=\"padding: 6px 0;\"><a href=\"mailto:" . e($senderEmail) . "\" style=\"color: #343c0a; font-weight: 600;\">" . e($senderEmail) . "</a></td></tr>
                    <tr><td style=\"padding: 6px 0; color: #78716c;\">Phone Number:</td><td style=\"padding: 6px 0;\"><a href=\"tel:" . e($senderPhone) . "\" style=\"color: #343c0a;\">" . e($senderPhone) . "</a></td></tr>
                    <tr><td style=\"padding: 6px 0; color: #78716c;\">Subject:</td><td style=\"padding: 6px 0; font-weight: 600;\">" . e($subject) . "</td></tr>
                    <tr><td style=\"padding: 10px 0 6px 0; color: #78716c; vertical-align: top;\">Message:</td><td style=\"padding: 10px 0 6px 0; line-height: 1.6;\">" . $message . "</td></tr>
                </table>
            </div>

            <p style=\"font-size: 12px; color: #78716c;\">You can reply directly to the sender at <a href=\"mailto:" . e($senderEmail) . "\" style=\"color: #343c0a;\">" . e($senderEmail) . "</a> or review all messages in the dashboard.</p>
        ";

        $html = self::renderTemplate("New Contact Inquiry: {$subject}", $body, [
            'label' => 'View Messages in SoftBook Admin',
            'url' => BASE_URL . '/admin/messages.php'
        ]);

        $receivers = array_values(array_unique(array_filter($receivers)));
        $primaryEmail = array_shift($receivers);
        $ccEmails = $receivers;

        $subject = "✉️ Contact Inquiry: " . $subject . " (From: " . $senderName . ")";
        return self::send($primaryEmail, $subject, $html, '', $ccEmails);
    }

    /**
     * Send Courtesy Auto-Reply to Guest Submitting Contact Us Form
     */
    public static function sendContactAutoReply(array $contact): array {
        if (!is_contact_auto_reply_enabled()) {
            return ['success' => true, 'message' => 'Auto-reply disabled.'];
        }

        $senderEmail = $contact['email'] ?? '';
        $senderName = $contact['name'] ?? 'Valued Guest';
        $subject = $contact['subject'] ?? 'Your Inquiry';

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email for auto-reply.'];
        }

        $body = "
            <p>Dear <strong>" . e($senderName) . "</strong>,</p>
            <p>Thank you for reaching out to <strong>" . e(hotel_name()) . "</strong>. We have received your message regarding <em>\"" . e($subject) . "\"</em>.</p>
            <p>Our concierge and reservation team will review your inquiry and get back to you promptly, typically within 2 to 4 business hours.</p>
            <p>If your matter is urgent or requires immediate booking assistance, please don't hesitate to call our 24/7 reception desk directly at <a href=\"tel:" . e(hotel_phone()) . "\" style=\"color: #343c0a; font-weight: bold;\">" . e(hotel_phone()) . "</a>.</p>
            <p>Warmest regards,<br><strong>" . e(hotel_name()) . " Concierge Team</strong></p>
        ";

        $html = self::renderTemplate("Thank You for Contacting " . hotel_name(), $body, [
            'label' => 'Explore Our Rooms & Suites',
            'url' => BASE_URL . '/rooms.php'
        ]);

        return self::send(['email' => $senderEmail, 'name' => $senderName], "We have received your message | " . hotel_name(), $html);
    }

    /**
     * Send Test Diagnostic Email
     */
    public static function sendTestEmail(string $recipientEmail): array {
        $headline = "SMTP Test Message";
        $content = "
            <p>Congratulations! Your email server settings on <strong>" . e(hotel_name()) . "</strong> are configured and working properly.</p>
            <div class=\"card\">
                <p style=\"margin: 0 0 6px 0;\"><strong>Delivery Driver:</strong> " . strtoupper(e(get_setting('mail_driver', 'mail'))) . "</p>
                <p style=\"margin: 0 0 6px 0;\"><strong>SMTP Host:</strong> " . e(get_setting('mail_host', 'N/A')) . "</p>
                <p style=\"margin: 0 0 6px 0;\"><strong>SMTP Port:</strong> " . e(get_setting('mail_port', 'N/A')) . "</p>
                <p style=\"margin: 0 0 6px 0;\"><strong>Booking Notification Email(s):</strong> " . e(implode(', ', get_booking_receiver_emails())) . "</p>
                <p style=\"margin: 0 0 6px 0;\"><strong>Contact Notification Email(s):</strong> " . e(implode(', ', get_contact_receiver_emails())) . "</p>
                <p style=\"margin: 0;\"><strong>Timestamp:</strong> " . date('Y-m-d H:i:s T') . "</p>
            </div>
            <p style=\"font-size: 12px; color: #78716c;\">This test email was triggered from SoftBook.</p>
        ";

        $html = self::renderTemplate($headline, $content, [
            'label' => 'Access Admin Dashboard',
            'url' => BASE_URL . '/admin/index.php'
        ]);

        return self::send($recipientEmail, "Test Email from " . hotel_name(), $html);
    }

    /**
     * Send Portal Staff Invitation Email
     */
    public static function sendUserInvitation(string $toEmail, string $toName, string $role, string $token, string $invitedByName = 'System Administrator'): array {
        $headline = "You're Invited to SoftBook CMS";
        $subject = "You've been invited to join SoftBook CMS | " . hotel_name();
        $acceptUrl = BASE_URL . "/admin/accept-invitation.php?token=" . urlencode($token);
        $roleLabel = ($role === 'admin') ? 'Administrator (Full Access)' : 'Content Editor (Operations & Content)';
        $roleColor = ($role === 'admin') ? '#343c0a' : '#b45309';

        $body = "
            <p>Dear <strong>" . e($toName) . "</strong>,</p>
            <p><strong>" . e($invitedByName) . "</strong> has invited you to join the <strong>" . e(hotel_name()) . "</strong> management portal on SoftBook CMS.</p>

            <div class=\"card\" style=\"background-color: #fafaf9; border-left: 4px solid {$roleColor};\">
                <div style=\"font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #78716c; margin-bottom: 8px;\">
                    Invitation Details
                </div>
                <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                    <tr><td style=\"padding: 5px 0; color: #78716c; width: 35%;\">Invited Member:</td><td style=\"padding: 5px 0; font-weight: bold; color: #1c1917;\">" . e($toName) . "</td></tr>
                    <tr><td style=\"padding: 5px 0; color: #78716c;\">Assigned Email:</td><td style=\"padding: 5px 0; font-family: monospace; font-weight: 600; color: #343c0a;\">" . e($toEmail) . "</td></tr>
                    <tr><td style=\"padding: 5px 0; color: #78716c;\">Permission Level:</td><td style=\"padding: 5px 0; font-weight: bold; color: {$roleColor};\">" . e($roleLabel) . "</td></tr>
                    <tr><td style=\"padding: 5px 0; color: #78716c;\">Link Validity:</td><td style=\"padding: 5px 0; color: #57534e;\">48 Hours</td></tr>
                </table>
            </div>

            <p style=\"margin-top: 15px;\">To activate your staff account and set your confidential password for your first login, please click the button below:</p>
            
            <p style=\"font-size: 12px; color: #78716c; margin-top: 15px;\">
                If the button above does not work, copy and paste this link into your browser:<br>
                <a href=\"" . e($acceptUrl) . "\" style=\"color: #343c0a; word-break: break-all;\">" . e($acceptUrl) . "</a>
            </p>
        ";

        $html = self::renderTemplate($headline, $body, [
            'label' => 'Accept Invitation & Set Password',
            'url' => $acceptUrl
        ]);

        return self::send(['email' => $toEmail, 'name' => $toName], $subject, $html);
    }

    /**
     * Send Password Reset Email
     */
    public static function sendPasswordReset(string $toEmail, string $toName, string $token): array {
        $headline = "Reset Your Password";
        $subject = "Password Reset Request | " . hotel_name() . " Portal";
        $resetUrl = BASE_URL . "/admin/reset-password.php?token=" . urlencode($token);

        $body = "
            <p>Dear <strong>" . e($toName) . "</strong>,</p>
            <p>We received a request to reset your password for your <strong>" . e(hotel_name()) . "</strong> SoftBook CMS portal account.</p>

            <div class=\"card\" style=\"background-color: #fafaf9; border-left: 4px solid #343c0a;\">
                <p style=\"margin: 0 0 6px 0; font-size: 13px;\"><strong>Account Email:</strong> " . e($toEmail) . "</p>
                <p style=\"margin: 0; font-size: 12px; color: #78716c;\"><strong>Security Notice:</strong> This reset link is single-use and will expire in <strong>60 minutes</strong>.</p>
            </div>

            <p style=\"margin-top: 15px;\">To create a new password and regain access to the portal, click the button below:</p>

            <p style=\"font-size: 12px; color: #78716c; margin-top: 15px;\">
                If you did not request a password reset, please ignore this email or notify your system administrator immediately. No changes have been made to your account.
            </p>
            <p style=\"font-size: 11px; color: #a8a29e; word-break: break-all;\">
                Direct Link: <a href=\"" . e($resetUrl) . "\" style=\"color: #343c0a;\">" . e($resetUrl) . "</a>
            </p>
        ";

        $html = self::renderTemplate($headline, $body, [
            'label' => 'Reset My Password',
            'url' => $resetUrl
        ]);

        return self::send(['email' => $toEmail, 'name' => $toName], $subject, $html);
    }
}

