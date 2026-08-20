<?php
/**
 * Indra Hotel - Google OAuth 2.0 Authentication Service for Public Guests & Booking History
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class GoogleAuth {
    
    /**
     * Check if Google OAuth is enabled and configured in CMS
     */
    public static function isEnabled(): bool {
        $enabled = get_setting('google_oauth_enabled', '0') === '1';
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();
        return $enabled && !empty($clientId) && !empty($clientSecret);
    }

    /**
     * Get Google OAuth Client ID
     */
    public static function getClientId(): string {
        return trim(get_setting('google_oauth_client_id', ''));
    }

    /**
     * Get Google OAuth Client Secret
     */
    public static function getClientSecret(): string {
        return trim(get_setting('google_oauth_client_secret', ''));
    }

    /**
     * Get Authorized Redirect URI for Public Guest Portal
     */
    public static function getGuestRedirectUri(): string {
        return BASE_URL . '/api/guest-google-callback.php';
    }

    /**
     * Build Google OAuth 2.0 Authorization URL for Public Guests
     */
    public static function getGuestAuthUrl(string $redirectAfter = ''): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = bin2hex(random_bytes(16));
        $_SESSION['guest_google_oauth_state'] = $csrfToken;
        
        $statePayload = json_encode([
            'csrf' => $csrfToken,
            'redirect' => $redirectAfter ?: (BASE_URL . '/my-booking.php')
        ]);

        $params = [
            'client_id' => self::getClientId(),
            'redirect_uri' => self::getGuestRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => base64_encode($statePayload)
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public static function exchangeCodeForToken(string $code, ?string $redirectUri = null): ?array {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code' => $code,
            'client_id' => self::getClientId(),
            'client_secret' => self::getClientSecret(),
            'redirect_uri' => $redirectUri ?: self::getGuestRedirectUri(),
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Fetch user profile from Google UserInfo endpoint
     */
    public static function getUserProfile(string $accessToken): ?array {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';

        $ch = curl_init($userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $profile = json_decode($response, true);
        return is_array($profile) ? $profile : null;
    }

    /**
     * Store Public Guest Session from Google Profile
     */
    public static function loginGuest(array $profile): array {
        $email = trim(strtolower($profile['email'] ?? ''));
        $name = trim($profile['name'] ?? 'Valued Guest');
        $googleId = trim($profile['sub'] ?? '');
        $picture = trim($profile['picture'] ?? '');
        $emailVerified = !empty($profile['email_verified']);

        if (empty($email) || !$emailVerified) {
            return [
                'success' => false,
                'message' => 'Unable to verify your email address with Google.'
            ];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize Guest Portal Session
        $_SESSION['guest_user'] = [
            'email' => $email,
            'name' => $name,
            'picture' => $picture,
            'google_id' => $googleId,
            'logged_in_at' => time()
        ];

        // Upsert Record in guest_users Table
        try {
            $pdo = getDB();
            $stmtCheck = $pdo->prepare("SELECT id FROM guest_users WHERE LOWER(email) = ? LIMIT 1");
            $stmtCheck->execute([$email]);
            $existing = $stmtCheck->fetch();
            $now = date('Y-m-d H:i:s');

            if ($existing) {
                $stmtUp = $pdo->prepare("UPDATE guest_users SET name = ?, avatar = ?, google_id = ?, auth_provider = 'google', last_login_at = ? WHERE id = ?");
                $stmtUp->execute([$name, $picture, $googleId, $now, (int)$existing['id']]);
            } else {
                $stmtIn = $pdo->prepare("INSERT INTO guest_users (name, email, avatar, google_id, auth_provider, status, last_login_at, created_at) VALUES (?, ?, ?, ?, 'google', 'active', ?, ?)");
                $stmtIn->execute([$name, $email, $picture, $googleId, $now, $now]);
            }
        } catch (Throwable $e) {
            // Ignore if DB upsert fails
        }

        return [
            'success' => true,
            'guest' => $_SESSION['guest_user']
        ];
    }
}
