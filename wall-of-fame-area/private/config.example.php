<?php
declare(strict_types=1);

// Keep this configuration OUTSIDE the public webroot.
// Copy to private/config.php and fill secrets locally (never commit config.php).
return [
    'db_host' => 'localhost',
    'db_name' => 'cpaneluser_seotidy',
    'db_user' => 'cpaneluser_seotidy',
    'db_pass' => 'REPLACE_WITH_DB_PASSWORD',
    'public_origin' => 'https://kas.id.lv/SEO-TidY/Wall-Of-Fame',
    'turnstile_site_key' => 'REPLACE_WITH_PUBLIC_KEY',
    'turnstile_secret' => 'REPLACE_WITH_SECRET',
    'admin_email' => 'owner@example.org',
    'admin_token' => 'REPLACE_WITH_AT_LEAST_32_RANDOM_CHARACTERS',
];
