<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/**
 * Experimental, read-only internal-link suggestions.
 * Disabled unless explicitly enabled in wp-config.php.
 */
final class InternalLinkingPreview
{
    public static function init(): void
    {
        if (!defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
            return;
        }
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('add_meta_boxes', [self::class, 'registerEditorBox']);
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('seo-tidy/v1', '/internal-linking', [
            'methods' => 'GET',
            'callback' => [self::class, 'overview'],
            'permission_callback' => static fn(): bool => current_user_can('manage_options'),
            'args' => [
                'page' => ['default' => 1, 'type' => 'integer', 'minimum' => 1],
                'search' => ['default' => '', 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public static function overview(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $query = new \WP_Query([
            's' => (string) $request->get_param('search'),
            'post_type' => ['post', 'page'], 'post_status' => 'publish',
            'posts_per_page' => 25, 'paged' => $page,
            'orderby' => 'ID', 'order' => 'ASC', 'ignore_sticky_posts' => true,
        ]);
        $state = InternalLinkingIndex::state();
        $ready = !empty($state['complete']) && empty($state['stale']);
        $items = [];
        foreach ($query->posts as $post) {
            $status = !self::eligible($post) ? 'excluded' : (!$ready ? 'pending' :
                (InternalLinkingIndex::entry($post->ID) ? 'indexed' : 'unindexed'));
            $items[] = [
                'id' => $post->ID,
                'title' => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'excerpt' => html_entity_decode(wp_trim_words(
                    wp_strip_all_tags(strip_shortcodes($post->post_content)), 20, '…'
                ), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'topics' => count(SmartTopics::approved($post->ID)),
                'status' => $status,
                'editUrl' => get_edit_post_link($post->ID, 'raw'),
            ];
        }
        $published = (int) wp_count_posts('post')->publish + (int) wp_count_posts('page')->publish;
        return new \WP_REST_Response([
            'items' => $items, 'total' => (int) $query->found_posts,
            'page' => $page, 'pages' => max(1, (int) $query->max_num_pages),
            'published' => $published,
            'processed' => !empty($state['stale']) ? 0 : max(0, (int) ($state['processed'] ?? 0)),
            'ready' => $ready, 'stale' => !empty($state['stale']),
            'scanAction' => admin_url('admin-post.php'),
            'scanNonce' => wp_create_nonce('seo_tidy_link_scan'),
        ]);
    }

    public static function menu(): void
    {
        add_submenu_page('seo-tidy', __('Internal Linking Preview', 'seo-tidy'),
            __('Internal Linking Preview', 'seo-tidy'), 'manage_options',
            'seo-tidy-linking-preview', [self::class, 'render']);
    }

    private static function tokens(string $text): array
    {
        $text = function_exists('mb_strtolower')
            ? mb_strtolower(wp_strip_all_tags($text), 'UTF-8')
            : strtolower(wp_strip_all_tags($text));
        preg_match_all('/[\p{L}\p{N}]{3,}/u', $text, $matches);
        $stop = array_fill_keys(explode(' ', 'the and for with from this that are can how your what where und von der die das vai kas kur par pie arī un ar no kā ko kāds tas šis šajā'), true);
        return array_values(array_filter($matches[0], static function ($t) use ($stop) {
            return !isset($stop[$t]);
        }));
    }

    public static function termTokens(string $text): array
    {
        return self::tokens($text);
    }

    public static function indexTerms(\WP_Post $post): array
    {
        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        return array_count_values(self::tokens($post->post_title . ' ' . $body));
    }

    private static function document(\WP_Post $post): array
    {
        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $topics = [];
        $existing = wp_get_post_terms($post->ID, ['category', 'post_tag'], ['fields' => 'names']);
        if (is_wp_error($existing)) {
            $existing = [];
        }
        foreach ($existing as $term) {
            foreach (self::tokens((string) $term) as $token) {
                $topics[$token] = true;
            }
        }
        foreach (SmartTopics::approved($post->ID) as $approved) {
            foreach (self::tokens($approved) as $token) {
                $topics[$token] = true;
            }
        }
        $title = self::tokens($post->post_title);
        $excerpt = function_exists('mb_substr') ? mb_substr($body, 0, 12000, 'UTF-8') : substr($body, 0, 12000);
        $bodyTerms = self::tokens($excerpt);
        $terms = array_count_values($bodyTerms);
        foreach (array_count_values($title) as $word => $count) {
            $terms[$word] = ($terms[$word] ?? 0) + 3 * $count;
        }
        return ['post' => $post, 'terms' => $terms, 'title' => $title,
            'body_terms' => array_fill_keys($bodyTerms, true),
            'topics' => array_keys($topics), 'size' => max(1, array_sum($terms))];
    }

    public static function eligible(\WP_Post $post): bool
    {
        if ($post->post_status !== 'publish' || $post->post_password !== '' ||
            !in_array($post->post_type, ['post', 'page'], true)) {
            return false;
        }
        if (get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true) === '1') {
            return false;
        }
        $robots = get_post_meta($post->ID, 'rank_math_robots', true);
        if (is_array($robots) && in_array('noindex', $robots, true)) {
            return false;
        }
        // Canonical override detection is incomplete: exclude known overrides conservatively.
        foreach (['_yoast_wpseo_canonical', 'rank_math_canonical_url'] as $key) {
            $canonical = trim((string) get_post_meta($post->ID, $key, true));
            if ($canonical !== '' && untrailingslashit($canonical) !== untrailingslashit(get_permalink($post))) {
                return false;
            }
        }
        return true;
    }

    private static function bm25(array $src, array $dst, array $df, int $total, float $average): float
    {
        $query = array_unique(array_merge($src['title'], $src['topics']));
        $score = 0.0;
        foreach ($query as $word) {
            $frequency = $dst['terms'][$word] ?? 0;
            if (!$frequency) {
                continue;
            }
            $idf = log(1 + ($total - ($df[$word] ?? 0) + .5) / (($df[$word] ?? 0) + .5));
            $score += $idf * ($frequency * 2.2) /
                ($frequency + 1.2 * (.25 + .75 * $dst['size'] / $average));
        }
        return $score;
    }

    private static function linksTo(string $html, int $targetId): bool
    {
        if (!class_exists('\WP_HTML_Tag_Processor')) {
            // Fail closed when reliable directed-link inspection is unavailable.
            return true;
        }
        $processor = new \WP_HTML_Tag_Processor($html);
        while ($processor->next_tag(['tag_name' => 'A'])) {
            $href = $processor->get_attribute('href');
            if (!is_string($href) || $href === '') {
                continue;
            }
            $parsed = wp_parse_url($href);
            if (!is_array($parsed)) {
                continue;
            }
            if (isset($parsed['host']) &&
                strtolower($parsed['host']) !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
                continue;
            }
            $resolved = isset($parsed['host']) ? $href : home_url('/' . ltrim($href, '/'));
            $resolvedId = url_to_postid(strtok($resolved, '#?'));
            if ($resolvedId === $targetId) {
                return true;
            }
        }
        return false;
    }


    public static function registerEditorBox(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        foreach (['post', 'page'] as $type) {
            add_meta_box('seo-tidy-link-recommendations',
                __('SEO-TidY internal links', 'seo-tidy'),
                [self::class, 'renderEditorBox'], $type, 'normal', 'default');
        }
    }

    public static function suggestionsForPost(\WP_Post $post): array
    {
        $state = InternalLinkingIndex::state();
        if (empty($state['complete']) || !empty($state['stale']) || !self::eligible($post)) {
            return [];
        }
        $src = self::document($post);
        $queryTerms = array_unique(array_merge($src['title'], $src['topics']));
        $ids = InternalLinkingIndex::candidateIds($queryTerms, $post->ID, 30);
        $stats = InternalLinkingIndex::corpusStats();
        if (empty($stats['count'])) {
            return [];
        }
        $df = InternalLinkingIndex::documentFrequency($queryTerms);
        $ranked = [];
        foreach ($ids as $id) {
            $target = get_post($id);
            if (!$target instanceof \WP_Post || !self::eligible($target) || $id === $post->ID) {
                continue;
            }
            $dst = self::document($target);
            $context = array_intersect($dst['title'], array_keys($src['body_terms']));
            if (!$context || self::linksTo($post->post_content, $id)) {
                continue;
            }
            if (count(array_intersect($src['topics'], $dst['topics'])) >= 2 &&
                count(array_intersect($src['title'], $dst['title'])) >= 2) {
                continue;
            }
            $score = self::bm25($src, $dst, $df, (int) $stats['count'], (float) $stats['avgdl']);
            if ($score > 0) {
                $ranked[] = ['post' => $target, 'score' => $score,
                    'context' => implode(', ', array_slice(array_unique($context), 0, 5))];
            }
        }
        usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($ranked, 0, 5);
    }

    public static function renderEditorBox(\WP_Post $post): void
    {
        echo '<p class="description">' .
            esc_html__('These suggestions are for this article only. Review the destination and insert links manually where they help readers.', 'seo-tidy') .
            '</p>';
        $state = InternalLinkingIndex::state();
        if (empty($state['complete']) || !empty($state['stale'])) {
            echo '<p>' . esc_html__('Complete the index scan from SEO-TidY before reviewing links.', 'seo-tidy') .
                ' <a href="' . esc_url(admin_url('admin.php?page=seo-tidy-linking-preview')) . '">' .
                esc_html__('Open link overview', 'seo-tidy') . '</a></p>';
            return;
        }
        $suggestions = self::suggestionsForPost($post);
        if (!$suggestions) {
            echo '<p>' . esc_html__('No reviewable link suggestions for this article.', 'seo-tidy') . '</p>';
            return;
        }
        echo '<ul>';
        foreach ($suggestions as $item) {
            $target = $item['post'];
            echo '<li style="padding:10px 0;border-bottom:1px solid #dcdcde"><strong>' .
                esc_html(get_the_title($target)) . '</strong><p class="description">' .
                esc_html(wp_trim_words(wp_strip_all_tags(strip_shortcodes($target->post_content)), 32, '…')) .
                '</p><p>' . esc_html__('Matching terms', 'seo-tidy') . ': ' .
                esc_html($item['context']) . '</p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' .
                esc_url(get_permalink($target)) . '">' . esc_html__('View destination', 'seo-tidy') .
                '</a></li>';
        }
        echo '</ul>';
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        $page = isset($_GET['source_page']) && is_scalar($_GET['source_page'])
            ? max(1, absint($_GET['source_page'])) : 1;
        $search = isset($_GET['link_search']) && is_string($_GET['link_search'])
            ? sanitize_text_field(wp_unslash($_GET['link_search'])) : '';
        $query = new \WP_Query(['s' => $search, 'post_type' => ['post', 'page'],
            'post_status' => 'publish', 'posts_per_page' => 25, 'paged' => $page,
            'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => false,
            'ignore_sticky_posts' => true]);
        $state = InternalLinkingIndex::state();
        $ready = !empty($state['complete']) && empty($state['stale']);
        $processed = max(0, (int) ($state['processed'] ?? 0));
        echo '<div class="wrap" style="max-width:1180px">';
        echo '<h1>' . esc_html__('Internal linking overview', 'seo-tidy') . '</h1>';
        echo '<p class="description">' . esc_html__('Find an article here, then manage topics and review suggested links in its WordPress editor.', 'seo-tidy') . '</p>';
        echo '<div class="postbox" style="padding:16px;margin-top:18px"><h2 style="margin:0 0 8px">' .
            esc_html__('Index status', 'seo-tidy') . '</h2><p>' .
            esc_html($ready ? __('Complete', 'seo-tidy') :
                (!empty($state['stale']) ? __('Needs rebuilding', 'seo-tidy') : __('In progress or not started', 'seo-tidy'))) .
            ' · ' . esc_html(sprintf(__('Processed: %d articles', 'seo-tidy'), $processed)) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="seo_tidy_link_scan">';
        wp_nonce_field('seo_tidy_link_scan');
        submit_button(__('Index next 25 articles', 'seo-tidy'), 'secondary', 'submit', false);
        echo ' <label style="margin-left:12px"><input type="checkbox" name="restart" value="1"> ' .
            esc_html__('Rebuild index from beginning', 'seo-tidy') . '</label>';
        echo ' <details style="display:inline-block;vertical-align:middle;margin-left:8px"><summary style="cursor:pointer;color:#2271b1">' .
            esc_html__('What does rebuilding do?', 'seo-tidy') . '</summary><p>' .
            esc_html__('Rebuilding clears the existing experimental index and starts again. Only use it if content has changed substantially or the index is invalid.', 'seo-tidy') .
            '</p></details></form></div>';
        echo '<form method="get" style="margin:20px 0"><input type="hidden" name="page" value="seo-tidy-linking-preview">';
        echo '<label for="seo-tidy-article-search" class="screen-reader-text">' . esc_html__('Find an article', 'seo-tidy') . '</label>';
        echo '<input id="seo-tidy-article-search" class="regular-text" type="search" name="link_search" value="' .
            esc_attr($search) . '" placeholder="' . esc_attr__('Search titles and content', 'seo-tidy') . '"> ';
        submit_button(__('Search', 'seo-tidy'), 'secondary', 'submit', false);
        echo '</form>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:48%">' .
            esc_html__('Article', 'seo-tidy') . '</th><th>' .
            esc_html__('Topics', 'seo-tidy') . '</th><th>' .
            esc_html__('Index', 'seo-tidy') . '</th><th>' .
            esc_html__('Action', 'seo-tidy') . '</th></tr></thead><tbody>';
        if (!$query->posts) {
            echo '<tr><td colspan="4">' . esc_html__('No articles found.', 'seo-tidy') . '</td></tr>';
        }
        foreach ($query->posts as $post) {
            $eligible = self::eligible($post);
            $entry = InternalLinkingIndex::entry($post->ID);
            $topicCount = count(SmartTopics::approved($post->ID));
            echo '<tr><td><strong>' . esc_html(get_the_title($post)) .
                '</strong><p class="description">' .
                esc_html(wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 20, '…')) .
                '</p></td><td>' . esc_html((string) $topicCount) .
                '</td><td>' . esc_html(!$eligible ? __('Excluded', 'seo-tidy') :
                    (!$ready ? __('Pending', 'seo-tidy') :
                    (!empty($entry) ? __('Indexed', 'seo-tidy') : __('Not indexed', 'seo-tidy')))) .
                '</td><td><a class="button button-secondary" href="' .
                esc_url(get_edit_post_link($post->ID)) . '">' .
                esc_html__('Open in editor', 'seo-tidy') . '</a></td></tr>';
        }
        echo '</tbody></table><p style="margin-top:18px">';
        if ($page > 1) {
            echo '<a class="button" href="' .
                esc_url(add_query_arg(['source_page' => $page - 1, 'link_search' => $search])) . '">' .
                esc_html__('Previous', 'seo-tidy') . '</a> ';
        }
        if ($page < (int) $query->max_num_pages) {
            echo '<a class="button" href="' .
                esc_url(add_query_arg(['source_page' => $page + 1, 'link_search' => $search])) . '">' .
                esc_html__('Next', 'seo-tidy') . '</a>';
        }
        echo '</p></div>';
    }
}
