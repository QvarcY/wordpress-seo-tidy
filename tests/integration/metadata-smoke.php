<?php

defined('ABSPATH') || exit;

use QvarcY\SeoTidy\Metadata;

function seo_tidy_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

$admins = get_users([
    'role' => 'administrator',
    'number' => 1,
]);

seo_tidy_check(!empty($admins), 'Administrator exists');
seo_tidy_check(class_exists(Metadata::class), 'Metadata class loaded');

$previousUser = get_current_user_id();
$created = [];

try {
    wp_set_current_user($admins[0]->ID);

    foreach (['post' => 'posts', 'page' => 'pages'] as $type => $route) {
        $id = wp_insert_post([
            'post_type' => $type,
            'post_status' => 'publish',
            'post_title' => 'SEO-TidY integration test',
            'post_content' => 'Temporary test content',
            'post_author' => $admins[0]->ID,
        ], true);

        seo_tidy_check(!is_wp_error($id) && $id > 0, $type . ' created');

        $created[] = $id;

        foreach (['_seo_tidy_title', '_seo_tidy_description'] as $key) {
            $registered = get_registered_meta_keys('post', $type);
            seo_tidy_check(isset($registered[$key]), $type . ' registers ' . $key);
        }

        $update = new WP_REST_Request('POST', '/wp/v2/' . $route . '/' . $id);
        $update->set_body_params([
            'meta' => [
                '_seo_tidy_title' => '<b>Test title</b>',
                '_seo_tidy_description' => 'Test description',
            ],
        ]);

        $response = rest_do_request($update);
        seo_tidy_check($response->get_status() === 200, $type . ' REST update');

        seo_tidy_check(
            get_post_meta($id, '_seo_tidy_title', true) === 'Test title',
            $type . ' title sanitized and saved'
        );

        seo_tidy_check(
            get_post_meta($id, '_seo_tidy_description', true) === 'Test description',
            $type . ' description saved'
        );

        $read = new WP_REST_Request('GET', '/wp/v2/' . $route . '/' . $id);
        $read->set_param('context', 'edit');

        $response = rest_do_request($read);
        $data = $response->get_data();

        seo_tidy_check(
            $response->get_status() === 200 &&
            ($data['meta']['_seo_tidy_title'] ?? null) === 'Test title',
            $type . ' REST read'
        );

        wp_set_current_user(0);

        $anonymous = new WP_REST_Request(
            'POST',
            '/wp/v2/' . $route . '/' . $id
        );

        $anonymous->set_body_params([
            'meta' => [
                '_seo_tidy_title' => 'Unauthorized change',
            ],
        ]);

        $denied = rest_do_request($anonymous);

        seo_tidy_check(
            $denied->get_status() >= 400,
            $type . ' anonymous update denied'
        );

        seo_tidy_check(
            get_post_meta($id, '_seo_tidy_title', true) === 'Test title',
            $type . ' unauthorized update made no changes'
        );

        wp_set_current_user($admins[0]->ID);
    }
} finally {
    wp_set_current_user($admins[0]->ID);

    foreach ($created as $id) {
        wp_delete_post($id, true);
    }

    wp_set_current_user($previousUser);
}

echo 'PASS: Temporary content cleaned up' . PHP_EOL;
echo 'M1.8.1 INTEGRATION TEST COMPLETE' . PHP_EOL;