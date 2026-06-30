<?php
/**
 * AIO/GEO módszertani tudásbázis.
 *
 * A pontozás nem egyetlen "SEO checklistából" áll. A felhasználó által adott
 * AIO elemzési segédletet, a Google 2026-os generatív keresési útmutatóját és
 * a GEO témájú friss kutatási irodalmat egy gyakorlati, auditálható keretbe
 * rendezzük. A források röviden vannak rögzítve, hogy a riport JSON-ban is
 * önmagyarázó maradjon.
 */

declare(strict_types=1);

function aio_methodology_sources(): array
{
    return [
        [
            'title' => 'Checkers Kft. felhasználói audit leirat',
            'url' => '#internal-user-feedback',
            'note' => 'Belső kvalitatív visszajelzés: a riport legyen high-level, célközönségből induló, probléma-miért-megoldás szerkezetű, és külön figyelje a navigációt, CTA-kat, külső útvonalakat és user journey logikát.',
        ],
        [
            'title' => 'GEO: Generative Engine Optimization',
            'url' => 'https://arxiv.org/abs/2311.09735',
            'note' => 'KDD 2024-re elfogadott munka; a generatív válaszmotorokban mérhető láthatóság és citálhatóság keretrendszerét vezeti be.',
        ],
        [
            'title' => 'Google: Optimizing for generative AI features on Google Search',
            'url' => 'https://developers.google.com/search/docs/fundamentals/ai-optimization-guide',
            'note' => 'Hivatalos Google Search Central útmutató: generatív keresésben továbbra is az alap SEO, hasznos tartalom és crawlolhatóság a stabil alap.',
        ],
        [
            'title' => 'Google: AI features and your website',
            'url' => 'https://developers.google.com/search/docs/appearance/ai-features',
            'note' => 'Leírja az AI Overviews/AI Mode működését, a query fan-out elvet és a megjelenés technikai feltételeit.',
        ],
        [
            'title' => 'Google structured data guidelines',
            'url' => 'https://developers.google.com/search/docs/appearance/structured-data/sd-policies',
            'note' => 'JSON-LD ajánlások, látható tartalommal egyező strukturált adat, @id kapcsolatok és minőségi korlátok.',
        ],
        [
            'title' => 'Google FAQ structured data deprecation',
            'url' => 'https://developers.google.com/search/docs/appearance/structured-data/faqpage',
            'note' => '2026. május 7-től a FAQ rich results már nem jelennek meg Google Searchben. A FAQPage schema nem általános B2B vagy e-kereskedelmi gyorsnyerő; csak valós, látható kérdés-válasz tartalomhoz érdemes megtartani.',
        ],
        [
            'title' => 'Google JavaScript SEO basics',
            'url' => 'https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics',
            'note' => 'Hivatalos útmutató a renderelt tartalom, JavaScript, structured data és cacheelés keresőoldali kockázatairól.',
        ],
        [
            'title' => 'Search Engine Journal: Google Drops FAQ Rich Results',
            'url' => 'https://www.searchenginejournal.com/google-drops-faq-rich-results-from-search/574429/',
            'note' => 'Szakmai hírelemzés a Google FAQ rich result kivezetéséről és annak gyakorlati SEO/AIO következményeiről.',
        ],
        [
            'title' => 'Algorythmic LLM Content Visibility Scanner',
            'url' => 'https://algorythmic.co/llm-content-visibility-scanner/',
            'note' => 'Publikus termékbenchmark: raw HTML láthatóság, JS-függő tartalom, metadata, JSON-LD és copy-ready akcióterv fókusz.',
        ],
        [
            'title' => 'OpenAI Responses API web_search',
            'url' => 'https://developers.openai.com/api/docs/guides/tools-web-search',
            'note' => 'Hivatalos OpenAI API útmutató a web_search tool használatához; erre épül az opcionális live AI keresési próba.',
        ],
        [
            'title' => 'OpenRouter Chat Completions API',
            'url' => 'https://openrouter.ai/docs/api/api-reference/chat/send-chat-completion-request',
            'note' => 'OpenAI-kompatibilis Chat Completions API több modellhez; az app ingyenes/olcsó második szakértői véleményként használja.',
        ],
        [
            'title' => 'Structural Feature Engineering for GEO',
            'url' => 'https://arxiv.org/abs/2603.29979',
            'note' => '2026-os arXiv tanulmány a makro-, mezo- és mikrostruktúra citációs viselkedésre gyakorolt szerepéről.',
        ],
        [
            'title' => 'Diagnosing and Repairing Citation Failures in GEO',
            'url' => 'https://arxiv.org/abs/2603.09296',
            'note' => '2026-os diagnosztikai megközelítés: nem általános újraírás, hanem citációs hibamódok szerinti célzott javítás.',
        ],
        [
            'title' => 'From Citation Selection to Citation Absorption',
            'url' => 'https://arxiv.org/abs/2604.25707',
            'note' => '2026. áprilisi GEO mérési keret: nem elég a citációk száma, külön mérni kell, hogy a forrás ténylegesen beépül-e a válaszba.',
        ],
        [
            'title' => 'How Generative AI Disrupts Search',
            'url' => 'https://arxiv.org/abs/2604.27790',
            'note' => 'SIGIR 2026 tanulmány Google Search, Gemini és AI Overviews összevetésével; erős platformkülönbségeket és instabilitást jelez.',
        ],
        [
            'title' => 'OpenAI GPT-5.5 model documentation',
            'url' => 'https://developers.openai.com/api/docs/models/gpt-5.5',
            'note' => 'A Responses API-n használható aktuális frontier modell; az app opcionális másodelemző és AI keresési próba rétege erre konfigurálható.',
        ],
    ];
}

