/**
 * Frontend vezérlés az AIO audit felülethez.
 *
 * A JavaScript csak a felhasználói élményt javítja: Ajax kérést küld,
 * állapotot jelez, majd a kapott JSON riportot rendereli. Az alkalmazás
 * alapvető működése továbbra is egyszerű PHP fájlstruktúrán marad.
 */

const form = document.querySelector('#auditForm');
const urlInput = document.querySelector('#urlInput');
const crawlModeInputs = document.querySelectorAll('input[name="crawl_mode"]');
const crawlLimitInput = document.querySelector('#crawlLimitInput');
const submitButton = document.querySelector('#submitButton');
const appStatus = document.querySelector('#appStatus');
const progressWrap = document.querySelector('#progressWrap');
const progressBar = document.querySelector('#progressBar');
const progressText = document.querySelector('#progressText');
const results = document.querySelector('#results');
const reportTabs = document.querySelector('#reportTabs');
const overallScore = document.querySelector('#overallScore');
const scoreLabel = document.querySelector('#scoreLabel');
const scoreSummary = document.querySelector('#scoreSummary');
const metricList = document.querySelector('#metricList');
const recommendationPanel = document.querySelector('#tab-recommendations');
const aiPanel = document.querySelector('#tab-ai');
const visibilityPanel = document.querySelector('#tab-visibility');
const resourcesPanel = document.querySelector('#tab-resources');
const pagesPanel = document.querySelector('#tab-pages');
const signalsPanel = document.querySelector('#tab-signals');
const methodologyPanel = document.querySelector('#tab-methodology');
const glossaryPanel = document.querySelector('#tab-glossary');
const exportPdfButton = document.querySelector('#exportPdfButton');
const historyList = document.querySelector('#historyList');
const clearSessionsButton = document.querySelector('#clearSessionsButton');
const recommendationTemplate = document.querySelector('#recommendationTemplate');
const visibilityProjectForm = document.querySelector('#visibilityProjectForm');
const visibilityProjectId = document.querySelector('#visibilityProjectId');
const visibilitySourceReportId = document.querySelector('#visibilitySourceReportId');
const visibilityAuditContext = document.querySelector('#visibilityAuditContext');
const visibilityAuditSourceNote = document.querySelector('#visibilityAuditSourceNote');
const visibilityAuditSourceTitle = document.querySelector('#visibilityAuditSourceTitle');
const visibilityAuditSourceText = document.querySelector('#visibilityAuditSourceText');
const clearVisibilityAuditSourceButton = document.querySelector('#clearVisibilityAuditSourceButton');
const visibilityUrl = document.querySelector('#visibilityUrl');
const visibilityStatus = document.querySelector('#visibilityStatus');
const visibilityDashboard = document.querySelector('#visibilityDashboard');
const visibilityProjectList = document.querySelector('#visibilityProjectList');
const runVisibilityButton = document.querySelector('#runVisibilityButton');
const previewVisibilityQueriesButton = document.querySelector('#previewVisibilityQueriesButton');
const previewPortfolioButton = document.querySelector('#previewPortfolioButton');
const runWeeklyPortfolioButton = document.querySelector('#runWeeklyPortfolioButton');
const exportVisibilityPdfButton = document.querySelector('#exportVisibilityPdfButton');
const visibilityProgressWrap = document.querySelector('#visibilityProgressWrap');
const visibilityProgressBar = document.querySelector('#visibilityProgressBar');
const visibilityProgressText = document.querySelector('#visibilityProgressText');
const visibilityQueryPreview = document.querySelector('#visibilityQueryPreview');
const importGa4Button = document.querySelector('#importGa4Button');
const ga4CsvFile = document.querySelector('#ga4CsvFile');
const ga4CsvText = document.querySelector('#ga4CsvText');
const ga4ImportResult = document.querySelector('#ga4ImportResult');
const importSerpAioButton = document.querySelector('#importSerpAioButton');
const runSerpApiButton = document.querySelector('#runSerpApiButton');
const runGeminiGroundedButton = document.querySelector('#runGeminiGroundedButton');
const serpAioFile = document.querySelector('#serpAioFile');
const serpAioText = document.querySelector('#serpAioText');
const serpAioImportResult = document.querySelector('#serpAioImportResult');
const visibilityNextStepTitle = document.querySelector('#visibilityNextStepTitle');
const visibilityNextStepText = document.querySelector('#visibilityNextStepText');
const suggestVisibilityTopicsButton = document.querySelector('#suggestVisibilityTopicsButton');
const topicSuggestionPanel = document.querySelector('#topicSuggestionPanel');
const suggestVisibilityCompetitorsButton = document.querySelector('#suggestVisibilityCompetitorsButton');
const competitorSuggestionPanel = document.querySelector('#competitorSuggestionPanel');
const visibilityWizardTabs = document.querySelectorAll('[data-visibility-wizard-tab]');
const visibilityWizardPanels = document.querySelectorAll('[data-wizard-panel]');
const visibilityWizardHint = document.querySelector('#visibilityWizardHint');
const visibilityWizardPrev = document.querySelector('#visibilityWizardPrev');
const visibilityWizardNext = document.querySelector('#visibilityWizardNext');
const visibilityWizardLabel = document.querySelector('#visibilityWizardLabel');

const metricLabels = {
    technical: 'Elérhetőség',
    seo: 'SEO',
    aio: 'AI érthetőség',
    visibility: 'AI keresési próba',
    ux: 'UX útvonal',
    content: 'Tartalom',
    entity: 'Bizalmi jelek',
    agentic: 'Használhatóság',
};

let progressTimer = null;
let progressPollTimer = null;
let currentJobId = null;
let currentProgressPercent = 0;
let lastProgressAt = 0;
let loadingStartedAt = 0;
let currentReport = null;
let currentVisibilityProject = null;
let currentVisibilityRuns = [];
let currentVisibilityGa4Imports = [];
let currentVisibilitySerpAioImports = [];
let currentVisibilityGeminiGroundingRuns = [];
let visibilityProgressPollTimer = null;
let visibilityProgressTimer = null;
let currentVisibilityProgressPercent = 0;
let lastVisibilityProgressAt = 0;

const visibilityWizardSteps = [
    {
        id: 'profile',
        label: '1/4 Mérési profil',
        next: 'Tovább a témákhoz',
        hint: 'Kezdd a domainnel és a piaccal. A profil mentése után ugyanazt a mérést később újra tudod futtatni.',
    },
    {
        id: 'topics',
        label: '2/4 Témák és kérdések',
        next: 'Tovább a versenytársakhoz',
        hint: 'Itt azt állítod be, milyen vevői kérdésekben mérjük a márkát. Ha bizonytalan vagy, kérj AI témajavaslatot.',
    },
    {
        id: 'competitors',
        label: '3/4 Versenytársak',
        next: 'Tovább a futtatáshoz',
        hint: 'A versenytárslista segít megmutatni, kit idéznek vagy ajánlanak helyetted az AI válaszok.',
    },
    {
        id: 'run',
        label: '4/4 Mentés és mérés',
        next: 'Futtatásnál maradok',
        hint: 'Először mentsd a profilt, majd nézd meg a kérdés-előnézetet vagy indítsd el a tényleges mérést.',
    },
];

const appViewAliases = {
    audit: 'audit',
    results: 'audit',
    reportTabs: 'audit',
    'visibility-monitor': 'visibility',
    reports: 'reports',
    'glossary-guide': 'glossary',
    'user-guide': 'guide',
};

document.querySelectorAll('.nav-item[data-view]').forEach((item) => {
    item.addEventListener('click', (event) => {
        event.preventDefault();
        switchAppView(item.dataset.view || 'audit', true);
    });
});

window.addEventListener('hashchange', () => {
    switchAppView(viewFromHash(window.location.hash), false);
});

switchAppView(viewFromHash(window.location.hash), false);
updateVisibilityJourneyState();
setVisibilityWizardStep('profile');

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    switchAppView('audit', true);

    const url = normalizeAuditUrl(urlInput.value);
    const crawlMode = selectedCrawlMode();
    const crawlLimit = selectedCrawlLimit(crawlMode);
    const jobId = createJobId();
    if (!url) {
        setStatus('Adj meg egy URL-t vagy domaint, például www.pelda.hu.', 'error');
        return;
    }

    urlInput.value = url;
    currentJobId = jobId;
    setLoading(true, jobId);

    try {
        const response = await fetch('api/analyze.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({ url, crawl_mode: crawlMode, crawl_limit: String(crawlLimit), job_id: jobId }),
        });

        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'Az audit nem futott le.');
        }

        renderReport(payload.report);
        prependHistory(payload.report);
        setStatus('Riport elkészült', 'success');
    } catch (error) {
        setStatus(error.message, 'error');
    } finally {
        setLoading(false);
    }
});

crawlModeInputs.forEach((input) => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.depth-card').forEach((card) => card.classList.toggle('active', card.querySelector('input')?.checked));
    });
});
document.querySelectorAll('.depth-card').forEach((card) => card.classList.toggle('active', card.querySelector('input')?.checked));

urlInput?.addEventListener('blur', () => {
    const normalizedUrl = normalizeAuditUrl(urlInput.value);
    if (normalizedUrl) {
        urlInput.value = normalizedUrl;
    }
});

/**
 * A felületen engedjük a természetes domain megadást is.
 * A tényleges biztonsági validálás továbbra is PHP oldalon történik.
 */
function normalizeAuditUrl(value) {
    let url = String(value || '').trim();

    if (url === '') {
        return '';
    }

    if (url.startsWith('//')) {
        url = `https:${url}`;
    } else if (/^[a-z][a-z0-9+.-]*:\/\//i.test(url) && !/^https?:\/\//i.test(url)) {
        return '';
    } else if (!/^https?:\/\//i.test(url)) {
        url = `https://${url}`;
    }

    try {
        const parsed = new URL(url);
        if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname) {
            return '';
        }

        return parsed.href;
    } catch (error) {
        return url;
    }
}

function selectedCrawlMode() {
    return document.querySelector('input[name="crawl_mode"]:checked')?.value || 'smart';
}

function selectedCrawlLimit(mode) {
    if (mode === 'custom') {
        const max = Number(crawlLimitInput?.getAttribute('max') || 120);
        return Math.max(10, Math.min(max, Number(crawlLimitInput?.value || 60)));
    }

    const selected = document.querySelector('input[name="crawl_mode"]:checked');
    return Number(selected?.dataset.limit || 60);
}

function viewFromHash(hash) {
    const key = String(hash || '').replace(/^#/, '');
    return appViewAliases[key] || 'audit';
}

function switchAppView(view, updateHash = false) {
    const normalizedView = ['audit', 'visibility', 'reports', 'glossary', 'guide'].includes(view) ? view : 'audit';
    document.querySelectorAll('[data-view-panel]').forEach((panel) => {
        panel.classList.toggle('view-hidden', panel.dataset.viewPanel !== normalizedView);
    });
    document.querySelectorAll('.nav-item[data-view]').forEach((item) => {
        item.classList.toggle('active', item.dataset.view === normalizedView);
    });

    if (updateHash) {
        const activeItem = document.querySelector(`.nav-item[data-view="${normalizedView}"]`);
        const targetHash = activeItem?.getAttribute('href') || '#audit';
        if (window.location.hash !== targetHash) {
            history.pushState(null, '', targetHash);
        }
    }

    document.querySelector('.main')?.scrollTo?.({ top: 0, behavior: 'smooth' });
    if (!document.querySelector('.main')?.scrollTo) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

document.querySelectorAll('.tab-button').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.tab-button').forEach((item) => item.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach((item) => item.classList.remove('active'));

        button.classList.add('active');
        document.querySelector(`#tab-${button.dataset.tab}`)?.classList.add('active');
    });
});

exportPdfButton?.addEventListener('click', () => {
    if (!currentReport) {
        setStatus('Előbb futtass le egy auditot.', 'error');
        return;
    }

    openPdfReport(currentReport);
});

historyList?.addEventListener('click', async (event) => {
    const button = event.target.closest('.history-load');
    const visibilityButton = event.target.closest('.history-visibility');
    const pdfButton = event.target.closest('.history-pdf');
    const deleteButton = event.target.closest('.history-delete');
    if (!button && !visibilityButton && !pdfButton && !deleteButton) {
        return;
    }

    const trigger = button || visibilityButton || pdfButton || deleteButton;
    const reportId = trigger.dataset.reportId;
    if (!reportId) {
        setStatus('Hiányzó riport azonosító.', 'error');
        return;
    }

    if (deleteButton) {
        await deleteSavedReport(reportId, deleteButton);
        return;
    }

    const report = await loadSavedReport(reportId, trigger, { scrollToReport: Boolean(button), render: !visibilityButton });
    if (visibilityButton && report) {
        prefillVisibilityProfileFromAuditReport(report);
        return;
    }

    if (pdfButton && report) {
        openPdfReport(report);
    }
});

clearSessionsButton?.addEventListener('click', async () => {
    const confirmed = window.confirm('Töröljük a korábbi technikai session/progress fájlokat? Ez nem törli a riportokat.');
    if (!confirmed) {
        return;
    }

    const originalLabel = clearSessionsButton.textContent;
    clearSessionsButton.disabled = true;
    clearSessionsButton.textContent = 'Törlés...';
    setStatus('Sessionök törlése...', 'neutral');

    try {
        const response = await fetch('api/clear_sessions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            body: new URLSearchParams({ action: 'clear' }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A sessionök törlése nem sikerült.');
        }
        setStatus(payload.message || 'Sessionök törölve.', 'success');
    } catch (error) {
        setStatus(error.message, 'error');
    } finally {
        clearSessionsButton.disabled = false;
        clearSessionsButton.textContent = originalLabel;
    }
});

visibilityUrl?.addEventListener('blur', () => {
    const normalizedUrl = normalizeAuditUrl(visibilityUrl.value);
    if (normalizedUrl) {
        visibilityUrl.value = normalizedUrl;
    }
});

visibilityWizardTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        setVisibilityWizardStep(tab.dataset.visibilityWizardTab || 'profile', true);
    });
});

visibilityWizardPrev?.addEventListener('click', () => {
    const currentStep = getCurrentVisibilityWizardStep();
    const currentIndex = visibilityWizardSteps.findIndex((step) => step.id === currentStep);
    setVisibilityWizardStep(visibilityWizardSteps[Math.max(0, currentIndex - 1)]?.id || 'profile', true);
});

visibilityWizardNext?.addEventListener('click', () => {
    const currentStep = getCurrentVisibilityWizardStep();
    const currentIndex = visibilityWizardSteps.findIndex((step) => step.id === currentStep);
    setVisibilityWizardStep(visibilityWizardSteps[Math.min(visibilityWizardSteps.length - 1, currentIndex + 1)]?.id || 'run', true);
});

visibilityProjectForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    await saveVisibilityProject();
});

runVisibilityButton?.addEventListener('click', async () => {
    await runVisibilityMeasurement();
});

previewVisibilityQueriesButton?.addEventListener('click', async () => {
    await previewVisibilityQueries('generated');
});

previewPortfolioButton?.addEventListener('click', async () => {
    await previewVisibilityQueries('weekly_portfolio');
});

suggestVisibilityTopicsButton?.addEventListener('click', async () => {
    await suggestVisibilityTopics();
});

topicSuggestionPanel?.addEventListener('click', (event) => {
    const applyButton = event.target.closest('[data-apply-topic-suggestions]');
    if (!applyButton) {
        return;
    }

    applySelectedTopicSuggestions();
});

suggestVisibilityCompetitorsButton?.addEventListener('click', async () => {
    await suggestVisibilityCompetitors();
});

competitorSuggestionPanel?.addEventListener('click', (event) => {
    const applyButton = event.target.closest('[data-apply-competitor-suggestions]');
    if (!applyButton) {
        return;
    }

    applySelectedCompetitorSuggestions();
});

runWeeklyPortfolioButton?.addEventListener('click', async () => {
    await runVisibilityMeasurement('weekly_portfolio');
});

clearVisibilityAuditSourceButton?.addEventListener('click', () => {
    clearVisibilityAuditSource();
    setVisibilityStatus('Az auditkapcsolat leválasztva. A mezők tartalmát kézzel tovább szerkesztheted.', 'neutral');
});

exportVisibilityPdfButton?.addEventListener('click', () => {
    if (!currentVisibilityProject || !currentVisibilityRuns.length) {
        setVisibilityStatus('Előbb nyiss meg vagy futtass egy visibility mérést.', 'error');
        return;
    }

    openVisibilityPdfReport(currentVisibilityProject, currentVisibilityRuns[0], currentVisibilityRuns);
});

importGa4Button?.addEventListener('click', async () => {
    await importGa4Referrals();
});

importSerpAioButton?.addEventListener('click', async () => {
    await importSerpAioEvidence();
});

runSerpApiButton?.addEventListener('click', async () => {
    await runSerpApiEvidenceProbe();
});

runGeminiGroundedButton?.addEventListener('click', async () => {
    await runGeminiGroundedProbe();
});

visibilityProjectList?.addEventListener('click', (event) => {
    const button = event.target.closest('.visibility-load-project');
    if (!button) {
        return;
    }

    const card = button.closest('.visibility-project-card');
    if (!card?.dataset.project) {
        setVisibilityStatus('A projekt adatai nem olvashatók.', 'error');
        return;
    }

    try {
        const project = JSON.parse(card.dataset.project);
        switchAppView('visibility', true);
        loadVisibilityProject(project);
        fetchVisibilityProjectDetails(project.id);
        document.querySelector('#visibility-monitor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        setVisibilityStatus('A projekt betöltése nem sikerült.', 'error');
    }
});

function setLoading(isLoading, jobId = null) {
    submitButton.disabled = isLoading;
    submitButton.textContent = isLoading ? 'Elemzés folyamatban' : 'Audit indítása';
    progressWrap.classList.toggle('hidden', !isLoading);

    if (isLoading) {
        let value = 6;
        currentProgressPercent = value;
        lastProgressAt = 0;
        loadingStartedAt = Date.now();
        progressBar.style.width = `${value}%`;
        progressText.textContent = 'Audit indítása...';
        appStatus.textContent = 'Fut';
        startProgressPolling(jobId);
        progressTimer = window.setInterval(() => {
            currentProgressPercent = Math.min(88, currentProgressPercent + Math.random() * 2.5);
            progressBar.style.width = `${currentProgressPercent}%`;
            if (!lastProgressAt || Date.now() - lastProgressAt > 2500) {
                progressText.textContent = estimatedProgressMessage(Date.now() - loadingStartedAt);
            }
        }, 520);
    } else {
        window.clearInterval(progressTimer);
        stopProgressPolling();
        progressBar.style.width = '100%';
    }
}

async function fetchVisibilityProjectDetails(projectId) {
    if (!projectId) {
        return;
    }

    setVisibilityStatus('Visibility előzmények betöltése...', 'neutral');
    try {
        const response = await fetch(`api/get_visibility_project.php?id=${encodeURIComponent(projectId)}`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A visibility előzmények nem tölthetők be.');
        }

        currentVisibilityRuns = Array.isArray(payload.runs) ? payload.runs : [];
        currentVisibilityGa4Imports = Array.isArray(payload.ga4_imports) ? payload.ga4_imports : [];
        currentVisibilitySerpAioImports = Array.isArray(payload.serp_aio_imports) ? payload.serp_aio_imports : [];
        currentVisibilityGeminiGroundingRuns = Array.isArray(payload.gemini_grounding_runs) ? payload.gemini_grounding_runs : [];
        currentVisibilityProject = payload.project;
        currentVisibilityProject.latest_ga4_import = currentVisibilityGa4Imports[0] || currentVisibilityProject.latest_ga4_import || null;
        currentVisibilityProject.latest_serp_aio_import = currentVisibilitySerpAioImports[0] || currentVisibilityProject.latest_serp_aio_import || null;
        currentVisibilityProject.latest_gemini_grounding_run = currentVisibilityGeminiGroundingRuns[0] || currentVisibilityProject.latest_gemini_grounding_run || null;
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        renderGa4ImportResult(currentVisibilityGa4Imports);
        renderSerpAioImportResult(currentVisibilitySerpAioImports);
        updateVisibilityJourneyState();
        upsertVisibilityProjectCard(currentVisibilityProject);
        if (exportVisibilityPdfButton) exportVisibilityPdfButton.disabled = !currentVisibilityRuns.length;
        if (importGa4Button) importGa4Button.disabled = !currentVisibilityProject?.id;
        if (importSerpAioButton) importSerpAioButton.disabled = !currentVisibilityProject?.id;
        if (runSerpApiButton) runSerpApiButton.disabled = !currentVisibilityProject?.id;
        if (runGeminiGroundedButton) runGeminiGroundedButton.disabled = !currentVisibilityProject?.id;
        setVisibilityStatus('Visibility projekt betöltve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    }
}

function createJobId() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID().replaceAll('-', '');
    }

    return `job_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function startProgressPolling(jobId) {
    stopProgressPolling();
    if (!jobId) {
        return;
    }

    const poll = async () => {
        try {
            const response = await fetch(`api/progress.php?job=${encodeURIComponent(jobId)}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const payload = await response.json();
            if (!payload.ok) {
                return;
            }

            const percent = Number(payload.percent || 0);
            currentProgressPercent = Math.max(currentProgressPercent, Math.max(4, Math.min(100, percent)));
            progressBar.style.width = `${currentProgressPercent}%`;
            lastProgressAt = Date.now();
            progressText.textContent = payload.detail
                ? `${payload.phase}: ${payload.detail}`
                : payload.phase || 'Audit folyamatban...';
            appStatus.textContent = payload.phase || 'Fut';
        } catch (error) {
            progressText.textContent = 'Audit folyamatban... a részállapot lekérdezése átmenetileg nem válaszol.';
        }
    };

    poll();
    progressPollTimer = window.setInterval(poll, 900);
}

function estimatedProgressMessage(elapsedMs) {
    const seconds = Math.floor(elapsedMs / 1000);

    if (seconds < 20) {
        return 'Oldalak letöltése és nyers HTML/AIO jelek gyűjtése...';
    }

    if (seconds < 55) {
        return 'On-page audit rendezése: schema, entitásjelek, UX útvonalak és citációs blokkok...';
    }

    if (seconds < 120) {
        return 'OpenAI részletes elemzés készül. Ez hosszabb lehet, mert strukturált javítási tervet kérünk.';
    }

    if (seconds < 190) {
        return 'Live AI keresési próba fut. A rendszer azt nézi, hogy a brand/domain megjelenhet-e AI válaszokban.';
    }

    if (seconds < 260) {
        return 'OpenRouter gyors másodvélemény és riportstruktúra készül. Még dolgozik a szerver.';
    }

    return 'Az audit még fut. Lassú céloldal vagy külső AI API válaszidő miatt ez több perc is lehet, de a folyamat nem szakadt meg.';
}

function stopProgressPolling() {
    if (progressPollTimer) {
        window.clearInterval(progressPollTimer);
        progressPollTimer = null;
    }
}

function setStatus(message, type = 'neutral') {
    appStatus.textContent = message;
    appStatus.style.borderColor = type === 'error' ? '#f0b4b4' : '';
    appStatus.style.color = type === 'error' ? '#b73333' : '';
}

function setVisibilityStatus(message, type = 'neutral') {
    if (!visibilityStatus) {
        return;
    }

    visibilityStatus.textContent = message;
    visibilityStatus.style.borderColor = type === 'error' ? '#f0b4b4' : '';
    visibilityStatus.style.color = type === 'error' ? '#b73333' : '';
}

function updateVisibilityJourneyState() {
    const hasProject = Boolean(currentVisibilityProject?.id);
    const hasRun = Boolean(currentVisibilityRuns.length || currentVisibilityProject?.latest_run);
    const hasEvidence = Boolean(
        currentVisibilityGa4Imports.length
        || currentVisibilitySerpAioImports.length
        || currentVisibilityGeminiGroundingRuns.length
        || currentVisibilityProject?.latest_ga4_import
        || currentVisibilityProject?.latest_serp_aio_import
        || currentVisibilityProject?.latest_gemini_grounding_run
    );

    const stepState = {
        profile: hasProject ? 'done' : 'active',
        questions: hasProject && !hasRun ? 'active' : (hasProject ? 'done' : ''),
        evidence: hasEvidence ? 'done' : (hasProject && !hasRun ? 'active' : ''),
        report: hasRun ? 'active done' : '',
    };

    document.querySelectorAll('[data-visibility-step]').forEach((step) => {
        const state = stepState[step.dataset.visibilityStep] || '';
        step.classList.toggle('active', state.includes('active'));
        step.classList.toggle('done', state.includes('done'));
    });

    if (!visibilityNextStepTitle || !visibilityNextStepText) {
        return;
    }

    if (!hasProject) {
        visibilityNextStepTitle.textContent = 'Ments egy mérési profilt';
        visibilityNextStepText.textContent = 'A profil köti össze a domaint, kérdéseket, versenytársakat, importokat, futásokat és PDF riportokat. Ezért ez az első kötelező lépés.';
        return;
    }

    if (!hasRun) {
        visibilityNextStepTitle.textContent = 'Profil mentve: indulhat a mérés';
        visibilityNextStepText.textContent = 'Ha szeretnéd, előbb nézd meg a generált kérdéseket. Ha rendben van, a “Mérés futtatása” gomb adja a fő AI visibility eredményt.';
        return;
    }

    if (!hasEvidence) {
        visibilityNextStepTitle.textContent = 'Mérés kész: jöhet PDF vagy kontrolladat';
        visibilityNextStepText.textContent = 'A visibility riport már letölthető. GA4 vagy SERP/AIO importot akkor adj hozzá, ha valós forgalmi vagy Google találati bizonyítékkal is alá akarod támasztani.';
        return;
    }

    visibilityNextStepTitle.textContent = 'Teljesebb mérési csomag összeállt';
    visibilityNextStepText.textContent = 'Van mérési futás és legalább egy kontrolladat. A PDF riport már a mérés, bizonyítékok és javítási backlog alapján készül.';
}

function getCurrentVisibilityWizardStep() {
    const activeTab = Array.from(visibilityWizardTabs).find((tab) => tab.classList.contains('active'));
    return activeTab?.dataset.visibilityWizardTab || 'profile';
}

