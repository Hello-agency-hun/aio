<?php
/**
 * Korábbi audit session/progress fájlok törlése.
 *
 * Ez nem töröl riportot és nem törli a belépési sessiont. Csak a /data/progress
 * könyvtár technikai állapotfájljait üríti, amelyek a futó vagy lezárt auditok
 * progress bar állapotát tárolják.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

$deleted = progress_delete_all();
json_response([
    'ok' => true,
    'deleted' => $deleted,
    'message' => $deleted > 0
        ? $deleted . ' korábbi session/progress fájl törölve.'
        : 'Nem volt törölhető korábbi session/progress fájl.',
]);
