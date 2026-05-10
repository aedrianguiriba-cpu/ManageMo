<?php
// API entry point - routes all requests to the main index.php
// This allows Vercel to recognize and execute PHP files

$request_uri = $_SERVER['REQUEST_URI'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);

// Remove the /api prefix from the URI
if (strpos($request_uri, '/api') === 0) {
    $request_uri = substr($request_uri, 4);
}

// If empty, set to root
if (empty($request_uri) || $request_uri === '/') {
    $request_uri = '/index.php';
}

// Route to the appropriate file
$file_path = dirname(__DIR__) . $request_uri;

// If it's a directory or doesn't exist, try index.php
if (is_dir($file_path) || !file_exists($file_path)) {
    $file_path = dirname(__DIR__) . '/index.php';
}

// If the file is a PHP file, include it
if (file_exists($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'php') {
    chdir(dirname($file_path));
    include $file_path;
} else {
    // For static files, serve them
    http_response_code(404);
    echo 'Not Found';
}
