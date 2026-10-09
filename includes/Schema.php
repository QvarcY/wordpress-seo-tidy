<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Schema
{
    public static function init(): void
    {
        add_action('wp_head', [self::class, 'render'], 20);
    }

    public static function render(): void
    {
        if (
            is_admin() ||
            is_feed() ||
            !is_singular(['post', 'page'])
        ) {
            return;
        }

        if (!rest_sanitize_boolean(get_option('seo_tidy_schema_enabled', true))) {
            return;
        }

        $post = get_queried_object();

        if (
            !$post instanceof \WP_Post ||
            $post->post_status !== 'publish'
        ) {
            return;
        }

        $data = self::build($post);

        if (!$data) {
            return;
        }

        $json = wp_json_encode(
            $data,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

        if (!is_string($json) || $json === '') {
            return;
        }

        echo '<script type="application/ld+json">';
        echo $json;
        echo "</script>\n";
    }

    public static function build(\WP_Post $post): array
    {
        if (
            !in_array($post->post_type, ['post', 'page'], true) ||
            $post->post_status !== 'publish'
        ) {
            return [];
        }

        $url = get_permalink($post);

        if (!is_string($url) || $url === '') {
            return [];
        }

        $customTitle = get_post_meta(
            $post->ID,
            '_seo_tidy_title',
            true
        );

        $title = is_string($customTitle) &&
            trim($customTitle) !== ''
            ? $customTitle
            : get_the_title($post);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $post->post_type === 'post'
                ? 'BlogPosting'
                : 'WebPage',
            '@id' => $url . '#seo-tidy-schema',
            'url' => $url,
            'name' => wp_strip_all_tags($title),
            'inLanguage' => get_bloginfo('language'),
        ];

        $description = get_post_meta(
            $post->ID,
            '_seo_tidy_description',
            true
        );

        if (is_string($description) && trim($description) !== '') {
            $data['description'] = $description;
        }

        $siteName = get_bloginfo('name');

        if (is_string($siteName) && $siteName !== '') {
            $data['publisher'] = [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => home_url('/'),
            ];
        }

        if ($post->post_type === 'post') {
            $data['headline'] = wp_strip_all_tags($title);
            $data['datePublished'] = get_post_time(
                'c',
                true,
                $post
            );

            $data['dateModified'] = get_post_modified_time(
                'c',
                true,
                $post
            );

            $authorName = get_the_author_meta(
                'display_name',
                (int) $post->post_author
            );

            if (is_string($authorName) && $authorName !== '') {
                $data['author'] = [
                    '@type' => 'Person',
                    'name' => $authorName,
                ];
            }
        }

        $image = get_the_post_thumbnail_url($post, 'full');

        if (is_string($image) && $image !== '') {
            $data['image'] = $image;
        }

        return $data;
    }
}