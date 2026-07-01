<?php
/**
 * Opcionális OpenAI másodelemző réteg.
 *
 * Biztonsági okból API-kulcsot nem tárolunk a publikus kódbázisban. A kulcsot
 * vagy környezeti változóként (`OPENAI_API_KEY`), vagy a webtől .htaccess-szel
 * védett `data/openai_config.php` fájlban lehet megadni:
 *
 * return [
 *     'api_key' => 'sk-...',
 *     'model' => 'gpt-5.5',
 *     'timeout' => 180,
 *     'max_output_tokens' => 6500,
 *     'enable_visibility_probe' => true,
 * ];
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/methodology.php';
require_once __DIR__ . '/ai_report_compactor.php';

function openai_config(): array
{
    $config = [
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-5.5',
        'timeout' => (int) (getenv('OPENAI_TIMEOUT') ?: OPENAI_REQUEST_TIMEOUT),
        'connect_timeout' => (int) (getenv('OPENAI_CONNECT_TIMEOUT') ?: OPENAI_CONNECT_TIMEOUT),
        'max_output_tokens' => (int) (getenv('OPENAI_MAX_OUTPUT_TOKENS') ?: 6500),
        'enable_visibility_probe' => getenv('OPENAI_ENABLE_VISIBILITY_PROBE') !== '0',
        'ca_bundle' => getenv('OPENAI_CA_BUNDLE') ?: DATA_DIR . '/cacert.pem',
    ];

    $protectedConfig = DATA_DIR . '/openai_config.php';
    if (is_file($protectedConfig)) {
        $fileConfig = require $protectedConfig;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
        }
    }

    return $config;
}

function enrich_report_with_openai(array $report, ?callable $progress = null): array
{
    $config = openai_config();
    if (empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'OpenAI elemzés kihagyva: nincs beállított OPENAI_API_KEY.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'message' => 'Az OpenAI híváshoz szükséges cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'gpt-5.5');
    $isQuickMode = (($report['crawl_mode'] ?? '') === 'quick');
    $timeout = max(45, (int) ($config['timeout'] ?? OPENAI_REQUEST_TIMEOUT));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? OPENAI_CONNECT_TIMEOUT));
    $maxOutputTokens = max(2200, (int) ($config['max_output_tokens'] ?? 6500));
    if ($isQuickMode) {
        $maxOutputTokens = min($maxOutputTokens, 2200);
    } elseif (ai_report_is_large($report)) {
        $maxOutputTokens = min($maxOutputTokens, 4800);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout + 60);
    }

    $payload = [
        'model' => $model,
        'reasoning' => ['effort' => ($isQuickMode || ai_report_is_large($report)) ? 'medium' : 'high'],
        'max_output_tokens' => $maxOutputTokens,
        'instructions' => openai_aio_system_prompt(),
        'input' => openai_aio_user_prompt($report),
    ];

    if ($progress) {
        $progress(69, 'OpenAI elemzés', 'Részletes stratégiai javítási terv generálása.');
    }
    $apiResult = openai_responses_request($payload, $config, $timeout, $connectTimeout);

    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'model' => $model,
            'timeout_seconds' => $timeout,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? extract_openai_text($decoded) : '';
    $visibilityProbe = (($config['enable_visibility_probe'] ?? true) === true)
        ? run_openai_visibility_probe($report, $config, $model, $timeout, $connectTimeout, $progress)
        : [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'Az OpenAI web_search alapú AI keresési próba ki van kapcsolva.',
        ];

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? extract_openai_usage($decoded) : null,
        'visibility_probe' => $visibilityProbe,
    ];
}

function openai_responses_request(array $payload, array $config, int $timeout, int $connectTimeout): array
{
    $ch = curl_init('https://api.openai.com/v1/responses');
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => ai_json_encode($payload),
    ];

    $caBundle = (string) ($config['ca_bundle'] ?? '');
    if ($caBundle !== '' && is_file($caBundle)) {
        $curlOptions[CURLOPT_CAINFO] = $caBundle;
    }

    curl_setopt_array($ch, $curlOptions);

    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'status' => $status,
            'raw' => $raw,
            'message' => ai_provider_error_message('OpenAI', $status, $raw, $error, $timeout),
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'raw' => $raw,
        'message' => '',
    ];
}

function run_openai_visibility_probe(array $report, array $config, string $model, int $timeout, int $connectTimeout, ?callable $progress = null): array
{
    $plan = $report['ai_search_plan'] ?? [];
    $queryLimit = (($report['crawl_mode'] ?? '') === 'quick') ? 4 : 6;
    $queries = array_slice($plan['query_set'] ?? [], 0, $queryLimit);
    if (!$queries) {
        return [
            'enabled' => true,
            'status' => 'skipped',
            'message' => 'Nincs generált AI keresési kérdéssor.',
        ];
    }

    $host = (string) ($plan['domain'] ?? parse_url((string) ($report['url'] ?? ''), PHP_URL_HOST));
    $brand = (string) ($plan['brand_name'] ?? $host);

    $payload = [
        'model' => $model,
        'tools' => [
            [
                'type' => 'web_search',
                'search_context_size' => 'medium',
            ],
        ],
        'tool_choice' => 'required',
        'reasoning' => ['effort' => 'medium'],
        'max_output_tokens' => (($report['crawl_mode'] ?? '') === 'quick') ? 5200 : 6500,
        'instructions' => implode("\n", [
            'Te AI keresési láthatósági mérő vagy.',
            'A web_search eszközt használd a megadott kérdésekhez.',
            'Nem az audit JSON alapján kell eldöntened a láthatóságot, hanem azt kell mérned, hogy a webes/AI keresési válaszban megjelenik-e a megadott brand vagy domain.',
            'Csak JSON objektummal válaszolj. Ne írj Markdown-t.',
            'A JSON mezők: summary, coverage_rate, citation_rate, owned_domain_mentions, competitors, narrative_risks, query_results.',
            'Minden kérdésnél nézd meg: említi-e a target brandet, idézi-e a target domaint, milyen versenytársakat említ, és milyen URL-eket idéz.',
            'query_results elemek mezői: id, query, brand_mentioned, domain_cited, position_hint, cited_urls, competitors, answer_summary, risk.',
            'Ha nincs elég adat, jelöld bizonytalannak a risk mezőben, ne találj ki forrást.',
        ]),
        'input' => ai_json_encode([
            'target_domain' => $host,
            'target_brand' => $brand,
            'queries' => $queries,
        ]),
    ];

    if ($progress) {
        $progress(79, 'Live AI keresési próba', sprintf('%d kérdés futtatása OpenAI web_search alapon. Gyors módban rövidített próba fut.', count($queries)));
    }
    $apiResult = openai_responses_request($payload, $config, min(max(90, $timeout), 240), $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'model' => $model,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? extract_openai_text($decoded) : '';
    $json = extract_json_object($text);

    return [
        'enabled' => true,
        'status' => is_array($json) ? 'completed' : 'raw',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'method' => 'OpenAI Responses API web_search',
        'result' => is_array($json) ? $json : null,
        'raw' => is_array($json) ? '' : $text,
        'usage' => is_array($decoded) ? extract_openai_usage($decoded) : null,
        'limitation' => 'Ez OpenAI web_search alapú próba, nem teljes ChatGPT/Gemini/Perplexity/Google AI Mode panel. A platformok válaszai eltérhetnek, ezért a monitoringot platformonként kell ismételni.',
    ];
}

function openai_aio_system_prompt(): string
{
    return implode("\n", [
        'Te magyar nyelvű senior AIO/GEO audit szakértő vagy.',
        'A válaszod legyen ügyfélbarát, egyszerű, közérthető, részletes és kivitelezhető. Kerüld a túl tudományos zsargont.',
        'Adj bő, rendszerezett elemzést. Ne spórolj a részletekkel: konkrét teendőket, példákat, javasolt szövegmintákat és sorrendet írj.',
        'Ne írj túl hosszú bekezdéseket: egy bekezdés legfeljebb 3 mondat legyen. A hosszabb részeket bontsd listákra.',
        'Ne ígérj garantált AI Overview vagy ChatGPT citációt. Mindig gyakorlati javítási tervet adj.',
        'A Markdown legyen tiszta és jól tagolt: ## főcímek, ### alcímek, rövid bekezdések, kötőjeles listák, **kiemelt mezőcímkék**. Ne használj táblázatot.',
        '2026. május 7-től a Google FAQ rich results nem jelennek meg Searchben, ezért a FAQPage schema-t ne kezeld láthatósági gyorsnyerőként. B2B vagy szolgáltatói oldalnál ne sablonos webshop/termék GYIK-et javasolj, hanem kontextushoz illő döntéstámogató válaszblokkokat. FAQPage schema csak valódi, látható kérdés-válasz tartalomhoz jöjjön szóba.',
        'Kiemelten értékeld, hogy a fő tartalom látszik-e a nyers, JavaScript futtatása előtti HTML-ben. Ha SPA vagy túl JavaScript-függő oldal jelei látszanak, adj SSR/SSG/statikus HTML javítási tervet.',
        'A javaslatokat prezentáció-kompatibilis logikával add: mi a probléma, miért probléma üzletileg/felhasználóilag, és milyen első lépésekkel induljon a javítás.',
        'A noindex oldalakat kezeld differenciáltan: navigációs, kategória/tag archívum, keresési találati, paginációs, kosár/fiók vagy technikai oldalon a noindex sokszor szándékos, ezért nem kell automatikusan meta title/description/schema javítást priorizálni. Gyanús hibának akkor jelöld, ha fontos szolgáltatás-, termék-, cikk-, landing vagy fő tartalmi oldal kap noindexet.',
        'A leiratból átvett szakmai szemlélet szerint nulladik lépés a célközönség, célcsoport, piaci logika és user journey tisztázása; csak ezután jöhet wireframe, copywriting, vizuál, backend, tesztelés és élesítés.',
        'A riportban szereplő AI Search Visibility Lab kérdéssort kezeld külön: az nem klasszikus SEO checklist, hanem vevői kérdésekből álló AI-láthatósági mérési terv. Mutasd meg, mely kérdésekre kellene ténylegesen futtatni OpenAI web_search, ChatGPT Search, Gemini, Perplexity vagy Google AI Mode próbákat.',
        'Ha saved_search_probe adat érkezik, azt kezeld keresési bizonyítékként: mely kérdésekre található meg a saját domain, milyen források dominálnak, milyen versenytárs domainek látszanak, és ezek alapján milyen tartalmi/citációs hiányt kell pótolni.',
        'Ha visibility_probe eredmény is érkezik, elemezd külön: coverage rate, citation rate, versenytársak, narrative risk, citation absorption.',
        'Pontosan ebben a szerkezetben válaszolj:',
        '## 1. Vezetői összefoglaló',
        '## 2. Prioritási roadmap',
        '## 3. Kritikus javítások részletesen',
        '## 4. Oldalszintű teendők',
        '## 5. Schema és entitásbizalom',
        '## 6. AI keresési láthatósági próba',
        '## 7. Tartalmi és citációs minták',
        '## 8. 30 napos megvalósítási terv',
        'Minden nagyobb teendőnél használd ezeket a félkövér címkéket: **Mi a gond:**, **Mit kell csinálni:**, **Miért fontos:**, **Példa:**, **Prioritás:**.',
        'A tartalmi minták résznél adj legalább 3 konkrét answer-first szövegmintát magyarul.',
    ]);
}

function openai_aio_user_prompt(array $report): string
{
    $compact = ai_report_compact($report, 'primary');

    return "Audit riport JSON kivonat:\n" . ai_json_encode($compact);
}

function openai_compact_saved_search_probe(array $probe): array
{
    return ai_report_compact_saved_search_probe($probe, 6);
}

function extract_json_object(string $text): ?array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('~```(?:json)?\s*(\{.*\})\s*```~su', $trimmed, $match)) {
        $decoded = json_decode($match[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $start = strpos($trimmed, '{');
    $end = strrpos($trimmed, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidate = substr($trimmed, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function extract_openai_text(array $decoded): string
{
    if (isset($decoded['output_text']) && is_string($decoded['output_text'])) {
        return trim($decoded['output_text']);
    }

    $parts = [];
    foreach (($decoded['output'] ?? []) as $output) {
        foreach (($output['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                $parts[] = (string) $content['text'];
            }
        }
    }

    return trim(implode("\n\n", $parts));
}

function extract_openai_usage(array $decoded): ?array
{
    $usage = $decoded['usage'] ?? null;
    if (!is_array($usage)) {
        return null;
    }

    $inputTokens = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
    $outputTokens = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
    $totalTokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));
    $reasoningTokens = 0;

    if (isset($usage['output_tokens_details']) && is_array($usage['output_tokens_details'])) {
        $reasoningTokens = (int) ($usage['output_tokens_details']['reasoning_tokens'] ?? 0);
    }

    return [
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'total_tokens' => $totalTokens,
        'reasoning_tokens' => $reasoningTokens,
        'raw' => $usage,
    ];
}
