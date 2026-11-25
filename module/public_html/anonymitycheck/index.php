<?php
// Local/public_html entrypoint for the anonymity scanner.
// Keeps the real implementation in AnonymityCheckController while allowing:
// http://localhost:8080/anonymitycheck/
$originalHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$originalUri = $_SERVER['REQUEST_URI'] ?? '/anonymitycheck/';
$port = '';
if (strpos($originalHost, ':') !== false) {
    $port = ':' . substr(strrchr($originalHost, ':'), 1);
}

$path = parse_url($originalUri, PHP_URL_PATH) ?: '/anonymitycheck/';
$query = parse_url($originalUri, PHP_URL_QUERY);
$subPath = '/';
if (strpos($path, '/anonymitycheck') === 0) {
    $subPath = substr($path, strlen('/anonymitycheck')) ?: '/';
    $subPath = '/' . ltrim($subPath, '/');
}

require_once dirname(__DIR__, 2) . '/app/Core/HttpRequest.php';
if (!HttpRequest::isLocalHost()) {
    // Fixed destination prevents host-header redirects. 308 preserves old POST API bodies.
    $destination = 'https://anonymitycheck.fezadan.org' . $subPath . ($query !== null && $query !== '' ? '?' . $query : '');
    if (preg_match('/[\r\n]/', $destination)) {
        http_response_code(400);
        exit;
    }
    header('Cache-Control: private, no-store, max-age=0');
    header('Location: ' . $destination, true, in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) ? 301 : 308);
    exit;
}

$_SERVER['HTTP_HOST'] = 'anonymitycheck.localhost' . $port;
$_SERVER['FEZADAN_ORIGINAL_HOST'] = $originalHost;
$_SERVER['FEZADAN_PATH_PREFIX'] = '/anonymitycheck';
$_SERVER['REQUEST_URI'] = $subPath . ($query ? '?' . $query : '');
$_SERVER['SCRIPT_NAME'] = '/index.php';
if ($subPath === '/') {
    unset($_GET['url']);
} else {
    $_GET['url'] = trim($subPath, '/');
}

require dirname(__DIR__) . '/index.php';
