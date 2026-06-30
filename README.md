# Hello AI Audit

PHP, HTML, CSS és JavaScript alapú, shared hosting kompatibilis AIO/SEO audit webalkalmazás.

## Telepítés

1. Másold fel a teljes könyvtárat a tárhelyre.
2. Ellenőrizd, hogy a `data/reports` könyvtár írható legyen a PHP számára.
3. A `data/.htaccess` tiltja a JSON riportok közvetlen webes elérését Apache szerveren.
4. Nyisd meg az `index.php` fájlt böngészőben.

## Belépés

Az auditfelület session alapú belépést kér.

Alap belépési adatok:

```text
Felhasználónév: admin
Jelszó: aiaudit
```

Később itt tudod átírni:

```php
includes/config.php
const AUTH_USERNAME = 'admin';
const AUTH_PASSWORD = 'aiaudit';
```

A védelem nem csak az `index.php` oldalon aktív: az audit API, a progress API, a korábbi riportok visszatöltése és a letöltési végpontok is bejelentkezést kérnek.

## Korábbi adatok törlése

- A `Korábbi riportok` blokkban minden mentett elemzés mellett van `Törlés` gomb.
- A `Sessionök törlése` gomb csak a technikai progress/session JSON fájlokat üríti a `data/progress` könyvtárból.
- A session törlés nem töröl riportot és nem jelentkeztet ki.

## AI láthatóságmérés

Az `AI láthatóság` blokk ismételhető mérési profilt ad az egyszeri audit mellé.

1. Add meg a saját domaint.
2. Írj be 3-8 fontos témát vagy vevői kérdéskört.
3. Válaszd ki az üzleti modellt: B2B szolgáltatás, helyi szolgáltatás, e-commerce, SaaS, szakértői brand vagy általános oldal.
4. Adj meg saját mérési kérdéseket, ha konkrét sales vagy ügyfélkérdést szeretnél elsőbbséggel mérni.
5. Állítsd be, hány kérdést fusson le a mérésben.
6. Adj meg ismert versenytárs domaineket, ha vannak.
7. A `Kérdések előnézete` gombbal ellenőrizd a mérési csomagot.
8. Mentsd a projektet, majd futtasd a mérést.

A rendszer üzleti modell szerint generál kérdésvariánsokat. B2B projektnél partner-, referencia- és döntési kérdések, SaaS-nál alternatíva-, funkció- és bevezetési kérdések, e-commerce-nél termék-, összehasonlító és vásárlási útmutató kérdések kerülnek előtérbe. A saját kérdések mindig elsőbbséget kapnak. A keresési provider-adatokból az app megméri a saját-domain jelenlétet, majd share of voice, versenytárslista, átlagos saját pozíció és megbízhatósági címke alapján mutatja az eredményt.

A kérdéselőnézet nem ment projektet és nem hív keresési providert. Csak az aktuális űrlapbeállítások alapján megmutatja, milyen kérdésekkel futna a mérés.

### Heti Top 20 query workflow

A `Heti Top 20 query portfólió` mező rögzített monitoring kérdéssort tárol. Ez különbözik a generált kérdéselőnézettől: a cél az, hogy hétről hétre ugyanazokat a kérdéseket mérd, így a trend valóban összehasonlítható.

Ajánlott kategóriaformátum:

```text
brand: Saját márka + fő szolgáltatás
buyer: Melyik szolgáltatót érdemes választani erre?
comparison: Saját márka alternatívái
competitor: Versenytárs vs saját márka
pricing: Mennyibe kerül ez a megoldás?
local: Legjobb szolgáltató adott piacon
expert: Ki ért ehhez a témához?
```

- A `Top 20 előnézet` gomb csak a rögzített portfóliót mutatja.
- A `Heti Top 20 futtatása` gomb `weekly_portfolio` módban indít mérést.
- Ha nincs mentett portfólió, a heti futtatás nem indul el; ilyenkor előbb mentsd a projektet Top 20 kérdésekkel.
- A futás JSON-ban külön jelölést kap: `run_mode: weekly_portfolio`.

Mérés közben a felület részletes státuszt mutat: előkészítés, kérdéssoros keresés, összesítés, JSON mentés és AI stratégiai értelmezés. Ha van OpenAI kulcs, az OpenAI készíti az értelmezést; ha az nem érhető el, az OpenRouter fallback próbál közérthető javítási tervet adni. API-tesztelésnél az `include_ai=0` paraméterrel az AI értelmezés kihagyható.

