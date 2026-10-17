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
        $text = mb_strtolower(wp_strip_all_tags($text), 'UTF-8');
        preg_match_all('/[\\p{L}\\p{N}]{3,}/u', $text, $matches);
        $stop = array_fill_keys(explode(' ', 'the and for with from this that are can how your what where und von der die das vai kas kur par pie arī un ar no kā ko kāds tas šis šajā'), true);
        return array_values(array_filter($matches[0], static function ($t) use ($stop) {
            return !isset($stop[$t]);
        }));
    }

    private static function document(\WP_Post $post): array
    {
        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $topics = [];
        foreach (wp_get_post_terms($post->ID, ['category', 'post_tag'], ['fields' => 'names']) as $term) {
            foreach (self::tokens((string) $term) as $token) {
                $topics[$token] = true;
            }
        }
        $title = self::tokens($post->post_title);
        $bodyTerms = self::tokens(mb_substr($body, 0, 12000, 'UTF-8'));
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

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        $page = isset($_GET['source_page']) ? max(1, absint($_GET['source_page'])) : 1;
        $query = new \WP_Query(['post_type' => ['post', 'page'], 'post_status' => 'publish',
            'posts_per_page' => 25, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC',
            'no_found_rows' => false, 'ignore_sticky_posts' => true]);
        $sources = array_filter($query->posts, [self::class, 'eligible']);
        // Explicitly bounded local comparison window. Not a complete-site scan.
        $pool = get_posts(['post_type' => ['post', 'page'], 'post_status' => 'publish',
            'posts_per_page' => 200, 'orderby' => 'ID', 'order' => 'ASC',
            'suppress_filters' => false]);
        $documents = [];
        foreach ($pool as $post) {
            if (self::eligible($post)) {
                $documents[$post->ID] = self::document($post);
            }
        }
        $df = [];
        $totalSize = 0;
        foreach ($documents as $doc) {
            $totalSize += $doc['size'];
            foreach (array_keys($doc['terms']) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $total = count($documents);
        $avg = $total ? $totalSize / $total : 1.0;
        echo '<div class="wrap"><h1>' . esc_html__('Internal Linking Preview', 'seo-tidy') . '</h1>';
        echo '<p>' . esc_html__('Experimental read-only preview. Suggestions need manual review. Only the first 200 eligible candidates are considered; results are incomplete on larger sites.', 'seo-tidy') . '</p>';
        foreach ($sources as $post) {
            $src = self::document($post);
            echo '<h2>' . esc_html(get_the_title($post)) . '</h2><ul>';
            $ranked = [];
            foreach ($documents as $id => $target) {
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
                if (strpos($post->post_content, $url) !== false) {
                    continue;
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
