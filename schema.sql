-- Indra Hotel Database Schema
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB / SQLite

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `avatar` VARCHAR(255) NULL,
  `google_id` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_types` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_type_id` INT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'Deluxe Room',
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `tagline` VARCHAR(255) NULL,
  `description` TEXT NOT NULL,
  `price_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `size_sqm` INT NOT NULL DEFAULT 35,
  `bed_type` VARCHAR(100) NOT NULL DEFAULT '1 King Bed',
  `capacity_adults` INT NOT NULL DEFAULT 2,
  `capacity_children` INT NOT NULL DEFAULT 1,
  `view_type` VARCHAR(100) DEFAULT 'City View',
  `image_url` TEXT NOT NULL,
  `gallery_json` TEXT NULL,
  `amenities_json` TEXT NULL,
  `status` ENUM('available', 'maintenance', 'occupied') NOT NULL DEFAULT 'available',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 0,
  `translations_json` LONGTEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `amenities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'check_circle',
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `translations_json` LONGTEXT NULL,
  `display_order` INT NOT NULL DEFAULT 99,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_reference` VARCHAR(50) NOT NULL UNIQUE,
  `room_id` INT NOT NULL,
  `guest_name` VARCHAR(150) NOT NULL,
  `guest_email` VARCHAR(191) NOT NULL,
  `guest_phone` VARCHAR(50) NOT NULL,
  `check_in_date` DATE NOT NULL,
  `check_out_date` DATE NOT NULL,
  `adults` INT NOT NULL DEFAULT 1,
  `children` INT NOT NULL DEFAULT 0,
  `nights` INT NOT NULL DEFAULT 1,
  `room_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_code` VARCHAR(50) NULL,
  `offer_title` VARCHAR(150) NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
  `special_requests` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_dates (`check_in_date`, `check_out_date`),
  INDEX idx_booking_room (`room_id`),
  CONSTRAINT fk_booking_room FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dining_wellness` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('dining', 'wellness') NOT NULL DEFAULT 'dining',
  `title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `description` TEXT NOT NULL,
  `hours` VARCHAR(150) NULL,
  `price_range` VARCHAR(50) NULL,
  `image_url` TEXT NOT NULL,
  `menu_items_json` TEXT NULL,
  `translations_json` LONGTEXT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `special_offers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `promo_code` VARCHAR(50) NULL,
  `discount_percent` INT NOT NULL DEFAULT 0,
  `description` TEXT NOT NULL,
  `badge_text` VARCHAR(50) DEFAULT 'Special Offer',
  `image_url` TEXT NOT NULL,
  `external_url` VARCHAR(255) NULL,
  `valid_from` DATE NULL,
  `valid_to` DATE NULL,
  `translations_json` LONGTEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` ENUM('all', 'rooms', 'dining', 'wellness', 'exterior', 'interior') NOT NULL DEFAULT 'rooms',
  `image_url` TEXT NOT NULL,
  `caption` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_spots` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'replied') NOT NULL DEFAULT 'unread',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_uploads` (
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
  INDEX idx_media_folder (`folder`),
  INDEX idx_media_type (`file_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `custom_pages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `theme_licenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `theme_slug` VARCHAR(100) NOT NULL UNIQUE,
  `is_purchased` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 0,
  `purchase_date` DATETIME NULL,
  `custom_styles_json` LONGTEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `driver` VARCHAR(50) NOT NULL DEFAULT 'mail',
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `message` TEXT NULL,
  `log` LONGTEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_invitations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL,
  `token` VARCHAR(128) NOT NULL UNIQUE,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  INDEX `idx_reset_token` (`token`),
  INDEX `idx_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guest_users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(191) PRIMARY KEY,
  `data` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  INDEX `idx_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

