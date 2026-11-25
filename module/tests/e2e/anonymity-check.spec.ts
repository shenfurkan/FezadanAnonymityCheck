import { expect, test, type Page } from '@playwright/test';

type IceCandidate = { address: string; type: 'srflx' | 'host' | 'relay' };
type AnonymityOptions = {
  activeCandidates?: IceCandidate[];
  hostCandidates?: IceCandidate[];
  httpIp?: string;
  initialTor?: boolean;
  apiTor?: boolean | null;
  trustedEdge?: boolean;
  networkOrg?: string;
  country?: string;
  timezone?: string;
  noIceServer?: boolean;
  rtcFailure?: boolean;
  rtcOfferHang?: boolean;
  disableRtc?: boolean;
  hangBrowserApis?: boolean;
  lateBattery?: boolean;
  geoTimeout?: boolean;
  geoUnavailable?: boolean;
  geoDelayMs?: number;
  proxyHeaders?: string[];
  unavailableBrowserApis?: boolean;
  emptySpeechVoices?: boolean;
  torTimeout?: boolean;
};

async function installAnonymityMocks(page: Page, options: AnonymityOptions = {}): Promise<void> {
  await page.addInitScript(options => {
    class FakeRTCPeerConnection {
      onicecandidate: ((event: { candidate: { candidate: string } | null }) => void) | null = null;
      private readonly hasStun: boolean;

      constructor(config?: { iceServers?: Array<{ urls?: string | string[] }> }) {
        this.hasStun = Boolean(config?.iceServers?.length);
      }

      createDataChannel() {}

      async createOffer() {
        if (options.rtcOfferHang) return new Promise(() => {});
        if (options.rtcFailure) throw new DOMException('WebRTC blocked', 'NotAllowedError');
        return { type: 'offer', sdp: 'fake-offer' };
      }

      async setLocalDescription() {
        setTimeout(() => {
          const candidates = this.hasStun
            ? (options.activeCandidates ?? [{ address: '88.238.63.102', type: 'srflx' }])
            : (options.hostCandidates ?? [{ address: 'masked-host.local', type: 'host' }]);
          candidates.forEach(({ address, type }) => {
            this.onicecandidate?.({
              candidate: { candidate: `candidate:0 1 udp 2122260223 ${address} 54400 typ ${type}` },
            });
          });
          this.onicecandidate?.({ candidate: null });
        }, 20);
      }

      close() { document.documentElement.dataset.rtcClosed = 'true'; }
    }

    Object.defineProperty(window, 'RTCPeerConnection', {
      configurable: true,
      value: options.disableRtc ? undefined : FakeRTCPeerConnection,
    });

    Object.defineProperty(navigator, 'userAgentData', {
      configurable: true,
      value: {
        brands: [{ brand: 'Chromium', version: '126' }],
        mobile: false,
        platform: 'Windows',
        getHighEntropyValues: async () => {
          if (options.hangBrowserApis) return new Promise(() => {});
          return {
            architecture: 'x86', bitness: '64', model: '', platformVersion: '15.0.0',
            fullVersionList: [{ brand: 'Chromium', version: '126.0.0.0' }],
          };
        },
      },
    });

    if (options.hangBrowserApis) {
      Object.defineProperty(navigator, 'getBattery', { configurable: true, value: () => new Promise(() => {}) });
      Object.defineProperty(navigator.permissions, 'query', { configurable: true, value: () => new Promise(() => {}) });
      Object.defineProperty(OfflineAudioContext.prototype, 'startRendering', { configurable: true, value: () => new Promise(() => {}) });
    } else if (options.lateBattery) {
      Object.defineProperty(navigator, 'getBattery', {
        configurable: true,
        value: () => new Promise(resolve => setTimeout(() => resolve({ level: 0.5, charging: false }), 2600)),
      });
    }

    if (options.unavailableBrowserApis) {
      Object.defineProperty(navigator.permissions, 'query', {
        configurable: true,
        value: () => Promise.reject(new DOMException('Permission query not supported', 'NotSupportedError')),
      });
      Object.defineProperty(HTMLCanvasElement.prototype, 'getContext', { configurable: true, value: () => null });
      Object.defineProperty(window, 'localStorage', {
        configurable: true,
        get() { throw new DOMException('Storage is restricted', 'SecurityError'); },
      });
    }

    Object.defineProperty(navigator, 'connection', {
      configurable: true,
      value: {
        effectiveType: '4g',
        downlink: 42,
        rtt: 25,
        saveData: false,
      },
    });

    Object.defineProperty(navigator, 'globalPrivacyControl', {
      configurable: true,
      value: true,
    });

    Object.defineProperty(navigator, 'maxTouchPoints', {
      configurable: true,
      value: 5,
    });

    Object.defineProperty(navigator, 'plugins', {
      configurable: true,
      value: [
        { name: 'Chrome PDF Viewer' },
        { name: 'Widevine Content Decryption Module' },
        { name: 'Native Client' },
        { name: 'Test Plugin' },
      ],
    });

    Object.defineProperty(navigator, 'mimeTypes', {
      configurable: true,
      value: [
        { type: 'application/pdf' },
        { type: 'application/x-google-chrome-pdf' },
      ],
    });

    const voiceEvents = new EventTarget();
    Object.defineProperty(window, 'speechSynthesis', {
      configurable: true,
      value: {
        addEventListener: (name: string, callback: EventListener) => {
          document.documentElement.dataset.voiceListener = 'attached';
          voiceEvents.addEventListener(name, callback);
        },
        removeEventListener: (name: string, callback: EventListener) => {
          document.documentElement.dataset.voiceListener = 'removed';
          voiceEvents.removeEventListener(name, callback);
        },
        getVoices: () => options.emptySpeechVoices ? [] : [
          { lang: 'tr-TR', name: 'Turkish Voice' },
          { lang: 'en-US', name: 'English Voice' },
          { lang: 'de-DE', name: 'German Voice' },
        ],
      },
    });

    window.matchMedia = (query: string) => ({
      matches: query.includes('prefers-color-scheme: dark') || query.includes('pointer: coarse'),
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    });
  }, options);
}

