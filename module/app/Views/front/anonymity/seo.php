<?php
$anonymityCanonical = ANONYMITY_SITE_URL . '/';
$isSavedAnonymityReport = !empty($cached_result);
$anonymityTitle = $isSavedAnonymityReport
    ? 'Saved Anonymity Report | FEZADAN'
    : 'Anonymity Check: IP, WebRTC & Browser Privacy Report | FEZADAN';
$anonymityDescription = 'Check your visible IP address, Tor exit evidence, WebRTC addresses and browser fingerprint signals. See what was measured, what is unknown and how to interpret it.';
$anonymityImage = ANONYMITY_SITE_URL . '/cdn/logo-dark.png';
?>
    <title><?= htmlspecialchars($anonymityTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($anonymityDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= $isSavedAnonymityReport ? 'noindex, nofollow, noarchive' : 'index, follow, max-image-preview:large' ?>">
    <link rel="canonical" href="<?= $anonymityCanonical ?>">
    <meta name="theme-color" content="#000000">
    <link rel="icon" href="/cdn/dark-favicon-32x32.png" sizes="32x32">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FEZADAN Anonymity Check">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="<?= htmlspecialchars($anonymityTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($anonymityDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= $anonymityCanonical ?>">
    <meta property="og:image" content="<?= $anonymityImage ?>">
    <meta property="og:image:width" content="1340">
    <meta property="og:image:height" content="723">
    <meta property="og:image:alt" content="FEZADAN logo">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($anonymityTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($anonymityDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= $anonymityImage ?>">
    <?php if (!$isSavedAnonymityReport): ?>
    <script type="application/ld+json" nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES, 'UTF-8') ?>"><?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'Organization', '@id' => 'https://fezadan.org/#organization', 'name' => 'FEZADAN', 'url' => 'https://fezadan.org/'],
            ['@type' => 'WebSite', '@id' => $anonymityCanonical . '#website', 'url' => $anonymityCanonical, 'name' => 'FEZADAN Anonymity Check', 'inLanguage' => 'en', 'publisher' => ['@id' => 'https://fezadan.org/#organization']],
            ['@type' => 'WebPage', '@id' => $anonymityCanonical . '#webpage', 'url' => $anonymityCanonical, 'name' => $anonymityTitle, 'description' => $anonymityDescription, 'inLanguage' => 'en', 'isPartOf' => ['@id' => $anonymityCanonical . '#website'], 'mainEntity' => ['@id' => $anonymityCanonical . '#application']],
            ['@type' => 'WebApplication', '@id' => $anonymityCanonical . '#application', 'url' => $anonymityCanonical, 'name' => 'FEZADAN Anonymity Check', 'description' => $anonymityDescription, 'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any', 'browserRequirements' => 'JavaScript enabled; individual checks depend on browser API availability.', 'isAccessibleForFree' => true, 'publisher' => ['@id' => 'https://fezadan.org/#organization'], 'featureList' => ['Visible connection address', 'Local Tor exit evidence', 'WebRTC address observations when first-party TURN is configured', 'Browser-readable fingerprint signals', 'Privacy preference signals', '24-hour shareable reports with IP addresses masked']],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
