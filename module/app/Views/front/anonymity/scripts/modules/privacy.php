<?php // Security & Anti-Tracking test modules. ?>
    async function checkAutomation() {
        const isWebdriver = navigator.webdriver === true;
        const isSelenium = Boolean(window.cdc_adoQpoasnfa || document.documentElement.getAttribute('webdriver'));
        const hasAutomation = isWebdriver || isSelenium;

        applyFinding({
            module: 'Automation Indicators',
            category: 'Security & Anti-Tracking',
            status: hasAutomation ? 'REVIEW' : 'NO_SIGNAL',
            severity: 'low',
            evidence: `webdriver=${isWebdriver} seleniumSignature=${isSelenium}`,
            impactText: hasAutomation ? 'Automation-related flags were observed. They are client-side hints, not proof of malicious activity or a public-address leak.' : 'No tested automation flags were observed. Their absence does not prove the browser is not automated.',
            mitigation: 'Automation is context, not an IP leak or a reason to bypass security checks.'
        });
    }

    async function checkDeviceAPIs(signal) {
        const gpuStatus = 'gpu' in navigator ? 'READABLE' : 'UNSUPPORTED';
        applyFinding({
            module: 'WebGPU API', category: 'Browser Fingerprinting', status: gpuStatus, severity: 'low',
            evidence: 'gpu' in navigator ? 'navigator.gpu interface available' : 'navigator.gpu unavailable',
            impactText: 'API availability is observable. It does not establish device uniqueness or tracking.',
            mitigation: 'No graphics adapter or device was requested.'
        });
        updateMatrixCell(els.mWebGpu, gpuStatus);

        let batteryVal = 'Blocked or unavailable';
        let batteryStatus = 'ERROR';

        if (navigator.getBattery) {
            try {
                const b = await waitForCheck(navigator.getBattery(), signal);
                batteryVal = `level=${Math.round(b.level * 100)}% charging=${b.charging}`;
                batteryStatus = 'READABLE';
            } catch (err) {
                if (signal.aborted) throw err;
                batteryVal = 'Battery API error: ' + err.name;
            }
        } else {
            batteryStatus = 'UNSUPPORTED';
        }

        applyFinding({
            module: 'Battery API',
            category: 'Browser Fingerprinting',
            status: batteryStatus,
            severity: 'low',
            evidence: batteryVal,
            impactText: batteryStatus === 'READABLE' ? 'Battery state was readable; its uniqueness or tracking use is not measured.' : 'No battery reading was obtained. This does not establish tracking protection.',
            mitigation: 'Review hardware API restrictions only if needed for your browser profile.'
        });
    }

    async function checkTheoreticalRisks() {
        const authStatus = window.PublicKeyCredential ? 'READABLE' : 'UNSUPPORTED';
        applyFinding({
            module: 'WebAuthn API',
            status: authStatus,
            severity: 'low',
            evidence: window.PublicKeyCredential ? 'PublicKeyCredential is available' : 'PublicKeyCredential not supported',
            impactText: 'Passkey support is visible, which is normal in modern browsers.',
            mitigation: 'No action required unless you use strict compartmentalized browser profiles.'
        });

        applyFinding({
            module: 'XS-Leaks',
            status: 'NOT_TESTED',
            severity: 'low',
            evidence: 'Cross-site timing checks were not executed.',
            impactText: 'This report does not infer account state through external timing probes.',
            mitigation: 'Keep third-party cookies restricted and isolate sensitive accounts.'
        });
    }

    async function checkAdBlocker(signal) {
        let isBlocked = false;
        
        const adTest = document.createElement('div');
        adTest.className = 'adsbox ad-placement banner-ads doubleclick-ad';
        adTest.setAttribute('style', 'position: absolute; left: -9999px; width: 1px; height: 1px;');
        document.body.appendChild(adTest);
        let frame;
        try {
            await waitForCheck(new Promise(resolve => { frame = requestAnimationFrame(resolve); }), signal);
            isBlocked = adTest.offsetHeight === 0 || window.getComputedStyle(adTest).display === 'none';
        } finally {
            cancelAnimationFrame(frame);
            adTest.remove();
        }

        const status = isBlocked ? 'OBSERVED' : 'NO_SIGNAL';
        applyFinding({
            module: 'Content Filter',
            category: 'Security & Anti-Tracking',
            status: status,
            severity: 'low',
            evidence: isBlocked ? 'Local ad bait element was hidden by browser or extension rules' : 'Local ad bait element remained visible',
            impactText: 'One cosmetic bait element was tested. Its visibility does not establish whether network trackers are blocked.',
            mitigation: 'Check your content blocker settings if needed; this local test is not a complete tracking-protection assessment.'
        });
        updateMatrixCell(els.mAdBlock, status);
    }

    async function checkTrackers() {
        const cookieString = document.cookie;
        const cookieNames = new Set(cookieString.split(';').map(cookie => cookie.trim().split('=')[0]));
        const targetCookies = ['_ga', '_gid', '_fbp', '_fbc', '_ym_uid'];
        const foundCookies = [];
        
        targetCookies.forEach(key => {
            if (cookieNames.has(key)) {
                foundCookies.push(key);
            }
        });

        const foundStorage = [];
        let storageError = '';
        const storageKeys = ['_ga', '_gid', '_fbp', '_ym_uid', 'amplitude', 'mixpanel'];
        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (typeof key !== 'string') continue;
                storageKeys.forEach(s => {
                    if (key.includes(s)) foundStorage.push(key);
                });
            }
        } catch (error) { storageError = error.name || 'Error'; }

        const trackerCount = foundCookies.length + foundStorage.length;
        const cookiesEnabled = navigator.cookieEnabled;
        
        let writeOk = false;
        try {
            document.cookie = "fezadan_test_cookie=1; path=/";
            if (document.cookie.includes("fezadan_test_cookie=1")) {
                writeOk = true;
                document.cookie = "fezadan_test_cookie=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
            }
        } catch (e) {}

        const status = trackerCount > 0 ? 'READABLE' : (storageError ? 'ERROR' : 'NO_SIGNAL');
        let evidence = `cookiesEnabled=${cookiesEnabled} writeVerified=${writeOk} localStorage=${storageError || 'readable'}`;
        if (trackerCount > 0) {
            evidence += ` foundCookies=[${foundCookies.join(',')}] foundStorage=[${foundStorage.join(',')}]`;
        }

        applyFinding({
            module: 'Local Storage & Cookies',
            category: 'Security & Anti-Tracking',
            status: status,
            severity: 'low',
            evidence: evidence,
            impactText: 'Only readable storage on this origin was inspected. Known key names do not prove active tracking; unavailable storage and other sites remain outside this result.',
            mitigation: trackerCount > 0 ? 'Review the named storage keys before clearing them; clearing site data can sign you out. A matching key name does not prove tracking.' : 'Keep third-party cookie restrictions enabled if appropriate. This first-party storage check does not verify cross-site blocking.'
        });
    }

    async function checkPrivacySignals() {
        const dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
        const gpc = navigator.globalPrivacyControl;
        const secure = window.isSecureContext;
        const isolated = window.crossOriginIsolated;

        const signals = [];
        if (dnt === '1') signals.push('DNT=enabled');
        else if (dnt === '0') signals.push('DNT=disabled');
        else signals.push('DNT=unset');
        if (gpc) signals.push('GPC=true');
        if (secure) signals.push('SecureContext');
        if (isolated) signals.push('CrossOriginIsolated');

        const status = (dnt === '1' || gpc) ? 'READABLE' : 'NO_SIGNAL';

        applyFinding({
            module: 'Privacy Signals',
            category: 'Security & Anti-Tracking',
            status: status,
            severity: 'low',
            evidence: signals.join(', ') || 'No privacy signals detected',
            impactText: (dnt === '1' || gpc) ? 'DNT/GPC preference signals are sent; they do not prove that tracking is blocked.' : 'No DNT/GPC preference signal is being sent.',
            mitigation: 'Use Global Privacy Control where supported to express a preference. Neither GPC nor DNT replaces content blocking, cookie restrictions or account separation.'
        });
        updateMatrixCell(els.mPrivacy, status);
    }

    async function checkSocialSessions() {
        applyFinding({
            module: 'Cross-site Sessions',
            status: 'NOT_TESTED',
            severity: 'low',
            evidence: 'No third-party session probes were sent.',
            impactText: 'Timing probes against external login providers are noisy and create unnecessary third-party traffic.',
            mitigation: 'Use private browsing or browser profile separation for account isolation.'
        });
        updateMatrixCell(els.mSocial, 'NOT_TESTED');
    }
