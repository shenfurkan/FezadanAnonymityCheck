<?php
trait AnonymityIndexTrait
{
    public function index($resultId = null) {
        $nonce = defined('CSP_NONCE') ? CSP_NONCE : '';
        $cachedResult = null;
        header('Cache-Control: private, no-store, max-age=0');
        if ($resultId !== null) header('X-Robots-Tag: noindex, nofollow, noarchive');

        if ($resultId && preg_match('/^results([a-f0-9]{32})$/', $resultId, $matches)) {
            $id = $matches[1];
            $filePath = ROOT . '/storage/anonymity/results_' . $id . '.json';
            if (is_file($filePath) && filesize($filePath) <= 65536 && time() - filemtime($filePath) <= 86400) {
                $cachedResult = json_decode(file_get_contents($filePath), true);
            }
        }

        if ($cachedResult && is_array($cachedResult)) {
            $cachedResult = $this->sanitizeAnonymityPayload($cachedResult);
            $data = [
                'ip' => $cachedResult['ip'] ?? 'Unavailable',
                'hostname' => $cachedResult['hostname'] ?? 'Unavailable',
                'country' => $cachedResult['country'] ?? '',
                'country_name' => $cachedResult['country_name'] ?? '',
                'network_asn' => $cachedResult['network_asn'] ?? '',
                'network_org' => $cachedResult['network_org'] ?? '',
                'city' => $cachedResult['city'] ?? '',
                'region' => $cachedResult['region'] ?? '',
                'geo_timezone' => $cachedResult['geo_timezone'] ?? '',
                'geo_source' => $cachedResult['geo_source'] ?? '',
                'geo_accuracy' => $cachedResult['geo_accuracy'] ?? '',
                'latitude' => $cachedResult['latitude'] ?? '',
                'longitude' => $cachedResult['longitude'] ?? '',
                'is_tor' => $cachedResult['is_tor'] ?? null,
                'tor_source' => $cachedResult['tor_source'] ?? '',
                'trusted_edge' => false,
                'ice_servers' => [],
                'proxy_headers' => $cachedResult['proxy_headers'] ?? [],
                'server_protocol' => $cachedResult['server_protocol'] ?? 'HTTP/1.1',
                'nonce' => $nonce,
                'headers' => $cachedResult['headers'] ?? [],
                'cached_result' => $cachedResult
            ];
            $this->view('front/anonymity_check', $data);
            return;
        }

        if ($resultId !== null) {
            // Expired or invalid share links must not become an indexable live report (soft 404).
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Report unavailable | FEZADAN</title><h1>Report unavailable</h1><p>This saved report has expired or could not be found.</p><a href="' . htmlspecialchars($_SERVER['FEZADAN_PATH_PREFIX'] ?? '', ENT_QUOTES, 'UTF-8') . '/">Run a new report</a></html>';
            return;
        }

        // Collect server-side network and location variables for new scan
        $headers = [];
        // Never reflect cookies, authorization, forwarded IPs or arbitrary headers into page scripts.
        foreach (['HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING',
            'HTTP_SEC_CH_UA', 'HTTP_SEC_CH_UA_PLATFORM', 'HTTP_SEC_CH_UA_MOBILE',
            'HTTP_UPGRADE_INSECURE_REQUESTS', 'HTTP_DNT', 'HTTP_SEC_GPC'] as $key) {
            if (isset($_SERVER[$key])) $headers[$key] = (string)$_SERVER[$key];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $isLocal = in_array($ip, ['127.0.0.1', '::1'], true)
            || HttpRequest::isLocalHost();
        
        $hostname = $isLocal ? 'Localhost / Development' : 'Not resolved (external DNS disabled)';

        $trustedEdge = (bool)($_SERVER['FEZADAN_TRUSTED_EDGE'] ?? false);
        $headerValue = static function (array $keys) use ($trustedEdge): string {
            if (!$trustedEdge) return '';
            foreach ($keys as $key) {
                if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
                    return trim((string)$_SERVER[$key]);
                }
            }
            return '';
        };
        
        $hasValidCoordinates = static function (string $latitude, string $longitude): bool {
            if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
                return false;
            }
            $latFloat = (float)$latitude;
            $lonFloat = (float)$longitude;
            return $latFloat >= -90 && $latFloat <= 90 && $lonFloat >= -180 && $lonFloat <= 180;
        };

