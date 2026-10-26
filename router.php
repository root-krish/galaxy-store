<?php
declare(strict_types=1);

// Router for PHP's development server. Serve only the public entry point and assets.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (in_array($path, ['/', '/index.php'], true)) {
    require __DIR__ . '/index.php';
    return true;
}
$file = realpath(__DIR__ . $path);
$assets = realpath(__DIR__ . '/assets') . DIRECTORY_SEPARATOR;
if ($file && str_starts_with($file, $assets) && is_file($file)
    && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'ico', 'woff2'], true)) {
    return false;
}
http_response_code(404);
echo 'Not found';
