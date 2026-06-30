<?php
/**
 * Mentett auditriport törlése.
 *
 * A riportok a /data/reports könyvtárban vannak, ezért csak bejelentkezett
 * felhasználó törölhet kontrollált API-végponton keresztül.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

$id = (string) ($_POST['id'] ?? '');
if ($id === '') {
    json_response(['ok' => false, 'message' => 'Hiányzó riport azonosító.'], 400);
}

if (!delete_report($id)) {
    json_response(['ok' => false, 'message' => 'A riport nem található vagy nem törölhető.'], 404);
}

json_response(['ok' => true, 'message' => 'Riport törölve.']);
