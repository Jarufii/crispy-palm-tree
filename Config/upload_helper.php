<?php
// Config/upload_helper.php — Secure file upload utility
defined('UMDC_APP') or define('UMDC_APP', true);

/**
 * Validate and save an uploaded file.
 * Returns relative path on success, or throws Exception on failure.
 *
 * @param array  $file       $_FILES['field']
 * @param string $subdir     'profiles' | 'campaigns' | 'verification' | 'delivery'
 * @param array  $allowedExt Allowed extensions e.g. ['jpg','jpeg','png','webp']
 * @param int    $maxBytes   Default 5 MB
 * @return string  Relative path like 'uploads/profiles/abc123.jpg'
 */
function umdc_upload_file(
    array  $file,
    string $subdir,
    array  $allowedExt = ['jpg','jpeg','png','webp'],
    int    $maxBytes   = 5 * 1024 * 1024
): string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory available.',
            UPLOAD_ERR_CANT_WRITE => 'Could not write file to disk.',
        ][$file['error']] ?? 'Unknown upload error.';
        throw new RuntimeException($msg);
    }

    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File is too large (max ' . round($maxBytes / 1048576) . ' MB).');
    }

    // MIME type validation via finfo (not just extension)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $mimeMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
    ];

    if (!isset($mimeMap[$mimeType])) {
        throw new RuntimeException('Invalid file type. Allowed: ' . implode(', ', $allowedExt) . '.');
    }

    $ext = $mimeMap[$mimeType];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File type not allowed for this upload.');
    }

    // Build safe destination path
    $baseDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
    $uploadDir = $baseDir . '/uploads/' . $subdir . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    // Random filename to prevent guessing / path traversal
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to save file. Please try again.');
    }

    return 'uploads/' . $subdir . '/' . $filename;
}

/**
 * Delete an old file safely (only within uploads/).
 */
function umdc_delete_file(string $relativePath): void {
    if (!$relativePath) return;
    // Security: must start with uploads/
    if (!str_starts_with($relativePath, 'uploads/')) return;
    $baseDir  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
    $fullPath = $baseDir . '/' . ltrim($relativePath, '/');
    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Return a safe URL for a stored file, or a default placeholder.
 */
function umdc_file_url(string $path, string $default = ''): string {
    if (!$path) return $default;
    return '/' . ltrim($path, '/');
}
