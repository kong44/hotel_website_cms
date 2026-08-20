<?php
/**
 * Indra Hotel - Core Routing Engine (Clean URLs)
 * Supports parameterized routes, reverse URL generation, and fallback resolution.
 */

if (defined('INDRA_ROUTER_LOADED')) {
    return;
}
define('INDRA_ROUTER_LOADED', true);

require_once __DIR__ . '/../config.php';

class Router {
    private static array $routes = [];
    private static array $namedRoutes = [];
    private static ?string $currentRoute = null;
    private static ?string $currentRouteName = null;
    private static array $params = [];

    /**
     * Register a GET route
     */
    public static function get(string $pattern, string|callable $handler, string $name = ''): void {
        self::add('GET', $pattern, $handler, $name);
    }

    /**
     * Register a POST route
     */
    public static function post(string $pattern, string|callable $handler, string $name = ''): void {
        self::add('POST', $pattern, $handler, $name);
    }

    /**
     * Register a route for any HTTP method
     */
    public static function any(string $pattern, string|callable $handler, string $name = ''): void {
        self::add('ANY', $pattern, $handler, $name);
    }

    /**
     * Add a route definition
     */
    public static function add(string $method, string $pattern, string|callable $handler, string $name = ''): void {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern !== '/') {
            $pattern = rtrim($pattern, '/');
        }

