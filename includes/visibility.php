<?php
/**
 * AI keresési láthatóság mérési modul.
 *
 * Ez a réteg az egyszeri weboldal-audit mellé ad egy ismételhető mérési
 * munkafolyamatot: projektet ment, témákból kérdéssort épít, keresési
 * providereken lekéri a találatokat, majd külön JSON futásként tárolja, hogy
 * később trendként és versenytárs-összevetésként is visszanézhető legyen.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/search_providers.php';

function visibility_projects_dir(): string
{
    $dir = DATA_DIR . '/visibility_projects';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function visibility_runs_dir(): string
{
    $dir = DATA_DIR . '/visibility_runs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function visibility_safe_id(string $id): string
{
    return preg_replace('~[^a-zA-Z0-9_\-]~', '', $id) ?: '';
}

function visibility_project_path(string $id): string
{
    return visibility_projects_dir() . '/' . visibility_safe_id($id) . '.json';
}

function visibility_run_path(string $id): string
{
    return visibility_runs_dir() . '/' . visibility_safe_id($id) . '.json';
}

function read_visibility_run(string $id): ?array
{
    $path = visibility_run_path($id);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function delete_visibility_run(string $id, string $projectId = ''): bool
{
    $run = read_visibility_run($id);
    if (!$run) {
        return false;
    }

    if ($projectId !== '' && (string) ($run['project_id'] ?? '') !== $projectId) {
        return false;
    }

    $path = visibility_run_path($id);
    return is_file($path) && @unlink($path);
}

function visibility_parse_list(string $value, int $limit = 20): array
{
    $items = preg_split('~[\r\n,;]+~u', $value) ?: [];
    $clean = [];
    foreach ($items as $item) {
        $item = trim(preg_replace('~\s+~u', ' ', $item) ?: '');
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return array_slice(array_values(array_unique($clean)), 0, $limit);
}

function visibility_parse_query_portfolio(string $value, int $limit = 20): array
{
    $lines = preg_split('~\R+~u', trim($value)) ?: [];
    $portfolio = [];

    foreach ($lines as $line) {
        $line = trim(preg_replace('~\s+~u', ' ', $line) ?: '');
        if ($line === '') {
            continue;
        }

        $category = 'manual';
        $query = $line;
        if (preg_match('~^([a-zA-Z0-9_\- áéíóöőúüűÁÉÍÓÖŐÚÜŰ]+)\s*[:|]\s*(.+)$~u', $line, $match)) {
            $category = visibility_safe_id(text_lower(trim($match[1]))) ?: 'manual';
            $query = trim($match[2]);
        }

        if ($query === '') {
            continue;
        }

        $portfolio[] = [
            'id' => 'portfolio_' . (count($portfolio) + 1),
            'type' => 'Top 20 portfólió: ' . visibility_portfolio_category_label($category),
            'category' => $category,
            'query' => $query,
            'why' => 'Rögzített heti monitoring kérdés. Ugyanezzel a kérdéssel érdemes újramérni, hogy az idősor összehasonlítható legyen.',
            'expected_signal' => 'Saját-domain jelenlét, versenytárs említés, top források és változás az előző heti futáshoz képest.',
            'source' => 'weekly_portfolio',
        ];
    }

    return array_slice($portfolio, 0, $limit);
}

function visibility_portfolio_category_label(string $category): string
{
    $labels = [
        'brand' => 'brand',
        'buyer' => 'buyer intent',
        'buyerintent' => 'buyer intent',
        'comparison' => 'összehasonlítás',
        'competitor' => 'versenytárs',
        'pricing' => 'ár',
        'local' => 'helyi',
        'expert' => 'szakértői',
        'problem' => 'probléma',
        'manual' => 'saját',
    ];

    return $labels[$category] ?? $category;
}

function visibility_business_models(): array
{
    return [
        'b2b_service' => 'B2B szolgáltatás',
        'local_service' => 'Helyi szolgáltatás',
        'ecommerce' => 'E-commerce',
        'saas' => 'SaaS / szoftver',
        'expert_brand' => 'Szakértői brand',
        'generic' => 'Általános weboldal',
    ];
}

function visibility_normalize_business_model(string $value): string
{
    $value = visibility_safe_id($value);
    return array_key_exists($value, visibility_business_models()) ? $value : 'generic';
}

function visibility_normalize_domain(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $url = normalize_url($value);
    $host = $url ? (string) parse_url($url, PHP_URL_HOST) : $value;
    $host = strtolower(trim($host));
    $host = preg_replace('~^www\.~i', '', $host) ?: $host;
    return preg_replace('~[^a-z0-9.\-]~i', '', $host) ?: '';
}

function visibility_project_from_input(array $input, array $existing = []): array
{
    $siteUrl = normalize_url((string) ($input['site_url'] ?? ''));
    if ($siteUrl === null || !is_public_target_url($siteUrl)) {
        throw new RuntimeException('Adj meg egy publikus, ellenőrizhető domaint vagy URL-t.');
    }

    $topics = visibility_parse_list((string) ($input['topics'] ?? ''), 12);
    if (!$topics) {
        throw new RuntimeException('Legalább egy témát vagy vevői kérdéskört adj meg.');
    }

    $customQueries = visibility_parse_list((string) ($input['custom_queries'] ?? ''), 16);
    $queryPortfolio = visibility_parse_query_portfolio((string) ($input['query_portfolio'] ?? ''), 20);
    $businessModel = visibility_normalize_business_model((string) ($input['business_model'] ?? 'generic'));
    $queryLimit = max(4, min(20, (int) ($input['query_limit'] ?? ($existing['query_limit'] ?? 12))));

    $competitors = [];
    foreach (visibility_parse_list((string) ($input['competitors'] ?? ''), 12) as $competitor) {
        $domain = visibility_normalize_domain($competitor);
        if ($domain !== '') {
            $competitors[] = $domain;
        }
    }

    $host = visibility_normalize_domain($siteUrl);
    $id = visibility_safe_id((string) ($input['id'] ?? ''));
    $sourceReportId = visibility_safe_id((string) ($input['source_report_id'] ?? ($existing['source_report_id'] ?? '')));
    $rawAuditContext = trim((string) ($input['audit_context_json'] ?? ''));
    $auditContext = $rawAuditContext !== ''
        ? visibility_sanitize_audit_context($rawAuditContext)
        : null;

    return [
        'id' => $id,
        'name' => trim((string) ($input['name'] ?? '')) ?: $host,
        'site_url' => $siteUrl,
        'target_domain' => $host,
        'language' => trim((string) ($input['language'] ?? 'hu')) ?: 'hu',
        'market' => trim((string) ($input['market'] ?? 'Magyarország')) ?: 'Magyarország',
        'business_model' => $businessModel,
        'business_model_label' => visibility_business_models()[$businessModel],
        'query_limit' => $queryLimit,
        'topics' => $topics,
        'custom_queries' => $customQueries,
        'query_portfolio' => $queryPortfolio,
        'competitors' => array_slice(array_values(array_unique($competitors)), 0, 12),
        'source_report_id' => $sourceReportId,
        'audit_context' => $auditContext,
        'created_at' => $existing['created_at'] ?? date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
    ];
}

function visibility_sanitize_audit_context(string $rawJson, $fallback = null): ?array
{
    $decoded = null;
    if (trim($rawJson) !== '') {
        $decoded = json_decode($rawJson, true);
    }

    if (!is_array($decoded)) {
        return is_array($fallback) ? $fallback : null;
    }

    $recommendations = [];
    foreach (array_slice(is_array($decoded['top_recommendations'] ?? null) ? $decoded['top_recommendations'] : [], 0, 8) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $recommendations[] = [
            'level' => text_excerpt((string) ($item['level'] ?? ''), 24),
            'category' => text_excerpt((string) ($item['category'] ?? ''), 80),
            'title' => text_excerpt((string) ($item['title'] ?? ''), 180),
            'next_step' => text_excerpt((string) ($item['next_step'] ?? ''), 280),
            'count' => max(0, (int) ($item['count'] ?? 0)),
        ];
    }

    return [
        'report_id' => visibility_safe_id((string) ($decoded['report_id'] ?? '')),
        'source_url' => text_excerpt((string) ($decoded['source_url'] ?? ''), 300),
        'created_at' => text_excerpt((string) ($decoded['created_at'] ?? ''), 64),
        'overall_score' => max(0, min(100, (int) ($decoded['overall_score'] ?? 0))),
        'summary_label' => text_excerpt((string) ($decoded['summary_label'] ?? ''), 80),
        'pages_checked' => max(0, (int) ($decoded['pages_checked'] ?? 0)),
        'critical_count' => max(0, (int) ($decoded['critical_count'] ?? 0)),
        'warning_count' => max(0, (int) ($decoded['warning_count'] ?? 0)),
        'scores' => array_filter([
            'technical' => isset($decoded['scores']['technical']) ? max(0, min(100, (int) $decoded['scores']['technical'])) : null,
            'seo' => isset($decoded['scores']['seo']) ? max(0, min(100, (int) $decoded['scores']['seo'])) : null,
            'aio' => isset($decoded['scores']['aio']) ? max(0, min(100, (int) $decoded['scores']['aio'])) : null,
            'visibility' => isset($decoded['scores']['visibility']) ? max(0, min(100, (int) $decoded['scores']['visibility'])) : null,
            'entity' => isset($decoded['scores']['entity']) ? max(0, min(100, (int) $decoded['scores']['entity'])) : null,
        ], static fn ($value): bool => $value !== null),
        'static_readiness' => is_array($decoded['static_readiness'] ?? null) ? array_slice($decoded['static_readiness'], 0, 8, true) : [],
        'top_recommendations' => $recommendations,
    ];
}

function visibility_save_project(array $input): array
{
    $id = visibility_safe_id((string) ($input['id'] ?? ''));
    $existing = $id !== '' ? (read_visibility_project($id) ?: []) : [];
    $project = visibility_project_from_input($input, $existing);

    if (($project['id'] ?? '') === '') {
        $project['id'] = 'visibility_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    file_put_contents(
        visibility_project_path((string) $project['id']),
        json_encode($project, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $project;
}

function read_visibility_project(string $id): ?array
{
    $path = visibility_project_path($id);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function list_visibility_projects(int $limit = 20): array
{
    $files = glob(visibility_projects_dir() . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $projects = [];
    foreach (array_slice($files, 0, $limit) as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $latestRun = latest_visibility_run_for_project((string) ($decoded['id'] ?? ''));
            $decoded['latest_run'] = $latestRun;
            $projects[] = $decoded;
        }
    }

    return $projects;
}

function build_visibility_query_set(array $project, int $limit = 16, bool $usePortfolio = false): array
{
    if ($usePortfolio) {
        $portfolio = is_array($project['query_portfolio'] ?? null) ? $project['query_portfolio'] : [];
        return array_slice(array_values($portfolio), 0, max(4, min(20, $limit)));
    }

    $market = (string) ($project['market'] ?? 'Magyarország');
    $domain = (string) ($project['target_domain'] ?? '');
    $competitors = array_values(array_filter($project['competitors'] ?? []));
    $businessModel = visibility_normalize_business_model((string) ($project['business_model'] ?? 'generic'));
    $queries = [];

    foreach (($project['custom_queries'] ?? []) as $customIndex => $customQuery) {
        $customQuery = trim((string) $customQuery);
        if ($customQuery === '') {
            continue;
        }

        $queries[] = [
            'id' => 'custom_' . ($customIndex + 1),
            'type' => 'saját mérési kérdés',
            'query' => $customQuery,
            'why' => 'Ezt a kérdést kézzel adtad meg, ezért elsőbbséget kap a mérésben.',
            'expected_signal' => 'Megjelenik-e a saját domain, milyen versenytársak és források kerülnek elő.',
        ];
    }

    foreach (($project['topics'] ?? []) as $topicIndex => $topic) {
        $topic = trim((string) $topic);
        if ($topic === '') {
            continue;
        }

        $queries = array_merge($queries, visibility_model_queries($businessModel, $topic, $topicIndex, $market));

        if ($competitors) {
            $queries[] = [
                'id' => 'compare_' . ($topicIndex + 1),
                'type' => 'versenytárs összevetés',
                'query' => "{$domain} alternatívái {$topic} témában " . implode(' ', array_slice($competitors, 0, 3)),
                'why' => 'Megmutatja, milyen domainhalmazzal kerül egy döntési térbe a márka.',
                'expected_signal' => 'Co-mention, alternatíva-listák, összehasonlító tartalmak.',
            ];
        }
    }

    return array_slice($queries, 0, max(4, min(24, $limit)));
}

function visibility_model_queries(string $businessModel, string $topic, int $topicIndex, string $market): array
{
    $number = $topicIndex + 1;
    $templates = [
        'b2b_service' => [
            ['problem', 'probléma alapú B2B kérdés', "Milyen partnert érdemes választani {$topic} területén {$market}?", 'B2B döntésnél partnerbizalom, bizonyíték és szakmai alkalmasság számít.'],
            ['case', 'esettanulmány keresés', "{$topic} esettanulmány és referencia {$market}", 'Az AI rendszerek gyakran bizonyítékot, referenciát és konkrét eredményeket keresnek.'],
            ['decision', 'beszerzési döntés', "Milyen szempontok alapján válasszunk {$topic} szolgáltatót?", 'A döntéstámogató tartalom citálhatóbb, mint az általános szolgáltatásleírás.'],
        ],
        'local_service' => [
            ['nearby', 'helyi ajánlási kérdés', "Legjobb {$topic} szolgáltató a közelemben {$market}", 'Helyi keresésnél lokáció, értékelés, elérhetőség és bizalom dönt.'],
            ['price', 'ár és folyamat kérdés', "Mennyibe kerül a {$topic} szolgáltatás és hogyan zajlik?", 'A felhasználók gyakran árra, időre és folyamatra kérdeznek rá.'],
            ['trust', 'helyi bizalmi kérdés', "Hogyan találok megbízható {$topic} szakembert?", 'A helyi szolgáltatásoknál a bizalmi jelek és vélemények fontos forrásminták.'],
        ],
        'ecommerce' => [
            ['best_product', 'termékválasztási kérdés', "Melyik {$topic} terméket érdemes megvenni {$market}?", 'E-commerce esetén az AI válaszok gyakran termékajánló és összehasonlító logikát követnek.'],
            ['comparison', 'összehasonlító kérdés', "{$topic} összehasonlítás ár értékelés garancia", 'A termékadatok, készlet, ár és értékelés együtt befolyásolja a láthatóságot.'],
            ['buying_guide', 'vásárlási útmutató', "Mire figyeljek {$topic} vásárlásakor?", 'A vásárlási útmutató erős answer-first és citációs tartalom lehet.'],
        ],
        'saas' => [
            ['alternative', 'SaaS alternatíva kérdés', "Legjobb {$topic} szoftver alternatívák {$market}", 'SaaS döntéseknél alternatívák, funkciók és integrációk kerülnek elő.'],
            ['feature', 'funkció alapú kérdés', "Milyen funkciók kellenek egy jó {$topic} szoftverhez?", 'Az AI válaszok gyakran funkciólistákból és use case-ekből építkeznek.'],
            ['implementation', 'bevezetési kérdés', "Hogyan lehet bevezetni egy {$topic} rendszert céges környezetben?", 'A bevezetési tartalom B2B/SaaS esetén erős döntéstámogató forrás.'],
        ],
        'expert_brand' => [
            ['expert', 'szakértő keresés', "Ki ért legjobban ehhez: {$topic} {$market}?", 'Szakértői brandnél személy, hitelesség és publikált tudás is mérendő.'],
            ['explain', 'magyarázó kérdés', "Mi az a {$topic} és hogyan érdemes jól csinálni?", 'A definíciós és edukációs tartalom gyakran jól idézhető AI válaszokban.'],
            ['opinion', 'szakmai vélemény kérdés', "{$topic} szakértői vélemény trendek és módszertan", 'A szakmai álláspont és módszertan segíti az entitásbizalmat.'],
        ],
        'generic' => [
            ['problem', 'probléma alapú kérdés', "Milyen céget vagy megoldást érdemes választani erre: {$topic} {$market}?", 'A vásárló nem márkát keres, hanem döntési segítséget.'],
            ['best', 'ajánlási kérdés', "Legjobb {$topic} szolgáltatók {$market}", 'A klasszikus AI ajánló-válaszok gyakran shortlist kérdésekből indulnak.'],
            ['trust', 'bizalmi kérdés', "Hogyan válasszak megbízható partnert {$topic} témában?", 'A bizalmi és bizonyíték alapú tartalmak AI válaszban könnyebben idézhetők.'],
        ],
    ];

    $selectedTemplates = $templates[$businessModel] ?? $templates['generic'];
    return array_map(static function (array $template) use ($number): array {
        return [
            'id' => $template[0] . '_' . $number,
            'type' => $template[1],
            'query' => $template[2],
            'why' => $template[3],
            'expected_signal' => 'Saját-domain találat, domináns versenytársak és citálható forrásminták.',
        ];
    }, $selectedTemplates);
}

function save_visibility_run(array $run): void
{
    if (empty($run['id'])) {
        throw new RuntimeException('Hiányzó visibility futás azonosító.');
    }

    file_put_contents(
        visibility_run_path((string) $run['id']),
        json_encode($run, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function run_visibility_tracking(array $project, int $queryLimit = 12, ?callable $progress = null, array $options = []): array
{
    $config = search_probe_config();
    $providers = search_probe_available_providers($config);
    if (!$providers) {
        throw new RuntimeException('Nincs aktív keresési provider. Állíts be LangSearch, Jina vagy SearXNG forrást a data/search_config.php fájlban.');
    }

    if ($progress) {
        $progress(8, 'Visibility mérés előkészítése', 'Projekt, témák és keresési providerek ellenőrzése.');
    }

    $targetDomain = (string) ($project['target_domain'] ?? '');
    $configuredCompetitors = array_values(array_filter($project['competitors'] ?? []));
    $runMode = (string) ($options['run_mode'] ?? 'generated');
    $usePortfolio = $runMode === 'weekly_portfolio';
    $queries = build_visibility_query_set($project, $queryLimit, $usePortfolio);
    $queryResults = [];
    $domainCounts = [];
    $competitorCounts = [];
    $ownedHits = 0;
    $ownedPositions = [];
    $errors = [];

    foreach ($queries as $index => $queryItem) {
        $query = (string) ($queryItem['query'] ?? '');

        if ($progress) {
            $progress(
                14 + (int) floor(($index / max(1, count($queries))) * 52),
                'Kérdéssoros keresés',
                sprintf('%d/%d. kérdés futtatása: %s', $index + 1, count($queries), text_excerpt($query, 90))
            );
        }

        $searchResult = search_probe_query($query, $providers, $config);
        $items = search_probe_mark_domain_hits($searchResult['results'] ?? [], $targetDomain);

        foreach ($items as $position => $item) {
            $items[$position]['position'] = $position + 1;
            $host = (string) ($item['host'] ?? '');
            if ($host !== '') {
                $domainCounts[$host] = ($domainCounts[$host] ?? 0) + 1;
            }
        }

        $ownResults = array_values(array_filter($items, static fn (array $item): bool => ($item['owned_domain_hit'] ?? false) === true));
        if ($ownResults) {
            $ownedHits++;
            $ownedPositions[] = (int) ($ownResults[0]['position'] ?? 0);
        }

        foreach (search_probe_competitors_from_results($items, $targetDomain) as $host) {
            $competitorCounts[$host] = ($competitorCounts[$host] ?? 0) + 1;
        }

        if (($searchResult['status'] ?? '') === 'error') {
            $errors[] = (string) ($searchResult['message'] ?? 'Keresési provider hiba.');
        }

        $queryResults[] = [
            'id' => $queryItem['id'] ?? ('query_' . ($index + 1)),
            'type' => $queryItem['type'] ?? 'query',
            'query' => $query,
            'why' => $queryItem['why'] ?? '',
            'expected_signal' => $queryItem['expected_signal'] ?? '',
            'provider' => $searchResult['provider'] ?? '',
            'status' => $searchResult['status'] ?? 'unknown',
            'from_cache' => (bool) ($searchResult['from_cache'] ?? false),
            'owned_domain_hit' => (bool) $ownResults,
            'own_results' => $ownResults,
            'competitors' => search_probe_competitors_from_results($items, $targetDomain),
            'results' => array_slice($items, 0, (int) ($config['max_results'] ?? 8)),
            'error' => $searchResult['message'] ?? '',
        ];
    }

    $successfulQueries = count(array_filter($queryResults, static function (array $item): bool {
        return ($item['status'] ?? '') !== 'error' && count($item['results'] ?? []) > 0;
    }));
    if ($successfulQueries === 0) {
        $message = $errors
            ? 'A visibility keresési provider nem adott használható találatot. Részlet: ' . implode(' | ', array_slice(array_values(array_unique($errors)), 0, 3))
            : 'A visibility keresési provider nem adott használható találatot. Ellenőrizd a keresési API kulcsot és a provider beállítást.';
        throw new RuntimeException($message);
    }

    arsort($domainCounts);
    arsort($competitorCounts);
    $totalQueries = count($queryResults);
    $totalMentions = max(1, array_sum($domainCounts));
    $shareOfVoice = [];
    foreach (array_slice($domainCounts, 0, 12, true) as $host => $count) {
        $shareOfVoice[] = [
            'domain' => $host,
            'mentions' => $count,
            'share' => (int) round(($count / $totalMentions) * 100),
            'is_owned' => $host === $targetDomain || str_ends_with($host, '.' . $targetDomain),
            'is_configured_competitor' => in_array($host, $configuredCompetitors, true),
        ];
    }

    $competitorTable = [];
    foreach (array_slice($competitorCounts, 0, 12, true) as $host => $count) {
        $competitorTable[] = [
            'domain' => $host,
            'query_hits' => $count,
            'configured' => in_array($host, $configuredCompetitors, true),
        ];
    }

    $hitRate = $totalQueries > 0 ? (int) round(($ownedHits / $totalQueries) * 100) : 0;
    $avgPosition = $ownedPositions ? round(array_sum($ownedPositions) / count($ownedPositions), 1) : null;
    $confidence = visibility_confidence_label($totalQueries, count($providers), count($errors), $hitRate);

    if ($progress) {
        $progress(70, 'Eredmények összesítése', 'Saját-domain jelenlét, share of voice és versenytársak számítása.');
    }

    $run = [
        'id' => 'visibility_run_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)),
        'project_id' => (string) ($project['id'] ?? ''),
        'created_at' => date(DATE_ATOM),
        'method' => 'Kérdéssoros keresési láthatóságmérés mentett provider-adatokból',
        'run_mode' => $usePortfolio ? 'weekly_portfolio' : 'generated',
        'portfolio_used' => $usePortfolio,
        'providers' => $providers,
        'target_domain' => $targetDomain,
        'query_count' => $totalQueries,
        'owned_query_hits' => $ownedHits,
        'visibility_rate' => $hitRate,
        'average_owned_position' => $avgPosition,
        'confidence' => $confidence,
        'share_of_voice' => $shareOfVoice,
        'competitors' => $competitorTable,
        'query_results' => $queryResults,
        'errors' => array_values(array_unique(array_filter($errors))),
        'interpretation' => visibility_interpretation($hitRate, $avgPosition, $confidence),
    ];
    $run['opportunity_backlog'] = visibility_build_opportunity_backlog($project, $run);
    $run['evidence_explanation'] = visibility_build_evidence_explanation($project, $run);
    $run['resource_summary'] = visibility_build_resource_summary($run);
    $run['report_layers'] = visibility_build_report_layers($project, $run);

    if ($progress) {
        $progress(76, 'Visibility futás mentése', 'A mérési eredmények JSON fájlba kerülnek.');
    }

    save_visibility_run($run);

    return $run;
}

function visibility_confidence_label(int $queryCount, int $providerCount, int $errorCount, int $hitRate): array
{
    $score = min(60, $queryCount * 4) + min(20, $providerCount * 10) - min(30, $errorCount * 10);
    if ($queryCount >= 12 && $providerCount >= 1 && $errorCount === 0) {
        $score += 10;
    }

    if ($score >= 75) {
        return [
            'level' => 'erős irányadó jel',
            'score' => min(100, $score),
            'note' => 'Elég kérdés futott ahhoz, hogy a trendet komolyan lehessen venni, de az AI válaszok természetes változékonysága miatt továbbra sem abszolút mérés.',
        ];
    }

    if ($score >= 45) {
        return [
            'level' => 'irányadó jel',
            'score' => max(0, $score),
            'note' => 'Hasznos pillanatkép, de ismételt futásokkal és több kérdéssel érdemes megerősíteni.',
        ];
    }

    return [
        'level' => 'gyenge minta',
        'score' => max(0, $score),
        'note' => 'Kevés kérdés, kevés provider vagy providerhiba miatt csak óvatosan értelmezhető.',
    ];
}

function visibility_interpretation(int $hitRate, ?float $avgPosition, array $confidence): string
{
    if ($hitRate >= 70) {
        return 'A saját domain gyakran megjelenik a vizsgált kérdések találati terében. A következő lépés a citálható, answer-first tartalom és külső hivatkozási jelek erősítése.';
    }

    if ($hitRate >= 35) {
        return 'A domain már látható, de nem stabil. Érdemes a gyenge témákra célzott útmutatókat, összehasonlító oldalakat és bizonyítékblokkokat készíteni.';
    }

    return 'A saját domain jelenleg ritkán kerül elő a vizsgált kérdésekre. Első körben témánként egy erős, jól strukturált döntéstámogató oldal és külső említési stratégia kell.';
}

/**
 * Ügyfélbarát mérési értelmezés.
 *
 * A visibility mérés keresési providerek mentett találataiból dolgozik, ezért
 * fontos külön kezelni a ténylegesen rögzített adatokat és az ezekből levont
 * irányadó AI-láthatósági következtetéseket. Ez a blokk később változtatás
 * nélkül mehet a dashboardra és a PDF riportba is.
 */
