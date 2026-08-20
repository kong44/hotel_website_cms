<?php
/**
 * Indra Hotel - Admin Media Management API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploader.php';

// Require Admin / Editor Auth
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$pdo = getDB();

// Ensure existing files in /uploads/ are synced to database
Database::syncUploadsFolderToDatabase($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ----------------------------------------------------
// 1. LIST MEDIA FILES (Supports filter & pagination)
// ----------------------------------------------------
if ($action === 'list' && $method === 'GET') {
    $folder = trim($_GET['folder'] ?? 'all');
    $type = trim($_GET['type'] ?? 'all');
    $search = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 24)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if ($folder !== 'all' && !empty($folder)) {
        $where[] = "folder = ?";
        $params[] = $folder;
    }

    if ($type !== 'all' && !empty($type)) {
        $where[] = "file_type = ?";
        $params[] = $type;
    }

    if (!empty($search)) {
        $where[] = "(filename LIKE ? OR original_name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count Total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM media_uploads {$whereClause}");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Fetch Items
    $sql = "SELECT * FROM media_uploads {$whereClause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format item attributes
    foreach ($items as &$item) {
        $item['formatted_size'] = format_file_size((int)$item['file_size']);
        $item['formatted_date'] = date('M d, Y H:i', strtotime($item['created_at']));
    }

    // Fetch unique available folders
    $foldersStmt = $pdo->query("SELECT DISTINCT folder FROM media_uploads WHERE folder IS NOT NULL AND folder != '' ORDER BY folder ASC");
    $folders = $foldersStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'available_folders' => $folders
        ]
    ]);
    exit;
}

// ----------------------------------------------------
// 2. DELETE MEDIA FILE
// ----------------------------------------------------
if ($action === 'delete' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid media ID.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM media_uploads WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Media file not found.']);
        exit;
    }

    // Check if media file is currently in use across the CMS before deleting
    $usageCheck = check_media_in_use($pdo, $media);
    if ($usageCheck['in_use']) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete this media asset because it is currently in use in: ' . implode(', ', $usageCheck['usages']) . '. Please replace or remove the media from those sections before deleting.',
            'in_use' => true,
            'usages' => $usageCheck['usages']
        ]);
        exit;
    }

    // Delete file from disk if exists
    $filePath = ROOT_PATH . '/' . ltrim($media['file_path'], '/');
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    // Delete record from database
    $delStmt = $pdo->prepare("DELETE FROM media_uploads WHERE id = ?");
    $delStmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Media file deleted successfully.'
    ]);
    exit;

}

// ----------------------------------------------------
// 3. UPLOAD MEDIA DIRECTLY
// ----------------------------------------------------
if ($action === 'upload' && $method === 'POST') {
    $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder'] ?? 'general');
    $fileInput = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['video'] ?? null;

    if (!$fileInput && !empty($_FILES)) {
        foreach ($_FILES as $f) {
            if (!empty($f['tmp_name'])) {
                $fileInput = $f;
                break;
            }
        }
    }

    if (!$fileInput) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No media file provided for upload.']);
        exit;
    }

    $result = Uploader::upload($fileInput, $folder);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'url' => $result['url'],
            'filename' => $result['filename']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action requested.']);
exit;

/**
 * Format bytes to readable string
 */
function format_file_size(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    }
    return '0 KB';
}

/**
 * Check if a media asset is currently used in any CMS tables
 */
function check_media_in_use(PDO $pdo, array $media): array {
    $usages = [];
    $filename = $media['filename'] ?? '';
    $filePath = $media['file_path'] ?? '';
    $url = $media['url'] ?? '';

    if (empty($filename)) {
        return ['in_use' => false, 'usages' => []];
    }

    $searchTerm = "%{$filename}%";

    // 1. Rooms (Accommodations) - Primary Image & Gallery JSON
    try {
        $stmt = $pdo->prepare("SELECT name, image_url, gallery_json FROM rooms WHERE image_url LIKE ? OR gallery_json LIKE ?");
        $stmt->execute([$searchTerm, $searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['name'] ?: 'Unnamed Suite';
            if (!empty($row['image_url']) && str_contains($row['image_url'], $filename)) {
                $usages[] = "Accommodations ('{$name}' Primary Image)";
            }
            if (!empty($row['gallery_json']) && str_contains($row['gallery_json'], $filename)) {
                $usages[] = "Accommodations ('{$name}' Gallery)";
            }
        }
    } catch (Throwable $e) {}

    // 2. Dining & Wellness
    try {
        $stmt = $pdo->prepare("SELECT title, type FROM dining_wellness WHERE image_url LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = ucfirst($row['type'] ?: 'Dining');
            $usages[] = "{$type} & Wellness ('{$row['title']}')";
        }
    } catch (Throwable $e) {}

    // 3. Special Offers
    try {
        $stmt = $pdo->prepare("SELECT title FROM special_offers WHERE image_url LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usages[] = "Special Offers ('{$row['title']}')";
        }
    } catch (Throwable $e) {}

    // 4. Photo Gallery
    try {
        $stmt = $pdo->prepare("SELECT title FROM gallery WHERE image_url LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usages[] = "Photo Gallery ('{$row['title']}')";
        }
    } catch (Throwable $e) {}

    // 5. Location Spots - Image & Gallery JSON
    try {
        $stmt = $pdo->prepare("SELECT title, image_url, gallery_json FROM location_spots WHERE image_url LIKE ? OR gallery_json LIKE ?");
        $stmt->execute([$searchTerm, $searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['image_url']) && str_contains($row['image_url'], $filename)) {
                $usages[] = "Prime Locations ('{$row['title']}' Primary Image)";
            }
            if (!empty($row['gallery_json']) && str_contains($row['gallery_json'], $filename)) {
                $usages[] = "Prime Locations ('{$row['title']}' Gallery)";
            }
        }
    } catch (Throwable $e) {}

    // 6. Site Settings (e.g. Logo, Favicon, Hero Video/Image)
    try {
        $stmt = $pdo->prepare("SELECT setting_key FROM site_settings WHERE setting_value LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keyLabel = ucwords(str_replace('_', ' ', $row['setting_key']));
            $usages[] = "Site Brand Setting ('{$keyLabel}')";
        }
    } catch (Throwable $e) {}

    // 7. Users / Staff Avatars
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE avatar LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usages[] = "User Profile Avatar ('{$row['name']}')";
        }
    } catch (Throwable $e) {}

    // 8. Public Guest Avatars
    try {
        $stmt = $pdo->prepare("SELECT name FROM guest_users WHERE avatar LIKE ?");
        $stmt->execute([$searchTerm]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usages[] = "Guest Profile Avatar ('{$row['name']}')";
        }
    } catch (Throwable $e) {}

    $usages = array_values(array_unique($usages));

    return [
        'in_use' => !empty($usages),
        'usages' => $usages
    ];
}

