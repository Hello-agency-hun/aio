<?php
/**
 * Egy AI láthatósági projekt részletei és futáselőzményei.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility.php';
require_once __DIR__ . '/../includes/ga4_referrals.php';
require_once __DIR__ . '/../includes/serp_aio_imports.php';
require_once __DIR__ . '/../includes/gemini_grounding.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'message' => 'Csak GET kérés engedélyezett.'], 405);
}

auth_require_json();

$projectId = (string) ($_GET['id'] ?? '');
if ($projectId === '') {
    json_response(['ok' => false, 'message' => 'Hiányzó projekt azonosító.'], 400);
}

$project = read_visibility_project($projectId);
if (!$project) {
    json_response(['ok' => false, 'message' => 'A láthatósági projekt nem található.'], 404);
}

$runs = list_visibility_runs($projectId, 24);
$ga4Imports = list_ga4_referral_imports($projectId, 8);
$serpAioImports = list_serp_aio_imports($projectId, 8);
$geminiGroundingRuns = list_gemini_grounding_runs($projectId, 8);
$project['latest_run'] = $runs[0] ?? null;
$project['latest_ga4_import'] = $ga4Imports[0] ?? null;
$project['latest_serp_aio_import'] = $serpAioImports[0] ?? null;
$project['latest_gemini_grounding_run'] = $geminiGroundingRuns[0] ?? null;

json_response([
    'ok' => true,
    'project' => $project,
    'runs' => $runs,
    'ga4_imports' => $ga4Imports,
    'serp_aio_imports' => $serpAioImports,
    'gemini_grounding_runs' => $geminiGroundingRuns,
]);
