<?php
// Redirect to public/ if accessed from project root
$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
if (strpos($uri, '/public/') !== 0) {
    header('Location: /public/');
    exit;
}
// Fallback: if already under /public, do nothing (should not happen here)
?>
