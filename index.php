<?php
/**
 * AIO Audit Studio fő felület.
 *
 * Az oldal PHP-val rendereli az induló állapotot és a korábbi riportok listáját,
 * az új audit futtatása pedig JavaScript/Ajax segítségével történik. Így az app
 * shared hostingon is egyszerűen telepíthető, mégis modern, gyors felhasználói
 * élményt ad.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/methodology.php';
require_once __DIR__ . '/includes/visibility.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['auth_action'] ?? '') === 'login') {
    auth_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['auth_action'] ?? '') === 'logout') {
    auth_logout();
    header('Location: ./');
    exit;
}

$reports = list_reports(12);
$assetFiles = [
    __DIR__ . '/assets/css/style.css',
    __DIR__ . '/assets/js/app.js',
    __DIR__ . '/assets/js/three-scene.js',
    __DIR__ . '/assets/img/hello-ai-audit-user-journey.png',
];
$assetVersion = (string) max(array_map(
    static fn (string $file): int => is_file($file) ? (int) filemtime($file) : time(),
    $assetFiles
));
$isAuthenticated = auth_is_logged_in();
$loginError = $isAuthenticated ? '' : auth_login_error();
$visibilityProjects = $isAuthenticated ? list_visibility_projects(8) : [];
?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AIO és SEO audit eszköz weboldalak AI keresési optimalizálásához.">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= e($assetVersion) ?>">
</head>
<body>
    <canvas id="helloThreeCanvas" aria-hidden="true"></canvas>
    <?php if (!$isAuthenticated): ?>
    <main class="login-screen">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand">
                <img src="assets/img/hello_ai_audit_logo_transparent.png" alt="Hello AI Audit">
            </div>
            <div>
                <h1 id="login-title">Belépés</h1>
                <p>Privát auditfelület. Jelentkezz be, és indulhat az elemzés.</p>
            </div>
            <?php if ($loginError !== ''): ?>
                <div class="login-error" role="alert"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form class="login-form" method="post" autocomplete="off">
                <input type="hidden" name="auth_action" value="login">
                <label for="username">Felhasználónév</label>
                <input id="username" name="username" type="text" required autofocus>
                <label for="password">Jelszó</label>
                <input id="password" name="password" type="password" required>
                <button type="submit">Belépés</button>
            </form>
        </section>
    </main>
    <?php else: ?>
    <div class="app-shell">
        <aside class="sidebar" aria-label="Navigáció">
            <a class="brand" href="./" aria-label="<?= e(APP_NAME) ?> kezdőlap">
                <span class="brand-mark">
                    <img src="assets/img/hello_ai_audit_logo_transparent.png" alt="Hello AI Audit">
                </span>
                <span>
                    <strong>Hello AI Audit</strong>
                    <small>AI keresés. Átlátható döntések.</small>
                </span>
            </a>

            <nav class="nav-list">
                <a class="nav-item active" href="#audit" data-view="audit">Audit indítása</a>
                <a class="nav-item" href="#visibility-monitor" data-view="visibility">AI láthatóság</a>
                <a class="nav-item" href="#reports" data-view="reports">Riportok</a>
                <a class="nav-item" href="#glossary-guide" data-view="glossary">Szótár</a>
                <a class="nav-item" href="#user-guide" data-view="guide">Útmutató</a>
            </nav>
            <form class="logout-form" method="post">
                <input type="hidden" name="auth_action" value="logout">
                <button type="submit">Kijelentkezés</button>
            </form>
        </aside>

        <main class="main">
            <section class="topbar">
                <div>
                    <h1>Hello AI Audit</h1>
                    <p>AI keresési és UX audit átlátható döntésekhez. Nem csak hibákat listázunk: megmutatjuk, mi a probléma, miért gond, és milyen első lépéssel érdemes javítani.</p>
                </div>
                <div class="version">v<?= e(APP_VERSION) ?></div>
            </section>

            <section id="audit" class="audit-panel" data-view-panel="audit" aria-labelledby="audit-title">
                <div class="panel-head">
                    <div>
                        <h2 id="audit-title">Új audit</h2>
                        <p>A fő domain teljes rendszeréről készítünk képet. Itt csak azt állítod be, hány kiemelt URL kap teljes, oldalszintű elemzést; a rendszer közben sitemapból, navigációból és belső linkekből építi fel a domain térképét.</p>
                    </div>
                    <span class="status-pill" id="appStatus">Készen áll</span>
                </div>

                <form class="url-form" id="auditForm" autocomplete="off">
                    <label for="urlInput">Vizsgálandó URL</label>
                    <div class="input-row">
                        <input id="urlInput" name="url" type="text" inputmode="url" placeholder="www.pelda.hu vagy https://pelda.hu" required>
                        <button type="submit" id="submitButton">Audit indítása</button>
                    </div>
                    <p class="form-help">Elég a domain is, például www.pelda.hu. Ha nincs megadva protokoll, automatikusan https:// előtagot használunk.</p>
                    <fieldset class="crawl-options">
                        <legend>Részletes elemzés kerete</legend>
                        <label class="depth-card">
                            <input type="radio" name="crawl_mode" value="quick" data-limit="20">
                            <span>
                                <strong>Gyors kép</strong>
                                <small>20 kiemelt URL teljes elemzése gyors előszűréshez.</small>
                            </span>
                        </label>
                        <label class="depth-card">
                            <input type="radio" name="crawl_mode" value="smart" data-limit="<?= e((string) DEFAULT_CRAWL_PAGES) ?>" checked>
                            <span>
                                <strong>Okos térkép</strong>
                                <small><?= e((string) DEFAULT_CRAWL_PAGES) ?> priorizált URL sitemap + navigáció alapján.</small>
                            </span>
                        </label>
                        <label class="depth-card">
                            <input type="radio" name="crawl_mode" value="deep" data-limit="<?= e((string) MAX_CRAWL_PAGES) ?>">
                            <span>
                                <strong>Nagy webhely</strong>
                                <small>Akár <?= e((string) MAX_CRAWL_PAGES) ?> URL nagyobb domainhez.</small>
                            </span>
                        </label>
                        <label class="depth-card custom-depth">
                            <input type="radio" name="crawl_mode" value="custom" data-limit="<?= e((string) DEFAULT_CRAWL_PAGES) ?>">
                            <span>
                                <strong>Egyedi</strong>
                                <small><input id="crawlLimitInput" type="number" min="10" max="<?= e((string) MAX_CRAWL_PAGES) ?>" value="<?= e((string) DEFAULT_CRAWL_PAGES) ?>"> URL teljes elemzése</small>
                            </span>
                        </label>
                    </fieldset>
                </form>

                <div class="progress-wrap hidden" id="progressWrap" aria-live="polite">
                    <div class="progress-track"><span id="progressBar"></span></div>
                    <span id="progressText">Oldalak letöltése...</span>
                </div>

                <div class="quick-steps" aria-label="Audit folyamat röviden">
                    <article>
                        <span>1</span>
                        <strong>URL megadása</strong>
                        <p>A kezdőoldalból indulunk, majd belső linkeken keresztül bővítjük a mintát.</p>
                    </article>
                    <article>
                        <span>2</span>
                        <strong>AI jelek mérése</strong>
                        <p>Technikai, tartalmi, UX, strukturált adat és bizalmi jeleket nézünk együtt.</p>
                    </article>
                    <article>
                        <span>3</span>
                        <strong>Javítási terv</strong>
                        <p>Probléma, miért probléma, első lépés szerinti teendőket, llms.txt-et és PDF riportot kapsz.</p>
                    </article>
                </div>
            </section>

            <section id="visibility-monitor" class="visibility-monitor-section view-hidden" data-view-panel="visibility" aria-labelledby="visibility-monitor-title">
                <div class="section-title">
                    <div>
                        <h2 id="visibility-monitor-title">AI láthatóságmérés</h2>
                        <p>Ismételhető mérés témákra és versenytársakra. A rendszer több vevői kérdésvariánst futtat keresési providereken, majd megmutatja, milyen arányban látszik a saját domain és kik uralják a találati teret.</p>
                    </div>
                    <span class="status-pill" id="visibilityStatus">Mérési profil készen</span>
                </div>

                <div class="visibility-monitor-layout">
                    <form class="visibility-project-form" id="visibilityProjectForm" autocomplete="off">
                        <input type="hidden" id="visibilityProjectId" name="id" value="">
                        <label for="visibilityName">Projekt neve</label>
                        <input id="visibilityName" name="name" type="text" placeholder="Pl. Hello Agency visibility">

                        <label for="visibilityUrl">Saját domain vagy URL</label>
                        <input id="visibilityUrl" name="site_url" type="text" inputmode="url" placeholder="www.pelda.hu" required>

                        <div class="visibility-inline-fields">
                            <div>
                                <label for="visibilityMarket">Piac</label>
                                <input id="visibilityMarket" name="market" type="text" value="Magyarország">
                            </div>
                            <div>
                                <label for="visibilityLanguage">Nyelv</label>
                                <input id="visibilityLanguage" name="language" type="text" value="hu">
                            </div>
                        </div>

                        <div class="visibility-inline-fields model-fields">
                            <div>
                                <label for="visibilityBusinessModel">Üzleti modell</label>
                                <select id="visibilityBusinessModel" name="business_model">
                                    <option value="b2b_service">B2B szolgáltatás</option>
                                    <option value="local_service">Helyi szolgáltatás</option>
                                    <option value="ecommerce">E-commerce</option>
                                    <option value="saas">SaaS / szoftver</option>
                                    <option value="expert_brand">Szakértői brand</option>
                                    <option value="generic">Általános weboldal</option>
                                </select>
                            </div>
                            <div>
                                <label for="visibilityQueryLimit">Kérdésszám</label>
                                <input id="visibilityQueryLimit" name="query_limit" type="number" min="4" max="20" value="12">
                            </div>
                        </div>

                        <label for="visibilityTopics">Témák, vevői kérdéskörök</label>
                        <textarea id="visibilityTopics" name="topics" rows="5" placeholder="AI keresési optimalizálás&#10;weboldal audit&#10;B2B leadgenerálás" required></textarea>

                        <label for="visibilityCustomQueries">Saját mérési kérdések</label>
                        <textarea id="visibilityCustomQueries" name="custom_queries" rows="4" placeholder="Melyik ügynökség segít AI keresési láthatóságot javítani?&#10;Milyen AIO audit eszközt érdemes használni?"></textarea>

                        <label for="visibilityQueryPortfolio">Heti Top 20 query portfólió</label>
                        <textarea id="visibilityQueryPortfolio" name="query_portfolio" rows="6" placeholder="buyer: Melyik AI audit eszközt érdemes használni B2B weboldalhoz?&#10;comparison: Hello AI Audit alternatívák&#10;pricing: Mennyibe kerül egy AIO audit?"></textarea>

                        <label for="visibilityCompetitors">Versenytárs domainek</label>
                        <textarea id="visibilityCompetitors" name="competitors" rows="4" placeholder="pelda-versenytars.hu&#10;masikceg.hu"></textarea>

                        <div class="visibility-actions">
                            <button type="submit" class="mini-button" id="saveVisibilityProjectButton">Projekt mentése</button>
                            <button type="button" class="mini-button secondary" id="previewVisibilityQueriesButton">Kérdések előnézete</button>
                            <button type="button" class="mini-button secondary" id="previewPortfolioButton">Top 20 előnézet</button>
                            <button type="button" class="mini-button secondary" id="runVisibilityButton" disabled>Mérés futtatása</button>
                            <button type="button" class="mini-button secondary" id="runWeeklyPortfolioButton" disabled>Heti Top 20 futtatása</button>
                            <button type="button" class="mini-button secondary" id="exportVisibilityPdfButton" disabled>Visibility PDF</button>
                        </div>
                        <div class="visibility-query-preview hidden" id="visibilityQueryPreview"></div>
                        <div class="visibility-progress hidden" id="visibilityProgressWrap" aria-live="polite">
                            <div class="progress-track"><span id="visibilityProgressBar"></span></div>
                            <span id="visibilityProgressText">Visibility mérés előkészítése...</span>
                        </div>
                        <p class="form-help">A heti Top 20 portfólió rögzített kérdéssor: akkor használd, ha ugyanazokat a kérdéseket akarod hétről hétre újramérni. Kategória formátum: buyer:, comparison:, competitor:, pricing:, local:, expert:.</p>
                    </form>

                    <article class="ga4-import-panel" id="ga4ImportPanel">
                        <div class="recommendation-head">
                            <div>
                                <span>GA4 AI referral import</span>
                                <h3>Valódi AI referral forgalom</h3>
                                <p>Exportálj GA4-ből source / medium vagy referrer alapú CSV-t, majd töltsd fel vagy másold be. A rendszer felismeri a ChatGPT, Perplexity, Gemini, Copilot, Claude és hasonló AI forrásokat.</p>
                            </div>
                            <button class="mini-button secondary" type="button" id="importGa4Button" disabled>GA4 import</button>
                        </div>
                        <div class="ga4-import-grid">
                            <label for="ga4CsvFile">CSV fájl</label>
                            <input id="ga4CsvFile" type="file" accept=".csv,text/csv,text/plain">
                            <label for="ga4CsvText">Vagy CSV tartalom</label>
                            <textarea id="ga4CsvText" rows="5" placeholder="Session source / medium,Sessions,Total users,Key events&#10;chatgpt.com / referral,12,9,2&#10;perplexity.ai / referral,4,3,0"></textarea>
                        </div>
                        <div class="ga4-import-result" id="ga4ImportResult">
                            <p class="empty-state">Ments vagy nyiss meg egy visibility projektet, majd importáld a GA4 exportot.</p>
                        </div>
                    </article>

                    <article class="serp-import-panel" id="serpAioImportPanel">
                        <div class="recommendation-head">
                            <div>
                                <span>Google SERP / AIO import</span>
                                <h3>AI Overview és SERP bizonyíték</h3>
                                <p>SerpApi, DataForSEO vagy kézi SERP export JSON/CSV. A rendszer kinyeri, volt-e AI Overview, idézte-e a saját domaint, és milyen versenytárs források szerepeltek.</p>
                            </div>
                            <div class="serp-action-group">
                                <button class="mini-button secondary" type="button" id="runGeminiGroundedButton" disabled>Gemini grounded próba</button>
                                <button class="mini-button secondary" type="button" id="runSerpApiButton" disabled>SerpApi live próba</button>
                                <button class="mini-button secondary" type="button" id="importSerpAioButton" disabled>SERP/AIO import</button>
                            </div>
                        </div>
                        <div class="ga4-import-grid">
                            <label for="serpAioFile">JSON vagy CSV fájl</label>
                            <input id="serpAioFile" type="file" accept=".json,.csv,application/json,text/csv,text/plain">
                            <label for="serpAioText">Vagy export tartalom</label>
                            <textarea id="serpAioText" rows="5" placeholder='{"search_parameters":{"q":"legjobb AI audit eszköz"},"ai_overview":{"sources":[{"link":"https://pelda.hu"}]},"organic_results":[{"link":"https://versenytars.hu"}]}'></textarea>
                        </div>
                        <div class="ga4-import-result" id="serpAioImportResult">
                            <p class="empty-state">Ments vagy nyiss meg egy visibility projektet, majd importáld a SERP/AIO exportot.</p>
                        </div>
                    </article>

                    <div class="visibility-dashboard" id="visibilityDashboard">
                        <article class="visibility-empty-state">
                            <h3>Először ments egy mérési profilt</h3>
                            <p>Add meg a saját domaint, 3-8 fontos témát és ismert versenytársakat. Utána egy gombbal futtatható a visibility mérés.</p>
                        </article>
                    </div>
                </div>

                <div class="visibility-project-list" id="visibilityProjectList">
                    <?php if (!$visibilityProjects): ?>
                        <p class="empty-state">Még nincs mentett AI láthatósági projekt.</p>
                    <?php endif; ?>
                    <?php foreach ($visibilityProjects as $project): ?>
                        <?php $latestRun = $project['latest_run'] ?? null; ?>
                        <article class="visibility-project-card" data-project='<?= e(json_encode($project, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                            <div>
                                <strong><?= e((string) ($project['name'] ?? $project['target_domain'])) ?></strong>
                                <small><?= e((string) ($project['target_domain'] ?? '')) ?> · <?= e((string) ($project['business_model_label'] ?? 'Általános weboldal')) ?> · <?= e((string) count($project['topics'] ?? [])) ?> téma · <?= e((string) count($project['query_portfolio'] ?? [])) ?> Top 20 kérdés</small>
                            </div>
                            <div class="history-actions">
                                <?php if ($latestRun): ?>
                                    <span><?= e((string) ($latestRun['visibility_rate'] ?? 0)) ?>%</span>
                                <?php endif; ?>
                                <button class="mini-button secondary visibility-load-project" type="button">Megnyitás</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="results-grid hidden" id="results" data-view-panel="audit" aria-live="polite">
                <article class="score-card">
                    <span class="card-label">Összpontszám</span>
                    <div class="score-ring" id="overallScore">0</div>
                    <h2 id="scoreLabel">Elemzés kész</h2>
                    <p id="scoreSummary">A riport összefoglalója itt jelenik meg.</p>
                </article>

                <article class="metrics-card">
                    <h2>Miben erős, miben gyenge?</h2>
                    <div class="metric-list" id="metricList"></div>
                </article>
            </section>

            <section class="tabs hidden" id="reportTabs" data-view-panel="audit">
                <div class="tabs-toolbar">
                    <div class="tab-buttons" role="tablist" aria-label="Riport nézetek">
                        <button class="tab-button active" type="button" data-tab="recommendations">Teendők</button>
                        <button class="tab-button" type="button" data-tab="ai">OpenAI elemzés</button>
                        <button class="tab-button" type="button" data-tab="visibility">AI keresési próba</button>
                        <button class="tab-button" type="button" data-tab="resources">Erőforrások</button>
                        <button class="tab-button" type="button" data-tab="pages">Oldalak</button>
                        <button class="tab-button" type="button" data-tab="signals">AIO jelek</button>
                        <button class="tab-button" type="button" data-tab="glossary">Szótár</button>
                    </div>
                    <button class="export-button" type="button" id="exportPdfButton">PDF riport</button>
                </div>

                <div class="tab-panel active" id="tab-recommendations"></div>
                <div class="tab-panel" id="tab-ai"></div>
                <div class="tab-panel" id="tab-visibility"></div>
                <div class="tab-panel" id="tab-resources"></div>
                <div class="tab-panel" id="tab-pages"></div>
                <div class="tab-panel" id="tab-signals"></div>
                <div class="tab-panel" id="tab-glossary"></div>
            </section>

            <section id="reports" class="history-section view-hidden" data-view-panel="reports">
                <div class="section-title">
                    <div>
                        <h2>Korábbi riportok</h2>
                        <p>A korábbi auditok visszatölthetők, letölthetők vagy törölhetők. A session takarítás csak a technikai progress fájlokat üríti.</p>
                    </div>
                    <button class="mini-button secondary history-cleanup" type="button" id="clearSessionsButton">Sessionök törlése</button>
                </div>
                <div class="history-list" id="historyList">
                    <?php if (!$reports): ?>
                        <p class="empty-state">Még nincs mentett riport. Indítsd el az első auditot.</p>
                    <?php endif; ?>
                    <?php foreach ($reports as $report): ?>
                        <article class="history-item" data-report-id="<?= e((string) $report['id']) ?>">
                            <div>
                                <strong><?= e((string) $report['url']) ?></strong>
                                <small><?= e((string) $report['created_at']) ?></small>
                            </div>
                            <div class="history-actions">
                                <span><?= e((string) $report['overall_score']) ?>/100</span>
                                <button class="mini-button secondary history-load" type="button" data-report-id="<?= e((string) $report['id']) ?>">Megnyitás</button>
                                <button class="mini-button secondary history-pdf" type="button" data-report-id="<?= e((string) $report['id']) ?>">PDF</button>
                                <a class="mini-button secondary" href="api/download_report.php?id=<?= e(rawurlencode((string) $report['id'])) ?>">JSON letöltés</a>
                                <button class="mini-button danger history-delete" type="button" data-report-id="<?= e((string) $report['id']) ?>">Törlés</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="glossary-guide" class="method-section glossary-guide-section view-hidden" data-view-panel="glossary">
                <div class="section-title">
                    <h2>AIO szótár és mini oktatóanyag</h2>
                    <p>Ha most találkozol először az AIO-val, innen érdemes indulni. A fogalmaknál találsz példát, konkrét javítási irányt és további olvasnivalót is.</p>
                </div>
                <div class="learning-path">
                    <article>
                        <span>1</span>
                        <strong>Először értsd meg a forrást</strong>
                        <p>Ki mondja, mit állít, és mi bizonyítja? Ez az entitásbizalom és bizonyítéknyelv alapja.</p>
                    </article>
                    <article>
                        <span>2</span>
                        <strong>Utána tedd idézhetővé</strong>
                        <p>A fontos szakaszok kapjanak rövid, önállóan érthető answer-first választ.</p>
                    </article>
                    <article>
                        <span>3</span>
                        <strong>Végül kapcsold össze gépileg</strong>
                        <p>Schema, @id, sameAs és tiszta belső linkek segítik az AI értelmezést.</p>
                    </article>
                </div>
                <div class="glossary-grid learning-glossary">
                    <?php foreach (aio_glossary() as $entry): ?>
                        <article class="glossary-card lesson-card">
                            <div class="lesson-card-head">
                                <span><?= e($entry['category'] ?? 'AIO') ?></span>
                                <h3><?= e($entry['term']) ?></h3>
                            </div>
                            <p><?= e($entry['short']) ?></p>
                            <details>
                                <summary>Mit jelent ez a gyakorlatban?</summary>
                                <div class="lesson-detail">
                                    <strong>Miért számít?</strong>
                                    <p><?= e($entry['why'] ?? $entry['details']) ?></p>
                                    <strong>Példa</strong>
                                    <p><?= e($entry['example'] ?? '') ?></p>
                                    <strong>Mit javíts?</strong>
                                    <p><?= e($entry['fix'] ?? '') ?></p>
                                    <?php if (!empty($entry['links'])): ?>
                                        <div class="lesson-links">
                                            <?php foreach ($entry['links'] as $link): ?>
                                                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="user-guide" class="method-section user-guide-section view-hidden" data-view-panel="guide">
                <div class="section-title">
                    <div>
                        <h2>Útmutató: hogyan használd a rendszert?</h2>
                        <p>Ez a gyors áttekintő végigvezet a teljes munkafolyamaton: projekt mentése, weboldal audit, performance és HTML ellenőrzés, AI visibility mérés, live bizonyítékok, backlog és ügyfélkész PDF riport.</p>
                    </div>
                    <span class="status-pill">8 lépés</span>
                </div>
                <div class="guide-helper-grid">
                    <article>
                        <span>Első alkalommal</span>
                        <strong>Projekt → Audit → Visibility</strong>
                        <p>Először ments projektet, futtasd a weboldal auditot, majd indíts AI láthatóságmérést a fontos témákra.</p>
                    </article>
                    <article>
                        <span>Bizonyítékhoz</span>
                        <strong>SerpApi, Gemini, GA4, Search</strong>
                        <p>A live kontrollok mutatják, hogy a láthatósági jel mögött milyen valós találati, AI-válasz és analitikai adat áll.</p>
                    </article>
                    <article>
                        <span>Ügyfélriporthoz</span>
                        <strong>Backlog → PDF export</strong>
                        <p>A javítási backlogból lesz a konkrét munkaterv, a PDF pedig vizuálisan átadható ügyfélanyag.</p>
                    </article>
                </div>
                <figure class="user-journey-figure">
                    <img src="assets/img/hello-ai-audit-user-journey.png?v=<?= e($assetVersion) ?>" alt="Hello AI Audit használati útmutató: belépés, projekt mentése, audit, performance, AI Visibility, live bizonyítékok, javítási backlog és PDF riport lépései" loading="lazy">
                    <figcaption>Javasolt útvonal: először mérj, utána gyűjts bizonyítékot, végül készíts javítási tervet és ügyfélriportot.</figcaption>
                </figure>
            </section>
        </main>
    </div>

    <template id="recommendationTemplate">
        <article class="recommendation">
            <div class="priority-dot"></div>
            <div>
                <div class="recommendation-head">
                    <h3></h3>
                    <span></span>
                </div>
                <p class="impact"></p>
                <p class="why"></p>
                <p class="fix"></p>
                <p class="next-step"></p>
                <small class="pages"></small>
            </div>
        </article>
    </template>

    <script type="application/json" id="glossaryDefaultsJson"><?= json_encode(aio_glossary(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="assets/js/app.js?v=<?= e($assetVersion) ?>" defer></script>
    <?php endif; ?>
    <script type="module" src="assets/js/three-scene.js?v=<?= e($assetVersion) ?>"></script>
</body>
</html>