A projekt megnyitásakor az app betölti a korábbi visibility futásokat is. A dashboard SVG trendgrafikont, futáslistát, futásonkénti megnyitást és futástörlést ad. A `Visibility PDF` gomb külön, nyomtatható, A4 landscape sablonriportot nyit: külön cover, KPI, trend, share of voice, bizonyossági értelmezés, versenytárs benchmark, backlog és kérdésszintű bizonyítéktár blokkokkal.

Minden visibility futás automatikus `Tartalmi javítási backlogot` is kap. A backlog megmutatja, mely kérdésekhez hiányzik saját tartalmi eszköz, milyen oldalt vagy blokkot érdemes készíteni, milyen H1-et használj, milyen szekciók legyenek rajta, és mely versenytárs/forrásminták jelentek meg a találati térben.

Az új visibility futások `Biztos vs irányadó` magyarázó blokkot is kapnak. Ez különválasztja a mentett provider-találatokból származó biztos adatokat, az AI keresési láthatóságra vonatkozó irányadó következtetéseket, és azokat a korlátokat, amelyeket ügyfélriportban nem szabad abszolút állításként kezelni.

Az új visibility futások `report_layers` blokkot is mentenek. Ez a riportban külön kezeli a `Mérési réteget` és a `Javítási réteget`: az első csak a ténylegesen lekért provider-adatokat és számított metrikákat foglalja össze, a második ezekből levezetett tartalmi/citációs munkatervet ad. Ugyanez megjelenik a dashboardon és a Visibility PDF-ben is.

Az audit riportban külön `Erőforrások` fül mutatja a Cost / token / cache kontrollnézetet. Ez nem számlázási modul, hanem működési dashboard: OpenAI/OpenRouter usage adatok, keresési provider cache hit rate, külső hívások, provider hibák és hiányzó tokenadatok áttekintése. A visibility futások is kapnak `resource_summary` mezőt és külön erőforrás-kártyát a dashboardon.

Az AI láthatósági projekt GA4 CSV importot is fogad. Exportálj GA4-ből source/medium, session source, referrer vagy landing page dimenziókkal és sessions/users/key events metrikákkal, majd töltsd fel vagy másold be. Az app felismeri a ChatGPT/OpenAI, Perplexity, Gemini, Copilot, Claude, Poe, You.com, Phind, Consensus és hasonló AI referral mintákat, majd `data/ga4_referral_imports` alá menti az összesítést.

A Google SERP / AI Overview bizonyíték külön importként menthető. A `SerpApi live próba` gomb a mentett visibility projektből 3 kérdést futtat Google/SerpApi-n keresztül, majd automatikusan SERP/AIO bizonyítékként menti. Emellett DataForSEO vagy kézi SERP export JSON/CSV tartalmat is fel lehet tölteni vagy bemásolni. A parser kinyeri a queryket, AI Overview jelenlétet, citált URL-eket, organikus találatokat, saját-domain citációt és versenytárs domain bontást, majd `data/serp_aio_imports` alá menti.

Az adatok külön JSON mappákba kerülnek:

```text
data/visibility_projects
data/visibility_runs
data/ga4_referral_imports
data/serp_aio_imports
data/gemini_grounding_runs
```

Ez a mérés irányadó jel, nem abszolút AI válaszpanel-garancia. A ChatGPT, Gemini, Perplexity és Google AI felületek válaszai változhatnak, ezért stratégiai használatnál érdemes heti ismétléssel és több kérdéskörrel trendet nézni.

## Működés

