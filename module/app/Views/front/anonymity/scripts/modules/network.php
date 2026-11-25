<?php // Network & Infrastructure test modules. ?>
    async function checkServerNetwork(signal) {
        const edgeHeaders = hasTrustedEdgeHeaders();
        const hasProxy = serverSignals.proxyCount > 0;
        const proxyStatus = hasProxy ? 'READABLE' : 'NO_SIGNAL';
        applyFinding({
            module: 'Proxy Headers',
            category: 'Network & Infrastructure',
            status: proxyStatus,
            severity: 'low',
            evidence: hasProxy
                ? 'proxyHeaders=' + serverSignals.proxyHeaders.join(', ')
                : (edgeHeaders ? 'Trusted CDN edge headers present; not treated as a user proxy signal' : 'No HTTP proxy headers matching routing intermediates'),
            impactText: hasProxy
                ? 'Routing headers may be added by site infrastructure or supplied by clients. They do not establish whether the visitor uses a VPN or proxy.'
                : (edgeHeaders ? 'The request reached the origin through a CDN edge. This is normal site infrastructure, not proof that the visitor is using a proxy.' : 'No proxy headers were observed in the request.'),
            mitigation: 'Routing headers are a heuristic, not proof of VPN use or anonymity.'
        });
        updateMatrixCell(els.mProxy, proxyStatus);

        let torDetected = serverSignals.isTor === true;
        let torEvidence = torDetected
            ? 'Tor exit signal from ' + (serverSignals.torSource || 'Cloudflare')
            : 'Tor status not verified; a fresh local exit list is required';
        let torVerified = Boolean(serverSignals.torSource) && typeof serverSignals.isTor === 'boolean';
        serverSignals.isTor = torVerified ? torDetected : null;
        privacyState.network = torDetected ? 'anonymous' : 'unknown';
        if (torVerified && !torDetected) torEvidence = 'Not found in the fresh local Tor exit snapshot.';

        if (!torVerified) {
            const result = await fetchJsonWithTimeout(anonymityEndpoint('/tor-check'), 2500, signal);
            if (result.ok && typeof result.is_tor === 'boolean') {
                torDetected = torDetected || result.is_tor;
                torVerified = true;
                if (result.is_tor || !serverSignals.isTor) {
                    torEvidence = 'Visitor exit-list check (' + (result.source || 'local_exit_list') + '): is_tor=' + result.is_tor;
                    serverSignals.torSource = result.source || 'local_exit_list';
                }
            } else if (result.error) {
                torEvidence = result.error;
            }
        }

        serverSignals.isTor = torVerified ? torDetected : null;
        privacyState.network = torDetected ? 'anonymous' : 'unknown';
        const torStatus = torDetected ? 'OBSERVED' : (torVerified ? 'NO_SIGNAL' : 'NO_DATA');
        applyFinding({
            module: 'Tor Infrastructure',
            category: 'Network & Infrastructure',
            status: torStatus,
            severity: 'low',
            evidence: torEvidence,
            impactText: torDetected ? 'The HTTP endpoint is a Tor exit node; this does not rule out browser leaks.' : (torVerified ? 'The visitor address was not found in the current snapshot. This does not rule out a VPN or proxy.' : 'Tor status is unknown, not a verified negative.'),
            mitigation: 'Tor use is not itself a risk. A browser configured for Tor still needs separate address checks.'
        });
        updateMatrixCell(els.mTor, torStatus);
    }

    async function checkNetworkOwner() {
        // Runs after the shared geo check, including failure. Never trigger an implicit retry.
        const org = serverSignals.networkOrg || '';
        const asn = serverSignals.networkAsn || '';
        const hasNetworkOwner = Boolean(org || asn);
        const evidence = hasNetworkOwner
            ? 'org=' + (org || 'unreported') + ' asn=' + (asn || 'unreported')
            : 'No ISP/ASN data available from trusted headers or local databases.';

        const vpnOwner = /\b(vpn|mullvad|proton(?:vpn)?|ivpn|nordvpn|surfshark|expressvpn|windscribe)\b/i.test(org);
        if (serverSignals.isTor === true) privacyState.network = 'anonymous';
        else if (vpnOwner) privacyState.network = 'masked';
        // An ordinary ISP, unknown ASN, or hosting provider does not prove direct access.
        const masked = privacyState.network === 'anonymous' || privacyState.network === 'masked';
        const ownerStatus = hasNetworkOwner ? 'READABLE' : 'NO_DATA';
        applyFinding({
            module: 'ISP / ASN',
            category: 'Network & Infrastructure',
            status: ownerStatus,
            severity: 'low',
            evidence: evidence,
            impactText: masked ? 'The visible owner may describe an exit provider. A provider-name hint does not verify VPN use or every connection route.' : 'The owner of the visible HTTP address does not establish whether your access-provider IP is masked. No direct-connection verdict is made.',
            mitigation: 'Compare this visible address with the exit expected from your VPN or Tor. Provider-name classification is only a hint.'
        });
        updateMatrixCell(els.mIsp, ownerStatus);
    }

    async function checkDnsResolver() {
        applyFinding({
            module: 'DNS Resolver',
            category: 'Network & Infrastructure',
            status: 'NOT_TESTED',
            severity: 'low',
            evidence: 'Resolver IP is not available from a normal HTTP request.',
            impactText: 'DNS leak testing needs a first-party DNS probe. This page does not call an external DNS test service.',
            mitigation: 'Verify resolver routing with your tunnel provider. DoH alone does not prove that DNS follows the tunnel.'
        });
        updateMatrixCell(els.mDns, 'NOT_TESTED');
    }

    function parseIpAddress(value) {
        if (typeof value !== 'string' || !value || value.includes('%') || value !== value.trim()) return null;
        if (!value.includes(':')) {
            if (!/^(?:0|[1-9]\d{0,2})(?:\.(?:0|[1-9]\d{0,2})){3}$/.test(value)) return null;
            const octets = value.split('.').map(Number);
            if (octets.some(n => n > 255)) return null;
            const [a, b, c] = octets;
            const local = a === 10 || a === 127 || (a === 172 && b >= 16 && b <= 31)
                || (a === 192 && b === 168) || (a === 169 && b === 254) || (a === 100 && b >= 64 && b <= 127);
            const reserved = a === 0 || a >= 224 || (a === 192 && b === 0 && (c === 0 || c === 2))
                || (a === 198 && (b === 18 || b === 19 || (b === 51 && c === 100))) || (a === 203 && b === 0 && c === 113);
            return { family: 4, key: octets.join('.'), scope: local ? 'local' : (reserved ? 'reserved' : 'global') };
        }
        // RFC 4291 compressed/full IPv6, embedded IPv4, and binary-equivalent RFC 5952 forms.
        let text = value.toLowerCase();
        if (text.includes('.')) {
            const tail = text.slice(text.lastIndexOf(':') + 1);
            const ipv4 = parseIpAddress(tail);
            if (!ipv4 || ipv4.family !== 4) return null;
            const octets = tail.split('.').map(Number);
            text = text.slice(0, text.lastIndexOf(':') + 1)
                + ((octets[0] << 8) | octets[1]).toString(16) + ':' + ((octets[2] << 8) | octets[3]).toString(16);
        }
        const halves = text.split('::');
        if (halves.length > 2) return null;
        const left = halves[0] ? halves[0].split(':') : [];
        const right = halves.length === 2 && halves[1] ? halves[1].split(':') : [];
        if ([...left, ...right].some(part => !/^[0-9a-f]{1,4}$/.test(part))) return null;
        const missing = 8 - left.length - right.length;
        if ((halves.length === 1 && missing !== 0) || (halves.length === 2 && missing < 1)) return null;
        const words = [...left, ...Array(missing).fill('0'), ...right].map(part => parseInt(part, 16));
        if (words.slice(0, 5).every(n => n === 0) && words[5] === 0xffff) {
            return parseIpAddress([words[6] >> 8, words[6] & 255, words[7] >> 8, words[7] & 255].join('.'));
        }
        const loopback = words.slice(0, 7).every(n => n === 0) && words[7] === 1;
        const local = (words[0] & 0xfe00) === 0xfc00 || (words[0] & 0xffc0) === 0xfe80 || loopback;
        const global = (words[0] & 0xe000) === 0x2000
            && !(words[0] === 0x2001 && (words[1] === 0xdb8 || words[1] === 2 || (words[1] & 0xfff0) === 0x10 || (words[1] & 0xfff0) === 0x20))
            && !(words[0] === 0x3fff && (words[1] & 0xf000) === 0);
        return { family: 6, key: words.map(n => n.toString(16).padStart(4, '0')).join(':'), scope: local ? 'local' : (global ? 'global' : 'reserved') };
    }

    function parseIceCandidate(candidate) {
        const parts = String(candidate.candidate || '').trim().split(/\s+/);
        const address = candidate.address || parts[4] || '';
        const typeIndex = parts.indexOf('typ');
        const type = candidate.type || (typeIndex >= 0 ? parts[typeIndex + 1] : '');
        return { address, type, ip: parseIpAddress(address), mdns: /\.local$/i.test(address) };
    }

    async function gatherIceCandidates(iceServers, signal) {
        const result = { candidates: [], completed: false, error: null };
        let pc;
        let timer;
        let finish;
        const done = new Promise(resolve => { finish = resolve; });
        try {
            pc = new RTCPeerConnection({ iceServers, iceTransportPolicy: 'all' });
            const seen = new Set();
            pc.createDataChannel('privacy-check');
            pc.onicecandidate = event => {
                if (!event.candidate) { result.completed = true; finish(); return; }
                const candidate = parseIceCandidate(event.candidate);
                const key = candidate.address + '|' + candidate.type;
                if (!seen.has(key)) { seen.add(key); result.candidates.push(candidate); }
            };
            pc.onicegatheringstatechange = () => {
                if (pc.iceGatheringState === 'complete') { result.completed = true; finish(); }
            };
            pc.onicecandidateerror = () => { result.error = 'ICE server unavailable'; };
            timer = setTimeout(finish, 4000);
            // Bound the offer as well as ICE gathering. No simulated loading delay.
            await waitForCheck(Promise.race([(async () => { await pc.setLocalDescription(await pc.createOffer()); await done; })(), done]), signal);
        } catch (error) {
            if (signal && signal.aborted) throw error;
            result.error = error.name || 'ICE gathering failed';
        } finally {
            clearTimeout(timer);
            if (pc) { pc.onicecandidate = null; pc.onicegatheringstatechange = null; pc.onicecandidateerror = null; pc.close(); }
        }
        return result;
    }

    function recordPublicRtcLeaks(result, httpIp) {
        const httpAddress = parseIpAddress(httpIp);
        if (!httpAddress || httpAddress.scope !== 'global') return;
        for (const family of [4, 6]) {
            // TURN relay IPs belong to the server, not the visitor.
            const leaks = result.candidates.filter(candidate => ['host', 'srflx', 'prflx'].includes(candidate.type)
                && candidate.ip && candidate.ip.family === family && candidate.ip.scope === 'global'
                && candidate.ip.key !== httpAddress.key);
            if (!leaks.length || findings.some(item => item.module === 'WebRTC Additional Address (IPv' + family + ')')) continue;
            applyFinding({
                module: 'WebRTC Additional Address (IPv' + family + ')', category: 'Network & Infrastructure', dimension: 'leak', address_scope: 'public',
                status: 'OBSERVED', severity: 'medium',
                evidence: 'Public ICE candidates: ' + [...new Set(leaks.map(candidate => candidate.address))].join(', ') + ' (HTTP IP ' + httpIp + ')',
                impactText: 'WebRTC exposed a public IPv' + family + ' address different from the HTTP endpoint. This alone does not prove VPN bypass: dual-stack and multi-interface routing can also explain it.',
                mitigation: 'Check VPN IPv4 / IPv6 routing and WebRTC policies. Restrict WebRTC if this address should be masked.'
            });
        }
    }

    async function checkWebRtcStunLeak(httpIp, signal) {
        if (!window.RTCPeerConnection || !serverSignals.iceServers.length) {
            applyFinding({
                module: 'WebRTC Active Check', category: 'Network & Infrastructure', dimension: 'leak',
                status: !window.RTCPeerConnection ? 'UNSUPPORTED' : 'NOT_TESTED', severity: 'low',
                evidence: !window.RTCPeerConnection ? 'WebRTC API unavailable' : 'First-party TLS TURN is not configured; no public STUN request was sent.',
                impactText: 'Active server-reflexive IPv4 / IPv6 testing was not performed. Passive host candidates are checked separately.',
                mitigation: 'Configure first-party TLS TURN for active testing. An unperformed test is not proof of protection.'
            });
            await checkWebRtc(signal);
            return;
        }
        rtcGatheringResult = await gatherIceCandidates(serverSignals.iceServers, signal);
        recordPublicRtcLeaks(rtcGatheringResult, httpIp);
        const comparableHttp = parseIpAddress(httpIp)?.scope === 'global';
        const reflexive = rtcGatheringResult.candidates.some(candidate => candidate.type === 'srflx' && candidate.ip?.scope === 'global');
        const checked = comparableHttp && reflexive && rtcGatheringResult.completed && !rtcGatheringResult.error;
        privacyState.webrtc = checked ? 'complete' : 'not_checked';
        // Describe only measured families. One IPv4 sample says nothing about IPv6.
        privacyState.rtcFamilies = checked ? [...new Set(rtcGatheringResult.candidates
            .filter(candidate => candidate.type === 'srflx' && candidate.ip?.scope === 'global')
            .map(candidate => candidate.ip.family))].sort() : [];
        const additional = findings.some(item => item.dimension === 'leak' && item.address_scope === 'public');
        const status = additional ? 'OBSERVED' : (checked ? 'NO_SIGNAL' : (rtcGatheringResult.error ? 'ERROR' : (rtcGatheringResult.completed ? 'NOT_TESTED' : 'TIMEOUT')));
        applyFinding({
            module: 'WebRTC Active Check', category: 'Network & Infrastructure', dimension: 'leak',
            status, severity: 'low',
            evidence: checked ? 'First-party TLS TURN gathering completed with public server-reflexive candidates.' : 'Active test incomplete: ' + (rtcGatheringResult.error || 'no comparable server-reflexive result'),
            impactText: additional ? 'An additional address was observed; see the IPv4 / IPv6 findings.' : (checked ? 'No additional public address was observed among gathered candidates. Untested address families, routes and DNS are outside this result.' : 'Blocked, timed-out or relay-only results cannot establish that there is no leak.'),
            mitigation: 'No third-party STUN service is contacted. Keep IPv4 and IPv6 covered by your tunnel.'
        });
        updateMatrixCell(els.mWebRtc, status);
        await checkWebRtc(signal);
    }

    async function checkGeoLocation(signal) {
        const needsGeo = serverSignals.latitude === null || serverSignals.latitude === '';
        const needsIsp = !serverSignals.networkAsn && !serverSignals.networkOrg;

        if (needsGeo || needsIsp) {
            const res = await getGeoLookup(signal);
            if (res && res.ok) {
                if (needsGeo && res.country_code) {
                    serverSignals.country = res.country_code || serverSignals.country;
                    serverSignals.countryName = res.country || serverSignals.countryName;
                    serverSignals.city = res.city || serverSignals.city;
                    serverSignals.region = res.region || serverSignals.region;
                    serverSignals.latitude = res.latitude !== undefined ? res.latitude : serverSignals.latitude;
                    serverSignals.longitude = res.longitude !== undefined ? res.longitude : serverSignals.longitude;
                    serverSignals.geoTimezone = res.timezone || serverSignals.geoTimezone;
                    serverSignals.geoAccuracy = res.city ? 'City-level' : 'Country-level (IP-based)';
                }
                if (needsIsp && (res.asn || res.org)) {
                    serverSignals.networkAsn = res.asn || '';
                    serverSignals.networkOrg = res.org || '';
                }
                serverSignals.geoSource = (res.sources && res.sources.length ? res.sources.join(' + ') : serverSignals.geoSource);
                updateGeoDom();
                initMap(serverSignals.latitude, serverSignals.longitude);
            }
            applyFinding({
                module: 'Local Network Data', category: 'Network & Infrastructure',
                status: res && res.ok ? 'READABLE' : 'NO_DATA', severity: 'low',
                evidence: res && res.ok ? 'Source: ' + serverSignals.geoSource : ((res && res.error) || 'Local data unavailable'),
                impactText: 'IP location is an estimate of the visible endpoint, not a physical location. Missing data is not evidence of protection.',
                mitigation: 'No external fallback is used. Local city and ASN databases must be maintained on the server.'
            });
        }
    }

    async function checkLocalhostAndExtensions(signal) {
        const states = [];
        let readable = 0;
        let failed = false;
        if (navigator.permissions && navigator.permissions.query) {
            for (const name of ['geolocation', 'camera', 'microphone']) {
                try {
                    states.push(name + '=' + (await waitForCheck(navigator.permissions.query({ name }), signal)).state);
                    readable++;
                }
                catch (error) {
                    if (signal.aborted) throw error;
                    states.push(name + '=unavailable (' + error.name + ')');
                    if (error.name !== 'NotSupportedError' && error.name !== 'TypeError') failed = true;
                }
            }
        }
        const status = readable ? 'READABLE' : (failed ? 'ERROR' : 'UNSUPPORTED');
        applyFinding({
            module: 'Permission States', category: 'Browser Permissions',
            status, severity: 'low',
            evidence: states.join(', ') || 'Permissions API unavailable',
            impactText: 'Only existing permission states were read. No permission request, extension fetch or local-network probe was made.',
            mitigation: 'Review site permissions in browser settings; camera, microphone and location access are not requested.'
        });
        updateMatrixCell(els.mLocalhost, status);
    }

    async function checkLocalApps() {
        applyFinding({
            module: 'Passive Device Capabilities', category: 'Browser Permissions',
            status: 'NO_SIGNAL', severity: 'low',
            evidence: 'touchPoints=' + (navigator.maxTouchPoints || 0) + ' hardwareConcurrency=' + (navigator.hardwareConcurrency || 'hidden'),
            impactText: 'Navigator capabilities were read without contacting apps, loopback addresses or private-network services.',
            mitigation: 'Installed desktop applications are not inferred by this report.'
        });
        updateMatrixCell(els.mLocalApps, 'NO_SIGNAL');
    }
