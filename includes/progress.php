<?php
/**
 * Egyszerű fájlalapú audit-progressz követés.
 *
 * Shared hostingon nincs szükség websoketre: az elemző végpont időnként JSON
 * állapotot ír a /data/progress könyvtárba, a frontend pedig ezt pollolja.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function progress_job_id(string $candidate): string
{
    $clean = preg_replace('~[^a-zA-Z0-9_-]~', '', $candidate) ?: '';

    return substr($clean, 0, 80);
}

function progress_dir(): string
{
    $dir = DATA_DIR . '/progress';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function progress_path(string $jobId): string
{
    return progress_dir() . '/' . progress_job_id($jobId) . '.json';
}

function progress_write(string $jobId, int $percent, string $phase, string $detail = '', array $extra = []): void
{
    $jobId = progress_job_id($jobId);
    if ($jobId === '') {
        return;
    }

    $payload = array_merge([
        'ok' => true,
        'job_id' => $jobId,
        'percent' => max(0, min(100, $percent)),
        'phase' => $phase,
        'detail' => $detail,
        'updated_at' => date(DATE_ATOM),
    ], $extra);

    @file_put_contents(progress_path($jobId), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function progress_read(string $jobId): array
{
    $jobId = progress_job_id($jobId);
    if ($jobId === '') {
        return ['ok' => false, 'message' => 'Hiányzó progress azonosító.'];
    }

    $path = progress_path($jobId);
    if (!is_file($path)) {
        return [
            'ok' => true,
            'job_id' => $jobId,
            'percent' => 5,
            'phase' => 'Várakozás',
            'detail' => 'Az audit folyamat előkészítése...',
        ];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'Sérült progress állapot.'];
}

function progress_delete_all(): int
{
    $files = glob(progress_dir() . '/*.json') ?: [];
    $deleted = 0;

    foreach ($files as $file) {
        if (is_file($file) && @unlink($file)) {
            $deleted++;
        }
    }

    return $deleted;
}