function aio_research_review(): array
{
    return [
        'last_checked' => '2026-06-22',
        'summary' => 'A feltöltött AIO elemzési segéd jó stratégiai irányt ad, de a 2026-os irány már kettéválasztja az on-page AIO alkalmasságot és a tényleges AI keresési láthatóságot. A frissített rendszer ezért nemcsak crawlolható tartalmat, schema-t és citációs blokkokat mér, hanem platformonként futtatható vevői kérdéssort, brand/domain jelenlétet, forrásidézetet és versenytárs co-mentiont is.',
        'updates' => [
            [
                'status' => 'Új beépített módszertan',
                'topic' => 'Promptalapú AI visibility monitoring',
                'document_claim' => 'A korábbi app főleg azt mérte, hogy az oldal technikailag és tartalmilag alkalmas-e AI értelmezésre.',
                'current_view' => 'A modern AI visibility monitoring eszközök már tényleges AI promptokat futtatnak, és azt nézik, megjelenik-e a brand/domain, ki jelenik meg helyette, milyen forrásokat idéz az AI, és hogyan írja le a márkát.',
                'recommendation' => 'Külön AI Search Visibility Lab réteg kell: brand, kategória, vevői probléma, összehasonlítás, forráskeresés és query fan-out kérdések platformonkénti futtatással.',
                'source' => '#competitive-ai-visibility-benchmark',
            ],
            [
                'status' => 'Új live mérési lehetőség',
                'topic' => 'OpenAI web_search alapú AI keresési próba',
                'document_claim' => 'Az OpenAI elemzés korábban csak az audit JSON alapján rendezte a javaslatokat.',
                'current_view' => 'A Responses API web_search tool segítségével futtatható egy valódi webes AI keresési próba, amely mérheti a coverage rate-et, citation rate-et, versenytársakat és narrative risket.',
                'recommendation' => 'Az OpenAI tesztet válasszuk szét két rétegre: auditmagyarázat és live AI-search probe. Utóbbi legyen opcionális, mert költségesebb és platformonként eltérő eredményeket ad.',
                'source' => 'https://developers.openai.com/api/docs/guides/tools-web-search',
            ],
            [
                'status' => 'Frissebb módszertan',
                'topic' => 'FAQPage schema új szerepe',
                'document_claim' => 'A korábbi AIO checklisták gyakran FAQ schema jelenlétét erős pozitív jelként kezelték.',
                'current_view' => 'A Google dokumentációja szerint 2026. május 7-től FAQ rich results már nem jelennek meg Searchben, a Search Console és API támogatás is kivezetés alatt áll.',
                'recommendation' => 'A FAQPage ne legyen magas súlyú pontozási elem. Helyette kontextusfüggően mérjük a látható válaszokat: B2B-nél döntési szempontok, e-kereskedelemnél termék/szállítás/garancia, szakmai oldalon bizonyíték és forrás.',
                'source' => 'https://developers.google.com/search/docs/appearance/structured-data/faqpage',
            ],
            [
                'status' => 'Frissebb módszertan',
                'topic' => 'Raw HTML láthatóság AI crawlereknek',
                'document_claim' => 'A segédanyag főként tartalmi és schema auditpontokra épített.',
                'current_view' => 'A publikus LLM Content Visibility Scanner terméklogikája helyesen emeli ki, hogy több AI crawler nem renderel teljes JavaScriptet, ezért az első HTML válasz tartalma kritikus.',
                'recommendation' => 'A motor külön mérje a nyers HTML szószámát, text-to-HTML arányát, script-dominanciát, üres app rootokat és SPA framework jeleket.',
                'source' => 'https://algorythmic.co/llm-content-visibility-scanner/',
            ],
            [
                'status' => 'Frissebb módszertan',
                'topic' => 'Citáció helyett citáció + abszorpció mérése',
                'document_claim' => 'A segédanyag erősen a citációs jelenlét megszerzésére fókuszál.',
                'current_view' => 'A 2026. áprilisi Citation Selection to Citation Absorption keret szerint azt is mérni kell, hogy a citált oldal nyelve, bizonyítéka vagy szerkezete ténylegesen bekerül-e a generált válaszba.',
                'recommendation' => 'Az audit ne csak azt nézze, van-e idézhető blokk, hanem azt is, van-e definíció, numerikus adat, összehasonlítás, lépéslista és bizonyíték, amelyet a válasz át tud venni.',
                'source' => 'https://arxiv.org/abs/2604.25707',
            ],
            [
                'status' => 'Kontroverz / túl erős',
                'topic' => 'A klasszikus SEO “technikai higiéniává degradálódott”',
                'document_claim' => 'A dokumentum szerint a SEO stratégiai szerepe háttérbe szorul, a fókusz a GEO.',
                'current_view' => 'A Google hivatalos generatív keresési útmutatója szerint az AI funkciók továbbra is a Search indexre, rangsorolási és minőségi rendszerekre épülnek.',
                'recommendation' => 'A rendszer kezelje a SEO-t alaprétegként, ne mellékesként: crawl, index, minőség, snippet, strukturált adat és hasznos tartalom nélkül a GEO sem stabil.',
                'source' => 'https://developers.google.com/search/docs/fundamentals/ai-optimization-guide',
            ],
            [
                'status' => 'Kontroverz / nem hivatalos standard',
                'topic' => 'llms.txt mint 2026-os szabvány',
                'document_claim' => 'A segédanyag szabványként hivatkozik az llms.txt és llms-full.txt fájlokra.',
                'current_view' => 'Az llms.txt hasznos, alacsony költségű agentikus navigációs segéd lehet, de jelenleg nem hivatalos Google rangsorolási vagy AI Overviews követelmény.',
                'recommendation' => 'Generáljunk llms.txt fájlt, de a riportban “emergens segédjelként” jelöljük, ne kötelező rangsorolási faktorként.',
                'source' => 'https://developers.google.com/search/docs/appearance/ai-features',
            ],
            [
                'status' => 'Finomítás szükséges',
                'topic' => '40-70 szavas answer-first blokkok',
                'document_claim' => 'A segéd konkrét ideális blokkméretet javasol.',
                'current_view' => 'A GEO kutatások támogatják az extractable evidence, definíciók és strukturált tartalom fontosságát, de nincs univerzális, minden platformra érvényes optimális szószám.',
                'recommendation' => 'Tartsuk meg a 40-90 szavas blokkot audit-heurisztikának, de ne állítsuk garanciának. Többféle válaszformát: definíció, lista, táblázat, lépéssor, összehasonlítás.',
                'source' => 'https://arxiv.org/abs/2604.25707',
            ],
            [
                'status' => 'Frissebb módszertan',
                'topic' => 'Platformonként eltérő citációs viselkedés',
                'document_claim' => 'A dokumentum több platform-specifikus arányt és mechanizmust általánosít.',
                'current_view' => 'A 2026-os empirikus kutatások szerint Google Search, AI Overviews, Gemini, ChatGPT és Perplexity forrásválasztása jelentősen eltérhet; ugyanaz a query kis módosításra is más forrást hozhat.',
                'recommendation' => 'A riport külön jelölje, mely javaslat univerzális SEO/AIO alap, melyik OpenAI web_search eredmény, és melyik platform-specifikus hipotézis. Egy mérés nem elég: ugyanazt a query batch-et időben és platformonként ismételni kell.',
                'source' => 'https://arxiv.org/abs/2604.27790',
            ],
            [
                'status' => 'Frissebb módszertan',
                'topic' => 'Citation failure taxonomy',
                'document_claim' => 'A segéd auditfolyamata hotspotokat keres, de kevésbé bontja hibamódokra a citációs bukást.',
                'current_view' => 'A 2026. márciusi citation failure kutatás célzott hibamódok szerint javít: retrieval failure, source selection failure, evidence extraction failure, answer absorption failure.',
                'recommendation' => 'A jövőbeli auditban minden probléma kapjon hibamód címkét, hogy ne általános tartalomjavítás, hanem célzott repair legyen.',
                'source' => 'https://arxiv.org/abs/2603.09296',
            ],
        ],
        'actionable_changes' => [
            'Külön AI Search Visibility Lab kerüljön a riportba: query set, coverage rate, citation rate, versenytárs co-mention, narrative accuracy.',
            'Az OpenAI API másodelemzés kapjon web_search alapú opcionális live próbát, de a riport jelezze, hogy ez csak OpenAI webes keresés, nem teljes platformpanel.',
            'A llms.txt legyen opcionális generált kimenet, nem kötelező pontszámfeltétel.',
            'A “citációs alkalmasság” mellé kerüljön “válasz-abszorpciós alkalmasság”: definíciók, számok, összehasonlítások, folyamatlépések.',
            'A SEO alapréteg súlya maradjon erős, mert a generatív keresés jelentős része továbbra is indexelt webes tartalomra épül.',
            'A konkrét CTR és platformarányok legyenek forrásolt, időbélyegzett megfigyelések, ne örök érvényű szabályok.',
        ],
    ];
}

