<?php

use QvarcY\SeoTidy\Analytics;
use QvarcY\SeoTidy\StatsBadge;

if (!defined('ABSPATH')) {
    exit(1);
}

function m13_assert($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

global $wpdb;

$options = [
    'seo_tidy_analytics_enabled',
    'seo_tidy_badge_footer',
    'seo_tidy_badge_branding',
    'seo_tidy_badge_bots',
    'seo_tidy_badge_humans',
    'seo_tidy_badge_labels',
    'seo_tidy_badge_theme',
];

$original = [];
foreach ($options as $name) {
    $original[$name] = get_option($name, null);
}

$originalServer = [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
    'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$originalUser = get_current_user_id();
$table = Analytics::tableName();
$date = wp_date('Y-m-d');
$originalRow = null;
$rowExisted = false;
$testStarted = false;

try {
    update_option('seo_tidy_analytics_enabled', false);

    m13_assert(
        Analytics::enabled() === false,
        'Analytics can be disabled'
    );

    m13_assert(
        Analytics::recordRequest() === null,
        'Disabled analytics does not execute collection'
    );

    Analytics::install();

    $exists = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($table)
        )
    );

    m13_assert($exists === $table, 'Daily statistics table exists');

    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");

    foreach ([
        'stat_date',
        'human_pageviews',
        'suspected_bot_requests',
        'unique_estimate',
    ] as $column) {
        m13_assert(
            in_array($column, $columns, true),
            'Database column: ' . $column
        );
    }

    $originalRow = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE stat_date = %s",
            $date
        ),
        ARRAY_A
    );

    $rowExisted = is_array($originalRow);
    $testStarted = true;

    update_option('seo_tidy_analytics_enabled', true);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_USER_AGENT'] =
        'Mozilla/5.0 SEO-TidY-M13-Integration-Test';

    m13_assert(
        Analytics::shouldCount(),
        'Eligible public request passes filtering'
    );

    $before = Analytics::summary(1);

    Analytics::recordRequest();

    $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
    Analytics::recordRequest();

    $after = Analytics::summary(1);

    m13_assert(
        $after['humanPageviews'] ===
            $before['humanPageviews'] + 1,
        'Human-classified pageview increases by one'
    );

    m13_assert(
        $after['suspectedBotRequests'] ===
            $before['suspectedBotRequests'] + 1,
        'Suspected bot request increases by one'
    );

    $_SERVER['REQUEST_METHOD'] = 'POST';

    m13_assert(
        Analytics::shouldCount() === false,
        'POST requests excluded'
    );

    $_SERVER['REQUEST_METHOD'] = 'GET';

    m13_assert(
        Analytics::suspectedBot('Googlebot/2.1'),
        'Known crawler identified'
    );

    m13_assert(
        !Analytics::suspectedBot('Mozilla/5.0 Firefox/128.0'),
        'Regular browser not automatically classified as bot'
    );

    wp_set_current_user(0);

    $publicRequest = new WP_REST_Request(
        'GET',
        '/seo-tidy/v1/analytics'
    );

    $denied = rest_do_request($publicRequest);

    m13_assert(
        $denied->get_status() === 401 ||
            $denied->get_status() === 403,
        'Unauthenticated REST access denied'
    );

    $adminUsers = get_users([
        'role' => 'administrator',
        'number' => 1,
        'fields' => 'ID',
    ]);

    m13_assert(
        !empty($adminUsers),
        'Test administrator exists'
    );

    wp_set_current_user((int) $adminUsers[0]);

    $allowed = rest_do_request($publicRequest);

    m13_assert(
        $allowed->get_status() === 200,
        'Administrator REST access allowed'
    );

    $payload = $allowed->get_data();

    m13_assert(
        isset(
            $payload['humanPageviews'],
            $payload['suspectedBotRequests'],
            $payload['periodDays']
        ),
        'Analytics REST payload includes expected metrics'
    );

    update_option('seo_tidy_badge_humans', true);
    update_option('seo_tidy_badge_bots', true);
    update_option('seo_tidy_badge_labels', true);
    update_option('seo_tidy_badge_branding', false);
    update_option('seo_tidy_badge_theme', 'dark');

    $badge = StatsBadge::render([]);

    m13_assert(
        str_contains($badge, 'seo-tidy-badge--dark'),
        'Dark badge theme renders'
    );

    m13_assert(
        str_contains($badge, '<svg'),
        'Badge contains SVG icons'
    );

    m13_assert(
        !str_contains($badge, 'Powered by SEO-TidY'),
        'Branding absent when disabled'
    );

    update_option('seo_tidy_badge_branding', true);

    $badge = StatsBadge::render([]);

    m13_assert(
        str_contains($badge, 'Powered by SEO-TidY'),
        'Branding appears only when enabled'
    );

    m13_assert(
        str_contains($badge, 'rel="nofollow'),
        'Branding link uses nofollow'
    );

    $shortcode = do_shortcode(
        '[seo_tidy_stats theme="light"]'
    );

    m13_assert(
        str_contains($shortcode, 'seo-tidy-badge--light'),
        'Shortcode theme override works'
    );

    update_option('seo_tidy_analytics_enabled', false);

    m13_assert(
        StatsBadge::render([]) === '',
        'Badge hides when analytics is disabled'
    );

    global $wp_registered_settings;

    foreach ([
        'seo_tidy_community_opt_in',
        'seo_tidy_community_name',
        'seo_tidy_community_url',
        'seo_tidy_community_description',
    ] as $name) {
        m13_assert(
            isset($wp_registered_settings[$name]),
            'Community setting registered: ' . $name
        );
    }

    echo PHP_EOL . 'M13 INTEGRATION TEST COMPLETE' . PHP_EOL;
} finally {
    if ($testStarted) {
        if ($rowExisted) {
            $wpdb->update(
                $table,
                [
                    'human_pageviews' =>
                        $originalRow['human_pageviews'],
                    'suspected_bot_requests' =>
                        $originalRow['suspected_bot_requests'],
                    'unique_estimate' =>
                        $originalRow['unique_estimate'],
                    'updated_at' =>
                        $originalRow['updated_at'],
                ],
                ['stat_date' => $date]
            );
        } else {
            $wpdb->delete($table, ['stat_date' => $date]);
        }
    }

    foreach ($options as $name) {
        if ($original[$name] === null) {
            delete_option($name);
        } else {
            update_option($name, $original[$name]);
        }
    }

    foreach ($originalServer as $key => $value) {
        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }
    }

    wp_set_current_user($originalUser);
}