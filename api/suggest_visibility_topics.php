<?php
/**
 * AI témasegéd végpont.
 *
 * Mentés előtt is használható: az aktuális űrlapadatokból javasol AI
 * láthatósági mérési témákat. Ha nincs élő AI provider vagy olvasható
 * weboldal-kontekstus, hibát adunk vissza, nem találgatunk sablonból.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility_topic_suggestions.php';

if (function_exists('set_time_limit')) {
    @set_time_limit(max(180, OPENAI_REQUEST_TIMEOUT + 60));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

try {
    $result = suggest_visibility_topics($_POST);
    json_response([
        'ok' => true,
        'source' => $result['source'],
        'message' => $result['message'],
        'profile' => $result['profile'],
        'context' => $result['context'],
        'suggestions' => $result['suggestions'],
        'topics' => $result['topics'],
    ]);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 400);
}