function aio_methodology_principles(): array
{
    return [
        [
            'name' => 'Célcsoport és user journey',
            'summary' => 'Mielőtt technológiát vagy backend fejlesztést javaslunk, tisztázni kell, kinek szól az oldal, milyen döntést támogat és melyik útvonalon vezeti tovább a látogatót.',
            'checks' => ['egyértelmű H1 és bevezető', 'fő célközönség', 'user journey belépési pont', 'kontakt útvonal', 'CTA-k következetessége', 'lead capture'],
        ],
        [
            'name' => 'Keresési alapok és indexelhetőség',
            'summary' => 'A generatív válaszok jellemzően indexelt, crawlolható és snippet-kompatibilis webes tartalmakra támaszkodnak.',
            'checks' => ['HTTP státusz', 'robots és bot hozzáférés', 'canonical', 'sitemap', 'belső linkek', 'nyers HTML-ben látható fő tartalom'],
        ],
        [
            'name' => 'Raw HTML láthatóság',
            'summary' => 'Az AI crawlerek egy része nem futtat JavaScriptet, ezért a fő üzenetnek, címsoroknak és belső linkeknek az első HTML válaszban is érthetőnek kell lenniük.',
            'checks' => ['szerveroldali tartalom', 'text-to-HTML arány', 'üres app root', 'SPA framework jelek', 'script-dominancia', 'viewport meta'],
        ],
        [
            'name' => 'Navigációs és portfóliólogika',
            'summary' => 'A menü nem lexikon: a látogatói célok szerint kell szervezni. A külső felületeket, webshopot vagy karrier oldalt vizuálisan is jelezni kell.',
            'checks' => ['menüpontok száma', 'kattintási szintek száma', 'külső linkek a navban', 'egységes menünyelv', 'kattintható kategóriaoldalak', 'lábléc kontakt'],
        ],
        [
            'name' => 'Entitás- és tudásgráf-koherencia',
            'summary' => 'A márka, szerző, szervezet, termék és cikk stabil azonosítókkal és egymásra mutató JSON-LD kapcsolatokkal legyen leírva.',
            'checks' => ['Organization/LocalBusiness', 'Person/author', 'Article/WebPage', '@id stabilitás', 'sameAs', 'breadcrumb'],
        ],
        [
            'name' => 'Citációs alkalmasság',
            'summary' => 'Az oldal tartalmazzon önállóan idézhető, rövid válaszblokkokat, bizonyítékokat, forrásokat és naprakész adatokat.',
            'checks' => ['answer-first blokkok', 'kérdés alapú H2/H3', 'források', 'dátumok', 'számok/adatok', 'egyedi állítások', 'kontextushoz illő Q&A tartalom'],
        ],
        [
            'name' => 'AI keresési láthatósági próba',
            'summary' => 'A valódi GEO mérés nem áll meg az oldalon: vevői kérdéseket futtat AI keresőkben, és méri, hogy a brand/domain megjelenik-e, idéződik-e, milyen kontextusban és kik mellett.',
            'checks' => ['brand query', 'kategória toplista', 'buyer-intent kérdés', 'összehasonlítás', 'forrásidézet', 'versenytárs co-mention', 'narratíva pontosság'],
        ],
        [
            'name' => 'Tartalmi megkülönböztethetőség',
            'summary' => 'A Google friss útmutatója szerint a nem-kommoditás, szakértői, saját tapasztalaton vagy adaton alapuló tartalom a legerősebb védőréteg.',
            'checks' => ['eredeti nézőpont', 'szakértői jel', 'esettanulmány', 'saját adat', 'nem generikus lista', 'E-E-A-T'],
        ],
        [
            'name' => 'Agentikus használhatóság',
            'summary' => 'A modern AI ágensek nemcsak HTML-t olvasnak: DOM-ot, képernyőképet, accessibility tree-t és űrlapfolyamatokat is értelmezhetnek.',
            'checks' => ['reszponzív UI', 'accessibility label', 'űrlapok érthetősége', 'termékadatok', 'strukturált CTA', 'vizuális bizonyíték'],
        ],
    ];
}

