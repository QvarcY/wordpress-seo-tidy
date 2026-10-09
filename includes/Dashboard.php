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
        register_rest_route(
            'seo-tidy/v1',
            '/content',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getContent'],
                'permission_callback' => static function (): bool {
                    return current_user_can('manage_options');
                },
                'args' => [
                    'filter' => [
                        'default' => 'all',
                        'enum' => ['all', 'missing_title', 'missing_description'],
                    ],
                    'type' => [
                        'default' => 'all',
                        'enum' => ['all', 'post', 'page'],
                    ],
                    'page' => [
                        'default' => 1,
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                ],
            ]
        );
    }

    public static function getContent(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $filter = $request->get_param('filter') ?: 'all';
        $type = $request->get_param('type') ?: 'all';
        $page = max(1, (int) $request->get_param('page'));
        $perPage = 20;

        $where = ["p.post_status = %s", "p.post_type IN (%s, %s)"];
        $params = ['publish', 'post', 'page'];

        if ($type !== 'all') {
            $where[] = 'p.post_type = %s';
            $params[] = $type;
        }

        if ($filter !== 'all') {
            $key = $filter === 'missing_title'
                ? '_seo_tidy_title'
                : '_seo_tidy_description';

            $where[] = "NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} AS m
                WHERE m.post_id = p.ID
                    AND m.meta_key = %s
                    AND TRIM(m.meta_value) <> ''
            )";
            $params[] = $key;
        }

        $condition = implode(' AND ', $where);

        $countQuery = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} AS p WHERE " . $condition,
            $params
        );

        $total = (int) $wpdb->get_var($countQuery);

        $listParams = array_merge($params, [
            $perPage,
            ($page - 1) * $perPage,
        ]);

        $listQuery = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type
            FROM {$wpdb->posts} AS p
            WHERE " . $condition . "
            ORDER BY p.post_date DESC, p.ID DESC
            LIMIT %d OFFSET %d",
            $listParams
        );

        $rows = $wpdb->get_results($listQuery, ARRAY_A);
        $items = [];

        foreach ($rows ?: [] as $row) {
            $id = (int) $row['ID'];
            $items[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'type' => $row['post_type'],
                'seoTitle' => (string) get_post_meta(
                    $id, '_seo_tidy_title', true
                ),
                'hasTitle' => trim((string) get_post_meta(
                    $id, '_seo_tidy_title', true
                )) !== '',
                'seoDescription' => (string) get_post_meta(
                    $id, '_seo_tidy_description', true
                ),
                'hasDescription' => trim((string) get_post_meta(
                    $id, '_seo_tidy_description', true
                )) !== '',
                'editUrl' => get_edit_post_link($id, 'raw'),
            ];
        }

        return new \WP_REST_Response([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ]);
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
