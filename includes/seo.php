<?php
/**
 * Indra Hotel - Comprehensive SEO Engine
 * Generates dynamic OpenGraph, Twitter Cards, Local Geo Tags, and Schema.org JSON-LD
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

class SEO {
    public static function renderTags(array $options = []): string {
        return self::renderMeta($options);
    }

    public static function renderMeta(array $options = []): string {
        $title = $options['title'] ?? get_setting('site_title', 'Indra Hotel | Contemporary Boutique Sanctuary in Phnom Penh');
        $rawDescription = $options['description'] ?? get_setting('site_meta_description', 'Experience refined luxury at Indra Hotel Phnom Penh. 12 contemporary designer suites with private balconies, saltwater pool, fitness center, and fine dining in Tuol Kork.');
        $description = mb_substr(strip_tags($rawDescription), 0, 160);
        $keywords = $options['keywords'] ?? get_setting('site_meta_keywords', 'Indra hotel phnom penh, boutique hotel cambodia, luxury suites tuol kork, hotel with pool phnom penh, best hotel phnom penh');
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$host}{$uri}";
        $canonical = $options['canonical'] ?? strtok($currentUrl, '?');
        $image = $options['image'] ?? 'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq';
        $type = $options['type'] ?? 'website';
        $siteVerification = get_setting('google_site_verification', 'googled9104820indraverification');

        $html = "<!-- Primary SEO Meta Tags -->\n";
        $html .= "<title>" . e($title) . "</title>\n";
        $html .= '<meta name="title" content="' . e($title) . '">' . "\n";
        $html .= '<meta name="description" content="' . e($description) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . e($keywords) . '">' . "\n";
        $html .= '<meta name="author" content="' . e(HOTEL_NAME) . '">' . "\n";
        $html .= '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        $html .= '<link rel="canonical" href="' . e($canonical) . '">' . "\n\n";

        // Local SEO & Geo Tags
        $html .= "<!-- Local SEO & Geo Tags (Phnom Penh, Cambodia) -->\n";
        $html .= '<meta name="geo.region" content="KH-12">' . "\n";
        $html .= '<meta name="geo.placename" content="Phnom Penh">' . "\n";
        $html .= '<meta name="geo.position" content="' . HOTEL_LATITUDE . ';' . HOTEL_LONGITUDE . '">' . "\n";
        $html .= '<meta name="ICBM" content="' . HOTEL_LATITUDE . ', ' . HOTEL_LONGITUDE . '">' . "\n\n";

        // Open Graph / Facebook
        $html .= "<!-- Open Graph / Facebook / WhatsApp -->\n";
        $html .= '<meta property="og:type" content="' . e($type) . '">' . "\n";
        $html .= '<meta property="og:site_name" content="' . e(HOTEL_NAME) . '">' . "\n";
        $html .= '<meta property="og:url" content="' . e($canonical) . '">' . "\n";
        $html .= '<meta property="og:title" content="' . e($title) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . e($description) . '">' . "\n";
        $html .= '<meta property="og:image" content="' . e($image) . '">' . "\n";
        $html .= '<meta property="og:image:width" content="1200">' . "\n";
        $html .= '<meta property="og:image:height" content="630">' . "\n";
        $html .= '<meta property="og:locale" content="en_US">' . "\n\n";

        // Twitter Card
        $html .= "<!-- Twitter Cards -->\n";
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:url" content="' . e($canonical) . '">' . "\n";
        $html .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . e($description) . '">' . "\n";
        $html .= '<meta name="twitter:image" content="' . e($image) . '">' . "\n\n";

        if ($siteVerification) {
            $html .= '<meta name="google-site-verification" content="' . e($siteVerification) . '">' . "\n";
        }

        return $html;
    }

    /**
     * Generate Schema.org JSON-LD for Hotel
     */
    public static function getHotelSchema(): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Hotel',
            '@id' => BASE_URL . '#hotel',
            'name' => HOTEL_NAME,
            'legalName' => HOTEL_LEGAL_NAME,
            'url' => BASE_URL,
            'logo' => BASE_URL . '/assets/images/logo.png',
            'image' => [
                'https://lh3.googleusercontent.com/aida/AP1WRLtHpM1LVwYuky4usi8aljQksBM3_T06H4btYM3gtlRqZ7b_NHp6dCow02XawKmSrDEyy4QtMR0PtPZlHSVqp-RD9bx6SzEFXe-vpfufKydm8eiddpQgw1Q1I9rh_Cc-FkEBv_gH40QUMF-3KrQRKburjx9jrdKTTwKVrOXZcMhiDl3gj8oQj_4ZGjvIzLvdtjrVXR_tWERJH_Z7FVUgaTDvTcckn8HPa1Xo0l-DpvWJs6OCpZDQhgPEsYcq',
                'https://lh3.googleusercontent.com/aida/AP1WRLs-aT78HuC87zVBtlw78IAcVeqttvz1DzuDCFkgHQa0GjAqpJ7QEgkbAcIsJqkihNQyvb5xyVUyfpGCLZpUTsGnoI_Zd2-vfG3hGgzgX8ILxOjMzXh6zptxCM2mUT_5SpyirJMD2KE7cfOEm3QbJ69fnH6VMjX0sVrsXmM5Jm-XtIvZg9-B3Mseq2ffcjkq7LNDstvkdy5lRLn-NIFXZWhKbwJ1tFsvSH-_tFiOrkWNTCNfDxbD8XbxG-j2'
            ],
            'description' => 'Just minutes from bustling Phnom Penh, Indra Hotel offers a serene and cozy environment perfect for unwinding and relaxation. 12 modern designer accommodations, swimming pool, fitness center, cafe, and fine dining in Tuol Kork.',
            'telephone' => HOTEL_PHONE,
            'email' => HOTEL_EMAIL,
            'priceRange' => HOTEL_PRICE_RANGE,
            'currenciesAccepted' => HOTEL_CURRENCY,
            'paymentAccepted' => 'Cash, Credit Card, Visa, Mastercard, ABA Pay, KHQR',
            'starRating' => [
                '@type' => 'Rating',
                'ratingValue' => HOTEL_STAR_RATING,
                'bestRating' => '5'
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => HOTEL_ADDRESS_STREET,
                'addressLocality' => HOTEL_ADDRESS_DISTRICT . ', ' . HOTEL_ADDRESS_CITY,
                'postalCode' => HOTEL_POSTAL_CODE,
                'addressCountry' => HOTEL_ADDRESS_COUNTRY
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => (float)HOTEL_LATITUDE,
                'longitude' => (float)HOTEL_LONGITUDE
            ],
            'checkinTime' => HOTEL_CHECKIN_TIME,
            'checkoutTime' => HOTEL_CHECKOUT_TIME,
            'numberOfRooms' => 12,
            'petsAllowed' => false,
            'amenityFeature' => [
                ['@type' => 'LocationFeatureSpecification', 'name' => 'Saltwater Swimming Pool', 'value' => true],
                ['@type' => 'LocationFeatureSpecification', 'name' => 'Fitness Center & Gym', 'value' => true],
                ['@type' => 'LocationFeatureSpecification', 'name' => 'Fine Dining Restaurant & Cafe', 'value' => true],
                ['@type' => 'LocationFeatureSpecification', 'name' => 'Complimentary High-Speed Wi-Fi', 'value' => true],
                ['@type' => 'LocationFeatureSpecification', 'name' => '24-Hour Front Desk Service', 'value' => true],
                ['@type' => 'LocationFeatureSpecification', 'name' => 'Airport Transfer Service', 'value' => true]
            ],
            'sameAs' => [
                HOTEL_FACEBOOK,
                HOTEL_INSTAGRAM,
                HOTEL_TRIPADVISOR
            ]
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }

    /**
     * Generate Schema.org JSON-LD for Individual HotelRoom
     */
    public static function getRoomSchema(array $room): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'HotelRoom',
            'name' => $room['name'],
            'description' => $room['description'],
            'image' => $room['image_url'],
            'bed' => [
                '@type' => 'BedDetails',
                'numberOfBeds' => 1,
                'typeOfBed' => $room['bed_type']
            ],
            'occupancy' => [
                '@type' => 'QuantitativeValue',
                'maxValue' => (int)$room['capacity_adults'] + (int)$room['capacity_children'],
                'unitCode' => 'C62'
            ],
            'floorSize' => [
                '@type' => 'QuantitativeValue',
                'value' => (int)$room['size_sqm'],
                'unitCode' => 'MTK'
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float)$room['price_per_night'],
                'priceCurrency' => HOTEL_CURRENCY,
                'availability' => 'https://schema.org/InStock',
                'url' => BASE_URL . '/room.php?slug=' . urlencode($room['slug'])
            ],
            'containedInPlace' => [
                '@type' => 'Hotel',
                'name' => HOTEL_NAME,
                'url' => BASE_URL
            ]
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }

    /**
     * Generate Schema.org JSON-LD for Breadcrumbs
     */
    public static function getBreadcrumbsSchema(array $items): string {
        $list = [];
        $position = 1;
        foreach ($items as $name => $url) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url
            ];
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }
}
