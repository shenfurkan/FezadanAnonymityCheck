<?php // Browser Fingerprinting test modules. ?>
    async function checkWebRtc(signal) {
        if (!window.RTCPeerConnection) {
            privacyState.webrtc = 'disabled';
            applyFinding({
                module: 'WebRTC API',
            category: 'Browser Fingerprinting',
                status: 'UNSUPPORTED',
                severity: 'low',
                evidence: 'RTCPeerConnection constructor undefined',
                impactText: 'The WebRTC API was not exposed to this page. That prevents this measurement; it does not prove why the API is absent or that every network route is protected.',
                mitigation: 'No leak-free verdict can be made from API unavailability. Use the visible connection address and other completed checks separately.'
            });
            updateMatrixCell(els.mWebRtc, 'UNSUPPORTED');
            return;
        }

        const result = rtcGatheringResult || await gatherIceCandidates([], signal);
        const local = result.candidates.filter(candidate => candidate.type !== 'relay' && candidate.ip?.scope === 'local');
        const mdns = result.candidates.filter(candidate => candidate.mdns);
        recordPublicRtcLeaks(result, serverSignals.ip);
        if (privacyState.webrtc !== 'complete') {
            privacyState.webrtc = result.completed && !result.error && !serverSignals.iceServers.length ? 'host_only' : 'not_checked';
        }
        if (local.length > 0) {
            applyFinding({
                module: 'WebRTC Local Address', category: 'Network & Infrastructure', dimension: 'leak', address_scope: 'local',
                status: 'EXPOSED', severity: 'medium',
                evidence: 'Local ICE candidates: ' + local.map(candidate => candidate.address).join(', '),
                impactText: 'Private or link-local addresses were readable. This reveals local addressing, not a public ISP identity.',
                mitigation: 'Use WebRTC IP-handling policies that mask local addresses with mDNS.'
            });
        }
        const publicAddress = findings.some(item => item.dimension === 'leak' && item.address_scope === 'public');
        if (local.length || publicAddress) {
            updateMatrixCell(els.mWebRtc, local.length ? 'EXPOSED' : 'OBSERVED');
        } else if (result.error || !result.completed) {
            applyFinding({
                module: 'WebRTC Host Check', category: 'Network & Infrastructure', dimension: 'leak',
                status: result.error ? 'ERROR' : 'TIMEOUT', severity: 'low',
                evidence: result.error || 'ICE gathering timed out',
                impactText: 'The check was incomplete; failure is not proof of protection.',
                mitigation: 'Review WebRTC policies. Do not rely on an incomplete result to rule out leaks.'
            });
            privacyState.webrtc = 'not_checked';
            updateMatrixCell(els.mWebRtc, result.error ? 'ERROR' : 'TIMEOUT');
        } else {
            applyFinding({
                module: 'WebRTC Host Check', category: 'Network & Infrastructure', dimension: 'leak',
                status: 'NO_SIGNAL', severity: 'low',
                evidence: mdns.length ? 'Local hosts were masked with mDNS hostnames.' : 'No additional global or private address was observed.',
                impactText: 'Only gathered candidates are covered. Host-only gathering cannot rule out STUN or DNS leaks.',
                mitigation: 'Keep IP-handling policies enabled and verify IPv4 and IPv6 tunnel routing.'
            });
            updateMatrixCell(els.mWebRtc, privacyState.webrtc === 'complete' ? 'NO_SIGNAL' : 'NOT_TESTED');
        }
    }

    async function checkCanvas(signal) {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 360;
            canvas.height = 90;
            const ctx = canvas.getContext('2d');
            if (!ctx) throw new DOMException('Canvas 2D context unavailable', 'NotSupportedError');

            ctx.textBaseline = 'top';
            ctx.fillStyle = '#f60';
            ctx.fillRect(10, 10, 110, 36);
            ctx.fillStyle = '#069';
            ctx.font = "18px 'Space Grotesk', Arial";
            ctx.fillText('fezadan fingerprint sample', 14, 18);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('fezadan fingerprint sample', 16, 40);
            
            const canvasHash = await sha256(canvas.toDataURL(), signal);
            applyFinding({
                module: 'Canvas Fingerprint',
            category: 'Browser Fingerprinting',
                status: 'READABLE',
                severity: 'low',
            evidence: 'hash=' + shortHash(canvasHash),
            impactText: 'Canvas output was readable. One sample does not establish stability or population uniqueness.',
            mitigation: 'If needed, use built-in browser fingerprinting protection. One readable sample does not show whether output is randomized.'
            });
            updateMatrixCell(els.mCanvas, 'READABLE');
        } catch (error) {
            if (signal.aborted) throw error;
            const status = error.name === 'NotSupportedError' ? 'UNSUPPORTED' : (error.name === 'SecurityError' ? 'BLOCKED' : 'ERROR');
            applyFinding({
                module: 'Canvas Fingerprint',
            category: 'Browser Fingerprinting',
                status,
                severity: 'low',
                evidence: 'Canvas rendering failed: ' + error.message,
                impactText: 'The sample could not be read. A failure does not establish fingerprinting protection.',
                mitigation: 'Review browser API restrictions if you want to repeat this check.'
            });
            updateMatrixCell(els.mCanvas, status);
        }
    }

    async function checkWebGL(signal) {
        const glCanvas = document.createElement('canvas');
        const gl = glCanvas.getContext('webgl') || glCanvas.getContext('experimental-webgl');
        
        if (!gl) {
            applyFinding({
                module: 'WebGL Renderer',
            category: 'Browser Fingerprinting',
                status: 'UNSUPPORTED',
                severity: 'low',
                evidence: 'WebGL graphics context is not initialized',
                impactText: 'Hardware graphics APIs are unavailable or blocked.',
                mitigation: 'No renderer sample was obtained. API unavailability alone does not establish fingerprinting protection.'
            });
            updateMatrixCell(els.mWebGL, 'UNSUPPORTED');
            return;
        }

        try {
            const ext = gl.getExtension('WEBGL_debug_renderer_info');
            const vendor = ext ? gl.getParameter(ext.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR);
            const renderer = ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
            const glHash = await sha256(vendor + '|' + renderer, signal);
            const status = 'READABLE';

            applyFinding({
                module: 'WebGL Renderer',
            category: 'Browser Fingerprinting',
                status: status,
                severity: 'low',
            evidence: 'vendor=' + vendor + ' renderer=' + renderer + ' hash=' + shortHash(glHash),
            impactText: 'Renderer details were readable. They may be normalized or shared by many devices; uniqueness is not measured.',
            mitigation: 'Use browser fingerprinting protection if needed. Software rendering is not itself a privacy failure.'
            });
            updateMatrixCell(els.mWebGL, status);
        } catch (error) {
            if (signal.aborted) throw error;
            applyFinding({
                module: 'WebGL Renderer',
            category: 'Browser Fingerprinting',
                status: error.name === 'SecurityError' ? 'BLOCKED' : 'ERROR',
                severity: 'low',
                evidence: 'Error fetching extension arguments: ' + error.message,
                impactText: 'Renderer information could not be read. This does not establish tracking protection.',
                mitigation: 'Review browser graphics settings if you want to repeat the check.'
            });
            updateMatrixCell(els.mWebGL, error.name === 'SecurityError' ? 'BLOCKED' : 'ERROR');
        } finally {
            const loseContext = gl.getExtension('WEBGL_lose_context');
            if (loseContext) loseContext.loseContext();
        }
    }

    async function checkAudio(signal) {
        let osc;
        let compressor;
        try {
            const OfflineCtx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
            if (!OfflineCtx) throw new DOMException('OfflineAudioContext unavailable', 'NotSupportedError');
            
            const ac = new OfflineCtx(1, 44100, 44100);
            osc = ac.createOscillator();
            compressor = ac.createDynamicsCompressor();
            
            osc.type = 'triangle';
            osc.frequency.value = 10000;
            compressor.threshold.value = -50;
            compressor.knee.value = 40;
            compressor.ratio.value = 12;
            
            osc.connect(compressor);
            compressor.connect(ac.destination);
            
            osc.start(0);
            const rendered = await waitForCheck(ac.startRendering(), signal);
            const data = rendered.getChannelData(0).slice(0, 300);
            const audioHash = await sha256(Array.from(data).map(n => n.toFixed(6)).join(','), signal);
            
            applyFinding({
                module: 'Audio Fingerprint',
            category: 'Browser Fingerprinting',
                status: 'READABLE',
                severity: 'low',
            evidence: 'hash=' + shortHash(audioHash),
            impactText: 'Audio output was readable. Stability and uniqueness were not measured.',
            mitigation: 'If this matters to your threat model, use maintained, built-in browser fingerprinting protections. A readable sample is not a reason to install spoofing extensions.'
            });
            updateMatrixCell(els.mAudio, 'READABLE');
        } catch (error) {
            if (signal.aborted) throw error;
            const status = error.name === 'NotSupportedError' ? 'UNSUPPORTED' : (error.name === 'SecurityError' ? 'BLOCKED' : 'ERROR');
            applyFinding({
                module: 'Audio Fingerprint',
            category: 'Browser Fingerprinting',
                status,
                severity: 'low',
                evidence: error.message,
                impactText: 'No audio sample was obtained. An unsupported or failed check does not establish tracking protection.',
                mitigation: 'No sample was obtained. Review browser restrictions only if you need this measurement; do not treat the failure as proof of protection.'
            });
            updateMatrixCell(els.mAudio, status);
        } finally {
            // OfflineAudioContext has no close/cancel API; release nodes and ignore late samples.
            if (osc) {
                try { osc.stop(); } catch (error) { /* The oscillator may not have started. */ }
                osc.disconnect();
            }
            if (compressor) compressor.disconnect();
        }
    }

    async function checkBrowserLocaleAndTime() {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        els.endpointTimezone.textContent = tz;

        const screenDetails = `${screen.width}x${screen.height} (DPR: ${window.devicePixelRatio || 1})`;
        const cpuCores = navigator.hardwareConcurrency || 'hidden';
        const memory = navigator.deviceMemory || 'hidden';
        const lang = (navigator.languages || [navigator.language]).join(', ');

        const evidence = `tz=${tz} screen=${screenDetails} cores=${cpuCores} memory=${memory} lang=${lang}`;
        
        applyFinding({
            module: 'Hardware & Locales',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: evidence,
            impactText: 'These browser properties are readable. Their stability and population uniqueness are not measured.',
            mitigation: 'Use built-in browser fingerprinting protection if your profile needs to limit these details.'
        });
        updateMatrixCell(els.mTimezone, 'READABLE');

        const serverGeoTimezone = serverSignals.geoTimezone;
        if (serverGeoTimezone && tz && serverGeoTimezone !== tz) {
            applyFinding({
                module: 'Timezone Mismatch',
                category: 'Network & Infrastructure',
                status: 'NO_SIGNAL',
                severity: 'low',
                evidence: 'browser_tz=' + tz + ' server_geo_tz=' + serverGeoTimezone,
                impactText: 'Timezone settings differ from the IP estimate. Travel, browser settings and inaccurate IP data can explain this; it is not evidence of VPN use or a vulnerability.',
                mitigation: 'No timezone change is recommended from this observation. It does not prove VPN use.'
            });
        }
    }

    async function checkUaClientHints(signal) {
        if (!navigator.userAgentData) {
            applyFinding({
                module: 'UA Client Hints',
                category: 'Browser Fingerprinting',
                status: 'UNSUPPORTED',
                severity: 'low',
                evidence: 'navigator.userAgentData not available',
                impactText: 'User-Agent Client Hints API is not supported in this browser.',
                mitigation: 'No change is needed solely because this API is absent. Other headers and browser APIs can still expose information.'
            });
            updateMatrixCell(els.mUach, 'UNSUPPORTED');
            return;
        }

        const b = navigator.userAgentData.brands || [];
        const brands = b.map(x => x.brand + '/' + x.version).join(', ');
        const mobile = navigator.userAgentData.mobile;
        const platform = navigator.userAgentData.platform;

        let evidence = 'brands=' + brands + ' mobile=' + mobile + ' platform=' + platform;

        try {
            const high = await waitForCheck(navigator.userAgentData.getHighEntropyValues([
                'architecture', 'bitness', 'model', 'platformVersion', 'fullVersionList'
            ]), signal);
            if (high) {
                evidence += ' arch=' + (high.architecture || '?') + ' bits=' + (high.bitness || '?') + ' model=' + (high.model || 'none') + ' platformVer=' + (high.platformVersion || '?');
            }
        } catch (e) {
            if (signal.aborted) throw e;
            evidence += ' highEntropy=unavailable (' + (e.name || 'Error') + ')';
        }

        applyFinding({
            module: 'UA Client Hints',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: evidence,
            impactText: 'Brand and platform information was readable. Additional details are included only if the browser returned them; Client Hints can coexist with the User-Agent header. This is not a uniqueness measurement.',
            mitigation: 'Prefer supported browser privacy settings over experimental flags or arbitrary header spoofing. Consider these details alongside other readable properties.'
        });
        updateMatrixCell(els.mUach, 'READABLE');
    }

    async function checkScreenFingerprint() {
        const props = {
            colorDepth: screen.colorDepth,
            pixelDepth: screen.pixelDepth,
            availW: screen.availWidth,
            availH: screen.availHeight,
            innerW: window.innerWidth,
            innerH: window.innerHeight,
            outerW: window.outerWidth,
            outerH: window.outerHeight,
            orient: screen.orientation ? screen.orientation.type : 'unavailable',
            extended: screen.isExtended || false
        };

        applyFinding({
            module: 'Screen Properties',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: 'cd=' + props.colorDepth + ' pd=' + props.pixelDepth + ' viewport=' + props.innerW + 'x' + props.innerH + ' outer=' + props.outerW + 'x' + props.outerH + ' avail=' + props.availW + 'x' + props.availH + ' orient=' + props.orient,
            impactText: 'Screen and window dimensions are readable. Browser toolbars and accessibility settings can explain differences; uniqueness is not measured.',
            mitigation: 'Built-in browser letterboxing can reduce dimension detail if needed. A window-size difference is not a vulnerability.'
        });
        updateMatrixCell(els.mScreen, 'READABLE');
    }

    async function checkPluginsAndMime() {
        const plugins = Array.from(navigator.plugins || []);
        const mimeTypes = Array.from(navigator.mimeTypes || []);
        const names = plugins.map(p => p.name);
        const count = names.length;

        applyFinding({
            module: 'Plugin & MIME Enumeration',
            category: 'Browser Fingerprinting',
            status: count > 0 ? 'READABLE' : 'NO_SIGNAL',
            severity: 'low',
            evidence: 'plugins=' + count + ' mimeTypes=' + mimeTypes.length + (count > 0 ? ' names=' + names.slice(0, 4).join(', ') + (count > 4 ? '...' : '') : ''),
            impactText: 'The browser reports these entries. Modern browsers often return a standardized list of PDF-viewer aliases, not distinct installed plugins.',
            mitigation: 'No plugin removal is recommended based on this count alone.'
        });
        updateMatrixCell(els.mPlugins, count > 0 ? 'READABLE' : 'NO_SIGNAL');
    }

    async function checkConnectionFingerprint() {
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!conn) {
            applyFinding({
                module: 'Connection Info',
                category: 'Browser Fingerprinting',
                status: 'UNSUPPORTED',
                severity: 'low',
                evidence: 'NetworkInformation API unavailable',
                impactText: 'Network type and speed cannot be enumerated from this browser.',
                mitigation: 'No action follows from this unavailable API alone.'
            });
            updateMatrixCell(els.mConnection, 'UNSUPPORTED');
            return;
        }

        const evidence = 'type=' + (conn.effectiveType || '?') + ' downlink=' + (conn.downlink != null ? conn.downlink + 'Mbps' : '?') + ' rtt=' + (conn.rtt != null ? conn.rtt + 'ms' : '?') + ' saveData=' + (conn.saveData || false);

        applyFinding({
            module: 'Connection Info',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: evidence,
            impactText: 'Coarse speed and latency estimates were readable. An effective type such as 4g is a performance category, not proof of cellular service, Wi-Fi or a particular network.',
            mitigation: 'No connection change is recommended from these estimates alone. Use built-in browser privacy controls if limiting this API is important to you.'
        });
        updateMatrixCell(els.mConnection, 'READABLE');
    }

    async function checkSpeechVoices(signal) {
        if (!window.speechSynthesis) {
            applyFinding({
                module: 'Speech Voices', category: 'Browser Fingerprinting', status: 'UNSUPPORTED', severity: 'low',
                evidence: 'Speech synthesis API unavailable',
                impactText: 'No voice list was read. API unavailability does not establish fingerprinting protection.',
                mitigation: 'No speech playback or external voice request was made.'
            });
            updateMatrixCell(els.mSpeech, 'UNSUPPORTED');
            return;
        }

        let voices = speechSynthesis.getVoices();
        if (!voices || !voices.length) {
            let timer;
            const onVoicesChanged = () => { voices = speechSynthesis.getVoices(); finish(); };
            let finish;
            try {
                await waitForCheck(new Promise(resolve => {
                    finish = resolve;
                    speechSynthesis.addEventListener('voiceschanged', onVoicesChanged);
                    timer = setTimeout(resolve, 800);
                }), signal);
            } finally {
                clearTimeout(timer);
                speechSynthesis.removeEventListener('voiceschanged', onVoicesChanged);
            }
        }

        if (!voices || !voices.length) {
            applyFinding({
                module: 'Speech Voices', category: 'Browser Fingerprinting', status: 'NO_DATA', severity: 'low',
                evidence: 'The speech API returned an empty voice list within the bounded wait',
                impactText: 'No voice data was returned. This is not proof that fingerprinting is blocked.',
                mitigation: 'No speech playback or external voice request was made.'
            });
            updateMatrixCell(els.mSpeech, 'NO_DATA');
            return;
        }

        const langs = [...new Set(voices.map(v => v.lang))];
        const count = voices.length;

        applyFinding({
            module: 'Speech Voices',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: 'voices=' + count + ' langs=' + langs.slice(0, 5).join(', ') + (langs.length > 5 ? '...' : ''),
            impactText: 'The browser returned voice names and languages. They may reveal platform or language configuration, but may also be standardized or shared; uniqueness and repeated recognition are not measured.',
            mitigation: 'Use built-in fingerprinting protection if needed. Do not disable accessibility features solely because a voice list was readable.'
        });
        updateMatrixCell(els.mSpeech, 'READABLE');
    }

    async function checkMathPrecision() {
        const vec = {};
        const val = Math.sin(1e100);
        vec.sin1e100 = isNaN(val) ? 'NaN' : Number.isFinite(val) ? val.toExponential(12) : 'overflow';
        const val2 = Math.tan(-1e100);
        vec.tanNeg1e100 = isNaN(val2) ? 'NaN' : Number.isFinite(val2) ? val2.toExponential(12) : 'overflow';
        const val3 = Math.exp(1000);
        vec.exp1000 = isNaN(val3) ? 'NaN' : Number.isFinite(val3) ? val3.toExponential(12) : 'overflow';

        applyFinding({
            module: 'Math Precision',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: 'sin=' + vec.sin1e100 + ' tan=' + vec.tanNeg1e100 + ' exp=' + vec.exp1000,
            impactText: 'JavaScript math results were readable. This sample does not identify a CPU or measure browser uniqueness.',
            mitigation: 'No configuration change is recommended based on these values alone.'
        });
    }

    async function checkHeaderAnalysis() {
        const h = serverSignals.headers || {};
        const acceptLang = h['HTTP_ACCEPT_LANGUAGE'] || '';
        // A language alone is not a country (English is not necessarily US).
        const languageRegion = ((acceptLang.split(',')[0] || '').split(';')[0].match(/-([a-z]{2})\b/i) || [])[1]?.toUpperCase();

        const hasSecChUa = 'HTTP_SEC_CH_UA' in h;
        const upgradeInsecure = h['HTTP_UPGRADE_INSECURE_REQUESTS'] === '1';
        const acceptEnc = h['HTTP_ACCEPT_ENCODING'] || '';
        const hasBrotli = acceptEnc.includes('br');
        const hasZstd = acceptEnc.includes('zstd');

        const signals = [];
        if (hasSecChUa) signals.push('Sec-CH-UA');
        if (upgradeInsecure) signals.push('Upgrade-Insecure');
        if (hasBrotli) signals.push('Brotli');
        if (hasZstd) signals.push('Zstd');

        const serverCountry = serverSignals.country;
        if (/^[A-Z]{2}$/.test(serverCountry) && serverCountry !== 'XX' && languageRegion && languageRegion !== serverCountry) {
            applyFinding({
                module: 'Lang-Geo Mismatch', category: 'Network & Infrastructure', status: 'NO_SIGNAL',
                severity: 'low',
                evidence: 'language_region=' + languageRegion + ' ip_country=' + serverCountry,
                impactText: 'Language preference differs from the IP country estimate. Travel, multilingual use and inaccurate IP data can explain this; it is not evidence of VPN use.',
                mitigation: 'No language change is recommended; this observation does not prove a tunnel.'
            });
        }
        const status = signals.length > 0 ? 'READABLE' : 'NO_SIGNAL';

        applyFinding({
            module: 'HTTP Header Profile',
            category: 'Browser Fingerprinting',
            status: status,
            severity: 'low',
            evidence: signals.length > 0 ? signals.join(', ') : 'Standard header set, no mismatches',
            impactText: 'These HTTP headers describe browser capabilities. A language / location difference is not proof of a privacy failure.',
            mitigation: 'Prefer a maintained privacy browser profile. Changing individual headers can create inconsistencies and does not establish anonymity.'
        });
        updateMatrixCell(els.mHeaders, status);
    }

    async function checkMediaQueries() {
        const tests = {
            'dark-mode': '(prefers-color-scheme: dark)',
            'reduced-motion': '(prefers-reduced-motion: reduce)',
            'high-contrast': '(prefers-contrast: more)',
            'reduced-transparency': '(prefers-reduced-transparency: reduce)',
            'forced-colors': '(forced-colors: active)',
            'inverted-colors': '(inverted-colors: inverted)',
            'p3-gamut': '(color-gamut: p3)',
            'rec2020-gamut': '(color-gamut: rec2020)',
            'high-dynamic-range': '(dynamic-range: high)',
        };

        const active = [];
        for (const [name, query] of Object.entries(tests)) {
            if (matchMedia(query).matches) active.push(name);
        }

        const status = active.length > 0 ? 'READABLE' : 'NO_SIGNAL';

        applyFinding({
            module: 'Media Query Profile',
            category: 'Browser Fingerprinting',
            status: status,
            severity: 'low',
            evidence: active.length > 0 ? 'Active: ' + active.join(', ') : 'No special media features detected',
            impactText: active.length > 0 ? 'OS-level display and accessibility preferences are readable via CSS media queries.' : 'No detectable OS-level media preferences.',
            mitigation: 'Use browser privacy modes that normalize CSS media query responses.'
        });
        updateMatrixCell(els.mMedia, status);
    }

    async function checkTouchAndInput() {
        const Touch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const pts = navigator.maxTouchPoints || 0;
        const coarse = matchMedia('(pointer: coarse)').matches;
        const fine = matchMedia('(pointer: fine)').matches;
        const hover = matchMedia('(hover: hover)').matches;

        applyFinding({
            module: 'Touch & Input',
            category: 'Browser Fingerprinting',
            status: 'READABLE',
            severity: 'low',
            evidence: 'touch=' + Touch + ' points=' + pts + ' coarse=' + coarse + ' fine=' + fine + ' hover=' + hover,
            impactText: 'Input modality (touch, pointer, hover) can indicate mobile vs desktop class.',
            mitigation: 'No input-setting change is recommended from device-class information alone.'
        });
        updateMatrixCell(els.mInput, 'READABLE');
    }

    async function checkFonts() {
        const testFonts = [
            'Consolas', 'Segoe UI', 'Calibri', 'Cambria', 'MS Trebuchet',
            'Helvetica Neue', 'Monaco', 'Geneva', 'Chalkboard',
            'Liberation Mono', 'Ubuntu',
            'Impact', 'Comic Sans MS', 'Arial Black'
        ];

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            applyFinding({
                module: 'Installed Fonts', category: 'Browser Fingerprinting', status: 'UNSUPPORTED', severity: 'low',
                evidence: 'Canvas text measurement context unavailable',
                impactText: 'No font-width comparisons were performed. A missing rendering context does not establish that fonts are hidden.',
                mitigation: 'No font changes are recommended without a measurement.'
            });
            updateMatrixCell(els.mFonts, 'UNSUPPORTED');
            return;
        }

        const testString = "mmmmmmmmmmlli";
        ctx.font = "72px serif";
        const baseWidth = ctx.measureText(testString).width;

        const detectedFonts = [];

        testFonts.forEach(font => {
            ctx.font = `72px "${font}", serif`;
            const width = ctx.measureText(testString).width;
            if (width !== baseWidth) {
                detectedFonts.push(font);
            }
        });

        const status = detectedFonts.length > 0 ? 'READABLE' : 'NO_SIGNAL';
        applyFinding({
            module: 'Installed Fonts',
            category: 'Browser Fingerprinting',
            status: status,
            severity: 'low',
            evidence: detectedFonts.length > 0 ? 'Width-based font candidates: ' + detectedFonts.slice(0, 5).join(', ') + (detectedFonts.length > 5 ? '...' : '') : 'No width difference in the tested font candidates',
            impactText: 'Text-width comparisons are a heuristic, not a confirmed inventory of installed fonts or a uniqueness measurement.',
            mitigation: 'Prefer the browser’s built-in font protections. Do not remove fonts based on this small, heuristic sample.'
        });
        updateMatrixCell(els.mFonts, status);
    }

    async function updateGeoDom() {
        const countryDisplay = serverSignals.countryName
            ? serverSignals.countryName + (serverSignals.country && serverSignals.country !== serverSignals.countryName ? ' (' + serverSignals.country + ')' : '')
            : (serverSignals.country || 'Unavailable');
        const cityDisplay = serverSignals.city || 'Unavailable';
        const networkDisplay = serverSignals.networkOrg
            ? serverSignals.networkOrg + (serverSignals.networkAsn ? ' / ' + serverSignals.networkAsn : '')
            : (serverSignals.networkAsn || 'Unavailable');

        const countryEls = document.querySelectorAll('.geo-details-item .param-value');
        for (const el of countryEls) {
            const label = el.parentElement.querySelector('.param-label');
            if (label && label.textContent.trim() === 'Country') el.textContent = countryDisplay;
        }

        const cityEls = document.querySelectorAll('.geo-details-item .param-value');
        for (const el of cityEls) {
            const label = el.parentElement.querySelector('.param-label');
            if (label && label.textContent.trim() === 'City') el.textContent = cityDisplay;
        }

        const ispEls = document.querySelectorAll('.param-list .param-item');
        for (const row of ispEls) {
            const label = row.querySelector('.param-label');
            if (label && label.textContent.trim() === 'ISP / ASN') {
                const valEl = row.querySelector('.param-value');
                if (valEl) valEl.textContent = networkDisplay;
                break;
            }
        }

        const sourceTag = document.getElementById('geo-source-tag');
        if (sourceTag && serverSignals.geoSource
                && serverSignals.geoSource !== 'Cloudflare'
                && serverSignals.geoSource !== 'Unavailable') {
            sourceTag.textContent = 'Source: ' + serverSignals.geoSource;
            sourceTag.style.display = 'block';
        } else if (sourceTag) {
            sourceTag.style.display = 'none';
        }
    }
