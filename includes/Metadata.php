<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Metadata
{
    public static function init(): void
    {
        add_action('init', [self::class, 'registerRestFields']);
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditor']);
        add_action('save_post', [self::class, 'save'], 10, 2);
        add_filter('pre_get_document_title', [self::class, 'documentTitle']);
        add_action('wp_head', [self::class, 'description'], 1);
    }

    public static function registerRestFields(): void
    {
        foreach (['post', 'page'] as $type) {
            foreach (['_seo_tidy_title', '_seo_tidy_description'] as $key) {
                register_post_meta(
                    $type,
                    $key,
                    [
                        'type' => 'string',
                        'single' => true,
                        'default' => '',
                        'show_in_rest' => [
                            'schema' => [
                                'type' => 'string',
                                'context' => ['view', 'edit'],
                            ],
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'auth_callback' => static function (
                            $allowed,
                            $metaKey,
                            $postId
                        ): bool {
                            return current_user_can('edit_post', (int) $postId);
                        },
                    ]
                );
            }
        }
    }
    public static function enqueueEditor(): void
    {
        $screen = get_current_screen();

        if (
            !$screen ||
            !in_array($screen->post_type, ['post', 'page'], true) ||
            !$screen->is_block_editor()
        ) {
            return;
        }

        $script = dirname(__DIR__) . '/build/editor/editor.js';

        $manifest = dirname(__DIR__) . '/build/editor/editor.asset.php';

        if (!is_readable($script) || !is_readable($manifest)) {
            return;
        }

        $asset = require $manifest;

        wp_enqueue_script(
            'seo-tidy-editor',
            plugins_url('build/editor/editor.js', dirname(__DIR__) . '/seo-tidy.php'),
            $asset['dependencies'],
            (string) filemtime($script),
            true
        );

        wp_set_script_translations(
            'seo-tidy-editor',
            'seo-tidy',
            dirname(__DIR__) . '/languages'
        );
    }
    public static function addMetaBox(): void
    {
        foreach (['post', 'page'] as $type) {
            add_meta_box(
                'seo-tidy-metadata',
                __('SEO-TidY Metadata', 'seo-tidy'),
                [self::class, 'render'],
                $type,
                'normal',
                'default',
                ['__back_compat_meta_box' => true]
            );
        }
    }

    public static function render(\WP_Post $post): void
    {
        $title = get_post_meta($post->ID, '_seo_tidy_title', true);
        $description = get_post_meta($post->ID, '_seo_tidy_description', true);

        wp_nonce_field('seo_tidy_save_metadata', 'seo_tidy_metadata_nonce');

        echo '<p><label for="seo-tidy-title"><strong>';
        echo esc_html__('SEO Title', 'seo-tidy');
        echo '</strong></label></p>';

        echo '<input type="text" class="widefat" id="seo-tidy-title" ';
        echo 'name="seo_tidy_title" value="' . esc_attr($title) . '">';

        echo '<p><label for="seo-tidy-description"><strong>';
        echo esc_html__('Meta Description', 'seo-tidy');
        echo '</strong></label></p>';

        echo '<textarea class="widefat" rows="4" ';
        echo 'id="seo-tidy-description" name="seo_tidy_description">';
        echo esc_textarea($description);
        echo '</textarea>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if (!in_array($post->post_type, ['post', 'page'], true)) {
            return;
        }

        if (
            !isset($_POST['seo_tidy_metadata_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['seo_tidy_metadata_nonce'])),
                'seo_tidy_save_metadata'
            )
        ) {
            return;
        }

        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            wp_is_post_revision($postId) ||
            !current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $fields = [
            'seo_tidy_title' => '_seo_tidy_title',
            'seo_tidy_description' => '_seo_tidy_description',
        ];

        foreach ($fields as $input => $key) {
            if (!isset($_POST[$input]) || !is_string($_POST[$input])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_POST[$input]));

            if ($value === '') {
                delete_post_meta($postId, $key);
            } else {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    public static function documentTitle(string $title): string
    {
        if (
            !get_option('seo_tidy_title_enabled', true) ||
            !is_singular(['post', 'page'])
        ) {
            return $title;
        }

        $custom = get_post_meta(get_queried_object_id(), '_seo_tidy_title', true);

        return is_string($custom) && $custom !== '' ? $custom : $title;
    }

    public static function description(): void
    {
        if (
            !get_option('seo_tidy_description_enabled', true) ||
            !is_singular(['post', 'page'])
        ) {
            return;
        }

        $description = get_post_meta(
            get_queried_object_id(),
            '_seo_tidy_description',
            true
        );

        if (is_string($description) && $description !== '') {
            echo '<meta name="description" content="';
            echo esc_attr($description);
            echo '">' . "\n";
        }
    }
}
