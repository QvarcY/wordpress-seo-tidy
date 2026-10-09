<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class SEOAudit
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
        add_action('save_post', [self::class, 'clearIncomingLinksCache']);
        add_action('deleted_post', [self::class, 'clearIncomingLinksCache']);
        add_action('transition_post_status', [self::class, 'clearIncomingLinksCache']);
        add_action('update_option_home', [self::class, 'clearIncomingLinksCache']);
        add_action('update_option_permalink_structure', [self::class, 'clearIncomingLinksCache']);
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


    private static function hasInternalLink(string $content, int $postId): bool
    {
        $site = wp_parse_url(home_url('/'));
        $siteHost = strtolower((string) ($site['host'] ?? ''));

        if ($siteHost === '') {
            return false;
        }

        $current = wp_parse_url(get_permalink($postId));
        $currentPath = rtrim(
            (string) ($current['path'] ?? '/'),
            '/'
        ) . '/';

        $processor = new \WP_HTML_Tag_Processor($content);

        while ($processor->next_tag('A')) {
            $href = $processor->get_attribute('href');

            if (!is_string($href)) {
                continue;
            }

            $href = trim($href);

            if (
                $href === '' ||
                str_starts_with($href, '#') ||
                str_starts_with($href, '?')
            ) {
                continue;
            }

            $parts = wp_parse_url($href);

            if (!is_array($parts)) {
                continue;
            }

            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if (
                $scheme !== '' &&
                !in_array($scheme, ['http', 'https'], true)
            ) {
                continue;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));

            if ($host !== '' && $host !== $siteHost) {
                continue;
            }

            if (
                $host !== '' &&
                isset($parts['port']) &&
                (int) $parts['port'] !== (int) (
                    $site['port'] ?? (
                        ($site['scheme'] ?? 'http') === 'https' ? 443 : 80
                    )
                )
            ) {
                continue;
            }

            $path = (string) ($parts['path'] ?? '');

            if ($host !== '' && $path === '') {
                $path = '/';
            }

            if ($path === '') {
                continue;
            }

            if (
                $host !== '' ||
                str_starts_with($href, '/')
            ) {
                $linkPath = rtrim($path, '/') . '/';

                if ($linkPath === $currentPath) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }



    public static function clearIncomingLinksCache(): void
    {
        delete_transient('seo_tidy_incoming_links_v1');
    }

    private static function incomingContentLinks(): ?array
    {
        $cached = get_transient('seo_tidy_incoming_links_v1');

        if (
            is_array($cached) &&
            array_key_exists('result', $cached)
        ) {
            return $cached['result'];
        }

        $result = self::buildIncomingContentLinks();

        set_transient(
            'seo_tidy_incoming_links_v1',
            ['result' => $result],
            5 * MINUTE_IN_SECONDS
        );

        return $result;
    }

    private static function buildIncomingContentLinks(): ?array
    {
        $ids = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => 201,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        if (count($ids) > 200) {
            return null;
        }

        $incoming = array_fill_keys($ids, 0);
        $home = wp_parse_url(home_url('/'));
        $scheme = (string) ($home['scheme'] ?? 'https');
        $host = (string) ($home['host'] ?? '');
        $port = isset($home['port']) ? ':' . $home['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        if ($host === '') {
            return null;
        }

        foreach ($ids as $sourceId) {
            $post = get_post($sourceId);

            if (!$post instanceof \WP_Post) {
                continue;
            }

            $processor = new \WP_HTML_Tag_Processor(
                (string) $post->post_content
            );
            $linked = [];

            while ($processor->next_tag('A')) {
                $href = $processor->get_attribute('href');

                if (!is_string($href)) {
                    continue;
                }

                $href = trim($href);

                if (
                    $href === '' ||
                    str_starts_with($href, '#') ||
                    str_starts_with($href, '?')
                ) {
                    continue;
                }

                if (str_starts_with($href, '//')) {
                    $href = $scheme . ':' . $href;
                } elseif (str_starts_with($href, '/')) {
                    $href = $origin . $href;
                } elseif (!preg_match('~^https?://~i', $href)) {
                    continue;
                }

                $parts = wp_parse_url($href);

                if (
                    !is_array($parts) ||
                    strtolower((string) ($parts['host'] ?? '')) !==
                        strtolower($host)
                ) {
                    continue;
                }

                $targetId = url_to_postid($href);

                if (
                    $targetId !== (int) $sourceId &&
                    array_key_exists($targetId, $incoming)
                ) {
                    $linked[$targetId] = true;
                }
            }

            foreach (array_keys($linked) as $targetId) {
                ++$incoming[$targetId];
            }
        }

        return $incoming;
    }


    private static function unavailablePostLinks(string $content): array
    {
        $home = wp_parse_url(home_url('/'));
        $homeHost = strtolower((string) ($home['host'] ?? ''));

        if ($homeHost === '') {
            return [];
        }

        $homeScheme = strtolower(
            (string) ($home['scheme'] ?? 'http')
        );
        $homePort = (int) ($home['port'] ?? (
            $homeScheme === 'https' ? 443 : 80
        ));

        $links = [];
        $processor = new \WP_HTML_Tag_Processor($content);

        while ($processor->next_tag('A')) {
            $href = $processor->get_attribute('href');

            if (!is_string($href)) {
                continue;
            }

            $href = trim($href);

            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $parts = wp_parse_url($href);

            if (!is_array($parts)) {
                continue;
            }

            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if (
                $scheme !== '' &&
                !in_array($scheme, ['http', 'https'], true)
            ) {
                continue;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));

            if ($host !== '' && $host !== $homeHost) {
                continue;
            }

            if ($host !== '') {
                $effectiveScheme = $scheme ?: $homeScheme;
                $port = (int) ($parts['port'] ?? (
                    $effectiveScheme === 'https' ? 443 : 80
                ));

                if ($port !== $homePort) {
                    continue;
                }
            }

            if (empty($parts['query'])) {
                continue;
            }

            $params = [];
            parse_str((string) $parts['query'], $params);

            foreach (['p', 'page_id'] as $key) {
                if (
                    !isset($params[$key]) ||
                    !is_scalar($params[$key])
                ) {
                    continue;
                }

                $raw = (string) $params[$key];

                if (!ctype_digit($raw) || (int) $raw < 1) {
                    continue;
                }

                $target = get_post((int) $raw);

                if (
                    !$target instanceof \WP_Post ||
                    !in_array($target->post_type, ['post', 'page'], true) ||
                    $target->post_status !== 'publish'
                ) {
                    $links[$href] = true;

                    if (count($links) >= 10) {
                        return array_keys($links);
                    }
                }
            }
        }

        return array_keys($links);
    }

    private static function hasUnavailablePostLink(string $content): bool
    {
        return self::unavailablePostLinks($content) !== [];
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
        $incomingLinks = self::incomingContentLinks();

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

            if (
                is_array($incomingLinks) &&
                isset($incomingLinks[$id]) &&
                $incomingLinks[$id] === 0
            ) {
                $issues[] = 'review_incoming_links';
            }

            $content = (string) $post->post_content;

            if (
                trim(wp_strip_all_tags($content)) !== '' &&
                !self::hasInternalLink($content, (int) $id)
            ) {
                $issues[] = 'review_internal_links';
            }



            // Tēma var pievienot H1 atsevišķi
            if (
                get_option('seo_tidy_audit_h1_enabled', true) &&
                stripos($content, '<h1') === false &&
                stripos($content, '<!-- wp:heading {"level":1') === false
            ) {
                $issues[] = 'review_h1';
            }

            // Pārbaudām tikai saturā esošos HTML attēlus
            $unavailableLinks = self::unavailablePostLinks($content);

            if ($unavailableLinks !== []) {
                $issues[] = 'review_unavailable_post_link';
            }

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
                'unavailableLinks' => $unavailableLinks,
                'editUrl' => get_edit_post_link($id, 'raw'),
            ];
        }

        return new \WP_REST_Response([
            'items' => $items,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
            'total' => (int) $query->found_posts,
            'incomingCheckSkipped' => $incomingLinks === null,
        ]);
    }
}