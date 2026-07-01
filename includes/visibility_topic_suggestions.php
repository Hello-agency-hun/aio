<?php
/**
 * AI témasegéd az AI láthatósági mérési profilhoz.
 *
 * A felhasználók gyakran nem tudják, milyen témákat érdemes mérni egy domain
 * esetében. Ez a modul a domain, piac, üzleti modell és a publikus weboldalból
 * olvasott kontextus alapján javasol témákat. Szándékosan nem ad helyi
 * sablon-fallbacket: ha az AI vagy az oldalolvasás nem működik, hibát jelez.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/openrouter.php';

function suggest_visibility_topics(array $input): array
{
    $siteUrl = normalize_url((string) ($input['site_url'] ?? ''));
    if ($siteUrl === null || !is_public_target_url($siteUrl)) {
        throw new RuntimeException('Adj meg egy publikus domaint vagy URL-t, hogy tudjak témákat javasolni.');
    }

    $businessModel = visibility_normalize_business_model((string) ($input['business_model'] ?? 'generic'));
    $profile = [
        'site_url' => $siteUrl,
        'target_domain' => visibility_normalize_domain($siteUrl),
        'project_name' => trim((string) ($input['name'] ?? '')),
        'market' => trim((string) ($input['market'] ?? 'Magyarország')) ?: 'Magyarország',
        'language' => trim((string) ($input['language'] ?? 'hu')) ?: 'hu',
        'business_model' => $businessModel,
        'business_model_label' => visibility_business_models()[$businessModel] ?? 'Általános weboldal',
        'existing_topics' => visibility_parse_list((string) ($input['topics'] ?? ''), 12),
    ];

    $context = visibility_topic_homepage_context($siteUrl);
    visibility_topic_require_context($context);
    $ai = visibility_topic_ai_suggestions($profile, $context);
    $suggestions = $ai['suggestions'];
    $source = $ai['source'];
    $message = $ai['message'];

    if (!$suggestions) {
        throw new RuntimeException($message ?: 'Az AI témasegéd nem adott használható témalistát. Ellenőrizd az OpenRouter kulcsot/modellt és próbáld újra.');
    }

    return [
        'profile' => $profile,
        'context' => $context,
        'suggestions' => array_slice($suggestions, 0, 10),
        'topics' => array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['topic'] ?? ''), $suggestions))),
        'source' => $source,
        'message' => $message,
    ];
}

function visibility_topic_homepage_context(string $url): array
{
    $context = [
        'status' => 'skipped',
        'title' => '',
        'description' => '',
        'h1' => [],
        'text_excerpt' => '',
        'message' => '',
    ];

    if (!function_exists('curl_init')) {
        $context['message'] = 'A homepage kontextus letöltése kimaradt: cURL nem elérhető.';
        return $context;
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'HelloAI-Audit/1.0 topic helper',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ];

    $caBundle = DATA_DIR . '/cacert.pem';
    if (is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    curl_setopt_array($ch, $options);
    $html = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $status < 200 || $status >= 400 || $html === '') {
        $context['status'] = 'error';
        $context['message'] = $error ?: ('A nyitóoldal nem adott használható HTML választ. HTTP ' . $status);
        return $context;
    }

    $limitedHtml = substr($html, 0, 520000);
    $context['status'] = 'completed';
    $context['title'] = visibility_topic_match_text('~<title[^>]*>(.*?)</title>~isu', $limitedHtml);
    $context['description'] = visibility_topic_match_text('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']~isu', $limitedHtml)
        ?: visibility_topic_match_text('~<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']~isu', $limitedHtml);
    $context['h1'] = visibility_topic_match_all_text('~<h1[^>]*>(.*?)</h1>~isu', $limitedHtml, 4);

    $plain = preg_replace('~<(script|style|noscript|svg)[^>]*>.*?</\1>~isu', ' ', $limitedHtml) ?: $limitedHtml;
    $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $context['text_excerpt'] = text_excerpt($plain, 2200);

    return $context;
}

function visibility_topic_require_context(array $context): void
{
    if (($context['status'] ?? '') !== 'completed') {
        throw new RuntimeException('Nem tudtam elolvasni a weboldal publikus tartalmát, ezért nem készítek témalistát találgatásból. Részlet: ' . (string) ($context['message'] ?? 'ismeretlen letöltési hiba'));
    }

    $text = trim((string) ($context['text_excerpt'] ?? ''));
    $title = trim((string) ($context['title'] ?? ''));
    $h1 = is_array($context['h1'] ?? null) ? implode(' ', $context['h1']) : '';
    if (text_length($text . ' ' . $title . ' ' . $h1) < 120) {
        throw new RuntimeException('A weboldalból túl kevés olvasható szöveget találtam. AI téma- vagy versenytársjavaslatot csak valódi oldal-kontekstusból adok.');
    }
}

function visibility_topic_match_text(string $pattern, string $html): string
{
    if (!preg_match($pattern, $html, $match)) {
        return '';
    }

    return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
}

function visibility_topic_match_all_text(string $pattern, string $html, int $limit): array
{
    if (!preg_match_all($pattern, $html, $matches)) {
        return [];
    }

    $items = [];
    foreach (array_slice($matches[1] ?? [], 0, $limit) as $value) {
        $clean = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
        if ($clean !== '') {
            $items[] = $clean;
        }
    }

    return array_values(array_unique($items));
}

function visibility_topic_ai_suggestions(array $profile, array $context): array
{
    $openAiConfig = openai_config();
    if (!empty($openAiConfig['api_key']) && function_exists('curl_init')) {
        return visibility_topic_openai_suggestions($profile, $context, $openAiConfig);
    }

    $config = openrouter_config();
    if (($config['enabled'] ?? true) !== true || empty($config['api_key']) || !function_exists('curl_init')) {
        throw new RuntimeException('Az AI témasegédhez OpenAI vagy OpenRouter API kulcs és cURL szükséges. Most nincs működő AI provider, ezért nem készítek sablonos témalistát.');
    }

    return visibility_topic_openrouter_suggestions($profile, $context, $config);
}

function visibility_topic_openai_suggestions(array $profile, array $context, array $config): array
{
    $model = (string) ($config['model'] ?: 'gpt-5.5');
    $payload = [
        'model' => $model,
        'reasoning' => ['effort' => 'low'],
        'max_output_tokens' => 2200,
        'instructions' => visibility_topic_system_prompt(),
        'input' => ai_json_encode([
            'profile' => $profile,
            'homepage_context' => $context,
        ]),
    ];

    $timeout = max(45, min(180, (int) ($config['timeout'] ?? OPENAI_REQUEST_TIMEOUT)));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? OPENAI_CONNECT_TIMEOUT));
    $apiResult = openai_responses_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        throw new RuntimeException('OpenAI témasegéd hiba: ' . $apiResult['message']);
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? extract_openai_text($decoded) : '';
    $json = extract_json_object($text);
    $suggestions = is_array($json['suggestions'] ?? null) ? visibility_topic_normalize_suggestions($json['suggestions']) : [];

    return [
        'source' => 'openai',
        'message' => $suggestions ? 'AI témasegéd elkészült OpenAI alapon.' : 'Az OpenAI válasz nem tartalmazott használható témalistát.',
        'suggestions' => $suggestions,
    ];
}

function visibility_topic_openrouter_suggestions(array $profile, array $context, array $config): array
{
    $model = (string) ($config['model'] ?: 'openrouter/free');
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => visibility_topic_system_prompt(),
            ],
            [
                'role' => 'user',
                'content' => ai_json_encode([
                    'profile' => $profile,
                    'homepage_context' => $context,
                ]),
            ],
        ],
        'temperature' => 0.25,
        'max_tokens' => 1800,
        'stream' => false,
    ];

    $timeout = max(45, min(120, (int) ($config['timeout'] ?? 90)));
    $connectTimeout = max(5, (int) ($config['connect_timeout'] ?? 20));
    $apiResult = openrouter_chat_request($payload, $config, $timeout, $connectTimeout);
    if (!$apiResult['ok']) {
        throw new RuntimeException('OpenRouter témasegéd hiba: ' . $apiResult['message']);
    }

    $decoded = json_decode((string) $apiResult['raw'], true);
    $text = is_array($decoded) ? openrouter_extract_text($decoded) : '';
    $json = openrouter_extract_json_object($text);
    $suggestions = is_array($json['suggestions'] ?? null) ? visibility_topic_normalize_suggestions($json['suggestions']) : [];

    return [
        'source' => 'openrouter',
        'message' => $suggestions ? 'AI témasegéd elkészült OpenRouter alapon.' : 'Az AI válasz nem tartalmazott használható témalistát.',
        'suggestions' => $suggestions,
    ];
}

function visibility_topic_system_prompt(): string
{
    return implode("\n", [
        'Magyar nyelvű AI visibility research strategist vagy.',
        'Feladatod: egy weboldalhoz javasolj mérhető, üzletileg releváns AI keresési témákat.',
        'A kapott homepage_context a weboldal publikus szövegéből készült; ebből dolgozz, ne általános sablonból.',
        'Ne SEO kulcsszavakat adj, hanem vevői problémaköröket, döntési helyzeteket és piaci témákat.',
        'A témák legyenek alkalmasak későbbi ChatGPT/Gemini/Perplexity/Google AI mérési kérdések generálására.',
        'Csak JSON objektummal válaszolj. Markdown tilos.',
        'JSON forma: {"suggestions":[{"topic":"","intent":"","why":"","example_questions":["",""],"priority":"high|medium|low"}]}',
        'Adj 8-10 témát. A topic legyen rövid, természetes magyar kifejezés.',
        'Ha a weboldal kommunikációs, kreatív, rendezvényes vagy B2B ügynökség, ezt vedd figyelembe; ne adj irreleváns e-commerce vagy webshop témákat.',
    ]);
}

function visibility_topic_normalize_suggestions(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $topic = trim((string) ($item['topic'] ?? ''));
        if ($topic === '') {
            continue;
        }

        $exampleQuestions = $item['example_questions'] ?? [];
        if (!is_array($exampleQuestions)) {
            $exampleQuestions = [$exampleQuestions];
        }

        $normalized[] = [
            'topic' => $topic,
            'intent' => trim((string) ($item['intent'] ?? 'AI keresési mérési téma')),
            'why' => trim((string) ($item['why'] ?? 'Erre a témára vevői döntési kérdéseket lehet mérni.')),
            'example_questions' => array_slice(array_values(array_filter(array_map('strval', $exampleQuestions))), 0, 3),
            'priority' => in_array(($item['priority'] ?? ''), ['high', 'medium', 'low'], true) ? (string) $item['priority'] : 'medium',
        ];
    }

    return array_slice($normalized, 0, 10);
}