        $route = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'name' => $name
        ];

        self::$routes[] = $route;
        if (!empty($name)) {
            self::$namedRoutes[$name] = $route;
        }
    }

    /**
     * Get the relative URI for current request
     */
    public static function getRequestPath(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip subfolder if app is installed in a subdirectory
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $baseDir = str_replace('\\', '/', $baseDir);
        $baseDir = preg_replace('/(\/admin|\/api).*$/i', '', $baseDir);
        $baseDir = rtrim($baseDir, '/');

        if (!empty($baseDir) && $baseDir !== '/' && strpos($path, $baseDir) === 0) {
            $path = substr($path, strlen($baseDir));
        }

        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * Get current HTTP method
     */
    public static function getRequestMethod(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Initialize all standard application routes
     */
    public static function registerAppRoutes(): void {
        if (!empty(self::$routes)) {
            return;
        }

        // --- Core Public Routes ---
        self::any('/', 'index.php', 'home');
        self::any('/home', 'index.php', 'home.alias');
        self::get('/about', 'about.php', 'about');
        self::get('/rooms', 'rooms.php', 'rooms');
        self::get('/room/{slug}', 'room.php', 'room.detail');
        self::get('/rooms/{slug}', 'room.php', 'rooms.detail.alias');
        self::get('/room', 'room.php', 'room.query');
        self::get('/eat-drink', 'eat-drink.php', 'eat-drink');
        self::get('/dining', 'eat-drink.php', 'dining.alias');
        self::get('/wellness', 'wellness.php', 'wellness');
        self::get('/spa', 'wellness.php', 'spa.alias');
        self::get('/gym', 'wellness.php', 'gym.alias');
        self::get('/offers', 'offers.php', 'offers');
        self::get('/offer/{id}', 'offer-detail.php', 'offer.detail');
        self::get('/offer-detail', 'offer-detail.php', 'offer.detail.query');
        self::get('/location', 'location.php', 'location');
        self::get('/location/{slug}', 'location-detail.php', 'location.detail');
        self::get('/location-detail', 'location-detail.php', 'location.detail.query');
        self::get('/gallery', 'gallery.php', 'gallery');
        self::any('/contact', 'contact.php', 'contact');
        self::any('/book', 'book.php', 'book');
        self::any('/booking', 'book.php', 'booking.alias');
        self::any('/my-booking', 'my-booking.php', 'my-booking');
        self::any('/my-bookings', 'my-booking.php', 'my-bookings.alias');
        self::get('/sitemap.xml', 'sitemap.xml.php', 'sitemap');

        // --- Admin Routes ---
        self::any('/admin', 'admin/index.php', 'admin.dashboard');
        self::any('/admin/login', 'admin/login.php', 'admin.login');
        self::any('/admin/logout', 'admin/logout.php', 'admin.logout');
        self::any('/admin/accommodations', 'admin/accommodations.php', 'admin.accommodations');
        self::any('/admin/room-form', 'admin/room-form.php', 'admin.room-form');
        self::any('/admin/room-types', 'admin/room-types.php', 'admin.room-types');
        self::any('/admin/amenities', 'admin/amenities.php', 'admin.amenities');
        self::any('/admin/bookings', 'admin/bookings.php', 'admin.bookings');
        self::any('/admin/booking-create', 'admin/booking-create.php', 'admin.booking-create');
        self::any('/admin/booking-detail', 'admin/booking-detail.php', 'admin.booking-detail');
        self::any('/admin/dining-wellness', 'admin/dining-wellness.php', 'admin.dining-wellness');
        self::any('/admin/homepage', 'admin/homepage.php', 'admin.homepage');
        self::any('/admin/property', 'admin/property.php', 'admin.property');
        self::any('/admin/offers', 'admin/offers.php', 'admin.offers');
        self::any('/admin/gallery', 'admin/gallery.php', 'admin.gallery');
        self::any('/admin/locations', 'admin/locations.php', 'admin.locations');
        self::any('/admin/messages', 'admin/messages.php', 'admin.messages');
        self::any('/admin/guests', 'admin/guests.php', 'admin.guests');
        self::any('/admin/users', 'admin/users.php', 'admin.users');
        self::any('/admin/email-settings', 'admin/email-settings.php', 'admin.email-settings');
        self::any('/admin/settings', 'admin/settings.php', 'admin.settings');
        self::any('/admin/livechat', 'admin/livechat.php', 'admin.livechat');
        self::any('/admin/forgot-password', 'admin/forgot-password.php', 'admin.forgot-password');
        self::any('/admin/reset-password', 'admin/reset-password.php', 'admin.reset-password');
        self::any('/admin/accept-invitation', 'admin/accept-invitation.php', 'admin.accept-invitation');
        self::any('/admin/google-login', 'admin/google-login.php', 'admin.google-login');
        self::any('/admin/google-callback', 'admin/google-callback.php', 'admin.google-callback');

        // --- API Routes ---
        self::any('/api/check-availability', 'api/check-availability.php', 'api.check-availability');
        self::any('/api/book-room', 'api/book-room.php', 'api.book-room');
        self::any('/api/contact-submit', 'api/contact-submit.php', 'api.contact-submit');
        self::any('/api/upload', 'api/upload.php', 'api.upload');
        self::any('/api/send-test-email', 'api/send-test-email.php', 'api.send-test-email');
        self::any('/api/guest-google-login', 'api/guest-google-login.php', 'api.guest-google-login');
        self::any('/api/guest-google-callback', 'api/guest-google-callback.php', 'api.guest-google-callback');
        self::any('/api/guest-logout', 'api/guest-logout.php', 'api.guest-logout');
    }

    /**
     * Dispatch the current request
     */
    public static function dispatch(): void {
        self::registerAppRoutes();

        $requestPath = self::getRequestPath();
        $requestMethod = self::getRequestMethod();

        // 1. Direct match with registered routes
        foreach (self::$routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $requestMethod) {
                continue;
            }

            $paramNames = [];
            // Convert {param} placeholders to regex named capture groups
            $patternRegex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $route['pattern']);

            $patternRegex = '#^' . $patternRegex . '$#i';

            if (preg_match($patternRegex, $requestPath, $matches)) {
                self::$currentRoute = $route['pattern'];
                self::$currentRouteName = $route['name'];

                // Populate matched parameters
                $params = [];
                foreach ($paramNames as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = urldecode($matches[$name]);
                        $_GET[$name] = $params[$name];
                    }
                }
                self::$params = $params;

                self::executeHandler($route['handler']);
                return;
            }
        }

        // 2. Fallback: Check if direct .php file exists (e.g. /rooms.php or /admin/login.php)
        $cleanRel = ltrim($requestPath, '/');
        $possibleFile = ROOT_PATH . '/' . $cleanRel;
        if (!empty($cleanRel) && file_exists($possibleFile) && is_file($possibleFile)) {
            self::$currentRoute = $requestPath;
            self::$currentRouteName = pathinfo($possibleFile, PATHINFO_FILENAME);
            require $possibleFile;
            return;
        }

        // Check if appending .php matches an existing file
        if (!empty($cleanRel) && file_exists($possibleFile . '.php') && is_file($possibleFile . '.php')) {
            self::$currentRoute = $requestPath;
            self::$currentRouteName = pathinfo($possibleFile, PATHINFO_FILENAME);
            require $possibleFile . '.php';
            return;
        }

        // 3. Not Found -> Render 404
        self::render404();
    }

    /**
     * Execute a route handler
     */
    private static function executeHandler(string|callable $handler): void {
        if (is_callable($handler)) {
            call_user_func_array($handler, [self::$params]);
            return;
        }

        $targetFile = ROOT_PATH . '/' . ltrim($handler, '/');
        if (file_exists($targetFile)) {
            require $targetFile;
        } else {
            self::render404();
        }
    }

    /**
     * Render 404 Not Found
     */
    public static function render404(): void {
        http_response_code(404);
        $custom404 = ROOT_PATH . '/404.php';
        if (file_exists($custom404)) {
            require $custom404;
        } else {
            echo '<!DOCTYPE html><html><head><title>404 Page Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>404 - Page Not Found</h1><p>The page you requested could not be found.</p><a href="' . BASE_URL . '/home">Return Home</a></body></html>';
        }
        exit;
    }

    /**
     * Generate URL for a named route
     */
    public static function route(string $name, array $params = [], array $query = []): string {
        self::registerAppRoutes();

        if (isset(self::$namedRoutes[$name])) {
            $path = self::$namedRoutes[$name]['pattern'];
            foreach ($params as $key => $value) {
                $path = str_replace('{' . $key . '}', urlencode((string)$value), $path);
            }
            // Remove any unreplaced optional param tokens
            $path = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $path);
            $path = rtrim($path, '/');
            if (empty($path)) {
                $path = '/';
            }

            $url = BASE_URL . ($path === '/' ? '' : $path);
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
            return $url;
        }

        // Fallback: generate direct URL
        return self::url($name, $query);
    }

    /**
     * Generate URL from direct path
     */
    public static function url(string $path = '', array $query = []): string {
        $path = '/' . ltrim($path, '/');
        if ($path === '/') {
            $path = '';
        }
        $url = BASE_URL . $path;
        if (!empty($query)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }
        return $url;
    }

    /**
     * Get current active route pattern or name
     */
    public static function currentRoute(): ?string {
        return self::$currentRoute;
    }

    public static function currentRouteName(): ?string {
        return self::$currentRouteName;
    }

    /**
     * Get route parameter
     */
    public static function param(string $key, mixed $default = null): mixed {
        return self::$params[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Check if a given route is currently active
     */
    public static function isActive(string|array $routes): bool {
        $checkList = is_array($routes) ? $routes : [$routes];
        $currentPath = self::getRequestPath();
        $currentName = self::$currentRouteName ?? '';
        $currentScript = basename($_SERVER['PHP_SELF'] ?? '');

        foreach ($checkList as $r) {
            $rClean = trim($r, '/');
            if (empty($rClean) && ($currentPath === '/' || $currentPath === '/home')) {
                return true;
            }
            if ($currentName === $rClean || strpos($currentName, $rClean) === 0) {
                return true;
            }
            if ($currentPath === '/' . $rClean || strpos($currentPath, '/' . $rClean . '/') === 0) {
                return true;
            }
            if ($currentScript === $rClean || $currentScript === $rClean . '.php') {
                return true;
            }
        }
        return false;
    }
}
