<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Dashboard
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(
            'seo-tidy/v1',
            '/dashboard',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getData'],
                'permission_callback' => static function (): bool {
                    return current_user_can('manage_options');
                },
            ]
        );
    }

    public static function getData(): \WP_REST_Response
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE WHEN EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} AS m
                        WHERE m.post_id = p.ID
                            AND m.meta_key = '_seo_tidy_title'
                            AND TRIM(m.meta_value) <> ''
                    ) THEN 1 ELSE 0 END
                ), 0) AS titles,
                COALESCE(SUM(
                    CASE WHEN EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} AS m
                        WHERE m.post_id = p.ID
                            AND m.meta_key = '_seo_tidy_description'
                            AND TRIM(m.meta_value) <> ''
                    ) THEN 1 ELSE 0 END
                ), 0) AS descriptions,
                COALESCE(SUM(
                    CASE WHEN p.post_type = 'post' THEN 1 ELSE 0 END
                ), 0) AS posts,
                COALESCE(SUM(
                    CASE WHEN p.post_type = 'page' THEN 1 ELSE 0 END
                ), 0) AS pages
            FROM {$wpdb->posts} AS p
            WHERE p.post_status = %s
                AND p.post_type IN (%s, %s)",
            'publish',
            'post',
            'page'
        );

        $counts = $wpdb->get_row($query, ARRAY_A);

        if (!is_array($counts)) {
            return new \WP_REST_Response(
                ['message' => 'Could not load dashboard statistics.'],
                500
            );
        }

        $total = (int) $counts['total'];
        $titles = (int) $counts['titles'];
        $descriptions = (int) $counts['descriptions'];

        return new \WP_REST_Response([
            'total' => $total,
            'posts' => (int) $counts['posts'],
            'pages' => (int) $counts['pages'],
            'titles' => $titles,
            'descriptions' => $descriptions,
            'missingTitles' => $total - $titles,
            'missingDescriptions' => $total - $descriptions,
            'schemaEnabled' => rest_sanitize_boolean(
                get_option('seo_tidy_schema_enabled', true)
            ),
            'links' => [
                'posts' => admin_url('edit.php'),
                'pages' => admin_url('edit.php?post_type=page'),
            ],
        ]);
    }
}