function visibility_build_evidence_explanation(array $project, array $run): array
{
    $providers = array_values(array_filter($run['providers'] ?? []));
    $queryCount = (int) ($run['query_count'] ?? 0);
    $ownedHits = (int) ($run['owned_query_hits'] ?? 0);
    $visibilityRate = (int) ($run['visibility_rate'] ?? 0);
    $targetDomain = (string) ($run['target_domain'] ?? ($project['target_domain'] ?? ''));
    $runMode = (string) ($run['run_mode'] ?? 'generated');
    $share = is_array($run['share_of_voice'] ?? null) ? $run['share_of_voice'] : [];
    $competitors = is_array($run['competitors'] ?? null) ? $run['competitors'] : [];
    $queries = is_array($run['query_results'] ?? null) ? $run['query_results'] : [];
    $confidence = is_array($run['confidence'] ?? null) ? $run['confidence'] : [];
    $errors = is_array($run['errors'] ?? null) ? $run['errors'] : [];
    $savedResultCount = 0;

    foreach ($queries as $item) {
        $savedResultCount += count(is_array($item['results'] ?? null) ? $item['results'] : []);
    }

    $ownedShare = null;
    foreach ($share as $item) {
        if (($item['is_owned'] ?? false) === true) {
            $ownedShare = (int) ($item['share'] ?? 0);
            break;
        }
    }

    $topCompetitors = array_slice(array_values(array_filter(array_map(
        static fn (array $item): string => (string) ($item['domain'] ?? ''),
        $competitors
    ))), 0, 4);

    $previousRuns = [];
    if (!empty($project['id'])) {
        $previousRuns = list_visibility_runs((string) $project['id'], 3);
    }
    $hasTrendContext = count($previousRuns) > 1;

    $hardSignals = [
        [
            'label' => 'Mentett keresési minta',
            'value' => sprintf('%d kérdés, %d mentett top találat', $queryCount, $savedResultCount),
            'detail' => 'Ezek konkrétan lekért és JSON fájlba mentett találatok, nem utólagos becslések.',
        ],
        [
            'label' => 'Saját-domain jelenlét',
            'value' => sprintf('%d/%d kérdésben jelent meg', $ownedHits, $queryCount),
            'detail' => sprintf('A vizsgált domain: %s. A találat akkor számít sajátnak, ha a fődomain vagy annak aldomainje szerepel.', $targetDomain ?: 'nincs megadva'),
        ],
        [
            'label' => 'Adatforrás',
            'value' => $providers ? implode(', ', $providers) : 'nincs provider adat',
            'detail' => $errors ? 'A futás közben volt providerhiba, ezért a minta óvatosabban értelmezendő.' : 'A futás az aktív keresési provider találati adataira épült.',
        ],
        [
            'label' => 'Találati tér share of voice',
            'value' => $ownedShare !== null ? $ownedShare . '% saját részesedés' : 'nincs saját share adat',
            'detail' => 'A share of voice a mentett top találatok domainmegoszlását mutatja ebben a futásban.',
        ],
        [
            'label' => 'Futás típusa és ideje',
            'value' => ($runMode === 'weekly_portfolio' ? 'Heti Top 20 portfólió' : 'Generált kérdéssor') . ' · ' . (string) ($run['created_at'] ?? ''),
            'detail' => 'Ugyanezzel a kérdéssorral ismételve lesz igazán összehasonlítható a trend.',
        ],
    ];

    $directionalSignals = [
        [
            'label' => 'AI visibility rate',
            'value' => $visibilityRate . '%',
            'detail' => 'Ez erős irányadó proxy: azt mutatja, milyen gyakran kerül elő a domain az AI-válaszokhoz is felhasználható keresési találati térben.',
        ],
        [
            'label' => 'Bizonyossági szint',
            'value' => (string) ($confidence['level'] ?? 'irányadó jel') . ' · ' . (int) ($confidence['score'] ?? 0) . '/100',
            'detail' => (string) ($confidence['note'] ?? 'A mintát ismételt futásokkal érdemes megerősíteni.'),
        ],
        [
            'label' => 'Versenytárs mintázat',
            'value' => $topCompetitors ? implode(', ', $topCompetitors) : 'nincs domináns versenytárs',
            'detail' => 'A gyakran előkerülő domainek mutatják, milyen forrástípusokat és tartalmi bizonyítékokat részesíthet előnyben a találati környezet.',
        ],
        [
            'label' => 'Trendértelmezés',
            'value' => $hasTrendContext ? 'van előzmény' : 'első vagy kevés futás',
            'detail' => $hasTrendContext
                ? 'Több futás alapján már a változás iránya is értelmezhető, nem csak az aktuális pillanatkép.'
                : 'Egy futás jó kiindulópont, de döntés előtt érdemes ugyanazt a portfóliót újramérni.',
        ],
    ];

    $notMeasured = [
        'Nem garantálja, hogy a Google AI Overview, ChatGPT, Gemini vagy Perplexity minden felhasználónál ugyanígy idézi az oldalt.',
        'Nem méri közvetlenül a személyre szabott, lokációs, bejelentkezett vagy előzményalapú AI válaszvariációkat.',
        'Nem állít pontos idézési valószínűséget; azt mutatja, hogy a domain mennyire van jelen a forrásként felhasználható találati térben.',
        'Nem helyettesíti a tartalmi, technikai SEO és brand-említési javításokat, hanem priorizálja őket.',
    ];

    $clientSummary = [
        sprintf('A mérés %d kérdés alapján vizsgálta, hogy a %s domain mennyire van jelen az AI-válaszokhoz is releváns találati térben.', $queryCount, $targetDomain ?: 'vizsgált'),
        sprintf('A saját domain %d/%d kérdésben jelent meg, ami %d%% irányadó AI keresési láthatóságot jelent ebben a mintában.', $ownedHits, $queryCount, $visibilityRate),
        'A “biztos” rész a mentett keresési adatokra vonatkozik; az AI visibility következtetés irányadó, ezért trendként és versenytárs-összevetésként a legerősebb.',
    ];

    return [
        'title' => 'Biztos adat vs irányadó AI jel',
        'hard_signals' => $hardSignals,
        'directional_signals' => $directionalSignals,
        'not_measured' => $notMeasured,
        'client_summary' => $clientSummary,
        'recommended_language' => implode(' ', $clientSummary) . ' Emiatt a riportot döntéstámogató mérésként érdemes kezelni: megmutatja, hol van már forrásjelenlét, hol erősek a versenytársak, és mely tartalmi eszközök javíthatják leggyorsabban az AI keresési láthatóságot.',
    ];
}

