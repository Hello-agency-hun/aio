<?php
/**
 * Egy mentett AI láthatósági futás törlése.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

$runId = (string) ($_POST['run_id'] ?? '');
$projectId = (string) ($_POST['project_id'] ?? '');

if ($runId === '' || $projectId === '') {
    json_response(['ok' => false, 'message' => 'Hiányzó futás vagy projekt azonosító.'], 400);
}

if (!delete_visibility_run($runId, $projectId)) {
    json_response(['ok' => false, 'message' => 'A visibility futás nem törölhető vagy nem található.'], 404);
}

$project = read_visibility_project($projectId);
$runs = list_visibility_runs($projectId, 24);
if ($project) {
    $project['latest_run'] = $runs[0] ?? null;
}

json_response([
    'ok' => true,
    'project' => $project,
    'runs' => $runs,
    'message' => 'Visibility futás törölve.',
]);
