<?php
/**
 * Indra Hotel - Authentication & Security Layer
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Auth {
    /**
     * Authenticate user with email and password
     */
    public static function attempt(string $email, string $password): bool {
        $email = trim(strtolower($email));
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? '';
            $_SESSION['last_activity'] = time();
            return true;
        }

        // Support fallback admin if database record has default hash
        if ($email === strtolower(DEFAULT_ADMIN_EMAIL) && $password === DEFAULT_ADMIN_PASSWORD) {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = DEFAULT_ADMIN_NAME;
            $_SESSION['user_email'] = DEFAULT_ADMIN_EMAIL;
            $_SESSION['user_role'] = 'admin';
            $_SESSION['last_activity'] = time();
            return true;
        }

        return false;
    }

    /**
     * Authenticate and initialize session for a user array directly (e.g. via Google OAuth or SSO)
     */
    public static function loginUser(array $user): void {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'] ?? '';
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check if current session is logged in
     */
    public static function check(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        // Inactivity timeout: 4 hours
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 14400)) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Require authentication or redirect to login
     */
    public static function requireAuth(): void {
        if (!self::check()) {
            set_flash('error', 'Please log in to access SoftBook.');
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . BASE_URL . '/admin/login.php?redirect=' . $redirect);
            exit;
        }
    }

    /**
     * Alias for requireAuth
     */
    public static function requireLogin(): void {
        self::requireAuth();
    }

    /**
     * Get logged-in user details
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        static $cachedUser = null;
        if ($cachedUser !== null) {
            return $cachedUser;
        }

        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $dbUser = $stmt->fetch();
            if ($dbUser) {
                $_SESSION['user_name'] = $dbUser['name'];
                $_SESSION['user_email'] = $dbUser['email'];
                $_SESSION['user_role'] = $dbUser['role'];
                $_SESSION['user_avatar'] = $dbUser['avatar'] ?? '';
                $cachedUser = $dbUser;
                return $cachedUser;
            }
        } catch (Throwable $e) {}

        $cachedUser = [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? 'Admin',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'admin',
            'avatar' => $_SESSION['user_avatar'] ?? ''
        ];
        return $cachedUser;
    }

    /**
     * Terminate user session
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Get current user's role
     */
    public static function role(): string {
        return $_SESSION['user_role'] ?? 'admin';
    }

    /**
     * Check if current user is an Administrator
     */
    public static function isAdmin(): bool {
        return self::check() && self::role() === 'admin';
    }

    /**
     * Check if current user is an Editor or higher
     */
    public static function isEditor(): bool {
        return self::check() && in_array(self::role(), ['admin', 'editor'], true);
    }

    /**
     * Require Administrator privileges or redirect
     */
    public static function requireAdmin(): void {
        self::requireAuth();
        if (!self::isAdmin()) {
            set_flash('error', 'Access restricted: Administrator privileges required for this section.');
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
    }

    /**
     * Require specific roles or redirect
     */
    public static function requireRole(array $roles): void {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            set_flash('error', 'You do not have sufficient permissions to access this feature.');
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
    }

    /**
     * Generate CSRF Token
     */
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function generateCsrf(): string {
        return self::csrfToken();
    }

    /**
     * Verify CSRF Token
     */
    public static function verifyCsrf(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function validateCsrf(?string $token): bool {
        return self::verifyCsrf($token);
    }
}