- Az audit URL alapján indul. A felületen választható, hány kiemelt URL kap teljes oldalszintű elemzést: gyors kép, ajánlott okos térkép, nagy webhely vagy egyedi keret.
- A crawler nem egyszerűen az első N linket nézi: sitemapból, kezdőoldali navigációból és belső linkekből épít domainképet, majd ebből priorizálja a részletesen elemzendő oldalakat.
- A riportokat JSON fájlokként menti a `data/reports` könyvtárba.
- A felület Ajax kéréssel futtatja az elemzést, majd tabos nézetben mutatja a javaslatokat.
- Az AIO/GEO módszertan nyolc részpontszámot használ: technikai felfedezhetőség, SEO, AI/GEO érthetőség, AI keresési próba, UX útvonal, tartalmi megkülönböztethetőség, entitásbizalom, valamint agentikus és raw HTML használhatóság.
- Az `AI keresési próba` fül query batch-et készít: brand, kategória, buyer-intent, összehasonlító, forráskereső és query fan-out kérdésekkel méri, hogy az AI keresők várhatóan kihozzák-e a domaint.
- A mentett keresési adatgyűjtő réteg külön provider-adaptert használ: SerpApi, SearXNG, LangSearch vagy Jina találatokat cache-el JSON fájlba, majd ezeket az OpenAI elemzés is megkapja keresési bizonyítékként.
- A módszertan a feltöltött AIO elemzési segédlet, Google Search Central irányelvek, a 2026-os FAQ rich result kivezetés és friss GEO kutatások alapján készült.
- A crawler külön méri, hogy a fontos tartalom látszik-e a JavaScript futtatása előtti HTML-ben, mert több AI crawler csak a nyers HTML választ dolgozza fel.
- A riportok a felhasználói leirat alapján `probléma / miért probléma / első lépés` szerkezetben adják vissza a javaslatokat, és külön figyelik a célközönség, user journey, navigáció, CTA és lead capture logikát.
- A riportban külön domain térkép jelzi, hány URL-t talált a rendszer, hány oldalt elemzett részletesen, és milyen oldaltípusok kerültek be a mintába.
- A riportban külön `Szótár` fül magyarázza az AIO/GEO fogalmakat, az `AIO jelek` fülön pedig hover/fókusz tooltip segíti az értelmezést.
- Audit után a `PDF riport` gomb nyomtatható, infografikus riportnézetet nyit. A böngésző nyomtatási ablakában a célként válaszd a `Mentés PDF-ként` opciót.

## OpenAI másodelemzés és live AI keresési próba

Az OpenAI API kulcsot ne írd közvetlenül a publikus PHP fájlokba.

Ajánlott megoldás:

```bash
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.5
OPENAI_TIMEOUT=180
OPENAI_CONNECT_TIMEOUT=20
OPENAI_MAX_OUTPUT_TOKENS=6500
OPENAI_ENABLE_VISIBILITY_PROBE=1
```

Shared hostingon alternatíva:

1. Másold a `data/openai_config.sample.php` fájlt `data/openai_config.php` néven.
2. Írd be az `api_key` értéket.
3. Ha lassú válaszok miatt megszakad az elemzés, állítsd a `timeout` értékét például `180` vagy `240` másodpercre.
4. Ellenőrizd, hogy a `data/.htaccess` tiltás aktív legyen.

Az alapértelmezett modell `gpt-5.5`, az OpenAI másodelemzés alap időkorlátja 180 másodperc, az AI riport alap kimeneti kerete pedig 6500 token. Ha a tárhely PHP/cURL CA bundle-je hiányos, az OpenAI fül TLS hibát jelezhet; ilyenkor a tárhelyen a tanúsítványláncot kell javítani, nem a TLS ellenőrzést kikapcsolni.

Ha az OpenAI kulcs elérhető és az `OPENAI_ENABLE_VISIBILITY_PROBE` nincs `0` értékre állítva, az app a Responses API `web_search` eszközével külön AI keresési láthatósági próbát is futtat. Ez coverage rate-et, citation rate-et, versenytárs co-mentiont és narratíva kockázatot ad vissza. Fontos: ez OpenAI webes keresési mérés, nem teljes Google AI Mode / Gemini / Perplexity panel, ezért stratégiai monitoringnál platformonként ismételni kell.

## OpenRouter gyors másodvélemény

Az OpenRouter integráció OpenAI-kompatibilis Chat Completions API-t használ. Shared hostingon nem kell Node SDK; a PHP backend közvetlenül hívja az OpenRouter végpontot.

Környezeti változókkal:

```bash
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_MODEL=openrouter/free
OPENROUTER_TIMEOUT=120
OPENROUTER_CONNECT_TIMEOUT=20
OPENROUTER_MAX_TOKENS=6000
OPENROUTER_ENABLED=1
OPENROUTER_ENABLE_ONLINE_PROBE=0
OPENROUTER_ONLINE_MODEL=openrouter/free
OPENROUTER_ONLINE_MAX_RESULTS=3
OPENROUTER_ONLINE_MAX_TOKENS=4200
```

Shared hostingon alternatíva:

