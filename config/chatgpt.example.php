<?php
declare(strict_types=1);
// Example only. Place the real file OUTSIDE public_html and outside Git.
// Default production location: domain directory /yugioh-chatgpt-config.php.
return [
    'resource'=>'https://stationxtulua.com/yugioh/mcp/',
    'owner_password_hash'=>'REPLACE_WITH_PASSWORD_HASH_GENERATED_BY_prepare_chatgpt.py',
    'state_dir'=>__DIR__.'/yugioh-chatgpt-state',
];
