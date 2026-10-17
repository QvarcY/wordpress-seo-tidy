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
        add_action('admin_menu', [self::class, 'menu']);
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

    private static function eligible(\WP_Post $post): bool
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

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        $page = isset($_GET['source_page']) && is_scalar($_GET['source_page'])
            ? max(1, absint($_GET['source_page'])) : 1;
        if (get_option('blog_public') === '0') {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Search engines are discouraged at site level; indexability cannot be confirmed.', 'seo-tidy') . '</p></div>';
        }
        $query = new \WP_Query(['post_type' => ['post', 'page'], 'post_status' => 'publish',
            'posts_per_page' => 25, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC',
            'no_found_rows' => false, 'ignore_sticky_posts' => true]);
        $sources = array_filter($query->posts, [self::class, 'eligible']);
        $state = InternalLinkingIndex::state();
        $ready = !empty($state['complete']) && empty($state['stale']);
        $candidateSets = [];
        $allCandidates = [];
        if ($ready) {
            foreach ($sources as $sourcePost) {
                $srcDoc = self::document($sourcePost);
                $queryTerms = array_unique(array_merge($srcDoc['title'], $srcDoc['topics']));
                $ids = InternalLinkingIndex::candidateIds($queryTerms, $sourcePost->ID, 30);
                $candidateSets[$sourcePost->ID] = [];
                foreach ($ids as $id) {
                    $targetPost = get_post($id);
                    if (!$targetPost instanceof \WP_Post || !self::eligible($targetPost)) {
                        continue;
                    }
                    $candidateSets[$sourcePost->ID][$id] = true;
                    $allCandidates[$id] = $targetPost;
                }
            }
        }
        $documents = [];
        foreach ($allCandidates as $targetPost) {
            $documents[$targetPost->ID] = self::document($targetPost);
        }
        $stats = InternalLinkingIndex::corpusStats();
        $total = (int) $stats['count'];
        $avg = (float) $stats['avgdl'];
        $allQueryTerms = [];
        foreach ($sources as $sourcePost) {
            $src = self::document($sourcePost);
            $allQueryTerms = array_merge($allQueryTerms, $src['title'], $src['topics']);
        }
        $df = InternalLinkingIndex::documentFrequency($allQueryTerms);
        echo '<div class="wrap"><h1>' . esc_html__('Internal Linking Preview', 'seo-tidy') . '</h1>';
        echo '<p>' . esc_html__('Experimental read-only preview. Suggestions need manual review. Candidate retrieval uses the indexed token table, capped at 30 targets per source. Results remain experimental.', 'seo-tidy') . '</p>';
        echo '<p>' . esc_html(sprintf(
            /* translators: %d: articles indexed */
            __('Indexed articles: %d. Indexing is manual and resumable.', 'seo-tidy'),
            (int) ($state['processed'] ?? 0)
        )) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="seo_tidy_link_scan">';
        wp_nonce_field('seo_tidy_link_scan');
        submit_button(__('Index next 25 articles', 'seo-tidy'), 'secondary', 'submit', false);
        echo ' <label><input type="checkbox" name="restart" value="1"> ' .
            esc_html__('Rebuild index from beginning', 'seo-tidy') . '</label>';
        echo '</form>';
        if (!$ready) {
            echo '<p class="notice notice-info">' .
                esc_html__('Complete the index scan before candidate results are displayed.', 'seo-tidy') .
                '</p>';
        }
        foreach ($sources as $post) {
            $src = self::document($post);
            echo '<h2>' . esc_html(get_the_title($post)) . '</h2>';
            SmartTopics::renderReviewPanel($post);
            echo '<ul>';
            $ranked = [];
            foreach ($documents as $id => $target) {
                if (!isset($candidateSets[$post->ID][$id])) {
                    continue;
                }
                if ($id === $post->ID) {
                    continue;
                }
                $shared = array_intersect($src['topics'], $target['topics']);
                $context = array_intersect($target['title'], array_keys($src['body_terms']));
                if (!$shared && !$context) {
                    continue;
                }
                // Conservative lexical gate: require explicit target title terms in source text.
                if (count($context) < 1) {
                    continue;
                }
                $url = get_permalink($target['post']);
                if (!$url || !wp_parse_url($url, PHP_URL_HOST) ||
                    wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST)) {
                    continue;
                }
                // Check directed existing links using WordPress HTML parsing, not raw URL substrings.
                if (self::linksTo($post->post_content, $id)) {
                    continue;
                }
                $sourceTopics = array_intersect($src['topics'], $target['topics']);
                if (count($sourceTopics) >= 2 && count(array_intersect($src['title'], $target['title'])) >= 2) {
                    continue; // Possible competing intent; do not label as confident.
                }
                $score = self::bm25($src, $target, $df, $total, $avg);
                if ($score > 0) {
                    $ranked[] = ['score' => $score, 'post' => $target['post']];
                }
            }
            usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);
            foreach (array_slice($ranked, 0, 5) as $candidate) {
                echo '<li><a href="' . esc_url(get_edit_post_link($candidate['post']->ID)) . '">' .
                    esc_html(get_the_title($candidate['post'])) . '</a> — ' .
                    esc_html__('Review manually; topical similarity is not proof of complementary intent.', 'seo-tidy') . '</li>';
            }
            if (!$ranked) {
                echo '<li>' . esc_html__('No reviewable candidates in the bounded sample.', 'seo-tidy') . '</li>';
            }
            echo '</ul>';
        }
        if ($page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg('source_page', $page - 1)) . '">' .
                esc_html__('Previous', 'seo-tidy') . '</a> ';
        }
        if ($page < (int) $query->max_num_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg('source_page', $page + 1)) . '">' .
                esc_html__('Next', 'seo-tidy') . '</a>';
        }
        echo '</div>';
    }
}
