<?php
/**
 * Indra Hotel - Internationalization (i18n) & Multi-Language Engine
 * Supported Locales: English (en), Khmer (km), Chinese (zh), Korean (ko)
 */

require_once __DIR__ . '/../config.php';

class I18n {
    public const SUPPORTED_LOCALES = [
        'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧', 'font' => 'font-sans'],
        'km' => ['name' => 'Khmer', 'native' => 'ភាសាខ្មែរ', 'flag' => '🇰🇭', 'font' => 'font-khmer'],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'flag' => '🇨🇳', 'font' => 'font-zh'],
        'ko' => ['name' => 'Korean', 'native' => '한국어', 'flag' => '🇰🇷', 'font' => 'font-ko']
    ];

    private static string $currentLocale = 'en';
    private static array $dictionary = [];

    /**
     * Initialize language from GET parameter, Session, or Cookie
     */
    public static function init(): void {
        $locale = 'en';

        if (isset($_GET['lang']) && array_key_exists($_GET['lang'], self::SUPPORTED_LOCALES)) {
            $locale = $_GET['lang'];
            $_SESSION['lang'] = $locale;
            if (!headers_sent()) {
                setcookie('indra_lang', $locale, time() + (86400 * 30), '/');
            }
        } elseif (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], self::SUPPORTED_LOCALES)) {
            $locale = $_SESSION['lang'];
        } elseif (isset($_COOKIE['indra_lang']) && array_key_exists($_COOKIE['indra_lang'], self::SUPPORTED_LOCALES)) {
            $locale = $_COOKIE['indra_lang'];
        }

        self::$currentLocale = $locale;
        self::loadDictionary($locale);
    }

    public static function getLocale(): string {
        return self::$currentLocale;
    }

    public static function setLocale(string $locale): void {
        if (array_key_exists($locale, self::SUPPORTED_LOCALES)) {
            self::$currentLocale = $locale;
            self::loadDictionary($locale);
        }
    }

    public static function getLocaleInfo(): array {
        return self::SUPPORTED_LOCALES[self::$currentLocale] ?? self::SUPPORTED_LOCALES['en'];
    }

    /**
     * Load locale JSON dictionary file
     */
    private static function loadDictionary(string $locale): void {
        $filePath = ROOT_PATH . "/locales/{$locale}.json";
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            self::$dictionary = json_decode($content, true) ?: [];
        } else {
            self::$dictionary = [];
        }
    }

    /**
     * Translate a static UI key
     */
    public static function trans(string $key, ?string $default = null): string {
        return self::$dictionary[$key] ?? $default ?? $key;
    }

    /**
     * Extract localized dynamic data from a model field or translations JSON
     * If requested language is not set or empty, directly falls back to English (en)
     */
    public static function getDynamicTranslation(array|object|string|null $item, string $field, string $default = ''): string {
        if (empty($item)) return $default;
        
        $locale = self::$currentLocale;
        
        // If $item is an array or object containing translations JSON
        if (is_array($item)) {
            if (!empty($item['translations_json'])) {
                $decoded = is_string($item['translations_json']) ? json_decode($item['translations_json'], true) : $item['translations_json'];
                
                // 1. Try requested locale if non-empty
                if (isset($decoded[$locale][$field]) && trim((string)$decoded[$locale][$field]) !== '') {
                    return (string)$decoded[$locale][$field];
                }
                
                // 2. Fallback to English translation
                if (isset($decoded['en'][$field]) && trim((string)$decoded['en'][$field]) !== '') {
                    return (string)$decoded['en'][$field];
                }
            }
            
            // 3. Fallback to primary column value (e.g. $item['name'])
            if (isset($item[$field]) && trim((string)$item[$field]) !== '') {
                return (string)$item[$field];
            }
        }
        
        return $default;
    }

    /**
     * Render Global Language Switcher Dropdown Component
     */
    public static function renderSwitcher(string $extraClass = '', string $theme = 'dark', string $direction = 'top'): string {
        $current = self::$currentLocale;
        $currentInfo = self::SUPPORTED_LOCALES[$current] ?? self::SUPPORTED_LOCALES['en'];
        
        $isDark = ($theme === 'dark');
        $isTop = ($direction === 'top' || $direction === 'up');

        $btnClass = $isDark 
            ? 'border-stone-700 bg-stone-900/90 hover:bg-stone-800 text-stone-200 hover:text-white' 
            : 'border-stone-300 bg-white/95 hover:bg-white text-stone-700';

        $menuClass = $isDark 
            ? 'bg-[#1f1f1f] border-stone-700 text-stone-200' 
            : 'bg-white border-stone-200 text-stone-700 shadow-xl';

        $posClass = $isTop ? 'bottom-full mb-2' : 'top-full mt-2';

        $html = '<div class="relative inline-block text-left language-switcher-dropdown ' . htmlspecialchars($extraClass) . '">';
        $html .= '<button type="button" onclick="if(window.toggleLanguageDropdown){window.toggleLanguageDropdown(this,event)}else{event.stopPropagation();this.nextElementSibling.classList.toggle(\'hidden\')}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold shadow-sm transition cursor-pointer select-none ' . $btnClass . '">';
        $html .= '<span>' . $currentInfo['flag'] . '</span>';
        $html .= '<span>' . htmlspecialchars($currentInfo['native']) . '</span>';
        $html .= '<span class="material-symbols-outlined text-sm">' . ($isTop ? 'expand_less' : 'expand_more') . '</span>';
        $html .= '</button>';

        $html .= '<div class="lang-dropdown-menu hidden absolute right-0 ' . $posClass . ' w-44 rounded-xl shadow-2xl border py-1.5 z-50 text-xs ' . $menuClass . '">';
        foreach (self::SUPPORTED_LOCALES as $code => $info) {
            if ($isDark) {
                $activeClass = ($code === $current) ? 'bg-[#dfe8a6]/20 font-bold text-[#dfe8a6]' : 'text-stone-300 hover:bg-stone-800 hover:text-white';
                $checkColor = 'text-[#dfe8a6]';
            } else {
                $activeClass = ($code === $current) ? 'bg-[#dfe8a6]/40 font-bold text-[#343c0a]' : 'text-stone-700 hover:bg-stone-50';
                $checkColor = 'text-[#343c0a]';
            }
            $url = self::getSwitchUrl($code);
            $html .= '<a href="' . htmlspecialchars($url) . '" class="flex items-center justify-between px-3.5 py-2 transition ' . $activeClass . '">';
            $html .= '<span class="flex items-center gap-2"><span>' . $info['flag'] . '</span><span>' . htmlspecialchars($info['native']) . '</span></span>';
            if ($code === $current) {
                $html .= '<span class="material-symbols-outlined text-xs ' . $checkColor . '">check</span>';
            }
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private static function getSwitchUrl(string $targetLang): string {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsed = parse_url($reqUri);
        $path = $parsed['path'] ?? 'index.php';
        if (empty($path) || $path === '/') {
            $path = 'index.php';
        }
        $queryParams = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
        }
        $queryParams = array_merge($queryParams, $_GET);
        $queryParams['lang'] = $targetLang;
        
        return $path . '?' . http_build_query($queryParams);
    }
}

// Initialize on include
I18n::init();

/**
 * Global Translation Helper Functions
 */
function __t(string $key, ?string $default = null): string {
    return I18n::trans($key, $default);
}

function __td(array|object|string|null $item, string $field, string $default = ''): string {
    return I18n::getDynamicTranslation($item, $field, $default);
}
