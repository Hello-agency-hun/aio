<?php
/**
 * Alkalmazás konfiguráció.
 *
 * A fájl minden olyan beállítást egy helyen tart, amelyet shared hostingon
 * várhatóan módosítani kell. A konstansokat egyszerűen lehet finomhangolni
 * anélkül, hogy az audit logikához vagy a felülethez hozzá kellene nyúlni.
 */

declare(strict_types=1);

const APP_NAME = 'Hello AI Audit';
const APP_VERSION = '1.0.0';

// Egyszerű belépési adatok a privát auditfelülethez.
// Éles többfelhasználós működéshez később érdemes jelszó-hashre vagy
// tárhelyszintű környezeti változóra cserélni.
const AUTH_USERNAME = 'admin';
const AUTH_PASSWORD = 'aiaudit';

// Alapértelmezett részletesen elemzett belső oldalszám.
// A felületen ez dinamikusan választható, nem fix korlátként működik.
const DEFAULT_CRAWL_PAGES = 60;

// Shared hosting kompatibilis felső védőkorlát a részletes HTML elemzéshez.
const MAX_CRAWL_PAGES = 120;

// Ennyi URL-t térképezhet fel a rendszer sitemapból és belső linkekből,
// majd ebből választja ki a részletesen elemzendő, legfontosabb oldalakat.
const MAX_DISCOVERED_URLS = 500;

// Egy oldal letöltésénél használt időkorlát másodpercben.
const REQUEST_TIMEOUT = 12;

// Az OpenAI másodelemzés hosszabb válaszidejét külön kezeljük.
// Lassú audit vagy terhelt API esetén a 45 másodperc kevés lehet.
const OPENAI_REQUEST_TIMEOUT = 180;
const OPENAI_CONNECT_TIMEOUT = 20;

// Védelem túl nagy HTML válaszok ellen. Shared hostingon fontos a memóriafegyelem.
const MAX_RESPONSE_BYTES = 1500000;

// Riportok tárolási helye. A /data könyvtár .htaccess védelemmel kerül lezárásra.
const DATA_DIR = __DIR__ . '/../data';
const REPORT_DIR = DATA_DIR . '/reports';

// Alap user-agent, hogy a céloldal naplóiban is átlátható legyen az audit forrása.
const AUDIT_USER_AGENT = 'AIOAuditStudio/1.0 (+https://example.com/aio-audit)';
