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
  define('PUBLIC_BASE_PATH', APP_BASE_PATH . '/public');
}
