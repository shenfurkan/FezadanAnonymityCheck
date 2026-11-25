# Host integration

This repository is a **module for Fezadan**, not a standalone application. It has no
bootstrap, router, database layer or site shell of its own. The install script only
copies files; the edits below make the host route and render them.

The `*.snippet.php` files below are copy/paste fragments, not runnable scripts.

Apply in this order.

## 1. Copy the module

```sh
scripts/install.sh --target /path/to/Fezadan
# include tests/e2e and tests/anonymity-geo-fallback.php:
scripts/install.sh --target /path/to/Fezadan --with-tests
```

The target must contain `app/Core/App.php`. Existing files with the same paths are
overwritten.

## 2. Routing — `app/Core/App.php`

Open `app-routing.snippet.php` and apply its four edits:

1. Add `AnonymityCheckController` to `ROUTABLE_ACTIONS`; add `sitemapAnonymity` to
   the `SeoController` entry.
2. Add the `sitemap_anonymity.xml` branch to the sitemap dispatch.
3. Add the `anonymitycheck.` host branch before `redirectAnonymityPath()`.
4. Paste `routeAnonymityCheck()` and `redirectAnonymityPath()`.

A controller method that is not in `ROUTABLE_ACTIONS` returns 404.

## 3. Config — `app/Config/config.php`

Apply `config.snippet.php`:

- add `anonymitycheck` to the `$mainHost` regex alternation;
- define `ANONYMITY_SITE_URL` and `anonymityUrl()`.

Set `ANONYMITY_TURN_URL` / `ANONYMITY_TURN_SECRET` in `.env` only if first-party
TURN is available. Without them the page reports active WebRTC checks as unavailable.

## 4. CSP — `public_html/index.php`

Apply `csp.snippet.php`. The anonymity host gets `frame-ancestors 'none'`,
`frame-src 'none'`, `img-src 'self' data:` and no `upgrade-insecure-requests`.
Keep this block after `CSP_NONCE` is defined and before `app/Config/config.php`
is required.

## 5. .htaccess — `public_html/.htaccess`

Apply `htaccess.snippet.conf`:

- sitemap rewrite for `sitemap_anonymity.xml`;
- 308/301 redirects from `/anonymitycheck/...` to the dedicated origin;
- local rewrite to `anonymitycheck/index.php` for Docker and development.

If the production origin changes, update both redirect rules and the fallback
origin in `public_html/anonymitycheck/index.php`; the module itself reads
`ANONYMITY_SITE_URL`.

## 6. Footer link — `app/Views/inc/footer.php`

Add the link using the helper, not a literal origin:

```php
<a href="<?= htmlspecialchars(anonymityUrl(), ENT_QUOTES, 'UTF-8') ?>"><?= __('nav.anonymity', 'Anonymity Check') ?></a>
```

Add the `nav.anonymity` translation for both languages if the key is missing.

## 7. Sitemap — `app/Controllers/SeoController.php`

Apply `sitemap.snippet.php`: action, index entry, cache writer and the private
generation methods. Then regenerate output:

```sh
php cron/generate-sitemap.php --force
```

## 8. Storage permissions

```sh
install -d -m 0750 storage/anonymity
chmod 0640 storage/anonymity/* 2>/dev/null || true
```

The module writes `results_*.json`, `georate_*.json` and `save_rate_*.json` there.
It also appends `logs/anonymity_check.jsonl`, so `logs/` must be writable by PHP.
See `docs/DATA.md` for the read-only MMDB and Tor list files.

## 9. Cron

```cron
0 * * * * /usr/local/bin/php /path/to/Fezadan/cron/update-tor-exits.php
```

The Tor list is rejected after 24 hours without a refresh. A failed download keeps
the previous list.

## 10. Verify

```sh
scripts/check.sh --target /path/to/Fezadan
curl -I http://localhost:8080/anonymitycheck/
curl -s http://localhost:8080/anonymitycheck/tor-check
curl -s https://anonymitycheck.fezadan.org/sitemap_anonymity.xml
```

## Host assets the module expects

The views reference host files that are not part of this module:

- `/assets/fonts/league-gothic/...`, `/assets/fonts/eb-garamond-...`, `/assets/fonts/jetbrains-mono-...`
- `/cdn/logo-dark-160.webp`, `/cdn/dark-favicon-32x32.png`

The two `public_html/assets/img/anonymity-bg*.jpg` files are copied for completeness;
the current views do not reference them.

## Files the module depends on from the host

`app/Core/Controller.php`, `app/Core/HttpRequest.php`, `HttpRequest::isLocalHost()`,
`CSP_NONCE`, `env_value()`, `APP_SALT`, `App::view()`, `App::render404()` and the
Composer autoloader. This is why it cannot run on its own.
