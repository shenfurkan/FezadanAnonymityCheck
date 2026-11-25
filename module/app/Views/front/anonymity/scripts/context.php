<?php
/**
 * @var mixed $cached_result
 * @var string $geoAccuracyDisplay
 * @var string $geoSourceDisplay
 * @var string $geoTimezoneDisplay
 * @var string $hostname
 * @var string $ip
 * @var mixed $is_tor
 * @var mixed $proxyCount
 * @var float|null $lat
 * @var float|null $lon
 * @var string $countryNameDisplay
 */
?>
<?php $scriptJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
    const isCachedReport = <?= $cached_result ? 'true' : 'false' ?>;
    const cachedData = <?= $cached_result ? json_encode($cached_result, $scriptJsonFlags) : 'null' ?>;

    const serverSignals = {
        isTor: <?= json_encode($is_tor ?? null, $scriptJsonFlags) ?>,
        torSource: <?= json_encode($tor_source ?? '', $scriptJsonFlags) ?>,
        trustedEdge: <?= !empty($trusted_edge) ? 'true' : 'false' ?>,
        iceServers: <?= json_encode($ice_servers ?? [], $scriptJsonFlags) ?>,
        proxyCount: <?= (int)$proxyCount ?>,
        proxyHeaders: <?= json_encode(array_keys($proxy_headers ?? []), $scriptJsonFlags) ?>,
        country: <?= json_encode($country ?? '', $scriptJsonFlags) ?>,
        city: <?= json_encode($city ?? '', $scriptJsonFlags) ?>,
        region: <?= json_encode($region ?? '', $scriptJsonFlags) ?>,
        networkAsn: <?= json_encode($network_asn ?? '', $scriptJsonFlags) ?>,
        networkOrg: <?= json_encode($network_org ?? '', $scriptJsonFlags) ?>,
        latitude: <?= json_encode($lat, $scriptJsonFlags) ?>,
        longitude: <?= json_encode($lon, $scriptJsonFlags) ?>,
        ip: <?= json_encode($ip, $scriptJsonFlags) ?>,
        hostname: <?= json_encode($hostname, $scriptJsonFlags) ?>,
        countryName: <?= json_encode($countryNameDisplay, $scriptJsonFlags) ?>,
        geoSource: <?= json_encode($geoSourceDisplay, $scriptJsonFlags) ?>,
        geoAccuracy: <?= json_encode($geoAccuracyDisplay, $scriptJsonFlags) ?>,
        geoTimezone: <?= json_encode($geoTimezoneDisplay, $scriptJsonFlags) ?>,
        headers: <?= json_encode($headers ?? [], $scriptJsonFlags) ?>,
        serverProtocol: <?= json_encode($server_protocol ?? 'HTTP/1.1', $scriptJsonFlags) ?>
    };

    const findings = [];
    const privacyState = { network: 'unknown', webrtc: 'not_checked', rtcFamilies: [] };
    const scanState = { running: false, cancelled: false, finished: 0, total: 0, checks: [], durationMs: 0 };
    let rtcGatheringResult = null;

    const els = {
        progressBar: document.getElementById('progress-bar'),
        progressScreen: document.getElementById('progress-screen'),
        progressHeader: document.getElementById('progress-header'),
        progressLog: document.getElementById('progress-log'),
        scoreDisplay: document.getElementById('score-display'),
        scoreVerdictText: document.getElementById('score-verdict-text'),
        scoreDescText: document.getElementById('score-desc-text'),
        saveStatus: document.getElementById('report-save-status'),
        rerunLink: document.getElementById('report-rerun'),
        findingsTbody: document.getElementById('findings-tbody'),
        findingsContainer: document.getElementById('findings-container'),
        endpointTimezone: document.getElementById('endpoint-timezone'),
        endpointBrowser: document.getElementById('endpoint-browser'),
        cachedAlert: document.getElementById('cached-alert'),
        startScreen: document.getElementById('start-screen'),
        dashboardWrapper: document.getElementById('dashboard-wrapper'),
        btnStartScan: document.getElementById('btn-start-scan'),
        summaryPriority: document.getElementById('summary-priority'),
        summaryFingerprint: document.getElementById('summary-fingerprint'),
        summaryNetwork: document.getElementById('summary-network'),
        summaryNetworkNote: document.getElementById('summary-network-note'),
        summaryFingerprintNote: document.getElementById('summary-fingerprint-note'),
        summaryLeakNote: document.getElementById('summary-leak-note'),
        assessmentTitle: document.getElementById('assessment-title'),
        assessmentSummary: document.getElementById('assessment-summary'),
        assessmentActions: document.getElementById('assessment-actions'),
        assessmentGaps: document.getElementById('assessment-gaps'),
        reportCoverage: document.getElementById('report-coverage'),
        mWebRtc: document.getElementById('m-webrtc'),
        mCanvas: document.getElementById('m-canvas'),
        mWebGL: document.getElementById('m-webgl'),
        mAudio: document.getElementById('m-audio'),
        mTimezone: document.getElementById('m-timezone'),
        mProxy: document.getElementById('m-proxy'),
        mTor: document.getElementById('m-tor'),
        mIsp: document.getElementById('m-isp'),
        mDns: document.getElementById('m-dns'),
        mLocalhost: document.getElementById('m-localhost'),
        mWebGpu: document.getElementById('m-webgpu'),
        mAdBlock: document.getElementById('m-adblock'),
        mSocial: document.getElementById('m-social'),
        mLocalApps: document.getElementById('m-localapps'),
        mFonts: document.getElementById('m-fonts'),
        mUach: document.getElementById('m-uach'),
        mScreen: document.getElementById('m-screen'),
        mPlugins: document.getElementById('m-plugins'),
        mConnection: document.getElementById('m-connection'),
        mInput: document.getElementById('m-input'),
        mMedia: document.getElementById('m-media'),
        mHeaders: document.getElementById('m-headers'),
        mPrivacy: document.getElementById('m-privacy'),
        mSpeech: document.getElementById('m-speech')
    };