1. Másold a `data/openrouter_config.sample.php` fájlt `data/openrouter_config.php` néven.
2. Írd be az `api_key` értéket.
3. Az alapértelmezett modell `openrouter/free`.
4. Ha a tárhely vagy a helyi PHP `unable to get local issuer certificate` hibát ad, hagyd meg a `data/cacert.pem` fájlt, vagy állítsd a `ca_bundle` értékét a szerveren elérhető CA bundle útvonalára.

Ez a réteg audit JSON alapú második szakértői véleményt ad. Nem live web_search mérés, tehát a tényleges “kihoz-e az AI?” vizsgálatot továbbra is az OpenAI web_search vagy későbbi Gemini/Perplexity/Google AI Mode csatlakozók adják.

Opcionálisan bekapcsolható OpenRouter online keresési próba is az `OPENROUTER_ENABLE_ONLINE_PROBE=1` vagy a `data/openrouter_config.php` `enable_online_probe => true` beállítással. Az OpenRouter dokumentációja szerint a `openrouter:web_search` server tool webes találatai extra költséget okozhatnak még ingyenes modell mellett is, ezért ez alapból kikapcsolva marad.

## Gemini kontrollvélemény

A Gemini integráció Google/Gemini szemléletű kontrollréteget ad az audit riporthoz, és fallbackként használható a visibility stratégiai értelmezésnél is. PHP/cURL alapon fut, tehát shared hostingon nem kell Node vagy Google SDK.

Környezeti változókkal:

```bash
GEMINI_API_KEY=...
GEMINI_MODEL=gemini-flash-latest
GEMINI_TIMEOUT=150
GEMINI_CONNECT_TIMEOUT=20
GEMINI_MAX_OUTPUT_TOKENS=6000
GEMINI_GROUNDED_TIMEOUT=150
GEMINI_GROUNDED_MAX_OUTPUT_TOKENS=1400
GEMINI_ENABLED=1
```

Shared hostingon alternatíva:

1. Másold a `data/gemini_config.sample.php` fájlt `data/gemini_config.php` néven.
2. Írd be az `api_key` értéket.
3. Ha hosszabb választ szeretnél, növeld a `max_output_tokens` értéket.
4. Lassú válasz esetén növeld a `timeout` értéket például `180` vagy `240` másodpercre.

Ez a réteg audit JSON és keresési bizonyíték alapján ad kontrollvéleményt. Nem garantált Google AI Overview panelmérés, hanem irányadó Google/Gemini nézőpontú javítási terv.

A visibility felületen a `Gemini grounded próba` gomb 3 projekt-kérdést futtat Gemini Google Search groundinggal. Ez nem klasszikus SERP export: azt méri, hogy a Gemini grounded válasza milyen webes forrásokra épül, említi-e vagy idézi-e a saját domaint, és milyen versenytárs források jelennek meg. Az eredmények `data/gemini_grounding_runs` alá kerülnek, visszatölthetők a dashboardon és bekerülnek a Visibility PDF-be is.

## Mentett keresési adatgyűjtés

Ez a réteg klasszikus webes keresési találatokat ment le a `data/search_cache` könyvtárba. A cél az, hogy az OpenAI elemzés konkrét találati adatokból is lássa, előkerül-e a saját domain, milyen források dominálnak, és milyen versenytárs domainek jelennek meg.

Shared hostingon:

1. Másold vagy szerkeszd a `data/search_config.php` fájlt.
2. Adj meg legalább egy providert:
   - `searxng_base_url`: saját vagy megbízható SearXNG instance URL.
   - `langsearch_api_key`: LangSearch Web Search API kulcs.
   - `jina_api_key`: Jina Search API kulcs.
3. A találatok automatikusan cache-elődnek, alapból 24 óráig.

Provider nélkül a modul nem hibázik, hanem `setup_required` állapotot jelez az `AI keresési próba` fülön. Ez szándékos: így a teljes audit működik akkor is, ha még nincs külön keresési API beállítva.

## Biztonsági megjegyzések

- Csak `http` és `https` URL-ek engedélyezettek, de a felület elfogadja a protokoll nélküli domaineket is, például `www.pelda.hu`.
- A letöltés idő- és méretlimittel fut.
- A HTML kimenet escape-elve jelenik meg.
- A JSON adatmappa Apache `.htaccess` tiltást kapott.
- Privát és lokális IP tartományok auditálását a rendszer SSRF védelem miatt elutasítja.
