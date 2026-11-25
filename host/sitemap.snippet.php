<?php
/**
 * app/Controllers/SeoController.php — anonymity sitemap.
 *
 * Add the public action to App::ROUTABLE_ACTIONS['SeoController'] (see
 * app-routing.snippet.php). The index entry and the cache writer are edits to
 * existing methods; the three private methods are new.
 */

// ---------------------------------------------------------- 1. Public action
public function sitemapAnonymity() {
    // A single static landing page needs no database query or synthetic daily lastmod.
    $this->serveXml($this->generateSitemapAnonymityXml(), $this->anonymityModificationTime());
}

// ------------------------------------------- 2. Index entry (generateSitemapIndexXml)
$anonymityBase = ANONYMITY_SITE_URL;
$anonymityLastmod = gmdate('Y-m-d', $this->anonymityModificationTime());
// ... after the existing sitemap_main.xml and sitemap_notes.xml entries:
$xmlIndex .= "  <sitemap><loc>{$anonymityBase}/sitemap_anonymity.xml</loc><lastmod>{$anonymityLastmod}</lastmod></sitemap>" . PHP_EOL;

// ------------------------------------- 3. Cache writer (regenerateAllCache)
$xmlAnonymity = $this->generateSitemapAnonymityXml();
$this->writeAtomic(sys_get_temp_dir() . '/fezadan_sitemap_anonymity.xml', $xmlAnonymity);
$this->writeAtomic(ROOT . '/public_html/sitemap_anonymity.xml', $xmlAnonymity);

// ------------------------------------------------------------ 4. New methods
private function generateSitemapAnonymityXml(): string {
    $anonymityBase = ANONYMITY_SITE_URL;
    $lastmod = gmdate('Y-m-d', $this->anonymityModificationTime());

    $xmlAnonymity  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xmlAnonymity .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    $xmlAnonymity .= "  <url><loc>{$anonymityBase}/</loc><lastmod>{$lastmod}</lastmod></url>" . PHP_EOL;
    $xmlAnonymity .= '</urlset>';

    return $xmlAnonymity;
}

private function anonymityModificationTime(): int {
    $files = [ROOT . '/app/Views/front/anonymity_check.php', ROOT . '/app/Views/front/anonymity/guide.php', ROOT . '/app/Views/front/anonymity/seo.php'];
    return max(array_map(static fn($file) => is_file($file) ? (int)filemtime($file) : 0, $files));
}

// ------------------------------------------------- 5. robots.txt sitemap line
// In robots(), point the anonymity host at its own sitemap:
echo 'Sitemap: ' . ($isAnonymity ? ANONYMITY_SITE_URL . '/sitemap_anonymity.xml' : ($isNotes ? $notesBase . '/sitemap_notes.xml' : $base . '/sitemap.xml')) . "\n";
