<?php
/**
 * Mentett riport visszatöltése Ajax kéréshez.
 *
 * A /data mappa közvetlenül védett, ezért a felület ezen a kontrollált
 * végponton keresztül kérheti vissza a korábbi JSON riportokat.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'message' => 'Csak GET kérés engedélyezett.'], 405);
}

auth_require_json();

$id = (string) ($_GET['id'] ?? '');
if ($id === '') {
    json_response(['ok' => false, 'message' => 'Hiányzó riport azonosító.'], 400);
}

$report = read_report($id);
if (!$report) {
    json_response(['ok' => false, 'message' => 'A riport nem található.'], 404);
}

json_response(['ok' => true, 'report' => $report]);