function setVisibilityWizardStep(stepId = 'profile', shouldScroll = false) {
    if (!visibilityWizardTabs.length || !visibilityWizardPanels.length) {
        return;
    }

    const step = visibilityWizardSteps.find((item) => item.id === stepId) || visibilityWizardSteps[0];
    const stepIndex = visibilityWizardSteps.findIndex((item) => item.id === step.id);

    visibilityWizardTabs.forEach((tab) => {
        const isActive = tab.dataset.visibilityWizardTab === step.id;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    visibilityWizardPanels.forEach((panel) => {
        const isActive = panel.dataset.wizardPanel === step.id;
        panel.classList.toggle('active', isActive);
        panel.toggleAttribute('hidden', !isActive);
    });

    if (visibilityWizardHint) {
        visibilityWizardHint.textContent = step.hint;
    }

    if (visibilityWizardLabel) {
        visibilityWizardLabel.textContent = step.label;
    }

    if (visibilityWizardPrev) {
        visibilityWizardPrev.disabled = stepIndex <= 0;
    }

    if (visibilityWizardNext) {
        visibilityWizardNext.disabled = stepIndex >= visibilityWizardSteps.length - 1;
        visibilityWizardNext.textContent = step.next;
    }

    if (shouldScroll) {
        const activePanel = Array.from(visibilityWizardPanels).find((panel) => panel.dataset.wizardPanel === step.id);
        activePanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function saveVisibilityProject() {
    if (!visibilityProjectForm) {
        return;
    }

    const formData = prepareVisibilityFormData();
    if (!formData) {
        setVisibilityStatus('Adj meg érvényes saját domaint vagy URL-t.', 'error');
        return;
    }

    const button = document.querySelector('#saveVisibilityProjectButton');
    const originalLabel = button?.textContent || '';
    if (button) {
        button.disabled = true;
        button.textContent = 'Mentés...';
    }
    setVisibilityStatus('Projekt mentése...', 'neutral');

    try {
        const response = await fetch('api/save_visibility_project.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams(formData),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A láthatósági projekt mentése nem sikerült.');
        }

        loadVisibilityProject(payload.project);
        upsertVisibilityProjectCard(payload.project);
        updateVisibilityJourneyState();
        setVisibilityWizardStep('run', true);
        setVisibilityStatus('Projekt mentve, mérés indítható', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel;
        }
    }
}

function prepareVisibilityFormData() {
    if (!visibilityProjectForm) {
        return null;
    }

    const formData = new FormData(visibilityProjectForm);
    const normalizedUrl = normalizeAuditUrl(String(formData.get('site_url') || ''));
    if (!normalizedUrl) {
        return null;
    }

    formData.set('site_url', normalizedUrl);
    visibilityUrl.value = normalizedUrl;
    const queryLimit = Math.max(4, Math.min(20, Number(formData.get('query_limit') || 12)));
    formData.set('query_limit', String(queryLimit));
    const queryLimitInput = document.querySelector('#visibilityQueryLimit');
    if (queryLimitInput) {
        queryLimitInput.value = String(queryLimit);
    }

    return formData;
}

async function previewVisibilityQueries(runMode = 'generated') {
    const formData = prepareVisibilityFormData();
    if (!formData) {
        setVisibilityStatus('Adj meg érvényes saját domaint vagy URL-t.', 'error');
        return;
    }

    const originalLabel = previewVisibilityQueriesButton.textContent;
    const activeButton = runMode === 'weekly_portfolio' ? previewPortfolioButton : previewVisibilityQueriesButton;
    const activeOriginalLabel = activeButton?.textContent || originalLabel;
    if (activeButton) {
        activeButton.disabled = true;
        activeButton.textContent = 'Előnézet...';
    }
    formData.set('run_mode', runMode);
    setVisibilityStatus('Kérdésportfólió előnézet készítése...', 'neutral');

    try {
        const response = await fetch('api/preview_visibility_queries.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams(formData),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A kérdéselőnézet nem készült el.');
        }

        renderVisibilityQueryPreview(payload.project, payload.queries || [], payload.run_mode || runMode);
        setVisibilityStatus(`${Number(payload.query_count || 0)} kérdés előnézete kész`, 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (activeButton) {
            activeButton.disabled = false;
            activeButton.textContent = activeOriginalLabel;
        }
    }
}

function renderVisibilityQueryPreview(project, queries = [], runMode = 'generated') {
    if (!visibilityQueryPreview) {
        return;
    }

    visibilityQueryPreview.classList.remove('hidden');
    visibilityQueryPreview.innerHTML = `
        <div class="recommendation-head">
            <div>
                <h3>Kérdésportfólió előnézet</h3>
                <p>${escapeHtml(runMode === 'weekly_portfolio' ? 'Heti Top 20 portfólió' : project.business_model_label || 'Általános weboldal')} · ${Number(queries.length)} kérdés · ${escapeHtml(project.target_domain || '')}</p>
            </div>
            <span>futtatás előtt</span>
        </div>
        <div class="query-grid compact-query-grid">
            ${queries.map((item) => `
                <section class="query-card">
                    <span>${escapeHtml(item.type || 'kérdés')}</span>
                    <h4>${escapeHtml(item.query || '')}</h4>
                    <p>${escapeHtml(item.why || '')}</p>
                    <small>${escapeHtml(item.expected_signal || '')}</small>
                </section>
            `).join('') || '<p class="empty-state">Nincs előnézeti kérdés.</p>'}
        </div>
    `;
}

async function suggestVisibilityTopics() {
    const formData = prepareVisibilityFormData();
    if (!formData) {
        setVisibilityStatus('Adj meg előbb egy publikus domaint vagy URL-t. Abból tudok releváns témákat ajánlani.', 'error');
        return;
    }

    const originalLabel = suggestVisibilityTopicsButton?.textContent || '';
    if (suggestVisibilityTopicsButton) {
        suggestVisibilityTopicsButton.disabled = true;
        suggestVisibilityTopicsButton.textContent = 'Témák készülnek...';
    }

    if (topicSuggestionPanel) {
        topicSuggestionPanel.classList.remove('hidden');
        topicSuggestionPanel.innerHTML = `
            <div class="topic-helper-loading">
                <strong>AI témasegéd dolgozik...</strong>
                <p>Megnézem a domaint, az üzleti modellt és a piacot, majd mérhető témákat javaslok.</p>
            </div>
        `;
    }
    setVisibilityStatus('AI témasegéd fut...', 'neutral');

    try {
        const response = await fetch('api/suggest_visibility_topics.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams(formData),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A témasegéd nem tudott javaslatot adni.');
        }

        renderTopicSuggestions(payload);
        setVisibilityStatus('AI témák elkészültek a weboldal-kontekstus alapján', 'success');
    } catch (error) {
        if (topicSuggestionPanel) {
            topicSuggestionPanel.classList.remove('hidden');
            topicSuggestionPanel.innerHTML = `<p class="empty-state">${escapeHtml(error.message)}</p>`;
        }
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (suggestVisibilityTopicsButton) {
            suggestVisibilityTopicsButton.disabled = false;
            suggestVisibilityTopicsButton.textContent = originalLabel;
        }
    }
}

function renderTopicSuggestions(payload = {}) {
    if (!topicSuggestionPanel) {
        return;
    }

    const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
    topicSuggestionPanel.classList.remove('hidden');
    topicSuggestionPanel.innerHTML = `
        <div class="topic-suggestion-head">
            <div>
                <span>AI javaslat weboldal-kontekstusból</span>
                <h4>Javasolt mérési témák</h4>
                <p>${escapeHtml(payload.message || 'Válaszd ki, mely témákat szeretnéd betölteni a mérési profilba.')}</p>
            </div>
            <button type="button" class="mini-button" data-apply-topic-suggestions>Kiválasztott témák betöltése</button>
        </div>
        <div class="topic-suggestion-grid">
            ${suggestions.map((item, index) => renderTopicSuggestionCard(item, index)).join('') || '<p class="empty-state">Nem érkezett témalistajavaslat.</p>'}
        </div>
    `;
}

function renderTopicSuggestionCard(item = {}, index = 0) {
    const topic = String(item.topic || '').trim();
    const questions = Array.isArray(item.example_questions) ? item.example_questions : [];
    const priority = item.priority === 'high' ? 'Magas' : (item.priority === 'low' ? 'Alacsony' : 'Közepes');

    return `
        <label class="topic-suggestion-card">
            <input type="checkbox" value="${escapeHtml(topic)}" checked>
            <span>${escapeHtml(priority)} prioritás · ${escapeHtml(item.intent || 'mérési téma')}</span>
            <strong>${escapeHtml(topic || `Javasolt téma ${index + 1}`)}</strong>
            <p>${escapeHtml(item.why || 'Erre a témára később vevői kérdéseket lehet mérni.')}</p>
            ${questions.length ? `
                <small>${escapeHtml(questions.slice(0, 2).join(' · '))}</small>
            ` : ''}
        </label>
    `;
}

function applySelectedTopicSuggestions() {
    const topicsInput = document.querySelector('#visibilityTopics');
    if (!topicsInput || !topicSuggestionPanel) {
        return;
    }

    const selectedTopics = Array.from(topicSuggestionPanel.querySelectorAll('.topic-suggestion-card input:checked'))
        .map((input) => String(input.value || '').trim())
        .filter(Boolean);

    if (!selectedTopics.length) {
        setVisibilityStatus('Válassz ki legalább egy témát a betöltéshez.', 'error');
        return;
    }

    const existing = String(topicsInput.value || '')
        .split(/\r?\n|,|;/)
        .map((item) => item.trim())
        .filter(Boolean);
    const merged = Array.from(new Set([...existing, ...selectedTopics]));
    topicsInput.value = merged.join('\n');
    setVisibilityStatus(`${selectedTopics.length} témát betöltöttem a mérési profilba`, 'success');
}

async function suggestVisibilityCompetitors() {
    const formData = prepareVisibilityFormData();
    if (!formData) {
        setVisibilityStatus('Adj meg előbb egy publikus domaint vagy URL-t. Abból tudok versenytársakat ajánlani.', 'error');
        return;
    }

    const originalLabel = suggestVisibilityCompetitorsButton?.textContent || '';
    if (suggestVisibilityCompetitorsButton) {
        suggestVisibilityCompetitorsButton.disabled = true;
        suggestVisibilityCompetitorsButton.textContent = 'Jelöltek készülnek...';
    }

    if (competitorSuggestionPanel) {
        competitorSuggestionPanel.classList.remove('hidden');
        competitorSuggestionPanel.innerHTML = `
            <div class="competitor-helper-loading">
                <strong>Versenytársjelöltek keresése...</strong>
                <p>A témák, piac és domain alapján keresési vagy AI jelölteket állítok össze.</p>
            </div>
        `;
    }
    setVisibilityStatus('Versenytárssegéd fut...', 'neutral');

    try {
        const response = await fetch('api/suggest_visibility_competitors.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams(formData),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A versenytárssegéd nem tudott javaslatot adni.');
        }

        renderCompetitorSuggestions(payload);
        setVisibilityStatus(payload.suggestions?.length ? 'Versenytársjelöltek elkészültek' : 'Nem találtam megbízható versenytársjelöltet', payload.suggestions?.length ? 'success' : 'neutral');
    } catch (error) {
        if (competitorSuggestionPanel) {
            competitorSuggestionPanel.classList.remove('hidden');
            competitorSuggestionPanel.innerHTML = `<p class="empty-state">${escapeHtml(error.message)}</p>`;
        }
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (suggestVisibilityCompetitorsButton) {
            suggestVisibilityCompetitorsButton.disabled = false;
            suggestVisibilityCompetitorsButton.textContent = originalLabel;
        }
    }
}

function renderCompetitorSuggestions(payload = {}) {
    if (!competitorSuggestionPanel) {
        return;
    }

    const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
    competitorSuggestionPanel.classList.remove('hidden');
    competitorSuggestionPanel.innerHTML = `
        <div class="competitor-suggestion-head">
            <div>
                <span>${escapeHtml(competitorSuggestionSourceLabel(payload.source || ''))}</span>
                <h4>Javasolt versenytársak</h4>
                <p>${escapeHtml(payload.message || 'Válaszd ki, mely domaineket szeretnéd betölteni a mérési profilba.')}</p>
            </div>
            <button type="button" class="mini-button" data-apply-competitor-suggestions ${suggestions.length ? '' : 'disabled'}>Kiválasztott domainek betöltése</button>
        </div>
        <div class="competitor-suggestion-grid">
            ${suggestions.map((item) => renderCompetitorSuggestionCard(item)).join('') || '<p class="empty-state">Nem érkezett megbízható versenytársjelölt. Adj meg 2-3 témát, vagy ellenőrizd a keresési/AI provider beállítást.</p>'}
        </div>
    `;
}

function renderCompetitorSuggestionCard(item = {}) {
    const domain = String(item.domain || '').trim();
    const confidence = item.confidence === 'high' ? 'Magas' : (item.confidence === 'low' ? 'Alacsony' : 'Közepes');
    const source = item.source === 'search_ai' ? 'keresés + AI' : (item.source === 'search' ? 'keresési evidencia' : 'AI értelmezés');
    const evidence = Array.isArray(item.evidence) ? item.evidence : [];
    const evidenceText = evidence.map((entry) => {
        if (typeof entry === 'string') {
            return entry;
        }
        if (entry && typeof entry === 'object') {
            return [entry.query, entry.title, entry.provider].filter(Boolean).join(' · ');
        }
        return '';
    }).filter(Boolean).slice(0, 2).join(' · ');

    return `
        <label class="competitor-suggestion-card">
            <input type="checkbox" value="${escapeHtml(domain)}" ${domain ? 'checked' : ''}>
            <span>${escapeHtml(confidence)} biztonság · ${escapeHtml(source)}</span>
            <strong>${escapeHtml(item.name || domain)}</strong>
            <code>${escapeHtml(domain)}</code>
            <p>${escapeHtml(item.why || 'Lehetséges benchmark jelölt az AI láthatósági méréshez.')}</p>
            ${evidenceText ? `<small>${escapeHtml(evidenceText)}</small>` : ''}
        </label>
    `;
}

function competitorSuggestionSourceLabel(source) {
    if (source === 'search_and_ai') {
        return 'Keresés + AI';
    }
    if (source === 'search') {
        return 'Keresési evidencia';
    }
    if (source === 'openrouter') {
        return 'AI értelmezés';
    }
    return 'Javaslat';
}

function applySelectedCompetitorSuggestions() {
    const competitorsInput = document.querySelector('#visibilityCompetitors');
    if (!competitorsInput || !competitorSuggestionPanel) {
        return;
    }

    const selectedDomains = Array.from(competitorSuggestionPanel.querySelectorAll('.competitor-suggestion-card input:checked'))
        .map((input) => String(input.value || '').trim())
        .filter(Boolean);

    if (!selectedDomains.length) {
        setVisibilityStatus('Válassz ki legalább egy versenytársdomaint a betöltéshez.', 'error');
        return;
    }

    const existing = String(competitorsInput.value || '')
        .split(/\r?\n|,|;/)
        .map((item) => item.trim())
        .filter(Boolean);
    const merged = Array.from(new Set([...existing, ...selectedDomains]));
    competitorsInput.value = merged.join('\n');
    setVisibilityStatus(`${selectedDomains.length} versenytársdomaint betöltöttem a mérési profilba`, 'success');
}

function prefillVisibilityProfileFromAuditReport(report = {}) {
    if (!visibilityProjectForm) {
        return;
    }

    const plan = report.ai_search_plan || {};
    const normalizedUrl = normalizeAuditUrl(report.summary?.root_url || report.url || '');
    const domain = normalizeVisibilityDomain(plan.domain || normalizedUrl);
    const brandName = String(plan.brand_name || domain || 'AI láthatóság').trim();
    const topics = buildVisibilityTopicsFromAudit(report);
    const queries = buildVisibilityQueriesFromAudit(report);
    const portfolio = queries.map((item) => `${item.type || 'audit'}: ${item.query}`).join('\n');
    const customQueries = queries.slice(0, 8).map((item) => item.query).join('\n');

    currentVisibilityProject = null;
    currentVisibilityRuns = [];
    currentVisibilityGa4Imports = [];
    currentVisibilitySerpAioImports = [];
    currentVisibilityGeminiGroundingRuns = [];

    if (visibilityProjectId) visibilityProjectId.value = '';
    if (visibilitySourceReportId) visibilitySourceReportId.value = report.id || '';
    if (visibilityAuditContext) visibilityAuditContext.value = JSON.stringify(buildVisibilityAuditContext(report));
    if (document.querySelector('#visibilityName')) document.querySelector('#visibilityName').value = `${brandName} AI láthatóság`;
    if (visibilityUrl) visibilityUrl.value = normalizedUrl || report.url || '';
    if (document.querySelector('#visibilityMarket')) document.querySelector('#visibilityMarket').value = inferMarketFromAudit(report);
    if (document.querySelector('#visibilityLanguage')) document.querySelector('#visibilityLanguage').value = inferLanguageFromAudit(report);
    if (document.querySelector('#visibilityBusinessModel')) document.querySelector('#visibilityBusinessModel').value = inferBusinessModelFromAudit(report);
    if (document.querySelector('#visibilityQueryLimit')) document.querySelector('#visibilityQueryLimit').value = String(Math.min(20, Math.max(8, queries.length || 12)));
    if (document.querySelector('#visibilityTopics')) document.querySelector('#visibilityTopics').value = topics.join('\n');
    if (document.querySelector('#visibilityCustomQueries')) document.querySelector('#visibilityCustomQueries').value = customQueries;
    if (document.querySelector('#visibilityQueryPortfolio')) document.querySelector('#visibilityQueryPortfolio').value = portfolio;
    if (document.querySelector('#visibilityCompetitors')) document.querySelector('#visibilityCompetitors').value = '';

    if (runVisibilityButton) runVisibilityButton.disabled = true;
    if (runWeeklyPortfolioButton) runWeeklyPortfolioButton.disabled = true;
    if (exportVisibilityPdfButton) exportVisibilityPdfButton.disabled = true;
    if (importGa4Button) importGa4Button.disabled = true;
    if (importSerpAioButton) importSerpAioButton.disabled = true;
    if (runSerpApiButton) runSerpApiButton.disabled = true;
    if (runGeminiGroundedButton) runGeminiGroundedButton.disabled = true;

    renderVisibilityAuditSourceNote(buildVisibilityAuditContext(report));
    renderVisibilityDashboard(null, { target_domain: domain, name: `${brandName} AI láthatóság` }, false, []);
    updateVisibilityJourneyState();
    switchAppView('visibility', true);
    setVisibilityWizardStep('profile', true);
    setVisibilityStatus('Az auditból előtöltöttem a visibility profilt. Nézd át, majd mentsd a mérést.', 'success');
}

function buildVisibilityTopicsFromAudit(report = {}) {
    const plan = report.ai_search_plan || {};
    const plannedTopics = Array.isArray(plan.main_topics) ? plan.main_topics : [];
    const recommendationTopics = (report.recommendations || [])
        .map((item) => item.category || item.title || '')
        .filter((item) => item && !/finomhangolás|alapjavítás/i.test(item));

    return uniqueCleanList([...plannedTopics, ...recommendationTopics], 12);
}

function buildVisibilityQueriesFromAudit(report = {}) {
    const plannedQueries = Array.isArray(report.ai_search_plan?.query_set)
        ? report.ai_search_plan.query_set
        : [];
    const mapped = plannedQueries
        .map((item) => ({
            type: item.type || item.id || 'audit',
            query: String(item.query || '').trim(),
        }))
        .filter((item) => item.query !== '');

    if (mapped.length >= 4) {
        return mapped.slice(0, 20);
    }

    const domain = normalizeVisibilityDomain(report.url || '');
    const fallbackQueries = [
        `Mit tudsz a(z) ${domain} weboldalról és milyen szolgáltatáshoz kötnéd?`,
        `Milyen források alapján döntenél ${domain} témájában?`,
        `Mely cégeket ajánlanád ${domain} alternatívájaként?`,
        `Milyen hibák miatt nem idézné egy AI kereső a ${domain} oldalt?`,
    ].map((query) => ({ type: 'audit', query }));

    return [...mapped, ...fallbackQueries].slice(0, 20);
}

function buildVisibilityAuditContext(report = {}) {
    const summary = report.summary || {};
    return {
        report_id: report.id || '',
        source_url: report.url || summary.root_url || '',
        created_at: report.created_at || '',
        overall_score: Number(report.overall_score || 0),
        summary_label: summary.label || '',
        pages_checked: Number(summary.pages_checked || 0),
        critical_count: Number(summary.critical_count || 0),
        warning_count: Number(summary.warning_count || 0),
        scores: report.scores || {},
        static_readiness: report.ai_search_plan?.static_readiness || {},
        top_recommendations: (report.recommendations || []).slice(0, 8).map((item) => ({
            level: item.level || '',
            category: item.category || '',
            title: item.title || '',
            next_step: item.next_step || item.fix || '',
            count: Number(item.count || 0),
        })),
    };
}

function renderVisibilityAuditSourceNote(context = null) {
    if (!visibilityAuditSourceNote) {
        return;
    }

    if (!context?.report_id) {
        visibilityAuditSourceNote.classList.add('hidden');
        return;
    }

    visibilityAuditSourceNote.classList.remove('hidden');
    if (visibilityAuditSourceTitle) {
        visibilityAuditSourceTitle.textContent = `Forrásaudit: ${context.overall_score || 0}/100 · ${context.summary_label || 'audit riport'}`;
    }
    if (visibilityAuditSourceText) {
        visibilityAuditSourceText.textContent = `${context.pages_checked || 0} oldal vizsgálata, ${context.critical_count || 0} kritikus és ${context.warning_count || 0} fontos teendő alapján előtöltve.`;
    }
}

function clearVisibilityAuditSource() {
    if (visibilitySourceReportId) visibilitySourceReportId.value = '';
    if (visibilityAuditContext) visibilityAuditContext.value = '';
    renderVisibilityAuditSourceNote(null);
}

function normalizeVisibilityDomain(value = '') {
    const normalized = normalizeAuditUrl(value);
    try {
        const parsed = new URL(normalized || `https://${String(value || '').trim()}`);
        return parsed.hostname.replace(/^www\./i, '');
    } catch (error) {
        return String(value || '').replace(/^https?:\/\//i, '').replace(/^www\./i, '').split('/')[0];
    }
}

function uniqueCleanList(items = [], limit = 12) {
    const seen = new Set();
    const cleaned = [];
    items.forEach((item) => {
        const value = String(item || '').trim();
        const key = value.toLocaleLowerCase('hu-HU');
        if (value === '' || seen.has(key)) {
            return;
        }
        seen.add(key);
        cleaned.push(value);
    });
    return cleaned.slice(0, limit);
}

function inferBusinessModelFromAudit(report = {}) {
    const text = [
        report.ai_search_plan?.market_context,
        report.url,
        ...(report.ai_search_plan?.main_topics || []),
    ].join(' ').toLocaleLowerCase('hu-HU');

    if (/e-?commerce|webshop|termék|product/.test(text)) return 'ecommerce';
    if (/saas|szoftver|software|platform/.test(text)) return 'saas';
    if (/helyi|local|város|étterem|rendelő/.test(text)) return 'local_service';
    if (/szakértő|tanácsad|coach|consultant/.test(text)) return 'expert_brand';
    if (/b2b|ügynökség|agency|szolgáltat/.test(text)) return 'b2b_service';
    return 'generic';
}

function inferLanguageFromAudit(report = {}) {
    const countryHint = String(report.ai_search_plan?.country_hint || '').toLocaleLowerCase('hu-HU');
    if (countryHint.includes('magyar') || countryHint === 'hu') {
        return 'hu';
    }
    if (countryHint.includes('angol') || countryHint === 'en') {
        return 'en';
    }
    return 'hu';
}

function inferMarketFromAudit(report = {}) {
    const countryHint = String(report.ai_search_plan?.country_hint || '').trim();
    const normalized = countryHint.toLocaleLowerCase('hu-HU');
    if (normalized.includes('magyar') || normalized === 'hu') {
        return 'Magyarország';
    }
    if (normalized.includes('english') || normalized.includes('angol') || normalized === 'en') {
        return 'United States';
    }
    return countryHint || 'Magyarország';
}

function loadVisibilityProject(project) {
    currentVisibilityProject = project;
    currentVisibilityRuns = project.latest_run ? [project.latest_run] : [];
    currentVisibilityGa4Imports = project.latest_ga4_import ? [project.latest_ga4_import] : currentVisibilityGa4Imports.filter((item) => item.project_id === project.id);
    currentVisibilitySerpAioImports = project.latest_serp_aio_import ? [project.latest_serp_aio_import] : currentVisibilitySerpAioImports.filter((item) => item.project_id === project.id);
    currentVisibilityGeminiGroundingRuns = project.latest_gemini_grounding_run ? [project.latest_gemini_grounding_run] : currentVisibilityGeminiGroundingRuns.filter((item) => item.project_id === project.id);
    if (visibilityProjectId) visibilityProjectId.value = project.id || '';
    if (visibilitySourceReportId) visibilitySourceReportId.value = project.source_report_id || project.audit_context?.report_id || '';
    if (visibilityAuditContext) visibilityAuditContext.value = project.audit_context ? JSON.stringify(project.audit_context) : '';
    if (document.querySelector('#visibilityName')) document.querySelector('#visibilityName').value = project.name || '';
    if (visibilityUrl) visibilityUrl.value = project.site_url || '';
    if (document.querySelector('#visibilityMarket')) document.querySelector('#visibilityMarket').value = project.market || 'Magyarország';
    if (document.querySelector('#visibilityLanguage')) document.querySelector('#visibilityLanguage').value = project.language || 'hu';
    if (document.querySelector('#visibilityBusinessModel')) document.querySelector('#visibilityBusinessModel').value = project.business_model || 'generic';
    if (document.querySelector('#visibilityQueryLimit')) document.querySelector('#visibilityQueryLimit').value = String(project.query_limit || 12);
    if (document.querySelector('#visibilityTopics')) document.querySelector('#visibilityTopics').value = (project.topics || []).join('\n');
    if (document.querySelector('#visibilityCustomQueries')) document.querySelector('#visibilityCustomQueries').value = (project.custom_queries || []).join('\n');
    if (document.querySelector('#visibilityQueryPortfolio')) document.querySelector('#visibilityQueryPortfolio').value = portfolioToTextarea(project.query_portfolio || []);
    if (document.querySelector('#visibilityCompetitors')) document.querySelector('#visibilityCompetitors').value = (project.competitors || []).join('\n');
    if (runVisibilityButton) runVisibilityButton.disabled = !project.id;
    if (runWeeklyPortfolioButton) runWeeklyPortfolioButton.disabled = !project.id || !(project.query_portfolio || []).length;
    if (exportVisibilityPdfButton) exportVisibilityPdfButton.disabled = !project.latest_run;
    if (importGa4Button) importGa4Button.disabled = !project.id;
    if (importSerpAioButton) importSerpAioButton.disabled = !project.id;
    if (runSerpApiButton) runSerpApiButton.disabled = !project.id;
    if (runGeminiGroundedButton) runGeminiGroundedButton.disabled = !project.id;

    renderVisibilityDashboard(project.latest_run || null, project, false, currentVisibilityRuns);
    renderGa4ImportResult(currentVisibilityGa4Imports);
    renderSerpAioImportResult(currentVisibilitySerpAioImports);
    renderVisibilityAuditSourceNote(project.audit_context || null);
    updateVisibilityJourneyState();
    setVisibilityWizardStep('run', true);
}

async function importGa4Referrals() {
    if (!currentVisibilityProject?.id) {
        setVisibilityStatus('Előbb ments vagy nyiss meg egy visibility projektet.', 'error');
        return;
    }

    const originalLabel = importGa4Button?.textContent || '';
    const formData = new FormData();
    formData.set('project_id', currentVisibilityProject.id);
    formData.set('ga4_csv_text', ga4CsvText?.value || '');
    if (ga4CsvFile?.files?.[0]) {
        formData.set('ga4_csv_file', ga4CsvFile.files[0]);
    }

    if (importGa4Button) {
        importGa4Button.disabled = true;
        importGa4Button.textContent = 'Import...';
    }
    setVisibilityStatus('GA4 AI referral import feldolgozása...', 'neutral');

    try {
        const response = await fetch('api/import_ga4_referrals.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: formData,
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A GA4 import nem sikerült.');
        }

        currentVisibilityGa4Imports = Array.isArray(payload.imports) ? payload.imports : [payload.import].filter(Boolean);
        currentVisibilityProject.latest_ga4_import = currentVisibilityGa4Imports[0] || null;
        if (ga4CsvText) ga4CsvText.value = '';
        if (ga4CsvFile) ga4CsvFile.value = '';
        renderGa4ImportResult(currentVisibilityGa4Imports);
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        updateVisibilityJourneyState();
        setVisibilityStatus('GA4 AI referral import mentve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (importGa4Button) {
            importGa4Button.disabled = !currentVisibilityProject?.id;
            importGa4Button.textContent = originalLabel;
        }
    }
}

function renderGa4ImportResult(imports = []) {
    if (!ga4ImportResult) {
        return;
    }

    const latest = imports[0] || null;
    if (!latest) {
        ga4ImportResult.innerHTML = '<p class="empty-state">Nincs GA4 AI referral import ehhez a projekthez. Exportálj CSV-t GA4-ből source/medium vagy referrer dimenzióval.</p>';
        return;
    }

    ga4ImportResult.innerHTML = renderGa4ReferralSummary(latest, imports);
}

async function importSerpAioEvidence() {
    if (!currentVisibilityProject?.id) {
        setVisibilityStatus('Előbb ments vagy nyiss meg egy visibility projektet.', 'error');
        return;
    }

    const originalLabel = importSerpAioButton?.textContent || '';
    const formData = new FormData();
    formData.set('project_id', currentVisibilityProject.id);
    formData.set('serp_aio_text', serpAioText?.value || '');
    if (serpAioFile?.files?.[0]) {
        formData.set('serp_aio_file', serpAioFile.files[0]);
    }

    if (importSerpAioButton) {
        importSerpAioButton.disabled = true;
        importSerpAioButton.textContent = 'Import...';
    }
    setVisibilityStatus('SERP/AIO export feldolgozása...', 'neutral');

    try {
        const response = await fetch('api/import_serp_aio.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: formData,
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A SERP/AIO import nem sikerült.');
        }

        currentVisibilitySerpAioImports = Array.isArray(payload.imports) ? payload.imports : [payload.import].filter(Boolean);
        currentVisibilityProject.latest_serp_aio_import = currentVisibilitySerpAioImports[0] || null;
        if (serpAioText) serpAioText.value = '';
        if (serpAioFile) serpAioFile.value = '';
        renderSerpAioImportResult(currentVisibilitySerpAioImports);
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        updateVisibilityJourneyState();
        setVisibilityStatus('SERP/AIO bizonyítékimport mentve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (importSerpAioButton) {
            importSerpAioButton.disabled = !currentVisibilityProject?.id;
            importSerpAioButton.textContent = originalLabel;
        }
    }
}

async function runSerpApiEvidenceProbe() {
    if (!currentVisibilityProject?.id) {
        setVisibilityStatus('Előbb ments vagy nyiss meg egy visibility projektet.', 'error');
        return;
    }

    const originalLabel = runSerpApiButton?.textContent || '';
    if (runSerpApiButton) {
        runSerpApiButton.disabled = true;
        runSerpApiButton.textContent = 'SerpApi fut...';
    }
    if (importSerpAioButton) {
        importSerpAioButton.disabled = true;
    }

    setVisibilityStatus('SerpApi live SERP próba fut: 3 projekt-kérdést ellenőrzök Google találatokkal...', 'neutral');

    try {
        const response = await fetch('api/run_serpapi_aio.php', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                project_id: currentVisibilityProject.id,
                query_limit: '3',
            }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A SerpApi live próba nem sikerült.');
        }

        currentVisibilitySerpAioImports = Array.isArray(payload.imports) ? payload.imports : [payload.import].filter(Boolean);
        currentVisibilityProject.latest_serp_aio_import = currentVisibilitySerpAioImports[0] || null;
        renderSerpAioImportResult(currentVisibilitySerpAioImports);
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        updateVisibilityJourneyState();
        setVisibilityStatus('SerpApi live SERP bizonyíték mentve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (runSerpApiButton) {
            runSerpApiButton.disabled = !currentVisibilityProject?.id;
            runSerpApiButton.textContent = originalLabel;
        }
        if (importSerpAioButton) {
            importSerpAioButton.disabled = !currentVisibilityProject?.id;
        }
    }
}

async function runGeminiGroundedProbe() {
    if (!currentVisibilityProject?.id) {
        setVisibilityStatus('Előbb ments vagy nyiss meg egy visibility projektet.', 'error');
        return;
    }

    const originalLabel = runGeminiGroundedButton?.textContent || '';
    if (runGeminiGroundedButton) {
        runGeminiGroundedButton.disabled = true;
        runGeminiGroundedButton.textContent = 'Gemini fut...';
    }
    if (runSerpApiButton) {
        runSerpApiButton.disabled = true;
    }

    setVisibilityStatus('Gemini grounded AI próba fut: 3 projekt-kérdés Google Search groundinggal...', 'neutral');

    try {
        const response = await fetch('api/run_gemini_grounded.php', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                project_id: currentVisibilityProject.id,
                query_limit: '3',
            }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A Gemini grounded próba nem sikerült.');
        }

        currentVisibilityGeminiGroundingRuns = Array.isArray(payload.runs) ? payload.runs : [payload.run].filter(Boolean);
        currentVisibilityProject.latest_gemini_grounding_run = currentVisibilityGeminiGroundingRuns[0] || null;
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        updateVisibilityJourneyState();
        setVisibilityStatus('Gemini grounded AI bizonyíték mentve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
    } finally {
        if (runGeminiGroundedButton) {
            runGeminiGroundedButton.disabled = !currentVisibilityProject?.id;
            runGeminiGroundedButton.textContent = originalLabel;
        }
        if (runSerpApiButton) {
            runSerpApiButton.disabled = !currentVisibilityProject?.id;
        }
    }
}

function renderSerpAioImportResult(imports = []) {
    if (!serpAioImportResult) {
        return;
    }

    const latest = imports[0] || null;
    if (!latest) {
        serpAioImportResult.innerHTML = '<p class="empty-state">Nincs SERP/AIO import ehhez a projekthez. Tölts fel SerpApi/DataForSEO JSON-t vagy query/citation CSV-t.</p>';
        return;
    }

    serpAioImportResult.innerHTML = renderSerpAioSummary(latest, imports);
}

function renderGa4ReferralSummary(importData = {}, imports = [], compact = false) {
    const summary = importData.summary || {};
    const sources = Array.isArray(importData.ai_sources) ? importData.ai_sources : [];
    const warnings = Array.isArray(importData.warnings) ? importData.warnings : [];
    const matchedRows = Array.isArray(importData.matched_rows) ? importData.matched_rows : [];
    const maxSessions = Math.max(1, ...sources.map((item) => Number(item.sessions || 0)));

    return `
        <div class="ga4-summary-grid">
            <section class="ga4-kpi">
                <span>AI session arány</span>
                <strong>${Number(summary.ai_session_share || 0)}%</strong>
                <small>${formatMetric(summary.ai_sessions)} / ${formatMetric(summary.total_sessions)} session</small>
            </section>
            <section class="ga4-kpi">
                <span>AI forrás</span>
                <strong>${Number(summary.detected_source_count || 0)}</strong>
                <small>${Number(summary.matched_rows || 0)} sor egyezett</small>
            </section>
            <section class="ga4-kpi">
                <span>AI felhasználó</span>
                <strong>${formatMetric(summary.ai_users)}</strong>
                <small>${formatMetric(summary.total_users)} összes felhasználó</small>
            </section>
            <section class="ga4-kpi">
                <span>AI konverzió</span>
                <strong>${formatMetric(summary.ai_conversions)}</strong>
                <small>${formatShortDate(importData.created_at)}</small>
            </section>
        </div>
        <div class="ga4-source-list">
            ${sources.slice(0, compact ? 5 : 8).map((source) => `
                <div class="ga4-source-row">
                    <strong>${escapeHtml(source.label || source.key || '')}</strong>
                    <span><i style="width:${Math.max(5, (Number(source.sessions || 0) / maxSessions) * 100)}%"></i></span>
                    <small>${formatMetric(source.sessions)} session</small>
                </div>
            `).join('') || '<p class="empty-state">Nem találtam ismert AI referral mintát.</p>'}
        </div>
        ${!compact && matchedRows.length ? `
            <div class="ga4-row-samples">
                ${matchedRows.slice(0, 4).map((row) => `
                    <article>
                        <span>${escapeHtml(row.source || '')}</span>
                        <strong>${escapeHtml(row.source_medium || row.landing_page || 'GA4 sor')}</strong>
                        <small>${formatMetric(row.sessions)} session · ${formatMetric(row.users)} user · ${formatMetric(row.conversions)} konverzió</small>
                    </article>
                `).join('')}
            </div>
        ` : ''}
        ${warnings.length ? `<div class="ga4-warnings">${warnings.map((warning) => `<p>${escapeHtml(warning)}</p>`).join('')}</div>` : ''}
        ${imports.length > 1 && !compact ? `<small class="ga4-history-note">${Number(imports.length)} mentett GA4 import ehhez a projekthez.</small>` : ''}
    `;
}

function renderSerpAioSummary(importData = {}, imports = [], compact = false) {
    const summary = importData.summary || {};
    const domains = Array.isArray(importData.domain_breakdown) ? importData.domain_breakdown : [];
    const queries = Array.isArray(importData.query_records) ? importData.query_records : [];
    const warnings = Array.isArray(importData.warnings) ? importData.warnings : [];
    const maxMentions = Math.max(1, ...domains.map((item) => Number(item.citation_mentions || 0) + Number(item.organic_mentions || 0)));

    return `
        <div class="serp-summary-grid">
            <section class="serp-kpi">
                <span>AIO jelenlét</span>
                <strong>${Number(summary.aio_presence_rate || 0)}%</strong>
                <small>${Number(summary.aio_present_count || 0)} / ${Number(summary.query_count || 0)} query</small>
            </section>
            <section class="serp-kpi">
                <span>Saját citáció</span>
                <strong>${Number(summary.target_citation_rate || 0)}%</strong>
                <small>${Number(summary.target_cited_count || 0)} queryben idézve</small>
            </section>
            <section class="serp-kpi">
                <span>Saját organikus</span>
                <strong>${Number(summary.target_organic_rate || 0)}%</strong>
                <small>${Number(summary.target_organic_count || 0)} queryben találat</small>
            </section>
            <section class="serp-kpi">
                <span>Versenytárs domain</span>
                <strong>${Number(summary.competitor_domain_count || 0)}</strong>
                <small>${Number(summary.citation_count || 0)} AIO citáció</small>
            </section>
        </div>
        <div class="serp-domain-list">
            ${domains.slice(0, compact ? 6 : 10).map((domain) => {
                const total = Number(domain.citation_mentions || 0) + Number(domain.organic_mentions || 0);
                return `
                    <div class="serp-domain-row ${domain.is_owned ? 'is-owned' : ''}">
                        <strong>${escapeHtml(domain.domain || '')}</strong>
                        <span><i style="width:${Math.max(5, (total / maxMentions) * 100)}%"></i></span>
                        <small>${Number(domain.citation_mentions || 0)} cit. · ${Number(domain.organic_mentions || 0)} org.</small>
                    </div>
                `;
            }).join('') || '<p class="empty-state">Nincs domain bontás az importban.</p>'}
        </div>
        ${!compact && queries.length ? `
            <div class="serp-query-samples">
                ${queries.slice(0, 5).map((item) => `
                    <article>
                        <span>${item.ai_overview_present ? 'AI Overview volt' : 'nincs AIO jel'} · ${item.target_cited ? 'saját citáció' : 'saját citáció nincs'}</span>
                        <strong>${escapeHtml(item.query || '')}</strong>
                        <small>${escapeHtml([
                            item.target_organic ? 'saját organikus találat' : '',
                            (item.competitors || []).length ? `Versenytársak: ${(item.competitors || []).slice(0, 4).join(', ')}` : '',
                        ].filter(Boolean).join(' · ') || 'Nincs részletes jel.')}</small>
                    </article>
                `).join('')}
            </div>
        ` : ''}
        ${warnings.length ? `<div class="ga4-warnings">${warnings.map((warning) => `<p>${escapeHtml(warning)}</p>`).join('')}</div>` : ''}
        ${imports.length > 1 && !compact ? `<small class="ga4-history-note">${Number(imports.length)} mentett SERP/AIO import ehhez a projekthez.</small>` : ''}
    `;
}

function renderGeminiGroundingSummary(run = {}, runs = [], compact = false) {
    const summary = run.summary || {};
    const domains = Array.isArray(summary.domain_breakdown) ? summary.domain_breakdown : [];
    const queries = Array.isArray(run.query_records) ? run.query_records : [];
    const warnings = Array.isArray(run.warnings) ? run.warnings : [];
    const maxMentions = Math.max(1, ...domains.map((item) => Number(item.mentions || 0)));

    return `
        <div class="serp-summary-grid gemini-grounding-grid">
            <section class="serp-kpi">
                <span>Grounded válasz</span>
                <strong>${Number(summary.grounded_rate || 0)}%</strong>
                <small>${Number(summary.grounded_count || 0)} / ${Number(summary.query_count || 0)} query</small>
            </section>
            <section class="serp-kpi">
                <span>Saját említés</span>
                <strong>${Number(summary.target_mention_rate || 0)}%</strong>
                <small>${Number(summary.target_mentioned_count || 0)} queryben említve</small>
            </section>
            <section class="serp-kpi">
                <span>Saját citáció</span>
                <strong>${Number(summary.target_citation_rate || 0)}%</strong>
                <small>${Number(summary.target_cited_count || 0)} queryben idézve</small>
            </section>
            <section class="serp-kpi">
                <span>Versenytárs forrás</span>
                <strong>${Number(summary.competitor_domain_count || 0)}</strong>
                <small>${Number(summary.citation_count || 0)} citáció összesen</small>
            </section>
        </div>
        <div class="serp-domain-list">
            ${domains.slice(0, compact ? 6 : 10).map((domain) => `
                <div class="serp-domain-row ${domain.is_owned ? 'is-owned' : ''}">
                    <strong>${escapeHtml(domain.domain || '')}</strong>
                    <span><i style="width:${Math.max(5, (Number(domain.mentions || 0) / maxMentions) * 100)}%"></i></span>
                    <small>${Number(domain.mentions || 0)} jel</small>
                </div>
            `).join('') || '<p class="empty-state">Nincs forrásdomain bontás a Gemini grounded futásban.</p>'}
        </div>
        ${!compact && queries.length ? `
            <div class="serp-query-samples">
                ${queries.slice(0, 5).map((item) => `
                    <article>
                        <span>${item.target_mentioned ? 'saját említés' : 'saját említés nincs'} · ${item.target_cited ? 'saját citáció' : 'saját citáció nincs'}</span>
                        <strong>${escapeHtml(item.query || '')}</strong>
                        <small>${escapeHtml([
                            (item.search_queries || []).length ? `Google queryk: ${(item.search_queries || []).slice(0, 2).join(', ')}` : '',
                            (item.competitors || []).length ? `Versenytársak: ${(item.competitors || []).slice(0, 4).join(', ')}` : '',
                            (item.citations || []).length ? `Források: ${(item.citations || []).map((source) => source.host).filter(Boolean).slice(0, 4).join(', ')}` : '',
                        ].filter(Boolean).join(' · ') || 'Nincs részletes grounded jel.')}</small>
                    </article>
                `).join('')}
            </div>
        ` : ''}
        ${warnings.length ? `<div class="ga4-warnings">${warnings.map((warning) => `<p>${escapeHtml(warning)}</p>`).join('')}</div>` : ''}
        ${runs.length > 1 && !compact ? `<small class="ga4-history-note">${Number(runs.length)} mentett Gemini grounded futás ehhez a projekthez.</small>` : ''}
    `;
}

function formatMetric(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) {
        return '0';
    }
    return number.toLocaleString('hu-HU', { maximumFractionDigits: number % 1 === 0 ? 0 : 1 });
}

