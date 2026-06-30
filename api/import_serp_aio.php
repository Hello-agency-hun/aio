<?php
/**
 * Google SERP / AI Overview provider export import mentése.
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
    $raw = trim((string) ($_POST['serp_aio_text'] ?? ''));
    $filename = '';

    if ($raw === '' && isset($_FILES['serp_aio_file']) && is_uploaded_file($_FILES['serp_aio_file']['tmp_name'])) {
        $filename = (string) ($_FILES['serp_aio_file']['name'] ?? '');
        $size = (int) ($_FILES['serp_aio_file']['size'] ?? 0);
        if ($size > 4_000_000) {
            throw new RuntimeException('A SERP/AIO export túl nagy. Maximum 4 MB-os fájlt tölts fel.');
        }
        $raw = (string) file_get_contents($_FILES['serp_aio_file']['tmp_name']);
    }

    if ($raw === '') {
        throw new RuntimeException('Másold be vagy töltsd fel a SERP/AIO JSON vagy CSV exportot.');
    }

    $import = serp_aio_save_import($projectId, $raw, $filename);

    json_response([
        'ok' => true,
        'import' => $import,
        'imports' => list_serp_aio_imports($projectId, 8),
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