function visibility_build_resource_summary(array $run): array
{
    $queryResults = is_array($run['query_results'] ?? null) ? $run['query_results'] : [];
    $queryCount = count($queryResults);
    $cacheHits = count(array_filter($queryResults, static fn (array $item): bool => ($item['from_cache'] ?? false) === true));
    $staleCacheHits = count(array_filter($queryResults, static fn (array $item): bool => ($item['stale_cache'] ?? false) === true));
    $providerErrors = count(array_filter($queryResults, static fn (array $item): bool => ($item['status'] ?? '') === 'error' || trim((string) ($item['error'] ?? '')) !== ''));
    $resultCount = 0;

    foreach ($queryResults as $item) {
        $resultCount += count(is_array($item['results'] ?? null) ? $item['results'] : []);
    }

    return [
        'search' => [
            'query_count' => $queryCount,
            'provider_calls' => max(0, $queryCount - $cacheHits),
            'cache_hits' => $cacheHits,
            'stale_cache_hits' => $staleCacheHits,
            'cache_hit_rate' => $queryCount > 0 ? (int) round(($cacheHits / $queryCount) * 100) : 0,
            'provider_errors' => $providerErrors,
            'saved_result_count' => $resultCount,
            'providers' => $run['providers'] ?? [],
        ],
        'ai' => [
            'status' => $run['ai_strategy']['status'] ?? 'pending',
            'provider' => $run['ai_strategy']['provider'] ?? '',
            'model' => $run['ai_strategy']['model'] ?? '',
            'usage' => $run['ai_strategy']['usage'] ?? null,
        ],
    ];
}

