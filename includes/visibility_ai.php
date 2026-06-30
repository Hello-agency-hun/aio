<?php
/**
 * AI stratégiai értelmező réteg a láthatóságméréshez.
 *
 * A keresési provider-adatok számszerű eredményt adnak. Ez a modul ezekből
 * készít ügyfélbarát, végrehajtható magyarázatot: miért fontos az eredmény,
 * hol erős a domain, hol látszanak versenytársak, és milyen tartalmi lépésekkel
 * lehet javítani a következő mérésig.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/openrouter.php';
require_once __DIR__ . '/gemini.php';

function enrich_visibility_run_with_ai(array $project, array $run, ?callable $progress = null): array
{
    if ($progress) {
        $progress(82, 'AI stratégiai értelmezés', 'A mérési adatokból közérthető javítási terv készül.');
    }

    $openAiResult = visibility_ai_openai($project, $run);
    if (($openAiResult['status'] ?? '') === 'completed') {
        return $openAiResult;
    }

    if ($progress) {
        $progress(86, 'Gemini kontroll', 'Google/Gemini szemléletű stratégiai értelmezés készül.');
    }

    $geminiResult = gemini_visibility_strategy($project, $run);
    if (($geminiResult['status'] ?? '') === 'completed') {
        $geminiResult['fallback_note'] = ($openAiResult['message'] ?? '') !== ''
            ? 'OpenAI nem adott kész választ, ezért Gemini fallback készült: ' . $openAiResult['message']
            : 'Gemini fallback készült.';
        return $geminiResult;
    }

    if ($progress) {
        $progress(88, 'OpenRouter kontroll', 'OpenAI helyett vagy mellett OpenRouter stratégiai értelmezés készül.');
    }

    $openRouterResult = visibility_ai_openrouter($project, $run);
    if (($openRouterResult['status'] ?? '') === 'completed') {
        $openRouterResult['fallback_note'] = ($openAiResult['message'] ?? '') !== ''
            ? 'OpenAI nem adott kész választ, ezért OpenRouter fallback készült: ' . $openAiResult['message']
            : 'OpenRouter fallback készült.';
        return $openRouterResult;
    }

    return [
        'enabled' => false,
        'status' => 'skipped',
        'provider' => 'none',
        'generated_at' => date(DATE_ATOM),
        'message' => trim(($openAiResult['message'] ?? '') . ' ' . ($geminiResult['message'] ?? '') . ' ' . ($openRouterResult['message'] ?? '')) ?: 'Nincs elérhető AI kulcs a stratégiai értelmezéshez.',
    ];
}

function visibility_ai_openai(array $project, array $run): array
{
    $config = openai_config();
    if (empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'provider' => 'openai',
            'message' => 'Nincs beállított OpenAI API kulcs.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'openai',
            'message' => 'A cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'gpt-5.5');
    $timeout = max(90, (int) ($config['timeout'] ?? OPENAI_REQUEST_TIMEOUT));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? OPENAI_CONNECT_TIMEOUT));
    $maxOutputTokens = max(3200, min(6500, (int) ($config['max_output_tokens'] ?? 5200)));

    $payload = [
        'model' => $model,
        'reasoning' => ['effort' => 'medium'],
        'max_output_tokens' => $maxOutputTokens,
        'instructions' => visibility_ai_system_prompt(),
        'input' => visibility_ai_user_prompt($project, $run),
    ];

    $apiResult = openai_responses_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'openai',
            'model' => $model,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? extract_openai_text($decoded) : '';

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'provider' => 'openai',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? extract_openai_usage($decoded) : null,
        'message' => $text !== '' ? '' : 'Az OpenAI válasz üres volt.',
    ];
}

function visibility_ai_openrouter(array $project, array $run): array
{
    $config = openrouter_config();
    if (($config['enabled'] ?? true) !== true || empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'provider' => 'openrouter',
            'message' => 'Nincs bekapcsolt OpenRouter API kulcs.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'openrouter',
            'message' => 'A cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'openrouter/free');
    $timeout = max(90, (int) ($config['timeout'] ?? 120));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $maxTokens = max(3800, min(6500, (int) ($config['max_tokens'] ?? 5200)));

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => visibility_ai_system_prompt()],
            ['role' => 'user', 'content' => visibility_ai_user_prompt($project, $run)],
        ],
        'temperature' => 0.2,
        'max_tokens' => $maxTokens,
        'stream' => false,
    ];

    $apiResult = openrouter_chat_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'openrouter',
            'model' => $model,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? openrouter_extract_text($decoded) : '';

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'provider' => 'openrouter',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? ($decoded['usage'] ?? null) : null,
        'message' => $text !== '' ? '' : 'Az OpenRouter válasz üres volt.',
    ];
}

function visibility_ai_system_prompt(): string
{
    return implode("\n", [
        'Te magyar nyelvű AI keresési láthatósági stratégiai elemző vagy.',
        'A kapott adatok keresési provider találatokból származnak, nem garantált ChatGPT/Gemini/Perplexity válaszpanelből.',
        'Ezért minden mérési következtetést irányadó jelként fogalmazz, ne abszolút igazságként.',
        'A válasz legyen ügyfélbarát, közérthető, konkrét és kivitelezhető.',
        'Ne használj túl tudományos zsargont. Ha kell szakkifejezés, rögtön magyarázd el egyszerűen.',
        'Markdownban válaszolj, pontosan ezekkel a főcímekkel:',
        '## Vezetői összefoglaló',
        '## Mit jelent a mérés?',
        '## Hol veszítünk láthatóságot?',
        '## Versenytárs minták',
        '## Tartalmi javítási terv',
        '## Következő mérési kör',
        'Minden részben adj konkrét teendőket. A Tartalmi javítási tervben legyen legalább 5 javasolt oldal vagy tartalmi blokk.',
        'Minden fontos állításnál különítsd el: **Biztos jel:**, **Irányadó jel:**, **Teendő:**.',
    ]);
}

function visibility_ai_user_prompt(array $project, array $run): string
{
    $compactRun = [
        'project' => [
            'name' => $project['name'] ?? '',
            'site_url' => $project['site_url'] ?? '',
            'target_domain' => $project['target_domain'] ?? '',
            'market' => $project['market'] ?? '',
            'language' => $project['language'] ?? '',
            'business_model' => $project['business_model'] ?? 'generic',
            'business_model_label' => $project['business_model_label'] ?? '',
            'topics' => $project['topics'] ?? [],
            'custom_queries' => $project['custom_queries'] ?? [],
            'configured_competitors' => $project['competitors'] ?? [],
        ],
        'measurement' => [
            'created_at' => $run['created_at'] ?? '',
            'method' => $run['method'] ?? '',
            'providers' => $run['providers'] ?? [],
            'query_count' => $run['query_count'] ?? 0,
            'owned_query_hits' => $run['owned_query_hits'] ?? 0,
            'visibility_rate' => $run['visibility_rate'] ?? 0,
            'average_owned_position' => $run['average_owned_position'] ?? null,
            'confidence' => $run['confidence'] ?? [],
            'interpretation' => $run['interpretation'] ?? '',
            'errors' => $run['errors'] ?? [],
        ],
        'share_of_voice' => array_slice($run['share_of_voice'] ?? [], 0, 12),
        'competitors' => array_slice($run['competitors'] ?? [], 0, 12),
        'query_results' => array_map(static function (array $item): array {
            return [
                'type' => $item['type'] ?? '',
                'query' => $item['query'] ?? '',
                'owned_domain_hit' => $item['owned_domain_hit'] ?? false,
                'own_results' => array_map(static function (array $result): array {
                    return [
                        'position' => $result['position'] ?? null,
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                    ];
                }, array_slice($item['own_results'] ?? [], 0, 3)),
                'competitors' => array_slice($item['competitors'] ?? [], 0, 8),
                'top_results' => array_map(static function (array $result): array {
                    return [
                        'position' => $result['position'] ?? null,
                        'title' => $result['title'] ?? '',
                        'host' => $result['host'] ?? '',
                        'url' => $result['url'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                    ];
                }, array_slice($item['results'] ?? [], 0, 5)),
                'error' => $item['error'] ?? '',
            ];
        }, array_slice($run['query_results'] ?? [], 0, 12)),
    ];

    return "AI láthatóságmérési JSON kivonat:\n" . json_encode($compactRun, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
