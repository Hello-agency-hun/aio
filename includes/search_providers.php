<?php
/**
 * Mentett AI-keresési adatgyűjtő réteg.
 *
 * Ez a modul nem helyettesíti az OpenAI web_search próbát. A célja az, hogy
 * külön, újrafelhasználható keresési bizonyítékokat gyűjtsön és JSON cache-ben
 * eltároljon. Így az OpenAI elemzés később nem csak az audit JSON-ból, hanem
 * a konkrét keresési találatokból, saját-domain találatokból és versenytárs
 * megjelenésekből is dolgozhat.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function search_probe_config(): array
{
    $config = [
        'enabled' => getenv('SEARCH_PROBE_ENABLED') !== '0',
        'provider_order' => array_filter(array_map('trim', explode(',', getenv('SEARCH_PROVIDER_ORDER') ?: 'serpapi,searxng,langsearch,jina'))),
        'searxng_base_url' => getenv('SEARXNG_BASE_URL') ?: '',
        'serpapi_api_key' => getenv('SERPAPI_API_KEY') ?: (getenv('SERP_API_KEY') ?: ''),
        'serpapi_location' => getenv('SERPAPI_LOCATION') ?: 'Hungary',
        'serpapi_google_domain' => getenv('SERPAPI_GOOGLE_DOMAIN') ?: 'google.com',
        'serpapi_hl' => getenv('SERPAPI_HL') ?: 'hu',
        'serpapi_gl' => getenv('SERPAPI_GL') ?: 'hu',
        'langsearch_api_key' => getenv('LANGSEARCH_API_KEY') ?: '',
        'jina_api_key' => getenv('JINA_API_KEY') ?: '',
        'timeout' => (int) (getenv('SEARCH_PROBE_TIMEOUT') ?: 18),
        'connect_timeout' => (int) (getenv('SEARCH_PROBE_CONNECT_TIMEOUT') ?: 6),
        'max_results' => (int) (getenv('SEARCH_PROBE_MAX_RESULTS') ?: 5),
        'cache_ttl_hours' => (int) (getenv('SEARCH_PROBE_CACHE_TTL_HOURS') ?: 24),
        'language' => getenv('SEARCH_PROBE_LANGUAGE') ?: 'hu-HU',
        'ca_bundle' => getenv('SEARCH_PROBE_CA_BUNDLE') ?: DATA_DIR . '/cacert.pem',
        'query_limits' => [
            'quick' => 3,
            'smart' => 4,
            'deep' => 6,
            'custom' => 4,
        ],
    ];

    $protectedConfig = DATA_DIR . '/search_config.php';
    if (is_file($protectedConfig)) {
        $fileConfig = require $protectedConfig;
        if (is_array($fileConfig)) {
            $config = array_replace_recursive($config, $fileConfig);
        }
    }

    $config['max_results'] = max(1, min(10, (int) $config['max_results']));
    $config['timeout'] = max(5, min(60, (int) $config['timeout']));
    $config['connect_timeout'] = max(3, min(20, (int) $config['connect_timeout']));
    $config['cache_ttl_hours'] = max(1, min(168, (int) $config['cache_ttl_hours']));

    return $config;
}

function run_saved_search_probe(array $report, ?callable $progress = null): array
{
    $config = search_probe_config();
    if (($config['enabled'] ?? true) !== true) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'A mentett keresési adatgyűjtés ki van kapcsolva.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'message' => 'A keresési adatgyűjtéshez szükséges cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $providers = search_probe_available_providers($config);
    if (!$providers) {
        return [
            'enabled' => true,
            'status' => 'setup_required',
            'message' => 'Nincs beállított keresési provider. Adj meg SerpApi, SearXNG, LangSearch vagy Jina hozzáférést a data/search_config.php fájlban.',
            'supported_providers' => ['SerpApi Google Search API', 'SearXNG self-host', 'LangSearch Web Search API', 'Jina Search API'],
        ];
    }

    $plan = $report['ai_search_plan'] ?? [];
    $querySet = $plan['query_set'] ?? [];
    if (!is_array($querySet) || !$querySet) {
        return [
            'enabled' => true,
            'status' => 'skipped',
            'message' => 'Nincs generált AI keresési kérdéssor.',
        ];
    }

    $mode = (string) ($report['crawl_mode'] ?? 'smart');
    $queryLimits = is_array($config['query_limits'] ?? null) ? $config['query_limits'] : [];
    $queryLimit = max(1, min(8, (int) ($queryLimits[$mode] ?? 4)));
    $queries = array_slice($querySet, 0, $queryLimit);
    $targetDomain = search_probe_normalize_host((string) ($plan['domain'] ?? parse_url((string) ($report['url'] ?? ''), PHP_URL_HOST)));

    $queryResults = [];
    $ownedHitCount = 0;
    $competitorCounts = [];
    $errors = [];

    foreach ($queries as $index => $queryItem) {
        $queryText = trim((string) ($queryItem['query'] ?? ''));
        if ($queryText === '') {
            continue;
        }

        if ($progress) {
            $progress(
                65 + min(4, $index),
                'Keresési adatgyűjtés',
                sprintf('%d/%d. AI keresési kérdés találatainak mentése.', $index + 1, count($queries))
            );
        }

        $searchResult = search_probe_query($queryText, $providers, $config);
        $normalizedItems = search_probe_mark_domain_hits($searchResult['results'] ?? [], $targetDomain);
        $ownedHit = array_reduce(
            $normalizedItems,
            static fn (bool $carry, array $item): bool => $carry || (($item['owned_domain_hit'] ?? false) === true),
            false
        );

        if ($ownedHit) {
            $ownedHitCount++;
        }

        foreach (search_probe_competitors_from_results($normalizedItems, $targetDomain) as $host) {
            $competitorCounts[$host] = ($competitorCounts[$host] ?? 0) + 1;
        }

        if (($searchResult['status'] ?? '') === 'error') {
            $errors[] = $searchResult['message'] ?? 'Ismeretlen provider hiba.';
        }

        $queryResults[] = [
            'id' => $queryItem['id'] ?? ('query_' . ($index + 1)),
            'type' => $queryItem['type'] ?? 'query',
            'query' => $queryText,
            'provider' => $searchResult['provider'] ?? '',
            'status' => $searchResult['status'] ?? 'unknown',
            'from_cache' => (bool) ($searchResult['from_cache'] ?? false),
            'cache_key' => $searchResult['cache_key'] ?? '',
            'owned_domain_hit' => $ownedHit,
            'own_results' => array_values(array_filter($normalizedItems, static fn (array $item): bool => ($item['owned_domain_hit'] ?? false) === true)),
            'competitors' => search_probe_competitors_from_results($normalizedItems, $targetDomain),
            'results' => array_slice($normalizedItems, 0, (int) $config['max_results']),
            'error' => $searchResult['message'] ?? '',
        ];
    }

    arsort($competitorCounts);
    $competitors = array_slice(array_keys($competitorCounts), 0, 12);
    $totalQueries = count($queryResults);

    return [
        'enabled' => true,
        'status' => $totalQueries > 0 ? 'completed' : 'empty',
        'generated_at' => date(DATE_ATOM),
        'method' => 'Mentett keresési provider-adapter',
        'providers' => $providers,
        'target_domain' => $targetDomain,
        'query_count' => $totalQueries,
        'max_results_per_query' => (int) $config['max_results'],
        'retrieval_hit_rate' => $totalQueries > 0 ? (int) round(($ownedHitCount / $totalQueries) * 100) : 0,
        'owned_domain_query_hits' => $ownedHitCount,
        'competitors' => $competitors,
        'query_results' => $queryResults,
        'errors' => array_values(array_unique(array_filter($errors))),
        'cache_ttl_hours' => (int) $config['cache_ttl_hours'],
        'analysis_hint' => 'Ez klasszikus webes keresési találati adat. Azt mutatja, milyen forrásokból dolgozhat egy AI kereső, de nem ugyanaz, mint egy ChatGPT/Gemini/Perplexity válaszpanel.',
    ];
}

function search_probe_available_providers(array $config): array
{
    $order = is_array($config['provider_order'] ?? null) ? $config['provider_order'] : [];
    $providers = [];

    foreach ($order as $provider) {
        $provider = strtolower(trim((string) $provider));
        if ($provider === 'searxng' && trim((string) ($config['searxng_base_url'] ?? '')) !== '') {
            $providers[] = 'searxng';
        }
        if ($provider === 'serpapi' && trim((string) ($config['serpapi_api_key'] ?? '')) !== '') {
            $providers[] = 'serpapi';
        }
        if ($provider === 'langsearch' && trim((string) ($config['langsearch_api_key'] ?? '')) !== '') {
            $providers[] = 'langsearch';
        }
        if ($provider === 'jina' && trim((string) ($config['jina_api_key'] ?? '')) !== '') {
            $providers[] = 'jina';
        }
    }

    return array_values(array_unique($providers));
}

function search_probe_query(string $query, array $providers, array $config): array
{
    $lastError = '';
    $staleCandidate = null;

    foreach ($providers as $provider) {
        $cacheKey = search_probe_cache_key($provider, $query, $config);
        $freshCache = search_probe_read_cache($cacheKey, (int) $config['cache_ttl_hours']);
        if ($freshCache) {
            $freshCache['from_cache'] = true;
            return $freshCache;
        }

        $staleCache = search_probe_read_cache($cacheKey, 0);
        if ($staleCache && !$staleCandidate) {
            $staleCandidate = $staleCache;
        }

        $result = match ($provider) {
            'serpapi' => search_probe_serpapi($query, $config),
            'searxng' => search_probe_searxng($query, $config),
            'langsearch' => search_probe_langsearch($query, $config),
            'jina' => search_probe_jina($query, $config),
            default => ['status' => 'error', 'message' => 'Ismeretlen keresési provider.'],
        };

        $result['provider'] = $provider;
        $result['cache_key'] = $cacheKey;
        $result['from_cache'] = false;

        if (($result['status'] ?? '') === 'completed') {
            search_probe_write_cache($cacheKey, $result);
            return $result;
        }

        $lastError = (string) ($result['message'] ?? 'A provider nem adott feldolgozható választ.');
    }

    if ($staleCandidate) {
        $staleCandidate['from_cache'] = true;
        $staleCandidate['stale_cache'] = true;
        return $staleCandidate;
    }

    return [
        'status' => 'error',
        'provider' => implode(',', $providers),
        'from_cache' => false,
        'results' => [],
        'message' => $lastError ?: 'Egy keresési provider sem adott választ.',
    ];
}

function search_probe_serpapi(string $query, array $config): array
{
    $apiKey = trim((string) ($config['serpapi_api_key'] ?? ''));
    if ($apiKey === '') {
        return ['status' => 'error', 'message' => 'Hiányzó SerpApi kulcs.'];
    }

    $params = [
        'engine' => 'google',
        'q' => $query,
        'api_key' => $apiKey,
        'google_domain' => (string) ($config['serpapi_google_domain'] ?? 'google.com'),
        'hl' => (string) ($config['serpapi_hl'] ?? 'hu'),
        'gl' => (string) ($config['serpapi_gl'] ?? 'hu'),
        'num' => (int) ($config['max_results'] ?? 5),
    ];
    $location = trim((string) ($config['serpapi_location'] ?? ''));
    if ($location !== '') {
        $params['location'] = $location;
    }

    $http = search_probe_http_request(
        'GET',
        'https://serpapi.com/search.json?' . http_build_query($params),
        ['Accept: application/json'],
        null,
        $config
    );

    if (!$http['ok']) {
        return ['status' => 'error', 'message' => $http['message']];
    }

    $decoded = json_decode((string) $http['body'], true);
    if (!is_array($decoded)) {
        return ['status' => 'error', 'message' => 'A SerpApi nem JSON választ adott.'];
    }
    if (!empty($decoded['error'])) {
        return ['status' => 'error', 'message' => 'SerpApi hiba: ' . text_excerpt((string) $decoded['error'], 180)];
    }

    $results = [];
    foreach (array_slice($decoded['organic_results'] ?? [], 0, (int) $config['max_results']) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $results[] = search_probe_normalize_item([
            'title' => $item['title'] ?? '',
            'url' => $item['link'] ?? $item['url'] ?? '',
            'snippet' => $item['snippet'] ?? $item['description'] ?? '',
            'source' => $item['source'] ?? 'SerpApi Google',
        ]);
    }

    return [
        'status' => $results ? 'completed' : 'empty',
        'results' => $results,
        'raw_result_count' => count($decoded['organic_results'] ?? []),
        'aio_detected' => isset($decoded['ai_overview']) || isset($decoded['ai_overview_results']),
    ];
}

function search_probe_searxng(string $query, array $config): array
{
    $baseUrl = rtrim((string) ($config['searxng_base_url'] ?? ''), '/');
    if ($baseUrl === '') {
        return ['status' => 'error', 'message' => 'Hiányzó SearXNG base URL.'];
    }

    $url = $baseUrl . '/search?' . http_build_query([
        'q' => $query,
        'format' => 'json',
        'language' => (string) ($config['language'] ?? 'hu-HU'),
        'categories' => 'general',
        'safesearch' => '1',
    ]);

    $http = search_probe_http_request('GET', $url, [], null, $config);
    if (!$http['ok']) {
        return ['status' => 'error', 'message' => $http['message']];
    }

    $decoded = json_decode((string) $http['body'], true);
    if (!is_array($decoded)) {
        return ['status' => 'error', 'message' => 'A SearXNG nem JSON választ adott.'];
    }

    $results = [];
    foreach (array_slice($decoded['results'] ?? [], 0, (int) $config['max_results']) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $results[] = search_probe_normalize_item([
            'title' => $item['title'] ?? '',
            'url' => $item['url'] ?? '',
            'snippet' => $item['content'] ?? '',
            'source' => $item['engine'] ?? 'SearXNG',
        ]);
    }

    return [
        'status' => $results ? 'completed' : 'empty',
        'results' => $results,
        'raw_result_count' => count($decoded['results'] ?? []),
    ];
}

function search_probe_langsearch(string $query, array $config): array
{
    $apiKey = trim((string) ($config['langsearch_api_key'] ?? ''));
    if ($apiKey === '') {
        return ['status' => 'error', 'message' => 'Hiányzó LangSearch API kulcs.'];
    }

    $http = search_probe_http_request(
        'POST',
        'https://api.langsearch.com/v1/web-search',
        [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        json_encode([
            'query' => $query,
            'freshness' => 'noLimit',
            'summary' => true,
            'count' => (int) $config['max_results'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $config
    );

    if (!$http['ok']) {
        return ['status' => 'error', 'message' => $http['message']];
    }

    $decoded = json_decode((string) $http['body'], true);
    if (!is_array($decoded)) {
        return ['status' => 'error', 'message' => 'A LangSearch nem JSON választ adott.'];
    }

    $items = search_probe_find_result_items($decoded);
    $results = [];
    foreach (array_slice($items, 0, (int) $config['max_results']) as $item) {
        $results[] = search_probe_normalize_item([
            'title' => $item['title'] ?? $item['name'] ?? '',
            'url' => $item['url'] ?? $item['link'] ?? '',
            'snippet' => $item['snippet'] ?? $item['summary'] ?? $item['content'] ?? '',
            'source' => $item['site_name'] ?? $item['siteName'] ?? 'LangSearch',
        ]);
    }

    return [
        'status' => $results ? 'completed' : 'empty',
        'results' => $results,
        'raw_result_count' => count($items),
    ];
}

function search_probe_jina(string $query, array $config): array
{
    $apiKey = trim((string) ($config['jina_api_key'] ?? ''));
    if ($apiKey === '') {
        return ['status' => 'error', 'message' => 'Hiányzó Jina API kulcs.'];
    }

    $http = search_probe_http_request(
        'GET',
        'https://s.jina.ai/' . rawurlencode($query),
        [
            'Authorization: Bearer ' . $apiKey,
            'Accept: text/plain',
        ],
        null,
        $config
    );

    if (!$http['ok']) {
        return ['status' => 'error', 'message' => $http['message']];
    }

    $results = search_probe_parse_jina_text((string) $http['body'], (int) $config['max_results']);

    return [
        'status' => $results ? 'completed' : 'empty',
        'results' => $results,
        'raw_result_count' => count($results),
        'raw_excerpt' => text_excerpt((string) $http['body'], 1200),
    ];
}

function search_probe_http_request(string $method, string $url, array $headers, ?string $body, array $config): array
{
    $ch = curl_init($url);
    $curlHeaders = array_merge([
        'User-Agent: ' . AUDIT_USER_AGENT,
        'Accept: application/json,text/plain;q=0.9,*/*;q=0.8',
    ], $headers);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => (int) $config['timeout'],
        CURLOPT_CONNECTTIMEOUT => (int) $config['connect_timeout'],
        CURLOPT_HTTPHEADER => $curlHeaders,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body ?? '';
    }

    $caBundle = (string) ($config['ca_bundle'] ?? '');
    if ($caBundle !== '' && is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    curl_setopt_array($ch, $options);

    $responseBody = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'status' => $status,
            'body' => $responseBody,
            'message' => $error ?: ('Keresési API hiba HTTP ' . $status . ': ' . text_excerpt($responseBody, 180)),
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'body' => $responseBody,
        'message' => '',
    ];
}