function visibility_build_report_layers(array $project, array $run): array
{
    $queryCount = (int) ($run['query_count'] ?? 0);
    $ownedHits = (int) ($run['owned_query_hits'] ?? 0);
    $visibilityRate = (int) ($run['visibility_rate'] ?? 0);
    $avgPosition = $run['average_owned_position'] ?? null;
    $confidence = is_array($run['confidence'] ?? null) ? $run['confidence'] : [];
    $backlog = is_array($run['opportunity_backlog'] ?? null) ? $run['opportunity_backlog'] : [];
    $actions = is_array($backlog['actions'] ?? null) ? $backlog['actions'] : [];
    $share = is_array($run['share_of_voice'] ?? null) ? $run['share_of_voice'] : [];
    $competitors = is_array($run['competitors'] ?? null) ? $run['competitors'] : [];
    $providers = array_values(array_filter($run['providers'] ?? []));
    $targetDomain = (string) ($run['target_domain'] ?? ($project['target_domain'] ?? ''));
    $topOwnedShare = 'nincs saját share adat';

    foreach ($share as $item) {
        if (($item['is_owned'] ?? false) === true) {
            $topOwnedShare = (int) ($item['share'] ?? 0) . '% saját share of voice';
            break;
        }
    }

    $topCompetitors = array_slice(array_values(array_filter(array_map(
        static fn (array $item): string => (string) ($item['domain'] ?? ''),
        $competitors
    ))), 0, 4);

    $topActions = array_slice(array_map(static function (array $action): array {
        return [
            'priority' => $action['priority'] ?? '',
            'title' => $action['title'] ?? '',
            'query' => $action['query'] ?? '',
            'recommended_asset' => $action['recommended_asset'] ?? '',
            'first_step' => $action['first_step'] ?? '',
        ];
    }, $actions), 0, 5);

    $highPriorityCount = count(array_filter($actions, static fn (array $action): bool => ($action['priority'] ?? '') === 'high'));
    $optimizationCount = count(array_filter($actions, static fn (array $action): bool => ($action['status'] ?? '') === 'optimize_existing'));

    return [
        'title' => 'Mérés vs javítás',
        'measurement_layer' => [
            'label' => 'Mérési réteg',
            'summary' => sprintf(
                'A futás %d kérdés alapján vizsgálta a %s domaint. A saját domain %d kérdésben jelent meg, ami %d%% visibility rate értéket ad ebben a mintában.',
                $queryCount,
                $targetDomain ?: 'vizsgált',
                $ownedHits,
                $visibilityRate
            ),
            'status' => 'measured',
            'facts' => [
                ['label' => 'Vizsgált kérdés', 'value' => (string) $queryCount, 'note' => 'A ténylegesen futtatott keresési kérdések száma.'],
                ['label' => 'Saját-domain találat', 'value' => $ownedHits . '/' . $queryCount, 'note' => 'A domain legalább egyszer megjelent az adott kérdés találatai között.'],
                ['label' => 'Visibility rate', 'value' => $visibilityRate . '%', 'note' => 'Mentett keresési találatokból számolt irányadó láthatósági arány.'],
                ['label' => 'Átlagos saját pozíció', 'value' => $avgPosition !== null ? (string) $avgPosition : '-', 'note' => 'Csak azoknál a kérdéseknél értelmezett, ahol volt saját találat.'],
                ['label' => 'Share of voice', 'value' => $topOwnedShare, 'note' => 'A mentett top találatok domainmegoszlása.'],
                ['label' => 'Adatforrás', 'value' => $providers ? implode(', ', $providers) : 'nincs provider adat', 'note' => 'A keresési provider, amelyből a mérés dolgozott.'],
            ],
            'confidence' => [
                'level' => $confidence['level'] ?? 'irányadó jel',
                'score' => (int) ($confidence['score'] ?? 0),
                'note' => $confidence['note'] ?? '',
            ],
            'evidence_sources' => [
                'query_results',
                'share_of_voice',
                'competitors',
                'resource_summary',
            ],
        ],
        'improvement_layer' => [
            'label' => 'Javítási réteg',
            'summary' => $actions
                ? sprintf('%d javítási teendő készült a mérésből. Ebből %d magas prioritású, %d meglévő tartalom erősítésére vonatkozik.', count($actions), $highPriorityCount, $optimizationCount)
                : 'A futás nem generált külön tartalmi backlogot. Ilyenkor a következő lépés az ismételt mérés vagy a kérdésportfólió pontosítása.',
            'status' => 'derived',
            'action_count' => count($actions),
            'high_priority_count' => $highPriorityCount,
            'optimization_count' => $optimizationCount,
            'top_actions' => $topActions,
            'strategic_focus' => [
                $visibilityRate < 35 ? 'Új döntéstámogató tartalmak létrehozása a hiányzó kérdésekre.' : 'A már látható tartalmak bizonyíték- és citációs erejének növelése.',
                $topCompetitors ? 'Versenytárs forrásminták visszafejtése: ' . implode(', ', $topCompetitors) : 'Versenytárs minták gyűjtése ismételt méréssel.',
                'Ugyanezt a kérdésportfóliót érdemes újramérni a javítások publikálása után.',
            ],
        ],
        'client_framing' => [
            'measured_sentence' => sprintf('A mérési rész azt mutatja, hogy a %s domain a vizsgált kérdések %d%%-ában jelent meg a mentett találati térben.', $targetDomain ?: 'vizsgált', $visibilityRate),
            'derived_sentence' => $actions
                ? 'A javítási rész ebből vezet le konkrét tartalmi és citációs teendőket, de ezek nem új mérések, hanem szakmai következtetések.'
                : 'A javítási rész jelenleg kevés teendőt jelez; a következő döntéshez érdemes bővíteni vagy ismételni a kérdésportfóliót.',
            'how_to_use' => 'Ügyfélkommunikációban a mérési adatot kezeld bizonyítékként, a javítási tervet pedig priorizált munkatervként.',
        ],
    ];
}

