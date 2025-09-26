<?php
// Common helpers available to public pages and components
require_once __DIR__ . '/../../config/database.php';

if (!function_exists('getSetting')) {
    function getSetting($key, $default = '', $includePrivate = false) {
        // Public context: never return private settings regardless of $includePrivate
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND is_public = 1 LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
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
        // Absolute URLs or data URIs
        if (preg_match('/^(https?:)?\/\//i', $path) || strpos($path, 'data:') === 0) {
            return $path;
        }
        // Already starts from project root e.g. "/syntaxtrust/uploads/..." -> keep
        if (strpos($path, '/uploads/') === 0) {
            return '..' . $path;
        }
        // Typical stored paths: "uploads/.." need one level up from public/
        if (strpos($path, 'uploads/') === 0) {
            return '../' . $path;
        }
        // Fallback: leave as-is (assumed relative to current file)
        return $path;
    }
}
