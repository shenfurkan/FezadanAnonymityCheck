import { expect, test } from '@playwright/test';
import { unlink } from 'node:fs/promises';
import path from 'node:path';

const anonymityHost = { Host: 'anonymitycheck.localhost' };
const canonical = 'https://anonymitycheck.fezadan.org/';

test.describe('anonymity origin, crawlability and mobile media', () => {
  test('old production paths redirect directly and preserve API POST requests', async ({ request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    for (const path of ['/anonymitycheck', '/anonymitycheck/', '/tr/anonymitycheck', '/en/anonymity-check']) {
      const response = await request.get(path + '?from=old&x=1', { headers: { Host: 'fezadan.org' }, maxRedirects: 0 });
      expect(response.status(), path).toBe(301);
      expect(response.headers().location).toBe(canonical + '?from=old&x=1');
    }
    const saved = await request.get('/anonymitycheck/results0123456789abcdef0123456789abcdef', { headers: { Host: 'fezadan.org' }, maxRedirects: 0 });
    expect(saved.status()).toBe(301);
    expect(saved.headers().location).toBe(canonical + 'results0123456789abcdef0123456789abcdef');
    const api = await request.post('/anonymitycheck/save', { headers: { Host: 'fezadan.org' }, data: {}, maxRedirects: 0 });
    expect(api.status()).toBe(308);
    expect(api.headers().location).toBe(canonical + 'save');
    const home = await request.get('/en', { headers: { Host: 'fezadan.org' } });
    expect(await home.text()).toContain(`href="${canonical}"`);
  });

  test('subdomain landing is indexable with static content and honest structured data', async ({ request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    const response = await request.get('/', { headers: anonymityHost, maxRedirects: 0 });
    expect(response.status()).toBe(200);
    expect(response.headers()['x-robots-tag'] || '').not.toContain('noindex');
    expect(response.headers()['cache-control']).toContain('no-store');
    const html = await response.text();
    expect(html).toContain(`rel="canonical" href="${canonical}"`);
    expect(html).toContain('name="robots" content="index, follow');
    expect(html).toContain('<h1>Anonymity Check</h1>');
    expect(html).toContain('This tool does not perform a DNS leak test.');
    expect(html).toContain('Frequently asked questions');
    expect(html).not.toContain('data-nosnippet="false"');
    const json = html.match(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/);
    expect(json).not.toBeNull();
    const graph = JSON.parse(json![1])['@graph'];
    expect(graph.find((node: any) => node['@type'] === 'WebApplication').url).toBe(canonical);
    expect(JSON.stringify(graph)).not.toMatch(/aggregateRating|reviewRating|ratingValue/);
    expect(response.headers()['content-security-policy']).toContain("connect-src 'self'");
    expect(response.headers()['content-security-policy']).toContain("frame-ancestors 'none'");
    for (const path of ['/index', '/home', '/en', '/tr']) {
      const alias = await request.get(path, { headers: anonymityHost, maxRedirects: 0 });
      expect(alias.status(), path).toBe(301);
      expect(alias.headers().location).toBe('/');
    }
  });

  test('subdomain robots and sitemap include only the canonical landing page', async ({ request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    const robots = await (await request.get('/robots.txt', { headers: anonymityHost })).text();
    expect(robots).toContain('Sitemap: ' + canonical + 'sitemap_anonymity.xml');
    expect(robots).toContain('Disallow: /save');
    expect(robots).not.toContain('Disallow: /results');
    const response = await request.get('/sitemap.xml', { headers: anonymityHost });
    const xml = await response.text();
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/xml');
    expect([...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => match[1])).toEqual([canonical]);
    expect(xml).not.toContain('changefreq');
    expect(xml).toBe(await (await request.get('/sitemap_anonymity.xml', { headers: anonymityHost })).text());
    expect((await request.get('/sitemap.xml', { headers: { ...anonymityHost, 'If-None-Match': response.headers().etag } })).status()).toBe(304);
  });

  test('missing reports and API responses are not indexable', async ({ request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    for (const prefix of ['', '/anonymitycheck']) {
      const headers = prefix ? {} : anonymityHost;
      const missing = await request.get(prefix + '/results' + '0'.repeat(32), { headers });
      expect(missing.status()).toBe(404);
      expect(missing.headers()['x-robots-tag']).toContain('noindex');
      expect(await missing.text()).toContain('This saved report has expired');
      const api = await request.get(prefix + '/tor-check', { headers });
      expect(api.status()).toBe(200);
      expect(api.headers()['x-robots-tag']).toContain('noindex');
    }
  });

  test('native subdomain report saves and reloads without cross-origin APIs or indexing personal data', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    const origin = new URL(baseURL!);
    origin.hostname = 'anonymitycheck.localhost';
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    let id = '';
    try {
      await page.goto(origin.href);
      const saving = page.waitForResponse(response => new URL(response.url()).pathname === '/save' && response.request().method() === 'POST');
      await page.getByRole('button', { name: 'Run report' }).click();
      const saved = await saving;
      expect(new URL(saved.url()).origin).toBe(origin.origin);
      expect(saved.status()).toBe(200);
      id = (await saved.json()).id;
      expect(id).toMatch(/^[a-f0-9]{32}$/);
      await expect(page).toHaveURL(origin.origin + '/results' + id);
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
      const response = await page.reload();
      expect(response!.headers()['x-robots-tag']).toContain('noindex');
      await expect(page.locator('#cached-alert')).toBeVisible();
      await expect(page.locator('#report-rerun')).toHaveAttribute('href', '/');
      await expect(page.locator('.param-value').first()).toHaveText('Not stored');
      await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(0);
      expect(errors).toEqual([]);
    } finally {
      if (/^[a-f0-9]{32}$/.test(id)) await unlink(path.resolve('storage/anonymity/results_' + id + '.json'));
    }
  });

  test('mobile cover uses matching preload, responsive images and usable footer controls', async ({ page, request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    await page.setViewportSize({ width: 390, height: 844 });
    const failed: string[] = [];
    page.on('pageerror', error => failed.push(error.message));
    await page.goto('/en');
    const image = page.locator('.card-image-wrapper img').first();
    await expect(image).toHaveAttribute('srcset', /\/media\/cover\/.+480w.+768w.+1200w/);
    const srcset = await image.getAttribute('srcset');
    await expect(page.locator('link[rel="preload"][as="image"]')).toHaveAttribute('imagesrcset', srcset!);
    await expect(image).toHaveAttribute('fetchpriority', 'high');
    expect(await image.getAttribute('loading')).not.toBe('lazy');
    await expect.poll(() => image.evaluate((node: HTMLImageElement) => node.complete && node.naturalWidth > 0)).toBe(true);
    const src = (await image.getAttribute('src'))!;
    const response = await request.get(src, { maxRedirects: 0 });
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('image/webp');
    expect((await response.body()).byteLength).toBeLessThan(150000);
    expect(response.headers()['cache-control']).toContain('immutable');
    expect((await request.get(src, { headers: { 'If-None-Match': response.headers().etag } })).status()).toBe(304);
    const head = await request.head(src);
    expect(head.status()).toBe(200);
    expect((await head.body()).byteLength).toBe(0);
    const sizes = await page.locator('.site-footer a').evaluateAll(nodes => nodes.map(node => {
      const rect = node.getBoundingClientRect(); return { width: rect.width, height: rect.height };
    }));
    expect(sizes.every(size => size.width >= 44 && size.height >= 44)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(failed).toEqual([]);
  });

  test('cover endpoint rejects unbounded widths, traversal, stale keys and unsupported methods', async ({ request, baseURL }) => {
    test.skip(!baseURL, 'baseURL is required');
    for (const path of ['/media/cover/25/9999/' + 'a'.repeat(16) + '.webp', '/media/cover/0/480/' + 'a'.repeat(16) + '.webp', '/media/cover/25/480/' + '0'.repeat(16) + '.webp', '/media/cover/25/1200/' + '0'.repeat(16) + '.jpg', '/media/cover/25/1200/' + 'a'.repeat(16) + '.svg', '/media/cover/25/480/not-a-version.webp', '/media/cover/25/480', '/media/cover/25/480/extra/segments']) {
      expect((await request.get(path)).status(), path).toBe(404);
    }
    const post = await request.post('/media/cover/25/480/' + 'a'.repeat(16) + '.webp');
    expect(post.status()).toBe(405);
    expect(post.headers().allow).toBe('GET, HEAD');
  });
});