        $countryCode = strtoupper($headerValue(['HTTP_CF_IPCOUNTRY']));
        $city = $headerValue(['HTTP_CF_IPCITY']);
        $region = $headerValue(['HTTP_CF_REGION']);
        $geoTimezone = $headerValue(['HTTP_CF_TIMEZONE']);
        $lat = $headerValue(['HTTP_CF_IPLATITUDE', 'HTTP_CF_LATITUDE']);
        $lon = $headerValue(['HTTP_CF_IPLONGITUDE', 'HTTP_CF_LONGITUDE']);
        $networkAsn = $headerValue(['HTTP_CF_ASN', 'HTTP_CF_ASNUM']);
        $networkOrg = $headerValue(['HTTP_CF_AS_ORG', 'HTTP_CF_ASN_ORG', 'HTTP_CF_ORG']);
        $countryName = $this->countryName($countryCode);
        $geoAccuracy = 'Unavailable';
        $geoSource = ($countryCode || $city || $lat || $lon) ? 'Cloudflare' : 'Unavailable';

        if (!$hasValidCoordinates($lat, $lon)) {
            $lat = '';
            $lon = '';
            $geoAccuracy = $countryCode !== '' ? 'Country-level (IP-based)' : 'Unavailable';
        } else {
            $geoAccuracy = $city !== '' ? 'City-level' : 'Coordinate-level';
        }

