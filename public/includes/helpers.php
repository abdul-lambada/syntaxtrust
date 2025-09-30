<?php
// Common helpers available to public pages and components
require_once __DIR__ . '/../../config/database.php';

if (!function_exists('getSetting')) {
    function getSetting($key, $default = '', $includePrivate = false) {
        // Public context: never return private settings regardless of $includePrivate
        global $pdo;
        try {
            if (!($pdo instanceof PDO)) {
                return $default;
            }
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND is_public = 1 LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// Normalize asset URLs so paths stored like "uploads/..." work from within public/*.php
if (!function_exists('assetUrl')) {
    function assetUrl($path) {
        $path = (string)$path;
        if ($path === '') return '';
        // Normalize slashes
        $norm = str_replace('\\', '/', $path);
        // Absolute URLs or data URIs
        if (preg_match('/^(https?:)?\/\//i', $norm) || strpos($norm, 'data:') === 0) {
            return $norm;
        }
        // If site-absolute path like "/something/..."
        if (strlen($norm) > 0 && $norm[0] === '/') {
            // Special-case: "/uploads/..." should point one level up from public/
            if (strpos($norm, '/uploads/') === 0) {
                return '..' . $norm;
            }
            // Otherwise return as-is
            return $norm;
        }
        // Handle paths starting with "uploads/"
        if (strpos($norm, 'uploads/') === 0) {
            return '../' . $norm;
        }
        // Handle wrongly stored paths like "public/uploads/..." -> "../uploads/..."
        if (strpos($norm, 'public/uploads/') === 0) {
            return '../' . substr($norm, strlen('public/'));
        }
        // If the string contains uploads segment anywhere (e.g., absolute fs path), extract from uploads/
        $pos = stripos($norm, 'uploads/');
        if ($pos !== false) {
            $rel = substr($norm, $pos); // start at 'uploads/'
            $rel = ltrim($rel, '/');
            return '../' . $rel;
        }
        // Fallback: leave as-is (assumed relative to current file)
        return $norm;
    }
}
