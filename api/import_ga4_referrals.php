<?php
/**
 * GA4 AI referral CSV import mentése egy visibility projekthez.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ga4_referrals.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $projectId = (string) ($_POST['project_id'] ?? '');
    $csv = trim((string) ($_POST['ga4_csv_text'] ?? ''));
    $filename = '';

    if ($csv === '' && isset($_FILES['ga4_csv_file']) && is_uploaded_file($_FILES['ga4_csv_file']['tmp_name'])) {
        $filename = (string) ($_FILES['ga4_csv_file']['name'] ?? '');
        $size = (int) ($_FILES['ga4_csv_file']['size'] ?? 0);
        if ($size > 2_000_000) {
            throw new RuntimeException('A GA4 CSV túl nagy. Maximum 2 MB-os exportot tölts fel.');
        }
        $csv = (string) file_get_contents($_FILES['ga4_csv_file']['tmp_name']);
    }

    if ($csv === '') {
        throw new RuntimeException('Másold be vagy töltsd fel a GA4 CSV exportot.');
    }

    $import = ga4_save_referral_import($projectId, $csv, $filename);

    json_response([
        'ok' => true,
        'import' => $import,
        'imports' => list_ga4_referral_imports($projectId, 8),
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