function visibility_build_opportunity_backlog(array $project, array $run): array
{
    $businessModel = visibility_normalize_business_model((string) ($project['business_model'] ?? 'generic'));
    $actions = [];

    foreach (($run['query_results'] ?? []) as $index => $item) {
        $query = trim((string) ($item['query'] ?? ''));
        if ($query === '') {
            continue;
        }

        $ownedHit = (bool) ($item['owned_domain_hit'] ?? false);
        $ownResults = is_array($item['own_results'] ?? null) ? $item['own_results'] : [];
        $firstPosition = $ownResults ? (int) ($ownResults[0]['position'] ?? 0) : 0;
        $needsNewAsset = !$ownedHit;
        $needsOptimization = $ownedHit && ($firstPosition === 0 || $firstPosition > 5);

        if (!$needsNewAsset && !$needsOptimization) {
            continue;
        }

        $contentType = visibility_recommended_content_type((string) ($item['type'] ?? ''), $businessModel);
        $competitors = array_slice(array_values(array_filter($item['competitors'] ?? [])), 0, 5);
        $topSources = array_slice(array_map(static function (array $result): array {
            return [
                'title' => $result['title'] ?? '',
                'host' => $result['host'] ?? '',
                'url' => $result['url'] ?? '',
            ];
        }, is_array($item['results'] ?? null) ? $item['results'] : []), 0, 4);

        $actions[] = [
            'id' => 'action_' . ($index + 1),
            'priority' => $needsNewAsset ? 'high' : 'medium',
            'status' => $needsNewAsset ? 'missing_asset' : 'optimize_existing',
            'query' => $query,
            'query_type' => $item['type'] ?? '',
            'title' => $needsNewAsset
                ? 'Új tartalmi eszköz készítése erre a kérdésre'
                : 'Meglévő saját találat erősítése erre a kérdésre',
            'recommended_asset' => $contentType,
            'suggested_h1' => visibility_suggested_h1($query, $contentType),
            'sections' => visibility_recommended_sections($contentType, $businessModel),
            'why' => $needsNewAsset
                ? 'A saját domain nem jelent meg a mentett keresési találatok között, ezért az AI keresők forráskészletébe valószínűleg más domainek kerülnek be.'
                : 'A saját domain megjelent, de nem elég erős pozícióban. A tartalmat és a belső/külső bizonyítékokat erősíteni kell.',
            'first_step' => $needsNewAsset
                ? 'Készíts egy önálló, indexelhető, answer-first oldalt vagy szekciót a kérdésre, majd linkeld a releváns főoldalról.'
                : 'Frissítsd a meglévő oldalt rövid válaszblokkal, összehasonlító bizonyítékokkal, schema kapcsolatokkal és belső linkekkel.',
            'competitors' => $competitors,
            'top_sources' => $topSources,
        ];
    }

    $highCount = count(array_filter($actions, static fn (array $action): bool => ($action['priority'] ?? '') === 'high'));

    return [
        'generated_at' => date(DATE_ATOM),
        'summary' => [
            'total_actions' => count($actions),
            'high_priority' => $highCount,
            'optimization_actions' => count($actions) - $highCount,
        ],
        'actions' => array_slice($actions, 0, 12),
    ];
}

