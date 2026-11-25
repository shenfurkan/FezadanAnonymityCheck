<?php // Shared utility, bounded waits, rendering, and evidence summaries. ?>
    function waitForCheck(promise, signal) {
        return new Promise((resolve, reject) => {
            const onAbort = () => settle(reject, signal.reason);
            const settle = (callback, value) => {
                if (signal) signal.removeEventListener('abort', onAbort);
                callback(value);
            };
            // Attach both handlers even if already aborted: late rejections must be consumed.
            Promise.resolve(promise).then(value => settle(resolve, value), error => settle(reject, error));
            if (signal) {
                if (signal.aborted) onAbort();
                else signal.addEventListener('abort', onAbort, { once: true });
            }
        });
    }

    async function sha256(value, signal) {
        const buffer = new TextEncoder().encode(value);
        const hash = await waitForCheck(crypto.subtle.digest('SHA-256', buffer), signal);
        return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function shortHash(hash) {
        return hash ? hash.slice(0, 16) + '...' : 'unavailable';
    }

    function anonymityEndpoint(path) {
        const prefix = window.location.pathname.indexOf('/anonymitycheck') === 0 ? '/anonymitycheck' : '';
        return prefix + path;
    }

    async function fetchJsonWithTimeout(url, timeoutMs, signal, options = {}) {
        const controller = new AbortController();
        const onAbort = () => controller.abort(signal.reason);
        if (signal) {
            if (signal.aborted) onAbort();
            else signal.addEventListener('abort', onAbort, { once: true });
        }
        const timeoutId = setTimeout(() => controller.abort(new DOMException('Request timed out', 'TimeoutError')), timeoutMs);
        try {
            const response = await fetch(url, {
                ...options,
                cache: 'no-store',
                credentials: 'omit',
                signal: controller.signal
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return await response.json();
        } catch (error) {
            if (controller.signal.aborted) throw controller.signal.reason;
            throw error;
        } finally {
            clearTimeout(timeoutId);
            if (signal) signal.removeEventListener('abort', onAbort);
        }
    }

    let geoLookupPromise = null;
    async function getGeoLookup(signal) {
        // Share failure as well as success. Retrying implicitly used the rate limit twice.
        if (!geoLookupPromise) geoLookupPromise = fetchJsonWithTimeout(anonymityEndpoint('/geo-lookup'), 3500, signal);
        return geoLookupPromise;
    }

    function getBrowserDetails() {
        const ua = navigator.userAgent;
        let browserName = "Unknown Browser";
        let osName = "Unknown OS";

        if (/iPhone|iPad|iPod/.test(ua)) osName = "iOS";
        else if (ua.indexOf("Android") !== -1) osName = "Android";
        else if (ua.indexOf("Win") !== -1) osName = "Windows";
        else if (ua.indexOf("Mac") !== -1) osName = "macOS";
        else if (ua.indexOf("Linux") !== -1) osName = "Linux";

        if (/Edg(?:e|A|iOS)?\//.test(ua)) browserName = "Edge";
        else if (/OPR\/|Opera/.test(ua)) browserName = "Opera";
        else if (/Firefox|FxiOS/.test(ua)) browserName = "Firefox";
        else if (/Chrome|CriOS/.test(ua)) browserName = "Chrome";
        else if (ua.indexOf("Safari") !== -1) browserName = "Safari";

        return `${browserName} / ${osName}`;
    }

    function closeAperture() {
        if (!els.progressScreen) return;
        document.body.classList.remove('progress-active');
        els.progressScreen.classList.remove('is-opening', 'is-active');
        els.progressScreen.style.display = 'none';
    }

    function updateMatrixCell(el, status) {
        if (!el) return;
        el.dataset.status = String(status || '').toUpperCase();
        el.className = 'badge ' + statusClass(status);
        el.textContent = statusLabel(status);
    }

    function applyFinding(finding) {
        findings.push(finding);
        if (renderTimer === null) renderTimer = setTimeout(flushReport, 0);
    }

    let renderTimer = null;
    function flushReport() {
        clearTimeout(renderTimer);
        renderTimer = null;
        renderFindings();
        updateReportSummary();
    }

    function statusClass(status) {
        const classes = {
            NO_SIGNAL: 'neutral', READABLE: 'neutral', OBSERVED: 'warning', MASKED: 'warning',
            EXPOSED: 'exposed', REVIEW: 'warning', WARNING: 'warning',
            BLOCKED: 'neutral', PROTECTED: 'neutral', SAFE: 'neutral',
            TIMEOUT: 'warning', ERROR: 'warning', CANCELLED: 'unsupported', NO_DATA: 'unsupported',
            NOT_TESTED: 'unsupported', UNSUPPORTED: 'unsupported', PENDING: 'testing'
        };
        return classes[String(status || '').toUpperCase()] || 'unsupported';
    }

    function statusLabel(status) {
        const labels = {
            NO_SIGNAL: 'No finding',
            READABLE: 'Readable',
            OBSERVED: 'Observed',
            SAFE: 'Legacy result',
            BLOCKED: 'Restricted',
            PROTECTED: 'Filtered',
            MASKED: 'Indicator',
            EXPOSED: 'Visible',
            WARNING: 'Review',
            REVIEW: 'Review',
            UNSUPPORTED: 'Unavailable',
            NO_DATA: 'No data',
            NOT_TESTED: 'Not tested',
            TIMEOUT: 'Timed out',
            ERROR: 'Failed',
            CANCELLED: 'Stopped',
            PENDING: 'Pending'
        };
        const key = String(status || '').toUpperCase();
        return labels[key] || key.replace(/_/g, ' ');
    }

    function severityRank(severity) {
        return { critical: 0, high: 1, medium: 2, low: 3 }[String(severity || '').toLowerCase()] ?? 4;
    }

    function evidenceKind(finding) {
        if (['NOT_TESTED', 'UNSUPPORTED', 'NO_DATA', 'CANCELLED'].includes(finding.status)) return 'Not measured';
        if (['TIMEOUT', 'ERROR'].includes(finding.status)) return 'Incomplete measurement';
        if (finding.status === 'BLOCKED') return 'Observed restriction';
        if (['Installed Fonts', 'Content Filter', 'Automation Indicators', 'Proxy Headers'].includes(finding.module)) return 'Heuristic';
        return 'Direct observation';
    }

    function renderFindings() {
        if (!els.findingsTbody) return;
        const openEvidence = new Set([...els.findingsTbody.querySelectorAll('details[open]')].map(el => el.dataset.finding));
        const focusedEvidence = document.activeElement?.closest('details[data-finding]')?.dataset.finding;
        const fragment = document.createDocumentFragment();

        const grouped = findings.reduce((acc, finding) => {
            const knownCategories = ['Network & Infrastructure', 'Browser Fingerprinting', 'Browser Permissions', 'Security & Anti-Tracking'];
            const cat = knownCategories.includes(finding.category) ? finding.category : 'Other Findings';
            if (!acc[cat]) acc[cat] = [];
            acc[cat].push(finding);
            return acc;
        }, Object.create(null));

        const order = ['Network & Infrastructure', 'Browser Fingerprinting', 'Browser Permissions', 'Security & Anti-Tracking', 'Other Findings'];

        order.forEach(cat => {
            if (!grouped[cat] || !grouped[cat].length) return;

            const header = document.createElement('div');
            header.className = 'findings-category-header';
            header.textContent = cat;
            fragment.appendChild(header);

            grouped[cat]
                .sort((a, b) => severityRank(a.severity) - severityRank(b.severity))
                .forEach(finding => {
                    const card = document.createElement('div');
                    const severity = ['critical', 'high', 'medium', 'low'].includes(finding.severity) ? finding.severity : 'low';
                    card.className = 'finding-card severity-' + severity;
                    card.innerHTML = `
                        <div class="finding-meta">
                            <span class="finding-name">${escapeHtml(finding.module)}</span>
                            <span class="badge ${statusClass(finding.status)}">${escapeHtml(statusLabel(finding.status))}</span>
                        </div>
                        <div class="finding-impact-block">
                            <div class="finding-title-small">Interpretation</div>
                            <div class="finding-text">${escapeHtml(finding.impactText || 'No interpretation was recorded.')}</div>
                            <details class="technical-evidence" data-finding="${findings.indexOf(finding)}">
                                <summary>Technical evidence · ${escapeHtml(evidenceKind(finding))}</summary>
                                <div class="finding-evidence">${escapeHtml(finding.evidence || 'No evidence was recorded.')}</div>
                            </details>
                        </div>
                        <div class="finding-mitigation-block">
                            <div class="finding-title-small">Suggested next step</div>
                            <div class="finding-mitigation-text">${escapeHtml(finding.mitigation || 'No recommendation was recorded.')}</div>
                        </div>
                    `;
                    fragment.appendChild(card);
                });
        });

        els.findingsTbody.replaceChildren(fragment);
        els.findingsTbody.querySelectorAll('details').forEach(el => {
            el.open = openEvidence.has(el.dataset.finding);
            if (focusedEvidence === el.dataset.finding) el.querySelector('summary').focus({ preventScroll: true });
        });
        els.findingsContainer.style.display = findings.length ? 'block' : 'none';
    }

    function hasTrustedEdgeHeaders() {
        return serverSignals.trustedEdge === true;
    }

    function getPrivacyVector() {
        // Observations, not an anonymity score or a population-uniqueness estimate.
        const surface = findings.filter(item => item.category === 'Browser Fingerprinting' && item.dimension !== 'leak');
        const readable = surface.filter(item => item.status === 'READABLE').length;
        const publicAddress = findings.some(item => item.dimension === 'leak' && item.address_scope === 'public');
        const localAddress = findings.some(item => item.dimension === 'leak' && item.address_scope === 'local');
        const checked = privacyState.webrtc === 'complete';
        return {
            network: privacyState.network,
            fingerprint: surface.length ? readable + ' readable checks' : 'Not checked',
            readable_count: readable,
            leak: publicAddress ? 'Additional public address' : (localAddress ? 'Local address visible'
                : (privacyState.webrtc === 'disabled' ? 'WebRTC unavailable' : (checked ? 'No additional address observed' : 'Not fully checked'))),
            webrtc: privacyState.webrtc,
            rtc_families: privacyState.rtcFamilies,
            public_address_observed: publicAddress,
            local_address_observed: localAddress
        };
    }

    function renderAssessment(vector) {
        const actions = [];
        const gaps = [];
        let title;
        let summary;
        if (scanState.running) {
            title = 'Observations are still arriving';
            summary = 'Do not draw a conclusion yet. Independent checks continue even when another measurement is unavailable.';
        } else if (scanState.cancelled) {
            title = 'This assessment is incomplete';
            summary = 'The report stopped. Only observations already obtained are shown; unfinished tests cannot rule out exposure.';
        } else if (vector.public_address_observed) {
            title = 'Review an additional public address';
            summary = 'WebRTC made a public address available to this page that differs from the page connection. This gives a website another address to associate with the session. It is not, by itself, proof of your home IP or a VPN bypass: IPv4/IPv6 and multiple interfaces can explain the difference.';
        } else if (vector.local_address_observed) {
            title = 'Local network addressing is visible';
            summary = 'WebRTC exposed a private or link-local address. This reveals part of the local network layout, not a public ISP identity. No additional public address was observed. '
                + (vector.webrtc === 'complete' ? 'The active result covers only the sampled routes and address families.' : 'Missing active measurements still limit that result.');
        } else if (vector.webrtc !== 'complete') {
            title = 'Address testing is incomplete';
            summary = vector.webrtc === 'host_only'
                ? 'The browser completed a host-candidate check without exposing an additional address. Active public-address comparison was not completed. This is a limited observation, not a leak-free or anonymous verdict.'
                : 'No additional address has been observed, but the browser did not supply a complete active comparison. An unavailable or failed test cannot show that a VPN or browser is protecting every route.';
        } else {
            title = 'No additional address in the sampled route';
            summary = 'The public server-reflexive addresses gathered in this session matched the page connection. This supports a narrow result for those candidates, not every interface, address family, application or future connection. It does not establish anonymity.';
        }

        if (vector.public_address_observed) {
            actions.push('Compare the additional address in the WebRTC evidence with your expected VPN or Tor exit. If it is unexpected, check IPv4/IPv6 tunnel coverage and WebRTC IP-handling settings, then repeat the report.');
        } else if (vector.local_address_observed) {
            actions.push('If local addressing should be hidden, review the browser’s WebRTC IP-handling policy and mDNS masking. Retest after a change; disabling WebRTC may break calls.');
        }
        if (vector.network === 'anonymous') {
            actions.push('A Tor exit was observed for this page connection. Keep activity intended for Tor inside Tor Browser; this result does not cover other applications or prove resistance to traffic correlation.');
        } else if (vector.network === 'masked') {
            actions.push('The visible network name resembles a VPN provider. Confirm the exit address with the provider’s own connection information; a name match alone does not verify the tunnel.');
        } else {
            actions.push('Using a VPN or Tor? Compare the visible IP and network owner below with your expected exit. An ISP name alone cannot confirm or rule out a tunnel.');
        }
        if (vector.webrtc !== 'complete') {
            gaps.push(vector.webrtc === 'host_only'
                ? 'Active WebRTC address comparison was not completed. Only passive host candidates were gathered; external NAT mappings remain unmeasured.'
                : (vector.webrtc === 'disabled'
                    ? 'The WebRTC API is unavailable in this browser. No active address comparison was possible; the reason for unavailability is not established.'
                    : 'Active WebRTC comparison is incomplete. Failure, timeout or relay-only candidates cannot rule out address exposure.'));
        } else {
            const missing = [4, 6].filter(family => !vector.rtc_families.includes(family));
            if (missing.length) gaps.push(missing.map(family => 'IPv' + family).join(' and ') + ' has no completed public server-reflexive sample. An absent candidate does not prove that this address family is disabled or protected.');
        }
        gaps.push('DNS resolver routing is not tested. This page cannot tell whether DNS follows your tunnel or identify a DNS leak.');
        const torFinding = findings.find(finding => finding.module === 'Tor Infrastructure');
        if (!torFinding || !['OBSERVED', 'NO_SIGNAL'].includes(torFinding.status)) {
            gaps.push('Tor exit status is not established by a completed lookup. Missing or stale exit data is not a negative result.');
        }
        if (findings.some(finding => finding.module === 'Local Network Data' && finding.status === 'NO_DATA')) {
            gaps.push('Local network data was unavailable. Missing ISP or location details are a data limitation, not evidence that the browser concealed them.');
        }
        const failures = findings.filter(finding => ['ERROR', 'TIMEOUT', 'CANCELLED'].includes(finding.status));
        if (failures.length) gaps.push('Incomplete measurements: ' + [...new Set(failures.map(finding => finding.module))].join(', ') + '. See their evidence below; no protection is inferred.');
        if (vector.readable_count) {
            actions.push('For cross-site tracking concerns, use built-in anti-fingerprinting settings and separate profiles for different identities. Avoid arbitrary spoofing: it can make a profile more distinctive.');
        }
        actions.push('Treat shared report links as public: location, network and browser details remain even after IP addresses are removed.');
        gaps.push('Fingerprint uniqueness, repeat-visit recognition, account linkage, provider logging and traffic outside this browser are not measured.');

        els.assessmentTitle.textContent = title;
        els.assessmentSummary.textContent = summary;
        for (const [el, items] of [[els.assessmentActions, actions], [els.assessmentGaps, gaps]]) {
            el.replaceChildren(...items.map(text => {
                const item = document.createElement('li');
                item.textContent = text;
                return item;
            }));
        }
        const measured = findings.filter(finding => ['READABLE', 'NO_SIGNAL', 'OBSERVED', 'MASKED', 'EXPOSED', 'REVIEW', 'BLOCKED'].includes(finding.status)).length;
        const unmeasured = findings.length - measured - failures.length;
        els.reportCoverage.textContent = findings.length + ' recorded observations: ' + measured + ' measured; '
            + unmeasured + ' not tested or unavailable; ' + failures.length + ' failed, timed out or stopped. These counts are not a privacy rating.';
    }

    function updateReportSummary() {
        const vector = getPrivacyVector();
        const networkLabels = { masked: 'VPN / proxy indicator', anonymous: 'Tor exit observed', unknown: 'Not established' };
        const networkNotes = {
            masked: 'A provider-name hint was observed. It does not verify VPN use or tunnel coverage. Routing headers alone are not visitor-masking evidence.',
            anonymous: 'The HTTP endpoint is identified as a Tor exit. Browser leaks still need separate checks.',
            unknown: 'Available evidence cannot establish whether the access-provider IP is masked. Compare the visible endpoint with the exit you intended to use.'
        };
        if (els.summaryPriority) els.summaryPriority.textContent = vector.leak;
        if (els.summaryFingerprint) els.summaryFingerprint.textContent = vector.fingerprint;
        if (els.summaryNetwork) els.summaryNetwork.textContent = networkLabels[vector.network];
        if (els.summaryNetworkNote) els.summaryNetworkNote.textContent = networkNotes[vector.network];
        if (els.summaryFingerprintNote) els.summaryFingerprintNote.textContent = 'Readable information is not proof of uniqueness or tracking. Population uniqueness and stability are not measured.';
        const rtcScope = vector.webrtc === 'complete'
            ? (vector.rtc_families.length ? 'Public server-reflexive samples: ' + vector.rtc_families.map(family => 'IPv' + family).join(', ') + '.' : 'Address-family coverage was not recorded.')
            : ({ host_only: 'Host-only check; active public-address comparison is not complete.', disabled: 'WebRTC API unavailable; active comparison was not possible.', not_checked: 'Active address comparison is incomplete.' }[vector.webrtc]);
        if (els.summaryLeakNote) els.summaryLeakNote.textContent = rtcScope + ' DNS leaks are not tested. Additional addresses are not automatically VPN bypasses.';
        if (els.scoreDisplay) els.scoreDisplay.textContent = scanState.finished + ' / ' + scanState.total;
        if (els.scoreVerdictText) els.scoreVerdictText.textContent = scanState.running ? 'Running checks' : (scanState.cancelled ? 'Report stopped' : 'Report finished');
        if (els.scoreDescText) els.scoreDescText.textContent = scanState.running
            ? 'Results appear as checks finish. Slow or unavailable checks do not stop the rest of the report.'
            : 'Tasks ended, not tests passed. Read the interpretation and measurement gaps before drawing a conclusion. No anonymity score is calculated.';
        renderAssessment(vector);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[ch]));
    }
