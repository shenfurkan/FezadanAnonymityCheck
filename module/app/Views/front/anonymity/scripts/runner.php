<?php // Bounded, dependency-aware checks and versioned saved-report rendering. ?>
    function loadCachedReport(data) {
        els.cachedAlert.style.display = 'flex';

        els.endpointBrowser.textContent = data.browser || "Unknown";
        els.endpointTimezone.textContent = data.timezone || "Unknown";

        findings.length = 0;
        (Array.isArray(data.findings) ? data.findings : []).slice(0, 50).filter(f => f && typeof f === 'object').forEach(f => findings.push(f));
        const currentModel = data.risk_model === 3 && data.vector && typeof data.vector === 'object';
        if (currentModel) {
            privacyState.network = ['masked', 'anonymous', 'unknown'].includes(data.vector.network) ? data.vector.network : 'unknown';
            privacyState.webrtc = ['not_checked', 'host_only', 'complete', 'disabled'].includes(data.vector.webrtc) ? data.vector.webrtc : 'not_checked';
            privacyState.rtcFamilies = Array.isArray(data.vector.rtc_families)
                ? [4, 6].filter(family => data.vector.rtc_families.includes(family)) : [];
        }
        scanState.checks = Array.isArray(data.checks) ? data.checks.slice(0, 50) : [];
        scanState.total = scanState.finished = scanState.checks.length;
        flushReport();

        const matrix = data.matrix && typeof data.matrix === 'object' ? data.matrix : {};
        Object.keys(getMatrixStates()).forEach(key => {
            updateMatrixCell(els['m' + key], matrix[key] || 'NOT_TESTED');
        });

        if (!currentModel) {
            els.scoreVerdictText.textContent = 'Legacy report — rerun';
            els.scoreDisplay.textContent = '—';
            els.scoreDescText.textContent = 'This saved report uses an older assessment model. Its score and classifications are not used by the current report; run a new check.';
            [els.summaryNetwork, els.summaryFingerprint, els.summaryPriority].forEach(el => { el.textContent = 'Legacy — rerun'; });
            els.assessmentTitle.textContent = 'Run a new report';
            els.assessmentSummary.textContent = 'This older report cannot support the current interpretation. Its original findings are retained below, not treated as a current assessment.';
            els.assessmentActions.replaceChildren();
            els.assessmentGaps.replaceChildren();
        }

        const lat = data.latitude !== undefined ? data.latitude : serverSignals.latitude;
        const lon = data.longitude !== undefined ? data.longitude : serverSignals.longitude;
        initMap(lat, lon);
    }

    function getMatrixStates() {
        const matrixKeys = [
            'WebRtc', 'Canvas', 'WebGL', 'Audio', 'Timezone',
            'Proxy', 'Tor', 'Isp', 'Dns', 'Localhost', 'WebGpu',
            'AdBlock', 'Social', 'LocalApps', 'Fonts',
            'Uach', 'Screen', 'Plugins', 'Connection', 'Input',
            'Media', 'Headers', 'Privacy', 'Speech'
        ];
        const state = {};
        matrixKeys.forEach(k => {
            const el = els['m' + k];
            if (el) {
                state[k] = el.dataset.status || 'NOT_TESTED';
            }
        });
        return state;
    }

    async function runScan() {
        if (scanState.running) return;
        scanState.running = true;
        els.startScreen.style.display = 'none';
        els.dashboardWrapper.style.display = 'block';
        els.dashboardWrapper.setAttribute('aria-busy', 'true');
        els.progressBar.style.display = 'none';
        closeAperture();
        document.body.classList.remove('start-screen-active');
        els.endpointBrowser.textContent = getBrowserDetails();
        els.endpointTimezone.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;
        initMap(serverSignals.latitude, serverSignals.longitude);

        const steps = [
            { name: 'Network', fn: checkServerNetwork, timeout: 3000, matrixEls: [els.mProxy, els.mTor] },
            { name: 'Location', fn: checkGeoLocation, timeout: 4000 },
            { name: 'Provider', fn: checkNetworkOwner, after: ['Network', 'Location'], matrixEls: [els.mIsp] },
            { name: 'WebRTC', fn: signal => checkWebRtcStunLeak(serverSignals.ip, signal), timeout: 4500, matrixEls: [els.mWebRtc] },
            { name: 'DNS', fn: checkDnsResolver, matrixEls: [els.mDns] },
            // Bound and serialize the heavier sampling tasks, not the unrelated network checks.
            { name: 'Canvas', fn: checkCanvas, matrixEls: [els.mCanvas] },
            { name: 'WebGL', fn: checkWebGL, after: ['Canvas'], matrixEls: [els.mWebGL] },
            { name: 'Audio', fn: checkAudio, after: ['WebGL'], matrixEls: [els.mAudio] },
            { name: 'Locale/Time', fn: checkBrowserLocaleAndTime, after: ['Location'], matrixEls: [els.mTimezone] },
            { name: 'UA Hints', fn: checkUaClientHints, matrixEls: [els.mUach] },
            { name: 'Automation', fn: checkAutomation },
            { name: 'Screen', fn: checkScreenFingerprint, matrixEls: [els.mScreen] },
            { name: 'Media Queries', fn: checkMediaQueries, matrixEls: [els.mMedia] },
            { name: 'Plugins', fn: checkPluginsAndMime, matrixEls: [els.mPlugins] },
            { name: 'Speech', fn: checkSpeechVoices, matrixEls: [els.mSpeech] },
            { name: 'Headers', fn: checkHeaderAnalysis, after: ['Location'], matrixEls: [els.mHeaders] },
            { name: 'Device APIs', fn: checkDeviceAPIs, matrixEls: [els.mWebGpu] },
            { name: 'Connection', fn: checkConnectionFingerprint, matrixEls: [els.mConnection] },
            { name: 'Permissions', fn: checkLocalhostAndExtensions, matrixEls: [els.mLocalhost] },
            { name: 'Privacy', fn: checkPrivacySignals, matrixEls: [els.mPrivacy] },
            { name: 'Touch', fn: checkTouchAndInput, matrixEls: [els.mInput] },
            { name: 'Math', fn: checkMathPrecision },
            { name: 'Content Filter', fn: checkAdBlocker, matrixEls: [els.mAdBlock] },
            { name: 'Tracker Data', fn: checkTrackers },
            { name: 'Device Capabilities', fn: checkLocalApps, matrixEls: [els.mLocalApps] },
            { name: 'Account Scope', fn: () => Promise.all([checkFonts(), checkSocialSessions(), checkTheoreticalRisks()]), matrixEls: [els.mFonts, els.mSocial] }
        ];

        scanState.total = steps.length;
        updateReportSummary();
        els.scoreVerdictText.focus({ preventScroll: true });
        const startedAt = performance.now();
        const deadline = new AbortController();
        const deadlineTimer = setTimeout(() => deadline.abort(new DOMException('Report time limit reached', 'TimeoutError')), 10000);
        const onPageHide = () => {
            scanState.cancelled = true;
            deadline.abort(new DOMException('Report stopped when leaving the page', 'AbortError'));
        };
        window.addEventListener('pagehide', onPageHide, { once: true });
        async function runCheck(step) {
            const controller = new AbortController();
            const onDeadline = () => controller.abort(deadline.signal.reason);
            if (deadline.signal.aborted) onDeadline();
            else deadline.signal.addEventListener('abort', onDeadline, { once: true });
            const timer = setTimeout(() => controller.abort(new DOMException(step.name + ' timed out', 'TimeoutError')), step.timeout || 2000);
            const checkStarted = performance.now();
            let status = 'FINISHED';
            try {
                // Give each check its own task instead of running all synchronous work
                // in one microtask chain attached to the Run report click.
                await waitForCheck(new Promise(resolve => setTimeout(resolve, 0)), controller.signal);
                await waitForCheck(Promise.resolve().then(() => {
                    controller.signal.throwIfAborted();
                    return step.fn(controller.signal);
                }), controller.signal);
            } catch (error) {
                const reason = controller.signal.aborted ? controller.signal.reason : error;
                status = reason && reason.name === 'TimeoutError' ? 'TIMEOUT' : (scanState.cancelled ? 'CANCELLED' : 'ERROR');
                (step.matrixEls || []).forEach(el => {
                    // Do not erase observations a multi-part check completed before a timeout.
                    if (!el.dataset.status || el.dataset.status === 'PENDING') updateMatrixCell(el, status);
                });
                applyFinding({
                    module: step.name + ' Check',
                    category: 'Other Findings',
                    status,
                    severity: 'low',
                    evidence: 'Check failed: ' + (error && error.message ? error.message : 'unknown error'),
                    impactText: 'This check did not complete. Failure is not proof of protection; the other checks continued.',
                    mitigation: 'Run a new report if needed. Review browser restrictions or server data if this repeats.'
                });
            } finally {
                clearTimeout(timer);
                deadline.signal.removeEventListener('abort', onDeadline);
                scanState.checks.push({ name: step.name, status, duration_ms: Math.round(performance.now() - checkStarted) });
                scanState.finished++;
                if (renderTimer === null) renderTimer = setTimeout(flushReport, 0);
            }
        }

        // Paint the real report UI before starting browser/GPU sampling. This is a
        // rendering yield, not staged progress; the existing deadline still applies.
        try {
            await waitForCheck(new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))), deadline.signal);
        } catch (_) { /* Aborted/deadline-expired checks below keep their honest status. */ }
        const pending = new Map();
        for (const step of steps) {
            pending.set(step.name, Promise.all((step.after || []).map(name => pending.get(name))).then(() => runCheck(step)));
        }
        try {
            await Promise.all(pending.values());
        } finally {
            clearTimeout(deadlineTimer);
            window.removeEventListener('pagehide', onPageHide);
            scanState.running = false;
            scanState.durationMs = Math.round(performance.now() - startedAt);
            els.dashboardWrapper.setAttribute('aria-busy', 'false');
            flushReport();
        }
        initMap(serverSignals.latitude, serverSignals.longitude);

        if (scanState.cancelled) {
            els.saveStatus.textContent = 'The report was stopped before completion and was not saved. Run a new report to finish the checks.';
            els.saveStatus.hidden = false;
            return;
        }

        const report = {
            hostname: serverSignals.hostname,
            country: serverSignals.country,
            country_name: serverSignals.countryName,
            network_asn: serverSignals.networkAsn,
            network_org: serverSignals.networkOrg,
            city: serverSignals.city,
            region: serverSignals.region,
            geo_timezone: serverSignals.geoTimezone,
            geo_source: serverSignals.geoSource,
            geo_accuracy: serverSignals.geoAccuracy,
            latitude: serverSignals.latitude,
            longitude: serverSignals.longitude,
            is_tor: serverSignals.isTor,
            tor_source: serverSignals.torSource,
            proxy_headers: serverSignals.proxyHeaders,
            server_protocol: serverSignals.serverProtocol,
            risk_model: 3,
            vector: getPrivacyVector(),
            findings: findings,
            matrix: getMatrixStates(),
            checks: scanState.checks,
            duration_ms: scanState.durationMs,
            browser: getBrowserDetails(),
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };

        try {
            const res = await fetchJsonWithTimeout(anonymityEndpoint('/save'), 3500, undefined, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8'
                },
                body: JSON.stringify(report)
            });
            if (res && res.ok === true && typeof res.id === 'string' && /^[a-f0-9]{32}$/.test(res.id)) {
                const targetUrl = anonymityEndpoint('/results' + res.id);
                history.pushState({ id: res.id }, '', targetUrl);
                const robots = document.querySelector('meta[name="robots"]');
                if (robots) robots.setAttribute('content', 'noindex, nofollow, noarchive');
                els.saveStatus.textContent = 'Report saved for 24 hours. You can share this address; raw IP addresses are not stored.';
            } else {
                throw new Error('Invalid save response');
            }
        } catch (e) {
            els.saveStatus.textContent = 'The report is available here, but a shareable copy could not be saved. Run a new report to try again.';
        }
        els.saveStatus.hidden = false;
    }

    function showSavedReport() {
        els.startScreen.style.display = 'none';
        closeAperture();
        els.dashboardWrapper.style.display = 'block';
        els.progressBar.style.display = 'none';

        loadCachedReport(cachedData);
    }

    window.addEventListener('DOMContentLoaded', () => {
        els.rerunLink.href = anonymityEndpoint('/');
        if (isCachedReport && cachedData) {
            showSavedReport();
        } else {
            els.startScreen.style.display = 'block';
            els.dashboardWrapper.style.display = 'none';
            document.body.classList.add('start-screen-active');

            els.btnStartScan.addEventListener('click', () => {
                els.btnStartScan.disabled = true;
                els.startScreen.classList.add('is-leaving');
                document.body.classList.remove('start-screen-active');
                runScan();
            });
        }
    });
