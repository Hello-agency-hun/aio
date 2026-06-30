<?php
/**
 * JSON alapú tárolási réteg.
 *
 * A shared hosting kompatibilitás miatt nincs adatbázis-függőség. Minden
 * riport külön JSON fájlba kerül, a mentés pedig LOCK_EX zárolással történik,
 * hogy párhuzamos kéréseknél se sérüljön a fájl.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function ensure_storage(): void
{
    if (!is_dir(REPORT_DIR)) {
        mkdir(REPORT_DIR, 0755, true);
    }
}

function save_report(array $report): string
{
    ensure_storage();

    $id = $report['id'] ?? ('report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)));
    $report['id'] = $id;
    $file = REPORT_DIR . '/' . preg_replace('~[^a-zA-Z0-9_\-]~', '', $id) . '.json';

    file_put_contents(
        $file,
        json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    return $id;
}

function report_file_path(string $id): string
{
    $safeId = preg_replace('~[^a-zA-Z0-9_\-]~', '', $id);
    return REPORT_DIR . '/' . $safeId . '.json';
}

function read_report(string $id): ?array
{
    ensure_storage();

    $file = report_file_path($id);
    if (!is_file($file)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

function delete_report(string $id): bool
{
    ensure_storage();

    $file = report_file_path($id);
    if (!is_file($file)) {
        return false;
    }

    return @unlink($file);
}

function list_reports(int $limit = 20): array
{
    ensure_storage();

    $files = glob(REPORT_DIR . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $reports = [];
    foreach (array_slice($files, 0, $limit) as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $reports[] = [
                'id' => $decoded['id'] ?? basename($file, '.json'),
                'url' => $decoded['url'] ?? '',
                'created_at' => $decoded['created_at'] ?? '',
                'overall_score' => $decoded['overall_score'] ?? 0,
                'status' => $decoded['status'] ?? 'completed',
            ];
        }
    }

    return $reports;
}
