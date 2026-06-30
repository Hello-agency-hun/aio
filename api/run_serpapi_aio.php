<?php
/**
 * SerpApi alapú élő Google SERP / AI Overview lekérdezés.
 *
 * A végpont a mentett visibility projekt query készletéből futtat néhány
 * ellenőrző keresést, majd az eredményt ugyanabba a SERP/AIO bizonyíték
 * formátumba menti, mint a kézi JSON/CSV import.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/serp_aio_imports.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $projectId = (string) ($_POST['project_id'] ?? '');
    $queryLimit = (int) ($_POST['query_limit'] ?? 3);
    $usePortfolio = ($_POST['use_portfolio'] ?? '0') === '1';

    $import = serp_aio_run_serpapi_project($projectId, $queryLimit, $usePortfolio);

    json_response([
        'ok' => true,
        'import' => $import,
        'imports' => list_serp_aio_imports($projectId, 8),
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
