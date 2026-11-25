<?php
/**
 * app/Core/App.php — routing integration.
 *
 * Four edits. Apply in this order; the dispatch branch must come before the
 * legacy-path redirect and before the language-prefix redirect.
 */

// ---------------------------------------------------------------- 1. Allow-list
// Add the controller actions. Without this entry every module route 404s.
'AnonymityCheckController' => ['index', 'torCheck', 'ispLookup', 'geoLookup', 'save'],
// The sitemap action must also be routable:
'SeoController' => ['robots', 'sitemapIndex', 'sitemapMain', 'sitemapNotes', 'sitemapAnonymity'],


// ------------------------------------------------------- 2. Sitemap dispatch
// In App::__construct(), next to the existing sitemap.xml handling.
if (in_array($pathLower, ['/sitemap.xml', '/sitemap_main.xml', '/sitemap_notes.xml', '/sitemap_anonymity.xml'], true)) {
    require_once ROOT . '/app/Controllers/SeoController.php';
    $seo = new SeoController();
    if ($pathLower === '/sitemap.xml') {
        if (strpos($host, 'anonymitycheck.') === 0) $seo->sitemapAnonymity();
        elseif (strpos($host, 'notlar.') === 0) $seo->sitemapNotes();
        else $seo->sitemapIndex();
    } elseif ($pathLower === '/sitemap_main.xml') {
        $seo->sitemapMain();
    } elseif ($pathLower === '/sitemap_anonymity.xml') {
        $seo->sitemapAnonymity();
    } else {
        $seo->sitemapNotes();
    }
    exit;
}


// ------------------------------------------------------ 3. Host dispatch
// $host is strtolower($_SERVER['HTTP_HOST']) from the top of the constructor.
$url = $this->parseUrl();
$hasLangPrefix = $this->detectLanguagePrefix($url);
// Admin auth, role and CSRF checks must see the same action the router dispatches.
// Never let a client-supplied ?url= override Apache's rewritten route.
$_GET['url'] = implode('/', $url);

if (strpos($host, 'anonymitycheck.') === 0) {
    $this->routeAnonymityCheck($url);
    return;
}

if (strpos($host, 'notlar.') === 0) {
    $this->routeNotes($url);
    return;
}

$this->redirectAnonymityPath($url, $queryString);
$this->redirectMissingLanguagePrefix($hasLangPrefix, $canonicalPath, $queryString);
$this->routeMainSite($url);


// ------------------------------------------------------ 4. Private methods
// Paste next to routeNotes() in class App.

private function routeAnonymityCheck(array $url): void
{
    header('Cache-Control: private, no-store, max-age=0');
    // API/report responses are crawlable for noindex to be seen, but never indexed.
    if ($url !== ['home'] && $url !== []) header('X-Robots-Tag: noindex, nofollow, noarchive');
    $anonymityPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (in_array($url, [['home'], ['index']], true) && !isset($_SERVER['FEZADAN_PATH_PREFIX']) && $anonymityPath !== '/') {
        header('Location: /', true, 301);
        exit;
    }
    require_once ROOT . '/app/Controllers/AnonymityCheckController.php';
    $this->controller = new AnonymityCheckController();
    $this->method = 'index';
    if (isset($url[0])) {
        $methodName = $this->resolveRoutableMethod($this->controller, $url[0]);
        if ($methodName !== null) {
            $this->method = $methodName;
            unset($url[0]);
        } elseif ($url[0] === 'home') {
            unset($url[0]);
        } elseif (!preg_match('/^results[a-f0-9]{32}$/', $url[0])) {
            $this->render404();
        }
    }
    $this->params = $url ? array_values($url) : [];
    $this->invokeAction();
}

private function redirectAnonymityPath(array $url, string $queryString): void
{
    $firstSegmentLower = strtolower($url[0] ?? '');
    if ($firstSegmentLower !== 'anonymitycheck' && $firstSegmentLower !== 'anonymity-check') {
        return;
    }

    $redirectPath = HttpRequest::isLocalHost() ? '/anonymitycheck/' : ANONYMITY_SITE_URL . '/';
    if (isset($url[1]) && $url[1] !== '') {
        $redirectPath .= implode('/', array_map('rawurlencode', array_slice($url, 1)));
    }
    if ($queryString !== '') {
        $redirectPath .= $queryString;
    }
    http_response_code(in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) ? 301 : 308);
    header('Location: ' . $redirectPath);
    exit;
}
