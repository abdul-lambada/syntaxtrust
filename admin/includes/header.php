<?php
require_once __DIR__ . '/../../config/app.php';
// Prevent caching of admin pages to avoid back-button showing protected content after logout
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

// Normalize relative paths (uploads, assets) to absolute URLs under the site root
if (!function_exists('assetUrlAdmin')) {
    function assetUrlAdmin(string $path): string {
        $path = trim($path);
        if ($path === '') return '';

        // Normalize slashes (handle Windows backslashes) for detection
        $norm = str_replace('\\', '/', $path);

        $base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
        // Ensure base is '' or starts with '/'
        if ($base !== '' && $base[0] !== '/') { $base = '/' . $base; }

        // Absolute URL
        if (preg_match('/^https?:\/\//i', $norm)) return $norm;

        // If already site-absolute
        if ($norm[0] === '/') {
            // Ensure bare "/uploads/..." is rooted under site base
            if (strpos($norm, '/uploads/') === 0) {
                return $base . $norm;
            }
            return $norm;
        }

        // Extract uploads segment from any stored path (absolute fs or prefixed)
        $uploadsPos = stripos($norm, 'uploads/');
        if ($uploadsPos !== false) {
            $rel = substr($norm, $uploadsPos); // start at 'uploads/'
            $rel = ltrim($rel, '/');
            return rtrim($base, '/') . '/' . $rel;
        }

        // Handle paths like 'public/uploads/...'
        if (strpos($norm, 'public/uploads/') === 0) {
            return rtrim($base, '/') . '/' . substr($norm, strlen('public/'));
        }

        // Admin-local assets directory
        if (strpos($norm, 'assets/') === 0) {
            return rtrim($base, '/') . '/admin/' . $norm;
        }

        // Fallback: treat as site-relative under base
        return rtrim($base, '/') . '/' . ltrim($norm, '/');
    }
}
// Determine dynamic page title: use $pageTitle if provided, else derive from script name
if (!isset($pageTitle) || trim((string)$pageTitle) === '') {
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? 'index.php');
    $base = basename($script, '.php');
    if ($base === '' || $base === false) { $base = 'Dashboard'; }
    if ($base === 'index') { $base = 'Dashboard'; }
    $base = str_replace(['-', '_'], ' ', (string)$base);
    $pageTitle = ucwords($base);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>SyntaxTrust Admin - <?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Custom fonts for this template-->
    <link href="assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="assets/css/sb-admin-2.min.css" rel="stylesheet">

    <!-- Custom styles for this page -->
    <link href="assets/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

</head>
