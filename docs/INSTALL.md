# Install

AnonymityCheck is a module for an existing Fezadan checkout. It is not a
standalone application and has no bootstrap of its own.

## Prerequisites

- PHP 8.2 (same as the host app).
- Composer dependency `maxmind-db/reader` (`~1.11`) installed in the host.
- A writable `storage/anonymity/` directory (see `DATA.md`).
- Host files that the views load but this module does not ship:
  `/assets/fonts/...`, `/cdn/logo-dark-160.webp`, `/cdn/dark-favicon-32x32.png`.
- For the dedicated origin: DNS and a TLS certificate for the subdomain, with the
  document root pointing at the host's `public_html/`.

## Copy the module

```sh
scripts/install.sh --target /path/to/Fezadan
```

The target must contain `app/Core/App.php`. The script copies `module/app`,
`module/public_html` and `module/cron`. Add `--with-tests` to also copy
`module/tests` (PHP fallback test and Playwright specs).

```sh
scripts/install.sh --target /path/to/Fezadan --with-tests
```

Existing files at the same paths are overwritten.

## Host integration

Copying files is not enough. Follow `host/README.md` in order:

1. `app/Core/App.php` routing (`host/app-routing.snippet.php`)
2. `app/Config/config.php` origin (`host/config.snippet.php`)
3. `public_html/index.php` CSP (`host/csp.snippet.php`)
4. `public_html/.htaccess` redirects (`host/htaccess.snippet.conf`)
5. `app/Views/inc/footer.php` link
6. `app/Controllers/SeoController.php` sitemap (`host/sitemap.snippet.php`)
7. storage permissions
8. hourly cron

At minimum the controller actions must be added to `App::ROUTABLE_ACTIONS`, or
every module URL returns 404.

## Storage

```sh
install -d -m 0750 storage/anonymity
chmod 0640 storage/anonymity/* 2>/dev/null || true
chmod 0750 logs
```

See `DATA.md` for the expected database files.

## Cron

```cron
0 * * * * /usr/local/bin/php /path/to/Fezadan/cron/update-tor-exits.php
```

The Tor exit list is considered stale after 24 hours; a failed download keeps the
last good list and exits non-zero.

## Optional TURN

Active WebRTC checks need a first-party TURN service. Set in `.env`:

```dotenv
ANONYMITY_TURN_URL=turns:turn.example.org:5349?transport=tcp
ANONYMITY_TURN_SECRET=...
```

Without it the report marks active WebRTC testing as unavailable. Never expose the
secret to the browser; the controller derives short-lived credentials.

## Verification

Local path access (Docker or development):

```sh
curl -I http://localhost:8080/anonymitycheck/
curl -s  http://localhost:8080/anonymitycheck/tor-check
curl -s  http://localhost:8080/anonymitycheck/geo-lookup
curl -s  http://localhost:8080/sitemap_anonymity.xml
```

Dedicated origin:

```sh
curl -I https://anonymitycheck.fezadan.org/
curl -s  https://anonymitycheck.fezadan.org/sitemap_anonymity.xml
```

Check the module file set and PHP syntax:

```sh
scripts/check.sh --target /path/to/Fezadan
```

Expected: the landing page returns 200 with `Cache-Control: private, no-store`;
`/anonymitycheck/results...` 404s for unknown ids; old `/anonymitycheck/...` paths
redirect to the dedicated origin (301 for GET/HEAD, 308 for other methods).
