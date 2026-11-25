<?php
/**
 * @var string $ip
 */
$nonce = $nonce ?? '';
$proxyCount = is_array($proxy_headers ?? null) ? count($proxy_headers) : 0;

$latRaw = trim((string)($latitude ?? ''));
$lonRaw = trim((string)($longitude ?? ''));
$hasMap = $latRaw !== '' && $lonRaw !== '' && is_numeric($latRaw) && is_numeric($lonRaw)
    && (float)$latRaw >= -90 && (float)$latRaw <= 90 && (float)$lonRaw >= -180 && (float)$lonRaw <= 180;
$lat = $hasMap ? (float)$latRaw : null;
$lon = $hasMap ? (float)$lonRaw : null;
$countryCodeDisplay = trim((string)($country ?? ''));
$countryNameDisplay = trim((string)($country_name ?? ''));
$countryDisplay = $countryNameDisplay !== ''
    ? $countryNameDisplay . ($countryCodeDisplay !== '' && $countryCodeDisplay !== $countryNameDisplay ? ' (' . $countryCodeDisplay . ')' : '')
    : ($countryCodeDisplay ?: 'Unavailable');
$cityDisplay = trim((string)($city ?? '')) ?: 'Unavailable';
$regionDisplay = trim((string)($region ?? ''));
$geoSourceDisplay = trim((string)($geo_source ?? '')) ?: 'Unavailable';
$geoAccuracyDisplay = trim((string)($geo_accuracy ?? ''));
$geoTimezoneDisplay = trim((string)($geo_timezone ?? ''));
$networkAsnDisplay = trim((string)($network_asn ?? ''));
$networkOrgDisplay = trim((string)($network_org ?? ''));
$networkOwnerDisplay = $networkOrgDisplay !== ''
    ? $networkOrgDisplay . ($networkAsnDisplay !== '' ? ' / AS' . ltrim($networkAsnDisplay, 'AS') : '')
    : ($networkAsnDisplay !== '' ? 'AS' . ltrim($networkAsnDisplay, 'AS') : 'Unavailable');
$coordinateDisplay = $hasMap
    ? number_format($lat, 4, '.', '') . ', ' . number_format($lon, 4, '.', '')
    : 'Coordinates unavailable';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/anonymity/seo.php'; ?>
    <link rel="preload" href="/assets/fonts/league-gothic/LeagueGothic-Condensed.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/eb-garamond-v32-latin-ext-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/jetbrains-mono-v24-latin-ext-regular.woff2" as="font" type="font/woff2" crossorigin>
    <?php require __DIR__ . '/anonymity/style.php'; ?>
