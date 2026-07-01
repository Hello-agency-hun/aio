<?php
/**
 * AI és keresési bizonyíték alapú versenytárssegéd.
 *
 * A modul célja, hogy a felhasználó ne üres textarea előtt találgassa, kiket
 * érdemes benchmarkolni. A versenytárslista csak élő AI értelmezés és
 * weboldal-kontekstus alapján készülhet; ha a provider nem működik, hibát
 * jelezünk, nem adunk sablonos vagy random fallback listát.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/visibility_topic_suggestions.php';
require_once __DIR__ . '/search_providers.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/openrouter.php';

function suggest_visibility_competitors(array $input): array
{
    $siteUrl = normalize_url((string) ($input['site_url'] ?? ''));
    if ($siteUrl === null || !is_public_target_url($siteUrl)) {
        throw new RuntimeException('Adj meg egy publikus domaint vagy URL-t, hogy tudjak versenytársakat javasolni.');
    }

    $businessModel = visibility_normalize_business_model((string) ($input['business_model'] ?? 'generic'));
    $targetDomain = visibility_normalize_domain($siteUrl);
    $profile = [
        'site_url' => $siteUrl,
        'target_domain' => $targetDomain,
        'project_name' => trim((string) ($input['name'] ?? '')),
        'market' => trim((string) ($input['market'] ?? 'Magyarország')) ?: 'Magyarország',
        'language' => trim((string) ($input['language'] ?? 'hu')) ?: 'hu',
        'business_model' => $businessModel,
        'business_model_label' => visibility_business_models()[$businessModel] ?? 'Általános weboldal',
        'topics' => visibility_parse_list((string) ($input['topics'] ?? ''), 8),
        'existing_competitors' => visibility_parse_list((string) ($input['competitors'] ?? ''), 12),
    ];

    $context = visibility_topic_homepage_context($siteUrl);
    visibility_topic_require_context($context);
    $searchEvidence = visibility_competitor_search_evidence($profile);
    $ai = visibility_competitor_ai_suggestions($profile, $context, $searchEvidence);
    $suggestions = visibility_competitor_merge_suggestions($searchEvidence['suggestions'] ?? [], $ai['suggestions'] ?? [], $targetDomain);

    $aiSource = (string) ($ai['source'] ?? '');
    if (!in_array($aiSource, ['openai', 'openrouter'], true) || !$suggestions) {
        throw new RuntimeException($ai['message'] ?: 'Az AI versenytárssegéd nem adott használható listát. Ellenőrizd az OpenRouter kulcsot/modellt, majd próbáld újra.');
    }

    $source = $aiSource;
    if (($searchEvidence['status'] ?? '') === 'completed') {
        $source = 'search_and_ai';
    }

    $message = visibility_competitor_result_message($source, $searchEvidence, $ai);

    return [
        'profile' => $profile,
        'context' => $context,
        'search_evidence' => $searchEvidence,
        'suggestions' => array_slice($suggestions, 0, 12),
        'domains' => array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['domain'] ?? ''), $suggestions))),
        'source' => $source,
        'message' => $message,
    ];
}

function visibility_competitor_search_evidence(array $profile): array
{
    $config = search_probe_config();
    if (($config['enabled'] ?? true) !== true || !function_exists('curl_init')) {
        return [
            'status' => 'skipped',
            'message' => 'A keresési provider nem aktív.',
            'queries' => [],
            'suggestions' => [],
        ];
    }

    $providers = search_probe_available_providers($config);
    if (!$providers) {
        return [
            'status' => 'setup_required',
            'message' => 'Nincs beállított keresési provider a konkrét találati versenytársakhoz.',
            'queries' => [],
            'suggestions' => [],
        ];
    }

    $queries = visibility_competitor_discovery_queries($profile);
    $targetDomain = (string) ($profile['target_domain'] ?? '');
    $domainMap = [];
    $errors = [];

    foreach ($queries as $query) {
        $result = search_probe_query($query, $providers, $config);
        if (($result['status'] ?? '') === 'error') {
            $errors[] = (string) ($result['message'] ?? 'Ismeretlen keresési hiba.');
            continue;
        }

        $items = search_probe_mark_domain_hits($result['results'] ?? [], $targetDomain);
        foreach ($items as $position => $item) {
            $host = search_probe_normalize_host((string) ($item['host'] ?? ''));
            if (!visibility_competitor_host_allowed($host, $targetDomain)) {
                continue;
            }

            if (!isset($domainMap[$host])) {
                $domainMap[$host] = [
                    'domain' => $host,
                    'name' => visibility_competitor_name_from_host($host),
                    'why' => 'Megjelent a témákhoz kapcsolódó keresési találatok között.',
                    'evidence' => [],
                    'confidence' => 'medium',
                    'source' => 'search',
                    'score' => 0,
                ];
            }

            $domainMap[$host]['score'] += max(1, 6 - (int) $position);
            $domainMap[$host]['evidence'][] = [
                'query' => $query,
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'snippet' => text_excerpt((string) ($item['snippet'] ?? ''), 180),
                'provider' => (string) ($result['provider'] ?? ''),
            ];
        }
    }

    uasort($domainMap, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    $suggestions = array_values(array_map(static function (array $item): array {
        $item['evidence'] = array_slice($item['evidence'] ?? [], 0, 3);
        if (($item['score'] ?? 0) >= 8) {
            $item['confidence'] = 'high';
        }
        unset($item['score']);
        return $item;
    }, array_slice($domainMap, 0, 12)));

    return [
        'status' => $suggestions ? 'completed' : 'empty',
        'message' => $suggestions ? 'Keresési találatokból azonosított domainjelöltek.' : 'A keresési provider nem adott használható versenytársjelöltet.',
        'providers' => $providers,
        'queries' => $queries,
        'suggestions' => $suggestions,
        'errors' => array_values(array_unique($errors)),
    ];
}

function visibility_competitor_discovery_queries(array $profile): array
{
    $market = (string) ($profile['market'] ?? 'Magyarország');
    $topics = array_slice($profile['topics'] ?? [], 0, 4);
    $label = text_lower((string) ($profile['business_model_label'] ?? 'szolgáltató'));
    $queries = [];

    foreach ($topics as $topic) {
        $topic = trim((string) $topic);
        if ($topic === '') {
            continue;
        }

        $queries[] = 'legjobb ' . $topic . ' ' . $market;
        $queries[] = $topic . ' ' . $label . ' ' . $market;
    }

    if (!$queries) {
        $domain = (string) ($profile['target_domain'] ?? '');
        $queries[] = $domain . ' alternatívák';
        $queries[] = $label . ' szolgáltató ' . $market;
    }

    return array_slice(array_values(array_unique($queries)), 0, 4);
}

function visibility_competitor_ai_suggestions(array $profile, array $context, array $searchEvidence): array
{
    $openAiConfig = openai_config();
    if (!empty($openAiConfig['api_key']) && function_exists('curl_init')) {
        return visibility_competitor_openai_suggestions($profile, $context, $searchEvidence, $openAiConfig);
    }

    $config = openrouter_config();
    if (($config['enabled'] ?? true) !== true || empty($config['api_key']) || !function_exists('curl_init')) {
        throw new RuntimeException('Az AI versenytárssegédhez OpenAI vagy OpenRouter API kulcs és cURL szükséges. Most nincs működő AI provider, ezért nem készítek találgató versenytárslistát.');
    }

    return visibility_competitor_openrouter_suggestions($profile, $context, $searchEvidence, $config);
}

function visibility_competitor_openai_suggestions(array $profile, array $context, array $searchEvidence, array $config): array
{
    $model = (string) ($config['model'] ?: 'gpt-5.5');
    $payload = [
        'model' => $model,
        'reasoning' => ['effort' => 'low'],
        'max_output_tokens' => 2200,
        'instructions' => visibility_competitor_system_prompt(),
        'input' => ai_json_encode([
            'profile' => $profile,
            'homepage_context' => $context,
            'search_evidence' => visibility_competitor_compact_search_evidence($searchEvidence),
        ]),
    ];

    $timeout = max(45, min(180, (int) ($config['timeout'] ?? OPENAI_REQUEST_TIMEOUT)));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? OPENAI_CONNECT_TIMEOUT));
    $apiResult = openai_responses_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        throw new RuntimeException('OpenAI versenytárssegéd hiba: ' . $apiResult['message']);
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? extract_openai_text($decoded) : '';
    $json = extract_json_object($text);
    $suggestions = is_array($json['suggestions'] ?? null)
        ? visibility_competitor_normalize_suggestions($json['suggestions'], (string) ($profile['target_domain'] ?? ''))
        : [];

    return [
        'source' => 'openai',
        'message' => $suggestions ? 'AI versenytársjavaslat elkészült OpenAI alapon.' : 'Az OpenAI válasz nem tartalmazott használható versenytárslistát.',
        'suggestions' => $suggestions,
    ];
}

function visibility_competitor_openrouter_suggestions(array $profile, array $context, array $searchEvidence, array $config): array
{
    $payload = [
        'model' => (string) ($config['model'] ?: 'openrouter/free'),
        'messages' => [
            [
                'role' => 'system',
                'content' => visibility_competitor_system_prompt(),
            ],
            [
                'role' => 'user',
                'content' => ai_json_encode([
                    'profile' => $profile,
                    'homepage_context' => $context,
                    'search_evidence' => visibility_competitor_compact_search_evidence($searchEvidence),
                ]),
            ],
        ],
        'temperature' => 0.2,
        'max_tokens' => 1800,
        'stream' => false,
    ];

    $timeout = max(45, min(120, (int) ($config['timeout'] ?? 90)));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $apiResult = openrouter_chat_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        throw new RuntimeException('OpenRouter versenytárssegéd hiba: ' . $apiResult['message']);
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? openrouter_extract_text($decoded) : '';
    $json = openrouter_extract_json_object($text);
    $suggestions = is_array($json['suggestions'] ?? null)
        ? visibility_competitor_normalize_suggestions($json['suggestions'], (string) ($profile['target_domain'] ?? ''))
        : [];

    return [
        'source' => 'openrouter',
        'message' => $suggestions ? 'AI versenytársjavaslat elkészült.' : 'Az AI válasz nem tartalmazott használható versenytárslistát.',
        'suggestions' => $suggestions,
    ];
}

function visibility_competitor_system_prompt(): string
{
    return implode("\n", [
        'Magyar nyelvű AI visibility versenytárskutató vagy.',
        'Javasolj benchmarkolható versenytárs domaineket a megadott weboldalhoz.',
        'A homepage_context a céloldal publikus szövegéből készült; ezt használd elsődleges üzleti kontextusként.',
        'Csak olyan domaineket adj, amelyek valószínű piaci vagy tartalmi versenytársak. Ne adj közösségi platformot, magazint vagy katalógust, ha nem direkt versenytárs.',
        'Ha keresési evidencia is érkezik, azt használd erősítő jelként, de a weboldal profiljához illeszd.',
        'A javaslat benchmark hipotézis: ne állítsd biztos tényként, ha nincs evidencia.',
        'Csak JSON objektummal válaszolj. Markdown tilos.',
        'JSON forma: {"suggestions":[{"domain":"","name":"","why":"","confidence":"high|medium|low","evidence":["",""]}]}',
        'Adj legfeljebb 10 versenytársat.',
        'Ha a céloldal kommunikációs, kreatív vagy rendezvényes ügynökség, ilyen típusú hazai piaci szereplőket keress, ne webshopokat.',
    ]);
}

function visibility_competitor_compact_search_evidence(array $searchEvidence): array
{
    return [
        'status' => $searchEvidence['status'] ?? '',
        'queries' => $searchEvidence['queries'] ?? [],
        'domains' => array_map(static fn (array $item): array => [
            'domain' => $item['domain'] ?? '',
            'evidence' => $item['evidence'] ?? [],
        ], array_slice($searchEvidence['suggestions'] ?? [], 0, 8)),
    ];
}

function visibility_competitor_normalize_suggestions(array $items, string $targetDomain): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $domain = visibility_normalize_domain((string) ($item['domain'] ?? ''));
        if (!visibility_competitor_host_allowed($domain, $targetDomain)) {
            continue;
        }

        $evidence = $item['evidence'] ?? [];
        if (!is_array($evidence)) {
            $evidence = [$evidence];
        }

        $normalized[] = [
            'domain' => $domain,
            'name' => trim((string) ($item['name'] ?? visibility_competitor_name_from_host($domain))),
            'why' => trim((string) ($item['why'] ?? 'AI által javasolt benchmark jelölt.')),
            'evidence' => array_slice(array_values(array_filter(array_map('strval', $evidence))), 0, 3),
            'confidence' => in_array(($item['confidence'] ?? ''), ['high', 'medium', 'low'], true) ? (string) $item['confidence'] : 'low',
            'source' => 'ai',
        ];
    }

    return $normalized;
}

function visibility_competitor_merge_suggestions(array $searchSuggestions, array $aiSuggestions, string $targetDomain): array
{
    $merged = [];
    foreach (array_merge($searchSuggestions, $aiSuggestions) as $item) {
        $domain = visibility_normalize_domain((string) ($item['domain'] ?? ''));
        if (!visibility_competitor_host_allowed($domain, $targetDomain)) {
            continue;
        }

        if (!isset($merged[$domain])) {
            $item['domain'] = $domain;
            $merged[$domain] = $item;
            continue;
        }

        $merged[$domain]['source'] = ($merged[$domain]['source'] ?? '') === 'search' || ($item['source'] ?? '') === 'search'
            ? 'search_ai'
            : ($merged[$domain]['source'] ?? 'ai');
        $merged[$domain]['why'] = trim((string) ($merged[$domain]['why'] ?? '')) ?: (string) ($item['why'] ?? '');
        $merged[$domain]['evidence'] = array_slice(array_values(array_filter(array_merge(
            $merged[$domain]['evidence'] ?? [],
            $item['evidence'] ?? []
        ))), 0, 4);
        if (($item['confidence'] ?? '') === 'high' || ($merged[$domain]['confidence'] ?? '') === 'high') {
            $merged[$domain]['confidence'] = 'high';
        }
    }

    return array_values($merged);
}

function visibility_competitor_host_allowed(string $host, string $targetDomain): bool
{
    $blocked = [
        'google.com', 'youtube.com', 'facebook.com', 'linkedin.com', 'instagram.com', 'tiktok.com',
        'x.com', 'twitter.com', 'wikipedia.org', 'reddit.com', 'pinterest.com',
    ];

    return $host !== ''
        && $host !== $targetDomain
        && !str_ends_with($host, '.' . $targetDomain)
        && !in_array($host, $blocked, true);
}

function visibility_competitor_name_from_host(string $host): string
{
    $first = explode('.', $host)[0] ?? $host;
    $first = str_replace(['-', '_'], ' ', $first);
    return trim(ucwords($first));
}

function visibility_competitor_result_message(string $source, array $searchEvidence, array $ai): string
{
    return match ($source) {
        'search_and_ai' => 'Keresési találatokból és AI értelmezésből állítottam össze a versenytársjelölteket.',
        'openai' => 'A weboldal-kontekstusból és OpenAI értelmezésből készült benchmark-javaslat. Érdemes kézzel ellenőrizni.',
        'openrouter' => 'A weboldal-kontekstusból és AI értelmezésből készült benchmark-javaslat. Érdemes kézzel ellenőrizni.',
        default => $ai['message'] ?? 'Az AI versenytárssegéd nem adott használható listát.',
    };
}
