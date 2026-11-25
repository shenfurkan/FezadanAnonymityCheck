<?php
trait AnonymitySaveTrait
{
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 65536) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Payload too large']);
            exit;
        }

        $input = file_get_contents('php://input');
        if (strlen((string)$input) > 65536) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Payload too large']);
            exit;
        }

        $payload = json_decode($input, true);
        if (!$payload || !is_array($payload)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }

        $dir = ROOT . '/storage/anonymity';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!$this->allowAnonymitySave($dir)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }

        // Clean up old files on save
        $this->cleanupOldResults($dir);

        // Generate an unguessable random share token that doesn't exist.
        do {
            $id = bin2hex(random_bytes(16));
            $filePath = $dir . '/results_' . $id . '.json';
        } while (file_exists($filePath));

        $payload['created_at'] = gmdate('c');
        $payload = $this->sanitizeAnonymityPayload($payload);

        // Save file
        if (@file_put_contents($filePath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Failed to write cache file']);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    private function allowAnonymitySave(string $dir): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $salt = defined('APP_SALT') ? APP_SALT : 'fezadan-anonymity-check';
        $hash = hash('sha256', 'anonymity_save|' . $ip . '|' . date('Y-m-d') . '|' . $salt);
        $file = $dir . '/save_rate_' . $hash . '.json';
        $now = time();
        $window = 60;
        $limit = 10;
        $hits = [];
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    $ts = (int)$ts;
                    if ($ts > ($now - $window)) {
                        $hits[] = $ts;
                    }
                }
            }
        }

        if (count($hits) >= $limit) {
            return false;
        }

        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        @chmod($file, 0600);
        return true;
    }

    private function sanitizeAnonymityPayload(array $payload): array {
        $allowed = [
            'hostname', 'country', 'country_name', 'network_asn', 'network_org',
            'city', 'region', 'geo_timezone', 'geo_source', 'geo_accuracy',
            'latitude', 'longitude', 'is_tor', 'tor_source', 'proxy_headers', 'server_protocol',
            'score', 'risk_model', 'vector', 'findings', 'matrix', 'browser', 'timezone',
            'checks', 'duration_ms', 'created_at',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $clean[$key] = $this->sanitizeAnonymityValue($payload[$key]);
            }
        }
        $clean['ip'] = 'Not stored';
        $clean['headers'] = [];
        if (($clean['risk_model'] ?? null) === 3) unset($clean['score']);
        return $clean;
    }

    private function sanitizeAnonymityValue($value, int $depth = 0) {
        if ($depth > 3) {
            return null;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_string($value)) {
            // ICE findings also contain addresses: removing only the top-level IP is insufficient.
            $value = preg_replace_callback('/(?<![A-Za-z0-9:.])(?:[a-f0-9]{0,4}:){2,}[a-f0-9:.]*(?![A-Za-z0-9:])/i', static function ($match) {
                $address = rtrim($match[0], '.');
                return filter_var($address, FILTER_VALIDATE_IP) ? '[IP redacted]' . substr($match[0], strlen($address)) : $match[0];
            }, $value);
            $value = preg_replace_callback('/(?<![A-Za-z0-9.])(?:\d{1,3}\.){3}\d{1,3}(?![A-Za-z0-9]|\.\d)/', static function ($match) {
                return filter_var($match[0], FILTER_VALIDATE_IP) ? '[IP redacted]' : $match[0];
            }, $value);
            return mb_substr($value, 0, 500, 'UTF-8');
        }
        if (!is_array($value)) {
            return null;
        }

        $clean = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if ($count >= 50) {
                break;
            }
            $safeKey = is_int($key) ? $key : preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$key);
            if ($safeKey === '') {
                continue;
            }
            $clean[$safeKey] = $this->sanitizeAnonymityValue($item, $depth + 1);
            $count++;
        }
        return $clean;
    }

    private function cleanupOldResults($dir) {
        if (!is_dir($dir)) return;
        $files = array_merge(glob($dir . '/results_*.json') ?: [], glob($dir . '/save_rate_*.json') ?: [], glob($dir . '/georate_*.json') ?: []);
        if (!$files) return;
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 86400) { // 24 hours
                @unlink($file);
            }
        }
    }
}
