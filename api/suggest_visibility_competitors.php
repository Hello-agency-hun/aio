<?php
/**
 * Versenytárssegéd végpont az AI láthatósági mérési profilhoz.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility_competitor_suggestions.php';

if (function_exists('set_time_limit')) {
    @set_time_limit(max(240, OPENAI_REQUEST_TIMEOUT + 90));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $result = suggest_visibility_competitors($_POST);
    json_response([
        'ok' => true,
        'source' => $result['source'],
        'message' => $result['message'],
        'profile' => $result['profile'],
        'search_evidence' => $result['search_evidence'],
        'suggestions' => $result['suggestions'],
        'domains' => $result['domains'],
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