function search_probe_parse_jina_text(string $text, int $limit): array
{
    $lines = preg_split('~\R+~u', $text) ?: [];
    $indexedItems = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('~^\[(\d+)\]\s+(Title|URL Source|URL|Description)\s*:\s*(.+)$~iu', $line, $match)) {
            $index = (int) $match[1];
            $field = strtolower((string) $match[2]);
            $value = trim((string) $match[3]);
            $indexedItems[$index] ??= ['source' => 'Jina Search'];

            if ($field === 'title') {
                $indexedItems[$index]['title'] = $value;
            } elseif ($field === 'url source' || $field === 'url') {
                $indexedItems[$index]['url'] = rtrim($value, '.,)');
            } elseif ($field === 'description') {
                $indexedItems[$index]['snippet'] = trim(($indexedItems[$index]['snippet'] ?? '') . ' ' . $value);
            }
        }
    }

    if ($indexedItems) {
        ksort($indexedItems);
        $normalized = [];
        foreach ($indexedItems as $item) {
            if (!empty($item['url'])) {
                $normalized[] = search_probe_normalize_item($item);
            }
            if (count($normalized) >= $limit) {
                break;
            }
        }

        if ($normalized) {
            return $normalized;
        }
    }

    $items = [];
    $current = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('~^(?:\[\d+\]\s*)?(?:Title|#)\s*:?\s*(.+)$~iu', $line, $match)) {
            if (!empty($current['url'])) {
                $items[] = search_probe_normalize_item($current);
                $current = [];
            }
            $current['title'] = trim($match[1]);
            continue;
        }

        if (preg_match('~^(?:\[\d+\]\s*)?(?:URL|Url|Source|URL Source)\s*:?\s*(https?://\S+)~iu', $line, $match)) {
            $current['url'] = rtrim($match[1], '.,)');
            continue;
        }

        if (preg_match('~https?://[^\s<>()]+~i', $line, $match)) {
            if (!empty($current['url'])) {
                $items[] = search_probe_normalize_item($current);
                $current = [];
            }
            $current['url'] = rtrim($match[0], '.,)');
        }

        $current['snippet'] = trim(($current['snippet'] ?? '') . ' ' . $line);
        $current['source'] = 'Jina Search';

        if (count($items) >= $limit) {
            break;
        }
    }

    if (!empty($current['url']) && count($items) < $limit) {
        $items[] = search_probe_normalize_item($current);
    }

    return array_values(array_filter(array_slice($items, 0, $limit), static fn (array $item): bool => ($item['url'] ?? '') !== ''));
}

