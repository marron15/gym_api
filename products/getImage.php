<?php

// CORS headers for web preview
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Basic path sanitization: allow only under uploads/products
$rel = isset($_GET['path']) ? $_GET['path'] : '';
$rel = ltrim($rel, '/\\');
if (strpos($rel, 'uploads/products/') !== 0) {
    http_response_code(400);
    echo 'Invalid path';
    exit;
}

$full = dirname(__DIR__) . '/' . $rel; // gym_api/ + uploads/products/...
if (!file_exists($full) || !is_file($full)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// Detect mime by extension
$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'png') $mime = 'image/png';
elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
elseif ($ext === 'webp') $mime = 'image/webp';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800');
readfile($full);
exit;

?>


