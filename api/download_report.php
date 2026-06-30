<?php
/**
 * Mentett riport letöltése JSON fájlként.
 *
 * A riportok nem közvetlenül a böngészőből érhetők el, hanem ezen a
 * biztonságos, azonosító alapján dolgozó végponton keresztül.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';

auth_require_download();

$id = (string) ($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo 'Hiányzó riport azonosító.';
    exit;
}

$report = read_report($id);
if (!$report) {
    http_response_code(404);
    echo 'A riport nem található.';
    exit;
}

$safeId = preg_replace('~[^a-zA-Z0-9_\-]~', '', (string) ($report['id'] ?? $id));
$filename = $safeId !== '' ? $safeId . '.json' : 'aio-riport.json';

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
