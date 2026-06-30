<?php
/**
 * Opcionális OpenRouter másodelemző réteg.
 *
 * Az OpenRouter OpenAI-kompatibilis Chat Completions API-t ad sok modellhez.
 * A projektben ezt olcsó/ingyenes második véleményként használjuk: az audit
 * JSON-t, a javítási javaslatokat és az AI Search Visibility Lab kérdéssorát
 * értékeli. Ez nem helyettesíti az OpenAI web_search alapú live keresési
 * próbát. Külön, opcionális online probe is elérhető OpenRouter web_search
 * server toollal, de az OpenRouter dokumentáció szerint a web search külön
 * költséget okozhat még free modell mellett is.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/methodology.php';

function openrouter_config(): array
{
    $config = [
        'enabled' => getenv('OPENROUTER_ENABLED') !== '0',
        'api_key' => getenv('OPENROUTER_API_KEY') ?: '',
        'model' => getenv('OPENROUTER_MODEL') ?: 'openrouter/free',
        'timeout' => (int) (getenv('OPENROUTER_TIMEOUT') ?: 120),
        'connect_timeout' => (int) (getenv('OPENROUTER_CONNECT_TIMEOUT') ?: 20),
        'max_tokens' => (int) (getenv('OPENROUTER_MAX_TOKENS') ?: 3500),
        'ca_bundle' => getenv('OPENROUTER_CA_BUNDLE') ?: DATA_DIR . '/cacert.pem',
        'enable_online_probe' => getenv('OPENROUTER_ENABLE_ONLINE_PROBE') === '1',
        'online_model' => getenv('OPENROUTER_ONLINE_MODEL') ?: 'openrouter/free',
        'online_max_results' => (int) (getenv('OPENROUTER_ONLINE_MAX_RESULTS') ?: 3),
        'online_max_tokens' => (int) (getenv('OPENROUTER_ONLINE_MAX_TOKENS') ?: 4200),
    ];

    $protectedConfig = DATA_DIR . '/openrouter_config.php';
    if (is_file($protectedConfig)) {
        $fileConfig = require $protectedConfig;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
        }
    }

    return $config;
}

function enrich_report_with_openrouter(array $report): array
{
    $config = openrouter_config();
    if (($config['enabled'] ?? true) !== true) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'OpenRouter elemzés ki van kapcsolva.',
        ];
    }

    if (empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'OpenRouter elemzés kihagyva: nincs beállított OPENROUTER_API_KEY.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'message' => 'Az OpenRouter híváshoz szükséges cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'openrouter/free');
    $timeout = max(30, (int) ($config['timeout'] ?? 120));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $maxTokens = max(3500, (int) ($config['max_tokens'] ?? 3500));

    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout + 30);
    }

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => openrouter_aio_system_prompt(),
            ],
            [
                'role' => 'user',
                'content' => openrouter_aio_user_prompt($report),
            ],
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
            'model' => $model,
            'timeout_seconds' => $timeout,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? openrouter_extract_text($decoded) : '';
    $onlineProbe = (($config['enable_online_probe'] ?? false) === true)
        ? run_openrouter_online_probe($report, $config, $connectTimeout)
        : [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'OpenRouter online keresési próba nincs bekapcsolva. A web search extra költséget okozhat még free modell mellett is.',
        ];

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? ($decoded['usage'] ?? null) : null,
        'online_probe' => $onlineProbe,
        'message' => $text !== '' ? '' : 'Az OpenRouter válasz üres volt.',
    ];
}

function run_openrouter_online_probe(array $report, array $config, int $connectTimeout): array
{
    $plan = $report['ai_search_plan'] ?? [];
    $queries = array_slice($plan['query_set'] ?? [], 0, (($report['crawl_mode'] ?? '') === 'quick') ? 4 : 6);
    if (!$queries) {
        return [
            'enabled' => true,
            'status' => 'skipped',
            'message' => 'Nincs generált AI keresési kérdéssor.',
        ];
    }

    $model = (string) ($config['online_model'] ?? 'openrouter/free');
    $timeout = max(45, (int) ($config['timeout'] ?? 120));
    $maxResults = max(1, min(10, (int) ($config['online_max_results'] ?? 3)));
    $maxTokens = max(1800, (int) ($config['online_max_tokens'] ?? 4200));
    $host = (string) ($plan['domain'] ?? parse_url((string) ($report['url'] ?? ''), PHP_URL_HOST));
    $brand = (string) ($plan['brand_name'] ?? $host);

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Te AI keresési láthatósági mérő vagy.',
                    'Használd az OpenRouter web_search toolt.',
                    'A cél: többféle vevői kérdés alapján mérni, megjelenik-e a target brand/domain, milyen versenytársakat említ a válasz, és milyen forrásokat idéz.',
                    'Csak JSON objektummal válaszolj. Ne írj Markdown-t.',
                    'Mezők: summary, coverage_rate, citation_rate, competitors, narrative_risks, query_results.',
                    'query_results mezők: id, query, brand_mentioned, domain_cited, cited_urls, competitors, answer_summary, risk.',
                    'Ha bizonytalan vagy, jelöld a risk mezőben. Ne találj ki URL-t.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'target_domain' => $host,
                    'target_brand' => $brand,
                    'queries' => $queries,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ],
        ],
        'tools' => [
            [
                'type' => 'openrouter:web_search',
                'parameters' => [
                    'max_results' => $maxResults,
                ],
            ],
        ],
        'temperature' => 0.1,
        'max_tokens' => $maxTokens,
        'stream' => false,
    ];

    $apiResult = openrouter_chat_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'model' => $model,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? openrouter_extract_text($decoded) : '';
    $json = openrouter_extract_json_object($text);

    return [
        'enabled' => true,
        'status' => is_array($json) ? 'completed' : 'raw',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'method' => 'OpenRouter openrouter:web_search server tool',
        'result' => is_array($json) ? $json : null,
        'raw' => is_array($json) ? '' : $text,
        'usage' => is_array($decoded) ? ($decoded['usage'] ?? null) : null,
        'limitation' => 'OpenRouter web_search extra költséget okozhat még free modell mellett is, és tool-calling támogatást igényelhet a választott modellnél.',
    ];
}

function openrouter_chat_request(array $payload, array $config, int $timeout, int $connectTimeout): array
{
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
            'HTTP-Referer: ' . (defined('APP_URL') ? APP_URL : 'https://hello-ai-audit.local'),
            'X-Title: Hello AI Audit',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        $message = $error ?: ('OpenRouter API hiba HTTP ' . $status);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $message .= ': ' . (string) $decoded['error']['message'];
        }

        return [
            'ok' => false,
            'status' => $status,
            'raw' => $raw,
            'message' => $message,
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'raw' => $raw,
        'message' => '',
    ];
}

function openrouter_aio_system_prompt(): string
{
    return implode("\n", [
        'Te magyar nyelvű AIO/GEO audit reviewer vagy.',
        'Feladatod: adj második szakértői véleményt a kapott audit JSON-ról.',
        'Ne ígérj garantált AI Overview, ChatGPT vagy Gemini megjelenést.',
        'Különítsd el az on-page AIO készültséget és a tényleges AI keresési láthatósági mérést.',
        'OpenRouter free modellként nem feltételezhetsz live web search eredményt. Ha láthatóságról beszélsz, mérési tervként és hipotézisként fogalmazz.',
        'Legyen közérthető, ügyfélbarát és részletes. Ne vágd rövidre a választ: minden cím alatt adj használható, teljes gondolatmenetet.',
        'Markdown szerkezetet használj ezekkel a címekkel:',
        '## OpenRouter gyors másodvélemény',
        '## Ami hiányzik a tényleges AI láthatósághoz',
        '## Mit teszteljünk AI keresőkben',
        '## 5 gyors javítás',
    ]);
}

function openrouter_aio_user_prompt(array $report): string
{
    $compact = [
        'url' => $report['url'] ?? '',
        'overall_score' => $report['overall_score'] ?? 0,
        'scores' => $report['scores'] ?? [],
        'summary' => $report['summary'] ?? [],
        'ai_search_plan' => $report['ai_search_plan'] ?? [],
        'recommendations' => array_slice($report['recommendations'] ?? [], 0, 12),
        'pages' => array_map(static function (array $page): array {
            return [
                'url' => $page['url'] ?? '',
                'title' => $page['title'] ?? '',
                'score' => $page['score'] ?? 0,
                'word_count' => $page['word_count'] ?? 0,
                'signals' => $page['signals'] ?? [],
                'context_signals' => $page['context_signals'] ?? [],
                'issues' => array_slice($page['issues'] ?? [], 0, 5),
            ];
        }, array_slice($report['pages'] ?? [], 0, 8)),
        'methodology_sources' => array_slice(aio_methodology_sources(), 0, 8),
    ];

    return "Audit riport JSON kivonat:\n" . json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function openrouter_extract_text(array $decoded): string
{
    $parts = [];
    foreach (($decoded['choices'] ?? []) as $choice) {
        $message = $choice['message'] ?? [];
        if (isset($message['content'])) {
            if (is_string($message['content'])) {
                $parts[] = $message['content'];
            } elseif (is_array($message['content'])) {
                foreach ($message['content'] as $contentPart) {
                    if (is_array($contentPart) && isset($contentPart['text'])) {
                        $parts[] = (string) $contentPart['text'];
                    }
                }
            }
        }
    }

    return trim(implode("\n\n", $parts));
}

function openrouter_extract_json_object(string $text): ?array
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