function search_probe_find_result_items(array $decoded): array
{
    $candidatePaths = [
        ['data', 'webPages', 'value'],
        ['data', 'results'],
        ['data'],
        ['webPages', 'value'],
        ['results'],
    ];

    foreach ($candidatePaths as $path) {
        $value = $decoded;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                $value = null;
                break;
            }
            $value = $value[$key];
        }

        if (is_array($value) && search_probe_is_list_of_results($value)) {
            return $value;
        }
    }

    return [];
}

function search_probe_is_list_of_results(array $items): bool
{
    if (!$items) {
        return false;
    }

    $first = reset($items);
    return is_array($first) && (isset($first['url']) || isset($first['link']) || isset($first['title']) || isset($first['name']));
}

function search_probe_normalize_item(array $item): array
{
    $url = normalize_url((string) ($item['url'] ?? '')) ?: (string) ($item['url'] ?? '');
    $host = search_probe_normalize_host((string) parse_url($url, PHP_URL_HOST));

    return [
        'title' => text_excerpt((string) ($item['title'] ?? ''), 140),
        'url' => $url,
        'host' => $host,
        'snippet' => text_excerpt((string) ($item['snippet'] ?? ''), 360),
        'source' => text_excerpt((string) ($item['source'] ?? ''), 80),
    ];
}

