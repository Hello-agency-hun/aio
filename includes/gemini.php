<?php
/**
 * Opcionális Google Gemini kontroll elemző réteg.
 *
 * A kulcsot nem publikus PHP fájlban tartjuk, hanem környezeti változóként
 * (`GEMINI_API_KEY`) vagy a webtől védett `data/gemini_config.php` fájlban.
 * A modul audit JSON és visibility mérés alapján ad második szakértői
 * véleményt. Ez nem helyettesíti a tényleges Google AI Overview panelmérést.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/methodology.php';

function gemini_config(): array
{
    $config = [
        'enabled' => getenv('GEMINI_ENABLED') !== '0',
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => getenv('GEMINI_MODEL') ?: 'gemini-flash-latest',
        'timeout' => (int) (getenv('GEMINI_TIMEOUT') ?: 150),
        'connect_timeout' => (int) (getenv('GEMINI_CONNECT_TIMEOUT') ?: 20),
        'max_output_tokens' => (int) (getenv('GEMINI_MAX_OUTPUT_TOKENS') ?: 6000),
        'grounded_timeout' => (int) (getenv('GEMINI_GROUNDED_TIMEOUT') ?: 150),
        'grounded_max_output_tokens' => (int) (getenv('GEMINI_GROUNDED_MAX_OUTPUT_TOKENS') ?: 1400),
        'ca_bundle' => getenv('GEMINI_CA_BUNDLE') ?: DATA_DIR . '/cacert.pem',
    ];

    $protectedConfig = DATA_DIR . '/gemini_config.php';
    if (is_file($protectedConfig)) {
        $fileConfig = require $protectedConfig;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
        }
    }

    return $config;
}

function enrich_report_with_gemini(array $report): array
{
    $config = gemini_config();
    if (($config['enabled'] ?? true) !== true) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'provider' => 'gemini',
            'message' => 'Gemini elemzés ki van kapcsolva.',
        ];
    }

    if (empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'provider' => 'gemini',
            'message' => 'Gemini elemzés kihagyva: nincs beállított GEMINI_API_KEY.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'gemini',
            'message' => 'A Gemini híváshoz szükséges cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'gemini-flash-latest');
    $timeout = max(45, (int) ($config['timeout'] ?? 150));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $maxOutputTokens = max(2200, (int) ($config['max_output_tokens'] ?? 6000));

    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout + 30);
    }

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => gemini_aio_system_prompt()],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => gemini_aio_user_prompt($report)],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => $maxOutputTokens,
        ],
    ];

    $apiResult = gemini_generate_content_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'gemini',
            'model' => $model,
            'timeout_seconds' => $timeout,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? gemini_extract_text($decoded) : '';

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'provider' => 'gemini',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? gemini_extract_usage($decoded) : null,
        'message' => $text !== '' ? '' : 'A Gemini válasz üres volt.',
    ];
}

function gemini_visibility_strategy(array $project, array $run): array
{
    $config = gemini_config();
    if (($config['enabled'] ?? true) !== true || empty($config['api_key'])) {
        return [
            'enabled' => false,
            'status' => 'skipped',
            'provider' => 'gemini',
            'message' => 'Nincs bekapcsolt Gemini API kulcs.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'gemini',
            'message' => 'A cURL PHP kiterjesztés nem elérhető.',
        ];
    }

    $model = (string) ($config['model'] ?: 'gemini-flash-latest');
    $timeout = max(75, (int) ($config['timeout'] ?? 150));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $maxOutputTokens = max(3200, min(7000, (int) ($config['max_output_tokens'] ?? 5600)));

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => gemini_visibility_system_prompt()],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => gemini_visibility_user_prompt($project, $run)],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => $maxOutputTokens,
        ],
    ];

    $apiResult = gemini_generate_content_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        return [
            'enabled' => true,
            'status' => 'error',
            'provider' => 'gemini',
            'model' => $model,
            'message' => $apiResult['message'],
        ];
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? gemini_extract_text($decoded) : '';

    return [
        'enabled' => true,
        'status' => $text !== '' ? 'completed' : 'empty',
        'provider' => 'gemini',
        'model' => $model,
        'generated_at' => date(DATE_ATOM),
        'analysis' => $text,
        'usage' => is_array($decoded) ? gemini_extract_usage($decoded) : null,
        'message' => $text !== '' ? '' : 'A Gemini válasz üres volt.',
    ];
}

function gemini_generate_content_request(array $payload, array $config, int $timeout, int $connectTimeout): array
{
    $model = rawurlencode((string) ($config['model'] ?: 'gemini-flash-latest'));
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent');
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $config['api_key'],
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
        $message = $error ?: ('Gemini API hiba HTTP ' . $status);
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

function gemini_aio_system_prompt(): string
{
    return implode("\n", [
        'Te magyar nyelvű Google/Gemini szemléletű AIO/GEO audit reviewer vagy.',
        'Feladatod: adj külön kontrollvéleményt a kapott audit JSON-ról.',
        'Ne ígérj garantált Google AI Overview, Gemini vagy ChatGPT megjelenést.',
        'Különítsd el az on-page AIO készültséget, a keresési bizonyítékokat és a tényleges AI keresési panelmérések hiányát.',
        'Legyen közérthető, ügyfélbarát és gyakorlati. Kerüld a túl tudományos zsargont.',
        'Markdown szerkezetet használj ezekkel a címekkel:',
        '## Gemini kontroll összefoglaló',
        '## Google/AI keresési kockázatok',
        '## Mit kellene tartalmilag átírni',
        '## Milyen kérdésekre mérjünk rá élőben',
        '## 5 gyors javítás',
        'Minden cím alatt adj teljes, használható gondolatmenetet és konkrét első lépéseket.',
    ]);
}

function gemini_aio_user_prompt(array $report): string
{
    $compact = [
        'url' => $report['url'] ?? '',
        'overall_score' => $report['overall_score'] ?? 0,
        'scores' => $report['scores'] ?? [],
        'summary' => $report['summary'] ?? [],
        'ai_search_plan' => $report['ai_search_plan'] ?? [],
        'saved_search_probe' => $report['saved_search_probe'] ?? [],
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

function gemini_visibility_system_prompt(): string
{
    return implode("\n", [
        'Te magyar nyelvű AI keresési láthatósági stratégiai elemző vagy Google/Gemini szemlélettel.',
        'A kapott adatok keresési provider találatokból származnak, nem garantált Google AI Overview vagy Gemini válaszpanelből.',
        'Minden következtetést irányadó jelként fogalmazz, ne abszolút igazságként.',
        'A válasz legyen ügyfélbarát, közérthető, konkrét és kivitelezhető.',
        'Markdownban válaszolj pontosan ezekkel a főcímekkel:',
        '## Vezetői összefoglaló',
        '## Mit jelent ez Google/Gemini nézőpontból?',
        '## Hol veszítünk láthatóságot?',
        '## Versenytárs minták',
        '## Tartalmi javítási terv',
        '## Következő mérési kör',
        'Minden fontos állításnál különítsd el: **Biztos jel:**, **Irányadó jel:**, **Teendő:**.',
    ]);
}

function gemini_visibility_user_prompt(array $project, array $run): string
{
    if (function_exists('visibility_ai_user_prompt')) {
        return visibility_ai_user_prompt($project, $run);
    }

    return "AI láthatóságmérési JSON kivonat:\n" . json_encode([
        'project' => $project,
        'run' => $run,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function gemini_extract_text(array $decoded): string
{
    $parts = [];
    foreach (($decoded['candidates'] ?? []) as $candidate) {
        foreach (($candidate['content']['parts'] ?? []) as $part) {
            if (is_array($part) && isset($part['text'])) {
                $parts[] = (string) $part['text'];
            }
        }
    }

    return trim(implode("\n\n", $parts));
}

function gemini_extract_usage(array $decoded): ?array
{
    $usage = $decoded['usageMetadata'] ?? null;
    if (!is_array($usage)) {
        return null;
    }

    return [
        'input_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
        'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
        'reasoning_tokens' => (int) ($usage['thoughtsTokenCount'] ?? 0),
        'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
    ];
}