function visibility_recommended_content_type(string $queryType, string $businessModel): string
{
    $queryTypeLower = text_lower($queryType);
    if (str_contains($queryTypeLower, 'összehasonl') || str_contains($queryTypeLower, 'alternat')) {
        return 'összehasonlító döntéstámogató oldal';
    }
    if (str_contains($queryTypeLower, 'esettanulm') || str_contains($queryTypeLower, 'referencia')) {
        return 'esettanulmány és referencia oldal';
    }
    if (str_contains($queryTypeLower, 'vásárl') || $businessModel === 'ecommerce') {
        return 'vásárlási útmutató';
    }
    if (str_contains($queryTypeLower, 'funkció') || $businessModel === 'saas') {
        return 'funkció és use-case oldal';
    }
    if (str_contains($queryTypeLower, 'helyi') || $businessModel === 'local_service') {
        return 'helyi szolgáltatási landing oldal';
    }
    if (str_contains($queryTypeLower, 'magyarázó') || $businessModel === 'expert_brand') {
        return 'szakértői magyarázó cikk';
    }

    return 'answer-first döntéstámogató oldal';
}

function visibility_suggested_h1(string $query, string $contentType): string
{
    $cleanQuery = trim(preg_replace('~\?+$~', '', $query) ?: $query);
    return ucfirst($contentType) . ': ' . $cleanQuery;
}