function portfolioToTextarea(portfolio = []) {
    if (!Array.isArray(portfolio)) {
        return '';
    }

    return portfolio.map((item) => {
        const category = item.category && item.category !== 'manual' ? `${item.category}: ` : '';
        return `${category}${item.query || ''}`.trim();
    }).filter(Boolean).join('\n');
}

async function runVisibilityMeasurement(runMode = 'generated') {
    if (!currentVisibilityProject?.id) {
        setVisibilityStatus('Előbb ments vagy nyiss meg egy mérési projektet.', 'error');
        return;
    }

    const activeRunButton = runMode === 'weekly_portfolio' ? runWeeklyPortfolioButton : runVisibilityButton;
    const originalLabel = activeRunButton?.textContent || '';
    const jobId = createJobId();
    if (activeRunButton) {
        activeRunButton.disabled = true;
        activeRunButton.textContent = runMode === 'weekly_portfolio' ? 'Top 20 fut...' : 'Mérés fut...';
    }
    setVisibilityStatus('Kérdéssor futtatása keresési providereken...', 'neutral');
    setVisibilityLoading(true, jobId);
    renderVisibilityDashboard(null, currentVisibilityProject, true);

    try {
        const response = await fetch('api/run_visibility_tracking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            body: new URLSearchParams({
                project_id: currentVisibilityProject.id,
                query_limit: String(runMode === 'weekly_portfolio' ? 20 : Math.max(4, Math.min(20, Number(document.querySelector('#visibilityQueryLimit')?.value || currentVisibilityProject.query_limit || 12)))),
                job_id: jobId,
                include_ai: '1',
                run_mode: runMode,
            }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A láthatóságmérés nem futott le.');
        }

        currentVisibilityProject = { ...payload.project, latest_run: payload.run };
        currentVisibilityRuns = Array.isArray(payload.runs) ? payload.runs : [payload.run];
        renderVisibilityDashboard(payload.run, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        if (exportVisibilityPdfButton) exportVisibilityPdfButton.disabled = false;
        updateVisibilityJourneyState();
        setVisibilityStatus('Láthatóságmérés elkészült', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
        renderVisibilityDashboard(currentVisibilityProject.latest_run || null, currentVisibilityProject, false, currentVisibilityRuns);
    } finally {
        if (activeRunButton) {
            activeRunButton.disabled = false;
            activeRunButton.textContent = originalLabel;
        }
        setVisibilityLoading(false);
    }
}

function setVisibilityLoading(isLoading, jobId = null) {
    if (!visibilityProgressWrap || !visibilityProgressBar || !visibilityProgressText) {
        return;
    }

    visibilityProgressWrap.classList.toggle('hidden', !isLoading);
    if (isLoading) {
        currentVisibilityProgressPercent = 5;
        lastVisibilityProgressAt = 0;
        visibilityProgressBar.style.width = '5%';
        visibilityProgressText.textContent = 'Visibility mérés indítása...';
        startVisibilityProgressPolling(jobId);
        visibilityProgressTimer = window.setInterval(() => {
            currentVisibilityProgressPercent = Math.min(92, currentVisibilityProgressPercent + Math.random() * 1.8);
            visibilityProgressBar.style.width = `${currentVisibilityProgressPercent}%`;
            if (!lastVisibilityProgressAt || Date.now() - lastVisibilityProgressAt > 2600) {
                visibilityProgressText.textContent = estimatedVisibilityProgressMessage(currentVisibilityProgressPercent);
            }
        }, 700);
    } else {
        window.clearInterval(visibilityProgressTimer);
        stopVisibilityProgressPolling();
        visibilityProgressBar.style.width = '100%';
        visibilityProgressText.textContent = 'Visibility mérés elkészült.';
    }
}

function startVisibilityProgressPolling(jobId) {
    stopVisibilityProgressPolling();
    if (!jobId) {
        return;
    }

    const poll = async () => {
        try {
            const response = await fetch(`api/progress.php?job=${encodeURIComponent(jobId)}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const payload = await response.json();
            if (!payload.ok) {
                return;
            }

            const percent = Number(payload.percent || 0);
            currentVisibilityProgressPercent = Math.max(currentVisibilityProgressPercent, Math.max(4, Math.min(100, percent)));
            visibilityProgressBar.style.width = `${currentVisibilityProgressPercent}%`;
            lastVisibilityProgressAt = Date.now();
            visibilityProgressText.textContent = payload.detail
                ? `${payload.phase}: ${payload.detail}`
                : payload.phase || 'Visibility mérés folyamatban...';
            setVisibilityStatus(payload.phase || 'Mérés fut', 'neutral');
        } catch (error) {
            visibilityProgressText.textContent = 'Visibility mérés folyamatban... a részállapot átmenetileg nem érhető el.';
        }
    };

    poll();
    visibilityProgressPollTimer = window.setInterval(poll, 900);
}

function stopVisibilityProgressPolling() {
    if (visibilityProgressPollTimer) {
        window.clearInterval(visibilityProgressPollTimer);
        visibilityProgressPollTimer = null;
    }
}

function estimatedVisibilityProgressMessage(percent) {
    if (percent < 30) {
        return 'Kérdéssor és keresési provider ellenőrzése...';
    }

    if (percent < 66) {
        return 'Több vevői kérdés futtatása, saját-domain és versenytárs találatok gyűjtése...';
    }

    if (percent < 82) {
        return 'Share of voice, átlagos pozíció és megbízhatósági címke számítása...';
    }

    return 'AI stratégiai értelmezés készül. Ez hosszabb lehet, mert konkrét javítási tervet kérünk.';
}

function renderVisibilityDashboard(run, project, isLoading = false, runs = []) {
    if (!visibilityDashboard) {
        return;
    }

    if (isLoading) {
        visibilityDashboard.innerHTML = `
            <article class="visibility-empty-state is-loading">
                <h3>Mérés fut</h3>
                <p>A rendszer több kérdésvariánst futtat, összegyűjti a saját-domain találatokat és kiemeli a domináns versenytársakat.</p>
                <div class="progress-track"><span style="width:72%"></span></div>
            </article>
        `;
        return;
    }

    if (!run) {
        const latestGeminiGroundingRun = project?.latest_gemini_grounding_run || currentVisibilityGeminiGroundingRuns[0] || null;
        visibilityDashboard.innerHTML = `
            <article class="visibility-empty-state">
                <span>${escapeHtml(project?.target_domain || 'domain')}</span>
                <h3>Még nincs futtatott mérés</h3>
                <p>A projekt mentve van. Indítsd el a mérést, és itt jelenik meg a visibility rate, share of voice, versenytárslista és kérdésszintű bontás.</p>
            </article>
            ${latestGeminiGroundingRun ? `
                <article class="visibility-lab gemini-grounding-panel">
                    <div class="recommendation-head">
                        <div>
                            <h3>Gemini grounded AI keresés</h3>
                            <p>Ez a mérés már lefutott, de a teljes visibility dashboardhoz érdemes a normál keresési mérést is elindítani.</p>
                        </div>
                        <span>${formatShortDate(latestGeminiGroundingRun.created_at)}</span>
                    </div>
                    ${renderGeminiGroundingSummary(latestGeminiGroundingRun, currentVisibilityGeminiGroundingRuns, true)}
                </article>
            ` : ''}
        `;
        return;
    }

    const runHistory = Array.isArray(runs) ? runs : [];
    const share = Array.isArray(run.share_of_voice) ? run.share_of_voice : [];
    const competitors = Array.isArray(run.competitors) ? run.competitors : [];
    const queries = Array.isArray(run.query_results) ? run.query_results : [];
    const confidence = run.confidence || {};
    const aiStrategy = run.ai_strategy || {};
    const opportunityBacklog = run.opportunity_backlog || {};
    const evidenceExplanation = run.evidence_explanation || {};
    const resourceSummary = buildVisibilityResourceSummary(run);
    const reportLayers = normalizeVisibilityReportLayers(run, project);
    const latestGa4Import = project?.latest_ga4_import || currentVisibilityGa4Imports[0] || null;
    const latestSerpAioImport = project?.latest_serp_aio_import || currentVisibilitySerpAioImports[0] || null;
    const latestGeminiGroundingRun = project?.latest_gemini_grounding_run || currentVisibilityGeminiGroundingRuns[0] || null;
    const maxMentions = Math.max(1, ...share.map((item) => Number(item.mentions || 0)));

    visibilityDashboard.innerHTML = `
        <article class="visibility-result-hero">
            <div>
                <span>${escapeHtml(run.target_domain || project?.target_domain || '')}</span>
                <h3>${Number(run.visibility_rate || 0)}% AI keresési láthatóság</h3>
                <p>${escapeHtml(run.interpretation || '')}</p>
                <small>${escapeHtml(run.run_mode === 'weekly_portfolio' ? 'Heti Top 20 portfólió' : project?.business_model_label || 'Általános weboldal')} · ${Number((project?.query_portfolio || []).length)} portfólió kérdés · ${Number((project?.custom_queries || []).length)} saját kérdés</small>
            </div>
            <div class="visibility-confidence">
                <strong>${escapeHtml(confidence.level || 'irányadó jel')}</strong>
                <small>${Number(confidence.score || 0)}/100 megbízhatóság</small>
            </div>
        </article>
        <div class="visibility-score-grid">
            <section class="visibility-metric">
                <strong>${Number(run.owned_query_hits || 0)}/${Number(run.query_count || 0)}</strong>
                <span>Saját-domain találat</span>
                <small>kérdésszintű jelenlét</small>
            </section>
            <section class="visibility-metric">
                <strong>${run.average_owned_position ? Number(run.average_owned_position).toFixed(1) : '-'}</strong>
                <span>Átlagos saját pozíció</span>
                <small>csak ahol megjelent</small>
            </section>
            <section class="visibility-metric">
                <strong>${competitors.length}</strong>
                <span>Aktív versenytárs</span>
                <small>${escapeHtml(competitors.slice(0, 3).map((item) => item.domain).join(', ') || 'nincs adat')}</small>
            </section>
            <section class="visibility-metric">
                <strong>${escapeHtml((run.providers || []).join(', ') || 'provider')}</strong>
                <span>Adatforrás</span>
                <small>${escapeHtml(run.method || '')}</small>
            </section>
        </div>
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>Visibility trend</h3>
                    <p>Az ismételt futások mutatják, hogy a javítások után stabilabban látszik-e a domain a fontos kérdésekben.</p>
                </div>
                <span>${runHistory.length} futás</span>
            </div>
            ${renderVisibilityTrend(runHistory)}
        </article>
        <article class="visibility-lab resource-dashboard">
            <div class="recommendation-head">
                <div>
                    <h3>Erőforrás és cache</h3>
                    <p>Megmutatja, mennyi külső keresési hívás történt, mennyi jött cache-ből, és érkezett-e AI tokenadat.</p>
                </div>
                <span>${resourceSummary.totalTokens ? `${resourceSummary.totalTokens.toLocaleString('hu-HU')} token` : `${resourceSummary.search.cacheHitRate}% cache`}</span>
            </div>
            ${renderVisibilityResourceSummary(resourceSummary)}
        </article>
        <article class="visibility-lab report-layer-panel">
            <div class="recommendation-head">
                <div>
                    <h3>Mérés vs javítás</h3>
                    <p>Különválasztjuk a konkrét mérési bizonyítékot és az abból levezetett javítási munkatervet.</p>
                </div>
                <span>${Number(reportLayers.improvement_layer?.action_count || 0)} javítás</span>
            </div>
            ${renderVisibilityReportLayers(reportLayers)}
        </article>
        <article class="visibility-lab ga4-dashboard-panel">
            <div class="recommendation-head">
                <div>
                    <h3>GA4 AI referral valóságcheck</h3>
                    <p>Analitikai import alapján látszik, érkezett-e tényleges forgalom AI-asszisztensekből vagy AI keresőkből.</p>
                </div>
                <span>${latestGa4Import ? formatShortDate(latestGa4Import.created_at) : 'nincs import'}</span>
            </div>
            ${latestGa4Import ? renderGa4ReferralSummary(latestGa4Import, currentVisibilityGa4Imports, true) : '<p class="empty-state">Még nincs GA4 import. A bal oldali GA4 panelen tölts fel source/medium vagy referrer CSV exportot.</p>'}
        </article>
        <article class="visibility-lab serp-dashboard-panel">
            <div class="recommendation-head">
                <div>
                    <h3>Google SERP/AIO bizonyíték</h3>
                    <p>Provider export alapján látszik, volt-e AI Overview, idézte-e a saját domaint, és kik jelentek meg forrásként.</p>
                </div>
                <span>${latestSerpAioImport ? formatShortDate(latestSerpAioImport.created_at) : 'nincs import'}</span>
            </div>
            ${latestSerpAioImport ? renderSerpAioSummary(latestSerpAioImport, currentVisibilitySerpAioImports, true) : '<p class="empty-state">Még nincs SERP/AIO import. A bal oldali SERP/AIO panelen tölts fel SerpApi/DataForSEO JSON-t vagy CSV-t.</p>'}
        </article>
        <article class="visibility-lab gemini-grounding-panel">
            <div class="recommendation-head">
                <div>
                    <h3>Gemini grounded AI keresés</h3>
                    <p>Gemini Google Search grounding alapján látszik, milyen kérdésekre kap grounded választ, idézi-e a saját domaint, és milyen versenytárs források kerülnek a válasz mögé.</p>
                </div>
                <span>${latestGeminiGroundingRun ? formatShortDate(latestGeminiGroundingRun.created_at) : 'nincs futás'}</span>
            </div>
            ${latestGeminiGroundingRun ? renderGeminiGroundingSummary(latestGeminiGroundingRun, currentVisibilityGeminiGroundingRuns, true) : '<p class="empty-state">Még nincs Gemini grounded próba. A bal oldali SERP/AIO panelen indítsd el a Gemini grounded próbát.</p>'}
        </article>
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>Biztos vs irányadó</h3>
                    <p>Ügyfélbarát mérési értelmezés: mi konkrét adat, mi következtetés, és mit nem állít a riport.</p>
                </div>
                <span>${escapeHtml(confidence.level || 'irányadó jel')}</span>
            </div>
            ${renderVisibilityEvidenceBlock(evidenceExplanation)}
        </article>
        <article class="visibility-lab">
            <h3>AI stratégiai értelmezés</h3>
            ${renderVisibilityAiStrategy(aiStrategy)}
        </article>
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>Tartalmi javítási backlog</h3>
                    <p>Automatikus teendőlista azokból a kérdésekből, ahol a saját domain nem látszik vagy nem elég erős.</p>
                </div>
                <span>${Number(opportunityBacklog.summary?.total_actions || 0)} teendő</span>
            </div>
            ${renderVisibilityOpportunityBacklog(opportunityBacklog)}
        </article>
        <article class="visibility-lab">
            <h3>Share of voice a találati térben</h3>
            <div class="sov-list">
                ${share.map((item) => `
                    <div class="sov-row ${item.is_owned ? 'is-owned' : ''}">
                        <strong>${escapeHtml(item.domain || '')}</strong>
                        <span><i style="width:${Math.max(6, (Number(item.mentions || 0) / maxMentions) * 100)}%"></i></span>
                        <small>${Number(item.share || 0)}%</small>
                    </div>
                `).join('') || '<p class="empty-state">Nincs share of voice adat.</p>'}
            </div>
        </article>
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>Korábbi futások</h3>
                    <p>Visszanézhető, melyik mérés milyen mintát adott. A hibás vagy próba futások törölhetők.</p>
                </div>
                <button class="mini-button secondary" type="button" onclick="window.dispatchEvent(new CustomEvent('visibility-export-request'))">PDF</button>
            </div>
            ${renderVisibilityRunHistory(runHistory)}
        </article>
        <article class="visibility-lab">
            <h3>Kérdésszintű eredmények</h3>
            <div class="query-grid">
                ${queries.map((item) => `
                    <section class="query-card search-query-card">
                        <span>${item.owned_domain_hit ? 'saját domain látszik' : 'saját domain nem látszik'} · ${escapeHtml(item.provider || 'provider')} ${item.from_cache ? '· cache' : ''}</span>
                        <h4>${escapeHtml(item.query || '')}</h4>
                        <p>${escapeHtml(item.owned_domain_hit
                            ? `Saját találat: ${(item.own_results || []).map((result) => result.url).slice(0, 2).join(', ')}`
                            : 'Ebben a kérdésben inkább más források dominálnak.')}</p>
                        <small>${escapeHtml([
                            asArray(item.competitors).length ? `Versenytársak: ${asArray(item.competitors).slice(0, 5).join(', ')}` : '',
                            Array.isArray(item.results) && item.results.length ? `Top források: ${item.results.map((result) => result.host).filter(Boolean).slice(0, 4).join(', ')}` : '',
                            item.error || '',
                        ].filter(Boolean).join(' · '))}</small>
                    </section>
                `).join('') || '<p class="empty-state">Nincs kérdésszintű adat.</p>'}
            </div>
        </article>
    `;
    wireVisibilityRunHistory();
}

function renderVisibilityEvidenceBlock(explanation = {}) {
    const hardSignals = Array.isArray(explanation.hard_signals) ? explanation.hard_signals : [];
    const directionalSignals = Array.isArray(explanation.directional_signals) ? explanation.directional_signals : [];
    const notMeasured = Array.isArray(explanation.not_measured) ? explanation.not_measured : [];
    const clientSummary = Array.isArray(explanation.client_summary) ? explanation.client_summary : [];

    if (!hardSignals.length && !directionalSignals.length && !notMeasured.length) {
        return '<p class="empty-state">Ez a magyarázó blokk az újabb visibility futásoknál jelenik meg. Futtasd újra a mérést, és bekerül a riportba.</p>';
    }

    return `
        ${clientSummary.length ? `
            <div class="evidence-summary">
                ${clientSummary.map((item) => `<p>${escapeHtml(item)}</p>`).join('')}
            </div>
        ` : ''}
        <div class="evidence-grid">
            ${renderEvidenceColumn('Biztos adat', hardSignals, 'hard')}
            ${renderEvidenceColumn('Irányadó jel', directionalSignals, 'directional')}
            ${renderEvidenceColumn('Nem direkt mérés', notMeasured.map((item) => ({
                label: 'Korlát',
                value: item,
                detail: 'Ezt a részt érdemes szóban is tisztázni az ügyféllel.'
            })), 'limited')}
        </div>
        ${explanation.recommended_language ? `
            <div class="client-language-box">
                <strong>Riportba illeszthető megfogalmazás</strong>
                <p>${escapeHtml(explanation.recommended_language)}</p>
            </div>
        ` : ''}
    `;
}

function renderEvidenceColumn(title, items = [], tone = 'hard') {
    if (!items.length) {
        return `
            <section class="evidence-card is-${escapeHtml(tone)}">
                <h4>${escapeHtml(title)}</h4>
                <p class="empty-state">Nincs adat ehhez a kategóriához.</p>
            </section>
        `;
    }

    return `
        <section class="evidence-card is-${escapeHtml(tone)}">
            <h4>${escapeHtml(title)}</h4>
            <div class="evidence-list">
                ${items.map((item) => `
                    <article>
                        <span>${escapeHtml(item.label || title)}</span>
                        <strong>${escapeHtml(item.value || '')}</strong>
                        <p>${escapeHtml(item.detail || '')}</p>
                    </article>
                `).join('')}
            </div>
        </section>
    `;
}

function buildVisibilityResourceSummary(run = {}) {
    const backendSearch = run.resource_summary?.search || {};
    const queryResults = Array.isArray(run.query_results) ? run.query_results : [];
    const queryCount = Number(backendSearch.query_count ?? queryResults.length ?? run.query_count ?? 0);
    const cacheHits = Number(backendSearch.cache_hits ?? queryResults.filter((item) => item.from_cache).length ?? 0);
    const providerCalls = Number(backendSearch.provider_calls ?? Math.max(0, queryCount - cacheHits));
    const providerErrors = Number(backendSearch.provider_errors ?? queryResults.filter((item) => item.status === 'error' || item.error).length ?? 0);
    const savedResultCount = Number(backendSearch.saved_result_count ?? queryResults.reduce((sum, item) => sum + (Array.isArray(item.results) ? item.results.length : 0), 0));
    const aiUsage = normalizeUsage(run.resource_summary?.ai?.usage || run.ai_strategy?.usage || {});

    return {
        search: {
            queryCount,
            cacheHits,
            providerCalls,
            providerErrors,
            savedResultCount,
            cacheHitRate: Number(backendSearch.cache_hit_rate ?? (queryCount > 0 ? Math.round((cacheHits / queryCount) * 100) : 0)),
            providers: Array.isArray(backendSearch.providers) ? backendSearch.providers : (run.providers || []),
        },
        ai: {
            status: run.ai_strategy?.status || run.resource_summary?.ai?.status || 'missing',
            provider: run.ai_strategy?.provider || run.resource_summary?.ai?.provider || '',
            model: run.ai_strategy?.model || run.resource_summary?.ai?.model || '',
            usage: aiUsage,
        },
        totalTokens: aiUsage.total,
    };
}

function renderVisibilityResourceSummary(summary) {
    return `
        <div class="resource-grid compact">
            <section class="resource-card">
                <span>Külső keresési hívás</span>
                <strong>${Number(summary.search.providerCalls)}</strong>
                <small>${Number(summary.search.cacheHits)} cache találat</small>
            </section>
            <section class="resource-card">
                <span>Cache hit rate</span>
                <strong>${Number(summary.search.cacheHitRate)}%</strong>
                <small>${Number(summary.search.queryCount)} vizsgált kérdés</small>
            </section>
            <section class="resource-card">
                <span>Mentett találat</span>
                <strong>${Number(summary.search.savedResultCount)}</strong>
                <small>${escapeHtml(summary.search.providers.join(', ') || 'provider')}</small>
            </section>
            <section class="resource-card">
                <span>AI usage</span>
                <strong>${summary.totalTokens ? summary.totalTokens.toLocaleString('hu-HU') : '-'}</strong>
                <small>${escapeHtml([summary.ai.provider, summary.ai.model, resourceStatusLabel(summary.ai.status)].filter(Boolean).join(' · '))}</small>
            </section>
        </div>
        <div class="resource-two-col">
            <section>
                <h4>Keresési cache</h4>
                <div class="resource-bar">
                    <span><i style="width:${Math.max(0, Math.min(100, summary.search.cacheHitRate))}%"></i></span>
                    <small>${Number(summary.search.cacheHits)}/${Number(summary.search.queryCount)} kérdés cache-ből · ${Number(summary.search.providerErrors)} provider hiba</small>
                </div>
            </section>
            <section>
                <h4>AI tokenadat</h4>
                <div class="resource-bar is-owned">
                    <span><i style="width:${summary.ai.usage.total ? 100 : 0}%"></i></span>
                    <small>${summary.ai.usage.total ? `${summary.ai.usage.input.toLocaleString('hu-HU')} input · ${summary.ai.usage.output.toLocaleString('hu-HU')} output${summary.ai.usage.reasoning ? ` · ${summary.ai.usage.reasoning.toLocaleString('hu-HU')} reasoning` : ''}` : 'A provider nem adott tokenadatot vagy az AI értelmezés kimaradt.'}</small>
                </div>
            </section>
        </div>
    `;
}

function normalizeVisibilityReportLayers(run = {}, project = {}) {
    if (run.report_layers?.measurement_layer && run.report_layers?.improvement_layer) {
        return run.report_layers;
    }

    const actions = Array.isArray(run.opportunity_backlog?.actions) ? run.opportunity_backlog.actions : [];
    const queryCount = Number(run.query_count || 0);
    const ownedHits = Number(run.owned_query_hits || 0);
    const visibilityRate = Number(run.visibility_rate || 0);
    const targetDomain = run.target_domain || project?.target_domain || '';

    return {
        title: 'Mérés vs javítás',
        measurement_layer: {
            label: 'Mérési réteg',
            summary: `A futás ${queryCount} kérdés alapján vizsgálta a ${targetDomain || 'vizsgált'} domaint. A saját domain ${ownedHits} kérdésben jelent meg, ami ${visibilityRate}% visibility rate értéket ad ebben a mintában.`,
            status: 'measured',
            facts: [
                { label: 'Vizsgált kérdés', value: String(queryCount), note: 'Futtatott keresési kérdések száma.' },
                { label: 'Saját-domain találat', value: `${ownedHits}/${queryCount}`, note: 'Kérdések, ahol a saját domain megjelent.' },
                { label: 'Visibility rate', value: `${visibilityRate}%`, note: 'Mentett találatokból számolt arány.' },
                { label: 'Adatforrás', value: (run.providers || []).join(', ') || 'provider', note: run.method || '' },
            ],
            confidence: run.confidence || {},
        },
        improvement_layer: {
            label: 'Javítási réteg',
            summary: actions.length
                ? `${actions.length} javítási teendő készült a mérésből.`
                : 'Ehhez a futáshoz nincs külön javítási backlog.',
            status: 'derived',
            action_count: actions.length,
            high_priority_count: actions.filter((action) => action.priority === 'high').length,
            optimization_count: actions.filter((action) => action.status === 'optimize_existing').length,
            top_actions: actions.slice(0, 5),
            strategic_focus: [
                visibilityRate < 35 ? 'Új döntéstámogató tartalmak létrehozása.' : 'Meglévő látható tartalmak erősítése.',
                'Ugyanezzel a kérdésportfólióval érdemes újramérni.',
            ],
        },
        client_framing: {
            measured_sentence: `A mérési rész azt mutatja, hogy a domain ${visibilityRate}%-os láthatóságot ért el ebben a mintában.`,
            derived_sentence: 'A javítási rész ebből vezet le konkrét tartalmi és citációs teendőket.',
            how_to_use: 'A mérési adat bizonyíték, a javítási terv priorizált munkaterv.',
        },
    };
}

function renderVisibilityReportLayers(layers = {}) {
    const measurement = layers.measurement_layer || {};
    const improvement = layers.improvement_layer || {};
    const framing = layers.client_framing || {};
    const facts = Array.isArray(measurement.facts) ? measurement.facts : [];
    const topActions = Array.isArray(improvement.top_actions) ? improvement.top_actions : [];
    const focus = Array.isArray(improvement.strategic_focus) ? improvement.strategic_focus : [];

    return `
        <div class="report-layer-grid">
            <section class="report-layer-card is-measured">
                <div class="layer-kicker">Bizonyíték</div>
                <h4>${escapeHtml(measurement.label || 'Mérési réteg')}</h4>
                <p>${escapeHtml(measurement.summary || '')}</p>
                <div class="layer-fact-grid">
                    ${facts.slice(0, 6).map((fact) => `
                        <article>
                            <span>${escapeHtml(fact.label || '')}</span>
                            <strong>${escapeHtml(fact.value || '')}</strong>
                            <small>${escapeHtml(fact.note || '')}</small>
                        </article>
                    `).join('')}
                </div>
                <div class="layer-note">
                    <strong>${escapeHtml(measurement.confidence?.level || 'irányadó jel')}</strong>
                    <span>${Number(measurement.confidence?.score || 0)}/100 bizonyosság</span>
                </div>
            </section>
            <section class="report-layer-card is-derived">
                <div class="layer-kicker">Munkaterv</div>
                <h4>${escapeHtml(improvement.label || 'Javítási réteg')}</h4>
                <p>${escapeHtml(improvement.summary || '')}</p>
                <div class="layer-action-list">
                    ${topActions.length ? topActions.map((action) => `
                        <article>
                            <span>${escapeHtml(action.priority === 'high' ? 'magas prioritás' : action.priority || 'teendő')}</span>
                            <strong>${escapeHtml(action.title || action.recommended_asset || '')}</strong>
                            <small>${escapeHtml(action.first_step || action.query || '')}</small>
                        </article>
                    `).join('') : '<p class="empty-state">Nincs generált javítási teendő.</p>'}
                </div>
                ${focus.length ? `<div class="section-chip-list">${focus.map((item) => `<span>${escapeHtml(item)}</span>`).join('')}</div>` : ''}
            </section>
        </div>
        <div class="client-language-box">
            <strong>Ügyfélkommunikációs keret</strong>
            <p>${escapeHtml([framing.measured_sentence, framing.derived_sentence, framing.how_to_use].filter(Boolean).join(' '))}</p>
        </div>
    `;
}

function renderVisibilityOpportunityBacklog(backlog = {}) {
    const actions = Array.isArray(backlog.actions) ? backlog.actions : [];
    if (!actions.length) {
        return '<p class="empty-state">Nincs külön tartalmi backlog. A vizsgált kérdésekben a domain vagy megjelent, vagy nincs elég adat teendő generálásához.</p>';
    }

    return `
        <div class="backlog-summary">
            <span>${Number(backlog.summary?.high_priority || 0)} magas prioritás</span>
            <span>${Number(backlog.summary?.optimization_actions || 0)} optimalizálás</span>
        </div>
        <div class="opportunity-list">
            ${actions.map((action) => `
                <article class="opportunity-card ${action.priority === 'high' ? 'is-high' : ''}">
                    <div class="recommendation-head">
                        <div>
                            <span>${escapeHtml(action.priority === 'high' ? 'magas prioritás' : 'közepes prioritás')} · ${escapeHtml(action.recommended_asset || '')}</span>
                            <h4>${escapeHtml(action.title || '')}</h4>
                        </div>
                        <strong>${escapeHtml(action.status === 'missing_asset' ? 'hiányzó tartalom' : 'erősítés')}</strong>
                    </div>
                    <p><strong>Kérdés:</strong> ${escapeHtml(action.query || '')}</p>
                    <p><strong>Javasolt H1:</strong> ${escapeHtml(action.suggested_h1 || '')}</p>
                    <p>${escapeHtml(action.why || '')}</p>
                    <div class="section-chip-list">
                        ${(action.sections || []).map((section) => `<span>${escapeHtml(section)}</span>`).join('')}
                    </div>
                    <small>${escapeHtml([
                        (action.competitors || []).length ? `Versenytársak: ${(action.competitors || []).join(', ')}` : '',
                        (action.top_sources || []).length ? `Forrásminták: ${(action.top_sources || []).map((source) => source.host).filter(Boolean).join(', ')}` : '',
                        action.first_step || '',
                    ].filter(Boolean).join(' · '))}</small>
                </article>
            `).join('')}
        </div>
    `;
}

function renderVisibilityTrend(runs = []) {
    const chronological = [...runs].reverse();
    if (chronological.length < 2) {
        return '<p class="empty-state">A trendhez legalább két futás kell. Futtasd újra később ugyanazzal a projekttel.</p>';
    }

    const width = 620;
    const height = 190;
    const pad = 26;
    const points = chronological.map((run, index) => {
        const x = pad + (index * ((width - pad * 2) / Math.max(1, chronological.length - 1)));
        const y = height - pad - ((Number(run.visibility_rate || 0) / 100) * (height - pad * 2));
        return { x, y, rate: Number(run.visibility_rate || 0), date: formatShortDate(run.created_at) };
    });
    const path = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');

    return `
        <div class="visibility-trend-chart">
            <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Visibility rate trend">
                <line x1="${pad}" y1="${height - pad}" x2="${width - pad}" y2="${height - pad}" />
                <line x1="${pad}" y1="${pad}" x2="${pad}" y2="${height - pad}" />
                <path d="${path}" />
                ${points.map((point) => `
                    <g>
                        <circle cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="5"></circle>
                        <text x="${point.x.toFixed(1)}" y="${Math.max(14, point.y - 10).toFixed(1)}">${point.rate}%</text>
                    </g>
                `).join('')}
            </svg>
            <div class="trend-labels">
                ${points.map((point) => `<span>${escapeHtml(point.date)}</span>`).join('')}
            </div>
        </div>
    `;
}

function renderVisibilityRunHistory(runs = []) {
    if (!runs.length) {
        return '<p class="empty-state">Még nincs mentett futás ehhez a projekthez.</p>';
    }

    return `
        <div class="visibility-run-list">
            ${runs.map((run) => `
                <article class="visibility-run-row" data-run-id="${escapeHtml(run.id || '')}">
                    <div>
                        <strong>${Number(run.visibility_rate || 0)}% · ${escapeHtml(formatShortDate(run.created_at))}</strong>
                        <small>${Number(run.owned_query_hits || 0)}/${Number(run.query_count || 0)} saját találat · ${escapeHtml(run.confidence?.level || 'irányadó jel')}</small>
                    </div>
                    <div class="history-actions">
                        <button class="mini-button secondary visibility-show-run" type="button" data-run-id="${escapeHtml(run.id || '')}">Megnyitás</button>
                        <button class="mini-button danger visibility-delete-run" type="button" data-run-id="${escapeHtml(run.id || '')}">Törlés</button>
                    </div>
                </article>
            `).join('')}
        </div>
    `;
}

function wireVisibilityRunHistory() {
    visibilityDashboard?.querySelectorAll('.visibility-show-run').forEach((button) => {
        button.addEventListener('click', () => {
            const run = currentVisibilityRuns.find((item) => item.id === button.dataset.runId);
            if (run) {
                renderVisibilityDashboard(run, currentVisibilityProject, false, currentVisibilityRuns);
            }
        });
    });

    visibilityDashboard?.querySelectorAll('.visibility-delete-run').forEach((button) => {
        button.addEventListener('click', async () => {
            await deleteVisibilityRun(button.dataset.runId, button);
        });
    });
}

async function deleteVisibilityRun(runId, button) {
    if (!runId || !currentVisibilityProject?.id) {
        setVisibilityStatus('Hiányzó futás azonosító.', 'error');
        return;
    }

    const confirmed = window.confirm('Töröljük ezt a visibility futást? A projekt megmarad.');
    if (!confirmed) {
        return;
    }

    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = 'Törlés...';
    try {
        const response = await fetch('api/delete_visibility_run.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            body: new URLSearchParams({ run_id: runId, project_id: currentVisibilityProject.id }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A futás törlése nem sikerült.');
        }

        currentVisibilityRuns = Array.isArray(payload.runs) ? payload.runs : [];
        currentVisibilityProject = payload.project || currentVisibilityProject;
        renderVisibilityDashboard(currentVisibilityRuns[0] || null, currentVisibilityProject, false, currentVisibilityRuns);
        upsertVisibilityProjectCard(currentVisibilityProject);
        if (exportVisibilityPdfButton) exportVisibilityPdfButton.disabled = !currentVisibilityRuns.length;
        setVisibilityStatus('Visibility futás törölve', 'success');
    } catch (error) {
        setVisibilityStatus(error.message, 'error');
        button.disabled = false;
        button.textContent = originalLabel;
    }
}

window.addEventListener('visibility-export-request', () => {
    if (currentVisibilityProject && currentVisibilityRuns.length) {
        openVisibilityPdfReport(currentVisibilityProject, currentVisibilityRuns[0], currentVisibilityRuns);
    }
});

function renderVisibilityAiStrategy(aiStrategy = {}) {
    if (!aiStrategy || Object.keys(aiStrategy).length === 0) {
        return '<p class="empty-state">Ehhez a futáshoz még nincs AI stratégiai összefoglaló.</p>';
    }

    if (aiStrategy.status !== 'completed') {
        return `
            <div class="ai-inline-note">
                <strong>AI összefoglaló nem készült el</strong>
                <p>${escapeHtml(aiStrategy.message || 'Nincs elérhető AI válasz ehhez a méréshez.')}</p>
                ${aiStrategy.provider ? `<small>${escapeHtml(aiStrategy.provider)} ${aiStrategy.model ? `· ${escapeHtml(aiStrategy.model)}` : ''}</small>` : ''}
            </div>
        `;
    }

    return `
        <div class="recommendation-head">
            <p>${escapeHtml(aiStrategy.provider || 'AI')} ${aiStrategy.model ? `· ${escapeHtml(aiStrategy.model)}` : ''}</p>
            <span>${escapeHtml(aiStrategy.generated_at || '')}</span>
        </div>
        <div class="ai-report visibility-ai-report">${renderAiReport(aiStrategy.analysis || '')}</div>
        ${aiStrategy.fallback_note ? `<small>${escapeHtml(aiStrategy.fallback_note)}</small>` : ''}
    `;
}

function upsertVisibilityProjectCard(project) {
    if (!visibilityProjectList || !project?.id) {
        return;
    }

    visibilityProjectList.querySelector('.empty-state')?.remove();
    const serialized = escapeHtml(JSON.stringify(project));
    const score = project.latest_run ? `${Number(project.latest_run.visibility_rate || 0)}%` : '';
    const sourceLabel = project.source_report_id ? ' · auditból' : '';
    const existing = Array.from(visibilityProjectList.querySelectorAll('.visibility-project-card'))
        .find((card) => {
            try {
                return JSON.parse(card.dataset.project || '{}').id === project.id;
            } catch (error) {
                return false;
            }
        });
    const html = `
        <div>
            <strong>${escapeHtml(project.name || project.target_domain || '')}</strong>
            <small>${escapeHtml(project.target_domain || '')} · ${escapeHtml(project.business_model_label || 'Általános weboldal')}${sourceLabel} · ${(project.topics || []).length} téma · ${(project.query_portfolio || []).length} Top 20 kérdés</small>
        </div>
        <div class="history-actions">
            ${score ? `<span>${score}</span>` : ''}
            <button class="mini-button secondary visibility-load-project" type="button">Megnyitás</button>
        </div>
    `;

    if (existing) {
        existing.dataset.project = JSON.stringify(project);
        existing.innerHTML = html;
        return;
    }

    const card = document.createElement('article');
    card.className = 'visibility-project-card';
    card.dataset.project = JSON.stringify(project);
    card.innerHTML = html;
    const listHead = visibilityProjectList.querySelector('.list-head');
    if (listHead) {
        listHead.insertAdjacentElement('afterend', card);
        return;
    }

    visibilityProjectList.prepend(card);
}

function renderReport(report) {
    currentReport = report;
    results.classList.remove('hidden');
    reportTabs.classList.remove('hidden');

    const score = Number(report.overall_score || 0);
    overallScore.textContent = score;
    overallScore.style.background = `conic-gradient(var(--accent) 0deg, var(--accent) ${score * 3.6}deg, #e8f0ed ${score * 3.6}deg)`;
    scoreLabel.textContent = report.summary?.label || 'Elemzés kész';
    const mappedUrls = Number(report.site_map?.discovered_url_count || report.summary?.pages_checked || 0);
    scoreSummary.textContent = `${mappedUrls} URL feltérképezve, ${report.summary?.pages_checked || 0} oldal részletesen elemezve. Sürgős teendők: ${report.summary?.critical_count || 0}, fontos javítások: ${report.summary?.warning_count || 0}.`;

    renderMetrics(report.scores || {});
    renderRecommendations(report.recommendations || []);
    renderAiAnalysis(report.ai_enrichment || {}, report.openrouter_enrichment || {}, report.gemini_enrichment || {});
    renderVisibilityLab(report.ai_search_plan || {}, report.ai_enrichment?.visibility_probe || {}, report.openrouter_enrichment?.online_probe || {}, report.saved_search_probe || {});
    renderResourceDashboard(report);
    renderPages(report.pages || []);
    renderSignals(report.pages || []);
    renderMethodology(report.methodology || {});
    renderGlossary(report.methodology?.glossary || {});
}

async function loadSavedReport(reportId, triggerButton, options = {}) {
    const originalLabel = triggerButton.textContent;
    triggerButton.disabled = true;
    triggerButton.textContent = 'Betöltés...';
    setStatus('Riport visszatöltése...', 'neutral');

    try {
        const response = await fetch(`api/get_report.php?id=${encodeURIComponent(reportId)}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A riport nem tölthető be.');
        }

        if (options.render !== false) {
            renderReport(payload.report);
            setStatus('Korábbi riport betöltve', 'success');
        }
        if (options.scrollToReport) {
            switchAppView('audit', true);
            results?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        return payload.report;
    } catch (error) {
        setStatus(error.message, 'error');
        return null;
    } finally {
        triggerButton.disabled = false;
        triggerButton.textContent = originalLabel;
    }
}

async function deleteSavedReport(reportId, triggerButton) {
    const card = triggerButton.closest('.history-item');
    const title = card?.querySelector('strong')?.textContent || 'ezt a riportot';
    const confirmed = window.confirm(`Biztosan törlöd ezt az elemzést?\n\n${title}`);
    if (!confirmed) {
        return;
    }

    const originalLabel = triggerButton.textContent;
    triggerButton.disabled = true;
    triggerButton.textContent = 'Törlés...';
    setStatus('Riport törlése...', 'neutral');

    try {
        const response = await fetch('api/delete_report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            body: new URLSearchParams({ id: reportId }),
        });
        const payload = await response.json();
        if (!payload.ok) {
            throw new Error(payload.message || 'A riport törlése nem sikerült.');
        }

        if (currentReport?.id === reportId) {
            currentReport = null;
            results?.classList.add('hidden');
            reportTabs?.classList.add('hidden');
        }

        card?.remove();
        ensureHistoryEmptyState();
        setStatus('Riport törölve', 'success');
    } catch (error) {
        setStatus(error.message, 'error');
        triggerButton.disabled = false;
        triggerButton.textContent = originalLabel;
    }
}

function ensureHistoryEmptyState() {
    if (!historyList || historyList.querySelector('.history-item')) {
        return;
    }

    historyList.innerHTML = '<p class="empty-state">Még nincs mentett riport. Indítsd el az első auditot.</p>';
}

function renderMetrics(scores) {
    metricList.innerHTML = '';

    Object.entries(metricLabels).forEach(([key, label]) => {
        const value = Number(scores[key] || 0);
        const item = document.createElement('div');
        item.className = 'metric';
        item.innerHTML = `
            <div class="metric-row">
                <span>${escapeHtml(label)}</span>
                <span>${value}/100</span>
            </div>
            <div class="metric-bar" aria-hidden="true"><span style="width:${value}%"></span></div>
        `;
        metricList.appendChild(item);
    });
}

function renderRecommendations(recommendations) {
    recommendationPanel.innerHTML = '';

    if (!recommendations.length) {
        recommendationPanel.innerHTML = '<p class="empty-state">Nem találtunk kiemelt javaslatot.</p>';
        return;
    }

    recommendations.forEach((item) => {
        const node = recommendationTemplate.content.firstElementChild.cloneNode(true);
        node.dataset.level = item.level;
        node.querySelector('h3').textContent = item.title;
        node.querySelector('span').textContent = `${levelLabel(item.level)} · ${item.category || 'Audit'} · ${item.count} oldal`;
        node.querySelector('.impact').textContent = item.impact;
        node.querySelector('.why').textContent = item.why || 'Ez a pont rontja, hogy a felhasználó és az AI rendszer gyorsan megértse az oldal szerepét.';
        node.querySelector('.fix').textContent = item.fix;
        node.querySelector('.next-step').textContent = item.next_step || 'Első lépésként priorizáld ezt a javítást, majd ellenőrizd új audit futtatásával.';
        node.querySelector('.pages').textContent = `Érintett oldalak: ${(item.pages || []).join(', ')}`;
        recommendationPanel.appendChild(node);
    });
}

function renderAiAnalysis(ai, openrouter = {}, gemini = {}) {
    if (!aiPanel) {
        return;
    }

    if (!ai.enabled) {
        aiPanel.innerHTML = `
            <article class="ai-box">
                <h3>AI részletes elemzés még nincs bekapcsolva</h3>
                <p>${escapeHtml(ai.message || 'A szerverre feltöltéskor lehet bekapcsolni az OpenAI kulcs biztonságos beállításával.')}</p>
                <small>Az alap audit és az llms.txt generátor kulcs nélkül is működik.</small>
            </article>
            ${renderOpenRouterAnalysis(openrouter)}
            ${renderGeminiAnalysis(gemini)}
            ${renderLlmsGeneratorBox()}
        `;
        wireLlmsGenerator();
        return;
    }

    if (ai.status !== 'completed') {
        aiPanel.innerHTML = `
            <article class="ai-box">
                <h3>AI részletes elemzés nem készült el</h3>
                <p>${escapeHtml(ai.message || 'Az API nem adott feldolgozható választ.')}</p>
                <small>Modell: ${escapeHtml(ai.model || 'ismeretlen')}</small>
            </article>
            ${renderOpenRouterAnalysis(openrouter)}
            ${renderGeminiAnalysis(gemini)}
            ${renderLlmsGeneratorBox()}
        `;
        wireLlmsGenerator();
        return;
    }

    aiPanel.innerHTML = `
        <article class="ai-box">
            <div class="recommendation-head">
                <h3>AI által rendezett javítási terv</h3>
                <span>${escapeHtml(ai.model || 'OpenAI')}</span>
            </div>
            <div class="ai-report">${renderAiReport(ai.analysis || '')}</div>
        </article>
        ${renderOpenRouterAnalysis(openrouter)}
        ${renderGeminiAnalysis(gemini)}
        ${renderLlmsGeneratorBox()}
    `;
    wireLlmsGenerator();
}

function renderGeminiAnalysis(gemini) {
    if (!gemini || Object.keys(gemini).length === 0) {
        return `
            <article class="ai-box gemini-box">
                <h3>Gemini kontrollvélemény</h3>
                <p>A Gemini modul még nem adott adatot ehhez a riporthoz.</p>
            </article>
        `;
    }

    if (!gemini.enabled) {
        return `
            <article class="ai-box gemini-box">
                <h3>Gemini kontrollvélemény nincs bekapcsolva</h3>
                <p>${escapeHtml(gemini.message || 'Nincs beállított Gemini kulcs.')}</p>
            </article>
        `;
    }

    if (gemini.status !== 'completed') {
        return `
            <article class="ai-box gemini-box">
                <div class="recommendation-head">
                    <h3>Gemini kontrollvélemény nem készült el</h3>
                    <span>${escapeHtml(gemini.model || 'Gemini')}</span>
                </div>
                <p>${escapeHtml(gemini.message || 'A Gemini modell nem adott feldolgozható választ.')}</p>
            </article>
        `;
    }

    const usage = gemini.usage || {};
    const usageText = usage.total_tokens
        ? `${Number(usage.total_tokens || 0)} token · ${Number(usage.input_tokens || 0)} input · ${Number(usage.output_tokens || 0)} output`
        : 'tokenadat nem érkezett';

    return `
        <article class="ai-box gemini-box">
            <div class="recommendation-head">
                <div>
                    <h3>Gemini kontrollvélemény</h3>
                    <p>Google/Gemini szemléletű audit-kontroll. Audit JSON és keresési bizonyíték alapján ad irányadó javítási tervet.</p>
                </div>
                <span>${escapeHtml(gemini.model || 'Gemini')}</span>
            </div>
            <div class="ai-report">${renderAiReport(gemini.analysis || '')}</div>
            <small>${escapeHtml(usageText)}</small>
        </article>
    `;
}

function renderOpenRouterAnalysis(openrouter) {
    if (!openrouter || Object.keys(openrouter).length === 0) {
        return `
            <article class="ai-box openrouter-box">
                <h3>OpenRouter gyors másodvélemény</h3>
                <p>Az OpenRouter modul még nem adott adatot ehhez a riporthoz.</p>
            </article>
        `;
    }

    if (!openrouter.enabled) {
        return `
            <article class="ai-box openrouter-box">
                <h3>OpenRouter gyors másodvélemény nincs bekapcsolva</h3>
                <p>${escapeHtml(openrouter.message || 'Nincs beállított OpenRouter kulcs.')}</p>
            </article>
        `;
    }

    if (openrouter.status !== 'completed') {
        return `
            <article class="ai-box openrouter-box">
                <div class="recommendation-head">
                    <h3>OpenRouter gyors másodvélemény nem készült el</h3>
                    <span>${escapeHtml(openrouter.model || 'OpenRouter')}</span>
                </div>
                <p>${escapeHtml(openrouter.message || 'Az OpenRouter modell nem adott feldolgozható választ.')}</p>
            </article>
        `;
    }

    const usage = openrouter.usage || {};
    const usageText = usage.total_tokens
        ? `${Number(usage.total_tokens || 0)} token · ${Number(usage.prompt_tokens || 0)} input · ${Number(usage.completion_tokens || 0)} output`
        : 'tokenadat nem érkezett';

    return `
        <article class="ai-box openrouter-box">
            <div class="recommendation-head">
                <div>
                    <h3>OpenRouter gyors másodvélemény</h3>
                    <p>Ingyenes/olcsó modellből érkező kontrollréteg. Nem live web_search mérés, hanem audit JSON alapú második vélemény.</p>
                </div>
                <span>${escapeHtml(openrouter.model || 'OpenRouter')}</span>
            </div>
            <div class="ai-report">${renderAiReport(openrouter.analysis || '')}</div>
            <small>${escapeHtml(usageText)}</small>
        </article>
    `;
}

function renderResourceDashboard(report = {}) {
    if (!resourcesPanel) {
        return;
    }

    const summary = buildAuditResourceSummary(report);
    resourcesPanel.innerHTML = `
        <article class="visibility-lab resource-dashboard">
            <div class="recommendation-head">
                <div>
                    <h3>Cost / token / cache dashboard</h3>
                    <p>Átlátható működési nézet: melyik AI réteg futott, mennyi tokenadat érkezett, és a keresési találatokból mennyi jött cache-ből.</p>
                </div>
                <span>${summary.totalTokens ? `${summary.totalTokens.toLocaleString('hu-HU')} token` : 'tokenadat részleges'}</span>
            </div>
            <div class="resource-grid">
                <section class="resource-card">
                    <span>AI tokenhasználat</span>
                    <strong>${summary.totalTokens ? summary.totalTokens.toLocaleString('hu-HU') : '-'}</strong>
                    <small>${summary.aiCompleted}/${summary.aiLayers.length} AI réteg adott kész választ</small>
                </section>
                <section class="resource-card">
                    <span>Keresési cache</span>
                    <strong>${summary.search.cacheHitRate}%</strong>
                    <small>${summary.search.cacheHits}/${summary.search.queryCount} kérdés cache-ből</small>
                </section>
                <section class="resource-card">
                    <span>Provider hívás</span>
                    <strong>${summary.search.providerCalls}</strong>
                    <small>${escapeHtml(summary.search.providers.join(', ') || 'nincs provider')}</small>
                </section>
                <section class="resource-card">
                    <span>Hiba / hiány</span>
                    <strong>${summary.issueCount}</strong>
                    <small>API vagy provider figyelmeztetés</small>
                </section>
            </div>
            <div class="resource-two-col">
                <section>
                    <h4>AI rétegek</h4>
                    <div class="resource-list">
                        ${summary.aiLayers.map(renderResourceAiRow).join('')}
                    </div>
                </section>
                <section>
                    <h4>Keresési adatgyűjtés</h4>
                    ${renderSearchResourcePanel(summary.search)}
                </section>
            </div>
            ${summary.notes.length ? `
                <div class="resource-notes">
                    ${summary.notes.map((note) => `<p>${escapeHtml(note)}</p>`).join('')}
                </div>
            ` : ''}
        </article>
    `;
}

function buildAuditResourceSummary(report = {}) {
    const aiLayers = [
        buildAiLayerSummary('OpenAI elemzés', report.ai_enrichment),
        buildAiLayerSummary('OpenAI live keresési próba', report.ai_enrichment?.visibility_probe),
        buildAiLayerSummary('OpenRouter másodvélemény', report.openrouter_enrichment),
        buildAiLayerSummary('OpenRouter online keresési próba', report.openrouter_enrichment?.online_probe),
        buildAiLayerSummary('Gemini kontrollvélemény', report.gemini_enrichment),
    ];
    const totalTokens = aiLayers.reduce((sum, item) => sum + Number(item.usage.total || 0), 0);
    const search = buildSearchResourceSummary(report.saved_search_probe || {});
    const issueCount = aiLayers.filter((item) => ['error', 'empty', 'raw'].includes(item.status)).length + Number(search.errors || 0);
    const notes = [];

    if (!totalTokens) {
        notes.push('Nem minden provider küld tokenadatot. Ez nem feltétlen hiba, de költségbecslésnél csak az ismert usage mezőkre lehet támaszkodni.');
    }
    if (search.cacheHitRate > 0) {
        notes.push('A cache találatok csökkentik a külső keresési hívásokat, ezért gyorsabb és stabilabb lehet az ismételt mérés.');
    }
    if (search.errors > 0) {
        notes.push('Volt keresési provider hiba. Ilyenkor érdemes később újramérni, vagy másodlagos providert beállítani.');
    }

    return {
        aiLayers,
        aiCompleted: aiLayers.filter((item) => item.status === 'completed').length,
        totalTokens,
        search,
        issueCount,
        notes,
    };
}

function buildAiLayerSummary(label, layer = {}) {
    const usage = normalizeUsage(layer?.usage || {});
    return {
        label,
        enabled: layer?.enabled !== false,
        status: layer?.status || (layer && Object.keys(layer).length ? 'unknown' : 'missing'),
        provider: layer?.provider || label.split(' ')[0].toLowerCase(),
        model: layer?.model || '',
        generatedAt: layer?.generated_at || '',
        message: layer?.message || layer?.limitation || '',
        usage,
    };
}

function normalizeUsage(usage = {}) {
    const input = Number(usage.input_tokens ?? usage.prompt_tokens ?? 0);
    const output = Number(usage.output_tokens ?? usage.completion_tokens ?? 0);
    const reasoning = Number(usage.reasoning_tokens ?? usage.reasoningTokens ?? usage.output_tokens_details?.reasoning_tokens ?? 0);
    const total = Number(usage.total_tokens ?? usage.total ?? (input + output));
    return { input, output, reasoning, total };
}

function buildSearchResourceSummary(probe = {}) {
    const queries = Array.isArray(probe.query_results) ? probe.query_results : [];
    const queryCount = Number(probe.query_count || queries.length || 0);
    const cacheHits = queries.filter((item) => item.from_cache).length;
    const providerCalls = Math.max(0, queryCount - cacheHits);
    const errors = Number((probe.errors || []).length || queries.filter((item) => item.status === 'error' || item.error).length || 0);
    const providers = Array.isArray(probe.providers) ? probe.providers : [];

    return {
        status: probe.status || 'missing',
        providers,
        queryCount,
        cacheHits,
        providerCalls,
        cacheHitRate: queryCount > 0 ? Math.round((cacheHits / queryCount) * 100) : 0,
        errors,
        cacheTtlHours: Number(probe.cache_ttl_hours || 0),
        hitRate: Number(probe.retrieval_hit_rate || 0),
        ownedHits: Number(probe.owned_domain_query_hits || 0),
        message: probe.message || probe.analysis_hint || '',
    };
}

function renderResourceAiRow(item) {
    const statusLabel = resourceStatusLabel(item.status);
    const usageText = item.usage.total
        ? `${item.usage.total.toLocaleString('hu-HU')} token · ${item.usage.input.toLocaleString('hu-HU')} input · ${item.usage.output.toLocaleString('hu-HU')} output${item.usage.reasoning ? ` · ${item.usage.reasoning.toLocaleString('hu-HU')} reasoning` : ''}`
        : 'tokenadat nem érkezett';

    return `
        <article class="resource-row">
            <div>
                <strong>${escapeHtml(item.label)}</strong>
                <small>${escapeHtml([item.model, usageText].filter(Boolean).join(' · '))}</small>
                ${item.message ? `<p>${escapeHtml(item.message)}</p>` : ''}
            </div>
            <span class="resource-status is-${escapeHtml(item.status)}">${escapeHtml(statusLabel)}</span>
        </article>
    `;
}

function renderSearchResourcePanel(search) {
    return `
        <div class="resource-list">
            <article class="resource-row">
                <div>
                    <strong>${escapeHtml(search.providers.join(', ') || 'Keresési provider nincs beállítva')}</strong>
                    <small>${Number(search.queryCount)} kérdés · ${Number(search.providerCalls)} külső hívás · ${Number(search.cacheHits)} cache találat</small>
                    ${search.message ? `<p>${escapeHtml(search.message)}</p>` : ''}
                </div>
                <span class="resource-status is-${escapeHtml(search.status)}">${escapeHtml(resourceStatusLabel(search.status))}</span>
            </article>
            <div class="resource-bar">
                <span><i style="width:${Math.max(0, Math.min(100, search.cacheHitRate))}%"></i></span>
                <small>Cache hit rate: ${Number(search.cacheHitRate)}% · TTL: ${Number(search.cacheTtlHours)} óra</small>
            </div>
            <div class="resource-bar is-owned">
                <span><i style="width:${Math.max(0, Math.min(100, search.hitRate))}%"></i></span>
                <small>Saját-domain találati arány: ${Number(search.hitRate)}% · ${Number(search.ownedHits)}/${Number(search.queryCount)} kérdés</small>
            </div>
        </div>
    `;
}

function resourceStatusLabel(status) {
    const labels = {
        completed: 'kész',
        skipped: 'kihagyva',
        error: 'hiba',
        setup_required: 'beállítás kell',
        empty: 'üres',
        raw: 'nyers válasz',
        missing: 'nincs adat',
        pending: 'folyamatban',
        unknown: 'ismeretlen',
    };
    return labels[status] || status || 'nincs adat';
}

function renderVisibilityLab(plan, probe, openrouterProbe = {}, savedSearchProbe = {}) {
    if (!visibilityPanel) {
        return;
    }

    const queries = plan.query_set || [];
    const readiness = plan.static_readiness || {};
    const probeResult = probe?.result || {};
    const queryResults = probeResult.query_results || [];
    const competitors = Array.isArray(probeResult.competitors) ? probeResult.competitors : [];

    visibilityPanel.innerHTML = `
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>AI Search Visibility Lab</h3>
                    <p>Ez a tényleges AI láthatósági mérési réteg: nem csak azt nézi, jó-e az oldal, hanem azt is, milyen vevői kérdésekre kell futtatni AI keresési próbát, és megjelenik-e a brand vagy domain a válaszban.</p>
                </div>
                <span>${escapeHtml(plan.domain || 'domain')}</span>
            </div>
            <div class="visibility-score-grid">
                ${visibilityMetricCard('Citation selection', readiness.citation_selection_readiness, 'Forrásként kiválasztási alkalmasság')}
                ${visibilityMetricCard('Answer absorption', readiness.citation_absorption_readiness, 'Válaszba átvehető bizonyíték')}
                ${visibilityMetricCard('Raw HTML arány', readiness.interpretable_pages_ratio, 'AI crawler számára olvasható oldalak')}
                ${visibilityMetricCard('Bizonyíték arány', readiness.evidence_pages_ratio, 'Adatot/forrást tartalmazó oldalak')}
            </div>
            <p class="site-map-note-text">${escapeHtml(plan.implementation_note || readiness.note || '')}</p>
        </article>
        ${renderSavedSearchProbe(savedSearchProbe)}
        ${renderProbeResult(probe, probeResult, queryResults, competitors, 'OpenAI web_search próba eredménye')}
        ${renderProbeResult(openrouterProbe, openrouterProbe?.result || {}, openrouterProbe?.result?.query_results || [], Array.isArray(openrouterProbe?.result?.competitors) ? openrouterProbe.result.competitors : [], 'OpenRouter online keresési próba')}
        <article class="visibility-lab">
            <h3>Mérési kérdéssor</h3>
            <p>Ezeket a kérdéseket érdemes OpenAI web_search, ChatGPT Search, Gemini, Perplexity és Google AI Mode környezetben ismételni. A cél a coverage rate, citation rate, versenytárs co-mention és narratíva pontosság mérése.</p>
            <div class="query-grid">
                ${queries.map((item) => `
                    <section class="query-card">
                        <span>${escapeHtml(item.type || 'query')}</span>
                        <h4>${escapeHtml(item.query || '')}</h4>
                        <p>${escapeHtml(item.why || '')}</p>
                        <small>${escapeHtml(item.expected_signal || '')}</small>
                    </section>
                `).join('') || '<p class="empty-state">Nincs generált kérdéssor.</p>'}
            </div>
        </article>
    `;
}

function renderSavedSearchProbe(probe = {}) {
    if (!probe || Object.keys(probe).length === 0) {
        return `
            <article class="ai-box">
                <h3>Mentett keresési adatgyűjtés</h3>
                <p>Ehhez a riporthoz még nincs külön keresési cache adat.</p>
            </article>
        `;
    }

    if (!probe.enabled) {
        return `
            <article class="ai-box">
                <h3>Mentett keresési adatgyűjtés kikapcsolva</h3>
                <p>${escapeHtml(probe.message || 'A provider-adapter jelenleg nem fut.')}</p>
            </article>
        `;
    }

    if (probe.status === 'setup_required') {
        return `
            <article class="visibility-lab search-cache-lab">
                <div class="recommendation-head">
                    <div>
                        <h3>Mentett keresési adatgyűjtés</h3>
                        <p>${escapeHtml(probe.message || 'Keresési provider beállítás szükséges.')}</p>
                    </div>
                    <span>provider kell</span>
                </div>
                <div class="provider-list">
                    ${(probe.supported_providers || []).map((provider) => `<span>${escapeHtml(provider)}</span>`).join('')}
                </div>
                <p class="site-map-note-text">A javasolt első lépés egy self-hosted SearXNG URL beállítása, vagy LangSearch/Jina API kulcs megadása a védett data/search_config.php fájlban.</p>
            </article>
        `;
    }

    if (probe.status === 'error') {
        return `
            <article class="ai-box">
                <h3>Mentett keresési adatgyűjtés nem futott le</h3>
                <p>${escapeHtml(probe.message || 'A keresési provider nem adott választ.')}</p>
            </article>
        `;
    }

    const queryResults = Array.isArray(probe.query_results) ? probe.query_results : [];
    const competitors = Array.isArray(probe.competitors) ? probe.competitors : [];
    const providerText = Array.isArray(probe.providers) && probe.providers.length ? probe.providers.join(', ') : 'provider';

    return `
        <article class="visibility-lab search-cache-lab">
            <div class="recommendation-head">
                <div>
                    <h3>Mentett keresési adatgyűjtés</h3>
                    <p>${escapeHtml(probe.analysis_hint || 'Klasszikus keresési találatok cache-elve, hogy az AI elemzés konkrét forrásadatból dolgozzon.')}</p>
                </div>
                <span>${escapeHtml(providerText)}</span>
            </div>
            <div class="visibility-score-grid">
                ${visibilityMetricCard('Saját-domain találati arány', probe.retrieval_hit_rate, 'Kérdések, ahol előkerült a domain')}
                <section class="visibility-metric">
                    <strong>${Number(probe.owned_domain_query_hits || 0)}</strong>
                    <span>Saját találat</span>
                    <small>${Number(probe.query_count || 0)} kérdésből</small>
                </section>
                <section class="visibility-metric">
                    <strong>${competitors.length}</strong>
                    <span>Versenytárs domain</span>
                    <small>${escapeHtml(competitors.slice(0, 3).join(', ') || 'nincs adat')}</small>
                </section>
                <section class="visibility-metric">
                    <strong>${Number(probe.max_results_per_query || 0)}</strong>
                    <span>Találat/kérdés</span>
                    <small>${Number(probe.cache_ttl_hours || 0)} órás cache</small>
                </section>
            </div>
            <div class="query-grid">
                ${queryResults.map((item) => {
                    const ownResults = Array.isArray(item.own_results) ? item.own_results : [];
                    const topResults = Array.isArray(item.results) ? item.results : [];
                    return `
                        <section class="query-card search-query-card">
                            <span>${item.owned_domain_hit ? 'saját domain látszik' : 'saját domain nem látszik'} · ${escapeHtml(item.provider || 'provider')} ${item.from_cache ? '· cache' : ''}</span>
                            <h4>${escapeHtml(item.query || item.id || '')}</h4>
                            <p>${ownResults.length
                                ? `Saját találat: ${escapeHtml(ownResults.map((result) => result.url).slice(0, 2).join(', '))}`
                                : 'Erre a kérdésre a mentett találatok között nem jelent meg saját-domain URL.'}</p>
                            <small>${escapeHtml([
                                asArray(item.competitors).length ? `Versenytársak: ${asArray(item.competitors).slice(0, 5).join(', ')}` : '',
                                topResults.length ? `Top források: ${topResults.map((result) => result.host).filter(Boolean).slice(0, 4).join(', ')}` : '',
                                item.error || '',
                            ].filter(Boolean).join(' · '))}</small>
                        </section>
                    `;
                }).join('') || '<p class="empty-state">Nincs kérdésszintű keresési adat.</p>'}
            </div>
        </article>
    `;
}

function visibilityMetricCard(title, value, caption) {
    const number = toPercentNumber(value);
    return `
        <section class="visibility-metric">
            <strong>${Math.max(0, Math.min(100, number))}%</strong>
            <span>${escapeHtml(title)}</span>
            <small>${escapeHtml(caption)}</small>
        </section>
    `;
}

function toPercentNumber(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    const match = String(value || '').match(/\d+(?:[.,]\d+)?/);
    if (!match) {
        return 0;
    }

    return Number(match[0].replace(',', '.')) || 0;
}

function renderProbeResult(probe, result, queryResults, competitors, title = 'Live AI keresési próba') {
    if (!probe?.enabled) {
        return `
            <article class="ai-box">
                <h3>${escapeHtml(title)} nincs bekapcsolva</h3>
                <p>${escapeHtml(probe?.message || 'A kérdéssor mérési protokollként használható, online keresés nélkül is.')}</p>
            </article>
        `;
    }

    if (probe.status === 'error') {
        return `
            <article class="ai-box">
                <h3>${escapeHtml(title)} nem készült el</h3>
                <p>${escapeHtml(probe.message || 'Az OpenAI web_search próba nem adott feldolgozható választ.')}</p>
                <small>${escapeHtml(probe.method || probe.model || 'OpenAI')}</small>
            </article>
        `;
    }

    if (probe.status !== 'completed') {
        return `
            <article class="ai-box">
                <h3>Live próba nyers eredménnyel</h3>
                <p>${escapeHtml(probe.limitation || 'A válasz nem volt strukturált JSON, ezért nyers megfigyelésként jelenik meg.')}</p>
                <pre class="raw-probe">${escapeHtml(probe.raw || probe.message || '')}</pre>
            </article>
        `;
    }

    return `
        <article class="visibility-lab">
            <div class="recommendation-head">
                <div>
                    <h3>${escapeHtml(title)}</h3>
                    <p>${escapeHtml(result.summary || probe.limitation || '')}</p>
                </div>
                <span>${escapeHtml(probe.method || probe.model || 'AI keresés')}</span>
            </div>
            <div class="visibility-score-grid">
                ${visibilityMetricCard('Coverage rate', result.coverage_rate, 'Márka/domain említés')}
                ${visibilityMetricCard('Citation rate', result.citation_rate, 'Saját domain forrásként')}
                <section class="visibility-metric">
                    <strong>${Number(result.owned_domain_mentions || 0)}</strong>
                    <span>Domain említés</span>
                    <small>Futott kérdésekben</small>
                </section>
                <section class="visibility-metric">
                    <strong>${competitors.length}</strong>
                    <span>Versenytárs</span>
                    <small>${escapeHtml(competitors.slice(0, 3).join(', ') || 'nincs adat')}</small>
                </section>
            </div>
            <div class="query-grid">
                ${queryResults.map((item) => `
                    <section class="query-card">
                        <span>${item.brand_mentioned ? 'brand említve' : 'brand nem látszik'} · ${item.domain_cited ? 'domain idézve' : 'nincs saját idézet'}</span>
                        <h4>${escapeHtml(item.query || item.id || '')}</h4>
                        <p>${escapeHtml(item.answer_summary || '')}</p>
                        <small>${escapeHtml([
                            asArray(item.competitors).length ? `Versenytársak: ${asArray(item.competitors).join(', ')}` : '',
                            asArray(item.cited_urls).length ? `Idézett URL-ek: ${asArray(item.cited_urls).slice(0, 3).join(', ')}` : '',
                            item.risk || '',
                        ].filter(Boolean).join(' · '))}</small>
                    </section>
                `).join('') || '<p class="empty-state">Nincs kérdésszintű eredmény.</p>'}
            </div>
        </article>
    `;
}

function asArray(value) {
    if (Array.isArray(value)) {
        return value.filter(Boolean).map(String);
    }

    if (typeof value === 'string' && value.trim() !== '') {
        return value.split(',').map((item) => item.trim()).filter(Boolean);
    }

    return [];
}

function renderLlmsGeneratorBox() {
    return `
        <article class="llms-box">
            <div class="recommendation-head">
                <div>
                    <h3>Használható llms.txt generátor</h3>
                    <p>Azonnal gyökérmappába tehető navigációs fájlt készít a talált fontos oldalakból. Nem tesz bele auditpontot vagy javítási listát.</p>
                </div>
                <button class="mini-button" type="button" id="generateLlmsButton">Generálás</button>
            </div>
            <textarea class="llms-output hidden" id="llmsOutput" spellcheck="false" aria-label="Generált llms.txt"></textarea>
            <div class="llms-actions hidden" id="llmsActions">
                <button class="mini-button secondary" type="button" id="copyLlmsButton">Másolás</button>
                <button class="mini-button secondary" type="button" id="downloadLlmsButton">Letöltés</button>
            </div>
        </article>
    `;
}

function wireLlmsGenerator() {
    document.querySelector('#generateLlmsButton')?.addEventListener('click', () => {
        const output = document.querySelector('#llmsOutput');
        const actions = document.querySelector('#llmsActions');
        output.value = generateLlmsTxt(currentReport);
        output.classList.remove('hidden');
        actions.classList.remove('hidden');
        output.focus();
    });

    document.querySelector('#copyLlmsButton')?.addEventListener('click', async () => {
        const output = document.querySelector('#llmsOutput');
        try {
            await navigator.clipboard.writeText(output.value);
        } catch (error) {
            output.select();
            document.execCommand('copy');
        }
    });

    document.querySelector('#downloadLlmsButton')?.addEventListener('click', () => {
        const output = document.querySelector('#llmsOutput');
        const blob = new Blob([output.value], { type: 'text/plain;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'llms.txt';
        link.click();
        URL.revokeObjectURL(link.href);
    });
}

function generateLlmsTxt(report) {
    const rootUrl = report?.url || 'https://pelda.hu/';
    const host = safeHost(rootUrl);
    const pages = (report?.pages || []).filter((page) => page.ok).slice(0, 20);
    const lines = [
        `# ${host}`,
        '',
        `> Rövid navigációs útmutató AI asszisztenseknek és fejlesztői eszközöknek a ${host} webhely tartalmához.`,
        '',
        '## Fő oldalak',
    ];

    if (pages.length) {
        pages.forEach((page) => {
            const title = page.title || page.h1?.[0] || page.url;
            lines.push(`- [${sanitizeMarkdown(title)}](${page.url})${page.description ? `: ${sanitizeMarkdown(page.description)}` : ''}`);
        });
    } else {
        lines.push(`- [Kezdőoldal](${rootUrl}): A webhely elsődleges belépési pontja.`);
    }

    lines.push(
        '',
        '## Olvasási útmutató',
        '- A fontos állításokat mindig az adott oldalon látható tartalommal együtt értelmezd.',
        '- A strukturált adat és a látható szöveg együtt adja a legjobb kontextust.',
        '- Ez a fájl navigációs segédlet. Hozzáférés-szabályozásra a robots.txt való.',
        '',
        `Last updated: ${new Date().toISOString().slice(0, 10)}`
    );

    return `${lines.join('\n')}\n`;
}

function safeHost(url) {
    try {
        return new URL(url).host;
    } catch (error) {
        return 'webhely';
    }
}

function sanitizeMarkdown(value) {
    return String(value || '')
        .replaceAll('[', '(')
        .replaceAll(']', ')')
        .replace(/\s+/g, ' ')
        .trim();
}

function renderPages(pages) {
    pagesPanel.innerHTML = '';

    const siteMap = currentReport?.site_map || {};
    if (siteMap.coverage_note) {
        const mapNote = document.createElement('article');
        mapNote.className = 'site-map-note';
        const buckets = Object.entries(siteMap.priority_buckets || {})
            .map(([label, count]) => `${label}: ${count}`)
            .join(' · ');
        mapNote.innerHTML = `
            <div>
                <strong>Domain térkép</strong>
                <p>${escapeHtml(siteMap.coverage_note)}</p>
                <small>${Number(siteMap.discovered_url_count || 0)} URL feltérképezve · ${Number(siteMap.analyzed_url_count || pages.length)} oldal részletesen elemezve${buckets ? ` · ${escapeHtml(buckets)}` : ''}</small>
            </div>
        `;
        pagesPanel.appendChild(mapNote);
    }

    if (!pages.length) {
        pagesPanel.innerHTML += '<p class="empty-state">Nincs megjeleníthető oldal.</p>';
        return;
    }

    pages.forEach((page) => {
        const row = document.createElement('article');
        row.className = 'page-row';
        const raw = page.raw_html_signals || {};
        const rawLabel = raw.visible_word_count !== undefined
            ? ` · raw HTML: ${Number(raw.visible_word_count || 0)} szó, ${Number(raw.text_to_html_ratio_percent || 0)}% szövegarány`
            : '';
        const indexing = page.indexing_signals || {};
        const indexingLabel = indexing.has_noindex
            ? ` · noindex: ${indexing.indexing_intent_label || 'kézi ellenőrzés'}`
            : '';
        row.innerHTML = `
            <div>
                <strong>${escapeHtml(page.url)}</strong>
                <small>${escapeHtml(page.title || page.error || 'Nincs cím')} · ${Number(page.word_count || 0)} szó · ${Number(page.load_time_ms || 0)} ms${escapeHtml(rawLabel)}${escapeHtml(indexingLabel)}</small>
            </div>
            <span class="page-score">${Number(page.score || 0)}/100</span>
        `;
        pagesPanel.appendChild(row);
    });
}

function renderSignals(pages) {
    signalsPanel.innerHTML = '';

    const signalNames = {
        has_raw_html_content: { label: 'Raw HTML-ben látható tartalom', tip: 'A fontos szöveg már a szerver által küldött HTML-ben is olvasható. Ez kritikus, mert több AI crawler nem futtat JavaScriptet.' },
        has_noindex: { label: 'Noindex jel', tip: 'Az oldal meta robots noindex direktívát tartalmaz. Ez navigációs, kategória/tag, keresési, paginációs vagy technikai oldalnál gyakran szándékos; fontos landing/cikk/termék oldalon viszont gyanús hiba lehet.' },
        suspicious_noindex: { label: 'Gyanús noindex', tip: 'Az oldal noindexelt, de a tartalma vagy típusa alapján organikus/AIO céloldal is lehet. Ilyenkor először az indexelési döntést kell ellenőrizni, csak utána jön a meta/schema javítás.' },
        avoids_client_rendered_shell: { label: 'Nem üres SPA shell', tip: 'Az oldal nem csak egy üres app/root konténert és scripteket küld vissza. A crawler JavaScript nélkül is talál értelmes tartalmat.' },
        has_low_js_dependency: { label: 'Alacsony JS-függőség', tip: 'A nyers HTML és a látható szöveg aránya nem utal arra, hogy a fő tartalom kizárólag hidratálás vagy AJAX után jelenne meg.' },
        has_meta_viewport: { label: 'Mobil viewport meta', tip: 'A mobilbarát megjelenés alapjele. Segít a reszponzív értelmezésben és a stabil mobil UX-ben.' },
        has_clear_page_purpose: { label: 'Egyértelmű oldal cél', tip: 'A H1, meta description és első tartalmi blokk alapján gyorsan érthető, kinek szól az oldal és mit lehet itt elintézni.' },
        has_manageable_navigation: { label: 'Átlátható navigáció', tip: 'A fontos oldalak kevés, kiszámítható menülépéssel elérhetők. Ez felhasználónak és AI agentnek is fontos.' },
        has_consistent_cta_language: { label: 'Következetes CTA nyelv', tip: 'A gombok és akciólinkek hasonló mintát követnek, ezért kiszámítható, mi fog történni kattintás után.' },
        has_contact_path: { label: 'Kontakt útvonal', tip: 'Van egyértelmű kapcsolatfelvételi út, ami nem csak eldugott vagy inkonzisztens popupként jelenik meg.' },
        has_newsletter_or_lead_capture: { label: 'Lead capture jel', tip: 'Hírlevél, letöltés, ajánlatkérés vagy más konverziós modul segíti, hogy a látogató ne csak passzívan nézelődjön.' },
        has_contextual_qa_support: { label: 'Kontextushoz illő Q&A', tip: 'Az oldal a saját üzleti helyzetéhez illő kérdésekre válaszol: B2B-nél döntési szempontok, e-kereskedelemnél termék/szállítás/garancia, szakmai tartalomnál bizonyítékok.' },
        has_faq_schema: { label: 'FAQPage séma', tip: 'Google Searchben 2026. május 7-től nem hoz FAQ rich resultet. Csak valódi, látható kérdés-válasz blokkhoz érdemes megtartani, nem általános B2B schema-fixként.' },
        has_article_schema: { label: 'Article/BlogPosting séma', tip: 'A cikk típusát, szerzőjét, dátumait és fő entitásait írja le strukturált adatként.' },
        has_organization_schema: { label: 'Organization/LocalBusiness séma', tip: 'A márka vagy cég strukturált azonosítása névvel, URL-lel, logóval, kapcsolati és külső profil adatokkal.' },
        has_breadcrumb_schema: { label: 'BreadcrumbList séma', tip: 'Megmutatja az oldal helyét a webhely hierarchiájában, ami segíti az entitás- és témakapcsolatok értelmezését.' },
        has_stable_schema_ids: { label: 'Stabil @id azonosítók', tip: 'Tartós, URL-szerű azonosítók a fő entitásokhoz. Segít összekapcsolni a szervezetet, szerzőt, cikket és weboldalt.' },
        has_sameas_identity: { label: 'sameAs entitáskapcsolatok', tip: 'Külső profilokra mutató schema kapcsolat, például szakmai adatbázis, social profil vagy iparági névjegyzék.' },
        has_person_or_author_schema: { label: 'Szerző/Person schema', tip: 'Strukturált szerzői vagy szakértői jel. Különösen fontos YMYL és szakértői témákban.' },
        has_question_headings: { label: 'Kérdés alapú címsorok', tip: 'Olyan H2/H3 címek, amelyek természetes felhasználói kérdésekre hasonlítanak. Jobban illeszkedhetnek query fan-out alkérdésekhez.' },
        has_answer_first_blocks: { label: 'Citációméretű válaszblokkok', tip: 'Rövid, önállóan érthető válaszok a szakasz elején. Ezekből könnyebb idézhető AI válaszrészletet képezni.' },
        has_summary_language: { label: 'Rövid összefoglaló blokk', tip: 'Key takeaways, röviden, összefoglaló vagy hasonló blokk, amely gyorsan kivonatolhatóvá teszi a tartalmat.' },
        has_author_signal: { label: 'Szerzői jel', tip: 'Látható szerző, szakértő vagy felelős szervezet. Bizalmi jel a felhasználónak és gépi értelmezésnek is.' },
        has_date_signal: { label: 'Frissítési dátum', tip: 'Publikálási vagy frissítési dátum, amely segíti a tartalom aktualitásának megítélését.' },
        has_citation_signal: { label: 'Forrás/hivatkozás jel', tip: 'Források, referenciák vagy hivatkozások jelenléte. Alátámasztja a fontos állításokat.' },
        has_claim_evidence_language: { label: 'Bizonyítéknyelv és adatjelek', tip: 'Kutatás, mérés, adat, forrás, százalék vagy esettanulmány jellegű szövegek. Ezek növelhetik a tartalom forrásértékét.' },
        has_visual_evidence: { label: 'Vizuális bizonyíték', tip: 'Táblázat, ábra, figure vagy videó, amely nem csak díszít, hanem bizonyítékot vagy magyarázatot ad.' },
        has_open_graph: { label: 'Open Graph meta', tip: 'Megosztási és entitáskonzisztencia jel: og:title, og:description, og:image.' },
        has_canonical: { label: 'Canonical link', tip: 'Jelzi a preferált URL-t, így a duplikált oldalak nem osztják szét a keresési jeleket.' },
    };

    Object.entries(signalNames).forEach(([key, meta]) => {
        const count = pages.filter((page) => page.signals?.[key]).length;
        const row = document.createElement('article');
        row.className = 'signal-row';
        row.innerHTML = `
            <div>
                <strong class="tooltip-term" tabindex="0">
                    ${escapeHtml(meta.label)}
                    <span class="tooltip-bubble" role="tooltip">${escapeHtml(meta.tip)}</span>
                </strong>
                <small>${count}/${pages.length} vizsgált oldalon aktív</small>
            </div>
            <span class="page-score">${pages.length ? Math.round((count / pages.length) * 100) : 0}%</span>
        `;
        signalsPanel.appendChild(row);
    });
}

function renderMethodology(methodology) {
    if (!methodologyPanel) {
        return;
    }

    const principles = methodology.principles || [];
    const sources = methodology.sources || [];

    methodologyPanel.innerHTML = `
        <article class="methodology-report">
            ${renderProcessFlowMarkup()}
            <h3>${escapeHtml(methodology.version || 'AIO/GEO módszertan')}</h3>
            <p>A pontozás a klasszikus SEO-t, a nyers HTML-ben látható tartalmat, az entitásalapú bizalmi jeleket, a citációs alkalmasságot és az agentikus használhatóságot együtt értékeli. A FAQ rich result 2026-os kivezetése után nem a FAQPage schema jelenléte a lényeg, hanem az, hogy az oldal a saját üzleti kontextusában ad-e látható, pontos, idézhető válaszokat.</p>
            <div class="principle-grid">
                ${principles.map((item) => `
                    <section>
                        <h4>${escapeHtml(item.name)}</h4>
                        <p>${escapeHtml(item.summary)}</p>
                        <small>${(item.checks || []).map(escapeHtml).join(' · ')}</small>
                    </section>
                `).join('')}
            </div>
            ${renderCitationMapMarkup()}
            <h3>Források és tudományos státusz</h3>
            <p>A GEO/AIO gyorsan fejlődő terület. A peer-reviewed és hivatalos dokumentáció erősebb bizonyítéknak számít, az arXiv 2026-os eredményeket pedig friss, de még részben validálódó kutatásként kezeli a rendszer.</p>
            <div class="source-list">
                ${sources.map((source) => `
                    <a class="source-item" href="${escapeHtml(source.url)}" target="_blank" rel="noopener noreferrer">
                        <strong>${escapeHtml(source.title)}</strong>
                        <span>${escapeHtml(source.note)}</span>
                    </a>
                `).join('')}
            </div>
        </article>
    `;
}

function renderProcessFlowMarkup() {
    const steps = [
        ['01', 'Cél', 'Kinek szól az oldal, milyen döntést támogat, milyen útvonalra terel?'],
        ['02', 'Feltérképezés', 'Elérhetőség, belső linkek, indexelhető URL-ek és HTTP válaszok.'],
        ['03', 'Raw HTML', 'Megnézzük, hogy a fő tartalom JavaScript nélkül is olvasható-e.'],
        ['04', 'UX útvonal', 'Navigáció, CTA-k, külső linkek, kontakt és lead capture jelek.'],
        ['05', 'Teendők', 'Probléma, miért probléma, első lépés és priorizált javítási terv.'],
    ];

    return `
        <div class="process-flow" role="list" aria-label="AIO audit módszertani folyamat">
            ${steps.map(([number, title, text]) => `
                <article class="process-step" role="listitem">
                    <span>${number}</span>
                    <h3>${escapeHtml(title)}</h3>
                    <p>${escapeHtml(text)}</p>
                </article>
            `).join('')}
        </div>
    `;
}

function renderCitationMapMarkup() {
    const nodes = [
        ['Kérdés', 'Mit keres a felhasználó?'],
        ['Alkérdések', 'Milyen részválasz kell?'],
        ['Forrás', 'Van-e megbízható oldal?'],
        ['Válasz', 'Ki lehet-e emelni tisztán?'],
        ['Citáció', 'Érdemes-e hivatkozni rá?'],
    ];

    return `
        <div class="citation-map" aria-label="Citációs alkalmassági modell">
            ${nodes.map(([label, text]) => `
                <div>
                    <span>${escapeHtml(label)}</span>
                    <strong>${escapeHtml(text)}</strong>
                </div>
            `).join('')}
        </div>
    `;
}

function renderGlossary(glossary) {
    if (!glossaryPanel) {
        return;
    }

    const defaultEntries = getDefaultGlossaryEntries();
    const entries = mergeGlossaryEntries(Object.values(glossary || {}), defaultEntries);
    if (!entries.length) {
        glossaryPanel.innerHTML = '<p class="empty-state">A szótár az első audit után töltődik be.</p>';
        return;
    }

    glossaryPanel.innerHTML = `
        <article class="glossary-intro">
            <div>
                <span>Mini oktatóanyag</span>
                <h3>Nem kell AIO szakértőnek lenned a riport értelmezéséhez</h3>
                <p>Nyisd le azt a fogalmat, amit a riportban látsz. Minden kártyánál megtalálod, miért számít, hogyan néz ki egy jó példa, és mit érdemes javítani az oldalon.</p>
            </div>
            <ol>
                <li>Azonosítsd a gyenge pontot a riportban.</li>
                <li>Keresd meg itt a fogalmat.</li>
                <li>Alkalmazd a “Mit javíts?” lépést az érintett oldalon.</li>
            </ol>
        </article>
        <div class="learning-path compact">
            <article>
                <span>1</span>
                <strong>Bizalom</strong>
                <p>Ki a forrás, és mi bizonyítja?</p>
            </article>
            <article>
                <span>2</span>
                <strong>Idézhetőség</strong>
                <p>Ki lehet-e emelni a választ?</p>
            </article>
            <article>
                <span>3</span>
                <strong>Gépi kapcsolat</strong>
                <p>Schema és belső link segíti-e?</p>
            </article>
        </div>
        <div class="glossary-grid learning-glossary">
            ${entries.map((entry) => `
                <article class="glossary-card lesson-card">
                    <div class="lesson-card-head">
                        <span>${escapeHtml(entry.category || 'AIO')}</span>
                        <h3>${escapeHtml(entry.term)}</h3>
                    </div>
                    <p>${escapeHtml(entry.short)}</p>
                    <details>
                        <summary>Mit jelent ez a gyakorlatban?</summary>
                        <div class="lesson-detail">
                            <strong>Miért számít?</strong>
                            <p>${escapeHtml(entry.why || entry.details || '')}</p>
                            <strong>Példa</strong>
                            <p>${escapeHtml(entry.example || '')}</p>
                            <strong>Mit javíts?</strong>
                            <p>${escapeHtml(entry.fix || '')}</p>
                            ${renderGlossaryLinks(entry.links || [])}
                        </div>
                    </details>
                </article>
            `).join('')}
        </div>
    `;
}

function getDefaultGlossaryEntries() {
    const source = document.querySelector('#glossaryDefaultsJson');
    if (!source) {
        return [];
    }

    try {
        return Object.values(JSON.parse(source.textContent || '{}'));
    } catch (error) {
        return [];
    }
}

function mergeGlossaryEntries(reportEntries, defaultEntries) {
    if (!reportEntries.length) {
        return defaultEntries;
    }

    const merged = reportEntries.map((entry) => {
        const fallback = defaultEntries.find((item) => item.term === entry.term) || {};
        return { ...fallback, ...entry };
    });
    const reportTerms = new Set(merged.map((entry) => entry.term));
    defaultEntries.forEach((entry) => {
        if (!reportTerms.has(entry.term)) {
            merged.push(entry);
        }
    });
    return merged;
}

function renderGlossaryLinks(links) {
    if (!links.length) {
        return '';
    }

    return `
        <div class="lesson-links">
            ${links.map((link) => `
                <a href="${escapeHtml(link.url || '#')}" target="_blank" rel="noopener noreferrer">${escapeHtml(link.label || 'Forrás')}</a>
            `).join('')}
        </div>
    `;
}

function prependHistory(report) {
    const empty = historyList.querySelector('.empty-state');
    if (empty) {
        empty.remove();
    }

    const item = document.createElement('article');
    item.className = 'history-item';
    item.dataset.reportId = report.id || '';
    item.innerHTML = `
        <div>
            <strong>${escapeHtml(report.url)}</strong>
            <small>${escapeHtml(report.created_at)}</small>
        </div>
        <div class="history-actions">
            <span>${Number(report.overall_score || 0)}/100</span>
            <button class="mini-button secondary history-load" type="button" data-report-id="${escapeHtml(report.id || '')}">Megnyitás</button>
            <button class="mini-button secondary history-visibility" type="button" data-report-id="${escapeHtml(report.id || '')}">Visibility profil</button>
            <button class="mini-button secondary history-pdf" type="button" data-report-id="${escapeHtml(report.id || '')}">PDF</button>
            <a class="mini-button secondary" href="api/download_report.php?id=${encodeURIComponent(report.id || '')}">JSON letöltés</a>
            <button class="mini-button danger history-delete" type="button" data-report-id="${escapeHtml(report.id || '')}">Törlés</button>
        </div>
    `;
    historyList.prepend(item);
}

function levelLabel(level) {
    if (level === 'critical') {
        return 'Sürgős';
    }
    if (level === 'warning') {
        return 'Fontos';
    }
    return 'Javasolt';
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderAiReport(value) {
    const lines = String(value || '').replace(/\r\n/g, '\n').split('\n');
    const html = [];
    let listOpen = false;

    const closeList = () => {
        if (listOpen) {
            html.push('</ul>');
            listOpen = false;
        }
    };

    lines.forEach((rawLine) => {
        const line = rawLine.trim();
        if (!line) {
            closeList();
            return;
        }

        if (line.startsWith('### ')) {
            closeList();
            html.push(`<h4>${escapeHtml(line.slice(4))}</h4>`);
            return;
        }

        if (line.startsWith('## ')) {
            closeList();
            html.push(`<h3>${escapeHtml(line.slice(3))}</h3>`);
            return;
        }

        if (line.startsWith('# ')) {
            closeList();
            html.push(`<h3>${escapeHtml(line.slice(2))}</h3>`);
            return;
        }

        if (/^[-*]\s+/.test(line)) {
            if (!listOpen) {
                html.push('<ul>');
                listOpen = true;
            }
            html.push(`<li>${formatInlineMarkdown(line.replace(/^[-*]\s+/, ''))}</li>`);
            return;
        }

        if (/^\d+\.\s+/.test(line)) {
            if (!listOpen) {
                html.push('<ul>');
                listOpen = true;
            }
            html.push(`<li>${formatInlineMarkdown(line.replace(/^\d+\.\s+/, ''))}</li>`);
            return;
        }

        closeList();
        html.push(`<p>${formatInlineMarkdown(line)}</p>`);
    });

    closeList();
    return html.join('');
}

function formatInlineMarkdown(value) {
    return escapeHtml(value)
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
}

function openPdfReport(report) {
    const popup = window.open('', '_blank');
    if (!popup) {
        setStatus('A böngésző blokkolta a PDF riport ablakot.', 'error');
        return;
    }

    popup.document.open();
    popup.document.write(buildPdfReportHtml(report));
    popup.document.close();
    popup.focus();

    window.setTimeout(() => {
        popup.print();
    }, 650);
}

function openVisibilityPdfReport(project, run, runs = []) {
    const popup = window.open('', '_blank');
    if (!popup) {
        setVisibilityStatus('A böngésző blokkolta a visibility PDF ablakot.', 'error');
        return;
    }

    popup.document.open();
    popup.document.write(buildVisibilityPdfReportHtml(project, run, runs));
    popup.document.close();
    popup.focus();

    window.setTimeout(() => {
        popup.print();
    }, 650);
}

function buildVisibilityPdfReportHtml(project, run, runs = []) {
    return renderVisibilityPdfTemplate(buildVisibilityPdfViewModel(project, run, runs));
}

function buildVisibilityPdfViewModel(project, run, runs = []) {
    const share = Array.isArray(run.share_of_voice) ? run.share_of_voice : [];
    const competitors = Array.isArray(run.competitors) ? run.competitors : [];
    const queries = Array.isArray(run.query_results) ? run.query_results : [];
    const backlogActions = Array.isArray(run.opportunity_backlog?.actions) ? run.opportunity_backlog.actions : [];
    const evidence = run.evidence_explanation || {};

    return {
        project,
        run,
        runs: Array.isArray(runs) ? runs : [],
        share,
        competitors,
        queries,
        backlogActions,
        reportLayers: normalizeVisibilityReportLayers(run, project),
        ga4Import: project.latest_ga4_import || currentVisibilityGa4Imports[0] || null,
        serpAioImport: project.latest_serp_aio_import || currentVisibilitySerpAioImports[0] || null,
        geminiGroundingRun: project.latest_gemini_grounding_run || currentVisibilityGeminiGroundingRuns[0] || null,
        evidence: {
            hardSignals: Array.isArray(evidence.hard_signals) ? evidence.hard_signals : [],
            directionalSignals: Array.isArray(evidence.directional_signals) ? evidence.directional_signals : [],
            notMeasured: Array.isArray(evidence.not_measured) ? evidence.not_measured : [],
            clientSummary: Array.isArray(evidence.client_summary) ? evidence.client_summary : [],
            recommendedLanguage: evidence.recommended_language || '',
        },
        aiText: run.ai_strategy?.status === 'completed' ? run.ai_strategy.analysis : '',
    };
}

function renderVisibilityPdfTemplate(data) {
    const { project, run, share, competitors, queries, backlogActions, reportLayers, ga4Import, serpAioImport, geminiGroundingRun, evidence, aiText } = data;
    const reportTitle = project.name || project.target_domain || 'AI láthatósági riport';
    const confidence = run.confidence || {};
    const primaryCompetitors = competitors.slice(0, 4).map((item) => item.domain).filter(Boolean);

    return `<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>AI láthatósági riport - ${escapeHtml(reportTitle)}</title>
    <style>${visibilityPdfStyles()}</style>
</head>
<body>
    <main class="pdf-report">
        <section class="page cover-page">
            <header class="report-header">
                <img src="assets/img/hello_ai_audit_logo_transparent.png" alt="Hello AI Audit">
                <div>
                    <span>AI láthatósági riport</span>
                    <strong>${escapeHtml(formatShortDate(run.created_at))}</strong>
                </div>
            </header>
            <div class="cover-grid">
                <section class="hero-copy">
                    <span class="eyebrow">Hello AI Audit</span>
                    <h1>${escapeHtml(reportTitle)}</h1>
                    <p>${escapeHtml(project.target_domain || '')} · ${escapeHtml(project.market || '')} · ${escapeHtml(run.run_mode === 'weekly_portfolio' ? 'Heti Top 20 monitoring' : project.business_model_label || 'Generált kérdéssor')}</p>
                    <div class="certainty-pill">${escapeHtml(confidence.level || 'irányadó jel')} · ${Number(confidence.score || 0)}/100</div>
                </section>
                <section class="hero-score">
                    ${renderVisibilityPdfGauge(Number(run.visibility_rate || 0))}
                    <p>${escapeHtml(run.interpretation || '')}</p>
                </section>
            </div>
            ${renderVisibilityPdfKpis(run, competitors)}
            <div class="cover-panels">
                <section class="panel chart-panel">
                    <div class="section-head compact">
                        <span>Trend</span>
                        <h2>Visibility rate idősor</h2>
                    </div>
                    ${renderVisibilityPdfTrendSvg(data.runs)}
                </section>
                <section class="panel">
                    <div class="section-head compact">
                        <span>Találati tér</span>
                        <h2>Share of voice</h2>
                    </div>
                    ${renderVisibilityPdfShareBars(share)}
                </section>
            </div>
        </section>

        <section class="page">
            <div class="section-head">
                <span>Riportlogika</span>
                <h2>Mérés vs javítás</h2>
                <p>Először azt mutatjuk, amit ténylegesen mértünk. Utána külön jelöljük, milyen javítási munkatervet vezetünk le ezekből az adatokból.</p>
            </div>
            ${renderVisibilityPdfReportLayers(reportLayers)}
            <div class="section-head stacked">
                <span>Mérési értelmezés</span>
                <h2>Biztos adat vs irányadó AI jel</h2>
                <p>Ez a blokk választja szét az ügyfélnek is vállalható tényadatot, az abból levont AI visibility következtetést és a mérés természetes korlátait.</p>
            </div>
            ${renderVisibilityPdfEvidence(evidence)}
            <div class="split-grid">
                <section class="panel">
                    <div class="section-head compact">
                        <span>Benchmark</span>
                        <h2>Versenytárs citation minták</h2>
                    </div>
                    ${renderVisibilityPdfCompetitors(competitors, primaryCompetitors)}
                </section>
                <section class="panel">
                    <div class="section-head compact">
                        <span>Adatforrás</span>
                        <h2>Keresési minta</h2>
                    </div>
                    ${renderVisibilityPdfMethodCard(run)}
                </section>
            </div>
            ${ga4Import ? `
                <div class="section-head stacked">
                    <span>Analitikai kontroll</span>
                    <h2>GA4 AI referral import</h2>
                    <p>Ez a blokk nem keresési proxy, hanem a GA4 exportban azonosított valós AI referral forgalom összesítése.</p>
                </div>
                ${renderVisibilityPdfGa4Import(ga4Import)}
            ` : ''}
            ${serpAioImport ? `
                <div class="section-head stacked">
                    <span>Google SERP kontroll</span>
                    <h2>SERP / AI Overview import</h2>
                    <p>Provider export alapján ellenőrizzük, volt-e AI Overview, kapott-e saját citációt a domain, és milyen forrásdomainekkel versenyez.</p>
                </div>
                ${renderVisibilityPdfSerpAioImport(serpAioImport)}
            ` : ''}
            ${geminiGroundingRun ? `
                <div class="section-head stacked">
                    <span>Gemini grounded kontroll</span>
                    <h2>Gemini Google Search-grounded mérés</h2>
                    <p>AI válaszoldali kontroll: a Gemini milyen Google Search-grounded forrásokra támaszkodik, említi-e vagy idézi-e a saját domaint.</p>
                </div>
                ${renderVisibilityPdfGeminiGroundingRun(geminiGroundingRun)}
            ` : ''}
        </section>

        <section class="page">
            <div class="section-head">
                <span>Javítási terv</span>
                <h2>Tartalmi backlog</h2>
                <p>A következő kártyák nem csak hibát jeleznek, hanem konkrét tartalmi eszközt, kezdő lépést és mintaforrásokat adnak a javításhoz.</p>
            </div>
            ${renderVisibilityPdfBacklog(backlogActions)}
            ${aiText ? `<section class="panel ai-panel"><div class="section-head compact"><span>AI értelmezés</span><h2>Stratégiai összefoglaló</h2></div><article class="ai">${formatPdfText(aiText)}</article></section>` : ''}
        </section>

        <section class="page">
            <div class="section-head">
                <span>Kérdésszintű adat</span>
                <h2>Mit látott a keresési minta?</h2>
                <p>Ez a rész az elemzés bizonyítéktára: kérdésenként látszik, hogy megjelent-e a saját domain, és milyen források domináltak.</p>
            </div>
            ${renderVisibilityPdfQueries(queries)}
            <footer class="footer">Készült a Hello AI Audit visibility export sablonnal.</footer>
        </section>
    </main>
</body>
</html>`;
}

function visibilityPdfStyles() {
    return `
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0d1321; font-family: "Plus Jakarta Sans", Arial, sans-serif; background: #ffffff; }
        .pdf-report { display: grid; gap: 0; }
        .page { position: relative; min-height: 190mm; padding: 18px; break-before: page; overflow: hidden; }
        .page:first-child { break-before: auto; }
        .page::before { content: ""; position: absolute; inset: 0; z-index: -2; background: radial-gradient(circle at 8% 10%, rgba(255,0,168,.14), transparent 30%), radial-gradient(circle at 96% 4%, rgba(124,58,237,.12), transparent 28%), #fff; }
        .page::after { content: ""; position: absolute; right: -70px; bottom: -90px; width: 260px; height: 260px; z-index: -1; border: 1px solid rgba(255,0,168,.16); border-radius: 999px; }
        .report-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding-bottom: 14px; border-bottom: 1px solid #eef0f4; }
        .report-header img { width: 178px; height: auto; display: block; }
        .report-header div { text-align: right; }
        .report-header span, .section-head span, .eyebrow { color: #ff00a8; font-size: 10px; font-weight: 950; letter-spacing: .08em; text-transform: uppercase; }
        .report-header strong { display: block; margin-top: 4px; color: #6b7280; font-size: 11px; }
        .cover-grid { display: grid; grid-template-columns: 1.28fr .72fr; gap: 16px; align-items: stretch; margin-top: 16px; }
        .hero-copy { padding: 24px; border-radius: 14px; background: linear-gradient(135deg, #0d1321, #151a2d 58%, #250929); color: #fff; box-shadow: 0 24px 70px rgba(13,19,33,.18); }
        .hero-copy h1 { max-width: 720px; margin: 10px 0 12px; font-size: 44px; line-height: 1; color: #fff; }
        .hero-copy p { max-width: 620px; margin: 0; color: #d8dbe5; font-size: 13px; line-height: 1.55; }
        .certainty-pill { display: inline-flex; margin-top: 18px; min-height: 34px; align-items: center; padding: 0 13px; border-radius: 999px; background: rgba(255,255,255,.1); color: #fff; font-size: 12px; font-weight: 850; }
        .hero-score { display: grid; align-content: center; justify-items: center; gap: 12px; padding: 18px; border: 1px solid #ffd2f1; border-radius: 14px; background: linear-gradient(180deg, #fff7fc, #fff); text-align: center; }
        .hero-score p { margin: 0; color: #596070; font-size: 12px; line-height: 1.48; }
        .gauge { width: 184px; height: 184px; }
        .gauge text { font-family: "Plus Jakarta Sans", Arial, sans-serif; text-anchor: middle; }
        .gauge .score { fill: #0d1321; font-size: 34px; font-weight: 950; }
        .gauge .label { fill: #6b7280; font-size: 10px; font-weight: 850; text-transform: uppercase; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 12px 0; }
        .kpi { min-height: 86px; padding: 13px; border: 1px solid #e8eaf0; border-radius: 12px; background: rgba(255,255,255,.92); }
        .kpi span { display: block; color: #7b8190; font-size: 9px; font-weight: 900; text-transform: uppercase; }
        .kpi strong { display: block; margin: 5px 0 3px; color: #0d1321; font-size: 24px; line-height: 1; }
        .kpi small { color: #8b92a1; font-size: 10px; line-height: 1.35; }
        .cover-panels, .split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .panel { padding: 14px; border: 1px solid #e8eaf0; border-radius: 12px; background: rgba(255,255,255,.92); }
        .section-head { margin-bottom: 12px; }
        .section-head h2 { margin: 5px 0 6px; color: #0d1321; font-size: 25px; line-height: 1.08; }
        .section-head p { max-width: 780px; margin: 0; color: #656b78; font-size: 12px; line-height: 1.5; }
        .section-head.stacked { margin-top: 14px; padding-top: 12px; border-top: 1px solid #eef0f4; }
        .section-head.compact { margin-bottom: 8px; }
        .section-head.compact h2 { font-size: 16px; }
        .trend-svg { width: 100%; height: 104px; display: block; }
        .trend-svg .axis { stroke: #dfe3ea; stroke-width: 1.2; }
        .trend-svg .line { fill: none; stroke: #ff00a8; stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; }
        .trend-svg .dot { fill: #fff; stroke: #ff00a8; stroke-width: 3; }
        .trend-svg text { fill: #6b7280; font-size: 10px; font-weight: 800; text-anchor: middle; }
        .bar-row { display: grid; grid-template-columns: 128px 1fr 42px; gap: 8px; align-items: center; margin: 8px 0; font-size: 11px; }
        .bar-row strong { min-width: 0; overflow-wrap: anywhere; }
        .bar-row span { height: 8px; border-radius: 99px; background: #f0f2f6; overflow: hidden; }
        .bar-row i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #ff00a8, #7c3aed); }
        .bar-row em { color: #6b7280; font-style: normal; font-weight: 850; text-align: right; }
        .evidence-lead { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px; }
        .evidence-lead p { margin: 0; padding: 12px; border: 1px solid #ffd2f1; border-radius: 12px; background: #fff7fc; color: #303747; font-size: 11px; line-height: 1.48; }
        .evidence-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .pdf-layer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px; }
        .pdf-layer-card { padding: 14px; border: 1px solid #e8eaf0; border-radius: 12px; background: #fff; }
        .pdf-layer-card.is-measured { border-color: rgba(16,185,129,.26); background: linear-gradient(180deg, rgba(16,185,129,.07), #fff); }
        .pdf-layer-card.is-derived { border-color: rgba(255,0,168,.22); background: linear-gradient(180deg, rgba(255,0,168,.07), #fff); }
        .pdf-layer-card .layer-kicker { color: #ff00a8; font-size: 9px; font-weight: 950; text-transform: uppercase; }
        .pdf-layer-card h3 { margin: 5px 0 6px; color: #0d1321; font-size: 16px; }
        .pdf-layer-card p { margin: 0 0 9px; color: #596070; font-size: 11px; line-height: 1.45; }
        .pdf-fact-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 7px; }
        .pdf-fact-grid article, .pdf-action-list article { padding: 8px; border: 1px solid #eff1f5; border-radius: 9px; background: rgba(255,255,255,.76); }
        .pdf-fact-grid span, .pdf-action-list span { display: block; color: #7b8190; font-size: 8px; font-weight: 900; text-transform: uppercase; }
        .pdf-fact-grid strong, .pdf-action-list strong { display: block; margin: 2px 0; color: #0d1321; font-size: 11px; line-height: 1.25; }
        .pdf-fact-grid small, .pdf-action-list small { color: #6b7280; font-size: 9px; line-height: 1.35; }
        .pdf-action-list { display: grid; gap: 7px; }
        .pdf-client-frame { margin: 10px 0 12px; padding: 11px; border-left: 5px solid #ff00a8; border-radius: 10px; background: #fff7fc; color: #4f5766; font-size: 11px; line-height: 1.48; }
        .pdf-ga4-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 10px; }
        .pdf-ga4-kpi { padding: 10px; border: 1px solid #e8eaf0; border-radius: 10px; background: #fff; }
        .pdf-ga4-kpi span { display: block; color: #ff00a8; font-size: 8px; font-weight: 950; text-transform: uppercase; }
        .pdf-ga4-kpi strong { display: block; margin: 3px 0; color: #0d1321; font-size: 18px; }
        .pdf-ga4-kpi small { color: #6b7280; font-size: 9px; }
        .pdf-ga4-bars { display: grid; gap: 6px; }
        .pdf-ga4-row { display: grid; grid-template-columns: 132px 1fr 64px; gap: 7px; align-items: center; font-size: 10px; }
        .pdf-ga4-row span:nth-child(2) { height: 7px; border-radius: 999px; background: #f0f2f6; overflow: hidden; }
        .pdf-ga4-row i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #10b981, #06b6d4); }
        .pdf-ga4-row small { color: #6b7280; text-align: right; }
        .pdf-serp-row i { background: linear-gradient(90deg, #ff00a8, #7c3aed); }
        .evidence-card { padding: 13px; border: 1px solid #e8eaf0; border-radius: 12px; background: #fff; }
        .evidence-card h3 { margin: 0 0 8px; color: #0d1321; font-size: 15px; }
        .evidence-card article { margin-top: 9px; padding-top: 9px; border-top: 1px solid #eff1f5; }
        .evidence-card article:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
        .evidence-card span { display: block; color: #ff00a8; font-size: 9px; font-weight: 950; text-transform: uppercase; }
        .evidence-card strong { display: block; margin: 3px 0; color: #0d1321; font-size: 12px; line-height: 1.25; overflow-wrap: anywhere; }
        .evidence-card p, .evidence-card li { margin: 3px 0; color: #68707e; font-size: 10px; line-height: 1.38; }
        .client-language { margin-top: 10px; padding: 12px; border-left: 5px solid #ff00a8; border-radius: 10px; background: #fff7fc; }
        .client-language strong { font-size: 12px; }
        .client-language p { margin: 5px 0 0; color: #4d5564; font-size: 11px; line-height: 1.5; }
        .method-list { display: grid; gap: 8px; }
        .method-list article, .competitor-row, .query-row { padding: 9px; border: 1px solid #eff1f5; border-radius: 10px; background: #fff; }
        .method-list strong, .competitor-row strong, .query-row strong { display: block; color: #0d1321; font-size: 12px; }
        .method-list small, .competitor-row small, .query-row small { display: block; margin-top: 3px; color: #747b89; font-size: 10px; line-height: 1.35; }
        .competitor-list, .query-list { display: grid; gap: 8px; }
        .competitor-row { display: grid; grid-template-columns: 1fr 52px; gap: 8px; align-items: center; }
        .competitor-row em { color: #ff00a8; font-style: normal; font-size: 18px; font-weight: 950; text-align: right; }
        .backlog-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .backlog-card { padding: 13px; border: 1px solid #e8eaf0; border-radius: 12px; background: #fff; break-inside: avoid; }
        .backlog-card.is-high { border-color: #ffbde9; background: linear-gradient(180deg, #fff7fc, #fff); }
        .backlog-card span { color: #ff00a8; font-size: 9px; font-weight: 950; text-transform: uppercase; }
        .backlog-card h3 { margin: 5px 0 7px; color: #0d1321; font-size: 15px; line-height: 1.2; }
        .backlog-card p { margin: 0 0 6px; color: #4f5766; font-size: 11px; line-height: 1.45; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
        .chip-row i { padding: 4px 7px; border-radius: 999px; background: #f3f4f6; color: #606775; font-style: normal; font-size: 9px; font-weight: 750; }
        .ai-panel { margin-top: 12px; }
        .ai p, .ai li { color: #303747; font-size: 11px; line-height: 1.5; }
        .query-row { display: grid; grid-template-columns: 1.3fr .45fr 1fr; gap: 10px; align-items: start; }
        .query-row .status { display: inline-flex; justify-content: center; padding: 5px 8px; border-radius: 999px; color: #fff; background: #6b7280; font-size: 9px; font-weight: 900; text-transform: uppercase; }
        .query-row .status.is-hit { background: #10b981; }
        .footer { margin-top: 12px; color: #9aa1ad; font-size: 10px; }
        @media print { .page { page-break-before: always; } .page:first-child { page-break-before: auto; } }
    `;
}

function renderVisibilityPdfKpis(run, competitors = []) {
    return `
        <div class="kpi-grid">
            ${renderVisibilityPdfKpi('Saját találat', `${Number(run.owned_query_hits || 0)}/${Number(run.query_count || 0)}`, 'kérdésszintű jelenlét')}
            ${renderVisibilityPdfKpi('Átlagos saját pozíció', run.average_owned_position ? Number(run.average_owned_position).toFixed(1) : '-', 'ahol a domain megjelent')}
            ${renderVisibilityPdfKpi('Aktív versenytárs', String(competitors.length), competitors.slice(0, 2).map((item) => item.domain).join(', ') || 'nincs adat')}
            ${renderVisibilityPdfKpi('Adatforrás', (run.providers || []).join(', ') || 'provider', run.method || 'mentett keresési minta')}
        </div>
    `;
}

function renderVisibilityPdfKpi(label, value, note) {
    return `<article class="kpi"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(note)}</small></article>`;
}

function renderVisibilityPdfGauge(score) {
    const safeScore = Math.max(0, Math.min(100, Number(score || 0)));
    const circumference = 2 * Math.PI * 70;
    const dash = (safeScore / 100) * circumference;
    return `
        <svg class="gauge" viewBox="0 0 184 184" role="img" aria-label="Visibility rate ${safeScore}%">
            <circle cx="92" cy="92" r="70" fill="none" stroke="#f1f2f6" stroke-width="18"></circle>
            <circle cx="92" cy="92" r="70" fill="none" stroke="url(#gaugeGradient)" stroke-width="18" stroke-linecap="round" stroke-dasharray="${dash.toFixed(1)} ${circumference.toFixed(1)}" transform="rotate(-90 92 92)"></circle>
            <defs><linearGradient id="gaugeGradient" x1="20" y1="20" x2="164" y2="164"><stop stop-color="#ff00a8"></stop><stop offset="1" stop-color="#7c3aed"></stop></linearGradient></defs>
            <text class="score" x="92" y="88">${safeScore}%</text>
            <text class="label" x="92" y="112">Visibility rate</text>
        </svg>
    `;
}

function renderVisibilityPdfTrendSvg(runs = []) {
    const chronological = [...(runs || [])].reverse().slice(-8);
    if (chronological.length < 2) {
        return '<p class="empty-state">A trendhez legalább két futás kell.</p>';
    }

    const width = 440;
    const height = 104;
    const pad = 18;
    const points = chronological.map((item, index) => {
        const x = pad + (index * ((width - pad * 2) / Math.max(1, chronological.length - 1)));
        const y = height - pad - ((Number(item.visibility_rate || 0) / 100) * (height - pad * 2));
        return { x, y, rate: Number(item.visibility_rate || 0), date: formatShortDate(item.created_at).slice(0, 10) };
    });
    const path = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');

    return `
        <svg class="trend-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Visibility trend">
            <line class="axis" x1="${pad}" y1="${height - pad}" x2="${width - pad}" y2="${height - pad}"></line>
            <line class="axis" x1="${pad}" y1="${pad}" x2="${pad}" y2="${height - pad}"></line>
            <path class="line" d="${path}"></path>
            ${points.map((point) => `<circle class="dot" cx="${point.x.toFixed(1)}" cy="${point.y.toFixed(1)}" r="4"></circle><text x="${point.x.toFixed(1)}" y="${Math.max(13, point.y - 8).toFixed(1)}">${point.rate}%</text>`).join('')}
        </svg>
    `;
}

function renderVisibilityPdfShareBars(share = []) {
    if (!share.length) {
        return '<p class="empty-state">Nincs share of voice adat.</p>';
    }

    return share.slice(0, 7).map((item) => `
        <div class="bar-row">
            <strong>${escapeHtml(item.domain || '')}</strong>
            <span><i style="width:${Math.max(4, Number(item.share || 0))}%"></i></span>
            <em>${Number(item.share || 0)}%</em>
        </div>
    `).join('');
}

function renderVisibilityPdfReportLayers(layers = {}) {
    const measurement = layers.measurement_layer || {};
    const improvement = layers.improvement_layer || {};
    const framing = layers.client_framing || {};
    const facts = Array.isArray(measurement.facts) ? measurement.facts : [];
    const topActions = Array.isArray(improvement.top_actions) ? improvement.top_actions : [];

    return `
        <div class="pdf-layer-grid">
            <section class="pdf-layer-card is-measured">
                <div class="layer-kicker">Bizonyíték</div>
                <h3>${escapeHtml(measurement.label || 'Mérési réteg')}</h3>
                <p>${escapeHtml(measurement.summary || '')}</p>
                <div class="pdf-fact-grid">
                    ${facts.slice(0, 6).map((fact) => `
                        <article>
                            <span>${escapeHtml(fact.label || '')}</span>
                            <strong>${escapeHtml(fact.value || '')}</strong>
                            <small>${escapeHtml(fact.note || '')}</small>
                        </article>
                    `).join('') || '<p>Nincs mérési adat.</p>'}
                </div>
            </section>
            <section class="pdf-layer-card is-derived">
                <div class="layer-kicker">Munkaterv</div>
                <h3>${escapeHtml(improvement.label || 'Javítási réteg')}</h3>
                <p>${escapeHtml(improvement.summary || '')}</p>
                <div class="pdf-action-list">
                    ${topActions.slice(0, 4).map((action) => `
                        <article>
                            <span>${escapeHtml(action.priority === 'high' ? 'magas prioritás' : action.priority || 'teendő')}</span>
                            <strong>${escapeHtml(action.title || action.recommended_asset || '')}</strong>
                            <small>${escapeHtml(action.first_step || action.query || '')}</small>
                        </article>
                    `).join('') || '<p>Nincs generált javítási teendő.</p>'}
                </div>
            </section>
        </div>
        <div class="pdf-client-frame">
            ${escapeHtml([framing.measured_sentence, framing.derived_sentence, framing.how_to_use].filter(Boolean).join(' '))}
        </div>
    `;
}

function renderVisibilityPdfGa4Import(importData = {}) {
    const summary = importData.summary || {};
    const sources = Array.isArray(importData.ai_sources) ? importData.ai_sources : [];
    const maxSessions = Math.max(1, ...sources.map((item) => Number(item.sessions || 0)));

    return `
        <section class="panel">
            <div class="pdf-ga4-grid">
                <article class="pdf-ga4-kpi"><span>AI session arány</span><strong>${Number(summary.ai_session_share || 0)}%</strong><small>${formatMetric(summary.ai_sessions)} / ${formatMetric(summary.total_sessions)} session</small></article>
                <article class="pdf-ga4-kpi"><span>AI forrás</span><strong>${Number(summary.detected_source_count || 0)}</strong><small>${Number(summary.matched_rows || 0)} egyező sor</small></article>
                <article class="pdf-ga4-kpi"><span>AI user</span><strong>${formatMetric(summary.ai_users)}</strong><small>${formatMetric(summary.total_users)} összes user</small></article>
                <article class="pdf-ga4-kpi"><span>AI konverzió</span><strong>${formatMetric(summary.ai_conversions)}</strong><small>${escapeHtml(formatShortDate(importData.created_at))}</small></article>
            </div>
            <div class="pdf-ga4-bars">
                ${sources.slice(0, 6).map((source) => `
                    <div class="pdf-ga4-row">
                        <strong>${escapeHtml(source.label || source.key || '')}</strong>
                        <span><i style="width:${Math.max(5, (Number(source.sessions || 0) / maxSessions) * 100)}%"></i></span>
                        <small>${formatMetric(source.sessions)} session</small>
                    </div>
                `).join('') || '<p>Nincs AI referral egyezés az importban.</p>'}
            </div>
        </section>
    `;
}

function renderVisibilityPdfSerpAioImport(importData = {}) {
    const summary = importData.summary || {};
    const domains = Array.isArray(importData.domain_breakdown) ? importData.domain_breakdown : [];
    const maxMentions = Math.max(1, ...domains.map((item) => Number(item.citation_mentions || 0) + Number(item.organic_mentions || 0)));

    return `
        <section class="panel">
            <div class="pdf-ga4-grid">
                <article class="pdf-ga4-kpi"><span>AIO jelenlét</span><strong>${Number(summary.aio_presence_rate || 0)}%</strong><small>${Number(summary.aio_present_count || 0)} / ${Number(summary.query_count || 0)} query</small></article>
                <article class="pdf-ga4-kpi"><span>Saját citáció</span><strong>${Number(summary.target_citation_rate || 0)}%</strong><small>${Number(summary.target_cited_count || 0)} queryben</small></article>
                <article class="pdf-ga4-kpi"><span>Saját organikus</span><strong>${Number(summary.target_organic_rate || 0)}%</strong><small>${Number(summary.target_organic_count || 0)} queryben</small></article>
                <article class="pdf-ga4-kpi"><span>Versenytárs domain</span><strong>${Number(summary.competitor_domain_count || 0)}</strong><small>${Number(summary.citation_count || 0)} citáció</small></article>
            </div>
            <div class="pdf-ga4-bars">
                ${domains.slice(0, 6).map((domain) => {
                    const total = Number(domain.citation_mentions || 0) + Number(domain.organic_mentions || 0);
                    return `
                        <div class="pdf-ga4-row pdf-serp-row">
                            <strong>${escapeHtml(domain.domain || '')}</strong>
                            <span><i style="width:${Math.max(5, (total / maxMentions) * 100)}%"></i></span>
                            <small>${Number(domain.citation_mentions || 0)} cit. · ${Number(domain.organic_mentions || 0)} org.</small>
                        </div>
                    `;
                }).join('') || '<p>Nincs domain bontás az importban.</p>'}
            </div>
        </section>
    `;
}

function renderVisibilityPdfGeminiGroundingRun(run = {}) {
    const summary = run.summary || {};
    const domains = Array.isArray(summary.domain_breakdown) ? summary.domain_breakdown : [];
    const maxMentions = Math.max(1, ...domains.map((item) => Number(item.mentions || 0)));

    return `
        <section class="panel">
            <div class="pdf-ga4-grid">
                <article class="pdf-ga4-kpi"><span>Grounded válasz</span><strong>${Number(summary.grounded_rate || 0)}%</strong><small>${Number(summary.grounded_count || 0)} / ${Number(summary.query_count || 0)} query</small></article>
                <article class="pdf-ga4-kpi"><span>Saját említés</span><strong>${Number(summary.target_mention_rate || 0)}%</strong><small>${Number(summary.target_mentioned_count || 0)} queryben</small></article>
                <article class="pdf-ga4-kpi"><span>Saját citáció</span><strong>${Number(summary.target_citation_rate || 0)}%</strong><small>${Number(summary.target_cited_count || 0)} queryben</small></article>
                <article class="pdf-ga4-kpi"><span>Versenytárs forrás</span><strong>${Number(summary.competitor_domain_count || 0)}</strong><small>${Number(summary.citation_count || 0)} citáció</small></article>
            </div>
            <div class="pdf-ga4-bars">
                ${domains.slice(0, 6).map((domain) => `
                    <div class="pdf-ga4-row pdf-serp-row">
                        <strong>${escapeHtml(domain.domain || '')}</strong>
                        <span><i style="width:${Math.max(5, (Number(domain.mentions || 0) / maxMentions) * 100)}%"></i></span>
                        <small>${Number(domain.mentions || 0)} jel</small>
                    </div>
                `).join('') || '<p>Nincs forrásdomain bontás a Gemini grounded futásban.</p>'}
            </div>
        </section>
    `;
}

function renderVisibilityPdfEvidence(evidence) {
    return `
        <div class="evidence-lead">
            ${(evidence.clientSummary || []).slice(0, 3).map((item) => `<p>${escapeHtml(item)}</p>`).join('') || '<p>Az újabb futások tartalmazzák a részletes mérési értelmezést.</p>'}
        </div>
        <div class="evidence-grid">
            ${renderVisibilityPdfEvidenceCard('Biztos adat', evidence.hardSignals || [])}
            ${renderVisibilityPdfEvidenceCard('Irányadó jel', evidence.directionalSignals || [])}
            ${renderVisibilityPdfEvidenceCard('Nem direkt mérés', (evidence.notMeasured || []).map((item) => ({ label: 'Korlát', value: item, detail: '' })))}
        </div>
        ${evidence.recommendedLanguage ? `<div class="client-language"><strong>Riportba illeszthető megfogalmazás</strong><p>${escapeHtml(evidence.recommendedLanguage)}</p></div>` : ''}
    `;
}

function renderVisibilityPdfEvidenceCard(title, items = []) {
    return `
        <section class="evidence-card">
            <h3>${escapeHtml(title)}</h3>
            ${items.slice(0, 5).map((item) => `<article><span>${escapeHtml(item.label || title)}</span><strong>${escapeHtml(item.value || '')}</strong>${item.detail ? `<p>${escapeHtml(item.detail)}</p>` : ''}</article>`).join('') || '<p>Nincs adat.</p>'}
        </section>
    `;
}

function renderVisibilityPdfCompetitors(competitors = [], primaryCompetitors = []) {
    if (!competitors.length) {
        return '<p class="empty-state">Nincs versenytárs adat.</p>';
    }

    return `
        <div class="competitor-list">
            ${competitors.slice(0, 6).map((item) => `
                <article class="competitor-row">
                    <div>
                        <strong>${escapeHtml(item.domain || '')}</strong>
                        <small>${item.configured ? 'Megadott versenytárs' : 'Találatokból azonosított domain'}${primaryCompetitors.includes(item.domain) ? ' · domináns minta' : ''}</small>
                    </div>
                    <em>${Number(item.query_hits || 0)}</em>
                </article>
            `).join('')}
        </div>
    `;
}

function renderVisibilityPdfMethodCard(run) {
    return `
        <div class="method-list">
            <article><strong>${escapeHtml((run.providers || []).join(', ') || 'provider')}</strong><small>Aktív keresési adatforrás</small></article>
            <article><strong>${Number(run.query_count || 0)} kérdés</strong><small>${escapeHtml(run.run_mode === 'weekly_portfolio' ? 'Heti Top 20 portfólió' : 'Generált és saját kérdések')}</small></article>
            <article><strong>${Number(run.owned_query_hits || 0)} saját találat</strong><small>${escapeHtml(run.method || 'Mentett provider találatokból számolva')}</small></article>
        </div>
    `;
}

function renderVisibilityPdfBacklog(actions = []) {
    if (!actions.length) {
        return '<p class="empty-state">Nincs generált backlog.</p>';
    }

    return `
        <div class="backlog-grid">
            ${actions.slice(0, 8).map((action) => `
                <article class="backlog-card ${action.priority === 'high' ? 'is-high' : ''}">
                    <span>${escapeHtml(action.priority === 'high' ? 'magas prioritás' : 'közepes prioritás')} · ${escapeHtml(action.recommended_asset || '')}</span>
                    <h3>${escapeHtml(action.title || '')}</h3>
                    <p><strong>Kérdés:</strong> ${escapeHtml(action.query || '')}</p>
                    <p><strong>Első lépés:</strong> ${escapeHtml(action.first_step || '')}</p>
                    <div class="chip-row">${(action.sections || []).slice(0, 5).map((section) => `<i>${escapeHtml(section)}</i>`).join('')}</div>
                </article>
            `).join('')}
        </div>
    `;
}

function renderVisibilityPdfQueries(queries = []) {
    if (!queries.length) {
        return '<p class="empty-state">Nincs kérdésszintű adat.</p>';
    }

    return `
        <div class="query-list">
            ${queries.slice(0, 18).map((item) => `
                <article class="query-row">
                    <div>
                        <strong>${escapeHtml(item.query || '')}</strong>
                        <small>${escapeHtml(item.type || 'query')} · ${escapeHtml(item.provider || 'provider')}</small>
                    </div>
                    <span class="status ${item.owned_domain_hit ? 'is-hit' : ''}">${item.owned_domain_hit ? 'látszik' : 'nem látszik'}</span>
                    <small>${escapeHtml((item.results || []).map((result) => result.host).filter(Boolean).slice(0, 5).join(', ') || item.error || 'nincs top forrás')}</small>
                </article>
            `).join('')}
        </div>
    `;
}

function formatShortDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 16);
    }

    return new Intl.DateTimeFormat('hu-HU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function buildPdfReportHtml(report) {
    const score = Number(report.overall_score || 0);
    const scores = report.scores || {};
    const recommendations = (report.recommendations || []).slice(0, 12);
    const pages = (report.pages || []).slice(0, 20);
    const glossary = Object.values(report.methodology?.glossary || {}).slice(0, 8);
    const sources = report.methodology?.sources || [];
    const generatedAt = new Date().toLocaleString('hu-HU');
    const aiText = report.ai_enrichment?.status === 'completed' ? report.ai_enrichment.analysis : '';
    const openrouterText = report.openrouter_enrichment?.status === 'completed' ? report.openrouter_enrichment.analysis : '';
    const geminiText = report.gemini_enrichment?.status === 'completed' ? report.gemini_enrichment.analysis : '';
    const visibilityPlan = report.ai_search_plan || {};
    const visibilityProbe = report.ai_enrichment?.visibility_probe || {};
    const savedSearchProbe = report.saved_search_probe || {};

    return `<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>AIO riport - ${escapeHtml(report.url || '')}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f1420; font-family: Inter, "Segoe UI", Arial, sans-serif; background: #ffffff; }
        h1, h2, h3, p { margin-top: 0; }
        .cover { min-height: 230mm; display: grid; align-content: space-between; padding: 0; }
        .cover-band { min-height: 58mm; margin: -14mm -14mm 18mm; padding: 16mm 14mm 20mm; background: linear-gradient(135deg, #ff2bc2, #6d5cff); color: #ffffff; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 850; }
        .brand-mark { width: 128px; height: 46px; display: block; padding: 5px; border-radius: 8px; background: #ffffff; }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .cover-band h1 { max-width: 620px; margin: 18mm 0 8px; font-size: 36px; line-height: 1.05; }
        .cover-band p { max-width: 650px; margin: 0; color: rgba(255,255,255,0.88); font-size: 15px; line-height: 1.55; overflow-wrap: anywhere; }
        .cover-grid { display: grid; grid-template-columns: 205px 1fr; gap: 18px; align-items: stretch; margin-top: 6mm; }
        .score-panel, .meta-panel, .summary-tile { border: 1px solid #efd9ea; border-radius: 8px; background: #fffaff; box-shadow: 0 12px 32px rgba(105, 0, 81, 0.07); }
        .score-panel { display: grid; place-items: center; padding: 16px; }
        .score-circle { width: 158px; height: 158px; display: grid; place-items: center; border-radius: 50%; background: conic-gradient(#ff2bc2 0deg, #ff2bc2 ${score * 3.6}deg, #f3e4ef ${score * 3.6}deg); box-shadow: inset 0 0 0 16px #ffffff; color: #1f1420; font-size: 42px; font-weight: 900; border: 1px solid #efd9ea; }
        .score-caption { margin-top: 10px; color: #765f72; font-size: 12px; font-weight: 850; text-transform: uppercase; }
        .meta-panel { display: grid; gap: 9px; padding: 16px; color: #765f72; font-size: 14px; }
        .meta-panel strong { color: #1f1420; }
        .summary-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
        .summary-tile { padding: 13px; }
        .summary-tile span { display: block; color: #765f72; font-size: 11px; font-weight: 850; text-transform: uppercase; }
        .summary-tile strong { display: block; margin-top: 5px; font-size: 22px; color: #c4008f; }
        .section { break-inside: avoid; page-break-inside: avoid; margin: 0 0 18px; }
        .page-break { break-before: page; page-break-before: always; }
        .section h2 { font-size: 22px; margin-bottom: 4px; }
        .section-lead { margin: 0 0 12px; color: #765f72; font-size: 13px; line-height: 1.5; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .metric-card, .rec-card, .page-row, .signal-row, .glossary-card, .ai-card, .method-card, .query-card { border: 1px solid #efd9ea; border-radius: 8px; padding: 12px; background: #fffaff; }
        .metric-card strong { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; }
        .bar { height: 8px; margin-top: 9px; overflow: hidden; border-radius: 999px; background: #e8f0ed; }
        .bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #ff2bc2, #6d5cff); }
        .rec-list, .page-list, .signal-list, .glossary-list { display: grid; gap: 9px; }
        .rec-card { display: grid; grid-template-columns: 8px 1fr; gap: 10px; background: #ffffff; }
        .dot { border-radius: 999px; background: #6d5cff; }
        .rec-card[data-level="critical"] .dot { background: #d84a4a; }
        .rec-card[data-level="warning"] .dot { background: #d28a1f; }
        .rec-card h3 { font-size: 14px; margin-bottom: 5px; }
        .rec-card p, .page-row small, .signal-row small, .glossary-card p, .glossary-card small, .ai-card, .method-card p { color: #765f72; line-height: 1.45; font-size: 12px; }
        .rec-card .category { display: inline-flex; margin-bottom: 7px; border-radius: 999px; padding: 4px 7px; background: #fff0fb; color: #c4008f; font-size: 10px; font-weight: 900; }
        .page-row, .signal-row { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        .pill { border-radius: 999px; padding: 5px 8px; background: #fff0fb; color: #c4008f; font-weight: 850; font-size: 12px; white-space: nowrap; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .method-flow { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
        .method-card { min-height: 90px; }
        .method-card span { display: inline-flex; margin-bottom: 6px; border-radius: 999px; padding: 4px 7px; background: #fff0fb; color: #c4008f; font-size: 10px; font-weight: 900; }
        .method-card strong { display: block; margin-bottom: 4px; font-size: 12px; }
        .query-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
        .query-card span { display: inline-block; margin-bottom: 6px; border-radius: 999px; padding: 4px 7px; background: #fff0fb; color: #c4008f; font-size: 10px; font-weight: 900; }
        .query-card h3 { margin: 0 0 6px; font-size: 13px; line-height: 1.3; }
        .source-list { display: grid; gap: 6px; font-size: 11px; color: #765f72; }
        .source-list a { color: #c4008f; text-decoration: none; overflow-wrap: anywhere; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #efd9ea; color: #765f72; font-size: 11px; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="position:fixed;right:16px;top:16px;z-index:5;border:0;border-radius:8px;background:#12211e;color:#fff;padding:10px 14px;font-weight:800;cursor:pointer;">PDF mentése</button>
    <section class="cover">
        <div class="cover-band">
            <div class="brand"><span class="brand-mark"><img src="assets/img/hello_ai_audit_logo_transparent.png" alt="Hello AI Audit"></span><span>AI keresési audit</span></div>
            <h1>Infografikus AI keresési audit riport</h1>
            <p>${escapeHtml(report.url || '')}</p>
        </div>
        <div>
            <div class="cover-grid">
                <div class="score-panel">
                    <div class="score-circle">${score}</div>
                    <div class="score-caption">Összpontszám</div>
                </div>
                <div class="meta-panel">
                    <div><strong>Minősítés:</strong> ${escapeHtml(report.summary?.label || '')}</div>
                    <div><strong>Vizsgált oldalak:</strong> ${Number(report.summary?.pages_checked || 0)} / ${Number(report.crawl_limit || 0)}</div>
                    <div><strong>Feltérképezett URL-ek:</strong> ${Number(report.site_map?.discovered_url_count || report.summary?.pages_checked || 0)}</div>
                    <div><strong>Riport generálva:</strong> ${escapeHtml(generatedAt)}</div>
                    <div><strong>Fókusz:</strong> SEO alapok, AI érthetőség, UX útvonal, entitásbizalom és citációs alkalmasság.</div>
                </div>
            </div>
            <div class="summary-strip">
                <div class="summary-tile"><span>Kritikus elem</span><strong>${Number(report.summary?.critical_count || 0)}</strong></div>
                <div class="summary-tile"><span>Figyelmeztetés</span><strong>${Number(report.summary?.warning_count || 0)}</strong></div>
                <div class="summary-tile"><span>Elemzett oldal</span><strong>${Number(report.summary?.pages_checked || 0)}</strong></div>
            </div>
        </div>
        <div class="footer">A riport döntéstámogató auditanyag. Nem garantál AI Overview vagy LLM citációt, hanem a javítási irányokat és kockázatokat priorizálja.</div>
    </section>

    <section class="section page-break">
        <h2>Részpontszámok</h2>
        <p class="section-lead">A pontszámok azt mutatják, hol érdemes először javítani, hogy az oldal keresőben és AI-válaszokban is érthetőbb forrás legyen.</p>
        <div class="cards">
            ${Object.entries(metricLabels).map(([key, label]) => metricCard(label, Number(scores[key] || 0))).join('')}
        </div>
    </section>

    <section class="section">
        <h2>Legfontosabb javítási javaslatok</h2>
        <p class="section-lead">A lista hatás szerint rendezett. Minden pontnál látszik a probléma, az üzleti/felhasználói kockázat, a javasolt megoldás és az első lépés.</p>
        <div class="rec-list">
            ${recommendations.map((item) => `
                <article class="rec-card" data-level="${escapeHtml(item.level || '')}">
                    <div class="dot"></div>
                    <div>
                        <h3>${escapeHtml(item.title || '')}</h3>
                        <span class="category">${escapeHtml(item.category || 'Audit')}</span>
                        <p><strong>Hatás:</strong> ${escapeHtml(item.impact || '')}</p>
                        <p><strong>Miért gond:</strong> ${escapeHtml(item.why || '')}</p>
                        <p><strong>Javítás:</strong> ${escapeHtml(item.fix || '')}</p>
                        <p><strong>Első lépés:</strong> ${escapeHtml(item.next_step || '')}</p>
                        <p><strong>Érintett oldalak:</strong> ${(item.pages || []).map(escapeHtml).join(', ')}</p>
                    </div>
                </article>
            `).join('')}
        </div>
    </section>

    ${renderPdfVisibilitySection(visibilityPlan, visibilityProbe, savedSearchProbe)}

    <section class="section page-break">
        <h2>Vizsgált oldalak</h2>
        <p class="section-lead">${escapeHtml(report.site_map?.coverage_note || 'A vizsgált oldalak a domain priorizált belső URL-listájából kerültek ki.')}</p>
        <div class="page-list">
            ${pages.map((page) => `
                <article class="page-row">
                    <div>
                        <strong>${escapeHtml(page.url || '')}</strong><br>
                        <small>${escapeHtml(page.title || page.error || 'Nincs cím')} · ${Number(page.word_count || 0)} szó · ${Number(page.load_time_ms || 0)} ms</small>
                    </div>
                    <span class="pill">${Number(page.score || 0)}/100</span>
                </article>
            `).join('')}
        </div>
    </section>

    <section class="section">
        <h2>AIO jelek áttekintése</h2>
        <p class="section-lead">Ezek a jelek segítik, hogy a rendszer felismerje az oldal témáját, szerzőjét, bizonyítékait és idézhető válaszrészeit.</p>
        <div class="signal-list">
            ${pdfSignalRows(pages)}
        </div>
    </section>

    <section class="section page-break">
        <h2>Módszertani folyamat</h2>
        <div class="method-flow">
            ${pdfMethodFlow()}
        </div>
    </section>

    <section class="section">
        <h2>Fogalmi háttér</h2>
        <div class="two-col">
            ${glossary.map((entry) => `
                <article class="glossary-card">
                    <h3>${escapeHtml(entry.term || '')}</h3>
                    <p>${escapeHtml(entry.short || '')}</p>
                    <small>${escapeHtml(entry.details || '')}</small>
                </article>
            `).join('')}
        </div>
    </section>

    ${aiText ? `
    <section class="section page-break">
        <h2>OpenAI másodelemzés</h2>
        <article class="ai-card">${formatPdfText(aiText)}</article>
    </section>` : ''}

    ${openrouterText ? `
    <section class="section">
        <h2>OpenRouter gyors másodvélemény</h2>
        <p class="section-lead">Modell: ${escapeHtml(report.openrouter_enrichment?.model || 'OpenRouter')}. Ez audit JSON alapú kontrollréteg, nem live web_search mérés.</p>
        <article class="ai-card">${formatPdfText(openrouterText)}</article>
    </section>` : ''}

    ${geminiText ? `
    <section class="section">
        <h2>Gemini kontrollvélemény</h2>
        <p class="section-lead">Modell: ${escapeHtml(report.gemini_enrichment?.model || 'Gemini')}. Google/Gemini szemléletű audit-kontroll, nem garantált AI Overview panelmérés.</p>
        <article class="ai-card">${formatPdfText(geminiText)}</article>
    </section>` : ''}

    <section class="section">
        <h2>Források</h2>
        <div class="source-list">
            ${sources.map((source) => `<a href="${escapeHtml(source.url || '')}">${escapeHtml(source.title || '')}: ${escapeHtml(source.note || '')}</a>`).join('')}
        </div>
        <div class="footer">Készült az AIO Audit Studio export funkciójával.</div>
    </section>
</body>
</html>`;
}

function metricCard(label, value) {
    return `
        <article class="metric-card">
            <strong><span>${escapeHtml(label)}</span><span>${value}/100</span></strong>
            <div class="bar"><span style="width:${Math.max(0, Math.min(100, value))}%"></span></div>
        </article>
    `;
}

function pdfMethodFlow() {
    const steps = [
        ['01', 'Cél', 'Célközönség, üzleti cél és látogatói útvonal.'],
        ['02', 'Raw HTML', 'A fő tartalom JavaScript nélkül is olvasható-e?'],
        ['03', 'UX útvonal', 'Navigáció, CTA, kontakt és lead capture logika.'],
        ['04', 'Idézhetőség', 'Rövid válaszblokkok és jól kiemelhető összefoglalók.'],
        ['05', 'Teendők', 'Probléma, miért probléma és első javítási lépés.'],
    ];

    return steps.map(([number, title, text]) => `
        <article class="method-card">
            <span>${number}</span>
            <strong>${escapeHtml(title)}</strong>
            <p>${escapeHtml(text)}</p>
        </article>
    `).join('');
}

function renderPdfVisibilitySection(plan, probe, savedSearchProbe = {}) {
    const readiness = plan.static_readiness || {};
    const queries = (plan.query_set || []).slice(0, 6);
    const probeResult = probe?.result || {};
    const savedSearchQueries = Array.isArray(savedSearchProbe.query_results) ? savedSearchProbe.query_results.slice(0, 6) : [];
    const savedSearchSummary = savedSearchProbe?.status === 'completed'
        ? `Mentett keresési adatok: saját-domain találati arány ${Number(savedSearchProbe.retrieval_hit_rate || 0)}%, ${Number(savedSearchProbe.owned_domain_query_hits || 0)} kérdésben saját találat.`
        : (savedSearchProbe?.message || 'Mentett keresési provider-adat még nincs bekapcsolva vagy nincs beállítva.');
    const probeSummary = probe?.status === 'completed'
        ? `OpenAI web_search próba: coverage ${Number(probeResult.coverage_rate || 0)}%, citation ${Number(probeResult.citation_rate || 0)}%.`
        : 'Live AI keresési próba nem készült vagy nincs bekapcsolva; a kérdéssor mérési protokollként használható.';

    return `
        <section class="section page-break">
            <h2>AI keresési láthatósági próba</h2>
            <p class="section-lead">${escapeHtml(probeSummary)} ${escapeHtml(probe?.limitation || plan.implementation_note || '')}</p>
            <div class="cards">
                ${metricCard('Citation selection', toPercentNumber(readiness.citation_selection_readiness))}
                ${metricCard('Answer absorption', toPercentNumber(readiness.citation_absorption_readiness))}
                ${metricCard('Raw HTML arány', toPercentNumber(readiness.interpretable_pages_ratio))}
            </div>
            <p class="section-lead" style="margin-top:12px;">${escapeHtml(savedSearchSummary)}</p>
            ${savedSearchQueries.length ? `
                <div class="query-grid" style="margin-top:10px;">
                    ${savedSearchQueries.map((item) => `
                        <article class="query-card">
                            <span>${item.owned_domain_hit ? 'saját domain látszik' : 'saját domain nem látszik'} · ${escapeHtml(item.provider || 'provider')}</span>
                            <h3>${escapeHtml(item.query || '')}</h3>
                            <p>${escapeHtml([
                                asArray(item.competitors).length ? `Versenytársak: ${asArray(item.competitors).slice(0, 5).join(', ')}` : '',
                                Array.isArray(item.results) && item.results.length ? `Top források: ${item.results.map((result) => result.host).filter(Boolean).slice(0, 4).join(', ')}` : '',
                            ].filter(Boolean).join(' · ') || 'Nincs részletes keresési adat.')}</p>
                        </article>
                    `).join('')}
                </div>
            ` : ''}
            <div class="query-grid" style="margin-top:10px;">
                ${queries.map((item) => `
                    <article class="query-card">
                        <span>${escapeHtml(item.type || 'query')}</span>
                        <h3>${escapeHtml(item.query || '')}</h3>
                        <p>${escapeHtml(item.expected_signal || '')}</p>
                    </article>
                `).join('') || '<p class="section-lead">Nincs generált kérdéssor.</p>'}
            </div>
        </section>
    `;
}

function pdfSignalRows(pages) {
    const signalMap = {
        has_raw_html_content: 'Raw HTML-ben látható tartalom',
        avoids_client_rendered_shell: 'Nem üres SPA shell',
        has_low_js_dependency: 'Alacsony JS-függőség',
        has_meta_viewport: 'Mobil viewport meta',
        has_clear_page_purpose: 'Egyértelmű oldal cél',
        has_manageable_navigation: 'Átlátható navigáció',
        has_consistent_cta_language: 'Következetes CTA nyelv',
        has_contact_path: 'Kontakt útvonal',
        has_contextual_qa_support: 'Kontextushoz illő Q&A',
        has_organization_schema: 'Organization/LocalBusiness séma',
        has_stable_schema_ids: 'Stabil @id azonosítók',
        has_sameas_identity: 'sameAs kapcsolatok',
        has_answer_first_blocks: 'Citációméretű válaszblokkok',
        has_citation_signal: 'Forrás/hivatkozás jel',
        has_claim_evidence_language: 'Bizonyítéknyelv és adatjelek',
        has_canonical: 'Canonical link',
    };

    return Object.entries(signalMap).map(([key, label]) => {
        const count = pages.filter((page) => page.signals?.[key]).length;
        const pct = pages.length ? Math.round((count / pages.length) * 100) : 0;
        return `
            <article class="signal-row">
                <div><strong>${escapeHtml(label)}</strong><br><small>${count}/${pages.length} oldalon aktív</small></div>
                <span class="pill">${pct}%</span>
            </article>
        `;
    }).join('');
}

function formatPdfText(value) {
    return escapeHtml(value)
        .replace(/\n{2,}/g, '<br><br>')
        .replace(/\n/g, '<br>');
}
