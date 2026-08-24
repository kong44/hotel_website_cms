<?php
/**
 * Indra Hotel - Database Connection & Abstraction Layer
 * Supports MySQL (Primary: `hotel_website`) with automatic initialization & SQLite fallback
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';

    /**
     * Get singleton PDO connection instance
     */
    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Try MySQL first
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
            ];
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::$activeDriver = 'mysql';
            self::ensureTablesExist(self::$instance, 'mysql');
            return self::$instance;
        } catch (PDOException $e) {
            // If MySQL is not running or database is not yet created, try to create DB or fallback to SQLite
            try {
                // Try connecting without dbname to create hotel_website database if permitted
                $serverDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$activeDriver = 'mysql';
                self::ensureTablesExist(self::$instance, 'mysql');
                return self::$instance;
            } catch (Throwable $fallbackErr) {
                // Smooth fallback to SQLite for local development
                $sqlitePath = SQLITE_FILE;
                $dsn = 'sqlite:' . $sqlitePath;
                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                self::$activeDriver = 'sqlite';
                self::ensureTablesExist(self::$instance, 'sqlite');
                return self::$instance;
            }
        }
    }

    public static function getDriver(): string {
        return self::$activeDriver;
    }

    /**
     * Verify and automatically create tables & seed if missing
     */
    public static function ensureTablesExist(PDO $pdo, string $driver): void {
        try {
            $tableCheck = ($driver === 'mysql') 
                ? "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'rooms'" 
                : "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='rooms'";
            $stmt = $pdo->query($tableCheck);
            $hasRooms = $stmt && ((int)$stmt->fetchColumn() > 0);

            if (!$hasRooms) {
                self::initializeDatabase($pdo, $driver);
            } else {
                // Ensure translations_json column exists in tables
                self::migrateTranslationsColumn($pdo, $driver);
                self::ensureCustomPagesAndThemesExist($pdo, $driver);
            }
        } catch (Throwable $e) {
            self::initializeDatabase($pdo, $driver);
        }
    }

    private static function ensureCustomPagesAndThemesExist(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `custom_pages` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `slug` VARCHAR(191) NOT NULL UNIQUE,
                  `title` VARCHAR(255) NOT NULL,
                  `meta_title` VARCHAR(255) NULL,
                  `meta_description` TEXT NULL,
                  `hero_badge` VARCHAR(255) NULL,
                  `hero_title` VARCHAR(255) NULL,
                  `hero_subtitle` TEXT NULL,
                  `hero_image` VARCHAR(500) NULL,
                  `hero_video` VARCHAR(500) NULL,
                  `hero_height` VARCHAR(50) DEFAULT 'medium',
                  `hero_overlay` VARCHAR(50) DEFAULT 'medium',
                  `theme_id` VARCHAR(100) DEFAULT 'default',
                  `layout_type` VARCHAR(50) DEFAULT 'full_width',
                  `sections_json` LONGTEXT NULL,
                  `status` ENUM('published', 'draft') DEFAULT 'published',
                  `is_in_nav` TINYINT(1) DEFAULT 0,
                  `nav_order` INT DEFAULT 99,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `theme_licenses` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `theme_slug` VARCHAR(100) NOT NULL UNIQUE,
                  `is_purchased` TINYINT(1) DEFAULT 0,
                  `is_active` TINYINT(1) DEFAULT 0,
                  `purchase_date` DATETIME NULL,
                  `custom_styles_json` LONGTEXT NULL,
                  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS custom_pages (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  slug TEXT NOT NULL UNIQUE,
                  title TEXT NOT NULL,
                  meta_title TEXT,
                  meta_description TEXT,
                  hero_badge TEXT,
                  hero_title TEXT,
                  hero_subtitle TEXT,
                  hero_image TEXT,
                  hero_video TEXT,
                  hero_height TEXT DEFAULT 'medium',
                  hero_overlay TEXT DEFAULT 'medium',
                  theme_id TEXT DEFAULT 'default',
                  layout_type TEXT DEFAULT 'full_width',
                  sections_json TEXT,
                  status TEXT DEFAULT 'published',
                  is_in_nav INTEGER DEFAULT 0,
                  nav_order INTEGER DEFAULT 99,
                  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS theme_licenses (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  theme_slug TEXT NOT NULL UNIQUE,
                  is_purchased INTEGER DEFAULT 0,
                  is_active INTEGER DEFAULT 0,
                  purchase_date DATETIME,
                  custom_styles_json TEXT,
                  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );");
            }
        } catch (Throwable $t) {}
    }


    private static function migrateTranslationsColumn(PDO $pdo, string $driver): void {
        $tables = ['rooms', 'dining_wellness', 'special_offers'];
        foreach ($tables as $table) {
            try {
                if ($driver === 'mysql') {
                    $cols = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'translations_json'")->fetchAll();
                    if (empty($cols)) {
                        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `translations_json` LONGTEXT NULL");
                    }
                } else {
                    $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
                    $hasCol = false;
                    foreach ($cols as $col) {
                        if (($col['name'] ?? '') === 'translations_json') {
                            $hasCol = true;
                            break;
                        }
                    }
                    if (!$hasCol) {
                        $pdo->exec("ALTER TABLE {$table} ADD COLUMN translations_json TEXT");
                    }
                }
            } catch (Throwable $t) {
                // Skip if column already exists or table cannot be altered
            }
        }
        self::migrateAmenitiesTable($pdo, $driver);
    }

    private static function migrateAmenitiesTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `amenities` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(150) NOT NULL UNIQUE,
                    `icon` VARCHAR(50) NOT NULL DEFAULT 'check_circle',
                    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
                    `translations_json` LONGTEXT NULL,
                    `display_order` INT NOT NULL DEFAULT 99,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `amenities` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` TEXT NOT NULL UNIQUE,
                    `icon` TEXT NOT NULL DEFAULT 'check_circle',
                    `category` TEXT NOT NULL DEFAULT 'general',
                    `translations_json` TEXT NULL,
                    `display_order` INTEGER NOT NULL DEFAULT 99,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }

            // Check if amenities table is empty, seed defaults
            $count = (int)$pdo->query("SELECT COUNT(*) FROM amenities")->fetchColumn();
            if ($count === 0) {
                $defaultAmenities = [
                    ['High-Speed Wi-Fi', 'wifi', 'technology', 1],
                    ['55" Smart Cable TV', 'tv', 'technology', 2],
                    ['65" 4K Smart TV', 'tv', 'technology', 3],
                    ['Artisan Coffee Maker', 'coffee', 'dining', 4],
                    ['Espresso Machine', 'coffee_maker', 'dining', 5],
                    ['Signature Nespresso Station', 'coffee_maker', 'dining', 6],
                    ['Minibar & Refrigerator', 'kitchen', 'dining', 7],
                    ['Complimentary Premium Minibar', 'local_bar', 'dining', 8],
                    ['In-Room Electronic Safe', 'lock', 'comfort', 9],
                    ['Air Conditioning', 'ac_unit', 'comfort', 10],
                    ['Organic Bath Amenities', 'soap', 'bathroom', 11],
                    ['Rainfall Shower', 'shower', 'bathroom', 12],
                    ['Deep Soaking Tub & Rain Shower', 'bathtub', 'bathroom', 13],
                    ['Marble Bathroom with Free-standing Tub', 'hot_tub', 'bathroom', 14],
                    ['Work Desk & Ergonomic Chair', 'desk', 'comfort', 15],
                    ['Daily Housekeeping', 'cleaning_services', 'comfort', 16],
                    ['Private Balcony with Seating', 'balcony', 'comfort', 17],
                    ['Expansive Private Panoramic Balcony', 'deck', 'comfort', 18],
                    ['Plush Bathrobes & Slippers', 'apparel', 'comfort', 19],
                    ['Lounge Seating Area', 'weekend', 'comfort', 20],
                    ['Separate Living Room', 'meeting_room', 'comfort', 21],
                    ['Walk-in Dressing Closet', 'checkroom', 'comfort', 22],
                    ['VIP Airport Transfer Option', 'airport_shuttle', 'luxury', 23]
                ];

                $stmt = $pdo->prepare("INSERT " . ($driver === 'mysql' ? "IGNORE" : "OR IGNORE") . " INTO amenities (name, icon, category, display_order) VALUES (?, ?, ?, ?)");
                foreach ($defaultAmenities as $a) {
                    $stmt->execute($a);
                }
            }
        } catch (Throwable $t) {
            // Ignore if creation fails
        }
        self::migrateLocationSpotsTable($pdo, $driver);
    }

    private static function migrateLocationSpotsTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `location_spots` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `slug` VARCHAR(255) NULL UNIQUE,
                    `title` VARCHAR(255) NOT NULL,
                    `distance_time` VARCHAR(100) NOT NULL,
                    `category` VARCHAR(50) NOT NULL DEFAULT 'attraction',
                    `icon` VARCHAR(50) NOT NULL DEFAULT 'location_on',
                    `image_url` VARCHAR(500) NULL,
                    `gallery_json` LONGTEXT NULL,
                    `opening_hours` VARCHAR(100) NULL,
                    `admission_fee` VARCHAR(100) NULL,
                    `address` VARCHAR(255) NULL,
                    `map_embed` TEXT NULL,
                    `tips` TEXT NULL,
                    `description` TEXT NOT NULL,
                    `translations_json` LONGTEXT NULL,
                    `display_order` INT NOT NULL DEFAULT 99,
                    `is_featured` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                // Ensure columns exist for existing installations
                $cols = $pdo->query("SHOW COLUMNS FROM `location_spots`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('slug', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `slug` VARCHAR(255) NULL");
                }
                if (!in_array('gallery_json', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `gallery_json` LONGTEXT NULL");
                }
                if (!in_array('opening_hours', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `opening_hours` VARCHAR(100) NULL");
                }
                if (!in_array('admission_fee', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `admission_fee` VARCHAR(100) NULL");
                }
                if (!in_array('address', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `address` VARCHAR(255) NULL");
                }
                if (!in_array('map_embed', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `map_embed` TEXT NULL");
                }
                if (!in_array('tips', $cols)) {
                    $pdo->exec("ALTER TABLE `location_spots` ADD COLUMN `tips` TEXT NULL");
                }
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `location_spots` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `slug` TEXT NULL UNIQUE,
                    `title` TEXT NOT NULL,
                    `distance_time` TEXT NOT NULL,
                    `category` TEXT NOT NULL DEFAULT 'attraction',
                    `icon` TEXT NOT NULL DEFAULT 'location_on',
                    `image_url` TEXT NULL,
                    `gallery_json` TEXT NULL,
                    `opening_hours` TEXT NULL,
                    `admission_fee` TEXT NULL,
                    `address` TEXT NULL,
                    `map_embed` TEXT NULL,
                    `tips` TEXT NULL,
                    `description` TEXT NOT NULL,
                    `translations_json` TEXT NULL,
                    `display_order` INTEGER NOT NULL DEFAULT 99,
                    `is_featured` INTEGER NOT NULL DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // Pragma check
                $cols = $pdo->query("PRAGMA table_info(location_spots)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                foreach (['slug', 'gallery_json', 'opening_hours', 'admission_fee', 'address', 'map_embed', 'tips'] as $nc) {
                    if (!in_array($nc, $colNames)) {
                        $pdo->exec("ALTER TABLE location_spots ADD COLUMN {$nc} TEXT NULL");
                    }
                }
            }

            // Seed default location spots if table is empty
            $count = (int)$pdo->query("SELECT COUNT(*) FROM location_spots")->fetchColumn();
            if ($count === 0) {
                $defaultSpots = [
                    [
                        'wat-phnom-sanctuary',
                        'Wat Phnom Sanctuary',
                        '10 Minutes Drive',
                        'cultural',
                        'temple_buddhist',
                        'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?auto=format&fit=crop&w=1200&q=80',
                        '07:00 AM - 06:00 PM Daily',
                        '$1.00 USD (Foreign Visitors)',
                        'Street 96, Norodom Blvd, Daun Penh, Phnom Penh',
                        'Wear modest attire covering shoulders and knees. Best visited during early morning or late afternoon to avoid midday sun.',
                        'A revered 14th-century Buddhist pagoda standing at 27 meters tall, Wat Phnom is the city\'s historical heart and legendary founding site.',
                        1,
                        1
                    ],
                    [
                        'royal-palace-silver-pagoda',
                        'Royal Palace & Silver Pagoda',
                        '15 Minutes Drive',
                        'cultural',
                        'account_balance',
                        'https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1200&q=80',
                        '08:00 AM - 11:00 AM, 02:00 PM - 05:00 PM',
                        '$10.00 USD / Person',
                        'Samdach Sothearos Blvd, Phnom Penh',
                        'Strict dress code enforced (no sleeveless tops, shorts, or short skirts). Morning visits allow comfortable walking across the marble and silver tile courtyard.',
                        'The magnificent royal residence featuring classic Khmer architecture, manicured French-style gardens, and the famed Emerald Buddha.',
                        2,
                        1
                    ],
                    [
                        'phnom-penh-night-market',
                        'Phnom Penh Night Market',
                        '12 Minutes Drive',
                        'attraction',
                        'storefront',
                        'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1200&q=80',
                        '05:00 PM - 11:00 PM (Friday - Sunday)',
                        'Free Admission',
                        'Preah Mohaksat Treiyani Kossamak, Riverfront, Phnom Penh',
                        'Bring small USD or Khmer Riel cash denominations. Enjoy hot skewers and coconut ice cream on woven floor mats while listening to live acoustic music.',
                        'Lively open-air bazaar along the Tonle Sap riverside, offering local silk textiles, handcrafted souvenirs, live music, and street gastronomy.',
                        3,
                        1
                    ],
                    [
                        'tk-avenue-mall',
                        'TK Avenue Mall',
                        '3 Minutes Walk',
                        'shopping',
                        'shopping_bag',
                        'https://images.unsplash.com/photo-1567449303183-ae0d6ed1498e?auto=format&fit=crop&w=1200&q=80',
                        '09:00 AM - 10:00 PM Daily',
                        'Free Admission',
                        'Corner of St 315 & St 516, Tuol Kork, Phnom Penh',
                        'Just a brief stroll from Indra Hotel. Ideal for banking ATMs, premium supermarkets, Legend Cinema, and specialty coffee roaster cafes.',
                        'Premier outdoor boutique lifestyle mall in Tuol Kork with international fashion, boutique cinema, supermarkets, and upscale cafes.',
                        4,
                        1
                    ]
                ];

                $stmt = $pdo->prepare("INSERT INTO location_spots (slug, title, distance_time, category, icon, image_url, opening_hours, admission_fee, address, tips, description, display_order, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaultSpots as $spot) {
                    $stmt->execute($spot);
                }
            } else {
                // Ensure existing spots have valid slugs
                $existing = $pdo->query("SELECT id, title, slug FROM location_spots WHERE slug IS NULL OR slug = ''")->fetchAll();
                foreach ($existing as $ex) {
                    $genSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $ex['title']), '-'));
                    $pdo->prepare("UPDATE location_spots SET slug = ? WHERE id = ?")->execute([$genSlug, $ex['id']]);
                }
            }
        } catch (Throwable $t) {
            // Ignore if creation fails
        }
        self::migrateRoomTypesTable($pdo, $driver);
    }

    private static function migrateRoomTypesTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `room_types` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(150) NOT NULL UNIQUE,
                    `slug` VARCHAR(150) NOT NULL UNIQUE,
                    `icon` VARCHAR(50) NOT NULL DEFAULT 'hotel',
                    `badge_color` VARCHAR(50) NOT NULL DEFAULT 'emerald',
                    `description` TEXT NULL,
                    `translations_json` LONGTEXT NULL,
                    `display_order` INT NOT NULL DEFAULT 99,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                // Ensure columns exist on rooms table
                $cols = $pdo->query("SHOW COLUMNS FROM `rooms`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('room_type_id', $cols)) {
                    $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `room_type_id` INT NULL");
                }
                if (!in_array('category', $cols)) {
                    $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'Deluxe Room'");
                }
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `room_types` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` TEXT NOT NULL UNIQUE,
                    `slug` TEXT NOT NULL UNIQUE,
                    `icon` TEXT NOT NULL DEFAULT 'hotel',
                    `badge_color` TEXT NOT NULL DEFAULT 'emerald',
                    `description` TEXT NULL,
                    `translations_json` TEXT NULL,
                    `display_order` INTEGER NOT NULL DEFAULT 99,
                    `is_active` INTEGER NOT NULL DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // Pragma check on rooms
                $cols = $pdo->query("PRAGMA table_info(rooms)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                if (!in_array('room_type_id', $colNames)) {
                    $pdo->exec("ALTER TABLE rooms ADD COLUMN room_type_id INTEGER NULL");
                }
                if (!in_array('category', $colNames)) {
                    $pdo->exec("ALTER TABLE rooms ADD COLUMN category TEXT NOT NULL DEFAULT 'Deluxe Room'");
                }
            }

            // Seed default room types if table is empty
            $count = (int)$pdo->query("SELECT COUNT(*) FROM room_types")->fetchColumn();
            if ($count === 0) {
                $defaultTypes = [
                    [
                        'Deluxe Room',
                        'deluxe-room',
                        'hotel',
                        'emerald',
                        'Refined contemporary accommodation featuring plush bedding, artisan espresso station, and ergonomic workspace.',
                        1,
                        1
                    ],
                    [
                        'Suite Room',
                        'suite-room',
                        'apartment',
                        'amber',
                        'Expansive luxury living quarters with separate lounge area, marble deep soaking tub, and panoramic views.',
                        2,
                        1
                    ],
                    [
                        'Conference Room',
                        'conference-room',
                        'meeting_room',
                        'indigo',
                        'Flexible meeting, boardroom, and banquet space equipped with 4K audiovisual presentation tech and high-speed Wi-Fi.',
                        3,
                        1
                    ],
                    [
                        'City View Room',
                        'city-view-room',
                        'location_city',
                        'blue',
                        'Bright, elevated room with floor-to-ceiling windows offering sweeping vistas across Phnom Penh.',
                        4,
                        1
                    ]
                ];

                $stmt = $pdo->prepare("INSERT INTO room_types (name, slug, icon, badge_color, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaultTypes as $t) {
                    $stmt->execute($t);
                }
            }

            // Auto-link existing rooms with matching room_type_id
            $pdo->exec("UPDATE rooms SET category = 'Suite Room' WHERE name LIKE '%Suite%' AND (category IS NULL OR category = '' OR category = 'Deluxe Room')");
            $pdo->exec("UPDATE rooms SET category = 'Conference Room' WHERE (name LIKE '%Conference%' OR name LIKE '%Meeting%') AND (category IS NULL OR category = '' OR category = 'Deluxe Room')");
            $pdo->exec("UPDATE rooms SET category = 'City View Room' WHERE (name LIKE '%City%' OR tagline LIKE '%City%') AND (category IS NULL OR category = '' OR category = 'Deluxe Room')");
        } catch (Throwable $t) {
            // Ignore if creation fails
        }
        self::migrateEmailLogsTable($pdo, $driver);
    }

    private static function migrateEmailLogsTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `email_logs` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `recipient` VARCHAR(255) NOT NULL,
                        `subject` VARCHAR(255) NOT NULL,
                        `driver` VARCHAR(50) NOT NULL DEFAULT 'mail',
                        `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
                        `message` TEXT NULL,
                        `log` LONGTEXT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } else {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS email_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        recipient TEXT NOT NULL,
                        subject TEXT NOT NULL,
                        driver TEXT NOT NULL DEFAULT 'mail',
                        status TEXT NOT NULL DEFAULT 'pending',
                        message TEXT,
                        log TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");
            }
        } catch (Throwable $t) {
            // Ignore if table creation fails
        }
        self::migrateBookingOffersColumn($pdo, $driver);
    }

    private static function migrateBookingOffersColumn(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $cols = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'promo_code'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `promo_code` VARCHAR(50) NULL");
                }
                $colsOffer = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'offer_title'")->fetchAll();
                if (empty($colsOffer)) {
                    $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `offer_title` VARCHAR(150) NULL");
                }
                $colsDisc = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'discount_amount'")->fetchAll();
                if (empty($colsDisc)) {
                    $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                if (!in_array('promo_code', $colNames)) {
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN promo_code TEXT NULL");
                }
                if (!in_array('offer_title', $colNames)) {
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN offer_title TEXT NULL");
                }
                if (!in_array('discount_amount', $colNames)) {
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN discount_amount NUMERIC NOT NULL DEFAULT 0");
                }
            }
        } catch (Throwable $t) {
            // Ignore if column migration fails
        }
        self::migrateSpecialOffersExternalUrlColumn($pdo, $driver);
        self::migrateGoogleOAuthSettings($pdo, $driver);
        self::migrateInvitationsAndResetsTables($pdo, $driver);
        self::migrateGuestUsersTable($pdo, $driver);
    }

    private static function migrateSpecialOffersExternalUrlColumn(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $cols = $pdo->query("SHOW COLUMNS FROM `special_offers` LIKE 'external_url'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `special_offers` ADD COLUMN `external_url` VARCHAR(255) NULL");
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(special_offers)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                if (!in_array('external_url', $colNames)) {
                    $pdo->exec("ALTER TABLE special_offers ADD COLUMN external_url TEXT NULL");
                }
            }
        } catch (Throwable $t) {
            // Ignore if column migration fails
        }
    }

    private static function migrateGoogleOAuthSettings(PDO $pdo, string $driver): void {
        try {
            // 1. Add google_id to users table
            if ($driver === 'mysql') {
                $cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'google_id'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(100) NULL AFTER `avatar`");
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                if (!in_array('google_id', $colNames)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN google_id TEXT NULL");
                }
            }

            // 2. Default settings initialization
            $defaultSettings = [
                'google_oauth_enabled' => '0',
                'google_oauth_client_id' => '',
                'google_oauth_client_secret' => '',
                'google_oauth_allowed_domain' => '',
                'google_oauth_auto_create_user' => '0',
                'google_oauth_default_role' => 'editor'
            ];

            foreach ($defaultSettings as $key => $defaultVal) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key = ?");
                $exists->execute([$key]);
                if ((int)$exists->fetchColumn() === 0) {
                    $insert = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$key, $defaultVal]);
                }
            }
        } catch (Throwable $t) {
            // Ignore if settings migration fails
        }
    }

    private static function migrateInvitationsAndResetsTables(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `user_invitations` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(190) NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `role` VARCHAR(50) NOT NULL DEFAULT 'editor',
                    `token` VARCHAR(128) NOT NULL UNIQUE,
                    `invited_by` INT NULL,
                    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `accepted_at` DATETIME NULL,
                    INDEX `idx_invitation_token` (`token`),
                    INDEX `idx_invitation_email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(190) NOT NULL,
                    `token` VARCHAR(128) NOT NULL UNIQUE,
                    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `used_at` DATETIME NULL,
                    INDEX `idx_reset_token` (`token`),
                    INDEX `idx_reset_email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_invitations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL,
                    name TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'editor',
                    token TEXT NOT NULL UNIQUE,
                    invited_by INTEGER NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    accepted_at DATETIME NULL
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL,
                    token TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'pending',
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    used_at DATETIME NULL
                )");
            }
        } catch (Throwable $t) {
            // Ignore migration error
        }
    }

    private static function migrateGuestUsersTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `guest_users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(150) NOT NULL,
                    `email` VARCHAR(190) NOT NULL UNIQUE,
                    `phone` VARCHAR(50) NULL,
                    `avatar` VARCHAR(500) NULL,
                    `google_id` VARCHAR(100) NULL,
                    `auth_provider` VARCHAR(50) NOT NULL DEFAULT 'direct_booking',
                    `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                    `notes` TEXT NULL,
                    `last_login_at` DATETIME NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_guest_email` (`email`),
                    INDEX `idx_guest_google_id` (`google_id`),
                    INDEX `idx_guest_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS guest_users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    phone TEXT NULL,
                    avatar TEXT NULL,
                    google_id TEXT NULL,
                    auth_provider TEXT NOT NULL DEFAULT 'direct_booking',
                    status TEXT NOT NULL DEFAULT 'active',
                    notes TEXT NULL,
                    last_login_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }

            // Auto-Backfill unique guests from existing bookings table
            try {
                $stmtBookers = $pdo->query("SELECT guest_name, guest_email, guest_phone, MIN(created_at) as first_booked FROM bookings WHERE guest_email IS NOT NULL AND TRIM(guest_email) != '' GROUP BY LOWER(guest_email)");
                if ($stmtBookers) {
                    $bookers = $stmtBookers->fetchAll();
                    $stmtCheck = $pdo->prepare("SELECT id FROM guest_users WHERE LOWER(email) = ? LIMIT 1");
                    $stmtInsert = $pdo->prepare("INSERT INTO guest_users (name, email, phone, auth_provider, status, created_at) VALUES (?, ?, ?, 'direct_booking', 'active', ?)");
                    foreach ($bookers as $b) {
                        $cleanEmail = strtolower(trim($b['guest_email']));
                        $stmtCheck->execute([$cleanEmail]);
                        if (!$stmtCheck->fetch()) {
                            $stmtInsert->execute([
                                trim($b['guest_name'] ?: 'Guest Booker'),
                                $cleanEmail,
                                trim($b['guest_phone'] ?? ''),
                                $b['first_booked'] ?: date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                }
            } catch (Throwable $e) {}
        } catch (Throwable $t) {
            // Ignore migration error
        }
        self::migrateMediaUploadsTable($pdo, $driver);
    }

    private static function migrateMediaUploadsTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `media_uploads` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `filename` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NULL,
                    `file_path` VARCHAR(255) NOT NULL,
                    `url` TEXT NOT NULL,
                    `folder` VARCHAR(50) NOT NULL DEFAULT 'general',
                    `file_type` VARCHAR(20) NOT NULL DEFAULT 'image',
                    `mime_type` VARCHAR(100) NULL,
                    `file_size` INT NOT NULL DEFAULT 0,
                    `uploaded_by` INT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_media_folder` (`folder`),
                    INDEX `idx_media_type` (`file_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS media_uploads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    filename TEXT NOT NULL,
                    original_name TEXT NULL,
                    file_path TEXT NOT NULL,
                    url TEXT NOT NULL,
                    folder TEXT NOT NULL DEFAULT 'general',
                    file_type TEXT NOT NULL DEFAULT 'image',
                    mime_type TEXT NULL,
                    file_size INTEGER NOT NULL DEFAULT 0,
                    uploaded_by INTEGER NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }

            self::syncUploadsFolderToDatabase($pdo);
        } catch (Throwable $t) {
            // Ignore migration error
        }
        self::migrateSessionsTable($pdo, $driver);
    }

    private static function migrateSessionsTable(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
                    `id` VARCHAR(191) PRIMARY KEY,
                    `data` LONGTEXT NOT NULL,
                    `last_activity` INT NOT NULL,
                    INDEX `idx_sessions_last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                    id TEXT PRIMARY KEY,
                    data TEXT NOT NULL,
                    last_activity INTEGER NOT NULL
                )");
            }
        } catch (Throwable $t) {}
    }

    public static function syncUploadsFolderToDatabase(PDO $pdo): void {
        try {
            $uploadsDir = ROOT_PATH . '/uploads';
            if (!is_dir($uploadsDir)) return;

            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $stmtCheck = $pdo->prepare("SELECT id FROM media_uploads WHERE filename = ? LIMIT 1");
            $stmtInsert = $pdo->prepare("INSERT INTO media_uploads (filename, original_name, file_path, url, folder, file_type, mime_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];
            $videoExts = ['mp4', 'webm', 'ogv', 'mov', 'm4v'];

            foreach ($items as $item) {
                if ($item->isFile()) {
                    $filename = $item->getFilename();
                    if (str_ends_with($filename, '.part') || str_starts_with($filename, '.')) {
                        continue;
                    }

                    $stmtCheck->execute([$filename]);
                    if ($stmtCheck->fetch()) {
                        continue;
                    }

                    $fullPath = $item->getPathname();
                    $relPath = str_replace(ROOT_PATH, '', $fullPath);
                    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
                    
                    $subPath = str_replace('uploads/', '', $relPath);
                    $parts = explode('/', $subPath);
                    $folder = count($parts) > 1 ? $parts[0] : 'general';

                    if ($folder === 'chunks') continue;

                    $url = BASE_URL . '/' . $relPath;
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    $fileType = 'other';
                    if (in_array($ext, $imageExts, true)) {
                        $fileType = 'image';
                    } elseif (in_array($ext, $videoExts, true)) {
                        $fileType = 'video';
                    }

                    $mimeType = function_exists('mime_content_type') ? @mime_content_type($fullPath) : null;
                    $size = $item->getSize();
                    $mtime = date('Y-m-d H:i:s', $item->getMTime());

                    $stmtInsert->execute([
                        $filename,
                        $filename,
                        $relPath,
                        $url,
                        $folder,
                        $fileType,
                        $mimeType ?: null,
                        $size,
                        $mtime
                    ]);
                }
            }
        } catch (Throwable $e) {}
    }


    public static function executeSqlFile(PDO $pdo, string $filePath): void {
        if (!file_exists($filePath)) {
            return;
        }
        $rawSql = file_get_contents($filePath);
        if (empty(trim($rawSql))) {
            return;
        }

        $sqlClean = preg_replace('/^--.*$/m', '', $rawSql);
        $statements = array_filter(array_map('trim', explode(';', $sqlClean)));

        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (Throwable $e) {
                    error_log("SQL Execution Notice: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Run Schema & Initial Seed
     */
    public static function initializeDatabase(PDO $pdo, string $driver): bool {
        if ($driver === 'mysql') {
            self::executeSqlFile($pdo, ROOT_PATH . '/schema.sql');
            self::executeSqlFile($pdo, ROOT_PATH . '/seed.sql');
        } else {
            // SQLite schema
            $sqliteSchema = "
            CREATE TABLE IF NOT EXISTS users (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              email TEXT NOT NULL UNIQUE,
              password_hash TEXT NOT NULL,
              role TEXT NOT NULL DEFAULT 'admin',
              avatar TEXT,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS rooms (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              slug TEXT NOT NULL UNIQUE,
              tagline TEXT,
              description TEXT NOT NULL,
              price_per_night REAL NOT NULL DEFAULT 0.00,
              size_sqm INTEGER NOT NULL DEFAULT 35,
              bed_type TEXT NOT NULL DEFAULT '1 King Bed',
              capacity_adults INTEGER NOT NULL DEFAULT 2,
              capacity_children INTEGER NOT NULL DEFAULT 1,
              view_type TEXT DEFAULT 'City View',
              image_url TEXT NOT NULL,
              gallery_json TEXT,
              amenities_json TEXT,
              translations_json TEXT,
              status TEXT NOT NULL DEFAULT 'available',
              is_featured INTEGER NOT NULL DEFAULT 0,
              display_order INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS bookings (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              booking_reference TEXT NOT NULL UNIQUE,
              room_id INTEGER NOT NULL,
              guest_name TEXT NOT NULL,
              guest_email TEXT NOT NULL,
              guest_phone TEXT NOT NULL,
              check_in_date DATE NOT NULL,
              check_out_date DATE NOT NULL,
              adults INTEGER NOT NULL DEFAULT 1,
              children INTEGER NOT NULL DEFAULT 0,
              nights INTEGER NOT NULL DEFAULT 1,
              room_rate REAL NOT NULL DEFAULT 0.00,
              total_price REAL NOT NULL DEFAULT 0.00,
              status TEXT NOT NULL DEFAULT 'pending',
              payment_status TEXT NOT NULL DEFAULT 'unpaid',
              special_requests TEXT,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS dining_wellness (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              type TEXT NOT NULL,
              title TEXT NOT NULL,
              subtitle TEXT,
              description TEXT NOT NULL,
              hours TEXT,
              price_range TEXT,
              image_url TEXT NOT NULL,
              menu_items_json TEXT,
              translations_json TEXT,
              is_published INTEGER NOT NULL DEFAULT 1,
              display_order INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS special_offers (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              title TEXT NOT NULL,
              promo_code TEXT,
              discount_percent INTEGER NOT NULL DEFAULT 0,
              description TEXT NOT NULL,
              valid_from DATE,
              valid_to DATE,
              image_url TEXT NOT NULL,
              badge_text TEXT,
              translations_json TEXT,
              is_active INTEGER NOT NULL DEFAULT 1,
              display_order INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS gallery (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              title TEXT NOT NULL,
              category TEXT NOT NULL DEFAULT 'rooms',
              image_url TEXT NOT NULL,
              caption TEXT,
              display_order INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS messages (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              email TEXT NOT NULL,
              phone TEXT,
              subject TEXT NOT NULL,
              message TEXT NOT NULL,
              status TEXT NOT NULL DEFAULT 'unread',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS site_settings (
              setting_key TEXT PRIMARY KEY,
              setting_value TEXT,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS custom_pages (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              slug TEXT NOT NULL UNIQUE,
              title TEXT NOT NULL,
              meta_title TEXT,
              meta_description TEXT,
              hero_badge TEXT,
              hero_title TEXT,
              hero_subtitle TEXT,
              hero_image TEXT,
              hero_video TEXT,
              hero_height TEXT DEFAULT 'medium',
              hero_overlay TEXT DEFAULT 'medium',
              theme_id TEXT DEFAULT 'default',
              layout_type TEXT DEFAULT 'full_width',
              sections_json TEXT,
              status TEXT DEFAULT 'published',
              is_in_nav INTEGER DEFAULT 0,
              nav_order INTEGER DEFAULT 99,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS theme_licenses (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              theme_slug TEXT NOT NULL UNIQUE,
              is_purchased INTEGER DEFAULT 0,
              is_active INTEGER DEFAULT 0,
              purchase_date DATETIME,
              custom_styles_json TEXT,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ";
            $pdo->exec($sqliteSchema);
            self::seedSqliteData($pdo);
        }
        return true;
    }

    private static function seedSqliteData(PDO $pdo): void {
        // Seed Admin User
        $chkUsers = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch();
        if (!$chkUsers || (int)$chkUsers['c'] === 0) {
            $passwordHash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)")
                ->execute([DEFAULT_ADMIN_NAME, DEFAULT_ADMIN_EMAIL, $passwordHash, 'admin']);
        }

        // Seed Rooms
        $chkRooms = $pdo->query("SELECT COUNT(*) as c FROM rooms")->fetch();
        if ($chkRooms && (int)$chkRooms['c'] > 0) {
            return;
        }
        $rooms = [
            [
                'name' => 'Deluxe King Room',
                'slug' => 'deluxe-king',
                'tagline' => 'Contemporary Sanctuary for Modern Travelers',
                'desc' => 'With modern and functional decor, our Deluxe King Room offers a serene and cozy environment perfect for unwinding and relaxation. Features a plush king-sized bed, high-speed WiFi, flat-screen smart TV, private minibar, artisan coffee station, and a luxurious en-suite bathroom stocked with organic bath amenities.',
                'price' => 85.00,
                'sqm' => 38,
                'bed' => '1 King Bed',
                'adults' => 2,
                'children' => 1,
                'view' => 'City View',
                'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLs-aT78HuC87zVBtlw78IAcVeqttvz1DzuDCFkgHQa0GjAqpJ7QEgkbAcIsJqkihNQyvb5xyVUyfpGCLZpUTsGnoI_Zd2-vfG3hGgzgX8ILxOjMzXh6zptxCM2mUT_5SpyirJMD2KE7cfOEm3QbJ69fnH6VMjX0sVrsXmM5Jm-XtIvZg9-B3Mseq2ffcjkq7LNDstvkdy5lRLn-NIFXZWhKbwJ1tFsvSH-_tFiOrkWNTCNfDxbD8XbxG-j2',
                'gallery' => json_encode([
                    'https://lh3.googleusercontent.com/aida/AP1WRLs-aT78HuC87zVBtlw78IAcVeqttvz1DzuDCFkgHQa0GjAqpJ7QEgkbAcIsJqkihNQyvb5xyVUyfpGCLZpUTsGnoI_Zd2-vfG3hGgzgX8ILxOjMzXh6zptxCM2mUT_5SpyirJMD2KE7cfOEm3QbJ69fnH6VMjX0sVrsXmM5Jm-XtIvZg9-B3Mseq2ffcjkq7LNDstvkdy5lRLn-NIFXZWhKbwJ1tFsvSH-_tFiOrkWNTCNfDxbD8XbxG-j2',
                    'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80'
                ]),
                'amenities' => json_encode(['High-Speed Wi-Fi','55" Smart Cable TV','Artisan Coffee Maker','Minibar & Refrigerator','In-Room Electronic Safe','Air Conditioning','Organic Bath Amenities','Rainfall Shower','Work Desk & Ergonomic Chair','Daily Housekeeping']),
                'trans' => json_encode([
                    'en' => ['name' => 'Deluxe King Room', 'tagline' => 'Contemporary Sanctuary for Modern Travelers', 'description' => 'With modern and functional decor, our Deluxe King Room offers a serene and cozy environment perfect for unwinding and relaxation.'],
                    'km' => ['name' => 'បន្ទប់ ឌីឡាក់ឃីង (Deluxe King)', 'tagline' => 'ទីជម្រកដ៏ស្ងប់ស្ងាត់សម្រាប់អ្នកដំណើរទាន់សម័យ', 'description' => 'ជាមួយនឹងការតុបតែងបែបទំនើប និងមានមុខងារ បន្ទប់ ឌីឡាក់ឃីង របស់យើងផ្តល់នូវបរិយាកាសស្ងប់ស្ងាត់ និងកក់ក្តៅ ស័ក្តិសមបំផុតសម្រាប់ការសម្រាកលំហែកាយ។'],
                    'zh' => ['name' => '豪华大床房 (Deluxe King)', 'tagline' => '专为现代商务与休闲旅客打造的当代庇护所', 'description' => '豪华大床房拥有现代实用的装潢，营造出宁静舒适的居停环境，是放松身心的理想之选。配有豪华特大床与高速无线网络。'],
                    'ko' => ['name' => '디럭스 킹 룸 (Deluxe King)', 'tagline' => '현대 여행자를 위한 도심 속 안식처', 'description' => '모던하고 실용적인 인테리어를 갖춘 디럭스 킹 룸은 휴식과 재충전을 위한 평온하고 아늑한 분위기를 제공합니다.']
                ], JSON_UNESCAPED_UNICODE),
                'featured' => 1,
                'order' => 1
            ],
            [
                'name' => 'Junior Suite with Balcony',
                'slug' => 'junior-suite',
                'tagline' => 'Spacious Urban Retreat with Private Terrace',
                'desc' => 'Enjoy the added luxury of a private terrace balcony overlooking the serene Tuol Kork neighborhood. The Junior Suite combines refined contemporary aesthetics with expansive living spaces, a dedicated lounge seating area, custom wooden furnishings, and premium bathrobes for optimal relaxation.',
                'price' => 125.00,
                'sqm' => 48,
                'bed' => '1 King Bed + Daybed',
                'adults' => 3,
                'children' => 1,
                'view' => 'Garden & City View',
                'img' => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80',
                'gallery' => json_encode([
                    'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=1200&q=80'
                ]),
                'amenities' => json_encode(['Private Balcony with Seating','High-Speed Wi-Fi','65" 4K Smart TV','Espresso Machine','Well-Stocked Minibar','Deep Soaking Tub & Rain Shower','Organic Toiletries','Plush Bathrobes & Slippers','Lounge Seating Area','Safety Deposit Box']),
                'trans' => json_encode([
                    'en' => ['name' => 'Junior Suite with Balcony', 'tagline' => 'Spacious Urban Retreat with Private Terrace', 'description' => 'Enjoy the added luxury of a private terrace balcony overlooking the serene Tuol Kork neighborhood.'],
                    'km' => ['name' => 'បន្ទប់ ជូនៀស៊្វីត មានយ៉រ (Junior Suite)', 'tagline' => 'បន្ទប់ធំទូលាយប្រណិត ជាមួយយ៉រផ្ទាល់ខ្លួន', 'description' => 'រីករាយជាមួយភាពប្រណិតបន្ថែមនៃយ៉រផ្ទាល់ខ្លួនដែលមើលឃើញទេសភាពដ៏ស្ងប់ស្ងាត់នៃខណ្ឌទួលគោក។'],
                    'zh' => ['name' => '阳台轻奢套房 (Junior Suite)', 'tagline' => '拥有私人景观阳台的宽敞都市居所', 'description' => '专享俯瞰堆谷区宁静街景的私人阳台露台。轻奢套房将精致现代美学与宽敞居住空间融为一体。'],
                    'ko' => ['name' => '발코니 주니어 스위트 (Junior Suite)', 'tagline' => '개인 테라스 발코니를 갖춘 여유로운 휴식 공간', 'description' => '툴콕 지역의 평화로운 전망을 감상할 수 있는 프라이빗 발코니를 즐겨보세요.']
                ], JSON_UNESCAPED_UNICODE),
                'featured' => 1,
                'order' => 2
            ],
            [
                'name' => 'Indra Suite with Balcony',
                'slug' => 'indra-suite',
                'tagline' => 'The Pinnacle of Khmer Elegance & Luxury',
                'desc' => 'Our signature residence exudes prestigious comfort and sustainable charm. Featuring an expansive master bedroom, separate open-plan living room, private sun terrace balcony with lush greenery, walk-in closet, and spa-inspired marble bathroom. Perfect for discerning travelers seeking an elite haven in Phnom Penh.',
                'price' => 180.00,
                'sqm' => 68,
                'bed' => '1 Grand King Bed',
                'adults' => 3,
                'children' => 2,
                'view' => 'Panoramic Skyline View',
                'img' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80',
                'gallery' => json_encode([
                    'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1591088398332-8a7791972843?auto=format&fit=crop&w=1200&q=80'
                ]),
                'amenities' => json_encode(['Expansive Private Panoramic Balcony','Separate Living Room','High-Speed Wi-Fi','Two 65" Smart TVs','Signature Nespresso Station','Complimentary Premium Minibar','Marble Bathroom with Free-standing Tub','Walk-in Dressing Closet','In-Room Dining Service','VIP Airport Transfer Option']),
                'trans' => json_encode([
                    'en' => ['name' => 'Indra Suite with Balcony', 'tagline' => 'The Pinnacle of Khmer Elegance & Luxury', 'description' => 'Our signature residence exudes prestigious comfort and sustainable charm.'],
                    'km' => ['name' => 'បន្ទប់ ឥន្ទ្រាស៊្វីត មានយ៉រ (Indra Suite)', 'tagline' => 'កំពូលនៃភាពថ្លៃថ្នូរ និងប្រណិតភាពបែបខ្មែរ', 'description' => 'បន្ទប់ស្នាក់នៅដ៏ឆ្នើមរបស់យើងបង្ហាញពីផាសុកភាពដ៏ឧត្តុង្គឧត្តម។ មានបន្ទប់គេងមេធំទូលាយ បន្ទប់ទទួលភ្ញៀវដាច់ដោយឡែក។'],
                    'zh' => ['name' => '因陀罗尊贵全景套房 (Indra Suite)', 'tagline' => '高棉典雅与奢华居停的至臻典范', 'description' => '酒店旗舰级行政套房，尽显尊崇舒适与环保魅力。配备超大主卧、独立开放式起居室与全景阳台。'],
                    'ko' => ['name' => '인드라 프레지덴셜 스위트 (Indra Suite)', 'tagline' => '크메르의 우아함과 최상의 럭셔리가 깃든 시그니처 객실', 'description' => '인드라 호텔의 시그니처 스위트로 최고의 품격과 편안함을 선사합니다.']
                ], JSON_UNESCAPED_UNICODE),
                'featured' => 1,
                'order' => 3
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO rooms (name, slug, tagline, description, price_per_night, size_sqm, bed_type, capacity_adults, capacity_children, view_type, image_url, gallery_json, amenities_json, translations_json, status, is_featured, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?)");
        foreach ($rooms as $r) {
            $stmt->execute([$r['name'], $r['slug'], $r['tagline'], $r['desc'], $r['price'], $r['sqm'], $r['bed'], $r['adults'], $r['children'], $r['view'], $r['img'], $r['gallery'], $r['amenities'], $r['trans'], $r['featured'], $r['order']]);
        }

        // Seed Dining & Wellness
        $dw = [
            [
                'type' => 'dining',
                'title' => 'The Indra Bistro & Cafe',
                'subtitle' => 'Artisanal Coffee & Contemporary International Dining',
                'desc' => 'Indulge in a range of culinary experiences featuring international and Asian-fusion cuisine with a contemporary twist. From a delectable breakfast at the cafe to fine dining and sunset cocktails, our sophisticated surroundings provide the perfect backdrop for an unforgettable dining experience.',
                'hours' => '06:30 AM - 10:30 PM',
                'price' => '$$ - $$$',
                'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho',
                'menu' => json_encode([
                    ['name' => 'Traditional Khmer Fish Amok', 'price' => '$14.00', 'desc' => 'Steamed sea bass fillet in rich aromatic coconut lemongrass curry served in banana leaf.'],
                    ['name' => 'Angus Ribeye Steak 250g', 'price' => '$28.00', 'desc' => 'Char-grilled prime beef served with Kampot green peppercorn sauce and truffle mashed potatoes.'],
                    ['name' => 'Signature Indra Benedict', 'price' => '$9.50', 'desc' => 'Poached farm eggs, smoked salmon, avocado, brioche bun, and homemade yuzu hollandaise.'],
                    ['name' => 'Kampot Pepper Crab Fried Rice', 'price' => '$12.50', 'desc' => 'Fragrant Jasmine rice wok-tossed with fresh blue crab meat, garlic, and fresh Kampot pepper.']
                ]),
                'order' => 1
            ],
            [
                'type' => 'wellness',
                'title' => 'Fitness Center & Saltwater Pool',
                'subtitle' => 'State-of-the-Art Training & Serene Swimming',
                'desc' => 'Our guests can stay fit and healthy with our fully equipped fitness center, open from 7:00am till 9:00pm. Complete with state-of-the-art training gear, cardiovascular machines, free weights, and a refreshing outdoor saltwater pool nestled in a lush tropical garden setting.',
                'hours' => '07:00 AM - 09:00 PM',
                'price' => 'Complimentary for Guests',
                'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5',
                'menu' => json_encode([
                    ['name' => 'Traditional Khmer Herbal Massage (60 mins)', 'price' => '$35.00', 'desc' => 'Ancient pressure-point therapy using warm herbal compresses to relieve tension and restore balance.'],
                    ['name' => 'Aromatherapy Deep Tissue (90 mins)', 'price' => '$50.00', 'desc' => 'Custom blend of botanical essential oils tailored to alleviate fatigue and rejuvenate sore muscles.'],
                    ['name' => 'Personal Fitness Coaching Session', 'price' => '$25.00', 'desc' => 'One-on-one session with certified trainer focusing on mobility, cardio, and strength.']
                ]),
                'order' => 2
            ]
        ];

        $stmtDW = $pdo->prepare("INSERT INTO dining_wellness (type, title, subtitle, description, hours, price_range, image_url, menu_items_json, is_published, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        foreach ($dw as $item) {
            $stmtDW->execute([$item['type'], $item['title'], $item['subtitle'], $item['desc'], $item['hours'], $item['price'], $item['img'], $item['menu'], $item['order']]);
        }

        // Seed Special Offers
        $offers = [
            ['title' => 'Direct Booking Privilege', 'code' => 'INDRA15', 'disc' => 15, 'desc' => 'Book your stay directly on our official website and enjoy an exclusive 15% discount across all suites, complimentary daily breakfast, and flexible cancellation up to 24 hours prior to arrival.', 'img' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1000&q=80', 'badge' => 'Best Rate Guarantee', 'order' => 1],
            ['title' => 'Romantic Suite Getaway', 'code' => 'LOVEINDRA', 'disc' => 20, 'desc' => 'Escape for a romantic getaway in our Indra Suite with Balcony. Includes a bottle of chilled sparkling wine on arrival, floral bed decoration, and late checkout until 3:00 PM.', 'img' => 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?auto=format&fit=crop&w=1000&q=80', 'badge' => 'Romantic Experience', 'order' => 2],
            ['title' => 'Long Stay Sanctuary Package', 'code' => 'STAYLONG', 'disc' => 25, 'desc' => 'Stay 5 nights or more and receive 25% off nightly rates, complimentary weekly laundry service, and 20% off all dining and spa treatments during your stay.', 'img' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1000&q=80', 'badge' => 'Extended Stay', 'order' => 3]
        ];
        $stmtOff = $pdo->prepare("INSERT INTO special_offers (title, promo_code, discount_percent, description, valid_from, valid_to, image_url, badge_text, is_active, display_order) VALUES (?, ?, ?, ?, '2026-01-01', '2026-12-31', ?, ?, 1, ?)");
        foreach ($offers as $off) {
            $stmtOff->execute([$off['title'], $off['code'], $off['disc'], $off['desc'], $off['img'], $off['badge'], $off['order']]);
        }

        // Seed Gallery
        $gallery = [
            ['title' => 'Stately Hotel Exterior', 'cat' => 'exterior', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq', 'cap' => 'Architectural facade surrounded by tropical palms', 'order' => 1],
            ['title' => 'Deluxe King Interior', 'cat' => 'rooms', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLs-aT78HuC87zVBtlw78IAcVeqttvz1DzuDCFkgHQa0GjAqpJ7QEgkbAcIsJqkihNQyvb5xyVUyfpGCLZpUTsGnoI_Zd2-vfG3hGgzgX8ILxOjMzXh6zptxCM2mUT_5SpyirJMD2KE7cfOEm3QbJ69fnH6VMjX0sVrsXmM5Jm-XtIvZg9-B3Mseq2ffcjkq7LNDstvkdy5lRLn-NIFXZWhKbwJ1tFsvSH-_tFiOrkWNTCNfDxbD8XbxG-j2', 'cap' => 'Elegantly appointed Deluxe King bedroom', 'order' => 2],
            ['title' => 'The Bistro Restaurant', 'cat' => 'dining', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLu4xqDm5eXV-bc_ApYrUK1GnN0Euq-6ES4WN642l6K8VhewdBb_YkEtWSSt-tybo0AKJFBQh2oRWlfCx42rbsSmJsLPSmn2ODfXDog-y3cHuE5NTuWtSDiZptOZNk-bMlpk3s-xS7TRQHWRaUeUH0_bRiicGwQeGjy6gBDbaO4KosbM7QsKUNWT0PhQSHXUnhupahrd4i6fqtDtt53ZX2XGRn06_VQba3YHrkQ1BSNwkc5KeAJ3nMpX63ho', 'cap' => 'Sophisticated dining ambiance and evening lighting', 'order' => 3],
            ['title' => 'Fitness Center & Weights', 'cat' => 'wellness', 'img' => 'https://lh3.googleusercontent.com/aida/AP1WRLuptPITXoiXpQR1wIOmYOuIMSUpJR1sTCXJga7uhGTXxKzccE6d21YAs-Fz3vugKf8Di3bkOx3Z2SAFqzNx65b_Uw7N7kpd85zK1LmfmQdCORWGDlOrtH72JS6rhGzsyzxnD8WonzUh6ObvlE7ID6Qbn5drvwWEj2vxz-cViALFQ0lhcHoW29UYsHXJWpGDyXLv5D6oiMwysDWC5sB1LzkdFz773ymQ3ZZ8FBQ4aSJgr2zufcudA_X7GzK5', 'cap' => 'Modern cardio and strength equipment', 'order' => 4],
            ['title' => 'Junior Suite Balcony Terrace', 'cat' => 'rooms', 'img' => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80', 'cap' => 'Private balcony with scenic greenery view', 'order' => 5],
            ['title' => 'Indra Suite Lounge', 'cat' => 'rooms', 'img' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80', 'cap' => 'Expansive suite living room with artisan furniture', 'order' => 6]
        ];
        $stmtGal = $pdo->prepare("INSERT INTO gallery (title, category, image_url, caption, display_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($gallery as $g) {
            $stmtGal->execute([$g['title'], $g['cat'], $g['img'], $g['cap'], $g['order']]);
        }

        // Seed Demo Bookings
        $bookings = [
            ['IND-2026-8492', 1, 'Alexander Wright', 'alex.wright@example.com', '+1 (555) 234-8901', '2026-08-20', '2026-08-23', 2, 0, 3, 85.00, 255.00, 'confirmed', 'paid', 'High floor requested, quiet corner room if possible.'],
            ['IND-2026-9104', 3, 'Dr. Sophal Meas', 'sophal.meas@hospital.kh', '+855 12 334 556', '2026-08-18', '2026-08-22', 2, 1, 4, 180.00, 720.00, 'checked_in', 'paid', 'Airport transfer requested from Phnom Penh Int Airport at 14:00.'],
            ['IND-2026-7731', 2, 'Elena Rostova', 'elena.rostova@traveler.com', '+44 7700 900123', '2026-08-25', '2026-08-28', 1, 0, 3, 125.00, 375.00, 'pending', 'unpaid', 'Late check-in around 21:30 expected.'],
            ['IND-2026-6209', 1, 'Jonathan Chen', 'jchen@techasia.sg', '+65 9123 4567', '2026-08-10', '2026-08-14', 2, 0, 4, 85.00, 340.00, 'checked_out', 'paid', 'Vegetarian breakfast options.']
        ];
        $stmtB = $pdo->prepare("INSERT INTO bookings (booking_reference, room_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, adults, children, nights, room_rate, total_price, status, payment_status, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($bookings as $b) {
            $stmtB->execute($b);
        }

        // Seed Messages
        $msgs = [
            ['Michael Brown', 'michael.b@consulting.co', '+855 98 765 432', 'Airport Pickup & Suite Inquiry', 'Hello Indra Hotel Team, I am visiting Phnom Penh next week for business and would like to confirm if your Indra Suite has a high-speed wired Ethernet connection and whether airport pickup can be arranged? Thank you!', 'unread'],
            ['Claire Dubois', 'claire.dubois@paris.fr', '+33 6 12 34 56 78', 'Romantic Anniversary Dinner Reservation', 'Good afternoon, we will be staying in your Junior Suite on August 26th for our 10th wedding anniversary. Could we arrange a special rooftop table and flowers at The Bistro? Best regards, Claire', 'read']
        ];
        $stmtM = $pdo->prepare("INSERT INTO messages (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($msgs as $m) {
            $stmtM->execute($m);
        }

        // Seed Site Settings
        $settings = [
            ['site_title', 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh'],
            ['site_meta_description', 'Experience refined luxury at Indra Hotel Phnom Penh. 12 contemporary designer suites with private balconies, saltwater pool, fitness center, and fine dining in Tuol Kork.'],
            ['site_meta_keywords', 'Indra hotel phnom penh, boutique hotel cambodia, luxury suites tuol kork, hotel with pool phnom penh, best hotel phnom penh'],
            ['google_analytics_id', 'G-INDRA2026'],
            ['google_site_verification', 'googled9104820indraverification'],
            ['hotel_notice_banner', 'Book directly with promo code INDRA15 for 15% off + complimentary artisan breakfast.']
        ];
        $stmtS = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($settings as $s) {
            $stmtS->execute($s);
        }
    }
}

// Function helper for getting PDO connection
function getDB(): PDO {
    return Database::getConnection();
}
