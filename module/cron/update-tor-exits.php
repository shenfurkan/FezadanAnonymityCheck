<?php
/**
 * Refresh the local Tor exit snapshot hourly, outside visitor requests:
 * 0 * * * * /usr/local/bin/php /path/to/Fezadan/cron/update-tor-exits.php
 * No visitor address is ever sent to Tor Project. Failed updates keep the last good list.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $context = stream_context_create([
        'http' => ['timeout' => 15, 'follow_location' => 0, 'header' => "User-Agent: Fezadan-TorList/1.0\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents('https://check.torproject.org/torbulkexitlist', false, $context, 0, 2097153);
    if ($body === false || strlen($body) > 2097152) throw new RuntimeException('Exit list download failed');
    $addresses = [];
    foreach (preg_split('/\R/', $body) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!filter_var($line, FILTER_VALIDATE_IP)) throw new RuntimeException('Invalid exit list response');
        $addresses[inet_ntop(inet_pton($line))] = true;
    }
    if (count($addresses) < 100) throw new RuntimeException('Incomplete exit list response');
    $dir = dirname(__DIR__) . '/storage/anonymity';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Storage unavailable');
    $temporary = tempnam($dir, 'tor_');
    try {
        if ($temporary === false || file_put_contents($temporary, implode("\n", array_keys($addresses)) . "\n", LOCK_EX) === false
            || !chmod($temporary, 0600) || !rename($temporary, $dir . '/tor-exit-nodes.txt')) {
            throw new RuntimeException('Exit list write failed');
        }
    } finally {
        if ($temporary !== false && is_file($temporary)) unlink($temporary);
    }
    echo 'Updated ' . count($addresses) . " Tor exit addresses.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
