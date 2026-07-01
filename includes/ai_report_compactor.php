<?php
/**
 * AI provider prompt-kivonatok.
 *
 * Nagy auditnál a teljes JSON riport túl nagy és lassú lehet a másodlagos
 * AI elemző rétegeknek. Ez a modul egységes, priorizált kivonatot készít:
 * a fontos pontszámokat, a top javításokat, a legproblémásabb oldalakat és a
 * mérési kérdéssort küldi tovább, nem a teljes nyers riportot.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/methodology.php';

function ai_report_is_large(array $report): bool
{
    $pageCount = count($report['pages'] ?? []);
    $crawlLimit = (int) ($report['crawl_limit'] ?? 0);
    $mappedUrls = (int) ($report['site_map']['discovered_url_count'] ?? 0);

    return $pageCount >= 25 || $crawlLimit >= 40 || $mappedUrls >= 120;
}

function ai_report_compact(array $report, string $profile = 'primary'): array
{
    $isLarge = ai_report_is_large($report);
    $limits = ai_report_compact_limits($profile, $isLarge);

    return [
        'url' => $report['url'] ?? '',
        'overall_score' => $report['overall_score'] ?? 0,
        'scores' => $report['scores'] ?? [],
        'summary' => ai_report_compact_summary($report['summary'] ?? []),
        'crawl_mode' => $report['crawl_mode'] ?? '',
        'crawl_limit' => $report['crawl_limit'] ?? 0,
        'large_audit_safe_mode' => $isLarge,
        'site_map' => ai_report_compact_site_map($report['site_map'] ?? []),
        'ai_search_plan' => ai_report_compact_search_plan($report['ai_search_plan'] ?? [], $limits['queries']),
        'saved_search_probe' => ai_report_compact_saved_search_probe($report['saved_search_probe'] ?? [], $limits['search_queries']),
        'recommendations' => ai_report_compact_recommendations($report['recommendations'] ?? [], $limits['recommendations']),
        'pages' => ai_report_compact_pages($report['pages'] ?? [], $limits['pages'], $limits['issues']),
        'methodology_sources' => array_slice(aio_methodology_sources(), 0, $limits['sources']),
        'compaction_note' => $isLarge
            ? 'Nagy audit futott, ezért az AI réteg priorizált kivonatot kapott a teljes riport helyett. A részletes oldallistát a fő riport tartalmazza.'
            : 'Normál audit kivonat.',
    ];
}

function ai_report_compact_limits(string $profile, bool $isLarge): array
{
    $profiles = [
        'primary' => $isLarge
            ? ['pages' => 8, 'issues' => 5, 'recommendations' => 12, 'queries' => 6, 'search_queries' => 4, 'sources' => 4]
            : ['pages' => 12, 'issues' => 6, 'recommendations' => 16, 'queries' => 8, 'search_queries' => 5, 'sources' => 6],
        'secondary' => $isLarge
            ? ['pages' => 5, 'issues' => 4, 'recommendations' => 8, 'queries' => 5, 'search_queries' => 3, 'sources' => 3]
            : ['pages' => 8, 'issues' => 5, 'recommendations' => 10, 'queries' => 6, 'search_queries' => 4, 'sources' => 4],
        'probe' => ['pages' => 0, 'issues' => 0, 'recommendations' => 4, 'queries' => $isLarge ? 4 : 6, 'search_queries' => 3, 'sources' => 0],
    ];

    return $profiles[$profile] ?? $profiles['primary'];
}

function ai_report_compact_summary(array $summary): array
{
    return [
        'label' => $summary['label'] ?? '',
        'pages_checked' => $summary['pages_checked'] ?? 0,
        'root_url' => $summary['root_url'] ?? '',
        'critical_count' => $summary['critical_count'] ?? 0,
        'warning_count' => $summary['warning_count'] ?? 0,
        'methodology_note' => text_excerpt((string) ($summary['methodology_note'] ?? ''), 360),
    ];
}

function ai_report_compact_site_map(array $siteMap): array
{
    return [
        'discovered_url_count' => $siteMap['discovered_url_count'] ?? 0,
        'selected_url_count' => $siteMap['selected_url_count'] ?? 0,
        'coverage_note' => text_excerpt((string) ($siteMap['coverage_note'] ?? ''), 360),
        'priority_mix' => $siteMap['priority_mix'] ?? [],
    ];
}

function ai_report_compact_search_plan(array $plan, int $queryLimit): array
{
    return [
        'brand_name' => $plan['brand_name'] ?? '',
        'domain' => $plan['domain'] ?? '',
        'market_context' => $plan['market_context'] ?? '',
        'country_hint' => $plan['country_hint'] ?? '',
        'main_topics' => array_slice($plan['main_topics'] ?? [], 0, 8),
        'query_set' => array_map(static function (array $query): array {
            return [
                'id' => $query['id'] ?? '',
                'type' => $query['type'] ?? '',
                'query' => $query['query'] ?? '',
                'why' => text_excerpt((string) ($query['why'] ?? ''), 220),
                'expected_signal' => text_excerpt((string) ($query['expected_signal'] ?? ''), 220),
            ];
        }, array_slice($plan['query_set'] ?? [], 0, $queryLimit)),
        'static_readiness' => $plan['static_readiness'] ?? [],
    ];
}

function ai_report_compact_recommendations(array $recommendations, int $limit): array
{
    return array_map(static function (array $item): array {
        return [
            'level' => $item['level'] ?? '',
            'title' => text_excerpt((string) ($item['title'] ?? ''), 180),
            'category' => $item['category'] ?? '',
            'why' => text_excerpt((string) ($item['why'] ?? ''), 260),
            'fix' => text_excerpt((string) ($item['fix'] ?? ''), 260),
            'next_step' => text_excerpt((string) ($item['next_step'] ?? ''), 280),
            'impact' => text_excerpt((string) ($item['impact'] ?? ''), 180),
            'count' => $item['count'] ?? 0,
            'pages' => array_slice($item['pages'] ?? [], 0, 5),
        ];
    }, array_slice($recommendations, 0, $limit));
}

function ai_report_compact_pages(array $pages, int $limit, int $issueLimit): array
{
    if ($limit <= 0) {
        return [];
    }

    usort($pages, static function (array $a, array $b): int {
        $aCritical = ai_report_page_issue_weight($a);
        $bCritical = ai_report_page_issue_weight($b);
        if ($aCritical !== $bCritical) {
            return $bCritical <=> $aCritical;
        }

        return ((int) ($a['score'] ?? 100)) <=> ((int) ($b['score'] ?? 100));
    });

    return array_map(static function (array $page) use ($issueLimit): array {
        return [
            'url' => $page['url'] ?? '',
            'title' => text_excerpt((string) ($page['title'] ?? ''), 160),
            'description' => text_excerpt((string) ($page['description'] ?? ''), 220),
            'score' => $page['score'] ?? 0,
            'word_count' => $page['word_count'] ?? 0,
            'signals' => ai_report_key_signals($page['signals'] ?? []),
            'raw_html_signals' => ai_report_key_signals($page['raw_html_signals'] ?? []),
            'indexing_signals' => ai_report_key_signals($page['indexing_signals'] ?? []),
            'answer_blocks' => array_slice($page['answer_blocks'] ?? [], 0, 2),
            'issues' => array_map(static function (array $issue): array {
                return [
                    'level' => $issue['level'] ?? '',
                    'title' => text_excerpt((string) ($issue['title'] ?? ''), 180),
                    'fix' => text_excerpt((string) ($issue['fix'] ?? ''), 240),
                ];
            }, array_slice($page['issues'] ?? [], 0, $issueLimit)),
        ];
    }, array_slice($pages, 0, $limit));
}

function ai_report_page_issue_weight(array $page): int
{
    $weight = 0;
    foreach (($page['issues'] ?? []) as $issue) {
        $level = (string) ($issue['level'] ?? '');
        $weight += $level === 'critical' ? 4 : ($level === 'warning' ? 2 : 1);
    }

    return $weight;
}

function ai_report_key_signals(array $signals): array
{
    $keep = [
        'has_noindex',
        'suspicious_noindex',
        'should_audit_index_metadata',
        'has_structured_data',
        'has_json_ld',
        'has_faq_schema',
        'has_organization_schema',
        'has_service_schema',
        'has_article_schema',
        'has_raw_html_content',
        'avoids_client_rendered_shell',
        'has_answer_first_blocks',
        'has_evidence_language',
        'has_entity_links',
        'has_clear_cta',
    ];

    return array_intersect_key($signals, array_flip($keep));
}

function ai_report_compact_saved_search_probe(array $probe, int $queryLimit): array
{
    if (!$probe) {
        return [];
    }

    return [
        'status' => $probe['status'] ?? '',
        'method' => $probe['method'] ?? '',
        'providers' => $probe['providers'] ?? [],
        'target_domain' => $probe['target_domain'] ?? '',
        'retrieval_hit_rate' => $probe['retrieval_hit_rate'] ?? 0,
        'owned_domain_query_hits' => $probe['owned_domain_query_hits'] ?? 0,
        'competitors' => array_slice($probe['competitors'] ?? [], 0, 8),
        'analysis_hint' => text_excerpt((string) ($probe['analysis_hint'] ?? ''), 280),
        'query_results' => array_map(static function (array $item): array {
            return [
                'id' => $item['id'] ?? '',
                'type' => $item['type'] ?? '',
                'query' => $item['query'] ?? '',
                'provider' => $item['provider'] ?? '',
                'status' => $item['status'] ?? '',
                'owned_domain_hit' => $item['owned_domain_hit'] ?? false,
                'own_results' => ai_report_compact_search_results($item['own_results'] ?? [], 2),
                'competitors' => array_slice($item['competitors'] ?? [], 0, 6),
                'top_results' => ai_report_compact_search_results($item['results'] ?? [], 4),
            ];
        }, array_slice($probe['query_results'] ?? [], 0, $queryLimit)),
    ];
}

function ai_report_compact_search_results(array $results, int $limit): array
{
    return array_map(static function (array $result): array {
        return [
            'title' => text_excerpt((string) ($result['title'] ?? ''), 140),
            'url' => $result['url'] ?? '',
            'host' => $result['host'] ?? '',
            'snippet' => text_excerpt((string) ($result['snippet'] ?? ''), 220),
        ];
    }, array_slice($results, 0, $limit));
}

function ai_json_encode(array $payload): string
{
    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return is_string($encoded) ? $encoded : '{}';
}

function ai_provider_error_message(string $provider, int $status, string $raw, string $curlError, int $timeout): string
{
    $decoded = json_decode($raw, true);
    $providerMessage = '';
    if (is_array($decoded)) {
        $providerMessage = (string) (
            $decoded['error']['message']
            ?? $decoded['message']
            ?? ''
        );
    }

    if ($status === 429) {
        return $provider . ' API limit vagy kvóta hiba (HTTP 429). Várj pár percet, ellenőrizd a provider kredit/rate limit beállításait, vagy futtasd kisebb AI kimenettel. ' . $providerMessage;
    }

    if ($curlError !== '' && stripos($curlError, 'timed out') !== false) {
        return $provider . ' válaszidő túllépés: ' . $timeout . ' másodperc alatt nem érkezett teljes válasz. A technikai audit elkészült, de ez az AI réteg túl lassú volt vagy túl nagy választ próbált adni.';
    }

    if ($curlError !== '') {
        return $provider . ' kapcsolati hiba: ' . $curlError;
    }

    $message = $provider . ' API hiba HTTP ' . $status;
    if ($providerMessage !== '') {
        $message .= ': ' . $providerMessage;
    }

    return $message;
}