function aio_score_dimensions(): array
{
    return [
        'technical' => 'Technikai felfedezhetőség',
        'seo' => 'SEO alapminőség',
        'aio' => 'AI/GEO láthatóság és citációs alkalmasság',
        'visibility' => 'AI keresési próba és forrásként megjelenés',
        'content' => 'Tartalmi megkülönböztethetőség',
        'ux' => 'UX útvonal és konverziós logika',
        'entity' => 'Entitásbizalom',
        'agentic' => 'Agentikus és raw HTML használhatóság',
    ];
}

function aio_glossary(): array
{
    return [
        'entity_trust' => [
            'term' => 'Entitásbizalom',
            'category' => 'Bizalom',
            'short' => 'Annak mértéke, hogy a kereső és az AI rendszer mennyire tudja stabilan azonosítani a márkát, szerzőt, szervezetet és tartalmi állításokat.',
            'details' => 'Az entitásbizalom erősödik, ha a weboldal következetes névhasználatot, Organization/Person schema elemeket, stabil @id azonosítókat, sameAs kapcsolatokat, szerzői adatokat és külső hitelesítési jeleket használ.',
            'why' => 'Ha az AI nem érti, ki a forrás, nehezebben fogja megbízható hivatkozásként kezelni az oldalt.',
            'example' => 'A cég neve minden oldalon azonosan szerepel, van szervezeti schema, logó, kapcsolat, social profil és szakértői szerzői jel.',
            'fix' => 'Tedd egységessé a márkanevet, adj Organization vagy LocalBusiness schema jelölést, és kapjanak stabil @id azonosítót a fő entitások.',
            'links' => [
                ['label' => 'Google strukturált adat irányelvek', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/sd-policies'],
                ['label' => 'Schema.org Organization', 'url' => 'https://schema.org/Organization'],
            ],
        ],
        'stable_id' => [
            'term' => '@id azonosító',
            'category' => 'Strukturált adat',
            'short' => 'A JSON-LD strukturált adatok tartós, URL-szerű entitásazonosítója.',
            'details' => 'Például https://domain.hu/#organization vagy https://domain.hu/cikk/#article. Segít abban, hogy az AI és a kereső ne különálló szövegrészletekként, hanem összekapcsolt entitásként értse a szervezetet, szerzőt, cikket és weboldalt.',
            'why' => 'A stabil @id olyan, mint egy belső névjegykártya: megmondja, hogy több schema blokk ugyanarra a cégre, cikkre vagy szerzőre mutat.',
            'example' => 'A WebPage schema a publisher mezőben a https://pelda.hu/#organization azonosítóra mutat, az Organization schema pedig ugyanilyen @id-val szerepel.',
            'fix' => 'Hozz létre következetes @id mintákat: #organization, #website, /oldal/#webpage, /cikk/#article, és ezeket kapcsold össze.',
            'links' => [
                ['label' => 'Schema.org JSON-LD', 'url' => 'https://schema.org/docs/jsonldcontext.json'],
                ['label' => 'Google structured data intro', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data'],
            ],
        ],
        'sameas' => [
            'term' => 'sameAs',
            'category' => 'Entitáskapcsolat',
            'short' => 'Schema.org mező, amely külső profilokkal kapcsolja össze ugyanazt az entitást.',
            'details' => 'Hasznos lehet közösségi profilok, szakmai adatbázisok, Wikidata, Crunchbase vagy iparági névjegyzékek megadására. Nem mennyiség, hanem relevancia és hitelesség számít.',
            'why' => 'A sameAs segít megerősíteni, hogy a weboldalon szereplő márka ugyanaz, mint a külső profilokon látható entitás.',
            'example' => 'Organization schema sameAs mezőben LinkedIn, Instagram, YouTube, szakmai adatbázis vagy cégprofil URL-ek szerepelnek.',
            'fix' => 'Csak valódi, karbantartott, a márkához tartozó profilokat adj meg. Kerüld a véletlenszerű vagy gyenge minőségű linkhalmozást.',
            'links' => [
                ['label' => 'Schema.org sameAs', 'url' => 'https://schema.org/sameAs'],
            ],
        ],
        'citation_suitability' => [
            'term' => 'Citációs alkalmasság',
            'category' => 'Idézhetőség',
            'short' => 'A tartalom mennyire alkalmas arra, hogy egy AI válasz konkrét forrásként idézze.',
            'details' => 'Erősíti a direkt, 40-90 szavas válaszblokk, a világos kérdés-alapú címsor, a friss dátum, a bizonyíték, a forrás, az eredeti adat és az önállóan is érthető állítás.',
            'why' => 'Az AI válaszok gyakran rövid, önállóan is érthető szövegrészeket keresnek. Ha minden fontos információ szétszórva van, nehezebb idézni.',
            'example' => 'H2: “Mennyibe kerül egy céges weboldal?” Utána 60 szavas direkt válasz, majd árképzési tényezők és példák.',
            'fix' => 'A fontos szakaszok elejére tegyél rövid választ, utána részletezést, adatot, példát és forráslinket.',
            'links' => [
                ['label' => 'GEO kutatás', 'url' => 'https://arxiv.org/abs/2311.09735'],
                ['label' => 'Citation absorption kutatás', 'url' => 'https://arxiv.org/abs/2604.25707'],
            ],
        ],
        'ai_visibility_probe' => [
            'term' => 'AI keresési láthatósági próba',
            'category' => 'Mérés',
            'short' => 'Vevői kérdések futtatása AI keresőkben annak mérésére, hogy előkerül-e a brand vagy domain.',
            'details' => 'Ez nem klasszikus SEO crawl. A módszer kérdéscsomagot futtat például OpenAI web_search, ChatGPT Search, Gemini, Perplexity vagy Google AI Mode felületen, majd méri a brand említést, domain idézést, pozíciót, versenytársakat és a válasz pontosságát.',
            'why' => 'A jó technikai AIO alap még nem bizonyítja, hogy az AI tényleg ajánlja vagy idézi az oldalt. A tényleges láthatóságot platformonként és időben ismételve kell mérni.',
            'example' => 'Query: “Mely B2B rendezvényügynökségeket ajánlanád Magyarországon?” Mérjük, hogy szerepel-e a márka, milyen indokkal, és milyen URL-eket idéz az AI.',
            'fix' => 'Építs query batch-et brand, kategória, buyer-intent, összehasonlító és forráskereső kérdésekkel. Futtasd rendszeresen, majd javítsd azokat a tartalmi és külső bizonyítékhiányokat, amelyek miatt a domain kimarad.',
            'links' => [
                ['label' => 'OpenAI web_search tool', 'url' => 'https://developers.openai.com/api/docs/guides/tools-web-search'],
            ],
        ],
        'coverage_rate' => [
            'term' => 'Coverage rate',
            'category' => 'AI visibility metrika',
            'short' => 'A mérési kérdések hány százalékában jelenik meg a márka vagy domain.',
            'details' => 'Ha 10 buyer-intent kérdésből 2 válasz említi a brandet, a coverage rate 20%. Ez még nem jelenti, hogy forrásként is idézték, ezért külön kell mérni a citation rate-et.',
            'why' => 'Megmutatja, hogy a márka egyáltalán része-e az AI választerének az adott témában.',
            'example' => 'A “legjobb CRM bevezetési tanácsadók” és “B2B sales automatizáció cégek” kérdésekben megjelenik, de a “HubSpot partner Magyarország” kérdésben nem.',
            'fix' => 'Erősítsd azokat a témaklasztereket, ahol a brand kimarad: szolgáltatásoldal, esettanulmány, összehasonlító oldal, külső szakmai említés.',
            'links' => [
                ['label' => 'GEO alapkutatás', 'url' => 'https://arxiv.org/abs/2311.09735'],
            ],
        ],
        'citation_rate' => [
            'term' => 'Citation rate',
            'category' => 'AI visibility metrika',
            'short' => 'A mérési válaszok hány százalékában hivatkozik az AI ténylegesen a vizsgált domainre.',
            'details' => 'Erősebb jel, mint a sima brand mention. A válaszban szereplő forráslink vagy idézett URL azt mutatja, hogy az oldal a forrásválasztási folyamatba is bekerült.',
            'why' => 'AI keresésben üzletileg az számít igazán, hogy a márka nemcsak szóba kerül, hanem bizonyítékként, ajánlott forrásként is megjelenik.',
            'example' => 'A válasz felsorolja a brandet, de csak versenytárs cikkeket idéz. Coverage van, citation nincs.',
            'fix' => 'Adj idézhető answer-first blokkokat, adatot, forrást, schema kapcsolatot és olyan publikus oldalakat, amelyek egyértelműen alátámasztják a kategóriaszakértelmet.',
            'links' => [
                ['label' => 'Citation absorption kutatás', 'url' => 'https://arxiv.org/abs/2604.25707'],
            ],
        ],
        'competitor_co_mention' => [
            'term' => 'Versenytárs co-mention',
            'category' => 'AI visibility metrika',
            'short' => 'Azok a márkák, amelyek ugyanarra az AI kérdésre a vizsgált brand mellett vagy helyette megjelennek.',
            'details' => 'A co-mention lista megmutatja, kikkel sorolja egy kategóriába az AI a márkát. Ha a saját brand kimarad, de mindig ugyanaz a 3-5 versenytárs jelenik meg, az tartalmi és PR/authority gapet jelez.',
            'why' => 'A tényleges AI keresési piac nem csak kulcsszóhelyezésből áll, hanem abból, hogy kik kerülnek be a válasz shortlistjébe.',
            'example' => 'A kérdésre az AI három versenytársat ajánl, a vizsgált márkát nem. Ez coverage failure és source selection gap.',
            'fix' => 'Készíts összehasonlító, kategória- és esettanulmány-tartalmakat, valamint szerezz releváns külső említéseket ugyanazon témakörben.',
            'links' => [
                ['label' => 'GEO alapkutatás', 'url' => 'https://arxiv.org/abs/2311.09735'],
            ],
        ],
        'narrative_accuracy' => [
            'term' => 'Narratíva pontosság',
            'category' => 'AI visibility metrika',
            'short' => 'Az AI válasza pontosan írja-e le a márkát, szolgáltatást, célcsoportot és bizonyítékokat.',
            'details' => 'Nem elég bekerülni a válaszba. Ha az AI rossz szolgáltatást, elavult adatot vagy félrevezető pozicionálást ír, az üzletileg kockázatos. Ezért kell mérni a válasz minőségét is.',
            'why' => 'A hibás AI narratíva rossz leadeket, bizalomvesztést vagy jogi/kockázati problémát okozhat.',
            'example' => 'A brandet webshopként írja le, miközben B2B tanácsadó cég. Ez jelzi, hogy a webes entitásjelek és külső említések félreérthetők.',
            'fix' => 'Tisztázd a H1, meta, About, Organization schema, sameAs és esettanulmány nyelvezetét. Ugyanaz a pozicionálás jelenjen meg minden fontos oldalon és külső profilon.',
            'links' => [
                ['label' => 'Google structured data policies', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/sd-policies'],
            ],
        ],
        'answer_first' => [
            'term' => 'Answer-first blokk',
            'category' => 'Tartalomszerkezet',
            'short' => 'Kérdés vagy probléma után azonnal következő rövid, tényszerű válasz.',
            'details' => 'A cél, hogy az AI ne hosszú bekezdésekből próbáljon összeollózni választ, hanem egy tiszta, idézhető, ellenőrizhető összefoglalót találjon.',
            'why' => 'A felhasználó és az AI is gyorsabban megérti, mi a válasz, mielőtt a részletekbe menne.',
            'example' => '“A technikai SEO audit célja, hogy feltárja az indexelési, sebességi és strukturált adat problémákat. Egy jó audit konkrét javítási sorrendet is ad.”',
            'fix' => 'Minden fontos H2 után írj 2-4 mondatos direkt választ, majd bontsd ki listával, táblázattal vagy példával.',
            'links' => [
                ['label' => 'Google hasznos tartalom', 'url' => 'https://developers.google.com/search/docs/fundamentals/creating-helpful-content'],
            ],
        ],
        'contextual_qa' => [
            'term' => 'Kontextushoz illő Q&A',
            'category' => 'Tartalomstratégia',
            'short' => 'Olyan kérdés-válasz tartalom, amely az adott oldal valódi döntési helyzetéhez igazodik.',
            'details' => 'Nem minden oldalnak ugyanazokra a kérdésekre kell válaszolnia. Egy B2B szolgáltató oldalán a döntéshozó bizonyítékot, folyamatot, referenciát és kockázatcsökkentést keres; egy webshop termékoldalán a méret, szállítás, garancia és kompatibilitás lehet fontos.',
            'why' => 'Az AI keresés nem csak schema mezőket olvas, hanem azt is, hogy a tartalom tényleg megválaszolja-e a felhasználó következő kérdését.',
            'example' => 'B2B: “Milyen iparágakban van referenciánk?” E-kereskedelem: “Milyen méretet válasszak?” Szakmai cikk: “Milyen forrás bizonyítja az állítást?”',
            'fix' => 'Írj 4-8 olyan kérdést, amit a célközönség a döntés előtt tényleg feltenne, és adj rájuk rövid, konkrét, bizonyítékkal megtámogatott választ.',
            'links' => [
                ['label' => 'Google FAQPage dokumentáció', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/faqpage'],
                ['label' => 'Google hasznos tartalom', 'url' => 'https://developers.google.com/search/docs/fundamentals/creating-helpful-content'],
            ],
        ],
        'query_fanout' => [
            'term' => 'Query fan-out',
            'category' => 'AI keresés',
            'short' => 'Összetett kérdés több kapcsolódó alkérdésre bontása a generatív keresésben.',
            'details' => 'Ezért fontos a témaklaszterek, belső linkek és részletes aloldalak megléte: az AI nem csak a fő kulcsszóra, hanem kapcsolódó szándékokra is bizonyítékot kereshet.',
            'why' => 'Egy AI keresés nem mindig egyetlen kulcsszóként működik. A rendszer több részszándékhoz kereshet forrást.',
            'example' => 'A “legjobb rendezvényügynökség B2B eseményre” kérdés széteshet referenciákra, szolgáltatásokra, iparági tapasztalatra, árképzésre és esettanulmányokra.',
            'fix' => 'Építs témaklasztert: fő szolgáltatásoldal, részszolgáltatás aloldalak, esettanulmányok, GYIK és belső linkek.',
            'links' => [
                ['label' => 'Google AI features', 'url' => 'https://developers.google.com/search/docs/appearance/ai-features'],
            ],
        ],
        'user_journey' => [
            'term' => 'User journey',
            'category' => 'UX stratégia',
            'short' => 'Az útvonal, amin egy célcsoport a weboldalon halad: mit keres, mit ért meg, hova kattint tovább.',
            'details' => 'A leirat egyik legerősebb tanulsága, hogy nem backenddel kell kezdeni. Először ki kell mondani, kiknek szól az oldal, milyen döntést kell támogatni, és melyik tartalom vagy CTA viszi tovább őket.',
            'why' => 'Ha a célközönség és útvonal nincs tisztázva, a portfólió, karrier, webshop, kapcsolat és tartalomlogika egymás ellen kezd dolgozni.',
            'example' => 'Egy B2B látogató először képességeket és referenciát keres; egy álláskereső csapatot és karrierutat; egy partner kapcsolatfelvételt és bizalmi jeleket.',
            'fix' => 'Rajzolj 2-4 fő látogatói útvonalat, és minden útvonalhoz rendelj fő menüpontot, landing oldalt, CTA-t és mérési pontot.',
            'links' => [
                ['label' => 'Nielsen Norman Group: Journey Mapping', 'url' => 'https://www.nngroup.com/articles/journey-mapping-101/'],
            ],
        ],
        'navigation_hierarchy' => [
            'term' => 'Navigációs hierarchia',
            'category' => 'UX stratégia',
            'short' => 'A menü és belső linkek logikája: mennyi döntési pontot kap a látogató, és mennyire kiszámítható a kattintás.',
            'details' => 'Ha a fontos oldalak csak sok kattintás, többszintű lenyíló vagy nem kattintható köztes kategóriák után érhetők el, a böngészés megszakad. AI agent szempontból ez a webhelystruktúra értelmezését is rontja.',
            'why' => 'A menü nem csak dizájnelem, hanem döntési térkép. Ha túl bonyolult, a felhasználó és az AI is nehezebben érti, mi fontos.',
            'example' => 'Rossz: háromszintű lenyíló, ahol csak a legutolsó elem kattintható. Jobb: fő kategória landing oldal, azon belül aloldalak és rövid magyarázó linkek.',
            'fix' => 'Tartsd a fő menüt 5-7 döntési pont körül, a külső felületeket jelöld, a köztes kategóriákat pedig tedd valódi landing oldallá.',
            'links' => [
                ['label' => 'NN/g: Navigation', 'url' => 'https://www.nngroup.com/topic/navigation/'],
            ],
        ],
        'cta_consistency' => [
            'term' => 'CTA-következetesség',
            'category' => 'Konverzió',
            'short' => 'A gombok és akciólinkek egységesen jelzik, milyen következő lépés történik.',
            'details' => 'Ha ugyanaz a kapcsolatfelvétel hol popup, hol külön oldal, hol más kinézetű gomb, a látogató kevésbé bízik a folyamatban. A következetes CTA az AI agenteknek is stabilabb akcióértelmezést ad.',
            'why' => 'A CTA a döntési út vége. Ha nem világos, mi fog történni, romlik a konverzió és a felhasználói kontrollérzet.',
            'example' => 'Elsődleges gomb: “Ajánlatot kérek”. Másodlagos link: “Tovább olvasok”. Külső webshop: “Shop megnyitása külső oldalon”.',
            'fix' => 'Készíts CTA-rendszert: elsődleges, másodlagos, külső, letöltés, hírlevél. Ugyanaz a cél mindig ugyanúgy nézzen ki és ugyanúgy viselkedjen.',
            'links' => [
                ['label' => 'NN/g: Call to Action Buttons', 'url' => 'https://www.nngroup.com/articles/command-links/'],
            ],
        ],
        'raw_html_visibility' => [
            'term' => 'Raw HTML láthatóság',
            'category' => 'Feltérképezhetőség',
            'short' => 'Azt mutatja, mennyi fontos tartalom látszik már abban a HTML-ben, amit a szerver JavaScript futtatás nélkül visszaad.',
            'details' => 'Sok AI crawler és tartalomfeldolgozó nem rendereli teljesen a React/Vue/Angular alkalmazást. Ha az első HTML válasz csak üres app konténert és scripteket tartalmaz, az oldal embernek szép lehet, de a crawlernek kevés információt ad.',
            'why' => 'Az AI keresési láthatóság nem csak schema kérdés. A fő állításoknak, címsoroknak, szolgáltatásoknak és belső linkeknek gépileg is olvashatóan jelen kell lenniük.',
            'example' => 'Jó: a HTML-ben már ott van a H1, szolgáltatásleírás, kérdés-válasz blokk és belső linkek. Kockázatos: <div id="root"></div> és minden tartalom csak JavaScript után töltődik be.',
            'fix' => 'Használj SSR-t, SSG-t, statikus HTML-t vagy szerveroldalon előállított fő tartalmat. A dekoráció és interakció maradhat JavaScriptben, de az üzleti lényeg ne csak ott legyen.',
            'links' => [
                ['label' => 'Google JavaScript SEO', 'url' => 'https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics'],
                ['label' => 'LLM visibility scanner példa', 'url' => 'https://algorythmic.co/llm-content-visibility-scanner/'],
            ],
        ],
        'client_side_rendering' => [
            'term' => 'Client-side rendering kockázat',
            'category' => 'Technikai AIO',
            'short' => 'Amikor a lényegi tartalom csak a böngészőben, JavaScript futása után jelenik meg.',
            'details' => 'A teljesen kliensoldali renderelésnél a szerver gyakran minimális HTML-t küld, például egy üres root elemet. Ez SEO-ban is körültekintést igényel, AI crawlereknél pedig még nagyobb kockázat, mert nem minden rendszer renderel.',
            'why' => 'Ha az AI crawler nem látja a fő tartalmat, nem tudja kivonatolni, idézni vagy entitásként értelmezni.',
            'example' => 'Egy Next.js oldal SSG/SSR módban jó lehet, mert a tartalom a HTML-ben van. Egy üres React SPA API-ból betöltött szövegekkel kockázatosabb.',
            'fix' => 'A kritikus oldalaknál ellenőrizd a “view-source” vagy curl HTML-t. Ha ott nincs érdemi szöveg, adj SSR/SSG renderelést vagy statikus fallback tartalmat.',
            'links' => [
                ['label' => 'Google JavaScript SEO basics', 'url' => 'https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics'],
            ],
        ],
        'faq_rich_result_deprecation' => [
            'term' => 'FAQ rich result kivezetés',
            'category' => 'Strukturált adat',
            'short' => '2026. május 7-től a FAQ rich results már nem jelennek meg Google Searchben.',
            'details' => 'A FAQPage schema továbbra is valid schema.org típus lehet, de Google-ben nem érdemes látható FAQ rich result előnyként kezelni. A valódi kérdés-válasz tartalom ettől még hasznos marad, ha az oldal üzleti helyzetéhez illik.',
            'why' => 'A korábbi SEO checklisták túlértékelték a FAQ schema-t. Most fontosabb, hogy a döntést segítő válasz látható, pontos és idézhető legyen.',
            'example' => 'B2B szolgáltatónál nem cél általános webshop-jellegű GYIK-et erőltetni. Jó cél: “kinek való a szolgáltatás?”, “mennyi ideig tart a folyamat?”, “milyen bizonyíték támasztja alá?” típusú válaszblokkok.',
            'fix' => 'Ne töröld automatikusan a FAQ schema-t, de ne is adj neki nagy pontszámot. Tartsd meg, ha valós és látható FAQ tartalmat ír le; különben építs kontextushoz illő, látható Q&A vagy döntéstámogató blokkokat.',
            'links' => [
                ['label' => 'Google FAQPage dokumentáció', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/faqpage'],
                ['label' => 'Search Engine Journal összefoglaló', 'url' => 'https://www.searchenginejournal.com/google-drops-faq-rich-results-from-search/574429/'],
            ],
        ],
        'llms_txt' => [
            'term' => 'llms.txt',
            'category' => 'Agentikus navigáció',
            'short' => 'Emergens, Markdown-alapú navigációs térkép AI eszközöknek.',
            'details' => 'Nem hivatalos Google rangsorolási követelmény és nem robots.txt helyettesítő. Hasznos lehet fejlesztői/agentikus eszközöknek, ha röviden felsorolja a fontos erőforrásokat és dokumentációkat.',
            'why' => 'Segíthet az AI eszközöknek gyorsan megérteni, mely oldalak fontosak, de nem helyettesíti a SEO technikai alapokat.',
            'example' => 'A gyökérben lévő llms.txt felsorolja a fő szolgáltatásokat, dokumentációkat, árakat, esettanulmányokat és kapcsolat oldalt.',
            'fix' => 'Generálj rövid, tiszta llms.txt fájlt a legfontosabb oldalakkal. Ne tegyél bele auditlistát vagy belső megjegyzést.',
            'links' => [
                ['label' => 'llms.txt kezdeményezés', 'url' => 'https://llmstxt.org/'],
            ],
        ],
        'agentic_usability' => [
            'term' => 'Agentikus használhatóság',
            'category' => 'Használhatóság',
            'short' => 'A weboldal mennyire érthető és használható AI ágensek számára.',
            'details' => 'Ide tartozik az olvasható HTML, accessibility label, stabil űrlaplogika, gyors betöltés, strukturált CTA, termékadatok és a képek/táblázatok értelmezhető leírása.',
            'why' => 'Az AI ágensek nemcsak olvasnak, hanem űrlapokat, gombokat és folyamatokat is értelmezhetnek. A zavaros UI itt is rontja a használhatóságot.',
            'example' => 'A kapcsolatfelvételi űrlap mezői egyértelmű labelt kapnak, a CTA gomb konkrét, a hibák érthetőek, a szolgáltatásadatok nem csak képen vannak.',
            'fix' => 'Adj labelt az űrlapmezőknek, használj beszédes CTA-kat, optimalizáld a mobilnézetet, és tedd szövegesen is elérhetővé a fontos adatokat.',
            'links' => [
                ['label' => 'WCAG alapok', 'url' => 'https://www.w3.org/WAI/fundamentals/accessibility-intro/'],
            ],
        ],
        'evidence_language' => [
            'term' => 'Bizonyítéknyelv',
            'category' => 'Bizonyíték',
            'short' => 'Olyan szöveges jelek, amelyek alátámasztott állításokra utalnak.',
            'details' => 'Példák: kutatás, mérés, forrás, dátum, minta, esettanulmány, százalék, összehasonlítás. Az AI rendszerek bizalmi szűrőinél ezek segíthetik a forrásértéket.',
            'why' => 'A “mi vagyunk a legjobbak” típusú állítás kevés. A konkrét adat, példa és forrás sokkal jobban idézhető.',
            'example' => '“A kampány 6 hét alatt 31%-kal növelte a kvalifikált leadek számát, 2025. Q4-es CRM adatok alapján.”',
            'fix' => 'A fontos állítások mellé adj számot, dátumot, módszert, forrást, ügyfélpéldát vagy esettanulmányt.',
            'links' => [
                ['label' => 'Google E-E-A-T és minőség', 'url' => 'https://developers.google.com/search/docs/fundamentals/creating-helpful-content'],
            ],
        ],
        'organization_schema' => [
            'term' => 'Organization/LocalBusiness schema',
            'category' => 'Strukturált adat',
            'short' => 'Strukturált adat, amely leírja a márkát, céget vagy helyi vállalkozást.',
            'details' => 'Tartalmazhat nevet, URL-t, logót, kapcsolati adatot, alapítást, közösségi profilokat és azonosító @id-t. A cél az entitás konzisztens azonosítása.',
            'why' => 'Ez az egyik legalapvetőbb jel arra, hogy az oldal mögött milyen szervezet áll.',
            'example' => 'Organization schema: name, url, logo, contactPoint, sameAs, @id. Helyi cég esetén LocalBusiness adatok: cím, nyitvatartás, telefon.',
            'fix' => 'Tegyél JSON-LD Organization vagy LocalBusiness blokkot a főoldalra, és kapcsold a WebSite/WebPage schema elemekhez.',
            'links' => [
                ['label' => 'Schema.org LocalBusiness', 'url' => 'https://schema.org/LocalBusiness'],
                ['label' => 'Google structured data', 'url' => 'https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data'],
            ],
        ],
    ];
}
