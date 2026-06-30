<?php
/**
 * AI láthatósági projektek és legutóbbi futások listázása.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visibility.php';
require_once __DIR__ . '/../includes/ga4_referrals.php';
require_once __DIR__ . '/../includes/serp_aio_imports.php';
require_once __DIR__ . '/../includes/gemini_grounding.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'message' => 'Csak GET kérés engedélyezett.'], 405);
}

auth_require_json();

$projects = list_visibility_projects(30);
foreach ($projects as $index => $project) {
    $projects[$index]['latest_ga4_import'] = latest_ga4_referral_import((string) ($project['id'] ?? ''));
    $projects[$index]['latest_serp_aio_import'] = latest_serp_aio_import((string) ($project['id'] ?? ''));
    $projects[$index]['latest_gemini_grounding_run'] = latest_gemini_grounding_run((string) ($project['id'] ?? ''));
}

json_response([
    'ok' => true,
    'projects' => $projects,
]);