async function installAnonymityRoutes(page: Page, options: AnonymityOptions = {}): Promise<void> {
  // Keep the real nonce/CSP and view; replace only server signals for deterministic network fixtures.
  await page.route('**/anonymitycheck/', async route => {
    const response = await route.fetch();
    let body = await response.text();
    const signals: Record<string, unknown> = {
      ip: options.httpIp ?? '1.1.1.1',
      isTor: options.initialTor ?? false,
      torSource: options.initialTor ? 'cloudflare' : '',
      trustedEdge: options.trustedEdge ?? false,
      iceServers: options.noIceServer ? [] : [{ urls: 'turns:turn.fezadan.org:5349?transport=tcp', username: 'test', credential: 'test' }],
      proxyCount: options.proxyHeaders?.length ?? 0,
      proxyHeaders: options.proxyHeaders ?? [],
      headers: { HTTP_ACCEPT_LANGUAGE: 'tr-TR,tr;q=0.9' },
    };
    for (const [key, value] of Object.entries(signals)) {
      body = body.replace(new RegExp(`^        ${key}: .*[,}]?$`, 'm'), `        ${key}: ${JSON.stringify(value)},`);
    }
    await route.fulfill({ response, body });
  });
  await page.route('**/anonymitycheck/geo-lookup', async route => {
    if (options.geoTimeout) return; // Leave the request pending; the browser must cancel it.
    if (options.geoDelayMs) await new Promise(resolve => setTimeout(resolve, options.geoDelayMs));
    await route.fulfill({
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify({
        ok: !options.geoUnavailable,
        sources: ['dbip_city', 'dbip_asn'],
        country_code: options.country ?? 'TR',
        country: options.country === 'US' ? 'United States' : 'Turkey',
        city: 'Istanbul',
        region: 'Istanbul',
        latitude: 41.0082,
        longitude: 28.9784,
        timezone: options.timezone ?? 'Europe/Istanbul',
        org: options.geoUnavailable ? '' : (options.networkOrg ?? 'Test ISP'),
        asn: options.geoUnavailable ? '' : 'AS64500',
        error: options.geoUnavailable ? 'Local geolocation databases unavailable' : null,
      }),
    });
  });

  await page.route('**/anonymitycheck/tor-check', async route => {
    if (options.torTimeout) return;
    await route.fulfill({
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify({ ok: options.apiTor !== null, is_tor: options.apiTor ?? false, source: 'local_exit_list' }),
    });
  });

  await page.route('**/anonymitycheck/save', async route => {
    await route.fulfill({
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify({ ok: true, id: '0123456789abcdef0123456789abcdef' }),
    });
  });

}