        $tor = $this->torStatusForIp($ip);
        $isTor = $tor['is_tor']; // Preserve unknown; absent/stale data is not a negative result.

        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_PROXY_CONNECTION',
            'HTTP_X_PROXY_ID',
            'HTTP_X_FORWARDED_HOST',
            'HTTP_X_FORWARDED_SERVER',
            'HTTP_CLIENT_IP'
        ];
        $detectedProxyHeaders = [];
        foreach ($proxyHeaders as $ph) {
            if (!empty($_SERVER[$ph])) {
                $detectedProxyHeaders[$ph] = 'present';
            }
        }

        $logPath = ROOT . '/logs/anonymity_check.jsonl';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $today = date('Y-m-d');
        if (is_file($logPath)) {
            $lastModified = date('Y-m-d', filemtime($logPath));
            if ($lastModified !== $today) {
                @file_put_contents($logPath, '', LOCK_EX);
            }
        }

        $salt = defined('APP_SALT') ? APP_SALT : 'fezadan-anonymity-check';
        $ipHash = hash('sha256', $ip . '|' . $today . '|' . $salt);

        $logEntry = [
            'ts' => date('c'),
            'event' => 'page_open',
            'module' => 'server_network',
            'status' => 'ready',
            'severity' => 'info',
            'ip_hash' => $ipHash,
            'data' => [
                'country' => $countryCode ?: null,
                'country_name' => $countryName ?: null,
                'city' => $city ?: null,
                'geo_source' => $geoSource,
                'geo_accuracy' => $geoAccuracy,
                'tor_signal' => $isTor,
                'proxy_header_names' => array_keys($detectedProxyHeaders),
                'user_agent_hash' => hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $today . '|' . $salt),
            ],
        ];
        @file_put_contents($logPath, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

        $data = [
            'ip' => $ip,
            'hostname' => $hostname,
            'country' => $countryCode,
            'country_name' => $countryName,
            'network_asn' => $networkAsn,
            'network_org' => $networkOrg,
            'city' => $city,
            'region' => $region,
            'geo_timezone' => $geoTimezone,
            'geo_source' => $geoSource,
            'geo_accuracy' => $geoAccuracy,
            'latitude' => $lat,
            'longitude' => $lon,
            'is_tor' => $isTor,
            'tor_source' => $tor['ok'] ? $tor['source'] : '',
            'trusted_edge' => $trustedEdge,
            'ice_servers' => $this->anonymityIceServers(),
            'proxy_headers' => $detectedProxyHeaders,
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            'nonce' => $nonce,
            'headers' => $headers,
            'cached_result' => null
        ];

        $this->view('front/anonymity_check', $data);
    }

    private function anonymityIceServers(): array {
        // Optional first-party coturn with use-auth-secret: ANONYMITY_TURN_URL
        // (turns:host:5349?transport=tcp) and ANONYMITY_TURN_SECRET. Never expose the shared secret.
        $url = env_value('ANONYMITY_TURN_URL');
        $secret = env_value('ANONYMITY_TURN_SECRET');
        if ($secret === '' || !preg_match('/^turns:([a-z0-9.-]+):(\d{1,5})\?transport=tcp$/i', $url, $match)) return [];
        $host = strtolower($match[1]);
        $siteHost = strtolower((string)parse_url(SITE_URL, PHP_URL_HOST));
        if ($siteHost === '' || ($host !== $siteHost && !str_ends_with($host, '.' . $siteHost))
            || (int)$match[2] < 1 || (int)$match[2] > 65535) return [];
        $username = (time() + 300) . ':' . bin2hex(random_bytes(8));
        return [[
            'urls' => $url,
            'username' => $username,
            'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
        ]];
    }

    private function countryName(string $code): string {
        $countries = [
            'AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AG' => 'Antigua and Barbuda',
            'AI' => 'Anguilla', 'AL' => 'Albania', 'AM' => 'Armenia', 'AO' => 'Angola', 'AQ' => 'Antarctica',
            'AR' => 'Argentina', 'AS' => 'American Samoa', 'AT' => 'Austria', 'AU' => 'Australia', 'AW' => 'Aruba',
            'AX' => 'Aland Islands', 'AZ' => 'Azerbaijan', 'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados',
            'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain',
            'BI' => 'Burundi', 'BJ' => 'Benin', 'BL' => 'Saint Barthelemy', 'BM' => 'Bermuda', 'BN' => 'Brunei',
            'BO' => 'Bolivia', 'BQ' => 'Caribbean Netherlands', 'BR' => 'Brazil', 'BS' => 'Bahamas', 'BT' => 'Bhutan',
            'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'BY' => 'Belarus', 'BZ' => 'Belize', 'CA' => 'Canada',
            'CC' => 'Cocos Islands', 'CD' => 'Democratic Republic of the Congo', 'CF' => 'Central African Republic',
            'CG' => 'Republic of the Congo', 'CH' => 'Switzerland', 'CI' => 'Cote d Ivoire', 'CK' => 'Cook Islands',
            'CL' => 'Chile', 'CM' => 'Cameroon', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica',
            'CU' => 'Cuba', 'CV' => 'Cape Verde', 'CW' => 'Curacao', 'CX' => 'Christmas Island', 'CY' => 'Cyprus',
            'CZ' => 'Czechia', 'DE' => 'Germany', 'DJ' => 'Djibouti', 'DK' => 'Denmark', 'DM' => 'Dominica',
            'DO' => 'Dominican Republic', 'DZ' => 'Algeria', 'EC' => 'Ecuador', 'EE' => 'Estonia', 'EG' => 'Egypt',
            'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ES' => 'Spain', 'ET' => 'Ethiopia', 'FI' => 'Finland',
            'FJ' => 'Fiji', 'FK' => 'Falkland Islands', 'FM' => 'Micronesia', 'FO' => 'Faroe Islands', 'FR' => 'France',
            'GA' => 'Gabon', 'GB' => 'United Kingdom', 'GD' => 'Grenada', 'GE' => 'Georgia', 'GF' => 'French Guiana',
            'GG' => 'Guernsey', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GL' => 'Greenland', 'GM' => 'Gambia',
            'GN' => 'Guinea', 'GP' => 'Guadeloupe', 'GQ' => 'Equatorial Guinea', 'GR' => 'Greece',
            'GS' => 'South Georgia and the South Sandwich Islands', 'GT' => 'Guatemala', 'GU' => 'Guam',
            'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HK' => 'Hong Kong', 'HM' => 'Heard Island and McDonald Islands',
            'HN' => 'Honduras', 'HR' => 'Croatia', 'HT' => 'Haiti', 'HU' => 'Hungary', 'ID' => 'Indonesia',
            'IE' => 'Ireland', 'IL' => 'Israel', 'IM' => 'Isle of Man', 'IN' => 'India', 'IO' => 'British Indian Ocean Territory',
            'IQ' => 'Iraq', 'IR' => 'Iran', 'IS' => 'Iceland', 'IT' => 'Italy', 'JE' => 'Jersey', 'JM' => 'Jamaica',
            'JO' => 'Jordan', 'JP' => 'Japan', 'KE' => 'Kenya', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia',
            'KI' => 'Kiribati', 'KM' => 'Comoros', 'KN' => 'Saint Kitts and Nevis', 'KP' => 'North Korea',
            'KR' => 'South Korea', 'KW' => 'Kuwait', 'KY' => 'Cayman Islands', 'KZ' => 'Kazakhstan', 'LA' => 'Laos',
            'LB' => 'Lebanon', 'LC' => 'Saint Lucia', 'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia',
            'LS' => 'Lesotho', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya',
            'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'MF' => 'Saint Martin',
            'MG' => 'Madagascar', 'MH' => 'Marshall Islands', 'MK' => 'North Macedonia', 'ML' => 'Mali',
            'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao', 'MP' => 'Northern Mariana Islands',
            'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MS' => 'Montserrat', 'MT' => 'Malta', 'MU' => 'Mauritius',
            'MV' => 'Maldives', 'MW' => 'Malawi', 'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique',
            'NA' => 'Namibia', 'NC' => 'New Caledonia', 'NE' => 'Niger', 'NF' => 'Norfolk Island', 'NG' => 'Nigeria',
            'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NP' => 'Nepal', 'NR' => 'Nauru',
            'NU' => 'Niue', 'NZ' => 'New Zealand', 'OM' => 'Oman', 'PA' => 'Panama', 'PE' => 'Peru',
            'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PH' => 'Philippines', 'PK' => 'Pakistan',
            'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon', 'PN' => 'Pitcairn', 'PR' => 'Puerto Rico',
            'PS' => 'Palestine', 'PT' => 'Portugal', 'PW' => 'Palau', 'PY' => 'Paraguay', 'QA' => 'Qatar',
            'RE' => 'Reunion', 'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia', 'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia', 'SB' => 'Solomon Islands', 'SC' => 'Seychelles', 'SD' => 'Sudan', 'SE' => 'Sweden',
            'SG' => 'Singapore', 'SH' => 'Saint Helena', 'SI' => 'Slovenia', 'SJ' => 'Svalbard and Jan Mayen',
            'SK' => 'Slovakia', 'SL' => 'Sierra Leone', 'SM' => 'San Marino', 'SN' => 'Senegal', 'SO' => 'Somalia',
            'SR' => 'Suriname', 'SS' => 'South Sudan', 'ST' => 'Sao Tome and Principe', 'SV' => 'El Salvador',
            'SX' => 'Sint Maarten', 'SY' => 'Syria', 'SZ' => 'Eswatini', 'TC' => 'Turks and Caicos Islands',
            'TD' => 'Chad', 'TF' => 'French Southern Territories', 'TG' => 'Togo', 'TH' => 'Thailand',
            'TJ' => 'Tajikistan', 'TK' => 'Tokelau', 'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan',
            'TN' => 'Tunisia', 'TO' => 'Tonga', 'TR' => 'Turkey', 'TT' => 'Trinidad and Tobago', 'TV' => 'Tuvalu',
            'TW' => 'Taiwan', 'TZ' => 'Tanzania', 'UA' => 'Ukraine', 'UG' => 'Uganda',
            'UM' => 'United States Minor Outlying Islands', 'US' => 'United States', 'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan', 'VA' => 'Vatican City', 'VC' => 'Saint Vincent and the Grenadines',
            'VE' => 'Venezuela', 'VG' => 'British Virgin Islands', 'VI' => 'U.S. Virgin Islands', 'VN' => 'Vietnam',
            'VU' => 'Vanuatu', 'WF' => 'Wallis and Futuna', 'WS' => 'Samoa', 'YE' => 'Yemen', 'YT' => 'Mayotte',
            'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe', 'XX' => 'Unknown', 'T1' => 'Tor Network',
        ];

        return $countries[$code] ?? $code;
    }
}
