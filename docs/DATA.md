# Data

The module reads local files only. No visitor address is sent to MaxMind, DB-IP or
the Tor Project at request time.

## Expected files

All paths are relative to the host application root.

| Path | Required | Purpose |
|------|----------|---------|
| `storage/anonymity/GeoLite2-City.mmdb` | preferred | City/country coordinates (MaxMind) |
| `storage/anonymity/dbip-city-lite.mmdb` | fallback | City/country coordinates (DB-IP) |
| `storage/anonymity/GeoLite2-ASN.mmdb` | preferred | ASN and organization (MaxMind) |
| `storage/anonymity/dbip-asn-lite.mmdb` | fallback | ASN and organization (DB-IP) |
| `storage/anonymity/tor-exit-nodes.txt` | yes | Tor exit addresses, refreshed hourly |

City and ASN fallbacks are independent: the code tries MaxMind first, then DB-IP,
and attributes the source actually used. A missing or unusable set leaves the
value "unknown"; it does not invent a result.

## Runtime files (generated, never committed)

- `storage/anonymity/results_<32 hex>.json` — saved reports, masked, 24-hour lifetime.
- `storage/anonymity/georate_<hash>.json` — geo endpoint rate limits.
- `storage/anonymity/save_rate_<hash>.json` — save endpoint rate limits.
- `logs/anonymity_check.jsonl` — one daily-rotated JSONL file of page opens.

These are runtime state. Delete them freely; losing them only expires saved reports
and resets rate limits.

## Licenses and sources

- **MaxMind GeoLite2** — MaxMind GeoLite2 End User License Agreement. A MaxMind
  account is required to download; redistribution is restricted. Keep the attribution
  MaxMind requires and do not commit the `.mmdb` files.
- **DB-IP Lite** — Creative Commons Attribution 4.0 (CC BY 4.0). Attribution:
  "IP Geolocation by DB-IP (https://db-ip.com)". Do not commit the `.mmdb` files.
- **Tor exit list** — Tor Project bulk exit list,
  `https://check.torproject.org/torbulkexitlist`, fetched by
  `cron/update-tor-exits.php`. The list changes; do not commit a snapshot.

## Permissions

```sh
chown <php-user>:<php-group> storage/anonymity
chmod 0750 storage/anonymity
chmod 0640 storage/anonymity/*.mmdb storage/anonymity/tor-exit-nodes.txt
```

The directory must be readable/writable by PHP. Files are read-only to the web
server. PHP-created runtime files follow the process umask; set a restrictive umask
or re-apply `0640` if your host's default is world-readable.

`storage/` is ignored by the host repository. Never commit `.mmdb` files, the Tor
list, saved reports, rate-limit files, `.env` or logs.
