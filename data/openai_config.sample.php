<?php
/**
 * OpenAI konfiguráció minta shared hostinghoz.
 *
 * Másold ezt a fájlt `data/openai_config.php` néven, majd ott add meg a saját
 * API kulcsodat. A `data` könyvtárat .htaccess védi, ezért a kulcs nem kerül
 * közvetlenül publikus PHP fájlba.
 */

return [
    'api_key' => '',
    'model' => 'gpt-5.5',
    'timeout' => 180,
    'connect_timeout' => 20,
    'max_output_tokens' => 6500,
    'enable_visibility_probe' => true,
    'ca_bundle' => DATA_DIR . '/cacert.pem',
];
