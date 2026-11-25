<?php
/**
 * public_html/index.php — CSP and frame isolation for the anonymity host.
 *
 * This block runs before app/Config/config.php and App are loaded. $_host is
 * already lowercased above. The stricter policy matters: the tool must not load
 * third-party images or fonts, may not be framed, and must not be upgraded in a
 * way that breaks local HTTP checks.
 */

$_host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$_isLocal = HttpRequest::isLocalHost();
$_isHttps = HttpRequest::isHttps();
$_isAnonymityCheckHost = strpos($_host, 'anonymitycheck.') === 0;

header('X-Frame-Options: ' . ($_isAnonymityCheckHost ? 'DENY' : 'SAMEORIGIN'));
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=(), browsing-topics=()');
if ($_isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
$__csp_upgrade = ($_isLocal || $_isAnonymityCheckHost) ? '' : ' upgrade-insecure-requests;';
$__csp_connect = "'self'";
if (WEB_ANALYTICS_ENABLED) {
    // The manually embedded, nonced beacon reports only to this Cloudflare endpoint.
    $__csp_connect .= ' https://cloudflareinsights.com/cdn-cgi/rum';
}
$__csp_frame_ancestors = $_isAnonymityCheckHost ? "'none'" : "'self'";
$__csp_script = "'self' 'nonce-{$__csp_nonce}'";
$__csp_style = "'self' 'unsafe-inline'";
$__csp_img = $_isAnonymityCheckHost ? "'self' data:" : "'self' data: https:";
$__csp_font = "'self'";
$__csp_frame = $_isAnonymityCheckHost
    ? "'none'"
    : "'self' http://localhost:8089 http://127.0.0.1:8089 https://litecaptcha.fezadan.org https://www.openstreetmap.org";
header("Content-Security-Policy: default-src 'self'; script-src {$__csp_script}; style-src {$__csp_style}; img-src {$__csp_img}; font-src {$__csp_font}; connect-src {$__csp_connect}; media-src 'self'; frame-src {$__csp_frame}; object-src 'none'; frame-ancestors {$__csp_frame_ancestors}; base-uri 'self'; form-action 'self';{$__csp_upgrade}");

/*
 * Required order in public_html/index.php:
 *   1. HttpRequest::normalizeProxy() and the WebAnalytics definition
 *   2. $__csp_nonce = bin2hex(random_bytes(16)); define('CSP_NONCE', ...)
 *   3. this block
 *   4. require app/Config/config.php and the rest of the bootstrap
 * CSP_NONCE is used by the module's inline script and its structured data.
 */
