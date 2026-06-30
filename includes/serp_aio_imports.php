<?php
/**
 * Google SERP / AI Overview provider export import.
 *
 * A modul provider-semleges: SerpApi, DataForSEO vagy kézi SERP exportból
 * származó JSON/CSV tartalmat próbál egységes bizonyítékká alakítani. A cél
 * nem a providerhez kötött számlázás, hanem hogy a visibility projekthez
 * menthető legyen: mely queryknél volt AI Overview, idézte-e a saját domaint,
 * és milyen versenytárs források jelentek meg.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';

function serp_aio_imports_dir(): string
{
    $dir = DATA_DIR . '/serp_aio_imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function serp_aio_import_path(string $id): string
{
    return serp_aio_imports_dir() . '/' . visibility_safe_id($id) . '.json';
}

function serp_aio_save_import(string $projectId, string $raw, string $filename = ''): array
{
    $projectId = visibility_safe_id($projectId);
    $project = $projectId !== '' ? read_visibility_project($projectId) : null;
    if (!$project) {
        throw new RuntimeException('A SERP/AIO importhoz előbb nyiss meg egy érvényes visibility projektet.');
    }

    $parsed = serp_aio_parse_export($raw, (string) ($project['target_domain'] ?? ''));
    $id = 'serp_aio_import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $import = [
        'id' => $id,
        'project_id' => $projectId,
        'created_at' => date(DATE_ATOM),
        'filename' => $filename,
        'method' => 'Google SERP / AI Overview provider export import',
        'summary' => $parsed['summary'],
        'domain_breakdown' => $parsed['domain_breakdown'],
        'query_records' => $parsed['query_records'],
        'warnings' => $parsed['warnings'],
    ];

    file_put_contents(
        serp_aio_import_path($id),
        json_encode($import, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $import;
}

function serp_aio_parse_export(string $raw, string $targetDomain): array
{
    $raw = trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?: '');
    if ($raw === '') {
        throw new RuntimeException('A SERP/AIO export üres. Másold be vagy töltsd fel a JSON/CSV exportot.');
    }

    $targetDomain = visibility_normalize_domain($targetDomain);
    $decoded = json_decode($raw, true);
    $records = is_array($decoded)
        ? serp_aio_records_from_json($decoded)
        : serp_aio_records_from_csv($raw);

    if (!$records) {
        throw new RuntimeException('Nem találtam feldolgozható SERP/AIO rekordot az exportban.');
    }

    $domainCounts = [];
    $warnings = [];
    $summary = [
        'query_count' => count($records),
        'aio_present_count' => 0,
        'target_cited_count' => 0,
        'target_organic_count' => 0,
        'citation_count' => 0,
        'competitor_domain_count' => 0,
        'aio_presence_rate' => 0,
        'target_citation_rate' => 0,
        'target_organic_rate' => 0,
    ];

    foreach ($records as $index => $record) {
        $records[$index]['id'] = $record['id'] ?? ('serp_query_' . ($index + 1));
        $records[$index]['target_domain'] = $targetDomain;
        $records[$index]['target_cited'] = false;
        $records[$index]['target_organic'] = false;

        if (($record['ai_overview_present'] ?? false) === true) {
            $summary['aio_present_count']++;
        }

        foreach ($record['citations'] ?? [] as $citation) {
            $host = visibility_normalize_domain((string) ($citation['host'] ?? parse_url((string) ($citation['url'] ?? ''), PHP_URL_HOST)));
            if ($host === '') {
                continue;
            }
            $summary['citation_count']++;
            $domainCounts[$host]['domain'] = $host;
            $domainCounts[$host]['citation_mentions'] = ($domainCounts[$host]['citation_mentions'] ?? 0) + 1;
            $domainCounts[$host]['organic_mentions'] = $domainCounts[$host]['organic_mentions'] ?? 0;
            $domainCounts[$host]['is_owned'] = serp_aio_is_owned_host($host, $targetDomain);
            if (serp_aio_is_owned_host($host, $targetDomain)) {
                $records[$index]['target_cited'] = true;
            }
        }

        foreach ($record['organic_results'] ?? [] as $organic) {
            $host = visibility_normalize_domain((string) ($organic['host'] ?? parse_url((string) ($organic['url'] ?? ''), PHP_URL_HOST)));
            if ($host === '') {
                continue;
            }
            $domainCounts[$host]['domain'] = $host;
            $domainCounts[$host]['citation_mentions'] = $domainCounts[$host]['citation_mentions'] ?? 0;
            $domainCounts[$host]['organic_mentions'] = ($domainCounts[$host]['organic_mentions'] ?? 0) + 1;
            $domainCounts[$host]['is_owned'] = serp_aio_is_owned_host($host, $targetDomain);
            if (serp_aio_is_owned_host($host, $targetDomain)) {
                $records[$index]['target_organic'] = true;
            }
        }

        if ($records[$index]['target_cited']) {
            $summary['target_cited_count']++;
        }
        if ($records[$index]['target_organic']) {
            $summary['target_organic_count']++;
        }

        $records[$index]['competitors'] = array_values(array_unique(array_filter(array_map(
            static function (array $item) use ($targetDomain): string {
                $host = visibility_normalize_domain((string) ($item['host'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST)));
                return ($host !== '' && !serp_aio_is_owned_host($host, $targetDomain)) ? $host : '';
            },
            array_merge($record['citations'] ?? [], $record['organic_results'] ?? [])
        ))));
    }

    $domainBreakdown = array_values($domainCounts);
    usort($domainBreakdown, static function (array $a, array $b): int {
        $aScore = (int) ($a['citation_mentions'] ?? 0) * 3 + (int) ($a['organic_mentions'] ?? 0);
        $bScore = (int) ($b['citation_mentions'] ?? 0) * 3 + (int) ($b['organic_mentions'] ?? 0);
        return $bScore <=> $aScore;
    });

    $summary['competitor_domain_count'] = count(array_filter($domainBreakdown, static fn (array $item): bool => ($item['is_owned'] ?? false) !== true));
    $summary['aio_presence_rate'] = $summary['query_count'] > 0 ? (int) round(($summary['aio_present_count'] / $summary['query_count']) * 100) : 0;
    $summary['target_citation_rate'] = $summary['query_count'] > 0 ? (int) round(($summary['target_cited_count'] / $summary['query_count']) * 100) : 0;
    $summary['target_organic_rate'] = $summary['query_count'] > 0 ? (int) round(($summary['target_organic_count'] / $summary['query_count']) * 100) : 0;

    if ($summary['aio_present_count'] === 0) {
        $warnings[] = 'Az importban nem találtam egyértelmű AI Overview blokkot. Ha provider JSON-t használsz, exportáld az ai_overview/items mezőket is.';
    }
    if ($summary['target_cited_count'] === 0) {
        $warnings[] = 'A saját domain nem jelent meg AI Overview citációként ebben az importban.';
    }

    return [
        'summary' => $summary,
        'domain_breakdown' => array_slice($domainBreakdown, 0, 50),
        'query_records' => array_slice($records, 0, 100),
        'warnings' => $warnings,
    ];
}

function serp_aio_records_from_json(array $decoded): array
{
    $candidates = [];
    serp_aio_collect_json_candidates($decoded, $candidates);
    $records = [];

    foreach ($candidates as $candidate) {
        $record = serp_aio_normalize_json_record($candidate);
        if ($record) {
            $records[] = $record;
        }
    }

    return $records;
}

function serp_aio_collect_json_candidates(array $node, array &$candidates): void
{
    if (serp_aio_looks_like_serp_record($node)) {
        $candidates[] = $node;
    }

    foreach ($node as $value) {
        if (is_array($value)) {
            serp_aio_collect_json_candidates($value, $candidates);
        }
    }
}

function serp_aio_looks_like_serp_record(array $node): bool
{
    return isset($node['ai_overview'])
        || isset($node['organic_results'])
        || isset($node['search_parameters'])
        || isset($node['keyword'])
        || (isset($node['type']) && in_array($node['type'], ['ai_overview', 'organic'], true))
        || (isset($node['items']) && is_array($node['items']));
}

function serp_aio_normalize_json_record(array $record): ?array
{
    $query = (string) ($record['search_parameters']['q'] ?? $record['search_parameters']['query'] ?? $record['keyword'] ?? $record['query'] ?? $record['q'] ?? '');
    $aiOverview = is_array($record['ai_overview'] ?? null) ? $record['ai_overview'] : [];
    if (!$aiOverview && is_array($record['ai_overview_results'] ?? null)) {
        $aiOverview = $record['ai_overview_results'];
    }
    $items = is_array($record['items'] ?? null) ? $record['items'] : [];
    $organicResults = is_array($record['organic_results'] ?? null) ? $record['organic_results'] : [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['type'] ?? '') === 'ai_overview') {
            $aiOverview = $item;
        } elseif (($item['type'] ?? '') === 'organic') {
            $organicResults[] = $item;
        }
    }

    $citations = serp_aio_extract_urls_from_node($aiOverview);
    $organic = serp_aio_normalize_result_items($organicResults);

    if ($query === '' && !$citations && !$organic) {
        return null;
    }

    return [
        'query' => $query ?: 'ismeretlen query',
        'ai_overview_present' => !empty($aiOverview) || !empty($citations),
        'citations' => $citations,
        'organic_results' => $organic,
    ];
}

function serp_aio_extract_urls_from_node($node): array
{
    $urls = [];
    serp_aio_collect_urls($node, $urls);
    $seen = [];
    $items = [];
    foreach ($urls as $url) {
        $host = visibility_normalize_domain((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $items[] = ['title' => '', 'url' => $url, 'host' => $host];
    }
    return $items;
}

function serp_aio_collect_urls($node, array &$urls): void
{
    if (is_string($node) && preg_match_all('~https?://[^\s,"\')\]]+~i', $node, $matches)) {
        foreach ($matches[0] as $url) {
            $urls[] = rtrim($url, '.,;)');
        }
        return;
    }
    if (!is_array($node)) {
        return;
    }
    foreach ($node as $key => $value) {
        if (is_string($value) && in_array((string) $key, ['url', 'link', 'source', 'domain'], true) && str_starts_with($value, 'http')) {
            $urls[] = $value;
        }
        serp_aio_collect_urls($value, $urls);
    }
}

function serp_aio_normalize_result_items(array $items): array
{
    $results = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $url = (string) ($item['link'] ?? $item['url'] ?? $item['source'] ?? '');
        if ($url === '' || !str_starts_with($url, 'http')) {
            continue;
        }
        $results[] = [
            'title' => (string) ($item['title'] ?? ''),
            'url' => $url,
            'host' => visibility_normalize_domain((string) parse_url($url, PHP_URL_HOST)),
            'position' => (int) ($item['position'] ?? $item['rank_absolute'] ?? ($index + 1)),
        ];
    }
    return $results;
}

function serp_aio_records_from_csv(string $csv): array
{
    $lines = array_values(array_filter(preg_split('~\R+~u', trim($csv)) ?: [], static fn (string $line): bool => trim($line) !== ''));
    if (count($lines) < 2) {
        return [];
    }

    $delimiter = serp_aio_detect_delimiter($lines[0]);
    $headers = array_map('trim', str_getcsv($lines[0], $delimiter));
    $recordsByQuery = [];

    foreach (array_slice($lines, 1) as $line) {
        $values = str_getcsv($line, $delimiter);
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = trim((string) ($values[$index] ?? ''));
        }

        $query = serp_aio_first_value($row, ['query', 'keyword', 'Search query', 'Keresés', 'q']) ?: 'ismeretlen query';
        $type = text_lower(serp_aio_first_value($row, ['type', 'result_type', 'SERP feature', 'feature']));
        $url = serp_aio_first_value($row, ['url', 'link', 'citation_url', 'source_url', 'Result URL', 'URL']);
        $aioText = text_lower(serp_aio_first_value($row, ['ai_overview', 'AI Overview', 'aio_present', 'SGE', 'overview']));
        $key = md5($query);

        if (!isset($recordsByQuery[$key])) {
            $recordsByQuery[$key] = [
                'query' => $query,
                'ai_overview_present' => str_contains($aioText, 'yes') || str_contains($aioText, 'true') || str_contains($aioText, 'igen') || str_contains($type, 'ai'),
                'citations' => [],
                'organic_results' => [],
            ];
        }

        if ($url !== '' && str_starts_with($url, 'http')) {
            $item = [
                'title' => serp_aio_first_value($row, ['title', 'Title', 'result_title']),
                'url' => $url,
                'host' => visibility_normalize_domain((string) parse_url($url, PHP_URL_HOST)),
                'position' => (int) (serp_aio_first_value($row, ['position', 'rank', 'Rank']) ?: 0),
            ];
            if (str_contains($type, 'ai') || str_contains($type, 'citation') || str_contains($type, 'source')) {
                $recordsByQuery[$key]['ai_overview_present'] = true;
                $recordsByQuery[$key]['citations'][] = $item;
            } else {
                $recordsByQuery[$key]['organic_results'][] = $item;
            }
        }
    }

    return array_values($recordsByQuery);
}

function serp_aio_detect_delimiter(string $headerLine): string
{
    $candidates = [',' => substr_count($headerLine, ','), ';' => substr_count($headerLine, ';'), "\t" => substr_count($headerLine, "\t")];
    arsort($candidates);
    return (string) array_key_first($candidates);
}

function serp_aio_first_value(array $row, array $keys): string
{
    $normalized = [];
    foreach ($row as $key => $value) {
        $normalized[text_lower(trim((string) $key))] = (string) $value;
    }
    foreach ($keys as $key) {
        $direct = $row[$key] ?? null;
        if ($direct !== null && trim((string) $direct) !== '') {
            return trim((string) $direct);
        }
        $lower = text_lower($key);
        if (isset($normalized[$lower]) && trim($normalized[$lower]) !== '') {
            return trim($normalized[$lower]);
        }
    }
    return '';
}

function serp_aio_is_owned_host(string $host, string $targetDomain): bool
{
    return $targetDomain !== '' && ($host === $targetDomain || str_ends_with($host, '.' . $targetDomain));
}

function serp_aio_run_serpapi_project(string $projectId, int $queryLimit = 3, bool $usePortfolio = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A SerpApi live lekérdezéshez szükséges cURL PHP kiterjesztés nem elérhető.');
    }

    $projectId = visibility_safe_id($projectId);
    $project = $projectId !== '' ? read_visibility_project($projectId) : null;
    if (!$project) {
        throw new RuntimeException('A SerpApi futtatáshoz előbb nyiss meg egy érvényes visibility projektet.');
    }

    $config = search_probe_config();
    if (trim((string) ($config['serpapi_api_key'] ?? '')) === '') {
        throw new RuntimeException('A SerpApi kulcs nincs beállítva a data/search_config.php fájlban.');
    }

    $queryLimit = max(1, min(5, $queryLimit));
    $queries = build_visibility_query_set($project, $queryLimit, $usePortfolio);
    if (!$queries) {
        throw new RuntimeException('Nincs futtatható query a SerpApi live próbához.');
    }

    $batch = [
        'provider' => 'SerpApi',
        'created_at' => date(DATE_ATOM),
        'target_domain' => (string) ($project['target_domain'] ?? ''),
        'queries' => [],
    ];
    $warnings = [];

    foreach ($queries as $index => $queryItem) {
        $query = trim((string) ($queryItem['query'] ?? ''));
        if ($query === '') {
            continue;
        }

        $result = serp_aio_fetch_serpapi_query($query, $config);
        if (($result['status'] ?? '') !== 'completed') {
            $warnings[] = sprintf('%d. query nem futott le: %s', $index + 1, (string) ($result['message'] ?? 'ismeretlen SerpApi hiba'));
            continue;
        }

        $payload = $result['payload'];
        $payload['search_parameters']['q'] = $payload['search_parameters']['q'] ?? $query;
        $payload['local_query_meta'] = [
            'id' => $queryItem['id'] ?? ('serpapi_' . ($index + 1)),
            'type' => $queryItem['type'] ?? 'SerpApi live query',
            'why' => $queryItem['why'] ?? '',
            'expected_signal' => $queryItem['expected_signal'] ?? '',
        ];
        $batch['queries'][] = $payload;
    }

    if (!$batch['queries']) {
        throw new RuntimeException('A SerpApi nem adott feldolgozható választ. ' . implode(' ', array_slice($warnings, 0, 3)));
    }

    $import = serp_aio_save_import(
        $projectId,
        json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'serpapi-live-' . date('Ymd-His') . '.json'
    );
    $import['method'] = 'SerpApi live Google SERP lekérdezés';
    $import['live_query_count'] = count($batch['queries']);
    $import['warnings'] = array_values(array_unique(array_merge($import['warnings'] ?? [], $warnings)));

    file_put_contents(
        serp_aio_import_path((string) $import['id']),
        json_encode($import, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $import;
}

function serp_aio_fetch_serpapi_query(string $query, array $config): array
{
    $params = [
        'engine' => 'google',
        'q' => $query,
        'api_key' => trim((string) ($config['serpapi_api_key'] ?? '')),
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

    return [
        'status' => 'completed',
        'payload' => $decoded,
    ];
}

function list_serp_aio_imports(string $projectId, int $limit = 8): array
{
    $projectId = visibility_safe_id($projectId);
    $files = glob(serp_aio_imports_dir() . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $imports = [];
    foreach ($files as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && (string) ($decoded['project_id'] ?? '') === $projectId) {
            $imports[] = $decoded;
        }
        if (count($imports) >= $limit) {
            break;
        }
    }

    return $imports;
}

function latest_serp_aio_import(string $projectId): ?array
{
    $imports = list_serp_aio_imports($projectId, 1);
    return $imports[0] ?? null;
}
