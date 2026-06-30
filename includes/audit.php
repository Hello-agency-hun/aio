<?php
/**
 * AIO/SEO audit motor.
 *
 * A modul kis crawlerként működik: megadott URL-ről indul, belső linkeket
 * gyűjt, majd limitált számú oldalon elemzi azokat a jeleket, amelyek SEO és
 * AI keresési optimalizálás szempontból fontosak. Az eredmény nem "varázs
 * pontszám", hanem UI-ban követhető, priorizált javítási lista.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/methodology.php';

function run_site_audit(string $inputUrl, array $options = []): array
{
    $rootUrl = normalize_url($inputUrl);
    if (!$rootUrl) {
        throw new InvalidArgumentException('Érvénytelen URL. Adj meg http vagy https címet.');
    }

    if (!is_public_target_url($rootUrl)) {
        throw new InvalidArgumentException('Biztonsági okból csak nyilvános, interneten elérhető domainek vizsgálhatók.');
    }

    $startedAt = microtime(true);
    $crawlLimit = resolve_crawl_limit($options);
    $crawlMode = resolve_crawl_mode((string) ($options['mode'] ?? 'smart'));
    $progress = is_callable($options['progress_callback'] ?? null) ? $options['progress_callback'] : null;
    if ($progress) {
        $progress(8, 'Audit előkészítése', 'URL normalizálás, robots/sitemap és alapbeállítások ellenőrzése.');
    }
    $siteFiles = inspect_site_files($rootUrl);
    if ($progress) {
        $progress(14, 'Domain térkép építése', 'Sitemap, robots.txt, llms.txt és belső URL-jelöltek gyűjtése.');
    }
    $crawlResult = crawl_site($rootUrl, $crawlLimit, $crawlMode, $siteFiles, $progress);
    $pages = $crawlResult['pages'];
    if ($progress) {
        $progress(57, 'AI keresési kérdéssor', 'Brand, piac, témák és vevői kérdések előállítása.');
    }
    $aiSearchPlan = build_ai_search_visibility_plan($rootUrl, $pages);
    if ($progress) {
        $progress(62, 'Javítási javaslatok rendezése', 'Pontszámok, hibák, prioritások és riportstruktúra összeállítása.');
    }
    $aggregate = aggregate_findings($rootUrl, $pages, $siteFiles);

    return [
        'id' => 'report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)),
        'url' => $rootUrl,
        'created_at' => date(DATE_ATOM),
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'status' => 'completed',
        'overall_score' => $aggregate['overall_score'],
        'scores' => $aggregate['scores'],
        'summary' => $aggregate['summary'],
        'recommendations' => $aggregate['recommendations'],
        'ai_search_plan' => $aiSearchPlan,
        'methodology' => [
            'version' => 'AIO/GEO 2026 v4',
            'principles' => aio_methodology_principles(),
            'sources' => aio_methodology_sources(),
            'score_dimensions' => aio_score_dimensions(),
            'glossary' => aio_glossary(),
        ],
        'site_files' => $siteFiles,
        'pages' => $pages,
        'crawl_limit' => $crawlLimit,
        'crawl_mode' => $crawlMode,
        'site_map' => [
            'discovered_url_count' => $crawlResult['discovered_url_count'],
            'candidate_url_count' => $crawlResult['candidate_url_count'],
            'analyzed_url_count' => count($pages),
            'coverage_note' => $crawlResult['coverage_note'],
            'priority_buckets' => $crawlResult['priority_buckets'],
            'sample_urls' => array_slice($crawlResult['candidate_urls'], 0, 80),
        ],
    ];
}

function build_ai_search_visibility_plan(string $rootUrl, array $pages): array
{
    $host = parse_url($rootUrl, PHP_URL_HOST) ?: $rootUrl;
    $brand = infer_brand_name($rootUrl, $pages);
    $domainContext = infer_domain_context($pages);
    $mainTopics = infer_main_topics($pages);
    $primaryTopic = $mainTopics[0] ?? 'a szolgáltatás';
    $countryHint = detect_country_hint($rootUrl, $pages);

    $querySet = [
        [
            'id' => 'brand_exact',
            'type' => 'brand',
            'query' => sprintf('Mit tudsz a(z) %s márkáról és a %s weboldalról?', $brand, $host),
            'why' => 'Méri, hogy az AI felismeri-e a márkát és pontosan írja-e le a tevékenységet.',
            'expected_signal' => 'Márkanév, domain, pontos szolgáltatásleírás, hibás vagy elavult állítások száma.',
        ],
        [
            'id' => 'category_recommendation',
            'type' => 'category',
            'query' => sprintf('Mely %s szolgáltatókat ajánlanád %s piacon %s témában?', $domainContext, $countryHint, $primaryTopic),
            'why' => 'Toplista-logika: bekerül-e a márka a döntési listába, és kik vannak mellette.',
            'expected_signal' => 'Toplista jelenlét, pozíció, versenytársak, említés indoklása.',
        ],
        [
            'id' => 'buyer_problem',
            'type' => 'buyer-intent',
            'query' => sprintf('Milyen céget válasszak, ha %s problémára keresek megbízható megoldást?', $primaryTopic),
            'why' => 'Valós vevői kérdés: nem kulcsszó, hanem döntési helyzet.',
            'expected_signal' => 'Ajánlott márkák, döntési szempontok, forrásként idézett oldalak.',
        ],
        [
            'id' => 'comparison',
            'type' => 'comparison',
            'query' => sprintf('Hasonlíts össze több %s szolgáltatót %s témában, és írj előnyöket-hátrányokat.', $domainContext, $primaryTopic),
            'why' => 'A Google AI Mode és más AI keresők gyakran összehasonlító, több lépéses kérdésekben hoznak forrásokat.',
            'expected_signal' => 'Brand szereplés, összehasonlítás minősége, bizonyítékok és hivatkozások.',
        ],
        [
            'id' => 'evidence_sources',
            'type' => 'citation',
            'query' => sprintf('Milyen források alapján döntsek %s szolgáltató kiválasztásakor?', $primaryTopic),
            'why' => 'Külön méri, hogy a domain forrásként vagy csak háttérzajként jelenik-e meg.',
            'expected_signal' => 'Idézett domainek, saját domain szereplése, earned media/third-party források.',
        ],
        [
            'id' => 'long_tail_fanout',
            'type' => 'fan-out',
            'query' => sprintf('Készíts döntési útmutatót %s témában: kinek való, mennyibe kerülhet, milyen hibákat kerüljek el, és milyen cégeket érdemes megnézni?', $primaryTopic),
            'why' => 'Query fan-out próba: egy összetett kérdés több alkérdésre bomlik, és többféle bizonyítékot kér.',
            'expected_signal' => 'Alkérdésenkénti jelenlét, idézett bizonyíték, válaszba átvett állítások.',
        ],
    ];

    return [
        'brand_name' => $brand,
        'domain' => $host,
        'market_context' => $domainContext,
        'country_hint' => $countryHint,
        'main_topics' => $mainTopics,
        'query_set' => $querySet,
        'measurement_model' => [
            'coverage_rate' => 'A kérdések hány százalékában jelenik meg a márka vagy domain.',
            'citation_rate' => 'A válaszok hány százaléka hivatkozik ténylegesen a domainre forrásként.',
            'citation_absorption' => 'A válasz átvesz-e definíciót, adatot, összehasonlítást vagy folyamatlépést az oldalról.',
            'competitor_presence' => 'Mely versenytársak jelennek meg ugyanazokra a kérdésekre.',
            'narrative_accuracy' => 'Az AI pontosan vagy félrevezetően írja-e le a márkát és szolgáltatást.',
        ],
        'static_readiness' => estimate_static_ai_search_readiness($pages),
        'implementation_note' => 'Kulcs nélkül ez mérési protokoll és promptkészlet. OpenAI API + web_search bekapcsolásával live AI keresési próbaként is futtatható; Gemini, Perplexity, DeepSeek eredményekhez később külön platformcsatlakozó adható.',
    ];
}

function infer_brand_name(string $rootUrl, array $pages): string
{
    $home = $pages[0] ?? [];
    $title = trim((string) ($home['title'] ?? ''));
    if ($title !== '') {
        $clean = preg_split('~[\-|–|—|·|:|/]~u', $title)[0] ?? $title;
        $clean = trim(preg_replace('~\s+~u', ' ', $clean) ?: '');
        if ($clean !== '' && text_length($clean) <= 60) {
            return $clean;
        }
    }

    $host = (string) (parse_url($rootUrl, PHP_URL_HOST) ?: $rootUrl);
    $host = preg_replace('~^www\.~i', '', $host) ?: $host;
    $base = preg_replace('~\.[a-z]{2,}(\.[a-z]{2,})?$~i', '', $host) ?: $host;

    return trim(ucwords(str_replace(['-', '_'], ' ', $base)));
}

function infer_domain_context(array $pages): string
{
    $contextCounts = [];
    foreach ($pages as $page) {
        $context = (string) ($page['context_signals']['primary_context'] ?? '');
        if ($context !== '') {
            $contextCounts[$context] = ($contextCounts[$context] ?? 0) + 1;
        }
    }

    arsort($contextCounts);
    $top = array_key_first($contextCounts);

    if (is_string($top) && $top !== '') {
        return $top;
    }

    return 'szakmai vagy üzleti';
}

function infer_main_topics(array $pages): array
{
    $candidates = [];
    foreach (array_slice($pages, 0, 12) as $page) {
        foreach ([$page['title'] ?? '', $page['description'] ?? '', implode(' ', $page['h1'] ?? [])] as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }

            $text = preg_replace('~[\-|–|—|·|:|/]~u', ' ', $text) ?: $text;
            $words = preg_split('~\s+~u', text_lower($text)) ?: [];
            foreach ($words as $word) {
                $word = trim($word, " \t\n\r\0\x0B.,;!?()[]{}\"'");
                if (text_length($word) < 5 || preg_match('~^(https?|www|kapcsolat|főoldal|kezdőlap|adatkezelés|cookie|hello)$~iu', $word)) {
                    continue;
                }
                $candidates[$word] = ($candidates[$word] ?? 0) + 1;
            }
        }
    }

    arsort($candidates);
    $topics = array_slice(array_keys($candidates), 0, 5);

    return $topics ?: ['szolgáltatás'];
}

function detect_country_hint(string $rootUrl, array $pages): string
{
    $host = (string) (parse_url($rootUrl, PHP_URL_HOST) ?: '');
    if (str_ends_with(text_lower($host), '.hu')) {
        return 'magyar';
    }

    $combined = text_lower(implode(' ', array_map(static fn (array $page): string => (string) ($page['description'] ?? ''), array_slice($pages, 0, 6))));
    if (preg_match('~\b(magyarország|budapest|magyar)\b~iu', $combined)) {
        return 'magyar';
    }

    return 'helyi vagy célpiaci';
}

function estimate_static_ai_search_readiness(array $pages): array
{
    $total = max(1, count($pages));
    $withAnswerBlocks = 0;
    $withEvidence = 0;
    $withEntity = 0;
    $withRawHtml = 0;
    $withContextualQa = 0;

    foreach ($pages as $page) {
        $signals = $page['signals'] ?? [];
        $withAnswerBlocks += ($signals['has_answer_first_blocks'] ?? false) ? 1 : 0;
        $withEvidence += ($signals['has_claim_evidence_language'] ?? false) ? 1 : 0;
        $withEntity += (($signals['has_organization_schema'] ?? false) && ($signals['has_stable_schema_ids'] ?? false)) ? 1 : 0;
        $withRawHtml += ($signals['has_raw_html_content'] ?? false) ? 1 : 0;
        $withContextualQa += ($signals['has_contextual_qa_support'] ?? false) ? 1 : 0;
    }

    $selection = (int) round((($withEntity * 0.35) + ($withRawHtml * 0.35) + ($withContextualQa * 0.30)) / $total * 100);
    $absorption = (int) round((($withAnswerBlocks * 0.45) + ($withEvidence * 0.35) + ($withContextualQa * 0.20)) / $total * 100);

    return [
        'citation_selection_readiness' => max(0, min(100, $selection)),
        'citation_absorption_readiness' => max(0, min(100, $absorption)),
        'interpretable_pages_ratio' => (int) round(($withRawHtml / $total) * 100),
        'evidence_pages_ratio' => (int) round(($withEvidence / $total) * 100),
        'note' => 'Ez nem live AI-platform mérés, hanem az oldalstruktúra alapján becsült alkalmasság. Live láthatóságot a query_set platformonkénti futtatása mér.',
    ];
}

function resolve_crawl_mode(string $mode): string
{
    return in_array($mode, ['quick', 'smart', 'deep', 'custom'], true) ? $mode : 'smart';
}

function resolve_crawl_limit(array $options = []): int
{
    $mode = resolve_crawl_mode((string) ($options['mode'] ?? 'smart'));
    $requested = (int) ($options['limit'] ?? 0);

    $defaultByMode = [
        'quick' => 20,
        'smart' => DEFAULT_CRAWL_PAGES,
        'deep' => MAX_CRAWL_PAGES,
        'custom' => $requested > 0 ? $requested : DEFAULT_CRAWL_PAGES,
    ];

    return max(10, min(MAX_CRAWL_PAGES, (int) ($defaultByMode[$mode] ?? DEFAULT_CRAWL_PAGES)));
}

function crawl_site(string $rootUrl, int $crawlLimit = DEFAULT_CRAWL_PAGES, string $crawlMode = 'smart', array $siteFiles = [], ?callable $progress = null): array
{
    $candidates = discover_seed_urls($rootUrl, $siteFiles, $crawlMode);
    $queue = prioritize_url_queue($candidates, $rootUrl);
    $seen = [];
    $pages = [];
    $candidateUrls = [];
    $priorityBuckets = [];

    while ($queue && count($pages) < $crawlLimit && count($seen) < MAX_DISCOVERED_URLS) {
        $url = array_shift($queue);
        $key = canonicalize_for_queue($url);

        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $candidateUrls[] = $url;
        $bucket = url_priority_bucket($url, $rootUrl);
        $priorityBuckets[$bucket] = ($priorityBuckets[$bucket] ?? 0) + 1;
        if ($progress) {
            $pageNumber = count($pages) + 1;
            $percent = 16 + (int) floor((min($pageNumber, $crawlLimit) / max(1, $crawlLimit)) * 38);
            $progress($percent, 'Oldalak letöltése', sprintf('%d/%d. részletes URL elemzése: %s', $pageNumber, $crawlLimit, $url));
        }

        $download = fetch_html($url);
        if (!$download['ok']) {
            $pages[] = [
                'url' => $url,
                'ok' => false,
                'status_code' => $download['status_code'],
                'error' => $download['error'],
                'score' => 0,
                'signals' => [],
                'issues' => [['level' => 'critical', 'text' => 'Az oldal nem tölthető le audit közben.']],
            ];
            continue;
        }

        $analysis = analyze_html_page($url, $download['body'], $download['status_code'], $download['load_time_ms'], $download['warning']);
        $pages[] = $analysis;
        if ($progress) {
            $progress(
                18 + (int) floor((count($pages) / max(1, $crawlLimit)) * 38),
                'On-page AIO jelek mérése',
                sprintf('%d/%d oldal kész. Nyers HTML, schema, UX, citációs blokkok és entitásjelek feldolgozva.', count($pages), $crawlLimit)
            );
        }

        foreach ($analysis['links']['internal'] as $link) {
            if (count($seen) + count($queue) >= MAX_DISCOVERED_URLS) {
                break;
            }
            $linkKey = canonicalize_for_queue($link);
            if (!isset($seen[$linkKey]) && same_host($link, $rootUrl)) {
                $queue[] = $link;
            }
        }

        $queue = prioritize_url_queue(array_values(array_unique($queue)), $rootUrl);
    }

    $candidateCount = count(array_unique($candidateUrls));

    return [
        'pages' => $pages,
        'candidate_urls' => array_values(array_unique($candidateUrls)),
        'candidate_url_count' => $candidateCount,
        'discovered_url_count' => max($candidateCount, count($seen) + count($queue)),
        'priority_buckets' => $priorityBuckets,
        'coverage_note' => crawl_coverage_note(count($pages), $crawlLimit, $candidateCount, count($queue)),
    ];
}

function discover_seed_urls(string $rootUrl, array $siteFiles, string $crawlMode): array
{
    $urls = [$rootUrl];

    if (in_array($crawlMode, ['smart', 'deep', 'custom'], true)) {
        foreach (($siteFiles['sitemap_urls'] ?? []) as $url) {
            if (is_string($url) && same_host($url, $rootUrl)) {
                $urls[] = $url;
            }
        }
    }

    return array_slice(array_values(array_unique(array_filter($urls, static fn (string $url): bool => !looks_like_non_html_asset($url)))), 0, MAX_DISCOVERED_URLS);
}

function prioritize_url_queue(array $urls, string $rootUrl): array
{
    $unique = [];
    foreach ($urls as $url) {
        if (!is_string($url) || $url === '' || !same_host($url, $rootUrl) || looks_like_non_html_asset($url)) {
            continue;
        }
        $unique[canonicalize_for_queue($url)] = $url;
    }

    $urls = array_values($unique);
    usort($urls, static function (string $a, string $b) use ($rootUrl): int {
        return url_priority_score($a, $rootUrl) <=> url_priority_score($b, $rootUrl)
            ?: strlen($a) <=> strlen($b)
            ?: strcmp($a, $b);
    });

    return $urls;
}

function url_priority_score(string $url, string $rootUrl): int
{
    $parts = parse_url($url);
    $path = trim((string) ($parts['path'] ?? '/'), '/');
    $query = (string) ($parts['query'] ?? '');

    if (canonicalize_for_queue($url) === canonicalize_for_queue($rootUrl)) {
        return 0;
    }

    $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path)));
    $depth = count($segments);
    $first = text_lower($segments[0] ?? '');
    $score = 20 + ($depth * 7);

    if ($depth <= 1) {
        $score -= 12;
    }

    if (preg_match('~^(szolgaltatas|szolgaltatasaink|services|service|rolunk|about|munkaink|work|works|portfolio|case|cases|referencia|references|blog|rumour|hirek|news|kontakt|kapcsolat|contact|team|career|karrier)$~iu', $first)) {
        $score -= 10;
    }

    if ($query !== '') {
        $score += 25;
        if (preg_match('~(^|&)tag=~i', $query)) {
            $score += 18;
        }
    }

    if (preg_match('~/(privacy|adatkezeles|terms|aszf|cookie|impresszum|login|cart|checkout|kosar|fiok)(/|$)~iu', '/' . $path . '/')) {
        $score += 30;
    }

    return max(0, $score);
}

function url_priority_bucket(string $url, string $rootUrl): string
{
    if (canonicalize_for_queue($url) === canonicalize_for_queue($rootUrl)) {
        return 'kezdőoldal';
    }

    $parts = parse_url($url);
    $path = trim((string) ($parts['path'] ?? '/'), '/');
    $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path)));
    $first = text_lower($segments[0] ?? '');

    if ($first === '') {
        return 'kezdőoldal';
    }
    if (preg_match('~^(szolgaltatas|szolgaltatasaink|services|service)$~iu', $first)) {
        return 'szolgáltatás';
    }
    if (preg_match('~^(munkaink|work|works|portfolio|case|cases|referencia|references)$~iu', $first)) {
        return 'portfólió';
    }
    if (preg_match('~^(blog|rumour|hirek|news|article|cikk)$~iu', $first)) {
        return 'tartalom';
    }
    if (preg_match('~^(rolunk|about|team|career|karrier)$~iu', $first)) {
        return 'bizalom/cég';
    }
    if (preg_match('~^(kontakt|kapcsolat|contact)$~iu', $first)) {
        return 'konverzió';
    }

    return 'egyéb aloldal';
}

function crawl_coverage_note(int $analyzedCount, int $crawlLimit, int $candidateCount, int $remainingQueue): string
{
    if ($analyzedCount < $crawlLimit && $remainingQueue === 0) {
        return 'A crawler végigért az elérhető belső URL-listán a beállított részletes elemzési kereten belül.';
    }

    if ($analyzedCount >= $crawlLimit && ($candidateCount >= $crawlLimit || $remainingQueue > 0)) {
        return 'A rendszer több URL-t talált, mint amennyi teljes oldalszintű elemzést kértél. A részletes lista prioritás szerint készült: kezdőoldal, fő kategóriák, szolgáltatás/portfólió/tartalmi oldalak előnyben.';
    }

    return 'A crawler sitemapból, kezdőoldali navigációból és belső linkekből épített priorizált domainképet.';
}

function fetch_html(string $url): array
{
    $startedAt = microtime(true);
    $body = '';
    $statusCode = 0;
    $error = null;
    $warning = null;

    if (function_exists('curl_init')) {
        $result = curl_fetch($url, true);
        $body = $result['body'];
        $statusCode = $result['status_code'];
        $contentType = $result['content_type'];
        $error = $result['error'];

        // Egyes shared/local környezetekben hiányozhat a CA bundle. Ilyenkor
        // audit célból újrapróbáljuk a letöltést, de a riportban jelezhető
        // figyelmeztetésként megtartjuk a tanúsítványlánc problémáját.
        if ($error && stripos($error, 'SSL certificate') !== false) {
            $warning = 'TLS tanúsítvány ellenőrzési hiba miatt fallback letöltés történt.';
            $result = curl_fetch($url, false);
            $body = $result['body'];
            $statusCode = $result['status_code'];
            $contentType = $result['content_type'];
            $error = $result['error'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => REQUEST_TIMEOUT,
                'user_agent' => AUDIT_USER_AGENT,
                'header' => "Accept: text/html,application/xhtml+xml\r\n",
            ],
        ]);
        $body = (string) @file_get_contents($url, false, $context, 0, MAX_RESPONSE_BYTES);
        $contentType = '';
        $statusCode = 200;
        if (isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $match)) {
            $statusCode = (int) $match[1];
        }
        $error = $body === '' ? 'A szerver nem adott olvasható HTML választ.' : null;
    }

    if (strlen($body) > MAX_RESPONSE_BYTES) {
        $body = substr($body, 0, MAX_RESPONSE_BYTES);
    }

    $looksHtml = $body !== '' && (stripos($body, '<html') !== false || stripos($body, '<!doctype') !== false);
    $isHtmlType = empty($contentType) || stripos($contentType, 'html') !== false;

    return [
        'ok' => $error === null && $statusCode >= 200 && $statusCode < 400 && $looksHtml && $isHtmlType,
        'status_code' => $statusCode,
        'body' => $body,
        'error' => $error ?: (!$looksHtml ? 'A válasz nem HTML oldalnak tűnik.' : null),
        'warning' => $warning,
        'load_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ];
}

function curl_fetch(string $url, bool $verifySsl): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => REQUEST_TIMEOUT,
        CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
        CURLOPT_USERAGENT => AUDIT_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);

    $body = (string) curl_exec($ch);
    $result = [
        'body' => $body,
        'status_code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'error' => curl_error($ch) ?: null,
    ];
    curl_close($ch);

    return $result;
}

function inspect_site_files(string $rootUrl): array
{
    $origin = site_origin($rootUrl);
    $robots = fetch_plain_resource($origin . '/robots.txt');
    $llms = fetch_plain_resource($origin . '/llms.txt');
    $llmsFull = fetch_plain_resource($origin . '/llms-full.txt');
    $robotsBody = $robots['body'] ?? '';
    $sitemapUrls = discover_sitemap_urls($origin, $robotsBody, $rootUrl);

    return [
        'robots' => [
            'url' => $origin . '/robots.txt',
            'exists' => $robots['ok'],
            'status_code' => $robots['status_code'],
            'has_gptbot_rule' => stripos($robotsBody, 'GPTBot') !== false,
            'has_oai_searchbot_rule' => stripos($robotsBody, 'OAI-SearchBot') !== false,
            'has_claudebot_rule' => stripos($robotsBody, 'ClaudeBot') !== false,
            'has_perplexitybot_rule' => stripos($robotsBody, 'PerplexityBot') !== false,
        ],
        'llms' => [
            'url' => $origin . '/llms.txt',
            'exists' => $llms['ok'],
            'status_code' => $llms['status_code'],
            'word_count' => $llms['ok'] ? count_words($llms['body'] ?? '') : 0,
            'note' => 'Emergens, nem garantált jel: Google nem követeli meg, de agentikus navigációs térképként hasznos lehet.',
        ],
        'llms_full' => [
            'url' => $origin . '/llms-full.txt',
            'exists' => $llmsFull['ok'],
            'status_code' => $llmsFull['status_code'],
            'word_count' => $llmsFull['ok'] ? count_words($llmsFull['body'] ?? '') : 0,
        ],
        'sitemap_declared' => stripos($robotsBody, 'Sitemap:') !== false,
        'sitemap_urls' => $sitemapUrls,
        'sitemap_url_count' => count($sitemapUrls),
    ];
}

function site_origin(string $url): string
{
    $parts = parse_url($url);
    return strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
}

function fetch_plain_resource(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status_code' => 0, 'body' => ''];
    }

    $result = curl_fetch($url, true);
    if (($result['error'] ?? '') && stripos((string) $result['error'], 'SSL certificate') !== false) {
        $result = curl_fetch($url, false);
    }

    $status = (int) ($result['status_code'] ?? 0);
    $body = (string) ($result['body'] ?? '');

    return [
        'ok' => $status >= 200 && $status < 300 && trim($body) !== '',
        'status_code' => $status,
        'body' => strlen($body) > 120000 ? substr($body, 0, 120000) : $body,
    ];
}

function discover_sitemap_urls(string $origin, string $robotsBody, string $rootUrl): array
{
    $sitemaps = [];
    if (preg_match_all('~^\s*Sitemap:\s*(\S+)~mi', $robotsBody, $matches)) {
        foreach ($matches[1] as $sitemapUrl) {
            $normalized = normalize_url($sitemapUrl);
            if ($normalized) {
                $sitemaps[] = $normalized;
            }
        }
    }

    $sitemaps[] = $origin . '/sitemap.xml';
    $sitemaps = array_values(array_unique($sitemaps));
    $seenSitemaps = [];
    $urls = [];

    foreach ($sitemaps as $sitemap) {
        parse_sitemap_urls($sitemap, $rootUrl, $seenSitemaps, $urls);
        if (count($urls) >= MAX_DISCOVERED_URLS) {
            break;
        }
    }

    return array_slice(array_values(array_unique($urls)), 0, MAX_DISCOVERED_URLS);
}

function parse_sitemap_urls(string $sitemapUrl, string $rootUrl, array &$seenSitemaps, array &$urls): void
{
    if (isset($seenSitemaps[$sitemapUrl]) || count($seenSitemaps) >= 18 || count($urls) >= MAX_DISCOVERED_URLS) {
        return;
    }
    $seenSitemaps[$sitemapUrl] = true;

    $resource = fetch_plain_resource($sitemapUrl);
    if (!($resource['ok'] ?? false)) {
        return;
    }

    $body = (string) ($resource['body'] ?? '');
    if ($body === '') {
        return;
    }

    if (preg_match_all('~<loc>\s*([^<]+)\s*</loc>~i', $body, $matches)) {
        foreach ($matches[1] as $rawLoc) {
            $loc = html_entity_decode(trim($rawLoc), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normalized = normalize_url($loc);
            if (!$normalized) {
                continue;
            }

            if (preg_match('~\.xml(\?.*)?$~i', parse_url($normalized, PHP_URL_PATH) ?: '')) {
                parse_sitemap_urls($normalized, $rootUrl, $seenSitemaps, $urls);
                continue;
            }

            if (same_host($normalized, $rootUrl) && !looks_like_non_html_asset($normalized)) {
                $urls[] = $normalized;
            }

            if (count($urls) >= MAX_DISCOVERED_URLS) {
                break;
            }
        }
    }
}

function looks_like_non_html_asset(string $url): bool
{
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    return (bool) preg_match('~\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|rar|mp4|mp3|css|js|woff2?|ttf|ico)(\?.*)?$~', $path);
}

function analyze_html_page(string $url, string $html, int $statusCode, int $loadTimeMs, ?string $downloadWarning = null): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $text = extract_visible_text($xpath);

    $title = node_text($xpath, '//title');
    $description = meta_content($xpath, 'description');
    $h1s = node_texts($xpath, '//h1');
    $headings = [];
    foreach (['h1', 'h2', 'h3'] as $tag) {
        $headings[$tag] = node_texts($xpath, '//' . $tag);
    }

    $images = $xpath->query('//img') ?: [];
    $imagesWithoutAlt = 0;
    foreach ($images as $image) {
        if (!$image instanceof DOMElement || trim($image->getAttribute('alt')) === '') {
            $imagesWithoutAlt++;
        }
    }

    $structuredData = extract_structured_data($xpath);
    $links = extract_links($xpath, $url);
    $answerBlocks = extract_answer_blocks($xpath);
    $entitySignals = extract_entity_signals($structuredData, $text);
    $mediaSignals = extract_media_signals($xpath);
    $rawHtmlSignals = extract_raw_html_signals($xpath, $html, $text);
    $uxSignals = extract_ux_signals($xpath, $url, $text, $title, $description, $h1s);
    $contextSignals = detect_page_context($text, $structuredData);
    $signals = build_page_signals($xpath, $text, $structuredData, $answerBlocks, $entitySignals, $mediaSignals, $rawHtmlSignals, $uxSignals, $contextSignals);
    $issues = build_page_issues($title, $description, $h1s, $headings, $images->length, $imagesWithoutAlt, $structuredData, $signals, $loadTimeMs, $answerBlocks, $entitySignals, $rawHtmlSignals, $uxSignals, $downloadWarning);
    $score = page_score($issues, $signals);

    return [
        'url' => $url,
        'ok' => true,
        'status_code' => $statusCode,
        'load_time_ms' => $loadTimeMs,
        'score' => $score,
        'title' => $title,
        'description' => $description,
        'h1' => $h1s,
        'word_count' => count_words($text),
        'headings' => $headings,
        'images_total' => $images->length,
        'images_without_alt' => $imagesWithoutAlt,
        'structured_data' => $structuredData,
        'answer_blocks' => $answerBlocks,
        'entity_signals' => $entitySignals,
        'media_signals' => $mediaSignals,
        'raw_html_signals' => $rawHtmlSignals,
        'ux_signals' => $uxSignals,
        'context_signals' => $contextSignals,
        'signals' => $signals,
        'issues' => $issues,
        'links' => $links,
    ];
}

function extract_visible_text(DOMXPath $xpath): string
{
    $nodes = $xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::svg)]');
    $parts = [];

    if ($nodes) {
        foreach ($nodes as $node) {
            $text = trim(preg_replace('~\s+~u', ' ', (string) $node->nodeValue));
            if ($text !== '') {
                $parts[] = $text;
            }
        }
    }

    return trim(preg_replace('~\s+~u', ' ', implode(' ', $parts)) ?: '');
}

function node_text(DOMXPath $xpath, string $query): string
{
    $nodes = $xpath->query($query);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    return trim(preg_replace('~\s+~u', ' ', (string) $nodes->item(0)?->textContent));
}

function node_texts(DOMXPath $xpath, string $query): array
{
    $nodes = $xpath->query($query);
    $values = [];
    if (!$nodes) {
        return $values;
    }

    foreach ($nodes as $node) {
        $text = trim(preg_replace('~\s+~u', ' ', (string) $node->textContent));
        if ($text !== '') {
            $values[] = $text;
        }
    }

    return $values;
}

function meta_content(DOMXPath $xpath, string $name): string
{
    $query = sprintf('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]/@content', strtolower($name));
    $nodes = $xpath->query($query);

    return $nodes && $nodes->length ? trim((string) $nodes->item(0)?->nodeValue) : '';
}

function extract_structured_data(DOMXPath $xpath): array
{
    $items = [];
    $scripts = $xpath->query('//script[@type="application/ld+json"]') ?: [];

    foreach ($scripts as $script) {
        $json = trim((string) $script->textContent);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $items[] = ['type' => 'Invalid JSON-LD', 'valid' => false];
            continue;
        }

        $candidates = isset($decoded['@graph']) && is_array($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $items[] = [
                    'type' => $candidate['@type'] ?? 'Unknown',
                    'valid' => true,
                    'has_id' => isset($candidate['@id']) && trim((string) $candidate['@id']) !== '',
                    'has_same_as' => isset($candidate['sameAs']),
                    'has_author' => isset($candidate['author']),
                    'has_date_modified' => isset($candidate['dateModified']) || isset($candidate['datePublished']),
                ];
            }
        }
    }

    return $items;
}

function extract_answer_blocks(DOMXPath $xpath): array
{
    $blocks = [];
    $headings = $xpath->query('//h2|//h3') ?: [];

    foreach ($headings as $heading) {
        if (!$heading instanceof DOMElement) {
            continue;
        }

        $question = trim(preg_replace('~\s+~u', ' ', (string) $heading->textContent));
        $isQuestionLike = (bool) preg_match('~\?|\b(mi|hogyan|mikor|miért|mennyibe|melyik|what|how|why|when|which)\b~iu', $question);
        if (!$isQuestionLike) {
            continue;
        }

        $paragraph = next_textual_sibling($heading);
        if ($paragraph === '') {
            continue;
        }

        $wordCount = count_words($paragraph);
        $blocks[] = [
            'heading' => $question,
            'answer' => text_excerpt($paragraph, 420),
            'word_count' => $wordCount,
            'is_citation_sized' => $wordCount >= 35 && $wordCount <= 90,
            'has_numbers' => (bool) preg_match('~\d~', $paragraph),
            'has_source_language' => (bool) preg_match('~\b(forrás|source|kutatás|study|adat|evidence|hivatkozás)\b~iu', $paragraph),
        ];
    }

    return array_slice($blocks, 0, 12);
}

function next_textual_sibling(DOMElement $node): string
{
    $current = $node->nextSibling;
    while ($current) {
        if ($current instanceof DOMElement && in_array(strtolower($current->tagName), ['p', 'div', 'section', 'article', 'ul', 'ol'], true)) {
            $text = trim(preg_replace('~\s+~u', ' ', (string) $current->textContent));
            if ($text !== '') {
                return $text;
            }
        }
        $current = $current->nextSibling;
    }

    return '';
}

function extract_entity_signals(array $structuredData, string $text): array
{
    $types = array_map(static fn (array $item): string => is_array($item['type'] ?? null) ? implode(',', $item['type']) : (string) ($item['type'] ?? ''), $structuredData);
    $itemsWithId = count(array_filter($structuredData, static fn (array $item): bool => ($item['has_id'] ?? false) === true));
    $itemsWithSameAs = count(array_filter($structuredData, static fn (array $item): bool => ($item['has_same_as'] ?? false) === true));

    return [
        'schema_types' => array_values(array_unique(array_filter($types))),
        'items_with_id' => $itemsWithId,
        'items_with_same_as' => $itemsWithSameAs,
        'has_person_or_author_schema' => contains_type($types, 'Person') || count(array_filter($structuredData, static fn (array $item): bool => ($item['has_author'] ?? false) === true)) > 0,
        'has_organization_identity' => contains_type($types, 'Organization') || contains_type($types, 'LocalBusiness'),
        'has_claim_or_fact_language' => (bool) preg_match('~\b(adat|kutatás|tanulmány|mérés|bizonyít|forrás|evidence|research|study|data)\b~iu', $text),
    ];
}

function extract_media_signals(DOMXPath $xpath): array
{
    $figures = $xpath->query('//figure')?->length ?? 0;
    $videos = $xpath->query('//video|//iframe[contains(@src,"youtube") or contains(@src,"vimeo")]')?->length ?? 0;
    $tables = $xpath->query('//table')?->length ?? 0;

    return [
        'figures' => $figures,
        'videos' => $videos,
        'tables' => $tables,
        'has_visual_evidence' => ($figures + $videos + $tables) > 0,
    ];
}

function extract_raw_html_signals(DOMXPath $xpath, string $html, string $visibleText): array
{
    $scriptCount = $xpath->query('//script')?->length ?? 0;
    $styleCount = $xpath->query('//style|//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="stylesheet"]')?->length ?? 0;
    $appRoots = $xpath->query('//*[@id="root" or @id="app" or @id="__next" or @id="__nuxt" or @data-reactroot]') ?: [];
    $hasEmptyAppRoot = false;

    foreach ($appRoots as $root) {
        $rootText = trim(preg_replace('~\s+~u', ' ', (string) $root->textContent));
        if (count_words($rootText) < 25) {
            $hasEmptyAppRoot = true;
            break;
        }
    }

    $frameworkHints = [];
    $frameworkPatterns = [
        'Next.js' => '~__NEXT_DATA__|/_next/|next-route-announcer~i',
        'Nuxt/Vue' => '~__NUXT__|data-v-|vue(?:\.|-)~i',
        'React' => '~react(?:\.production|\.development|-dom)|data-reactroot~i',
        'Angular' => '~ng-version|angular(?:\.min)?\.js|_nghost~i',
        'Svelte/SvelteKit' => '~svelte|/_app/immutable/~i',
        'Astro' => '~astro-island|astro-component~i',
    ];

    foreach ($frameworkPatterns as $name => $pattern) {
        if (preg_match($pattern, $html)) {
            $frameworkHints[] = $name;
        }
    }

    $rawHtmlBytes = strlen($html);
    $visibleChars = text_length($visibleText);
    $visibleWords = count_words($visibleText);
    $ratio = $rawHtmlBytes > 0 ? round(($visibleChars / $rawHtmlBytes) * 100, 2) : 0.0;
    $hasNoscriptContent = (bool) $xpath->query('//noscript[string-length(normalize-space(.)) > 30]')?->length;
    $hasMetaViewport = (bool) $xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="viewport"]')?->length;
    $likelyClientRenderedShell = ($hasEmptyAppRoot && $visibleWords < 160 && $scriptCount >= 6)
        || ($visibleWords < 240 && $ratio < 2.5 && $scriptCount >= 16);

    return [
        'raw_html_bytes' => $rawHtmlBytes,
        'visible_text_chars' => $visibleChars,
        'visible_word_count' => $visibleWords,
        'text_to_html_ratio_percent' => $ratio,
        'script_count' => $scriptCount,
        'style_count' => $styleCount,
        'framework_hints' => array_values(array_unique($frameworkHints)),
        'has_empty_app_root' => $hasEmptyAppRoot,
        'has_noscript_content' => $hasNoscriptContent,
        'has_meta_viewport' => $hasMetaViewport,
        'has_substantial_raw_content' => $visibleWords >= 250 || $ratio >= 4.0,
        'has_low_js_dependency' => $scriptCount <= 14 || $ratio >= 4.0 || $visibleWords >= 450,
        'likely_client_rendered_shell' => $likelyClientRenderedShell,
    ];
}

function extract_ux_signals(DOMXPath $xpath, string $baseUrl, string $text, string $title, string $description, array $h1s): array
{
    $navAnchors = $xpath->query('//nav//a[@href] | //header//a[@href]') ?: [];
    $navLinks = [];
    $externalNavLinks = [];
    $navLabels = [];

    foreach ($navAnchors as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }

        $href = absolute_url($anchor->getAttribute('href'), $baseUrl);
        $label = trim(preg_replace('~\s+~u', ' ', (string) $anchor->textContent));
        if (!$href) {
            continue;
        }

        $navLinks[$href] = true;
        if ($label !== '') {
            $navLabels[text_lower($label)] = true;
        }
        if (!same_host($href, $baseUrl)) {
            $externalNavLinks[] = [
                'url' => $href,
                'label' => $label,
            ];
        }
    }

    $ctaTexts = collect_cta_texts($xpath);
    $contactPattern = '~\b(contact|kapcsolat|ajánlat|ajanlat|quote|sales|demo|book|hívj|irj|írj|email|telefon)\b~iu';
    $newsletterPattern = '~\b(hírlevél|hirlevel|newsletter|subscribe|feliratkoz|letöltés|letoltes|download|lead magnet|whitepaper)\b~iu';
    $hasContactPath = (bool) preg_match($contactPattern, $text)
        || ($xpath->query('//a[starts-with(@href,"mailto:") or starts-with(@href,"tel:")]')?->length ?? 0) > 0
        || ($xpath->query('//form')?->length ?? 0) > 0;
    $hasNewsletterOrLeadCapture = (bool) preg_match($newsletterPattern, $text)
        || ($xpath->query('//input[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="email"]')?->length ?? 0) > 0;

    $navCount = count($navLinks);
    $navDepth = max_nav_list_depth($xpath);
    $ctaVariants = count(array_unique(array_map('text_lower', $ctaTexts)));
    $purposeText = trim($title . ' ' . $description . ' ' . implode(' ', $h1s));
    $hasClearPurpose = count_words($purposeText) >= 8
        && count($h1s) === 1
        && ($description !== '' || count_words($text) >= 120);

    return [
        'nav_link_count' => $navCount,
        'nav_label_count' => count($navLabels),
        'nav_max_depth' => $navDepth,
        'external_nav_links' => array_slice($externalNavLinks, 0, 8),
        'cta_count' => count($ctaTexts),
        'cta_label_variants' => $ctaVariants,
        'cta_labels' => array_slice(array_values(array_unique($ctaTexts)), 0, 12),
        'has_clear_page_purpose' => $hasClearPurpose,
        'has_manageable_navigation' => $navCount === 0 || ($navCount <= 9 && $navDepth <= 2),
        'has_external_nav_links' => count($externalNavLinks) > 0,
        'has_consistent_cta_language' => count($ctaTexts) === 0 || $ctaVariants <= 6,
        'has_contact_path' => $hasContactPath,
        'has_newsletter_or_lead_capture' => $hasNewsletterOrLeadCapture,
        'has_user_journey_entry' => $hasClearPurpose && ($hasContactPath || count($ctaTexts) > 0),
    ];
}

function detect_page_context(string $text, array $structuredData): array
{
    $lower = text_lower($text);
    $types = array_map(static fn (array $item): string => is_array($item['type'] ?? null) ? implode(',', $item['type']) : (string) ($item['type'] ?? ''), $structuredData);
    $typeText = text_lower(implode(' ', $types));

    $b2bPattern = '~\b(b2b|vállalati|vallalati|enterprise|saas|ügynökség|ugynokseg|agency|tanácsadás|tanacsadas|consulting|partner|beszerzés|beszerzes|ajánlatkérés|ajanlatkeres|demo|sales|szolgáltatás|szolgaltatas)\b~iu';
    $ecommercePattern = '~\b(webshop|kosár|kosar|checkout|termék|termek|sku|készlet|keszlet|szállítás|szallitas|vásárlás|vasarlas|rendelés|rendeles|ár|ar|akció|akcio)\b~iu';
    $healthGovPattern = '~\b(kormány|kormany|önkormányzat|onkormanyzat|gov|egészség|egeszseg|klinika|kórház|korhaz|orvos|patient|health)\b~iu';

    $isB2b = (bool) preg_match($b2bPattern, $lower);
    $isEcommerce = (bool) preg_match($ecommercePattern, $lower) || str_contains($typeText, 'product') || str_contains($typeText, 'offer');
    $isHealthOrGov = (bool) preg_match($healthGovPattern, $lower)
        || str_contains($typeText, 'medical')
        || str_contains($typeText, 'government');

    $primary = 'általános szolgáltatói/tartalmi oldal';
    if ($isHealthOrGov) {
        $primary = 'egészségügyi vagy közigazgatási tartalom';
    } elseif ($isB2b) {
        $primary = 'B2B vagy szolgáltatói döntéstámogató oldal';
    } elseif ($isEcommerce) {
        $primary = 'e-kereskedelmi vagy termékoldal';
    }

    return [
        'primary_context' => $primary,
        'is_b2b_or_service' => $isB2b,
        'is_ecommerce' => $isEcommerce,
        'is_health_or_government' => $isHealthOrGov,
    ];
}

function collect_cta_texts(DOMXPath $xpath): array
{
    $nodes = $xpath->query('//a | //button') ?: [];
    $texts = [];
    $pattern = '~\b(contact|kapcsolat|learn more|tudj meg|read more|tovább|tovabb|request|quote|ajánlat|ajanlat|demo|book|download|letölt|subscribe|feliratkoz|join|career|shop|buy|vásárl|vasarl)\b~iu';

    foreach ($nodes as $node) {
        $text = trim(preg_replace('~\s+~u', ' ', (string) $node->textContent));
        if ($text === '' || !preg_match($pattern, $text)) {
            continue;
        }

        if (text_length($text) > 90 && preg_match('~(Tovább[^→]{0,50}→|Tovabb[^→]{0,50}→|Learn more|Read more|Contact us|Kapcsolat|Ajánlat[^.]{0,40}|Feliratkoz[^.]{0,40}|Download[^.]{0,40})~iu', $text, $match)) {
            $text = trim($match[1]);
        }

        if (text_length($text) <= 120) {
            $texts[] = $text;
        }
    }

    return $texts;
}

function max_nav_list_depth(DOMXPath $xpath): int
{
    $nodes = $xpath->query('//nav//li | //header//li') ?: [];
    $maxDepth = 0;

    foreach ($nodes as $node) {
        $depth = 0;
        $current = $node;
        while ($current instanceof DOMNode) {
            if ($current instanceof DOMElement && strtolower($current->tagName) === 'li') {
                $depth++;
            }
            $current = $current->parentNode;
        }
        $maxDepth = max($maxDepth, $depth);
    }

    return $maxDepth;
}

function extract_links(DOMXPath $xpath, string $baseUrl): array
{
    $internal = [];
    $external = [];
    $anchors = $xpath->query('//a[@href]') ?: [];

    foreach ($anchors as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }
        $absolute = absolute_url($anchor->getAttribute('href'), $baseUrl);
        if (!$absolute) {
            continue;
        }
        if (same_host($absolute, $baseUrl)) {
            $internal[] = $absolute;
        } else {
            $external[] = $absolute;
        }
    }

    return [
        'internal' => array_values(array_unique($internal)),
        'external' => array_values(array_unique($external)),
    ];
}

function build_page_signals(DOMXPath $xpath, string $text, array $structuredData, array $answerBlocks, array $entitySignals, array $mediaSignals, array $rawHtmlSignals, array $uxSignals, array $contextSignals): array
{
    $lower = text_lower($text);
    $types = array_map(static fn (array $item): string => is_array($item['type'] ?? null) ? implode(',', $item['type']) : (string) ($item['type'] ?? ''), $structuredData);
    $citationSizedBlocks = count(array_filter($answerBlocks, static fn (array $block): bool => ($block['is_citation_sized'] ?? false) === true));
    $hasQuestionHeadings = (bool) preg_match('~\?|\b(mi|hogyan|mikor|miért|mennyibe|melyik|kinek|mit kap|mennyi idő|which|what|how|why|when|for whom)\b~iu', implode(' ', node_texts($xpath, '//h2|//h3')));
    $hasVisibleQaSupport = $hasQuestionHeadings || $citationSizedBlocks > 0 || (bool) preg_match('~\b(gyakori kérdések|gyik|faq|kérdések és válaszok|questions and answers|pricing|árak|folyamat|referenciák|case study|esettanulmány)\b~iu', $text);

    return [
        'is_b2b_or_service_context' => ($contextSignals['is_b2b_or_service'] ?? false) === true,
        'is_ecommerce_context' => ($contextSignals['is_ecommerce'] ?? false) === true,
        'is_health_or_government_context' => ($contextSignals['is_health_or_government'] ?? false) === true,
        'has_faq_schema' => contains_type($types, 'FAQPage'),
        'has_article_schema' => contains_type($types, 'Article') || contains_type($types, 'BlogPosting'),
        'has_organization_schema' => contains_type($types, 'Organization') || contains_type($types, 'LocalBusiness'),
        'has_breadcrumb_schema' => contains_type($types, 'BreadcrumbList'),
        'has_stable_schema_ids' => ($entitySignals['items_with_id'] ?? 0) > 0,
        'has_sameas_identity' => ($entitySignals['items_with_same_as'] ?? 0) > 0,
        'has_person_or_author_schema' => ($entitySignals['has_person_or_author_schema'] ?? false) === true,
        'has_question_headings' => $hasQuestionHeadings,
        'has_contextual_qa_support' => $hasVisibleQaSupport,
        'faq_schema_fits_context' => contains_type($types, 'FAQPage') && $hasVisibleQaSupport && (($contextSignals['is_health_or_government'] ?? false) === true || !empty($answerBlocks)),
        'has_answer_first_blocks' => $citationSizedBlocks > 0,
        'has_summary_language' => str_contains($lower, 'összefoglal') || str_contains($lower, 'röviden') || str_contains($lower, 'summary') || str_contains($lower, 'key takeaways'),
        'has_author_signal' => (bool) preg_match('~\b(szerző|author|írta|by)\b~iu', $text),
        'has_date_signal' => (bool) preg_match('~\b(20[0-9]{2}[.\-/ ]\d{1,2}[.\-/ ]\d{1,2}|frissítve|updated|published)\b~iu', $text),
        'has_citation_signal' => (bool) preg_match('~\b(forrás|source|hivatkozás|citation|references)\b~iu', $text),
        'has_claim_evidence_language' => ($entitySignals['has_claim_or_fact_language'] ?? false) === true,
        'has_visual_evidence' => ($mediaSignals['has_visual_evidence'] ?? false) === true,
        'has_raw_html_content' => ($rawHtmlSignals['has_substantial_raw_content'] ?? false) === true,
        'avoids_client_rendered_shell' => ($rawHtmlSignals['likely_client_rendered_shell'] ?? false) !== true,
        'has_low_js_dependency' => ($rawHtmlSignals['has_low_js_dependency'] ?? false) === true,
        'has_meta_viewport' => ($rawHtmlSignals['has_meta_viewport'] ?? false) === true,
        'has_clear_page_purpose' => ($uxSignals['has_clear_page_purpose'] ?? false) === true,
        'has_manageable_navigation' => ($uxSignals['has_manageable_navigation'] ?? false) === true,
        'has_consistent_cta_language' => ($uxSignals['has_consistent_cta_language'] ?? false) === true,
        'has_contact_path' => ($uxSignals['has_contact_path'] ?? false) === true,
        'has_newsletter_or_lead_capture' => ($uxSignals['has_newsletter_or_lead_capture'] ?? false) === true,
        'has_user_journey_entry' => ($uxSignals['has_user_journey_entry'] ?? false) === true,
        'has_open_graph' => ($xpath->query('//meta[starts-with(@property,"og:")]')?->length ?? 0) > 0,
        'has_twitter_card' => ($xpath->query('//meta[starts-with(@name,"twitter:")]')?->length ?? 0) > 0,
        'has_canonical' => ($xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]')?->length ?? 0) > 0,
    ];
}

function contains_type(array $types, string $needle): bool
{
    foreach ($types as $type) {
        if (stripos($type, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function build_page_issues(string $title, string $description, array $h1s, array $headings, int $imagesTotal, int $imagesWithoutAlt, array $structuredData, array $signals, int $loadTimeMs, array $answerBlocks, array $entitySignals, array $rawHtmlSignals, array $uxSignals, ?string $downloadWarning): array
{
    $issues = [];

    add_issue($issues, $title === '', 'critical', 'Hiányzik a title tag.', 'Adj minden fontos oldalnak egyedi, 45-65 karakteres címet, amely tartalmazza a fő entitást és az ígéretet.');
    add_issue($issues, text_length($title) > 70, 'warning', 'A title túl hosszú lehet.', 'Rövidítsd úgy, hogy a kereső és AI összefoglalókban is gyorsan értelmezhető maradjon.');
    add_issue($issues, $description === '', 'critical', 'Hiányzik a meta description.', 'Írj 140-160 karakteres összefoglalót, amely konkrét választ ad arra, mire jó az oldal.');
    add_issue($issues, count($h1s) !== 1, 'critical', 'Nem pontosan egy H1 címsor található.', 'Használj egyetlen H1-et, amely világosan megnevezi az oldal témáját.');
    add_issue($issues, !($signals['has_clear_page_purpose'] ?? false), 'warning', 'Nem elég egyértelmű az oldal célja és célközönsége.', 'Tisztázd, hogy az oldal kinek szól, milyen döntést segít, és mi a következő lépés. A H1, bevezető és első CTA ugyanarra az útvonalra mutasson.');
    add_issue($issues, !($signals['has_manageable_navigation'] ?? false), 'warning', 'A fontos oldalak túl sok menülépés után érhetők el.', 'Egyszerűsítsd a fő navigációt célcsoport vagy user journey szerint. A legfontosabb kategóriák legyenek kattintható landing oldalak, ne csak rejtett, többszintű lenyílók.');
    add_issue($issues, ($uxSignals['has_external_nav_links'] ?? false) === true, 'info', 'Külső oldalak keverednek a fő navigációba.', 'Jelöld vizuálisan és szövegben is, ha egy menüpont külső oldalra, webshopba vagy külön karrierfelületre visz.');
    add_issue($issues, !($signals['has_consistent_cta_language'] ?? false), 'info', 'A CTA gombok nyelvezete nem elég következetes.', 'Egységesítsd a gombokat: külön minta kapcsolatfelvételre, további információra, letöltésre és külső felületre.');
    add_issue($issues, !($signals['has_contact_path'] ?? false), 'warning', 'Nincs egyértelmű kapcsolatfelvételi út.', 'Adj kiszámítható kontakt útvonalat a fő CTA-ban és a láblécben is. Kerüld, hogy ugyanaz a kontaktfolyamat hol popupként, hol külön oldalként jelenjen meg.');
    add_issue($issues, !($signals['has_newsletter_or_lead_capture'] ?? false), 'info', 'Nem látszik hírlevél vagy lead capture lehetőség.', 'Ha az oldal célja bizalomépítés vagy sales támogatás, adj hírlevél-feliratkozást, letölthető anyagot vagy ajánlatkérő modult releváns pontokra.');
    add_issue($issues, count($headings['h2'] ?? []) < 2, 'warning', 'Kevés H2 szakaszcím van.', 'Törd a tartalmat kérdés-válasz jellegű, jól idézhető szakaszokra.');
    add_issue($issues, $imagesTotal > 0 && $imagesWithoutAlt > 0, 'warning', 'Vannak alt szöveg nélküli képek.', 'Adj leíró alt szöveget a képeknek, különösen ahol termék, szolgáltatás vagy folyamat látható.');
    add_issue($issues, ($rawHtmlSignals['likely_client_rendered_shell'] ?? false) === true, 'critical', 'A fő tartalom valószínűleg JavaScript után jelenik meg.', 'Tedd a legfontosabb szöveget már a szerver által küldött HTML-be SSR, SSG vagy statikus HTML segítségével. Sok AI crawler nem futtat JavaScriptet.');
    add_issue($issues, !($signals['has_raw_html_content'] ?? false), 'warning', 'Kevés értelmes tartalom látszik a nyers HTML-ben.', 'Növeld a szerveroldalon érkező főszöveget: címsorok, szolgáltatásleírás, ár/folyamat/FAQ válaszok és belső linkek ne csak kliensoldali rendereléssel jelenjenek meg.');
    add_issue($issues, !($signals['has_low_js_dependency'] ?? false), 'warning', 'Magas JavaScript-függőség látszik a nyers HTML-hez képest.', 'A kritikus tartalmat és navigációt ne bízd kizárólag hidratálásra vagy AJAX hívásokra; a crawlernek az első HTML válaszban is legyen mit olvasnia.');
    add_issue($issues, !($signals['has_meta_viewport'] ?? false), 'info', 'Hiányzik a mobil viewport meta tag.', 'Adj <meta name="viewport" content="width=device-width, initial-scale=1"> sort, hogy a mobilbarát értelmezés és megjelenítés stabil legyen.');
    add_issue($issues, (($signals['is_b2b_or_service_context'] ?? false) === true || ($signals['is_ecommerce_context'] ?? false) === true) && !($signals['has_contextual_qa_support'] ?? false), 'warning', 'Hiányzik a kontextushoz illő döntéstámogató Q&A tartalom.', 'B2B oldalon ne általános GYIK-et írj: válaszolj a döntéshozói kérdésekre, például kinek való, milyen folyamatban dolgoztok, milyen bizonyíték vagy referencia támasztja alá. E-kereskedelemnél a termékhez, mérethez, szállításhoz és garanciához kapcsolódó válaszok legyenek láthatók.');
    add_issue($issues, ($signals['has_faq_schema'] ?? false) === true && !($signals['faq_schema_fits_context'] ?? false), 'info', 'FAQPage schema van, de nem ez a fő láthatósági eszköz.', 'A Google FAQ rich result 2026. május 7-től nem jelenik meg Searchben, ezért ne általános schema-fixként kezeld. Tartsd meg csak akkor, ha valódi, látható kérdés-válasz tartalmat ír le; B2B oldalon inkább döntéstámogató válaszblokkokat és bizonyítékokat építs.');
    add_issue($issues, !$signals['has_organization_schema'], 'info', 'Nem látható Organization vagy LocalBusiness séma.', 'Adj szervezeti entitásjelölést névvel, URL-lel, logóval, kapcsolati pontokkal és social profilokkal.');
    add_issue($issues, !$signals['has_breadcrumb_schema'], 'info', 'Hiányzik a BreadcrumbList séma.', 'A hierarchiát jelöld breadcrumb strukturált adattal, hogy AI rendszerek könnyebben értsék az oldal helyét.');
    add_issue($issues, !$signals['has_stable_schema_ids'], 'warning', 'Hiányoznak a stabil @id azonosítók a strukturált adatokból.', 'Adj tartós @id URL-eket a fő entitásokhoz, például Organization, WebPage, Article és Person elemekhez.');
    add_issue($issues, !$signals['has_sameas_identity'], 'info', 'Gyenge külső entitás-összekapcsolás.', 'A márka/szervezet schema elemén használd a sameAs mezőt releváns közösségi, adatbázis vagy szakmai profilokra.');
    add_issue($issues, !$signals['has_person_or_author_schema'], 'info', 'A szerzői szakértelem nincs strukturált adattal megtámogatva.', 'Szakértői tartalomnál kapjon a szerző Person vagy author jelölést, rövid bio és hitelességi bizonyíték mellett.');
    add_issue($issues, empty($answerBlocks), 'warning', 'Nem találtunk citációra alkalmas kérdés-válasz blokkot.', 'A fontos H2 szakaszok után adj 40-90 szavas direkt választ, majd részletező magyarázatot és bizonyítékot.');
    add_issue($issues, !$signals['has_summary_language'], 'warning', 'Nincs jól azonosítható rövid összefoglaló blokk.', 'Tegyél a fő tartalom elejére 3-5 pontos, tényszerű összefoglalót.');
    add_issue($issues, !$signals['has_author_signal'], 'info', 'Gyenge szerzői vagy felelősségi jel.', 'Tüntesd fel a szerzőt, szakértőt vagy szervezetet, aki a tartalomért felel.');
    add_issue($issues, !$signals['has_date_signal'], 'info', 'Nem látszik frissítési vagy publikálási dátum.', 'AI keresőknek hasznos a publikálva/frissítve dátum, főleg változó témáknál.');
    add_issue($issues, !$signals['has_citation_signal'], 'info', 'Kevés forrás- vagy hivatkozási jel.', 'Fontos állításoknál adj forrásokat, referenciákat vagy bizonyítékokat.');
    add_issue($issues, !$signals['has_open_graph'], 'info', 'Hiányoznak Open Graph meta adatok.', 'Adj og:title, og:description és og:image mezőket a megoszthatóság és entitáskonzisztencia miatt.');
    add_issue($issues, !$signals['has_canonical'], 'warning', 'Hiányzik a canonical link.', 'Adj canonical URL-t, hogy a duplikált útvonalak ne gyengítsék a jeleket.');
    add_issue($issues, $loadTimeMs > 2500, 'warning', 'Az oldal lassan töltődött be az audit során.', 'Optimalizáld a képeket, cache-t és kritikus CSS/JS betöltést.');
    add_issue($issues, empty($structuredData), 'warning', 'Nincs JSON-LD strukturált adat.', 'Legalább WebSite, Organization és tartalomtípustól függően Article/Product/Service sémát használj.');
    add_issue($issues, $downloadWarning !== null, 'info', 'A letöltés TLS fallbackkel futott.', 'Éles környezetben állíts be friss CA bundle-t a PHP/cURL számára, hogy az audit teljes TLS ellenőrzéssel működjön.');
    add_issue($issues, !($entitySignals['has_claim_or_fact_language'] ?? false), 'info', 'Kevés explicit bizonyítéknyelv és adatjel található.', 'A kulcsállításokhoz adj mérési adatot, dátumot, forrást, példát vagy esettanulmányt, hogy az állítás idézhető legyen.');

    return $issues;
}

function add_issue(array &$issues, bool $condition, string $level, string $text, string $fix): void
{
    if ($condition) {
        $issues[] = ['level' => $level, 'text' => $text, 'fix' => $fix];
    }
}

function page_score(array $issues, array $signals): int
{
    $score = 100;
    foreach ($issues as $issue) {
        $score -= match ($issue['level']) {
            'critical' => 12,
            'warning' => 7,
            default => 3,
        };
    }

    foreach ($signals as $enabled) {
        if ($enabled === true) {
            $score += 2;
        }
    }

    return max(0, min(100, $score));
}

function aggregate_findings(string $rootUrl, array $pages, array $siteFiles): array
{
    $pageCount = max(1, count($pages));
    $scores = [
        'technical' => 0,
        'seo' => 0,
        'aio' => 0,
        'visibility' => 0,
        'ux' => 0,
        'content' => 0,
        'entity' => 0,
        'agentic' => 0,
    ];
    $allIssues = [];

    foreach ($pages as $page) {
        $scores['technical'] += ($page['ok'] ?? false) ? min(100, 100 - (($page['load_time_ms'] ?? 0) > 2500 ? 20 : 0)) : 0;
        $scores['seo'] += estimate_seo_score($page);
        $scores['aio'] += estimate_aio_score($page);
        $scores['visibility'] += estimate_ai_search_visibility_score($page);
        $scores['ux'] += estimate_ux_score($page);
        $scores['content'] += estimate_content_score($page);
        $scores['entity'] += estimate_entity_score($page);
        $scores['agentic'] += estimate_agentic_score($page, $siteFiles);

        foreach (($page['issues'] ?? []) as $issue) {
            $key = $issue['text'];
            if (!isset($allIssues[$key])) {
                $allIssues[$key] = $issue + ['count' => 0, 'pages' => []];
            }
            $allIssues[$key]['count']++;
            $allIssues[$key]['pages'][] = $page['url'];
        }
    }

    foreach ($scores as $key => $value) {
        $scores[$key] = (int) round($value / $pageCount);
    }

    $overall = (int) round(
        ($scores['technical'] * 0.14)
        + ($scores['seo'] * 0.13)
        + ($scores['aio'] * 0.18)
        + ($scores['visibility'] * 0.13)
        + ($scores['ux'] * 0.14)
        + ($scores['content'] * 0.15)
        + ($scores['entity'] * 0.08)
        + ($scores['agentic'] * 0.05)
    );

    return [
        'overall_score' => $overall,
        'scores' => $scores,
        'summary' => [
            'label' => score_label($overall),
            'pages_checked' => count($pages),
            'root_url' => $rootUrl,
            'critical_count' => count(array_filter($allIssues, static fn ($issue): bool => ($issue['level'] ?? '') === 'critical')),
            'warning_count' => count(array_filter($allIssues, static fn ($issue): bool => ($issue['level'] ?? '') === 'warning')),
            'methodology_note' => 'Google 2026 szerint a generatív keresési láthatóság alapja továbbra is a crawlolható, hasznos, egyedi tartalom. A FAQ rich result kivezetése után a pontozás nagyobb súlyt ad a nyers HTML-ben látható fő tartalomnak, a citációs blokkoknak és az entitásjeleknek.',
        ],
        'recommendations' => prioritize_recommendations(add_site_file_issues(array_values($allIssues), $siteFiles, $rootUrl)),
    ];
}

function add_site_file_issues(array $issues, array $siteFiles, string $rootUrl): array
{
    if (!($siteFiles['robots']['exists'] ?? false)) {
        $issues[] = [
            'level' => 'warning',
            'text' => 'Nem található robots.txt.',
            'fix' => 'Hozz létre robots.txt fájlt sitemap hivatkozással és tudatos AI crawler szabályokkal. A Google Search AI funkciókhoz a Googlebot hozzáférése kritikus.',
            'count' => 1,
            'pages' => [$rootUrl],
        ];
    }

    if (!($siteFiles['sitemap_declared'] ?? false)) {
        $issues[] = [
            'level' => 'info',
            'text' => 'A robots.txt nem hivatkozik sitemap fájlra.',
            'fix' => 'Adj Sitemap sort a robots.txt-hez, hogy a fontos URL-ek gyorsabban feltérképezhetők legyenek.',
            'count' => 1,
            'pages' => [$rootUrl],
        ];
    }

    if (!($siteFiles['llms']['exists'] ?? false)) {
        $issues[] = [
            'level' => 'info',
            'text' => 'Nem található llms.txt.',
            'fix' => 'Az llms.txt nem hivatalos Google követelmény, de alacsony költségű navigációs térkép lehet AI eszközök és jövőbeli agentikus rendszerek számára.',
            'count' => 1,
            'pages' => [$rootUrl],
        ];
    }

    return $issues;
}

function estimate_seo_score(array $page): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $score = 100;
    if (($page['title'] ?? '') === '') {
        $score -= 22;
    }
    if (($page['description'] ?? '') === '') {
        $score -= 18;
    }
    if (count($page['h1'] ?? []) !== 1) {
        $score -= 18;
    }
    if (($page['images_without_alt'] ?? 0) > 0) {
        $score -= 10;
    }
    if (!(($page['signals']['has_canonical'] ?? false))) {
        $score -= 8;
    }
    if (!(($page['signals']['has_raw_html_content'] ?? false))) {
        $score -= 12;
    }
    if (!(($page['signals']['avoids_client_rendered_shell'] ?? true))) {
        $score -= 18;
    }

    return max(0, $score);
}

function estimate_aio_score(array $page): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $signals = $page['signals'] ?? [];
    $weights = [
        'has_raw_html_content' => 16,
        'avoids_client_rendered_shell' => 14,
        'has_low_js_dependency' => 8,
        'has_article_schema' => 10,
        'has_organization_schema' => 12,
        'has_breadcrumb_schema' => 8,
        'has_stable_schema_ids' => 10,
        'has_question_headings' => 12,
        'has_answer_first_blocks' => 14,
        'has_summary_language' => 14,
        'has_author_signal' => 8,
        'has_date_signal' => 8,
        'has_citation_signal' => 8,
        'has_claim_evidence_language' => 8,
        'has_open_graph' => 4,
    ];

    $score = 0;
    foreach ($weights as $signal => $weight) {
        if (($signals[$signal] ?? false) === true) {
            $score += $weight;
        }
    }

    return max(0, min(100, $score));
}

function estimate_ai_search_visibility_score(array $page): int
{
    $signals = $page['signals'] ?? [];
    $score = 0;

    $weights = [
        'has_raw_html_content' => 14,
        'has_contextual_qa_support' => 14,
        'has_answer_first_blocks' => 18,
        'has_claim_evidence_language' => 16,
        'has_citation_signal' => 12,
        'has_summary_language' => 10,
        'has_organization_schema' => 8,
        'has_sameas_identity' => 4,
        'has_stable_schema_ids' => 4,
    ];

    foreach ($weights as $signal => $weight) {
        if (($signals[$signal] ?? false) === true) {
            $score += $weight;
        }
    }

    return max(0, min(100, $score));
}

function estimate_content_score(array $page): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $score = 50;
    $score += min(25, (int) (($page['word_count'] ?? 0) / 40));
    $score += min(15, count($page['headings']['h2'] ?? []) * 3);
    $score += (($page['signals']['has_raw_html_content'] ?? false) ? 8 : -10);
    $score += (($page['signals']['avoids_client_rendered_shell'] ?? true) ? 0 : -15);
    $score += (($page['signals']['has_question_headings'] ?? false) ? 10 : 0);
    $score += (($page['signals']['has_answer_first_blocks'] ?? false) ? 12 : 0);
    $score += (($page['signals']['has_claim_evidence_language'] ?? false) ? 8 : 0);

    return min(100, $score);
}

function estimate_ux_score(array $page): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $signals = $page['signals'] ?? [];
    $ux = $page['ux_signals'] ?? [];
    $score = 25;
    $score += (($signals['has_clear_page_purpose'] ?? false) ? 18 : 0);
    $score += (($signals['has_manageable_navigation'] ?? false) ? 14 : 0);
    $score += (($signals['has_consistent_cta_language'] ?? false) ? 12 : 0);
    $score += (($signals['has_contact_path'] ?? false) ? 12 : 0);
    $score += (($signals['has_user_journey_entry'] ?? false) ? 12 : 0);
    $score += (($signals['has_newsletter_or_lead_capture'] ?? false) ? 6 : 0);

    if (($ux['has_external_nav_links'] ?? false) === true) {
        $score -= 8;
    }
    if (($ux['nav_max_depth'] ?? 0) > 2) {
        $score -= 10;
    }
    if (($ux['nav_link_count'] ?? 0) > 12) {
        $score -= 8;
    }

    return max(0, min(100, $score));
}

function estimate_entity_score(array $page): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $score = 20;
    $signals = $page['signals'] ?? [];
    $score += (($signals['has_organization_schema'] ?? false) ? 20 : 0);
    $score += (($signals['has_stable_schema_ids'] ?? false) ? 20 : 0);
    $score += (($signals['has_sameas_identity'] ?? false) ? 15 : 0);
    $score += (($signals['has_person_or_author_schema'] ?? false) ? 15 : 0);
    $score += (($signals['has_breadcrumb_schema'] ?? false) ? 10 : 0);

    return min(100, $score);
}

function estimate_agentic_score(array $page, array $siteFiles): int
{
    if (!($page['ok'] ?? false)) {
        return 0;
    }

    $score = 45;
    $score += (($page['signals']['has_raw_html_content'] ?? false) ? 12 : 0);
    $score += (($page['signals']['avoids_client_rendered_shell'] ?? true) ? 8 : -20);
    $score += (($page['signals']['has_low_js_dependency'] ?? false) ? 8 : 0);
    $score += (($page['signals']['has_meta_viewport'] ?? false) ? 6 : 0);
    $score += (($page['signals']['has_visual_evidence'] ?? false) ? 10 : 0);
    $score += (($page['images_total'] ?? 0) === 0 || ($page['images_without_alt'] ?? 0) === 0 ? 10 : 0);
    $score += (($page['signals']['has_open_graph'] ?? false) ? 8 : 0);
    $score += (($siteFiles['robots']['exists'] ?? false) ? 8 : 0);
    $score += (($siteFiles['llms']['exists'] ?? false) ? 4 : 0);
    $score += (($page['load_time_ms'] ?? 0) <= 2500 ? 15 : 0);

    return max(0, min(100, $score));
}

function prioritize_recommendations(array $issues): array
{
    $priority = ['critical' => 0, 'warning' => 1, 'info' => 2];
    usort($issues, static function (array $a, array $b) use ($priority): int {
        return ($priority[$a['level']] ?? 3) <=> ($priority[$b['level']] ?? 3)
            ?: ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
    });

    return array_slice(array_map(static function (array $issue): array {
        $pages = array_values(array_unique($issue['pages'] ?? []));
        $strategy = issue_strategy((string) ($issue['text'] ?? ''), (string) ($issue['level'] ?? 'info'));
        return [
            'level' => $issue['level'] ?? 'info',
            'title' => $issue['text'] ?? '',
            'category' => $strategy['category'],
            'why' => $strategy['why'],
            'fix' => $issue['fix'] ?? '',
            'next_step' => $strategy['next_step'],
            'impact' => recommendation_impact($issue['level'] ?? 'info'),
            'count' => $issue['count'] ?? 1,
            'pages' => array_slice($pages, 0, 5),
        ];
    }, $issues), 0, 18);
}

function issue_strategy(string $title, string $level): array
{
    $lower = text_lower($title);

    if (str_contains($lower, 'célja') || str_contains($lower, 'célközönsége')) {
        return [
            'category' => 'Stratégia',
            'why' => 'Ha nincs kimondva, kinek szól az oldal és milyen döntést kell támogatnia, a portfólió, CTA, navigáció és tartalom könnyen szétesik.',
            'next_step' => 'Tarts egy rövid célcsoport-workshopot: elsődleges közönség, üzleti cél, fő döntési út, majd ennek megfelelő H1, bevezető és CTA.',
        ];
    }

    if (str_contains($lower, 'title') || str_contains($lower, 'meta description') || str_contains($lower, 'h1')) {
        return [
            'category' => 'SEO alap',
            'why' => 'A title, meta description és H1 adja az oldal első értelmezési rétegét a felhasználónak, keresőnek és AI rendszernek.',
            'next_step' => 'Írj egyetlen, célközönségre szabott H1-et, mellé rövid meta leírást és olyan title-t, amely megnevezi a fő témát és ígéretet.',
        ];
    }

    if (str_contains($lower, 'navigáció') || str_contains($lower, 'külső oldalak')) {
        return [
            'category' => 'Navigáció',
            'why' => 'Ha a fontos oldalak túl sok menülépés után érhetők el, a látogató könnyen elakad, az AI agentek pedig nehezebben értik meg az oldalhierarchiát.',
            'next_step' => 'Rajzold fel a fő user journey-ket, majd 5-7 fő menüelemre és kattintható kategóriaoldalakra egyszerűsítsd a struktúrát.',
        ];
    }

    if (str_contains($lower, 'cta') || str_contains($lower, 'kapcsolatfelvételi') || str_contains($lower, 'lead capture') || str_contains($lower, 'hírlevél')) {
        return [
            'category' => 'Konverzió',
            'why' => 'A látogató akkor halad tovább, ha pontosan érti, mi történik kattintás után. Az inkonzisztens CTA és eldugott kontaktút csökkenti a bizalmat.',
            'next_step' => 'Egységesítsd a CTA-kat: elsődleges kapcsolat/ajánlatkérés, másodlagos további információ, külön jelölt külső felület és opcionális hírlevél/letöltés.',
        ];
    }

    if (str_contains($lower, 'javascript') || str_contains($lower, 'nyers html') || str_contains($lower, 'betölt') || str_contains($lower, 'spa')) {
        return [
            'category' => 'Technikai AIO',
            'why' => 'A látványos, de lassú vagy JavaScriptre épülő belépőélmény embernek izgalmas lehet, de crawler és AI kereső számára üres vagy későn érkező tartalmat jelenthet.',
            'next_step' => 'Tedd a fő tartalmat az első HTML válaszba, és csak a kiegészítő animációt, dekorációt vagy interakciót hagyd kliensoldali JavaScriptre.',
        ];
    }

    if (str_contains($lower, 'schema') || str_contains($lower, '@id') || str_contains($lower, 'sameas')) {
        return [
            'category' => 'Entitásbizalom',
            'why' => 'A strukturált adatok segítenek összekötni a márkát, szerzőt, oldalt és külső profilokat, így az AI nem elszigetelt szövegrészleteket lát.',
            'next_step' => 'Készíts stabil Organization/WebSite/WebPage/Article schema rendszert, következetes @id mintával és valódi sameAs kapcsolatokkal.',
        ];
    }

    if (str_contains($lower, 'válasz') || str_contains($lower, 'összefoglaló') || str_contains($lower, 'bizonyíték') || str_contains($lower, 'forrás')) {
        return [
            'category' => 'Tartalom',
            'why' => 'Az AI válaszok rövid, önállóan is érthető és ellenőrizhető blokkokból tudnak stabil forrást építeni.',
            'next_step' => 'Minden fontos H2 után adj 2-4 mondatos direkt választ, majd adatot, példát, forrást vagy összehasonlítást.',
        ];
    }

    return [
        'category' => $level === 'critical' ? 'Alapjavítás' : 'Finomhangolás',
        'why' => 'Ez a pont rontja a felhasználói értelmezést, a keresőrobotok feldolgozását vagy az AI rendszerek forrásbizalmát.',
        'next_step' => 'Vedd fel a javítási backlogba, rendelj hozzá felelőst, majd ellenőrizd új audit futtatásával.',
    ];
}

function recommendation_impact(string $level): string
{
    return match ($level) {
        'critical' => 'Magas hatás: alapvető SEO/AIO érthetőségi probléma.',
        'warning' => 'Közepes hatás: javítja az idézhetőséget és a gépi értelmezést.',
        default => 'Finomhangolás: erősíti a bizalmi és entitásjeleket.',
    };
}
