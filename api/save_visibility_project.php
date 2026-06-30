<?php
/**
 * AI láthatósági projekt mentése.
 *
 * A projekt tartalmazza a vizsgált domaint, a fő témákat és az ismert
 * versenytársakat. Ezekből épül fel a későbbi kérdéssoros mérés.
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
    $project = visibility_save_project($_POST);
    json_response(['ok' => true, 'project' => $project]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
