<?php
/**
 * Gemini Google Search grounding alapú AI visibility próba.
 *
 * Ez a réteg nem klasszikus Google SERP export: a Gemini modell Google Search
 * grounding eszközzel válaszol a visibility projekt kérdéseire, majd a
 * visszakapott groundingMetadata alapján mentjük a citációkat, keresési
 * queryket, saját-domain jeleket és versenytárs forrásokat.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/gemini.php';

function gemini_grounding_runs_dir(): string
{
    $dir = DATA_DIR . '/gemini_grounding_runs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function gemini_grounding_run_path(string $id): string
{
    return gemini_grounding_runs_dir() . '/' . visibility_safe_id($id) . '.json';
}

function run_gemini_grounded_visibility_project(string $projectId, int $queryLimit = 3, bool $usePortfolio = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A Gemini grounded próbához szükséges cURL PHP kiterjesztés nem elérhető.');
    }

    $projectId = visibility_safe_id($projectId);
    $project = $projectId !== '' ? read_visibility_project($projectId) : null;
    if (!$project) {
        throw new RuntimeException('A Gemini grounded futtatáshoz előbb nyiss meg egy érvényes visibility projektet.');
    }

    $config = gemini_config();
    if (($config['enabled'] ?? true) !== true || trim((string) ($config['api_key'] ?? '')) === '') {
        throw new RuntimeException('A Gemini kulcs nincs beállítva a data/gemini_config.php fájlban.');
    }

    $queryLimit = max(1, min(5, $queryLimit));
    $queries = build_visibility_query_set($project, $queryLimit, $usePortfolio);
    if (!$queries) {
        throw new RuntimeException('Nincs futtatható query a Gemini grounded próbához.');
    }

    $targetDomain = visibility_normalize_domain((string) ($project['target_domain'] ?? ''));
    $brand = trim((string) ($project['name'] ?? $targetDomain));
    $records = [];
    $warnings = [];

    foreach ($queries as $index => $queryItem) {
        $query = trim((string) ($queryItem['query'] ?? ''));
        if ($query === '') {
            continue;
        }

        $result = gemini_grounding_single_query($query, $project, $config);
        if (($result['status'] ?? '') !== 'completed') {
            $warnings[] = sprintf('%d. Gemini grounded query nem futott le: %s', $index + 1, (string) ($result['message'] ?? 'ismeretlen hiba'));
            continue;
        }

        $record = gemini_grounding_normalize_record(
            $queryItem,
            (string) ($result['text'] ?? ''),
            is_array($result['grounding_metadata'] ?? null) ? $result['grounding_metadata'] : [],
            $targetDomain,
            $brand
        );
        $record['provider'] = 'Gemini Google Search grounding';
        $record['model'] = (string) ($config['model'] ?? 'gemini-flash-latest');
        $record['usage'] = $result['usage'] ?? null;
        $records[] = $record;
    }

    if (!$records) {
        throw new RuntimeException('A Gemini grounded próba nem adott feldolgozható választ. ' . implode(' ', array_slice($warnings, 0, 3)));
    }

    $summary = gemini_grounding_build_summary($records, $targetDomain);
    $id = 'gemini_grounding_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $run = [
        'id' => $id,
        'project_id' => $projectId,
        'created_at' => date(DATE_ATOM),
        'method' => 'Gemini GenerateContent API Google Search grounding',
        'provider' => 'gemini',
        'model' => (string) ($config['model'] ?? 'gemini-flash-latest'),
        'target_domain' => $targetDomain,
        'target_brand' => $brand,
        'query_count' => count($records),
        'summary' => $summary,
        'query_records' => $records,
        'warnings' => array_values(array_unique(array_filter($warnings))),
        'limitation' => 'Ez Gemini Google Search grounding próba. Nem azonos a teljes Google AI Overview vagy AI Mode felületi megjelenéssel, de valós webes grounding forrásokat és citációs jeleket ad.',
    ];

    file_put_contents(
        gemini_grounding_run_path($id),
        json_encode($run, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $run;
}

function gemini_grounding_single_query(string $query, array $project, array $config): array
{
    $targetDomain = (string) ($project['target_domain'] ?? '');
    $brand = (string) ($project['name'] ?? $targetDomain);
    $prompt = implode("\n", [
        'Futtass Google Search groundingot ehhez az AI visibility mérési kérdéshez.',
        'Válaszolj magyarul, tömören, de forrásokra támaszkodva.',
        'A cél nem reklámszöveg, hanem mérés: megjelenik-e a target brand/domain, milyen versenytársak kerülnek elő, és milyen forrásokra támaszkodik a válasz.',
        'Ne találj ki URL-t. Ha a target nem látszik, mondd ki.',
        '',
        'Target domain: ' . $targetDomain,
        'Target brand/projekt: ' . $brand,
        'Piac: ' . (string) ($project['market'] ?? ''),
        'Üzleti modell: ' . (string) ($project['business_model_label'] ?? $project['business_model'] ?? ''),
        'Kérdés: ' . $query,
    ]);

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'tools' => [
            [
                'google_search' => new stdClass(),
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => max(900, min(2200, (int) ($config['grounded_max_output_tokens'] ?? 1400))),
        ],
    ];

    $timeout = max(60, (int) ($config['grounded_timeout'] ?? $config['timeout'] ?? 150));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $apiResult = gemini_generate_content_request($payload, $config, $timeout, $connectTimeout);

    if (!$apiResult['ok']) {
        return [
            'status' => 'error',
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    if (!is_array($decoded)) {
        return [
            'status' => 'error',
            'message' => 'A Gemini grounded válasz nem JSON.',
        ];
    }

    return [
        'status' => 'completed',
        'text' => gemini_extract_text($decoded),
        'grounding_metadata' => $decoded['candidates'][0]['groundingMetadata'] ?? [],
        'usage' => gemini_extract_usage($decoded),
    ];
}

function gemini_grounding_normalize_record(array $queryItem, string $answer, array $groundingMetadata, string $targetDomain, string $brand): array
{
    $citations = gemini_grounding_extract_citations($groundingMetadata);
    $searchQueries = array_values(array_filter(array_map('strval', $groundingMetadata['webSearchQueries'] ?? [])));
    $answerLower = text_lower($answer);
    $brandLower = text_lower($brand);
    $domainLower = text_lower($targetDomain);

    $targetMentioned = ($brandLower !== '' && str_contains($answerLower, $brandLower))
        || ($domainLower !== '' && str_contains($answerLower, $domainLower));
    $targetCited = false;

    foreach ($citations as $citation) {
        $host = visibility_normalize_domain((string) ($citation['host'] ?? ''));
        if ($targetDomain !== '' && ($host === $targetDomain || str_ends_with($host, '.' . $targetDomain))) {
            $targetCited = true;
            break;
        }
    }

    $blockedHosts = ['google.com', 'vertexaisearch.cloud.google.com'];
    $competitors = array_values(array_unique(array_filter(array_map(
        static function (array $citation) use ($targetDomain, $blockedHosts): string {
            $host = visibility_normalize_domain((string) ($citation['host'] ?? ''));
            if ($host === '' || in_array($host, $blockedHosts, true) || $host === $targetDomain || str_ends_with($host, '.' . $targetDomain)) {
                return '';
            }
            return $host;
        },
        $citations
    ))));

    return [
        'id' => $queryItem['id'] ?? ('gemini_query_' . bin2hex(random_bytes(3))),
        'type' => $queryItem['type'] ?? 'Gemini grounded query',
        'query' => (string) ($queryItem['query'] ?? ''),
        'why' => (string) ($queryItem['why'] ?? ''),
        'answer_summary' => text_excerpt($answer, 1400),
        'search_queries' => $searchQueries,
        'citations' => $citations,
        'citation_count' => count($citations),
        'target_mentioned' => $targetMentioned,
        'target_cited' => $targetCited,
        'competitors' => $competitors,
    ];
}

function gemini_grounding_extract_citations(array $groundingMetadata): array
{
    $chunks = is_array($groundingMetadata['groundingChunks'] ?? null) ? $groundingMetadata['groundingChunks'] : [];
    $supports = is_array($groundingMetadata['groundingSupports'] ?? null) ? $groundingMetadata['groundingSupports'] : [];
    $supportByIndex = [];

    foreach ($supports as $support) {
        if (!is_array($support)) {
            continue;
        }
        foreach (($support['groundingChunkIndices'] ?? []) as $chunkIndex) {
            $supportByIndex[(int) $chunkIndex][] = (string) ($support['segment']['text'] ?? '');
        }
    }

    $citations = [];
    $seen = [];
    foreach ($chunks as $index => $chunk) {
        if (!is_array($chunk)) {
            continue;
        }
        $web = is_array($chunk['web'] ?? null) ? $chunk['web'] : [];
        $url = (string) ($web['uri'] ?? $web['url'] ?? '');
        $title = trim((string) ($web['title'] ?? ''));
        if ($url === '' && $title === '') {
            continue;
        }
        $host = visibility_normalize_domain((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'vertexaisearch.cloud.google.com') {
            $titleHost = visibility_normalize_domain($title);
            if ($titleHost !== '') {
                $host = $titleHost;
            }
        }
        $key = $url !== '' ? $url : ($host . '|' . $title);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $citations[] = [
            'title' => $title,
            'url' => $url,
            'host' => $host,
            'segments' => array_values(array_filter(array_unique($supportByIndex[$index] ?? []))),
        ];
    }

    return $citations;
}

function gemini_grounding_build_summary(array $records, string $targetDomain): array
{
    $domainCounts = [];
    $targetMentioned = 0;
    $targetCited = 0;
    $citationCount = 0;
    $groundedCount = 0;

    foreach ($records as $record) {
        if (($record['target_mentioned'] ?? false) === true) {
            $targetMentioned++;
        }
        if (($record['target_cited'] ?? false) === true) {
            $targetCited++;
        }
        if (!empty($record['citations']) || !empty($record['search_queries'])) {
            $groundedCount++;
        }

        foreach ($record['citations'] ?? [] as $citation) {
            $host = visibility_normalize_domain((string) ($citation['host'] ?? ''));
            if ($host === '') {
                continue;
            }
            $citationCount++;
            $domainCounts[$host]['domain'] = $host;
            $domainCounts[$host]['mentions'] = ($domainCounts[$host]['mentions'] ?? 0) + 1;
            $domainCounts[$host]['is_owned'] = $targetDomain !== '' && ($host === $targetDomain || str_ends_with($host, '.' . $targetDomain));
        }
    }

    $domainBreakdown = array_values($domainCounts);
    usort($domainBreakdown, static fn (array $a, array $b): int => ((int) ($b['mentions'] ?? 0)) <=> ((int) ($a['mentions'] ?? 0)));
    $queryCount = count($records);

    return [
        'query_count' => $queryCount,
        'grounded_count' => $groundedCount,
        'target_mentioned_count' => $targetMentioned,
        'target_cited_count' => $targetCited,
        'citation_count' => $citationCount,
        'competitor_domain_count' => count(array_filter($domainBreakdown, static fn (array $item): bool => ($item['is_owned'] ?? false) !== true)),
        'grounded_rate' => $queryCount > 0 ? (int) round(($groundedCount / $queryCount) * 100) : 0,
        'target_mention_rate' => $queryCount > 0 ? (int) round(($targetMentioned / $queryCount) * 100) : 0,
        'target_citation_rate' => $queryCount > 0 ? (int) round(($targetCited / $queryCount) * 100) : 0,
        'domain_breakdown' => array_slice($domainBreakdown, 0, 30),
    ];
}

function list_gemini_grounding_runs(string $projectId, int $limit = 8): array
{
    $projectId = visibility_safe_id($projectId);
    $files = glob(gemini_grounding_runs_dir() . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $runs = [];
    foreach ($files as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && (string) ($decoded['project_id'] ?? '') === $projectId) {
            $runs[] = $decoded;
        }
        if (count($runs) >= $limit) {
            break;
        }
    }

    return $runs;
}

function latest_gemini_grounding_run(string $projectId): ?array
{
    $runs = list_gemini_grounding_runs($projectId, 1);
    return $runs[0] ?? null;
}
