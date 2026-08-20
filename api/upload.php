<?php
/**
 * Indra Hotel - Universal Media Upload Endpoint (Supports Direct & Chunked Uploads for Large Images & Videos)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploader.php';

// Require authentication for upload
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// 1. Handle Chunked Upload (Used for large files / videos to bypass server post_max_size limits)
if (isset($_POST['chunk_index'])) {
    $chunkIndex = (int)$_POST['chunk_index'];
    $totalChunks = (int)$_POST['total_chunks'];
    $fileUuid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['file_uuid'] ?? '');
    $originalName = basename($_POST['filename'] ?? 'media_file');
    $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder'] ?? 'general');

    if (empty($fileUuid) || $totalChunks < 1 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid chunk upload parameters.']);
        exit;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, Uploader::ALLOWED_EXTENSIONS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file extension: .' . $ext . '. Allowed: ' . implode(', ', Uploader::ALLOWED_EXTENSIONS)]);
        exit;
    }

    $chunkFile = $_FILES['chunk'] ?? null;
    if (!$chunkFile || empty($chunkFile['tmp_name']) || !file_exists($chunkFile['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Chunk data was not received.']);
        exit;
    }

    $chunksDir = ROOT_PATH . '/uploads/chunks/';
    if (!is_dir($chunksDir)) {
        mkdir($chunksDir, 0755, true);
    }

    $partPath = $chunksDir . $fileUuid . '_' . $chunkIndex . '.part';
    if (!move_uploaded_file($chunkFile['tmp_name'], $partPath)) {
        @copy($chunkFile['tmp_name'], $partPath);
    }

    // Check if this was the last chunk -> Assemble all parts
    if ($chunkIndex === $totalChunks - 1) {
        $targetDir = ROOT_PATH . '/uploads/' . ($folder ? $folder . '/' : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $cleanBaseName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)));
        $cleanBaseName = substr($cleanBaseName, 0, 30);
        $finalFilename = $cleanBaseName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $finalPath = $targetDir . $finalFilename;

        $out = fopen($finalPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $p = $chunksDir . $fileUuid . '_' . $i . '.part';
            if (file_exists($p)) {
                $in = fopen($p, 'rb');
                while ($buff = fread($in, 8192)) {
                    fwrite($out, $buff);
                }
                fclose($in);
                @unlink($p);
            }
        }
        fclose($out);

        $publicUrl = BASE_URL . '/uploads/' . ($folder ? $folder . '/' : '') . $finalFilename;
        
        // Record in media_uploads database
        Uploader::recordMedia($finalFilename, $originalName, $folder, $publicUrl);

        echo json_encode([
            'success' => true,
            'url' => $publicUrl,
            'filename' => $finalFilename
        ]);
        exit;

    } else {
        // Intermediate chunk saved
        echo json_encode([
            'success' => true,
            'chunk_received' => $chunkIndex,
            'total_chunks' => $totalChunks
        ]);
        exit;
    }
}

// 2. Handle Standard Single-Request Upload (Images & Small Files)
$folder = $_POST['folder'] ?? 'general';
$fileInput = null;

if (!empty($_FILES['video'])) {
    $fileInput = $_FILES['video'];
} elseif (!empty($_FILES['image'])) {
    $fileInput = $_FILES['image'];
} elseif (!empty($_FILES['file'])) {
    $fileInput = $_FILES['file'];
} else {
    // Look for first available file in $_FILES
    foreach ($_FILES as $key => $f) {
        if (!empty($f['tmp_name'])) {
            $fileInput = $f;
            break;
        }
    }
}

if (!$fileInput) {
    // Check if POST was truncated due to exceeding post_max_size
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_FILES)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'File size (' . round($contentLength / 1048576, 1) . 'MB) exceeds server PHP post_max_size. Using chunked upload automatically.'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No media file uploaded.']);
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
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