</head>
<body class="<?= empty($cached_result) ? 'start-screen-active' : '' ?>">

    <a class="brand-mark" href="https://fezadan.org" aria-label="Created by Fezadan">
        <img src="/cdn/logo-dark-160.webp" alt="" width="160" height="86">
        <span class="brand-mark-text">
            <span class="brand-kicker">Created by</span>
            <span class="brand-name">Fezadan</span>
        </span>
    </a>

    <div id="progress-container">
        <div id="progress-bar"></div>
    </div>

    <main class="container">
        <noscript><p class="scan-note">JavaScript is required for browser checks. No browser measurements have been performed.</p></noscript>

        <!-- Start gate -->
        <section class="start-screen" id="start-screen">
            <h1>Anonymity Check</h1>
            <p class="subtitle"><span>What can a website learn about your connection and browser?</span></p>
            <p class="scan-note">Examine address exposure, browser information and the limits of each measurement. No anonymity score, third-party probes or permission prompts. DNS is not tested; active WebRTC testing depends on a first-party service.</p>
            <p class="scan-note">A shareable copy is saved for 24 hours with IP addresses removed. It can still reveal network, location and browser details to anyone with the link.</p>

            <button class="btn-scan" id="btn-start-scan">Run report</button>
        </section>

        <!-- PROGRESS SCREEN -->
        <section class="progress-screen-container" id="progress-screen" style="display: none;" role="status" aria-live="polite">
            <div class="aperture-stage" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; border: none; background: transparent; overflow: visible;">
                <div class="aperture-label" id="progress-log" style="position: relative; bottom: auto; left: auto; transform: none;">Ready</div>
            </div>
            <div class="progress-text" id="progress-header">Ready</div>
        </section>

        <!-- DASHBOARD RESULTS WRAPPER -->
        <div id="dashboard-wrapper" style="display: none;" data-nosnippet>
            <div class="report-actions">
                <a id="report-rerun" href="/">Run a new report</a>
                <span id="report-save-status" class="param-desc" role="status" hidden></span>
            </div>
            <!-- Cached Alert Header -->
            <div id="cached-alert">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>Saved client-generated report loaded. Results are not independently verified.</span>
            </div>

            <section class="assessment" aria-labelledby="assessment-title">
                <p class="card-title">Interpretation</p>
                <h2 id="assessment-title">Waiting for observations</h2>
                <p id="assessment-summary">A result will describe the evidence, not certify anonymity.</p>
                <div class="assessment-columns">
                    <div>
                        <h3>What to do next</h3>
                        <ol id="assessment-actions"></ol>
                    </div>
                    <div>
                        <h3>What remains unknown</h3>
                        <ul id="assessment-gaps"></ul>
                    </div>
                </div>
                <details class="report-methodology">
                    <summary>Who can observe what? Read the threat model</summary>
                    <dl>
                        <dt>A website you visit</dt><dd>Sees its connection endpoint and information available to page scripts. A VPN can change the endpoint without changing browser properties.</dd>
                        <dt>A tracker across websites</dt><dd>May combine account identifiers, storage and browser properties. This single-origin report does not test cross-site recognition, fingerprint uniqueness or long-term stability.</dd>
                        <dt>Your ISP, VPN operator or a network observer</dt><dd>Has a different view from this page. Provider logging, traffic correlation, DNS routing and traffic from other applications are not audited here.</dd>
                    </dl>
                    <p>Method: one browser session, local network databases and bounded browser API checks. A readable value is an observation, a provider-name match is a heuristic, and a missing measurement is unknown. No population comparison or anonymity probability is calculated.</p>
                    <p class="methodology-sources">Background: <a href="https://developer.mozilla.org/en-US/docs/Web/API/RTCIceCandidate/type" rel="noopener noreferrer">WebRTC candidate types (MDN)</a> · <a href="https://support.torproject.org/tor-browser/security/using-tb-safely/" rel="noopener noreferrer">Anonymity limits and browser practices (Tor Project)</a>. These references describe the method; they do not verify an individual report.</p>
                </details>
            </section>

            <!-- Dashboard main grid -->
            <div class="dashboard-grid">
                
                <!-- Left Side Columns -->
                <div class="left-col">
                    
                    <!-- Check completion is not an anonymity score. Existing IDs remain stable. -->
                    <div class="card score-card">
                        <div class="score-info">
                            <div class="card-title">Report status</div>
                            <h2 class="score-verdict" id="score-verdict-text" aria-live="polite" aria-atomic="true" tabindex="-1">Pending</h2>
                            <div class="report-count"><span class="score-number" id="score-display">0 / 0</span> tasks ended — not tests passed</div>
                            <div class="score-desc" id="score-desc-text">
                                Results appear as checks finish. Missing measurements are shown, not treated as protection.
                            </div>
                            <p class="score-desc" id="report-coverage">Measurements have not started.</p>
                        </div>
                    </div>

                    <!-- Parameters Card -->
                    <div class="card parameters-card">
                        <div class="card-header">
                            <div class="card-title">Network profile</div>
                        </div>
                        <div class="param-list">
                            <div class="param-item">
                                <div class="param-label">IP Address</div>
                                <div class="param-value"><?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="param-item">
                                <div class="param-label">ISP / ASN</div>
                                <div class="param-value"><?= htmlspecialchars($networkOwnerDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="param-item">
                                <div class="param-label">Browser</div>
                                <div class="param-value" id="endpoint-browser">Pending</div>
                            </div>
                            <div class="param-item">
                                <div class="param-label">Timezone</div>
                                <div class="param-value" id="endpoint-timezone">Pending</div>
                                <?php if ($geoTimezoneDisplay !== ''): ?>
                                    <div class="param-desc">Server-Side: <?= htmlspecialchars($geoTimezoneDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Side Column (Geo & Map) -->
                <div class="card geo-card">
                    <div class="card-header">
                        <div class="card-title">Location Estimate</div>
                    </div>
                    
                    <div class="geo-content">
                        <div class="geo-details-list">
                            <div class="geo-details-item">
                                <div class="param-label">Country</div>
                                <div class="param-value" style="font-size: 1.15rem;"><?= htmlspecialchars($countryDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="geo-details-item">
                                <div class="param-label">Accuracy</div>
                                <div class="param-desc">IP-based estimate, not physical location.</div>
                            </div>
                            <div class="geo-details-item" style="grid-column: span 2; align-items: center; text-align: center;">
                                <div class="param-label">City</div>
                                <div class="param-value"><?= htmlspecialchars($cityDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="param-desc" id="geo-source-tag" style="margin-top: 4px;<?= ($geoSourceDisplay !== 'Unavailable' && $geoSourceDisplay !== 'Cloudflare') ? '' : ' display: none;' ?>"><?= ($geoSourceDisplay !== 'Unavailable' && $geoSourceDisplay !== 'Cloudflare') ? 'Source: ' . htmlspecialchars($geoSourceDisplay, ENT_QUOTES, 'UTF-8') : '' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Local location estimate panel -->
                    <div id="map-wrapper">
                        <div id="map"></div>
                    </div>
                </div>

            </div>

            <div class="summary-strip">
                <div class="summary-item">
                    <div class="summary-label">Connection evidence</div>
                    <div class="summary-value" id="summary-network">Unknown</div>
                    <div class="param-desc" id="summary-network-note">CDN headers do not establish visitor masking.</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Browser information</div>
                    <div class="summary-value" id="summary-fingerprint">Not checked</div>
                    <div class="param-desc" id="summary-fingerprint-note">Readable information is not a uniqueness measurement.</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">WebRTC addresses</div>
                    <div class="summary-value" id="summary-priority">Not checked</div>
                    <div class="param-desc" id="summary-leak-note">DNS and untested paths remain outside the report.</div>
                </div>
            </div>

            <!-- Indicators Matrix Section -->
            <section class="matrix-section">
                <div class="card-title">Checks</div>
                <p class="section-intro">Readable means a value was accessible, not that it identifies you. Not tested, unavailable and failed are different from no finding.</p>
                <div class="matrix-grid">
                    <div class="matrix-cell">
                        <span class="matrix-name">WebRTC</span>
                        <span class="badge testing" id="m-webrtc">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Canvas</span>
                        <span class="badge testing" id="m-canvas">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">WebGL</span>
                        <span class="badge testing" id="m-webgl">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Audio</span>
                        <span class="badge testing" id="m-audio">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Timezone</span>
                        <span class="badge testing" id="m-timezone">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Proxy</span>
                        <span class="badge testing" id="m-proxy">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Tor</span>
                        <span class="badge testing" id="m-tor">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">ISP / ASN</span>
                        <span class="badge testing" id="m-isp">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">DNS Resolver</span>
                        <span class="badge testing" id="m-dns">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Permissions</span>
                        <span class="badge testing" id="m-localhost">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">WebGPU</span>
                        <span class="badge testing" id="m-webgpu">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Content filter</span>
                        <span class="badge testing" id="m-adblock">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Account isolation</span>
                        <span class="badge testing" id="m-social">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Device APIs</span>
                        <span class="badge testing" id="m-localapps">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Fonts</span>
                        <span class="badge testing" id="m-fonts">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">UA Hints</span>
                        <span class="badge testing" id="m-uach">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Screen</span>
                        <span class="badge testing" id="m-screen">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Plugins</span>
                        <span class="badge testing" id="m-plugins">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Connection</span>
                        <span class="badge testing" id="m-connection">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Input</span>
                        <span class="badge testing" id="m-input">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Media Q.</span>
                        <span class="badge testing" id="m-media">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Headers</span>
                        <span class="badge testing" id="m-headers">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Privacy</span>
                        <span class="badge testing" id="m-privacy">Pending</span>
                    </div>
                    <div class="matrix-cell">
                        <span class="matrix-name">Speech</span>
                        <span class="badge testing" id="m-speech">Pending</span>
                    </div>
                </div>
            </section>

            <!-- Detailed Findings Section -->
            <section class="findings-section" id="findings-container" style="display: none;">
                <h2 class="card-title">Evidence and explanations</h2>
                <p class="section-intro">Address observations appear first. Open the technical evidence for the raw measurement; routine capabilities are not vulnerabilities.</p>
                <div class="findings-list" id="findings-tbody">
                    <!-- Javascript populated findings cards -->
                </div>
            </section>
        </div>

    </main>
    <?php if (empty($cached_result)) require __DIR__ . '/anonymity/guide.php'; ?>

<?php require __DIR__ . '/anonymity/script.php'; ?>
</body>
</html>
