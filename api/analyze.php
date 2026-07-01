<?php
/**
 * Ajax API végpont az audit futtatásához.
 *
 * Csak POST kérést fogad, JSON választ ad, és minden hibát kontrollált
 * üzenetté alakít. A tényleges auditot az includes/audit.php végzi.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/search_providers.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/openrouter.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/progress.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

auth_require_json();

$url = (string) ($_POST['url'] ?? '');
$crawlMode = (string) ($_POST['crawl_mode'] ?? 'smart');
$crawlLimit = (int) ($_POST['crawl_limit'] ?? DEFAULT_CRAWL_PAGES);
$crawlOptions = [
    'mode' => $crawlMode,
    'limit' => $crawlLimit,
];
$jobId = progress_job_id((string) ($_POST['job_id'] ?? bin2hex(random_bytes(8))));
$progress = static function (int $percent, string $phase, string $detail = '') use ($jobId): void {
    progress_write($jobId, $percent, $phase, $detail);
};
$crawlOptions['progress_callback'] = $progress;

$resolvedLimit = resolve_crawl_limit($crawlOptions);
if (function_exists('set_time_limit')) {
    @set_time_limit((REQUEST_TIMEOUT * $resolvedLimit) + (OPENAI_REQUEST_TIMEOUT * 3) + 480);
}

try {
    $progress(4, 'Audit indítása', 'A kérés megérkezett, a céloldal ellenőrzése indul.');
    $report = run_site_audit($url, $crawlOptions);
    $progress(64, 'Keresési adatgyűjtés', 'Mentett keresési találatok, saját-domain jelenlét és versenytársak gyűjtése.');
    $report['saved_search_probe'] = run_saved_search_probe($report, $progress);
    $progress(70, 'OpenAI elemzés', 'Részletes javítási terv készítése az auditadatokból és a mentett keresési adatokból.');
    $report['ai_enrichment'] = enrich_report_with_openai($report, $progress);
    $progress(90, 'OpenRouter kontroll', 'Gyors másodvélemény készítése ingyenes/olcsó modellen.');
    $report['openrouter_enrichment'] = enrich_report_with_openrouter($report);
    $progress(93, 'Gemini kontroll', 'Google/Gemini szemléletű kontrollvélemény készítése.');
    $report['gemini_enrichment'] = enrich_report_with_gemini($report);
    $progress(96, 'Riport mentése', 'JSON riport, előzmények és exportadatok mentése.');
    save_report($report);
    $progress(100, 'Riport elkészült', 'Az audit minden részegysége lezárult.');
    json_response(['ok' => true, 'report' => $report]);
} catch (Throwable $exception) {
    $progress(100, 'Hiba történt', $exception->getMessage());
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 400);
}
