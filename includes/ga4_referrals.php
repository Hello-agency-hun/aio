<?php
/**
 * GA4 AI referral import modul.
 *
 * Shared hosting környezetben a legstabilabb első lépés a GA4-ből exportált
 * CSV feldolgozása. Ez a modul nem kér Google OAuth jogosultságot: a felhasználó
 * bemásolja vagy feltölti az exportot, a rendszer pedig felismeri az AI
 * referrer/source mintákat és JSON importként menti a projekthez.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visibility.php';

function ga4_referral_imports_dir(): string
{
    $dir = DATA_DIR . '/ga4_referral_imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function ga4_referral_import_path(string $id): string
{
    return ga4_referral_imports_dir() . '/' . visibility_safe_id($id) . '.json';
}

function ga4_save_referral_import(string $projectId, string $csv, string $filename = ''): array
{
    $projectId = visibility_safe_id($projectId);
    if ($projectId === '' || !read_visibility_project($projectId)) {
        throw new RuntimeException('A GA4 importhoz előbb nyiss meg egy érvényes visibility projektet.');
    }

    $parsed = ga4_parse_referral_csv($csv);
    $id = 'ga4_import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $import = [
        'id' => $id,
        'project_id' => $projectId,
        'created_at' => date(DATE_ATOM),
        'filename' => $filename,
        'method' => 'GA4 CSV AI referral import',
        'summary' => $parsed['summary'],
        'ai_sources' => $parsed['ai_sources'],
        'matched_rows' => $parsed['matched_rows'],
        'columns' => $parsed['columns'],
        'warnings' => $parsed['warnings'],
    ];

    file_put_contents(
        ga4_referral_import_path($id),
        json_encode($import, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $import;
}

function ga4_parse_referral_csv(string $csv): array
{
    $csv = trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?: '');
    if ($csv === '') {
        throw new RuntimeException('A GA4 CSV üres. Másold be vagy töltsd fel az export tartalmát.');
    }

    $lines = preg_split('~\R+~u', $csv) ?: [];
    $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
    if (count($lines) < 2) {
        throw new RuntimeException('A GA4 CSV-hez legalább fejléc és egy adatsor szükséges.');
    }

    $delimiter = ga4_detect_csv_delimiter($lines[0]);
    $headers = array_map('trim', str_getcsv($lines[0], $delimiter));
    $normalizedHeaders = array_map('ga4_normalize_header', $headers);
    $rows = [];
    $warnings = [];

    foreach (array_slice($lines, 1) as $line) {
        $values = str_getcsv($line, $delimiter);
        if (count(array_filter($values, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = trim((string) ($values[$index] ?? ''));
        }
        $rows[] = $row;
    }

    if (!$rows) {
        throw new RuntimeException('A GA4 CSV nem tartalmaz feldolgozható adatsort.');
    }

    $aiSources = ga4_ai_source_patterns();
    $summary = [
        'total_rows' => count($rows),
        'matched_rows' => 0,
        'total_sessions' => 0.0,
        'ai_sessions' => 0.0,
        'total_users' => 0.0,
        'ai_users' => 0.0,
        'total_conversions' => 0.0,
        'ai_conversions' => 0.0,
        'ai_session_share' => 0,
        'detected_source_count' => 0,
    ];
    $sourceBreakdown = [];
    $matchedRows = [];

    foreach ($rows as $rowIndex => $row) {
        $metrics = ga4_extract_metrics($row, $normalizedHeaders);
        $summary['total_sessions'] += $metrics['sessions'];
        $summary['total_users'] += $metrics['users'];
        $summary['total_conversions'] += $metrics['conversions'];

        $match = ga4_match_ai_source($row, $aiSources);
        if (!$match) {
            continue;
        }

        $summary['matched_rows']++;
        $summary['ai_sessions'] += $metrics['sessions'];
        $summary['ai_users'] += $metrics['users'];
        $summary['ai_conversions'] += $metrics['conversions'];

        $key = $match['key'];
        if (!isset($sourceBreakdown[$key])) {
            $sourceBreakdown[$key] = [
                'key' => $key,
                'label' => $match['label'],
                'matched_pattern' => $match['pattern'],
                'sessions' => 0.0,
                'users' => 0.0,
                'conversions' => 0.0,
                'rows' => 0,
            ];
        }

        $sourceBreakdown[$key]['sessions'] += $metrics['sessions'];
        $sourceBreakdown[$key]['users'] += $metrics['users'];
        $sourceBreakdown[$key]['conversions'] += $metrics['conversions'];
        $sourceBreakdown[$key]['rows']++;

        $matchedRows[] = [
            'row_number' => $rowIndex + 2,
            'source' => $match['label'],
            'matched_pattern' => $match['pattern'],
            'sessions' => $metrics['sessions'],
            'users' => $metrics['users'],
            'conversions' => $metrics['conversions'],
            'landing_page' => ga4_first_existing_value($row, ['Landing page + query string', 'Landing page', 'Page path + query string', 'Page path']),
            'source_medium' => ga4_first_existing_value($row, ['Session source / medium', 'Source / medium', 'First user source / medium']),
            'raw_hint' => text_excerpt(implode(' · ', array_filter($row)), 220),
        ];
    }

    usort($sourceBreakdown, static fn (array $a, array $b): int => ((int) $b['sessions']) <=> ((int) $a['sessions']));
    $summary['ai_session_share'] = $summary['total_sessions'] > 0
        ? (int) round(($summary['ai_sessions'] / $summary['total_sessions']) * 100)
        : 0;
    $summary['detected_source_count'] = count($sourceBreakdown);

    if ($summary['matched_rows'] === 0) {
        $warnings[] = 'Nem találtam ismert AI referral/source mintát a CSV-ben. Ellenőrizd, hogy source/medium vagy referrer dimenziót exportáltál-e.';
    }
    if ($summary['total_sessions'] <= 0) {
        $warnings[] = 'Nem találtam session metrikát. Az import így is menthető, de forgalmi arányt nem lehet pontosan számolni.';
    }

    return [
        'summary' => $summary,
        'ai_sources' => array_values($sourceBreakdown),
        'matched_rows' => array_slice($matchedRows, 0, 200),
        'columns' => $headers,
        'warnings' => $warnings,
    ];
}

function ga4_detect_csv_delimiter(string $headerLine): string
{
    $candidates = [',' => substr_count($headerLine, ','), ';' => substr_count($headerLine, ';'), "\t" => substr_count($headerLine, "\t")];
    arsort($candidates);
    return (string) array_key_first($candidates);
}

function ga4_normalize_header(string $header): string
{
    $header = text_lower(trim($header));
    $header = strtr($header, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
    return preg_replace('~[^a-z0-9]+~', '_', $header) ?: '';
}

function ga4_extract_metrics(array $row, array $normalizedHeaders): array
{
    return [
        'sessions' => ga4_metric_from_row($row, $normalizedHeaders, ['sessions', 'munkamenetek', 'engaged_sessions']),
        'users' => ga4_metric_from_row($row, $normalizedHeaders, ['total_users', 'users', 'active_users', 'felhasznalok', 'aktiv_felhasznalok']),
        'conversions' => ga4_metric_from_row($row, $normalizedHeaders, ['key_events', 'conversions', 'konverziok', 'conversion']),
    ];
}

function ga4_metric_from_row(array $row, array $normalizedHeaders, array $aliases): float
{
    $values = array_values($row);
    foreach ($normalizedHeaders as $index => $header) {
        if (in_array($header, $aliases, true) || count(array_filter($aliases, static fn (string $alias): bool => str_contains($header, $alias))) > 0) {
            return ga4_parse_number((string) ($values[$index] ?? '0'));
        }
    }
    return 0.0;
}

function ga4_parse_number(string $value): float
{
    $value = trim($value);
    if ($value === '') {
        return 0.0;
    }

    $value = preg_replace('~[^\d,.\-]~u', '', $value) ?: '';
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace(',', '', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? (float) $value : 0.0;
}

function ga4_match_ai_source(array $row, array $patterns): ?array
{
    $haystack = text_lower(implode(' ', array_values($row)));
    foreach ($patterns as $pattern) {
        foreach ($pattern['needles'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return [
                    'key' => $pattern['key'],
                    'label' => $pattern['label'],
                    'pattern' => $needle,
                ];
            }
        }
    }

    return null;
}

function ga4_ai_source_patterns(): array
{
    return [
        ['key' => 'chatgpt', 'label' => 'ChatGPT / OpenAI', 'needles' => ['chatgpt.com', 'chat.openai.com', 'openai.com', 'searchgpt']],
        ['key' => 'perplexity', 'label' => 'Perplexity', 'needles' => ['perplexity.ai', 'perplexity']],
        ['key' => 'gemini', 'label' => 'Gemini / Bard', 'needles' => ['gemini.google.com', 'bard.google.com', 'gemini', 'bard']],
        ['key' => 'copilot', 'label' => 'Microsoft Copilot', 'needles' => ['copilot.microsoft.com', 'copilot', 'bing chat']],
        ['key' => 'claude', 'label' => 'Claude / Anthropic', 'needles' => ['claude.ai', 'anthropic.com', 'claude']],
        ['key' => 'poe', 'label' => 'Poe', 'needles' => ['poe.com', 'poe']],
        ['key' => 'you', 'label' => 'You.com', 'needles' => ['you.com']],
        ['key' => 'phind', 'label' => 'Phind', 'needles' => ['phind.com', 'phind']],
        ['key' => 'consensus', 'label' => 'Consensus', 'needles' => ['consensus.app', 'consensus']],
        ['key' => 'meta_ai', 'label' => 'Meta AI', 'needles' => ['meta.ai', 'meta ai']],
        ['key' => 'grok', 'label' => 'Grok / xAI', 'needles' => ['grok', 'x.ai']],
        ['key' => 'mistral', 'label' => 'Mistral / Le Chat', 'needles' => ['chat.mistral.ai', 'mistral', 'le chat']],
    ];
}

function ga4_first_existing_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }
    return '';
}

function list_ga4_referral_imports(string $projectId, int $limit = 8): array
{
    $projectId = visibility_safe_id($projectId);
    $files = glob(ga4_referral_imports_dir() . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $imports = [];
    foreach ($files as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && (string) ($decoded['project_id'] ?? '') === $projectId) {
            $imports[] = $decoded;
        }
        if (count($imports) >= $limit) {
            break;
        }
    }

    return $imports;
}

function latest_ga4_referral_import(string $projectId): ?array
{
    $imports = list_ga4_referral_imports($projectId, 1);
    return $imports[0] ?? null;
}
