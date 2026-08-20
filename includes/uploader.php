<?php
/**
 * Indra Hotel - Image Upload & Media Management Helper
 */

require_once __DIR__ . '/../config.php';

class Uploader {
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico', 'mp4', 'webm', 'ogv', 'mov', 'm4v'];
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
        'video/x-m4v'
    ];
    public const MAX_FILE_SIZE = 104857600; // 100 MB

    /**
     * Process an uploaded file array from $_FILES
     *
     * @param array $file $_FILES['input_name']
     * @param string $folder Subfolder under /uploads/ (e.g. 'rooms', 'brand', 'gallery')
     * @return array ['success' => bool, 'url' => string, 'filename' => string, 'error' => string]
     */
    public static function upload(array $file, string $folder = 'general'): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file parameter.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return ['success' => false, 'error' => 'File exceeds maximum upload size limit (10MB).'];
                case UPLOAD_ERR_NO_FILE:
                    return ['success' => false, 'error' => 'No file was uploaded.'];
                default:
                    return ['success' => false, 'error' => 'Upload error occurred. Code: ' . $file['error']];
            }
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File size exceeds the 10MB limit.'];
        }

        // Validate Extension
        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)];
        }

        // Validate MIME type (if finfo is available)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Allow standard images and SVG
            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true) && $ext !== 'svg' && $ext !== 'ico') {
                return ['success' => false, 'error' => 'Invalid MIME type: ' . $mime];
            }
        }

        // Target Directory
        $safeFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder);
        $targetDir = ROOT_PATH . '/uploads/' . ($safeFolder ? $safeFolder . '/' : '');

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Generate unique, clean file name
        $cleanBaseName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)));
        $cleanBaseName = substr($cleanBaseName, 0, 30);
        $uniqueFilename = $cleanBaseName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $targetDir . $uniqueFilename;

        $moved = is_uploaded_file($file['tmp_name']) 
            ? move_uploaded_file($file['tmp_name'], $targetPath) 
            : (rename($file['tmp_name'], $targetPath) || copy($file['tmp_name'], $targetPath));

        if ($moved) {
            $publicUrl = BASE_URL . '/uploads/' . ($safeFolder ? $safeFolder . '/' : '') . $uniqueFilename;
            
            // Record in media_uploads database
            self::recordMedia($uniqueFilename, $originalName, $safeFolder, $publicUrl);

            return [
                'success' => true,
                'url' => $publicUrl,
                'filename' => $uniqueFilename,
                'error' => ''
            ];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file to destination.'];
    }

    /**
     * Record uploaded file metadata in media_uploads table
     */
    public static function recordMedia(string $filename, string $originalName, string $folder, string $publicUrl): int {
        try {
            require_once __DIR__ . '/db.php';
            $pdo = getDB();
            $safeFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder);
            $relPath = 'uploads/' . ($safeFolder ? $safeFolder . '/' : '') . $filename;
            $fullPath = ROOT_PATH . '/' . $relPath;

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];
            $videoExts = ['mp4', 'webm', 'ogv', 'mov', 'm4v'];

            $fileType = 'other';
            if (in_array($ext, $imageExts, true)) {
                $fileType = 'image';
            } elseif (in_array($ext, $videoExts, true)) {
                $fileType = 'video';
            }

            $mimeType = function_exists('mime_content_type') && file_exists($fullPath) ? @mime_content_type($fullPath) : null;
            $size = file_exists($fullPath) ? filesize($fullPath) : 0;
            $userId = class_exists('Auth') && Auth::check() ? (Auth::id() ?: null) : null;

            $stmt = $pdo->prepare("INSERT INTO media_uploads (filename, original_name, file_path, url, folder, file_type, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $filename,
                $originalName ?: $filename,
                $relPath,
                $publicUrl,
                $safeFolder ?: 'general',
                $fileType,
                $mimeType ?: null,
                $size,
                $userId
            ]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

