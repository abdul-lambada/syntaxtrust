<?php
// Global application path configuration
// Adjust these if the project directory changes (e.g., when moving to production)
// Must start with a leading slash, must NOT end with a trailing slash
if (!defined('APP_BASE_PATH')) {
  define('APP_BASE_PATH', '/syntaxtrust');
}
if (!defined('PUBLIC_BASE_PATH')) {
  define('PUBLIC_BASE_PATH', APP_BASE_PATH . '/public');
}
