<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Bootstrap
{
    private static string $pageHook = '';

    public static function init(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'registerMenu']);
            add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        }
    }

    public static function registerMenu(): void
    {
        self::$pageHook = (string) add_menu_page(
            __('SEO-TidY', 'seo-tidy'),
            __('SEO-TidY', 'seo-tidy'),
            'manage_options',
            'seo-tidy',
            [self::class, 'renderPage'],
            'dashicons-search',
            80
        );
    }

    public static function enqueueAssets(string $hook): void
    {
        if ($hook !== self::$pageHook || !current_user_can('manage_options')) {
            return;
        }

        $script = SEO_TIDY_PATH . 'build/index.js';
        $manifest = SEO_TIDY_PATH . 'build/index.asset.php';

        if (!is_readable($script) || !is_readable($manifest)) {
            return;
        }

        $asset = require $manifest;

        if (
            !is_array($asset) ||
            !isset($asset['dependencies']) ||
            !is_array($asset['dependencies'])
        ) {
            return;
        }

        wp_enqueue_style('wp-components');

        wp_enqueue_script(
            'seo-tidy-admin',
            plugins_url('build/index.js', SEO_TIDY_PATH . 'seo-tidy.php'),
            $asset['dependencies'],
            (string) filemtime($script),
            true
        );

        wp_set_script_translations(
            'seo-tidy-admin',
            'seo-tidy',
            SEO_TIDY_PATH . 'languages'
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'seo-tidy'));
        }

        echo '<div id="seo-tidy-app" class="wrap">';
        echo '<h1>' . esc_html__('SEO-TidY', 'seo-tidy') . '</h1>';
        echo '<p>' . esc_html__(
            'Loading SEO-TidY administration...',
            'seo-tidy'
        ) . '</p>';
        echo '</div>';
    }
}
