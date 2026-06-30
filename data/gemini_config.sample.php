<?php
/**
 * Másold ezt a fájlt `gemini_config.php` néven, és töltsd ki az api_key mezőt.
 * A /data könyvtár .htaccess védelem alatt áll, ezért shared hostingon ez a
 * legegyszerűbb biztonságos konfigurációs mód.
 */
return [
    'enabled' => true,
    'api_key' => '',
    'model' => 'gemini-flash-latest',
    'timeout' => 150,
    'connect_timeout' => 20,
    'max_output_tokens' => 6000,
    'grounded_timeout' => 150,
    'grounded_max_output_tokens' => 1400,
    'ca_bundle' => __DIR__ . '/cacert.pem',
];