async function runReport(page: Page) {
  const saved = page.waitForRequest(request => request.url().endsWith('/anonymitycheck/save') && request.method() === 'POST');
  await page.goto('/anonymitycheck/');
  await page.getByRole('button', { name: 'Run report' }).click();
  await expect(page.locator('#dashboard-wrapper')).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('#dashboard-wrapper')).toHaveAttribute('aria-busy', 'false', { timeout: 12_000 });
  return (await saved).postDataJSON();
}

test.describe('anonymity check report', () => {
  test.beforeEach(({ baseURL }) => { test.skip(!baseURL, 'baseURL is required'); });
  test('runs an evidence report, shows additional RTC addresses, and saves a share URL', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');

    await installAnonymityMocks(page);
    await installAnonymityRoutes(page);

    const report = await runReport(page);
    expect(report.risk_model).toBe(3);
    expect(report).not.toHaveProperty('score');
    expect(report).not.toHaveProperty('ip');
    expect(report).not.toHaveProperty('headers');
    expect(report.findings.every((finding: object) => !('penalty' in finding) && !('confidence' in finding))).toBe(true);

    await expect(page.locator('.param-item', { hasText: 'ISP / ASN' })).toContainText('Test ISP / AS64500');
    await expect(page.locator('.geo-card')).toContainText('Istanbul');
    await expect(page.locator('#map')).toContainText(/./, { timeout: 10_000 });

    await expect(page.locator('#findings-container')).toBeVisible();
    await expect(page.locator('#findings-tbody')).toContainText('WebRTC Additional Address');
    await expect(page.locator('#findings-tbody')).toContainText('88.238.63.102');
    await expect(page.locator('#findings-tbody')).toContainText('UA Client Hints');
    await expect(page.locator('#findings-tbody')).toContainText('Plugin & MIME Enumeration');
    await expect(page.locator('#findings-tbody')).toContainText('HTTP Header Profile');

    await expect(page.locator('#m-uach')).not.toHaveText('Pending');
    await expect(page.locator('#m-screen')).not.toHaveText('Pending');
    await expect(page.locator('#m-plugins')).not.toHaveText('Pending');
    await expect(page.locator('#m-connection')).not.toHaveText('Pending');
    await expect(page.locator('#m-privacy')).not.toHaveText('Pending');

    await expect(page).toHaveURL(/\/anonymitycheck\/results0123456789abcdef0123456789abcdef$/);
  });

  test('keeps the report usable when geo lookup fails', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');

    await installAnonymityMocks(page);
    await page.route('**/anonymitycheck/geo-lookup', async route => {
      await route.fulfill({ status: 429, contentType: 'application/json', body: JSON.stringify({ ok: false, error: 'Too many requests' }) });
    });
    await page.route('**/anonymitycheck/tor-check', async route => {
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, is_tor: false }) });
    });
    await page.route('**/anonymitycheck/save', async route => {
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, id: 'fedcba9876543210fedcba9876543210' }) });
    });
    await runReport(page);

    await expect(page.locator('#dashboard-wrapper')).toBeVisible();
    await expect(page.locator('#findings-container')).toBeVisible();
    await expect(page.locator('#map')).toContainText(/Location unavailable|Coordinates unavailable/, { timeout: 10_000 });
    await expect(page).toHaveURL(/\/anonymitycheck\/resultsfedcba9876543210fedcba9876543210$/);
  });

  test('uses a verified page Tor signal without a redundant API lookup', async ({ page }) => {
    let torRequests = 0;
    page.on('request', request => { if (request.url().endsWith('/tor-check')) torRequests++; });
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { initialTor: true, trustedEdge: true });
    const report = await runReport(page);
    await expect(page.locator('#m-tor')).toHaveAttribute('data-status', 'OBSERVED');
    await expect(page.locator('#summary-network')).toHaveText('Tor exit observed');
    expect(report.is_tor).toBe(true);
    expect(report.vector.network).toBe('anonymous');
    expect(report.tor_source).toBe('cloudflare');
    expect(torRequests).toBe(0);
  });

  test('propagates a positive visitor exit-list result into the summary and saved report', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { apiTor: true });
    const report = await runReport(page);
    expect(report.is_tor).toBe(true);
    expect(report.vector.network).toBe('anonymous');
    await expect(page.locator('#summary-network')).toHaveText('Tor exit observed');
  });

  test('reports additional IPv4 and IPv6 without claiming critical VPN bypass', async ({ page }) => {
    await installAnonymityMocks(page, { activeCandidates: [
      { address: '88.238.63.102', type: 'srflx' },
      { address: '2606:4700:4700::1111', type: 'srflx' },
    ] });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    for (const family of [4, 6]) {
      const finding = page.locator('.finding-card', { hasText: `WebRTC Additional Address (IPv${family})` });
      await expect(finding).toHaveClass(/severity-medium/);
      await expect(finding).toContainText('does not prove VPN bypass');
    }
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'OBSERVED');
    await expect(page.locator('#summary-priority')).toHaveText('Additional public address');
    await expect(page.locator('#assessment-title')).toHaveText('Review an additional public address');
    await expect(page.locator('#assessment-summary')).toContainText('not, by itself, proof');
    await expect(page.locator('#assessment-actions')).toContainText('IPv4/IPv6 tunnel coverage');
    expect(report.vector.public_address_observed).toBe(true);
  });

  test('normalizes IPv6 text and ignores TURN relay, documentation and invalid addresses', async ({ page }) => {
    await installAnonymityMocks(page, { activeCandidates: [
      { address: '2606:4700:4700::1111', type: 'srflx' },
      { address: '2606:4700:4700::2222', type: 'relay' },
      { address: '2001:db8::1', type: 'srflx' },
      { address: 'ff02::1', type: 'host' },
      { address: '2606::4700::1', type: 'srflx' },
      { address: '1:2:3:4:5:6:7:8:9', type: 'srflx' },
      { address: '999.1.2.3', type: 'srflx' },
      { address: '01.1.1.1', type: 'srflx' },
    ] });
    await installAnonymityRoutes(page, { httpIp: '2606:4700:4700:0000:0000:0000:0000:1111' });
    const report = await runReport(page);
    expect(report.findings.filter((finding: { severity: string }) => finding.severity === 'critical')).toHaveLength(0);
    expect(report.vector.leak).toBe('No additional address observed');
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'NO_SIGNAL');
    await expect(page.locator('#summary-leak-note')).toContainText('DNS leaks are not tested');
  });

  test('normalizes IPv4-mapped IPv6 rather than reporting the HTTP address as a leak', async ({ page }) => {
    await installAnonymityMocks(page, { activeCandidates: [{ address: '::FFFF:1.1.1.1', type: 'srflx' }] });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    expect(report.vector.leak).toBe('No additional address observed');
    expect(report.findings.some((finding: { module: string }) => finding.module.startsWith('WebRTC Additional Address'))).toBe(false);
  });

  test('detects passive global IPv6 host leaks with no configured STUN or TURN', async ({ page }) => {
    await installAnonymityMocks(page, { hostCandidates: [{ address: '2606:4700:4700::1111', type: 'host' }] });
    await installAnonymityRoutes(page, { noIceServer: true });
    const report = await runReport(page);
    expect(report.vector.leak).toBe('Additional public address');
    await expect(page.locator('.finding-card', { hasText: 'WebRTC Additional Address (IPv6)' })).toHaveClass(/severity-medium/);
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'OBSERVED');
  });

  for (const fixture of [{ name: 'failed', rtcFailure: true }, { name: 'relay-only', activeCandidates: [{ address: '2606:4700:4700::2222', type: 'relay' as const }] }]) {
    test(`does not present a ${fixture.name} active test as protection`, async ({ page }) => {
      await installAnonymityMocks(page, fixture);
      await installAnonymityRoutes(page);
      const report = await runReport(page);
      expect(report.vector.leak).toBe('Not fully checked');
      expect(report.vector).not.toHaveProperty('leak_points');
      expect(report.vector.webrtc).not.toBe('complete');
      await expect(page.locator('#findings-tbody')).not.toContainText('WebRTC is not leaking');
    });
  }

  test('does not infer direct access or assign scores from an ordinary ISP or locale mismatch', async ({ page }) => {
    const clean = { activeCandidates: [{ address: '1.1.1.1', type: 'srflx' as const }] };
    await installAnonymityMocks(page, clean);
    await installAnonymityRoutes(page, { networkOrg: 'Turk Telekom' });
    const direct = await runReport(page);
    expect(direct.vector.network).toBe('unknown');
    expect(direct).not.toHaveProperty('score');
    await expect(page.locator('#summary-network')).toHaveText('Not established');
    await expect(page.locator('#summary-fingerprint')).not.toContainText(/Unique|Uncommon|Standard/);

    const vpnPage = await page.context().newPage();
    await installAnonymityMocks(vpnPage, clean);
    await installAnonymityRoutes(vpnPage, { networkOrg: 'Mullvad VPN', country: 'US', timezone: 'America/New_York' });
    const vpn = await runReport(vpnPage);
    expect(vpn.vector.network).toBe('masked');
    expect(vpn).not.toHaveProperty('score');
    await expect(vpnPage.locator('#summary-network')).toHaveText('VPN / proxy indicator');
    for (const module of ['Timezone Mismatch', 'Lang-Geo Mismatch']) {
      const finding = vpn.findings.find((finding: { module: string }) => finding.module === module);
      expect(finding).toBeDefined();
      expect(finding).not.toHaveProperty('penalty');
      expect(finding.impactText).toContain('not evidence of VPN use');
    }
    await vpnPage.close();
  });

  test('keeps CDN infrastructure distinct from visitor masking', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { trustedEdge: true, networkOrg: 'Turk Telekom' });
    const report = await runReport(page);
    expect(report.vector.network).toBe('unknown');
    await expect(page.locator('#summary-network')).not.toHaveText('CDN edge');
  });

  test('does not infer visitor VPN use from untrusted routing headers', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { proxyHeaders: ['HTTP_X_FORWARDED_FOR'], networkOrg: 'Turk Telekom' });
    const report = await runReport(page);
    expect(report.vector.network).toBe('unknown');
    await expect(page.locator('#m-proxy')).toHaveAttribute('data-status', 'READABLE');
    await expect(page.locator('.finding-card', { hasText: 'Proxy Headers' })).toContainText('site infrastructure');
    await expect(page.locator('#summary-network')).toHaveText('Not established');
  });

  test('shows independent results while a slow location check is still pending', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { geoDelayMs: 1800 });
    const saved = page.waitForRequest(request => request.url().endsWith('/anonymitycheck/save'));
    await page.goto('/anonymitycheck/');
    await page.getByRole('button', { name: 'Run report' }).click();
    await expect(page.locator('#dashboard-wrapper')).toBeVisible({ timeout: 1000 });
    await expect(page.locator('#m-screen')).toHaveAttribute('data-status', 'READABLE', { timeout: 1000 });
    await expect(page.locator('#m-isp')).toHaveText('Pending');
    await expect(page.locator('#dashboard-wrapper')).toHaveAttribute('aria-busy', 'true');
    const report = (await saved).postDataJSON();
    expect(report.checks.every((check: { status: string }) => check.status === 'FINISHED')).toBe(true);
  });

  for (const failure of ['unavailable', 'timeout'] as const) {
    test(`keeps missing ASN and ${failure} Tor/geo data unknown, without retrying geo`, async ({ page }) => {
      let geoRequests = 0;
      page.on('request', request => { if (request.url().endsWith('/geo-lookup')) geoRequests++; });
      await installAnonymityMocks(page);
      await installAnonymityRoutes(page, {
        apiTor: null, geoUnavailable: failure === 'unavailable', geoTimeout: failure === 'timeout',
      });
      const report = await runReport(page);
      expect(geoRequests).toBe(1);
      expect(report.is_tor).toBeNull();
      expect(report.vector.network).toBe('unknown');
      expect(report.duration_ms).toBeLessThan(8000);
      await expect(page.locator('#m-isp')).toHaveAttribute('data-status', 'NO_DATA');
      await expect(page.locator('#m-tor')).toHaveAttribute('data-status', 'NO_DATA');
      await expect(page.locator('#summary-network')).toHaveText('Not established');
      await expect(page.locator('#m-dns')).toHaveAttribute('data-status', 'NOT_TESTED');
    });
  }

  test('bounds hung browser APIs and retains observations completed before a timeout', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await installAnonymityMocks(page, { hangBrowserApis: true });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    expect(report.duration_ms).toBeLessThan(8000);
    for (const id of ['uach', 'audio', 'localhost']) {
      await expect(page.locator('#m-' + id)).toHaveAttribute('data-status', 'TIMEOUT');
    }
    await expect(page.locator('#m-webgpu')).not.toHaveText('Pending');
    const deviceCheck = report.checks.find((check: { name: string }) => check.name === 'Device APIs');
    expect(deviceCheck.status).toBe('TIMEOUT');
    expect(errors).toEqual([]);
  });

  test('does not let a late API result change the completed or saved report', async ({ page }) => {
    await installAnonymityMocks(page, { lateBattery: true });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    expect(report.findings.some((finding: { module: string }) => finding.module === 'Battery API')).toBe(false);
    const before = await page.locator('#findings-tbody').textContent();
    await page.waitForTimeout(800); // The deliberately late battery fixture resolves after its deadline.
    expect(await page.locator('#findings-tbody').textContent()).toBe(before);
  });

  test('bounds a hung RTC offer and never reports it as a completed leak test', async ({ page }) => {
    await installAnonymityMocks(page, { rtcOfferHang: true });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    expect(report.vector.webrtc).toBe('not_checked');
    expect(report.vector.leak).toBe('Not fully checked');
    expect(report.duration_ms).toBeLessThan(8000);
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'TIMEOUT');
    await expect(page.locator('html')).toHaveAttribute('data-rtc-closed', 'true');
  });

  test('keeps a timed-out Tor lookup unknown rather than reporting a negative', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { torTimeout: true });
    const report = await runReport(page);
    expect(report.is_tor).toBeNull();
    expect(report.vector.network).toBe('unknown');
    await expect(page.locator('#m-tor')).toHaveAttribute('data-status', 'TIMEOUT');
  });

  test('distinguishes unsupported APIs and unreadable storage from no findings', async ({ page }) => {
    await installAnonymityMocks(page, { unavailableBrowserApis: true });
    await installAnonymityRoutes(page);
    const report = await runReport(page);
    for (const id of ['canvas', 'webgl', 'fonts', 'localhost']) {
      await expect(page.locator('#m-' + id)).toHaveAttribute('data-status', 'UNSUPPORTED');
    }
    const storage = report.findings.find((finding: { module: string }) => finding.module === 'Local Storage & Cookies');
    expect(storage.status).toBe('ERROR');
    expect(storage.evidence).toContain('SecurityError');
  });

  test('shows missing voice data and removes the voiceschanged listener after the bounded wait', async ({ page }) => {
    await installAnonymityMocks(page, { emptySpeechVoices: true });
    await installAnonymityRoutes(page);
    await runReport(page);
    await expect(page.locator('#m-speech')).toHaveAttribute('data-status', 'NO_DATA');
    await expect(page.locator('html')).toHaveAttribute('data-voice-listener', 'removed');
  });

  test('stops pending checks when leaving the page without saving or mutating late findings', async ({ page }) => {
    await installAnonymityMocks(page, { hangBrowserApis: true, rtcOfferHang: true });
    await installAnonymityRoutes(page, { geoTimeout: true, torTimeout: true });
    const saved: string[] = [];
    page.on('request', request => { if (request.url().endsWith('/save')) saved.push(request.url()); });
    await page.goto('/anonymitycheck/');
    await page.getByRole('button', { name: 'Run report' }).click();
    await expect(page.locator('#m-screen')).toHaveAttribute('data-status', 'READABLE');
    await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
    await expect(page.locator('#dashboard-wrapper')).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('#score-verdict-text')).toHaveText('Report stopped');
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'CANCELLED');
    await expect(page.locator('html')).toHaveAttribute('data-rtc-closed', 'true');
    await expect(page.locator('#report-save-status')).toContainText('was not saved');
    expect(saved).toEqual([]);
  });

  test('keeps the intro scrollable in a short mobile viewport and moves keyboard focus into results', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 320 });
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page);
    await page.goto('/anonymitycheck/');
    expect(await page.locator('body').evaluate(el => getComputedStyle(el).overflowY)).not.toBe('hidden');
    expect(await page.locator('.brand-mark').evaluate(el => getComputedStyle(el).position)).toBe('static');
    const button = page.getByRole('button', { name: 'Run report' });
    await button.focus();
    await button.press('Enter');
    await expect(page.locator('#score-verdict-text')).toBeFocused();
    // The landing page owns h1; the report status is a section heading, with focus preserved.
    await expect(page.getByRole('heading', { level: 2, name: /report/i })).toHaveAttribute('id', 'score-verdict-text');
    await expect(page.locator('#dashboard-wrapper')).toHaveAttribute('aria-busy', 'false');
  });

  test('distinguishes an unavailable RTC API from a verified clean connection', async ({ page }) => {
    await installAnonymityMocks(page, { disableRtc: true });
    await installAnonymityRoutes(page, { noIceServer: true });
    const report = await runReport(page);
    expect(report.vector.webrtc).toBe('disabled');
    await expect(page.locator('#summary-priority')).toHaveText('WebRTC unavailable');
    await expect(page.locator('#m-webrtc')).toHaveAttribute('data-status', 'UNSUPPORTED');
    expect(report).not.toHaveProperty('score');
  });

  test('makes saving failure visible without losing the local report', async ({ page }) => {
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page);
    await page.route('**/anonymitycheck/save', route => route.fulfill({ status: 429, contentType: 'application/json', body: '{"ok":false}' }));
    await runReport(page);
    await expect(page.locator('#report-save-status')).toContainText('could not be saved');
    await expect(page.locator('#findings-container')).toBeVisible();
    await expect(page).toHaveURL(/\/anonymitycheck\/$/);
  });

  test('sends no third-party, extension or loopback probes and keeps nonce CSP intact', async ({ page, baseURL }) => {
    const requests: string[] = [];
    const errors: string[] = [];
    page.on('request', request => requests.push(request.url()));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text() + ' ' + message.location().url); });
    page.on('pageerror', error => errors.push(error.message));
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { noIceServer: true });
    await runReport(page);
    expect(requests.every(url => new URL(url).origin === new URL(baseURL!).origin)).toBe(true);
    expect(requests.some(url => /chrome-extension:|127\.0\.0\.1|flagcdn|openstreetmap|google/.test(url))).toBe(false);
    expect(errors).toEqual([]);
    const response = await page.request.get('/anonymitycheck/tor-check');
    expect(response.ok()).toBe(true);
    const tor = await response.json();
    expect(tor).not.toHaveProperty('ip');
    const csp = response.headers()['content-security-policy'];
    expect(csp).toContain("connect-src 'self';");
    expect(csp).not.toContain('127.0.0.1');
    expect(csp).toContain("script-src 'self' 'nonce-");
  });

  test('runs a native host-only report on mobile without overflow or local-network requests', async ({ page, baseURL }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const errors: string[] = [];
    const requests: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text() + ' ' + message.location().url); });
    page.on('request', request => requests.push(request.url()));
    await installAnonymityRoutes(page, { noIceServer: true });
    const report = await runReport(page);
    expect(report.risk_model).toBe(3);
    expect(report).not.toHaveProperty('score');
    expect(report.vector.webrtc).not.toBe('complete');
    await expect(page.locator('#m-webrtc')).not.toHaveText(/Clear|Protected/);
    expect(await page.locator('.brand-mark').evaluate(el => getComputedStyle(el).position)).toBe('static');
    expect(requests.every(url => new URL(url).origin === new URL(baseURL!).origin)).toBe(true);
    expect(errors).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  });

  test('saves and reloads the vector without nested addresses or executable report strings', async ({ page, request }) => {
    const response = await request.post('/anonymitycheck/save', { data: {
      ip: '88.238.63.102', headers: { HTTP_COOKIE: 'secret-cookie' },
      is_tor: true, tor_source: 'local_exit_list', risk_model: 3,
      vector: { network: 'anonymous', webrtc: 'complete' },
      matrix: { WebRtc: 'OBSERVED', WebGL: 'READABLE', WebGpu: 'UNSUPPORTED', Uach: 'TIMEOUT' },
      checks: [{ name: 'WebRTC', status: 'FINISHED', duration_ms: 20 }], duration_ms: 20,
      findings: [{ module: 'WebRTC Additional Address (IPv6)', category: 'Network & Infrastructure', dimension: 'leak', address_scope: 'public',
        status: 'OBSERVED', severity: 'medium',
        evidence: '2606:4700:4700::1111 and 88.238.63.102 </script><script>window.reportInjected=true</script>',
      }],
    } });
    expect(response.ok()).toBe(true);
    const { id } = await response.json();
    const outbound: string[] = [];
    page.on('request', req => { if (/\/(geo-lookup|tor-check|save)$/.test(req.url())) outbound.push(req.url()); });
    await page.goto('/anonymitycheck/results' + id);
    await expect(page.locator('#cached-alert')).toBeVisible();
    await expect(page.locator('#summary-network')).toHaveText('Tor exit observed');
    await expect(page.locator('#summary-priority')).toHaveText('Additional public address');
    await expect(page.locator('#findings-tbody')).toContainText('[IP redacted]');
    await expect(page.locator('#findings-tbody')).not.toContainText('2606:4700:4700::1111');
    expect(await page.evaluate(() => 'reportInjected' in window)).toBe(false);
    expect(await page.content()).not.toContain('secret-cookie');
    await expect(page.locator('#score-display')).toHaveText('1 / 1');
    for (const [id, status] of Object.entries({ webrtc: 'OBSERVED', webgl: 'READABLE', webgpu: 'UNSUPPORTED', uach: 'TIMEOUT', canvas: 'NOT_TESTED' })) {
      await expect(page.locator('#m-' + id)).toHaveAttribute('data-status', status);
    }
    expect(outbound).toEqual([]);
  });

  test('reads old saved reports without presenting their score or classifications as current results', async ({ page, request }) => {
    const response = await request.post('/anonymitycheck/save', { data: {
      risk_model: 2, score: 99, vector: { network: 'direct', webrtc: 'complete' },
      findings: [{ module: 'Old Canvas', category: 'Browser Fingerprinting', status: 'EXPOSED', penalty: 8 }],
    } });
    expect(response.ok()).toBe(true);
    const { id } = await response.json();
    await page.goto('/anonymitycheck/results' + id);
    await expect(page.locator('#score-verdict-text')).toHaveText('Legacy report — rerun');
    await expect(page.locator('#summary-network')).toHaveText('Legacy — rerun');
    await expect(page.locator('#score-display')).toHaveText('—');
    await expect(page.locator('#findings-tbody')).toContainText('Old Canvas');
    await expect(page.locator('#m-canvas')).toHaveAttribute('data-status', 'NOT_TESTED');
    await expect(page.locator('#report-rerun')).toHaveAttribute('href', '/anonymitycheck/');
    await expect(page.locator('#assessment-title')).toHaveText('Run a new report');
    await expect(page.locator('#assessment-actions li')).toHaveCount(0);
  });

  test('a host-only report explains its limits and does not confuse ended tasks with passed tests', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { noIceServer: true });
    const report = await runReport(page);
    expect(report.vector.webrtc).toBe('host_only');
    await expect(page.locator('#assessment-title')).toHaveText('Address testing is incomplete');
    await expect(page.locator('#assessment-summary')).toContainText('host-candidate check');
    await expect(page.locator('#assessment-gaps')).toContainText('DNS resolver routing is not tested');
    await expect(page.locator('#report-coverage')).toContainText('not tested or unavailable');
    await expect(page.locator('.report-count')).toContainText('not tests passed');
    await page.getByText('Who can observe what? Read the threat model', { exact: true }).click();
    await expect(page.locator('.report-methodology dd').last()).toBeVisible();
    await expect(page.locator('.report-methodology')).toContainText('traffic correlation');
  });

  for (const family of [4, 6]) {
    test(`an IPv${family}-only active sample leaves the other family explicitly unmeasured`, async ({ page, baseURL }) => {
      test.skip(!baseURL, 'baseURL is required');
      const address = family === 4 ? '1.1.1.1' : '2606:4700:4700::1111';
      await installAnonymityMocks(page, { activeCandidates: [{ address, type: 'srflx' }] });
      await installAnonymityRoutes(page, { httpIp: address });
      const report = await runReport(page);
      expect(report.vector.rtc_families).toEqual([family]);
      await expect(page.locator('#assessment-title')).toHaveText('No additional address in the sampled route');
      await expect(page.locator('#assessment-gaps')).toContainText(`IPv${family === 4 ? 6 : 4} has no completed public server-reflexive sample`);
      await expect(page.locator('#summary-leak-note')).toContainText(`samples: IPv${family}`);
    });
  }

  test('local address exposure gets a local-network explanation, not a public identity claim', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    await installAnonymityMocks(page, { hostCandidates: [{ address: '192.168.1.42', type: 'host' }] });
    await installAnonymityRoutes(page, { noIceServer: true });
    await runReport(page);
    await expect(page.locator('#assessment-title')).toHaveText('Local network addressing is visible');
    await expect(page.locator('#assessment-summary')).toContainText('not a public ISP identity');
    await expect(page.locator('#assessment-actions')).toContainText('mDNS masking');
    await expect(page.locator('#assessment-gaps')).toContainText('Only passive host candidates');
  });

  test('technical evidence is keyboard-accessible and stays open as independent checks finish', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    await page.setViewportSize({ width: 390, height: 844 });
    await installAnonymityMocks(page);
    await installAnonymityRoutes(page, { geoDelayMs: 1800 });
    await page.goto('/anonymitycheck/');
    await page.getByRole('button', { name: 'Run report' }).click();
    const card = page.locator('.finding-card', { hasText: 'Screen Properties' });
    const evidence = card.locator('details');
    await expect(evidence).not.toHaveAttribute('open', '');
    await evidence.locator('summary').focus();
    await evidence.locator('summary').press('Enter');
    await expect(evidence).toHaveAttribute('open', '');
    await expect(page.locator('#dashboard-wrapper')).toHaveAttribute('aria-busy', 'false');
    await expect(evidence).toHaveAttribute('open', '');
    await expect(evidence.locator('summary')).toBeFocused();
    await expect(evidence.locator('.finding-evidence')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/anonymity-report-mobile.png', fullPage: true });
  });
});
