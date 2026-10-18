<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Bootstrap
{
    private static string $pageHook = '';

    public static function init(): void
    {
        require_once __DIR__ . '/Metadata.php';
        require_once __DIR__ . '/Schema.php';
        require_once __DIR__ . '/Dashboard.php';
        require_once __DIR__ . '/SEOAudit.php';
        require_once __DIR__ . '/InternalLinkingPreview.php';
        require_once __DIR__ . '/InternalLinkingIndex.php';
        require_once __DIR__ . '/InternalLinkingTranslations.php';
        require_once __DIR__ . '/SmartTopics.php';
        require_once __DIR__ . '/AnswerReadiness.php';
        require_once __DIR__ . '/Migration.php';
        require_once __DIR__ . '/GitHubUpdater.php';
        require_once __DIR__ . '/Analytics.php';
        require_once __DIR__ . '/StatsBadge.php';
        require_once __DIR__ . '/WallOfFame.php';
        require_once __DIR__ . '/WallModeration.php';
        Metadata::init();
        Schema::init();
        Dashboard::init();
        SEOAudit::init();
        InternalLinkingPreview::init();
        InternalLinkingIndex::init();
        InternalLinkingTranslations::init();
        SmartTopics::init();
        AnswerReadiness::init();
        Migration::init();
        GitHubUpdater::init();
        Analytics::init();
        StatsBadge::init();
        WallOfFame::init();
        WallModeration::init();
        add_action('init', [self::class, 'loadTranslations']);
        add_action('init', [self::class, 'registerSettings']);
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'registerMenu']);
            add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        }
    }

    public static function loadTranslations(): void
    {
        load_plugin_textdomain(
            'seo-tidy',
            false,
            dirname(plugin_basename(SEO_TIDY_PATH . 'seo-tidy.php')) .
                '/languages'
        );
    }

    public static function registerSettings(): void
    {
        foreach ([
            'seo_tidy_schema_enabled',
            'seo_tidy_title_enabled',
            'seo_tidy_description_enabled',
            'seo_tidy_audit_h1_enabled',
        ] as $name) {
            register_setting('seo_tidy', $name, [
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest' => true,
            ]);
        }

        register_setting(
            'seo_tidy',
            'seo_tidy_analytics_enabled',
            [
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest' => true,
            ]
        );

        foreach ([
            'seo_tidy_badge_footer' => false,
            'seo_tidy_badge_branding' => false,
            'seo_tidy_badge_bots' => true,
            'seo_tidy_badge_humans' => true,
            'seo_tidy_badge_labels' => true,
        ] as $name => $default) {
            register_setting('seo_tidy', $name, [
                'type' => 'boolean',
                'default' => $default,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest' => true,
            ]);
        }

        register_setting('seo_tidy', 'seo_tidy_badge_theme', [
            'type' => 'string',
            'default' => 'transparent',
            'sanitize_callback' => static function ($value): string {
                return in_array(
                    $value,
                    ['light', 'dark', 'transparent'],
                    true
                ) ? $value : 'transparent';
            },
            'show_in_rest' => true,
        ]);

        register_setting('seo_tidy', 'seo_tidy_community_opt_in', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest' => true,
        ]);

        register_setting('seo_tidy', 'seo_tidy_community_name', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
        ]);

        register_setting('seo_tidy', 'seo_tidy_community_url', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest' => true,
        ]);

        register_setting('seo_tidy', 'seo_tidy_community_description', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest' => true,
        ]);

        register_setting('seo_tidy', 'seo_tidy_beta_updates', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest' => true,
        ]);
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

        $adminCss = SEO_TIDY_PATH . 'assets/admin.css';

        if (is_readable($adminCss)) {
            wp_enqueue_style(
                'seo-tidy-admin-style',
                plugins_url('assets/admin.css', SEO_TIDY_PATH . 'seo-tidy.php'),
                ['wp-components'],
                (string) filemtime($adminCss)
            );
        }

        wp_enqueue_script(
            'seo-tidy-admin',
            plugins_url('build/index.js', SEO_TIDY_PATH . 'seo-tidy.php'),
            $asset['dependencies'],
            (string) filemtime($script),
            true
        );

        wp_localize_script(
            'seo-tidy-admin',
            'seoTidyBranding',
            [
                'linkingEnabled' => defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') && SEO_TIDY_INTERNAL_LINKING_PREVIEW === true,
                'linkingUrl' => admin_url('admin.php?page=seo-tidy-linking-preview'),
                'linkingLabels' => [
                    'tab' => __('Internal Links', 'seo-tidy'),
                    'intro' => __('Find relevant internal links and manage topics directly in each article editor.', 'seo-tidy'),
                    'description' => __('The overview shows the indexing status and helps you find the article to work on.', 'seo-tidy'),
                    'open' => __('Open internal linking overview', 'seo-tidy'),
                ],
                'heroUrl' => plugins_url(
                    'assets/seo-tidy-hero.webp',
                    SEO_TIDY_PATH . 'seo-tidy.php'
                ),
            ]
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
