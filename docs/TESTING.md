# Testing

Tests live in the host checkout, not in this repository, unless you install them
with `--with-tests`.

## Host regression runner

`php tests/run.php` is Fezadan's source-level regression runner. It reads the
module sources and asserts the security-relevant strings and wiring, for example:

- the save endpoint requires POST and validates payload size;
- saved payloads mask the IP and drop headers;
- the geo trait tries MaxMind then DB-IP and reports the source used;
- the view script `require`s its parts in order;
- `.htaccess` keeps the `sitemap_anonymity` rewrite.

Run it from the host root:

```sh
php tests/run.php
```

It exits non-zero on the first failed assertion group.

## Playwright

The module specs assume the host integration is applied and the app is running at
`http://localhost:8080` (`PLAYWRIGHT_BASE_URL` overrides):

```sh
npx playwright test tests/e2e/anonymity-check.spec.ts
npx playwright test tests/e2e/anonymity-seo-media.spec.ts
```

Both specs are serial by project config (one Chromium worker). Coverage:

- `anonymity-check.spec.ts` — report flow, RTC/IP evidence, timeouts, saved-report
  rendering, keyboard/mobile behaviour, CSP and absence of third-party probes.
  API responses and browser APIs are mocked in the page.
- `anonymity-seo-media.spec.ts` — old path redirects (301 vs 308), canonical and
  structured data, host-specific robots/sitemap, noindex for missing reports, and
  save/reload through the real local endpoints.

`anonymity-seo-media.spec.ts` deletes the saved result it creates from
`storage/anonymity/results_*.json`; `storage/anonymity/` must therefore be writable
by the PHP user and readable by the test process.

## Standalone geo fallback test

`tests/anonymity-geo-fallback.php` is a self-contained assertion script. It stubs
`MaxMind\Db\Reader`, uses a temporary `ROOT`, makes no network requests, and checks
the preferred/fallback database order and source attribution.

```sh
php tests/anonymity-geo-fallback.php
```

Exit code 0 means all assertions passed; failures print the assertion message to
stderr and exit 1. It does not need the Composer autoloader or real `.mmdb` files.

## File checks

```sh
scripts/check.sh                 # module/ in this repository
scripts/check.sh --target /path/to/Fezadan
```

It verifies the expected file set and runs `php -l` when PHP is on `PATH`; a missing
PHP binary warns instead of failing.
