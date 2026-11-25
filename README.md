# Fezadan AnonymityCheck

A module for the [Fezadan](https://fezadan.org) PHP MVC site. It is **not a
standalone application**: it has no bootstrap, router, database layer or site shell.
The host supplies routing, configuration, CSP, Apache rules and shared assets.

The tool reports what a browser and the local server can actually observe about a
visitor: visible IP, Tor exit-list evidence, WebRTC addresses when a first-party TURN
service exists, and browser fingerprint signals. It does not produce an anonymity
score, does not test DNS and does not call third-party probes.

## Install

```sh
scripts/install.sh --target /path/to/Fezadan
scripts/install.sh --target /path/to/Fezadan --with-tests
```

Copying files is only half the job. The host still needs routing, config, CSP,
`.htaccess`, a footer link, sitemap and storage/cron setup. Follow
[`host/README.md`](host/README.md).

## Docs

- [`docs/INSTALL.md`](docs/INSTALL.md) — prerequisites, install, host integration, cron, verification
- [`docs/DATA.md`](docs/DATA.md) — local databases, licenses, permissions
- [`docs/TESTING.md`](docs/TESTING.md) — PHP runner, Playwright, standalone geo test

## Data and license

The module reads `storage/anonymity/` locally: MaxMind GeoLite2 or DB-IP Lite city
and ASN databases, plus a Tor exit list refreshed hourly. Those files are never
committed. See [`docs/DATA.md`](docs/DATA.md) for licenses and attribution.

Code is licensed under GPL-3.0; see [`LICENSE`](LICENSE).
