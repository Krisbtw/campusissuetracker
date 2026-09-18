<?php
/**
 * FixMyCampus - Vercel Serverless Router / Front Controller
 * Routes incoming requests on Vercel to the appropriate PHP script or static asset.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Clean path
$path = '/' . ltrim($path, '/');

// Base directory (project root)
$baseDir = realpath(__DIR__ . '/..');

// Handle root path
if ($path === '/' || $path === '/index.php' || $path === '') {
    $targetFile = $baseDir . '/index.php';
} else {
    $targetFile = $baseDir . $path;

    // If directory requested, look for dashboard.php or index.php
    if (is_dir($targetFile)) {
        if (file_exists($targetFile . '/dashboard.php')) {
            $targetFile = $targetFile . '/dashboard.php';
        } elseif (file_exists($targetFile . '/index.php')) {
            $targetFile = $targetFile . '/index.php';
        }
    } elseif (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
        // Clean URLs without .php extension
        $targetFile = $targetFile . '.php';
    }
}

// Security: Prevent directory traversal outside project root
$realTarget = realpath($targetFile);
if (!$realTarget || strpos($realTarget, $baseDir) !== 0 || !file_exists($realTarget)) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>The requested resource was not found on this server.</p>";
    exit();
}

// If the requested file is a static asset (CSS, JS, images, fonts)
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
if ($ext !== 'php') {
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'html'  => 'text/html',
    ];
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($realTarget));
    readfile($realTarget);
    exit();
}

// Set up server environment so target script behaves as if invoked directly
$_SERVER['SCRIPT_FILENAME'] = $realTarget;
$_SERVER['SCRIPT_NAME'] = str_replace($baseDir, '', $realTarget);
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
if (empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['VERCEL_URL'] ?? (getenv('VERCEL_URL') ?: 'campusissuetracker-iota.vercel.app'));
}
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $_SERVER['HTTPS'] = 'on';
}

// Switch working directory to target file's directory so relative includes (e.g. '../config/db.php') resolve properly
chdir(dirname($realTarget));

// Only set default text/html header if not routing to an API script
$isApiScript = (strpos($realTarget, DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR) !== false || strpos($realTarget, '/api/') !== false);
if (!$isApiScript) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Execute the requested PHP script
require $realTarget;
