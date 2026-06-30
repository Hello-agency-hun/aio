<?php
/**
 * AI láthatósági kérdésportfólió előnézete.
 *
 * Nem ment projektet és nem futtat keresési providert. Csak megmutatja, milyen
 * kérdésekből állna a mérési csomag az aktuális űrlapbeállítások alapján.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $project = visibility_project_from_input($_POST);
    $limit = max(4, min(20, (int) ($_POST['query_limit'] ?? ($project['query_limit'] ?? 12))));
    $usePortfolio = ($_POST['run_mode'] ?? '') === 'weekly_portfolio';
    $queries = build_visibility_query_set($project, $limit, $usePortfolio);

    json_response([
        'ok' => true,
        'project' => $project,
        'query_limit' => $limit,
        'run_mode' => $usePortfolio ? 'weekly_portfolio' : 'generated',
        'portfolio_used' => $usePortfolio,
        'query_count' => count($queries),
        'queries' => $queries,
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
