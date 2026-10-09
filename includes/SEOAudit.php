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


    private static function duplicateMetadata(): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT pm.meta_key,
                    TRIM(pm.meta_value) AS value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p
                ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('post', 'page')
               AND pm.meta_key IN (%s, %s)
               AND TRIM(pm.meta_value) <> ''
             GROUP BY pm.meta_key,
                      BINARY TRIM(pm.meta_value)
             HAVING COUNT(DISTINCT pm.post_id) > 1",
            '_seo_tidy_title',
            '_seo_tidy_description'
        );

        $rows = $wpdb->get_results($sql);
        $duplicates = [];

        foreach ((array) $rows as $row) {
            $duplicates[$row->meta_key][(string) $row->value] = true;
        }

        return $duplicates;
    }


    private static function noindexSource(int $postId): string
    {
        if ((string) get_option('blog_public', '1') === '0') {
            return 'site';
        }

        $yoast = get_post_meta(
            $postId,
            '_yoast_wpseo_meta-robots-noindex',
            true
        );

        if ((string) $yoast === '1') {
            return 'yoast';
        }

        $rankMath = get_post_meta(
            $postId,
            'rank_math_robots',
            true
        );

        if (
            is_array($rankMath) &&
            in_array('noindex', $rankMath, true)
        ) {
            return 'rank_math';
        }

        return '';
    }

    private static function needsAltReview(string $content): bool
    {
        $processor = new \WP_HTML_Tag_Processor($content);

        while ($processor->next_tag('IMG')) {
            if ($processor->get_attribute('alt') === null) {
                return true;
            }
        }

        foreach (parse_blocks($content) as $block) {
            if (self::blockNeedsAltReview($block)) {
                return true;
            }
        }

        return false;
    }

    private static function blockNeedsAltReview(array $block): bool
    {
        if (($block['blockName'] ?? '') === 'core/image') {
            $html = (string) ($block['innerHTML'] ?? '');
            $attrs = $block['attrs'] ?? [];

            if (
                is_array($attrs) &&
                stripos($html, '<img') === false &&
                (isset($attrs['id']) || isset($attrs['url'])) &&
                !array_key_exists('alt', $attrs)
            ) {
                return true;
            }
        }

        foreach (($block['innerBlocks'] ?? []) as $inner) {
            if (is_array($inner) && self::blockNeedsAltReview($inner)) {
                return true;
            }
        }

        return false;
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
        $duplicates = self::duplicateMetadata();

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

            if (isset(
                $duplicates['_seo_tidy_title'][$title]
            )) {
                $issues[] = 'duplicate_title';
            }

            if ($description === '') {
                $issues[] = 'missing_description';
            } elseif (self::length($description) < 70) {
                $issues[] = 'short_description';
            } elseif (self::length($description) > 160) {
                $issues[] = 'long_description';
            }

            if (isset(
                $duplicates['_seo_tidy_description'][$description]
            )) {
                $issues[] = 'duplicate_description';
            }


            $noindex = self::noindexSource((int) $id);

            if ($noindex !== '') {
                $issues[] = $noindex === 'site'
                    ? 'site_noindex'
                    : 'review_noindex';
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
            if (self::needsAltReview($content)) {
                $issues[] = 'review_image_alt';
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