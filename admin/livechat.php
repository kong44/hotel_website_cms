<?php
/**
 * Indra Hotel - Live Chat Configuration CMS (Tawk.to Integration)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Role Enforcement: Administrator privileges required
Auth::requireAdmin();

$pdo = getDB();
$adminTitle = 'Live Chat Settings (Tawk.to)';

// Handle Save Settings
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_livechat'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: ' . BASE_URL . '/admin/livechat.php');
        exit;
    }

    $tawktoEnabled = isset($_POST['tawkto_enabled']) ? '1' : '0';
    $propertyId = trim($_POST['tawkto_property_id'] ?? '');
    $widgetId = trim($_POST['tawkto_widget_id'] ?? 'default');
    $directLink = trim($_POST['tawkto_direct_chat_link'] ?? '');
    $rawSnippet = trim($_POST['tawkto_raw_snippet'] ?? '');

    // Auto-parse from raw snippet or URL if provided
    if (!empty($rawSnippet)) {
        if (preg_match('/embed\.tawk\.to\/([a-zA-Z0-9]+)\/([a-zA-Z0-9]+)/', $rawSnippet, $matches)) {
            $propertyId = $matches[1];
            $widgetId = $matches[2];
        }
    }

    $settings = [
        'tawkto_enabled' => $tawktoEnabled,
        'tawkto_property_id' => $propertyId,
        'tawkto_widget_id' => $widgetId ?: 'default',
        'tawkto_direct_chat_link' => $directLink,
        'tawkto_raw_snippet' => $rawSnippet
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Refresh memory cache
    get_setting('__refresh__');
    set_flash('success', 'Live Chat (Tawk.to) configuration updated successfully.');
    header('Location: ' . BASE_URL . '/admin/livechat.php');
    exit;
}

// Current Values
$tawktoEnabled = get_setting('tawkto_enabled', '0');
$propertyId = get_setting('tawkto_property_id', '');
$widgetId = get_setting('tawkto_widget_id', 'default');
$directLink = get_setting('tawkto_direct_chat_link', '');
$rawSnippet = get_setting('tawkto_raw_snippet', '');

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-8 max-w-5xl">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#4B5320]">Guest Communication</span>
            </div>
            <h1 class="font-headline text-3xl font-bold text-onyx-charcoal tracking-tight">Live Chat Integration</h1>
            <p class="text-xs text-stone-500 mt-1">Connect your free <strong>Tawk.to</strong> widget to chat in real-time with prospective hotel guests.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-stone-500">Widget Status:</span>
            <?php if ($tawktoEnabled === '1' && !empty($propertyId)): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-600 text-white shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                    <span>Live & Active</span>
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-stone-200 text-stone-700">
                    <span class="w-2 h-2 rounded-full bg-stone-400"></span>
                    <span>Disabled / Inactive</span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tawk.to Introduction & Value Card -->
    <div class="bg-gradient-to-r from-emerald-900 to-[#191c1d] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-2 z-10">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-white/10 text-[#dfe8a6] text-[11px] font-bold uppercase tracking-wider">
                <span class="material-symbols-outlined text-sm">chat</span>
                <span>Free 24/7 Guest Support</span>
            </div>
            <h3 class="font-headline text-xl font-bold">Real-Time Messaging with Tawk.to</h3>
            <p class="text-xs text-stone-300 max-w-xl leading-relaxed">
                Tawk.to is a 100% free live chat application that allows hotel front desk and concierge staff to monitor website visitors, answer reservation inquiries in real-time, and chat via iOS/Android mobile apps.
            </p>
        </div>
        <div class="shrink-0 z-10">
            <a href="https://www.tawk.to" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 bg-[#dfe8a6] hover:bg-white text-[#191e00] font-bold text-xs px-5 py-2.5 rounded-lg transition shadow">
                <span>Create Free Tawk.to Account</span>
                <span class="material-symbols-outlined text-sm">open_in_new</span>
            </a>
        </div>
    </div>

    <!-- Configuration Form -->
    <form action="<?= BASE_URL ?>/admin/livechat.php" method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
        <input type="hidden" name="save_livechat" value="1">

        <!-- 1. Enable / Disable Toggle -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl">chat</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Enable Tawk.to Live Chat Widget</h2>
                        <p class="text-xs text-stone-500">Display the floating live chat bubble on all public pages for hotel visitors.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <!-- Live Status Pill -->
                    <span id="swipe-status-pill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition duration-200 <?= $tawktoEnabled === '1' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs' : 'bg-stone-100 text-stone-600 border border-stone-200' ?>">
                        <span id="swipe-status-dot" class="w-2 h-2 rounded-full <?= $tawktoEnabled === '1' ? 'bg-emerald-500 animate-pulse' : 'bg-stone-400' ?>"></span>
                        <span id="swipe-status-label"><?= $tawktoEnabled === '1' ? 'ENABLED' : 'DISABLED' ?></span>
                    </span>

                    <!-- Modern Swipe Button -->
                    <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input type="checkbox" name="tawkto_enabled" id="tawkto_enabled_input" value="1" <?= $tawktoEnabled === '1' ? 'checked' : '' ?> class="sr-only peer" onchange="updateTawkSwipeUI(this.checked)">
                        <div id="swipe-track" class="w-14 h-7 rounded-full p-0.5 transition-all duration-300 ease-in-out cursor-pointer shadow-inner <?= $tawktoEnabled === '1' ? 'bg-[#343c0a]' : 'bg-stone-300 hover:bg-stone-400/80' ?>">
                            <div id="swipe-knob" class="w-6 h-6 bg-white rounded-full shadow-md transform transition-all duration-300 ease-in-out flex items-center justify-center text-[10px] font-bold <?= $tawktoEnabled === '1' ? 'translate-x-7 text-[#343c0a]' : 'translate-x-0 text-stone-400' ?>">
                                <span id="swipe-icon" class="material-symbols-outlined text-xs"><?= $tawktoEnabled === '1' ? 'check' : 'close' ?></span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- 2. Smart Paste & Auto-Parser -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-[#dfe8a6]/50 text-[#343c0a] flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">auto_fix_high</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Smart Embed Parser (Quick Setup)</h2>
                    <p class="text-xs text-stone-500">Paste your Tawk.to Direct Embed Script or URL here to automatically extract your IDs.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Paste Tawk.to Snippet or Embed Link</label>
                <textarea name="tawkto_raw_snippet" id="tawkto_raw_snippet" rows="3" 
                          placeholder="Paste either:&#10;• https://embed.tawk.to/64abcdef123456/1h123456&#10;• or the entire <script>...</script> block from Tawk.to"
                          oninput="autoParseTawkSnippet(this.value)"
                          class="w-full text-xs font-mono border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"><?= e($rawSnippet) ?></textarea>
                <p class="text-[11px] text-stone-400 mt-1">The system will instantly extract and fill the Property ID and Widget ID below.</p>
            </div>
        </div>

        <!-- 3. Direct Connection Parameters -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-stone-200 shadow-2xs space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">tune</span>
                </div>
                <div>
                    <h2 class="font-headline font-bold text-lg text-onyx-charcoal">Tawk.to Channel Credentials</h2>
                    <p class="text-xs text-stone-500">Unique property identifier and widget ID for your chat channel.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Tawk.to Property ID *</label>
                    <input type="text" name="tawkto_property_id" id="input_tawk_property_id" value="<?= e($propertyId) ?>" placeholder="e.g. 64abcdef1234567890abcdef"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Found in Tawk.to Dashboard: <em>Administration &rarr; Channels &rarr; Chat Widget &rarr; Direct Chat Link</em>.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Tawk.to Widget ID</label>
                    <input type="text" name="tawkto_widget_id" id="input_tawk_widget_id" value="<?= e($widgetId) ?>" placeholder="default"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Usually <code class="bg-stone-100 px-1 rounded">default</code> or a custom string like <code class="bg-stone-100 px-1 rounded">1h4abc123</code>.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1.5">Direct Chat Link (Optional)</label>
                    <input type="url" name="tawkto_direct_chat_link" id="input_tawk_direct_link" value="<?= e($directLink) ?>" placeholder="https://tawk.to/chat/64abcdef123456/1h123456"
                           class="w-full text-sm font-mono border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                    <p class="text-[11px] text-stone-400 mt-1">Used if you want direct chat buttons in confirmation emails or WhatsApp links.</p>
                </div>
            </div>
        </div>

        <!-- 4. Step-by-Step Instructions Card -->
        <div class="bg-stone-50 rounded-2xl p-6 border border-stone-200 space-y-4">
            <h3 class="font-headline font-bold text-sm text-onyx-charcoal flex items-center gap-2">
                <span class="material-symbols-outlined text-[#343c0a]">help_outline</span>
                <span>How to Get Your Free Tawk.to Widget in 3 Minutes:</span>
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-stone-600">
                <div class="bg-white p-4 rounded-xl border border-stone-200 space-y-1">
                    <span class="font-bold text-[#343c0a] text-sm">Step 1</span>
                    <h4 class="font-bold text-stone-900">Sign Up at Tawk.to</h4>
                    <p class="text-[11px] text-stone-500">Go to <a href="https://www.tawk.to" target="_blank" class="text-[#343c0a] underline font-semibold">tawk.to</a>, create a free account, and enter your website name: <em>Indra Hotel</em>.</p>
                </div>

                <div class="bg-white p-4 rounded-xl border border-stone-200 space-y-1">
                    <span class="font-bold text-[#343c0a] text-sm">Step 2</span>
                    <h4 class="font-bold text-stone-900">Copy the Widget Code</h4>
                    <p class="text-[11px] text-stone-500">Navigate to <strong>Administration &rarr; Channels &rarr; Chat Widget</strong>, and copy the provided Embed Code or Direct Link.</p>
                </div>

                <div class="bg-white p-4 rounded-xl border border-stone-200 space-y-1">
                    <span class="font-bold text-[#343c0a] text-sm">Step 3</span>
                    <h4 class="font-bold text-stone-900">Paste & Save</h4>
                    <p class="text-[11px] text-stone-500">Paste the snippet into the box above, toggle <strong>Enable</strong>, and click <strong>Save Configuration</strong>. That's it!</p>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <button type="submit" class="bg-[#343c0a] hover:bg-deep-olive text-white px-8 py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow cursor-pointer transition">
                Save Live Chat Configuration
            </button>
        </div>
    </form>

</div>

<script>
function updateTawkSwipeUI(isChecked) {
    const track = document.getElementById('swipe-track');
    const knob = document.getElementById('swipe-knob');
    const swipeIcon = document.getElementById('swipe-icon');
    const pill = document.getElementById('swipe-status-pill');
    const dot = document.getElementById('swipe-status-dot');
    const label = document.getElementById('swipe-status-label');

    if (isChecked) {
        // Track & Knob
        track.className = 'w-14 h-7 rounded-full p-0.5 transition-all duration-300 ease-in-out cursor-pointer shadow-inner bg-[#343c0a]';
        knob.className = 'w-6 h-6 bg-white rounded-full shadow-md transform transition-all duration-300 ease-in-out flex items-center justify-center text-[10px] font-bold translate-x-7 text-[#343c0a]';
        swipeIcon.textContent = 'check';

        // Status Pill
        pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition duration-200 bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs';
        dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
        label.textContent = 'ENABLED';
    } else {
        // Track & Knob
        track.className = 'w-14 h-7 rounded-full p-0.5 transition-all duration-300 ease-in-out cursor-pointer shadow-inner bg-stone-300 hover:bg-stone-400/80';
        knob.className = 'w-6 h-6 bg-white rounded-full shadow-md transform transition-all duration-300 ease-in-out flex items-center justify-center text-[10px] font-bold translate-x-0 text-stone-400';
        swipeIcon.textContent = 'close';

        // Status Pill
        pill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition duration-200 bg-stone-100 text-stone-600 border border-stone-200';
        dot.className = 'w-2 h-2 rounded-full bg-stone-400';
        label.textContent = 'DISABLED';
    }
}

function autoParseTawkSnippet(rawText) {
    if (!rawText) return;
    
    // Pattern 1: https://embed.tawk.to/PROPERTY_ID/WIDGET_ID
    const embedMatch = rawText.match(/embed\.tawk\.to\/([a-zA-Z0-9]+)\/([a-zA-Z0-9]+)/);
    if (embedMatch) {
        document.getElementById('input_tawk_property_id').value = embedMatch[1];
        document.getElementById('input_tawk_widget_id').value = embedMatch[2];
        return;
    }

    // Pattern 2: https://tawk.to/chat/PROPERTY_ID/WIDGET_ID
    const chatMatch = rawText.match(/tawk\.to\/chat\/([a-zA-Z0-9]+)\/([a-zA-Z0-9]+)/);
    if (chatMatch) {
        document.getElementById('input_tawk_property_id').value = chatMatch[1];
        document.getElementById('input_tawk_widget_id').value = chatMatch[2];
        document.getElementById('input_tawk_direct_link').value = chatMatch[0];
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
