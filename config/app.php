<?php
// Global application path configuration
// Adjust these if the project directory changes (e.g., when moving to production)
// Must start with a leading slash, must NOT end with a trailing slash
require_once __DIR__ . '/env.php';

// Dynamically set environment constant based on env.php
if (!defined('ENVIRONMENT')) {
  $env = app_env();
  define('ENVIRONMENT', $env === 'production' ? 'production' : 'development');
}

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
  // Some hosting places project directly at domain root, so APP_BASE_PATH may be ''
  define('PUBLIC_BASE_PATH', APP_BASE_PATH . ($hasPublicInPath ? '/public' : ''));
}

if (ENVIRONMENT === 'production') {
  // Hide errors in production
  error_reporting(0);
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
} else {
  // Show errors in development
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
}