function visibility_recommended_sections(string $contentType, string $businessModel): array
{
    $base = [
        'Rövid, 2-3 mondatos answer-first válasz',
        'Kinek való és kinek nem való ez a megoldás',
        'Döntési szempontok pontokba szedve',
        'Bizonyíték: referencia, adat, vélemény vagy esettanulmány',
        'Következő lépés és egyértelmű CTA',
    ];

    if ($businessModel === 'saas') {
        array_splice($base, 3, 0, ['Funkció-összehasonlítás és integrációk', 'Bevezetési idő, support és árlogika']);
    } elseif ($businessModel === 'ecommerce') {
        array_splice($base, 3, 0, ['Termékjellemzők, ár, garancia és szállítás', 'Vásárlói értékelések és alternatívák']);
    } elseif ($businessModel === 'local_service') {
        array_splice($base, 3, 0, ['Szolgáltatási terület, nyitvatartás és kapcsolat', 'Helyi vélemények és bizalmi jelek']);
    } elseif ($businessModel === 'b2b_service') {
        array_splice($base, 3, 0, ['Referenciák, folyamat és döntéshozói bizonyítékok', 'Kockázatok, SLA vagy együttműködési modell']);
    }

    return array_slice(array_values(array_unique($base)), 0, 8);
}

function list_visibility_runs(string $projectId, int $limit = 10): array
{
    $files = glob(visibility_runs_dir() . '/*.json') ?: [];
    $runs = [];
    foreach ($files as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && (string) ($decoded['project_id'] ?? '') === $projectId) {
            $runs[] = $decoded;
        }
    }

    usort($runs, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return array_slice($runs, 0, $limit);
}

function latest_visibility_run_for_project(string $projectId): ?array
{
    $runs = list_visibility_runs($projectId, 1);
    return $runs[0] ?? null;
}
