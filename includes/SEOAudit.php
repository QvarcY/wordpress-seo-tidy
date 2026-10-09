<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class SEOAudit
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('seo-tidy/v1', '/audit', [
            'methods' => 'GET',
            'callback' => [self::class, 'getResults'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'args' => [
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'minimum' => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    public static function getResults(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));

        $query = new \WP_Query([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => $page,
            'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
            'fields' => 'ids',
            'ignore_sticky_posts' => true,
        ]);

        $items = [];

        foreach ($query->posts as $id) {
            $post = get_post($id);
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $issues = [];
            $title = trim((string) get_post_meta(
                $id, '_seo_tidy_title', true
            ));
            $description = trim((string) get_post_meta(
                $id, '_seo_tidy_description', true
            ));

            if ($title === '') {
                $issues[] = trim((string) get_the_title($id)) === ''
                    ? 'missing_title'
                    : 'default_title';
            } elseif (self::length($title) < 30) {
                $issues[] = 'short_title';
            } elseif (self::length($title) > 65) {
                $issues[] = 'long_title';
            }

            if ($description === '') {
                $issues[] = 'missing_description';
            } elseif (self::length($description) < 70) {
                $issues[] = 'short_description';
            } elseif (self::length($description) > 160) {
                $issues[] = 'long_description';
            }

            $content = (string) $post->post_content;

            // Tēma var pievienot H1 atsevišķi
            if (
                get_option('seo_tidy_audit_h1_enabled', true) &&
                stripos($content, '<h1') === false &&
                stripos($content, '<!-- wp:heading {"level":1') === false
            ) {
                $issues[] = 'review_h1';
            }

            // Pārbaudām tikai saturā esošos HTML attēlus
            if (preg_match_all('/<img\b[^>]*>/i', $content, $images)) {
                foreach ($images[0] as $image) {
                    if (
                        !preg_match('/\balt\s*=\s*(["\x27]).*?\1/is', $image)
                    ) {
                        $issues[] = 'review_image_alt';
                        break;
                    }
                }
            }

            $items[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'type' => $post->post_type,
                'issues' => $issues,
                'seoTitle' => $title,
                'seoDescription' => $description,
                'editUrl' => get_edit_post_link($id, 'raw'),
            ];
        }

        return new \WP_REST_Response([
            'items' => $items,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
            'total' => (int) $query->found_posts,
        ]);
    }
}