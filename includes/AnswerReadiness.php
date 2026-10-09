<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class AnswerReadiness
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('seo-tidy/v1', '/answers', [
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

    private static function textLength(string $text): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);
    }

    private static function inspect(\WP_Post $post): array
    {
        $content = (string) $post->post_content;

        // Analizējam saglabātos blokus un HTML, nevis tēmas izvadi
        $html = do_blocks($content);
        $text = trim(wp_strip_all_tags(
            strip_shortcodes($html)
        ));
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        $headings = [];
        if (preg_match_all(
            '/<h([2-6])\b[^>]*>(.*?)<\/h\1>/is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $headings[] = [
                    'level' => (int) $match[1],
                    'text' => trim(wp_strip_all_tags($match[2])),
                ];
            }
        }

        $firstParagraph = '';
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $match)) {
            $firstParagraph = trim(wp_strip_all_tags($match[1]));
        }

        $facts = [
            'characters' => self::textLength($text),
            'headings' => count($headings),
            'questionHeadings' => count(array_filter(
                $headings,
                static function (array $heading): bool {
                    return str_contains($heading['text'], '?');
                }
            )),
        ];

        $suggestions = [];

        if ($facts['characters'] === 0) {
            $suggestions[] = 'empty_content';
        } else {
            if (
                $firstParagraph === '' ||
                self::textLength($firstParagraph) < 80
            ) {
                $suggestions[] = 'review_introduction';
            }

            if ($facts['characters'] >= 600 && !$headings) {
                $suggestions[] = 'review_sections';
            }

            if ($facts['questionHeadings'] === 0) {
                $suggestions[] = 'consider_questions';
            }
        }

        return [
            'facts' => $facts,
            'suggestions' => $suggestions,
        ];
    }

    public static function getResults(
        \WP_REST_Request $request
    ): \WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));

        $query = new \WP_Query([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => $page,
            'fields' => 'ids',
            'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
            'ignore_sticky_posts' => true,
        ]);

        $items = [];

        foreach ($query->posts as $id) {
            $post = get_post($id);

            if (!$post instanceof \WP_Post) {
                continue;
            }

            $result = self::inspect($post);

            $items[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'facts' => $result['facts'],
                'suggestions' => $result['suggestions'],
                'editUrl' => get_edit_post_link($id, 'raw'),
            ];
        }

        return new \WP_REST_Response([
            'items' => $items,
            'total' => (int) $query->found_posts,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
        ]);
    }
}