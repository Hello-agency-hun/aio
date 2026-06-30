<?php
/**
 * AI keresési láthatóságmérés futtatása egy mentett projektre.
 *
 * A futás nem írja felül az auditriportokat: külön /data/visibility_runs JSON
 * állományba kerül, így később trendként visszanézhető.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility.php';
require_once __DIR__ . '/../includes/visibility_ai.php';
require_once __DIR__ . '/../includes/progress.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

$projectId = (string) ($_POST['project_id'] ?? '');
$queryLimitInput = $_POST['query_limit'] ?? null;
$jobId = progress_job_id((string) ($_POST['job_id'] ?? bin2hex(random_bytes(8))));
$includeAi = ($_POST['include_ai'] ?? '1') !== '0';
$runMode = (string) ($_POST['run_mode'] ?? 'generated');
$progress = static function (int $percent, string $phase, string $detail = '') use ($jobId): void {
    progress_write($jobId, $percent, $phase, $detail);
};

try {
    $progress(4, 'Visibility mérés indítása', 'A kérés megérkezett, a projekt betöltése indul.');

    if ($projectId === '') {
        throw new RuntimeException('Hiányzó projekt azonosító.');
    }

    $project = read_visibility_project($projectId);
    if (!$project) {
        throw new RuntimeException('A láthatósági projekt nem található.');
    }

    $queryLimit = max(4, min(20, (int) ($queryLimitInput ?? ($project['query_limit'] ?? 12))));
    if ($runMode === 'weekly_portfolio' && empty($project['query_portfolio'])) {
        throw new RuntimeException('A heti Top 20 futtatáshoz előbb ments rögzített query portfóliót a projektben.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(120 + ($queryLimit * 30) + OPENAI_REQUEST_TIMEOUT);
    }

    $run = run_visibility_tracking($project, $queryLimit, $progress, ['run_mode' => $runMode]);
    if ($includeAi) {
        $run['ai_strategy'] = enrich_visibility_run_with_ai($project, $run, $progress);
        $run['resource_summary'] = visibility_build_resource_summary($run);
        $run['report_layers'] = visibility_build_report_layers($project, $run);
        $progress(95, 'AI stratégia mentése', 'Az értelmező összefoglaló hozzáadása a visibility futáshoz.');
        save_visibility_run($run);
    } else {
        $run['ai_strategy'] = [
            'enabled' => false,
            'status' => 'skipped',
            'message' => 'AI stratégiai értelmezés kihagyva a kérés alapján.',
        ];
        $run['resource_summary'] = visibility_build_resource_summary($run);
        $run['report_layers'] = visibility_build_report_layers($project, $run);
        save_visibility_run($run);
    }

    $progress(100, 'Visibility mérés elkészült', 'A keresési mérés és az értelmezés lezárult.');
    json_response(['ok' => true, 'project' => $project, 'run' => $run, 'runs' => list_visibility_runs($projectId, 12)]);
} catch (Throwable $exception) {
    $progress(100, 'Visibility mérési hiba', $exception->getMessage());
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
