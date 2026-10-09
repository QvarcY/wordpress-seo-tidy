<?php

defined('ABSPATH') || exit;

function seo_tidy_updater_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

$settings = get_registered_settings();
$name = 'seo_tidy_beta_updates';
$setting = $settings[$name] ?? null;

seo_tidy_updater_assert(
    is_array($setting),
    'Beta update setting registered'
);

seo_tidy_updater_assert(
    ($setting['type'] ?? null) === 'boolean',
    'Beta update setting uses boolean type'
);

seo_tidy_updater_assert(
    ($setting['default'] ?? null) === false,
    'Beta updates disabled by default'
);

seo_tidy_updater_assert(
    !empty($setting['show_in_rest']),
    'Beta update setting exposed through REST'
);

seo_tidy_updater_assert(
    is_callable($setting['sanitize_callback'] ?? null),
    'Beta setting has a sanitizer'
);

$administrator = get_users([
    'role' => 'administrator',
    'number' => 1,
    'fields' => 'all',
]);

seo_tidy_updater_assert(
    !empty($administrator),
    'Administrator exists'
);

wp_set_current_user($administrator[0]->ID);

$request = new WP_REST_Request('GET', '/wp/v2/settings');
$response = rest_do_request($request);

seo_tidy_updater_assert(
    !$response->is_error() &&
        $response->get_status() === 200,
    'Administrator can read WordPress settings'
);

$data = $response->get_data();

seo_tidy_updater_assert(
    array_key_exists($name, $data) &&
        is_bool($data[$name]),
    'REST exposes beta setting as boolean'
);

echo 'UPDATER SETTINGS INTEGRATION TEST COMPLETE' . PHP_EOL;
