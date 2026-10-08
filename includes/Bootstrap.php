<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Bootstrap
{
    public static function init(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'registerMenu']);
        }
    }

    public static function registerMenu(): void
    {
        add_menu_page(
            __('SEO-TidY', 'seo-tidy'),
            __('SEO-TidY', 'seo-tidy'),
            'manage_options',
            'seo-tidy',
            [self::class, 'renderPage'],
            'dashicons-search',
            80
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'seo-tidy'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('SEO-TidY', 'seo-tidy') . '</h1>';
        echo '<p>' . esc_html__(
            'SEO-TidY is being developed. SEO features are not available yet.',
            'seo-tidy'
        ) . '</p>';
        echo '</div>';
    }
}
