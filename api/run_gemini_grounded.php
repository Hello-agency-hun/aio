<?php
/**
 * Gemini Google Search grounding alapú visibility próba futtatása.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini_grounding.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $projectId = (string) ($_POST['project_id'] ?? '');
    $queryLimit = (int) ($_POST['query_limit'] ?? 3);
    $usePortfolio = ($_POST['use_portfolio'] ?? '0') === '1';

    $run = run_gemini_grounded_visibility_project($projectId, $queryLimit, $usePortfolio);

    json_response([
        'ok' => true,
        'run' => $run,
        'runs' => list_gemini_grounding_runs($projectId, 8),
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
