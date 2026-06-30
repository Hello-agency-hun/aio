<?php
/**
 * Mentett keresési provider konfiguráció minta.
 *
 * Másold `data/search_config.php` néven, majd töltsd ki azt a providert,
 * amelyet használni szeretnél. A fájl a /data könyvtárban van, amelyet
 * .htaccess védelem zár el a publikus webtől.
 */

return [
    'enabled' => true,

    // Sorrend: az első elérhető provider fut, hiba esetén a következő próbálkozik.
    'provider_order' => ['searxng', 'langsearch', 'jina'],

    // Ingyenes, saját kézben tartható megoldás: self-hosted SearXNG JSON endpoint.
    // Példa: 'https://kereso.sajatdomain.hu'
    'searxng_base_url' => '',

    // LangSearch Web Search API kulcs. Van ingyenes csomag, de API kulcs kell.
    'langsearch_api_key' => '',

    // Jina Search API kulcs. A s.jina.ai endpoint jelenleg Authorization fejlécet kérhet.
    'jina_api_key' => '',

    'max_results' => 5,
    'cache_ttl_hours' => 24,
    'timeout' => 18,
    'connect_timeout' => 6,
    'language' => 'hu-HU',
    'ca_bundle' => __DIR__ . '/cacert.pem',

    'query_limits' => [
        'quick' => 3,
        'smart' => 4,
        'deep' => 6,
        'custom' => 4,
    ],
];
