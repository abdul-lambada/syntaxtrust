<?php
// Reusable secure upload helper
// Usage:
// $saved = secure_upload('input_name', 'uploads/settings/', [
//     'maxBytes' => 2 * 1024 * 1024,
//     'allowedExt' => ['jpg','jpeg','png','webp','gif'],
//     'allowedMime' => ['image/jpeg','image/png','image/webp','image/gif'],
//     'prefix' => 'img_'
// ], $currentPath);
// Returns saved relative path on success, or $currentPath/null if unchanged.

if (!function_exists('secure_upload')) {
    function secure_upload(string $input, string $destDir, array $policy, ?string $currentPath = null)
    {
        if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
            return $currentPath; // no change
        }

        $maxBytes = $policy['maxBytes'] ?? (2 * 1024 * 1024);
        $allowedExt = $policy['allowedExt'] ?? ['jpg','jpeg','png','webp'];
        $allowedMime = $policy['allowedMime'] ?? ['image/jpeg','image/png','image/webp'];
        $prefix = $policy['prefix'] ?? 'file_';

        // Ensure directory exists
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $tmp  = $_FILES[$input]['tmp_name'];
        $orig = $_FILES[$input]['name'] ?? '';
        $size = (int)($_FILES[$input]['size'] ?? 0);

        if ($size <= 0 || $size > $maxBytes) {
            return $currentPath;
        }

        // MIME detection
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? finfo_file($finfo, $tmp) : (function_exists('mime_content_type') ? mime_content_type($tmp) : null);
        if ($finfo) { finfo_close($finfo); }
        if (!$mime || !in_array($mime, $allowedMime, true)) {
            return $currentPath;
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
        ];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            // infer from MIME
            $ext = $extMap[$mime] ?? null;
        }
        if (!$ext || !in_array($ext, $allowedExt, true)) {
            return $currentPath;
        }

        $fname = $prefix . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $destPath = rtrim($destDir, "/\\") . DIRECTORY_SEPARATOR . $fname;

        if (!move_uploaded_file($tmp, $destPath)) {
            return $currentPath;
        }

        // Safe delete old file if within destDir
        if ($currentPath) {
            $base = realpath(rtrim($destDir, "/\\"));
            $target = realpath($currentPath);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        return $destPath;
    }
}
