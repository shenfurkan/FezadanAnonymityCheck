<?php
// Run in a separate PHP process: substitute only the local MMDB reader, never
// the lookup code. All database paths are disposable; no network requests occur.
namespace MaxMind\Db {
    class Reader {
        public static array $records = [];
        public static array $reads = [];
        private string $name;
        public function __construct(string $path) { $this->name = basename($path); }
        public function get(string $ip) {
            self::$reads[] = $this->name;
            $value = self::$records[$this->name] ?? null;
            if ($value instanceof \Throwable) throw $value;
            return $value;
        }
        public function close(): void {}
    }
}

namespace {
    require dirname(__DIR__) . '/app/Controllers/AnonymityCheck/AnonymityGeoApiTrait.php';
    define('ROOT', sys_get_temp_dir() . '/fezadan-geo-test-' . bin2hex(random_bytes(8)));
    putenv('APP_ENV=production'); // Do not take the application's testing shortcut.
    $dir = ROOT . '/storage/anonymity';
    mkdir($dir, 0700, true);
    $files = ['GeoLite2-City.mmdb', 'dbip-city-lite.mmdb', 'GeoLite2-ASN.mmdb', 'dbip-asn-lite.mmdb'];
    foreach ($files as $file) touch($dir . '/' . $file);
    $lookup = new class {
        use \AnonymityGeoApiTrait;
        public function lookup(): array { return $this->geolocateIp('1.1.1.1'); }
    };
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $ok = false;
    try {
        \MaxMind\Db\Reader::$records = [
            'dbip-city-lite.mmdb' => ['country' => ['iso_code' => 'TR'], 'city' => ['names' => ['en' => 'Istanbul']]],
            'dbip-asn-lite.mmdb' => ['autonomous_system_number' => 64500],
        ];
        $result = $lookup->lookup();
        $assert($result['ok'] && $result['city'] === 'Istanbul' && $result['asn'] === 'AS64500', 'Empty preferred MMDB must fall through; ASN-only records remain usable.');
        $assert($result['org'] === '' && $result['sources'] === ['dbip_city', 'dbip_asn'], 'Source attribution must describe the databases actually used.');

        \MaxMind\Db\Reader::$records['GeoLite2-City.mmdb'] = ['country' => ['iso_code' => '']];
        \MaxMind\Db\Reader::$records['GeoLite2-ASN.mmdb'] = ['autonomous_system_organization' => ''];
        $result = $lookup->lookup();
        $assert($result['city'] === 'Istanbul' && $result['asn'] === 'AS64500', 'Empty fields must not be attributed as a successful preferred lookup.');

        \MaxMind\Db\Reader::$records['GeoLite2-City.mmdb'] = ['country' => ['iso_code' => 'US']];
        \MaxMind\Db\Reader::$records['GeoLite2-ASN.mmdb'] = ['autonomous_system_organization' => 'Primary network'];
        \MaxMind\Db\Reader::$reads = [];
        $result = $lookup->lookup();
        $assert($result['country_code'] === 'US' && $result['org'] === 'Primary network', 'Useful preferred results must keep their priority.');
        $assert(\MaxMind\Db\Reader::$reads === ['GeoLite2-City.mmdb', 'GeoLite2-ASN.mmdb'], 'Do not query fallbacks when preferred results are usable.');

        \MaxMind\Db\Reader::$records = [];
        $result = $lookup->lookup();
        $assert(!$result['ok'] && $result['sources'] === [] && $result['asn'] === '', 'No local result stays unknown, with no invented source.');
        $ok = true;
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
    } finally {
        foreach ($files as $file) unlink($dir . '/' . $file);
        rmdir($dir);
        rmdir(ROOT . '/storage');
        rmdir(ROOT);
    }
    exit($ok ? 0 : 1);
}
