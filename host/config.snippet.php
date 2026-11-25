<?php
/**
 * app/Config/config.php — anonymity-check origin configuration.
 *
 * Apply after scripts/install.sh. Two edits, both in app/Config/config.php.
 * Keep the origin in ANONYMITY_SITE_URL; the module and sitemap read it.
 */

// 1. Exclude the dedicated subdomain when deriving the main host.
//    Existing line; add "anonymitycheck" to the alternation if absent.
$mainHost = preg_replace('/^(?:www|notlar|anonymitycheck|furkan)\./i', '', $detectedHost);

// 2. Canonical origin and the footer helper.
//    Change the origin here for a different deployment. The same origin is also
//    hard-coded as the redirect fallback in public_html/anonymitycheck/index.php.
define('ANONYMITY_SITE_URL', 'https://anonymitycheck.fezadan.org');
function anonymityUrl(): string {
    return HttpRequest::isLocalHost() ? '/anonymitycheck/' : ANONYMITY_SITE_URL . '/';
}

/*
 * Optional WebRTC TURN configuration (used by AnonymityIndexTrait::anonymityIceServers()):
 *   ANONYMITY_TURN_URL    turns:turn.example.org:5349?transport=tcp
 *   ANONYMITY_TURN_SECRET shared coturn "use-auth-secret" value
 * Put both in .env, never in code. Without them the page reports active WebRTC
 * testing as unavailable instead of inventing a result.
 */
