<?php
/**
 * Letölthető kutatási kontroll riport.
 *
 * A felület szándékosan nem futtat élő OpenAI-kérést ehhez a részhez. Ez a
 * végpont a beépített, karbantartott módszertani összevetést adja le
 * Markdown formátumban, amely könnyen olvasható, archiválható vagy tovább
 * szerkeszthető.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/methodology.php';

auth_require_download();

$review = aio_research_review();
$sources = aio_methodology_sources();
$filename = 'aio-modszertani-osszevetes-' . date('Y-m-d') . '.md';

$lines = [
    '# AIO módszertani összevetés',
    '',
    'Utolsó beépített ellenőrzés: ' . ($review['last_checked'] ?? 'ismeretlen'),
    'Export dátuma: ' . date('Y-m-d H:i'),
    '',
    '## Rövid verdikt',
    '',
    (string) ($review['summary'] ?? ''),
    '',
    '## Frissítendő vagy vitatható pontok',
    '',
];

foreach (($review['updates'] ?? []) as $index => $item) {
    $lines[] = '### ' . ($index + 1) . '. ' . ($item['topic'] ?? 'Módszertani pont');
    $lines[] = '';
    $lines[] = '- Státusz: ' . ($item['status'] ?? 'Nincs megadva');
    $lines[] = '- A jelenlegi segédanyag állítása: ' . ($item['document_claim'] ?? '');
    $lines[] = '- Friss nézőpont: ' . ($item['current_view'] ?? '');
    $lines[] = '- Javasolt módosítás: ' . ($item['recommendation'] ?? '');
    $lines[] = '- Forrás: ' . ($item['source'] ?? '');
    $lines[] = '';
}

$lines[] = '## Beépítendő változtatások';
$lines[] = '';
foreach (($review['actionable_changes'] ?? []) as $change) {
    $lines[] = '- ' . $change;
}

$lines[] = '';
$lines[] = '## Hivatkozott módszertani források';
$lines[] = '';
foreach ($sources as $source) {
    $lines[] = '- ' . ($source['title'] ?? 'Forrás') . ': ' . ($source['url'] ?? '');
    if (!empty($source['note'])) {
        $lines[] = '  ' . $source['note'];
    }
}

header('Content-Type: text/markdown; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo implode("\n", $lines) . "\n";
