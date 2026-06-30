<?php
/**
 * AI témasegéd az AI láthatósági mérési profilhoz.
 *
 * A felhasználók gyakran nem tudják, milyen témákat érdemes mérni egy domain
 * esetében. Ez a modul a domain, piac, üzleti modell és egy könnyű homepage
 * kontextus alapján javasol olyan témaköröket, amelyekből később buyer,
 * comparison, expert és trust jellegű AI keresési kérdések építhetők.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';
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
    $ai = visibility_topic_ai_suggestions($profile, $context);
    $suggestions = $ai['suggestions'];
    $source = $ai['source'];
    $message = $ai['message'];

    if (!$suggestions) {
        $suggestions = visibility_topic_fallback_suggestions($profile, $context);
        $source = 'fallback';
        $message = 'AI válasz helyett biztonságos helyi témasablont adtam. Ezek jó kiindulópontok, de érdemes kézzel pontosítani őket.';
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
        CURLOPT_TIMEOUT => 10,
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
    $context['text_excerpt'] = text_excerpt($plain, 1200);

    return $context;
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
    $config = openrouter_config();
    if (($config['enabled'] ?? true) !== true || empty($config['api_key']) || !function_exists('curl_init')) {
        return [
            'source' => 'unavailable',
            'message' => 'OpenRouter nincs beállítva, ezért helyi témasablon készül.',
            'suggestions' => [],
        ];
    }

    $model = (string) ($config['model'] ?: 'openrouter/free');
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Magyar nyelvű AI visibility research strategist vagy.',
                    'Feladatod: egy weboldalhoz javasolj mérhető, üzletileg releváns AI keresési témákat.',
                    'Ne SEO kulcsszavakat adj, hanem vevői problémaköröket és döntési témákat.',
                    'A témák legyenek alkalmasak későbbi ChatGPT/Gemini/Perplexity/Google AI mérési kérdések generálására.',
                    'Csak JSON objektummal válaszolj. Markdown tilos.',
                    'JSON forma: {"suggestions":[{"topic":"","intent":"","why":"","example_questions":["",""],"priority":"high|medium|low"}]}',
                    'Adj 8-10 témát. A topic legyen rövid, természetes magyar kifejezés.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'profile' => $profile,
                    'homepage_context' => $context,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
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
        return [
            'source' => 'error',
            'message' => 'OpenRouter témasegéd hiba: ' . $apiResult['message'],
            'suggestions' => [],
        ];
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

function visibility_topic_fallback_suggestions(array $profile, array $context): array
{
    $domain = (string) ($profile['target_domain'] ?? 'a weboldal');
    $model = (string) ($profile['business_model'] ?? 'generic');
    $base = [
        ['topic' => 'vevői probléma és megoldási útvonal', 'intent' => 'problem', 'why' => 'AI keresőkben sok kérdés problémafelismeréssel indul, nem márkanévvel.', 'priority' => 'high'],
        ['topic' => 'szolgáltató vagy megoldás kiválasztási szempontok', 'intent' => 'buyer', 'why' => 'A döntés előtt álló felhasználók gyakran választási kritériumokra kérdeznek rá.', 'priority' => 'high'],
        ['topic' => 'összehasonlítás alternatív megoldásokkal', 'intent' => 'comparison', 'why' => 'A versenytársakkal együtt említett válaszok mutatják a valódi AI láthatósági mezőt.', 'priority' => 'high'],
        ['topic' => 'ár, megtérülés és döntési kockázat', 'intent' => 'pricing', 'why' => 'A költség, ROI és kockázat témák erős vásárlási szándékot jeleznek.', 'priority' => 'medium'],
        ['topic' => 'bizonyítékok, referenciák és szakértői hitelesség', 'intent' => 'trust', 'why' => 'Az AI válaszok gyakran megbízható forrásokra, esettanulmányokra és szakértői jelekre támaszkodnak.', 'priority' => 'high'],
        ['topic' => 'gyakori hibák és buktatók', 'intent' => 'expert', 'why' => 'A hibákat magyarázó tartalom jó eséllyel válik idézhető, answer-first forrássá.', 'priority' => 'medium'],
        ['topic' => 'bevezetési folyamat és első lépések', 'intent' => 'how-to', 'why' => 'A gyakorlati útmutatók jól illeszkednek AI válaszokba és döntéstámogató blokkokba.', 'priority' => 'medium'],
        ['topic' => 'helyi vagy iparági kontextus', 'intent' => 'local', 'why' => 'A piac és iparág szűkítése relevánsabb találati és citációs versenyt mutat.', 'priority' => 'medium'],
    ];

    $modelExtras = [
        'b2b_service' => ['topic' => 'B2B döntéshozói kifogások és beszerzési érvek', 'intent' => 'buyer', 'why' => 'B2B oldalaknál több szereplő dönt, ezért a kifogáskezelés és bizonyíték külön téma.', 'priority' => 'high'],
        'local_service' => ['topic' => 'helyi szolgáltató választása és bizalmi jelei', 'intent' => 'local', 'why' => 'Helyi kereséseknél a lokáció, elérhetőség, értékelés és gyors döntési információ kulcsfontosságú.', 'priority' => 'high'],
        'ecommerce' => ['topic' => 'termékválasztási szempontok és alternatívák', 'intent' => 'comparison', 'why' => 'E-commerce esetben az AI válaszok gyakran kategória, használati helyzet és összehasonlítás alapján ajánlanak.', 'priority' => 'high'],
        'saas' => ['topic' => 'szoftver alternatívák, integrációk és use case-ek', 'intent' => 'comparison', 'why' => 'SaaS mérésnél fontos, hogy milyen feladatra, kinek és milyen stackbe ajánlja az AI a megoldást.', 'priority' => 'high'],
        'expert_brand' => ['topic' => 'szakértői álláspontok és gondolatvezetői témák', 'intent' => 'expert', 'why' => 'Szakértői brandnél a név nélküli témaszakértői említés is fontos láthatósági jel.', 'priority' => 'high'],
    ];

    if (isset($modelExtras[$model])) {
        array_unshift($base, $modelExtras[$model]);
    }

    $title = trim((string) ($context['title'] ?? ''));
    if ($title !== '') {
        array_unshift($base, [
            'topic' => text_excerpt($title, 72),
            'intent' => 'brand-context',
            'why' => 'A nyitóoldal címe alapján ez lehet az oldal legközvetlenebb piaci témája.',
            'priority' => 'high',
        ]);
    }

    return array_map(static function (array $item) use ($domain): array {
        $topic = (string) $item['topic'];
        return [
            'topic' => $topic,
            'intent' => (string) $item['intent'],
            'why' => (string) $item['why'],
            'example_questions' => [
                'Milyen szempontok alapján érdemes ' . $topic . ' témában dönteni?',
                'Melyik szolgáltató vagy forrás segít a(z) ' . $topic . ' kérdésben?',
                'Megjelenik-e a ' . $domain . ' releváns válaszforrásként erre a témára?',
            ],
            'priority' => (string) $item['priority'],
        ];
    }, array_slice($base, 0, 10));
}
