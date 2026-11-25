<?php
trait AnonymityGeoApiTrait
{
    public function torCheck() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $result = $this->torStatusForIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function torStatusForIp(string $ip, ?string $listPath = null): array {
        // REMOTE_ADDR is normalized by the entrypoint. No remote origin-IP query or visitor DNSBL query.
        $result = ['ok' => false, 'source' => 'local_exit_list', 'is_tor' => null, 'error' => null];
        if (($_SERVER['FEZADAN_TRUSTED_EDGE'] ?? false) && ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '') === 'T1') {
            return ['ok' => true, 'source' => 'cloudflare', 'is_tor' => true, 'error' => null];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $result['error'] = 'Visitor IP unavailable';
            return $result;
        }
        $listPath = $listPath ?? ROOT . '/storage/anonymity/tor-exit-nodes.txt';
        if (!is_file($listPath) || !is_readable($listPath) || filesize($listPath) > 2097152
            || time() - filemtime($listPath) > 86400) {
            $result['error'] = 'Local Tor exit list unavailable or older than 24 hours';
            return $result;
        }
        $lines = @file($listPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $valid = 0;
        $matched = false;
        foreach ($lines ?: [] as $line) {
            $address = trim($line);
            if ($address === '' || $address[0] === '#') continue;
            if (!filter_var($address, FILTER_VALIDATE_IP)) {
                $result['error'] = 'Invalid local Tor exit list';
                return $result;
            }
            $valid++;
            $matched = $matched || inet_pton($address) === inet_pton($ip);
        }
        if (!$valid) {
            $result['error'] = 'Empty local Tor exit list';
            return $result;
        }
        return ['ok' => true, 'source' => 'local_exit_list', 'is_tor' => $matched, 'error' => null];
    }

    public function ispLookup() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $geo = $this->geolocateIp($ip);
        if ($geo['ok'] && ($geo['asn'] || $geo['org'] || $geo['isp'])) {
            echo json_encode([
                'ok' => true,
                'org' => $geo['org'] ?: ($geo['isp'] ?: ''),
                'asn' => $geo['asn'] ?: '',
            ], JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['ok' => false, 'error' => $geo['error'] ?? 'ISP lookup failed'], JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    public function geoLookup() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $dir = ROOT . '/storage/anonymity';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $salt = defined('APP_SALT') ? APP_SALT : 'fezadan-anonymity-check';
        $hash = hash('sha256', 'geo_lookup|' . $ip . '|' . date('Y-m-d') . '|' . $salt);
        $rateFile = $dir . '/georate_' . $hash . '.json';
        $now = time();
        $window = 60;
        $limit = 5;
        $hits = [];
        if (is_file($rateFile)) {
            $decoded = json_decode((string)@file_get_contents($rateFile), true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if ((int)$ts > ($now - $window)) {
                        $hits[] = (int)$ts;
                    }
                }
            }
        }
        if (count($hits) >= $limit) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Too many requests']);
            exit;
        }
        $hits[] = $now;
        @file_put_contents($rateFile, json_encode($hits), LOCK_EX);
        @chmod($rateFile, 0600);
        $this->cleanupOldResults($dir);

        $result = $this->geolocateIp($ip);
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function geolocateIp(string $ip): array {
        $result = [
            'ok' => false,
            'sources' => [],
            'country_code' => '',
            'country' => '',
            'city' => '',
            'region' => '',
            'latitude' => null,
            'longitude' => null,
            'timezone' => '',
            'isp' => '',
            'org' => '',
            'asn' => '',
            'error' => null,
        ];

        if (getenv('APP_ENV') === 'testing') {
            return [
                'ok' => true,
                'sources' => ['testing_mock'],
                'country_code' => 'TR',
                'country' => 'Turkey',
                'city' => 'Istanbul',
                'region' => 'Istanbul',
                'latitude' => 41.0082,
                'longitude' => 28.9784,
                'timezone' => 'Europe/Istanbul',
                'isp' => 'Mock ISP',
                'org' => 'Mock Organization',
                'asn' => 'AS12345',
                'error' => null,
            ];
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $result['error'] = 'Visitor IP unavailable';
            return $result;
        }

        // Tier 1: Local City MMDB (MaxMind GeoLite2-City or DB-IP City Lite)
        $cityDbCandidates = [
            ROOT . '/storage/anonymity/GeoLite2-City.mmdb' => 'maxmind_city',
            ROOT . '/storage/anonymity/dbip-city-lite.mmdb' => 'dbip_city',
        ];
        foreach ($cityDbCandidates as $cityDbPath => $citySourceLabel) {
            if (!is_file($cityDbPath)) continue;
            try {
                $reader = new \MaxMind\Db\Reader($cityDbPath);
                $record = $reader->get($ip);
                $reader->close();
                if ($record && !empty($record['country']['iso_code'])) {
                    $result['country_code'] = (string)$record['country']['iso_code'];
                    $result['country'] = (string)($record['country']['names']['en'] ?? $record['country']['iso_code']);
                    $result['sources'][] = $citySourceLabel;
                    if (isset($record['city']['names']['en'])) {
                        $result['city'] = (string)$record['city']['names']['en'];
                    }
                    if (isset($record['subdivisions'][0]['names']['en'])) {
                        $result['region'] = (string)$record['subdivisions'][0]['names']['en'];
                    }
                    if (isset($record['location']['latitude'])) {
                        $result['latitude'] = (float)$record['location']['latitude'];
                    }
                    if (isset($record['location']['longitude'])) {
                        $result['longitude'] = (float)$record['location']['longitude'];
                    }
                    if (isset($record['location']['time_zone'])) {
                        $result['timezone'] = (string)$record['location']['time_zone'];
                    }
                    break;
                }
            } catch (\Throwable $e) {
                // Fall through to the next source, but leave a trace (e.g. MaxMind missing from vendor/)
                error_log('[Fezadan][Degraded] geo tier ' . $citySourceLabel . ': ' . get_class($e) . ': ' . $e->getMessage());
            }
        }

        // Tier 2: Local ASN MMDB (MaxMind GeoLite2-ASN or DB-IP ASN Lite)
        $asnDbCandidates = [
            ROOT . '/storage/anonymity/GeoLite2-ASN.mmdb' => 'maxmind_asn',
            ROOT . '/storage/anonymity/dbip-asn-lite.mmdb' => 'dbip_asn',
        ];
        foreach ($asnDbCandidates as $asnDbPath => $asnSourceLabel) {
            if (!is_file($asnDbPath)) continue;
            try {
                $reader = new \MaxMind\Db\Reader($asnDbPath);
                $record = $reader->get($ip);
                $reader->close();
                if ($record && (!empty($record['autonomous_system_organization']) || !empty($record['autonomous_system_number']))) {
                    $result['asn'] = isset($record['autonomous_system_number'])
                        ? 'AS' . $record['autonomous_system_number']
                        : '';
                    $result['org'] = (string)($record['autonomous_system_organization'] ?? '');
                    $result['sources'][] = $asnSourceLabel;
                    break;
                }
            } catch (\Throwable $e) {
                // Fall through to the next source, but leave a trace (e.g. MaxMind missing from vendor/)
                error_log('[Fezadan][Degraded] geo tier ' . $asnSourceLabel . ': ' . get_class($e) . ': ' . $e->getMessage());
            }
        }

        $result['ok'] = !empty($result['sources']);
        if (!$result['ok']) {
            $result['error'] = 'Local geolocation databases unavailable; no external lookup sent';
        }
        return $result;
    }
}
