<?php
// Global application path configuration
// Adjust these if the project directory changes (e.g., when moving to production)
// Must start with a leading slash, must NOT end with a trailing slash
require_once __DIR__ . '/env.php';
if (!defined('APP_BASE_PATH')) {
  $base = app_base_path();
  // Normalize: leading slash, no trailing slash; allow empty for domain root
  if ($base === '/' || $base === null) { $base = ''; }
  if ($base !== '' && substr($base, 0, 1) !== '/') { $base = '/' . $base; }
  $base = rtrim($base, '/');
  define('APP_BASE_PATH', $base);
}
if (!defined('PUBLIC_BASE_PATH')) {
  // If current script path includes '/public', keep it in the base path (dev/shared hosting)
  // If webroot is already pointed to the 'public' folder, do NOT add '/public'
  $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
  $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
  $hasPublicInPath = (strpos($scriptDir, '/public') !== false);
  define('PUBLIC_BASE_PATH', APP_BASE_PATH . ($hasPublicInPath ? '/public' : ''));
}