function search_probe_mark_domain_hits(array $items, string $targetDomain): array
{
    return array_values(array_map(static function (array $item) use ($targetDomain): array {
        $host = search_probe_normalize_host((string) ($item['host'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST)));
        $item['host'] = $host;
        $item['owned_domain_hit'] = $targetDomain !== '' && ($host === $targetDomain || str_ends_with($host, '.' . $targetDomain));
        return $item;
    }, $items));
}

function search_probe_competitors_from_results(array $items, string $targetDomain): array
{
    $blockedHosts = ['google.com', 'youtube.com', 'facebook.com', 'linkedin.com', 'instagram.com', 'tiktok.com', 'x.com', 'twitter.com'];
    $hosts = [];

    foreach ($items as $item) {
        $host = search_probe_normalize_host((string) ($item['host'] ?? ''));
        if ($host === '' || $host === $targetDomain || str_ends_with($host, '.' . $targetDomain) || in_array($host, $blockedHosts, true)) {
            continue;
        }
        $hosts[] = $host;
    }

    return array_values(array_unique($hosts));
}

function search_probe_normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('~^www\.~i', '', $host) ?: $host;
    return preg_replace('~[^a-z0-9.\-]~i', '', $host) ?: '';
}

function search_probe_cache_key(string $provider, string $query, array $config): string
{
    return sha1(json_encode([
        'provider' => $provider,
        'query' => $query,
        'max_results' => (int) $config['max_results'],
        'language' => (string) ($config['language'] ?? ''),
        'searxng_base_url' => $provider === 'searxng' ? (string) ($config['searxng_base_url'] ?? '') : '',
        'serpapi_google_domain' => $provider === 'serpapi' ? (string) ($config['serpapi_google_domain'] ?? '') : '',
        'serpapi_hl' => $provider === 'serpapi' ? (string) ($config['serpapi_hl'] ?? '') : '',
        'serpapi_gl' => $provider === 'serpapi' ? (string) ($config['serpapi_gl'] ?? '') : '',
        'serpapi_location' => $provider === 'serpapi' ? (string) ($config['serpapi_location'] ?? '') : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function search_probe_cache_dir(): string
{
    $dir = DATA_DIR . '/search_cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function search_probe_cache_path(string $key): string
{
    return search_probe_cache_dir() . '/' . preg_replace('~[^a-f0-9]~', '', $key) . '.json';
}

function search_probe_read_cache(string $key, int $ttlHours): ?array
{
    $path = search_probe_cache_path($key);
    if (!is_file($path)) {
        return null;
    }

    if ($ttlHours > 0 && (time() - filemtime($path)) > ($ttlHours * 3600)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function search_probe_write_cache(string $key, array $payload): void
{
    $payload['cached_at'] = date(DATE_ATOM);
    file_put_contents(
        search_probe_cache_path($key),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}
