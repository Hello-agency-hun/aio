<?php
/**
 * OpenRouter konfiguráció minta shared hostinghoz.
 *
 * Másold ezt a fájlt `data/openrouter_config.php` néven, majd add meg a saját
 * OpenRouter kulcsodat. Ingyenes modell esetén is jobb szerveroldalon tartani,
 * hogy a kulcs ne jelenjen meg a böngészőben.
 */

return [
    'enabled' => true,
    'api_key' => '',
    'model' => 'openrouter/free',
    'timeout' => 120,
    'connect_timeout' => 20,
    'max_tokens' => 6000,
    'ca_bundle' => DATA_DIR . '/cacert.pem',
    'enable_online_probe' => false,
    'online_model' => 'openrouter/free',
    'online_max_results' => 3,
    'online_max_tokens' => 4200,
];
