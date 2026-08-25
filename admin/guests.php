<?php
/**
 * Indra Hotel - Public Guest Directory & Portal Users CRM (SoftBook CMS)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$pdo = getDB();
$adminTitle = 'Public Guest Directory';
$currentUser = Auth::user();

// =======================================================
// 1. AJAX Endpoint: Get Guest Booking History
// =======================================================
if (isset($_GET['action']) && $_GET['action'] === 'history' && !empty($_GET['email'])) {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim($_GET['email']));

    $stmt = $pdo->prepare("
        SELECT b.*, r.name as room_name, r.image_url as room_image 
        FROM bookings b 
        LEFT JOIN rooms r ON b.room_id = r.id 
        WHERE LOWER(b.guest_email) = ? 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$email]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'email' => $email,
        'count' => count($bookings),
        'bookings' => $bookings
    ]);
    exit;
}

// =======================================================
// 2. Action: Export Guest Directory to CSV
// =======================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'indra_hotel_guests_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8 compatibility
    fputs($output, "\xEF\xBB\xBF");

    // CSV Header
    fputcsv($output, [
        'ID',
        'Guest Name',
        'Email Address',
        'Phone Number',
        'Auth Provider',
        'Google ID',
        'VIP / Status',
        'Total Stays',
        'Total Spend (USD)',
        'First Registered',
        'Last Active / Login',
        'Staff Notes'
    ]);

    // Fetch all guests with aggregated metrics
    $sql = "
        SELECT g.*, 
               COUNT(b.id) as total_stays,
               COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.total_price ELSE 0 END), 0) as total_spend
        FROM guest_users g
        LEFT JOIN bookings b ON LOWER(g.email) = LOWER(b.guest_email)
        GROUP BY g.id
        ORDER BY total_spend DESC, g.created_at DESC
    ";
    $guestsExport = $pdo->query($sql)->fetchAll();

    foreach ($guestsExport as $g) {
        fputcsv($output, [
            $g['id'],
            $g['name'],
            $g['email'],
            $g['phone'] ?? '',
            $g['auth_provider'] ?? 'direct_booking',
            $g['google_id'] ?? '',
            strtoupper($g['status'] ?? 'active'),
            $g['total_stays'] ?? 0,
            number_format((float)($g['total_spend'] ?? 0), 2, '.', ''),
            $g['created_at'] ? date('Y-m-d H:i', strtotime($g['created_at'])) : '',
            $g['last_login_at'] ? date('Y-m-d H:i', strtotime($g['last_login_at'])) : '',
            $g['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

// =======================================================
// 3. Action: Update Guest Status & Staff Notes
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_guest') {
    $guestId = (int)($_POST['guest_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'vip', 'blocked', 'restricted', 'flagged']) ? $_POST['status'] : 'active';
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($guestId > 0) {
        $stmtG = $pdo->prepare("SELECT email FROM guest_users WHERE id = ?");
        $stmtG->execute([$guestId]);
        $email = $stmtG->fetchColumn();

        $stmtUpdate = $pdo->prepare("UPDATE guest_users SET status = ?, phone = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpdate->execute([$status, $phone, $notes, $guestId]);

        if ($email) {
            $stmtSync = $pdo->prepare("
                INSERT INTO guests (email, status, last_login) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(email) DO UPDATE SET status = excluded.status
            ");
            $stmtSync->execute([strtolower($email), $status]);
        }

        set_flash('success', "Guest user status updated to '{$status}'.");
    }
    header('Location: ' . BASE_URL . '/admin/guests.php');
    exit;
}

// =======================================================
// 4. Metrics & KPI Calculations
// =======================================================
$totalGuests = (int)$pdo->query("SELECT COUNT(*) FROM guest_users")->fetchColumn();
$googleGuests = (int)$pdo->query("SELECT COUNT(*) FROM guest_users WHERE google_id IS NOT NULL OR auth_provider = 'google'")->fetchColumn();
$vipGuests = (int)$pdo->query("SELECT COUNT(*) FROM guest_users WHERE status = 'vip'")->fetchColumn();
$blockedGuests = (int)$pdo->query("SELECT COUNT(*) FROM guest_users WHERE status IN ('blocked', 'restricted', 'flagged')")->fetchColumn();

// =======================================================
// 5. Query Filters, Search & Pagination
// =======================================================
$search = trim($_GET['q'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'recent');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(LOWER(g.name) LIKE ? OR LOWER(g.email) LIKE ? OR g.phone LIKE ?)";
    $searchTerm = '%' . strtolower($search) . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filter === 'google') {
    $whereClauses[] = "(g.google_id IS NOT NULL OR g.auth_provider = 'google')";
} elseif ($filter === 'vip') {
    $whereClauses[] = "g.status = 'vip'";
} elseif ($filter === 'flagged') {
    $whereClauses[] = "g.status = 'flagged'";
}

$whereSql = implode(" AND ", $whereClauses);

// Sorting
$orderBy = "g.created_at DESC";
if ($sort === 'spend') {
    $orderBy = "total_spend DESC, g.created_at DESC";
} elseif ($sort === 'stays') {
    $orderBy = "total_stays DESC, g.created_at DESC";
} elseif ($sort === 'name') {
    $orderBy = "g.name ASC";
} elseif ($sort === 'login') {
    $orderBy = "g.last_login_at DESC NULLS LAST, g.created_at DESC";
}

// Count total matching guests
$countSql = "
    SELECT COUNT(DISTINCT g.id) 
    FROM guest_users g 
    WHERE {$whereSql}
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalMatching = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalMatching / $perPage));

// Main Query with aggregated bookings metrics
$mainSql = "
    SELECT g.*, 
           COUNT(b.id) as total_stays,
           COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.total_price ELSE 0 END), 0) as total_spend,
           COUNT(CASE WHEN b.status = 'confirmed' OR b.status = 'pending' THEN 1 END) as active_stays,
           COUNT(CASE WHEN b.status = 'checked_in' THEN 1 END) as checked_in_stays
    FROM guest_users g
    LEFT JOIN bookings b ON LOWER(g.email) = LOWER(b.guest_email)
    WHERE {$whereSql}
    GROUP BY g.id
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";

$stmtMain = $pdo->prepare($mainSql);
$stmtMain->execute($params);
$guests = $stmtMain->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">

    <!-- Top Breadcrumb & Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase tracking-wider mb-1">
                <span>Directory</span>
                <span>/</span>
                <span class="text-[#343c0a]">Public Guest CRM</span>
            </div>
            <h1 class="font-headline font-bold text-2xl sm:text-3xl text-onyx-charcoal flex items-center gap-2.5">
                <span class="material-symbols-outlined text-3xl text-[#343c0a]">groups</span>
                <span>Public User & Guest Directory</span>
            </h1>
            <p class="text-xs text-stone-500 mt-1">Track public guest portal registrations, Google OAuth sign-ins, stay histories, and lifetime value.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/users.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 text-xs font-bold transition shadow-2xs">
                <span class="material-symbols-outlined text-base">badge</span>
                <span>Internal Staff Users</span>
            </a>

            <a href="<?= BASE_URL ?>/admin/guests.php?export=csv" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#343c0a] hover:bg-deep-olive text-white text-xs font-bold transition shadow-2xs">
                <span class="material-symbols-outlined text-base">download</span>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Live KPI Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Registered Guests -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Total Public Guests</span>
                <div class="font-headline font-bold text-2xl sm:text-3xl text-onyx-charcoal mt-1"><?= number_format($totalGuests) ?></div>
                <span class="text-[11px] text-stone-400">Captured via Portal & Booking</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-[#dfe8a6]/40 flex items-center justify-center text-[#343c0a]">
                <span class="material-symbols-outlined text-2xl">person</span>
            </div>
        </div>

        <!-- Google SSO Linked -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Google SSO Connected</span>
                <div class="font-headline font-bold text-2xl sm:text-3xl text-blue-600 mt-1"><?= number_format($googleGuests) ?></div>
                <span class="text-[11px] text-stone-400">Authenticated via Google</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                <span class="material-symbols-outlined text-2xl">verified_user</span>
            </div>
        </div>

        <!-- VIP / Loyal Guests -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">VIP / Loyal Guests</span>
                <div class="font-headline font-bold text-2xl sm:text-3xl text-amber-600 mt-1"><?= number_format($vipGuests) ?></div>
                <span class="text-[11px] text-stone-400">Marked as VIP by staff</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                <span class="material-symbols-outlined text-2xl">star</span>
            </div>
        </div>

        <!-- Restricted / Blocked Guests -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-stone-500">Restricted / Blocked</span>
                <div class="font-headline font-bold text-2xl sm:text-3xl text-rose-600 mt-1"><?= number_format($blockedGuests) ?></div>
                <span class="text-[11px] text-stone-400">Blocked from automated bookings</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                <span class="material-symbols-outlined text-2xl">block</span>
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-stone-200 shadow-2xs space-y-4">
        <form action="<?= BASE_URL ?>/admin/guests.php" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            
            <!-- Search Bar -->
            <div class="sm:col-span-5 relative">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-stone-400 text-lg">search</span>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, email, or phone..." 
                       class="w-full pl-9 pr-4 py-2 border border-stone-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
            </div>

            <!-- Filter By Provider / Status -->
            <div class="sm:col-span-3">
                <select name="filter" onchange="this.form.submit()" class="w-full py-2 px-3 border border-stone-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Public Guests (<?= $totalGuests ?>)</option>
                    <option value="google" <?= $filter === 'google' ? 'selected' : '' ?>>Google SSO Connected (<?= $googleGuests ?>)</option>
                    <option value="vip" <?= $filter === 'vip' ? 'selected' : '' ?>>VIP Guests Only (<?= $vipGuests ?>)</option>
                    <option value="flagged" <?= $filter === 'flagged' ? 'selected' : '' ?>>Flagged Guests</option>
                </select>
            </div>

            <!-- Sort By -->
            <div class="sm:col-span-3">
                <select name="sort" onchange="this.form.submit()" class="w-full py-2 px-3 border border-stone-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#343c0a]">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Sort: Latest Registered</option>
                    <option value="spend" <?= $sort === 'spend' ? 'selected' : '' ?>>Sort: Highest Lifetime Spend</option>
                    <option value="stays" <?= $sort === 'stays' ? 'selected' : '' ?>>Sort: Most Stays / Bookings</option>
                    <option value="login" <?= $sort === 'login' ? 'selected' : '' ?>>Sort: Last Active / Login</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Sort: Alphabetical (A-Z)</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="sm:col-span-1 flex items-center">
                <button type="submit" class="w-full py-2 px-3 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-bold transition flex items-center justify-center cursor-pointer">
                    <span>Filter</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Main Guests Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-2xs overflow-hidden">
        
        <?php if (empty($guests)): ?>
            <div class="p-12 text-center text-stone-400 space-y-3">
                <span class="material-symbols-outlined text-5xl text-stone-300">person_off</span>
                <h3 class="font-headline font-bold text-lg text-stone-700">No public guests found</h3>
                <p class="text-xs text-stone-500 max-w-md mx-auto">
                    <?= !empty($search) ? 'No guests matching "' . e($search) . '". Try clearing your search filters.' : 'Guest accounts will automatically populate when customers make reservations or sign in via Google OAuth on the guest portal.' ?>
                </p>
                <?php if (!empty($search) || $filter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>/admin/guests.php" class="inline-block mt-2 text-xs font-bold text-[#343c0a] hover:underline">Clear Search & Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-stone-50 text-[11px] font-bold uppercase tracking-wider text-stone-500 border-b border-stone-200">
                            <th class="py-3.5 px-4">Guest Profile</th>
                            <th class="py-3.5 px-4">Contact Info</th>
                            <th class="py-3.5 px-4">Auth Provider</th>
                            <th class="py-3.5 px-4">Stay & Spend Summary</th>
                            <th class="py-3.5 px-4">Status & Notes</th>
                            <th class="py-3.5 px-4">Registered Date</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 text-xs">
                        <?php foreach ($guests as $g): 
                            $initials = strtoupper(substr($g['name'], 0, 1));
                            $isGoogle = !empty($g['google_id']) || ($g['auth_provider'] === 'google');
                            $totalStays = (int)$g['total_stays'];
                            $totalSpend = (float)$g['total_spend'];
                            $activeStays = (int)$g['active_stays'];
                        ?>
                        <tr class="hover:bg-stone-50/70 transition">
                            
                            <!-- Guest Profile -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($g['avatar'])): ?>
                                        <img src="<?= e($g['avatar']) ?>" alt="<?= e($g['name']) ?>" class="w-9 h-9 rounded-full object-cover border border-stone-200 flex-shrink-0" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($g['name']) ?>&background=343c0a&color=fff'">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-[#343c0a] text-white font-bold flex items-center justify-center text-xs flex-shrink-0 shadow-2xs">
                                            <?= $initials ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div class="font-bold text-onyx-charcoal flex items-center gap-1.5">
                                            <span><?= e($g['name']) ?></span>
                                            <?php if ($g['status'] === 'vip'): ?>
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-extrabold border border-amber-300" title="VIP Guest">
                                                    <span>VIP</span>
                                                    <span class="material-symbols-outlined text-[11px]">star</span>
                                                </span>
                                            <?php elseif ($g['status'] === 'flagged'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-bold" title="Flagged Account">
                                                    Flagged
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[11px] text-stone-400 font-mono">ID #<?= $g['id'] ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Info -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <a href="mailto:<?= e($g['email']) ?>" class="text-[#343c0a] hover:underline font-medium block flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-stone-400">mail</span>
                                        <span><?= e($g['email']) ?></span>
                                    </a>
                                    <?php if (!empty($g['phone'])): ?>
                                    <a href="tel:<?= e($g['phone']) ?>" class="text-stone-500 hover:text-stone-800 font-mono text-[11px] flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-stone-400">call</span>
                                        <span><?= e($g['phone']) ?></span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Auth Provider -->
                            <td class="py-3.5 px-4">
                                <?php if ($isGoogle): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-semibold">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                        </svg>
                                        <span>Google SSO</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 border border-stone-200 text-[11px] font-medium">
                                        <span class="material-symbols-outlined text-xs text-stone-500">book_online</span>
                                        <span>Direct Booking</span>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($g['last_login_at'])): ?>
                                    <span class="block text-[10px] text-stone-400 mt-1">Active: <?= date('M d, Y H:i', strtotime($g['last_login_at'])) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Stay & Spend Summary -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-onyx-charcoal"><?= $totalStays ?> <?= $totalStays === 1 ? 'Stay' : 'Stays' ?></span>
                                        <span class="text-stone-300">•</span>
                                        <span class="font-bold text-emerald-700"><?= format_price($totalSpend) ?></span>
                                    </div>
                                    
                                    <?php if ($activeStays > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-semibold border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span><?= $activeStays ?> Upcoming / Active</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Status & Notes -->
                            <td class="py-3.5 px-4 max-w-xs">
                                <?php if (!empty($g['notes'])): ?>
                                    <div class="p-2 rounded-lg bg-amber-50/70 border border-amber-200/60 text-[11px] text-amber-900 leading-snug line-clamp-2" title="<?= e($g['notes']) ?>">
                                        <span class="font-bold">Staff Note:</span> <?= e($g['notes']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[11px] text-stone-400 italic">No notes</span>
                                <?php endif; ?>
                            </td>

                            <!-- Registered Date -->
                            <td class="py-3.5 px-4 text-[11px] text-stone-500 whitespace-nowrap">
                                <?= date('M d, Y', strtotime($g['created_at'])) ?>
                                <span class="block text-[10px] text-stone-400"><?= date('H:i', strtotime($g['created_at'])) ?></span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- View Stay History Modal Trigger -->
                                    <button type="button" onclick="openGuestHistoryModal('<?= e($g['email']) ?>', '<?= e(addslashes($g['name'])) ?>')" 
                                            class="p-1.5 rounded-lg border border-stone-200 bg-white hover:bg-stone-100 text-stone-700 hover:text-[#343c0a] transition cursor-pointer" title="View Booking History">
                                        <span class="material-symbols-outlined text-base">receipt_long</span>
                                    </button>

                                    <!-- Edit Guest & Notes Trigger -->
                                    <button type="button" onclick="openGuestEditModal(<?= htmlspecialchars(json_encode($g), ENT_QUOTES, 'UTF-8') ?>)" 
                                            class="p-1.5 rounded-lg border border-stone-200 bg-white hover:bg-stone-100 text-stone-700 hover:text-[#343c0a] transition cursor-pointer" title="Edit VIP Status & Notes">
                                        <span class="material-symbols-outlined text-base">edit_note</span>
                                    </button>

                                    <!-- Quick Email -->
                                    <a href="mailto:<?= e($g['email']) ?>" class="p-1.5 rounded-lg border border-stone-200 bg-white hover:bg-stone-100 text-stone-700 hover:text-[#343c0a] transition" title="Email Guest">
                                        <span class="material-symbols-outlined text-base">mail</span>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <?php if ($totalPages > 1): ?>
            <div class="p-4 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-stone-500">
                <div>
                    Showing <span class="font-bold text-stone-800"><?= $offset + 1 ?></span> to <span class="font-bold text-stone-800"><?= min($offset + $perPage, $totalMatching) ?></span> of <span class="font-bold text-stone-800"><?= $totalMatching ?></span> guests
                </div>

                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>" 
                           class="px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-50 font-bold transition">Prev</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>" 
                           class="px-3 py-1.5 rounded-lg border font-bold transition <?= $p === $page ? 'bg-[#343c0a] text-white border-[#343c0a]' : 'border-stone-300 bg-white hover:bg-stone-50 text-stone-700' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&sort=<?= urlencode($sort) ?>" 
                           class="px-3 py-1.5 rounded-lg border border-stone-300 bg-white hover:bg-stone-50 font-bold transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

<!-- =======================================================
     MODAL 1: Guest Stay History & Reservations Ledger
     ======================================================= -->
<div id="guestHistoryModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-stone-200 max-w-3xl w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="p-5 bg-stone-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-[#dfe8a6]">
                    <span class="material-symbols-outlined text-2xl">receipt_long</span>
                </div>
                <div>
                    <h3 class="font-headline font-bold text-lg text-white" id="modalGuestName">Guest Stay History</h3>
                    <p class="text-xs text-stone-400 font-mono" id="modalGuestEmail">Loading...</p>
                </div>
            </div>
            <button type="button" onclick="closeGuestHistoryModal()" class="text-stone-400 hover:text-white p-1 rounded-lg transition cursor-pointer">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>

        <!-- Modal Body (History Cards) -->
        <div class="p-6 max-h-[65vh] overflow-y-auto space-y-4" id="modalHistoryContent">
            <div class="py-12 text-center text-stone-400">
                <span class="material-symbols-outlined animate-spin text-3xl text-[#343c0a]">autorenew</span>
                <p class="text-xs font-semibold mt-2">Loading reservation history...</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-stone-50 border-t border-stone-200 flex items-center justify-between text-xs">
            <span class="text-stone-500" id="modalBookingSummary">0 bookings on file</span>
            <button type="button" onclick="closeGuestHistoryModal()" class="px-4 py-2 rounded-xl bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold transition cursor-pointer">
                Close
            </button>
        </div>

    </div>
</div>

<!-- =======================================================
     MODAL 2: Edit Guest Status & Private Staff Notes
     ======================================================= -->
<div id="guestEditModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-stone-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <form action="<?= BASE_URL ?>/admin/guests.php" method="POST">
            <input type="hidden" name="action" value="update_guest">
            <input type="hidden" name="guest_id" id="editGuestId" value="">

            <div class="p-5 border-b border-stone-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-[#343c0a]">edit_note</span>
                    <h3 class="font-headline font-bold text-lg text-onyx-charcoal" id="editModalTitle">Edit Guest Record</h3>
                </div>
                <button type="button" onclick="closeGuestEditModal()" class="text-stone-400 hover:text-stone-700 p-1 rounded-lg transition cursor-pointer">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                
                <div>
                    <label class="block font-bold text-stone-700 uppercase tracking-wider mb-1">Guest Status & Tag</label>
                    <select name="status" id="editGuestStatus" class="w-full border border-stone-300 rounded-xl p-2.5 font-medium focus:ring-2 focus:ring-[#343c0a]">
                        <option value="active">Active Guest</option>
                        <option value="vip">VIP Guest ⭐ (Special VIP Treatment)</option>
                        <option value="flagged">Flagged Account (Restricted / High Risk)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase tracking-wider mb-1">Phone / WhatsApp Number</label>
                    <input type="text" name="phone" id="editGuestPhone" placeholder="e.g. +855 23 999 888" 
                           class="w-full border border-stone-300 rounded-xl p-2.5 font-mono focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <div>
                    <label class="block font-bold text-stone-700 uppercase tracking-wider mb-1">Internal Staff Notes</label>
                    <textarea name="notes" id="editGuestNotes" rows="4" placeholder="Record internal preferences, special requests, anniversary notes, or dietary restrictions..."
                              class="w-full border border-stone-300 rounded-xl p-2.5 focus:ring-2 focus:ring-[#343c0a]"></textarea>
                    <p class="text-[11px] text-stone-400 mt-1">These notes are completely private and only visible to hotel staff in SoftBook CMS.</p>
                </div>

            </div>

            <div class="p-4 bg-stone-50 border-t border-stone-200 flex items-center justify-end gap-2 text-xs">
                <button type="button" onclick="closeGuestEditModal()" class="px-4 py-2 rounded-xl border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 font-bold transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#343c0a] hover:bg-deep-olive text-white font-bold transition shadow-2xs cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<script>
// =======================================================
// Modal 1: Fetch & Render Guest Stay History
// =======================================================
async function openGuestHistoryModal(email, name) {
    const modal = document.getElementById('guestHistoryModal');
    const nameEl = document.getElementById('modalGuestName');
    const emailEl = document.getElementById('modalGuestEmail');
    const contentEl = document.getElementById('modalHistoryContent');
    const summaryEl = document.getElementById('modalBookingSummary');

    nameEl.textContent = name + "'s Stay History";
    emailEl.textContent = email;
    contentEl.innerHTML = `
        <div class="py-12 text-center text-stone-400">
            <span class="material-symbols-outlined animate-spin text-3xl text-[#343c0a]">autorenew</span>
            <p class="text-xs font-semibold mt-2">Loading reservation history...</p>
        </div>
    `;

    modal.classList.remove('hidden');

    try {
        const response = await fetch('<?= BASE_URL ?>/admin/guests.php?action=history&email=' + encodeURIComponent(email));
        const data = await response.json();

        if (data.success && data.bookings && data.bookings.length > 0) {
            summaryEl.textContent = `${data.count} total ${data.count === 1 ? 'reservation' : 'reservations'} found`;
            
            let html = '';
            data.bookings.forEach(b => {
                const statusColors = {
                    'confirmed': 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'checked_in': 'bg-blue-100 text-blue-800 border-blue-300',
                    'checked_out': 'bg-stone-100 text-stone-700 border-stone-300',
                    'cancelled': 'bg-rose-100 text-rose-700 border-rose-300',
                    'pending': 'bg-amber-100 text-amber-800 border-amber-300'
                };
                const statusClass = statusColors[b.status] || 'bg-stone-100 text-stone-700 border-stone-200';

                html += `
                    <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 hover:bg-white transition flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-[#343c0a]">${b.booking_reference}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${statusClass}">${b.status}</span>
                                ${b.payment_status ? `<span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-stone-200 text-stone-600">${b.payment_status}</span>` : ''}
                            </div>
                            <div class="font-headline font-bold text-sm text-onyx-charcoal">${b.room_name || 'Accommodation'}</div>
                            <div class="text-xs text-stone-500 flex items-center gap-2">
                                <span>📅 ${b.check_in_date} ➔ ${b.check_out_date} (${b.nights || 1} nts)</span>
                                <span>•</span>
                                <span>👥 ${b.adults || 1} Adults${b.children ? `, ${b.children} Ch` : ''}</span>
                            </div>
                            ${b.offer_title ? `<div class="text-[11px] text-amber-800 font-medium">🏷️ Promo: ${b.offer_title}</div>` : ''}
                        </div>

                        <div class="text-left sm:text-right flex sm:flex-col items-center sm:items-end justify-between gap-2">
                            <div class="font-headline font-bold text-base text-emerald-700">$${parseFloat(b.total_price || 0).toFixed(2)}</div>
                            <a href="<?= BASE_URL ?>/admin/booking-detail.php?id=${b.id}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#343c0a] hover:bg-deep-olive text-white text-[11px] font-bold transition">
                                <span>View Voucher</span>
                                <span class="material-symbols-outlined text-xs">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                `;
            });
            contentEl.innerHTML = html;
        } else {
            summaryEl.textContent = '0 bookings found';
            contentEl.innerHTML = `
                <div class="p-8 text-center text-stone-400">
                    <span class="material-symbols-outlined text-4xl text-stone-300">receipt</span>
                    <p class="text-xs font-semibold mt-2">No direct bookings found for this email address.</p>
                </div>
            `;
        }
    } catch (e) {
        contentEl.innerHTML = `
            <div class="p-6 text-center text-rose-500">
                <span class="material-symbols-outlined text-3xl">error</span>
                <p class="text-xs font-semibold mt-1">Failed to fetch reservation history: ${e.message}</p>
            </div>
        `;
    }
}

function closeGuestHistoryModal() {
    document.getElementById('guestHistoryModal').classList.add('hidden');
}

// =======================================================
// Modal 2: Edit Guest Status & Notes
// =======================================================
function openGuestEditModal(guest) {
    document.getElementById('editGuestId').value = guest.id;
    document.getElementById('editModalTitle').textContent = 'Edit ' + guest.name;
    document.getElementById('editGuestStatus').value = guest.status || 'active';
    document.getElementById('editGuestPhone').value = guest.phone || '';
    document.getElementById('editGuestNotes').value = guest.notes || '';
    document.getElementById('guestEditModal').classList.remove('hidden');
}

function closeGuestEditModal() {
    document.getElementById('guestEditModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
