<?php
// Redirect to public/ if accessed from project root in any environment (root or subfolder)
require_once __DIR__ . '/config/app.php';

// Always send users to the public area; compute the correct base automatically
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
$target = rtrim($base, '/') . '/public/';
header('Location: ' . $target);
exit;
?>
