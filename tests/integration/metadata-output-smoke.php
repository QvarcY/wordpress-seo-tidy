<?php

defined('ABSPATH') || exit;

use QvarcY\SeoTidy\Metadata;

function seo_tidy_output_check(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }

    echo 'PASS: ' . $label . PHP_EOL;
}

$admins = get_users(['role' => 'administrator', 'number' => 1]);
seo_tidy_output_check(!empty($admins), 'Administrator exists');

$oldUser = get_current_user_id();
$oldPost = $_POST;
$oldQuery = $GLOBALS['wp_query'] ?? null;
$created = [];

try {
    wp_set_current_user($admins[0]->ID);

    foreach (['post', 'page'] as $type) {
        $id = wp_insert_post([
            'post_type' => $type,
            'post_status' => 'publish',
            'post_title' => 'Original SEO-TidY title',
            'post_author' => $admins[0]->ID,
        ], true);

        seo_tidy_output_check(
            !is_wp_error($id) && $id > 0,
            $type . ' created'
        );

        $created[] = $id;

        $_POST = [
            'seo_tidy_metadata_nonce' => wp_create_nonce(
                'seo_tidy_save_metadata'
            ),
            'seo_tidy_title' => 'Classic SEO title',
            'seo_tidy_description' => 'Classic "quoted" & <b>bold</b>',
        ];

        do_action('save_post', $id, get_post($id), true);

        seo_tidy_output_check(
            get_post_meta($id, '_seo_tidy_title', true) ===
                'Classic SEO title',
            $type . ' classic title saved'
        );

        seo_tidy_output_check(
            get_post_meta($id, '_seo_tidy_description', true) ===
                'Classic "quoted" & bold',
            $type . ' classic description sanitized'
        );

        $args = ['post_type' => $type];
        $args[$type === 'page' ? 'page_id' : 'p'] = $id;

        $GLOBALS['wp_query'] = new WP_Query($args);

        seo_tidy_output_check(
            is_singular($type) && get_queried_object_id() === $id,
            $type . ' singular query ready'
        );

        $title = apply_filters(
            'pre_get_document_title',
            'Original SEO-TidY title'
        );

        seo_tidy_output_check(
            $title === 'Classic SEO title',
            $type . ' frontend title override'
        );

        ob_start();
        Metadata::description();
        $head = ob_get_clean();

        $expected = '<meta name="description" content="Classic ' .
            '&quot;quoted&quot; &amp; bold">';

        seo_tidy_output_check(
            substr_count($head, '<meta name="description"') === 1 &&
            str_contains($head, $expected),
            $type . ' description escaped and emitted once'
        );

        $_POST = [
            'seo_tidy_metadata_nonce' => 'invalid-nonce',
            'seo_tidy_title' => 'Unauthorized change',
        ];

        do_action('save_post', $id, get_post($id), true);

        seo_tidy_output_check(
            get_post_meta($id, '_seo_tidy_title', true) ===
                'Classic SEO title',
            $type . ' invalid nonce rejected'
        );

        $_POST = [
            'seo_tidy_metadata_nonce' => wp_create_nonce(
                'seo_tidy_save_metadata'
            ),
            'seo_tidy_title' => '',
            'seo_tidy_description' => '',
        ];

        do_action('save_post', $id, get_post($id), true);

        seo_tidy_output_check(
            get_post_meta($id, '_seo_tidy_title', true) === '' &&
            get_post_meta($id, '_seo_tidy_description', true) === '',
            $type . ' empty SEO fields cleared'
        );

        seo_tidy_output_check(
            apply_filters(
                'pre_get_document_title',
                'Original SEO-TidY title'
            ) === 'Original SEO-TidY title',
            $type . ' title fallback'
        );

        ob_start();
        Metadata::description();
        $emptyHead = ob_get_clean();

        seo_tidy_output_check(
            $emptyHead === '',
            $type . ' no empty description tag'
        );
    }
} finally {
    $_POST = $oldPost;
    $GLOBALS['wp_query'] = $oldQuery;

    wp_set_current_user($admins[0]->ID);

    foreach ($created as $id) {
        wp_delete_post($id, true);
    }

    wp_set_current_user($oldUser);
}

echo 'PASS: Temporary content cleaned up' . PHP_EOL;
echo 'M1.8.2 INTEGRATION TEST COMPLETE' . PHP_EOL;