<?php
/**
 * Audit progress állapot lekérdezése.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'message' => 'Csak GET kérés engedélyezett.'], 405);
}

auth_require_json();

$jobId = (string) ($_GET['job'] ?? '');
json_response(progress_read($jobId));
