<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class StatsBadge
{
    public static function init(): void
    {
        add_shortcode('seo_tidy_stats', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueStyles']);
        add_action('wp_footer', [self::class, 'renderFooter'], 50);
    }

    public static function enqueueStyles(): void
    {
        if (!Analytics::enabled()) {
            return;
        }

        $file = SEO_TIDY_PATH . 'assets/stats-badge.css';

        if (!is_readable($file)) {
            return;
        }

        wp_enqueue_style(
            'seo-tidy-stats-badge',
            plugins_url(
                'assets/stats-badge.css',
                SEO_TIDY_PATH . 'seo-tidy.php'
            ),
            [],
            (string) filemtime($file)
        );
    }

    public static function renderFooter(): void
    {
        if (!get_option('seo_tidy_badge_footer', false)) {
            return;
        }

        echo self::render([]);
    }

    public static function shortcode($atts = []): string
    {
        return self::render(
            shortcode_atts(
                [
                    'theme' => get_option(
                        'seo_tidy_badge_theme',
                        'transparent'
                    ),
                ],
                is_array($atts) ? $atts : [],
                'seo_tidy_stats'
            )
        );
    }

    public static function render(array $args): string
    {
        if (!Analytics::enabled()) {
            return '';
        }

        $theme = (string) (
            $args['theme'] ??
            get_option('seo_tidy_badge_theme', 'transparent')
        );

        if (!in_array(
            $theme,
            ['light', 'dark', 'transparent'],
            true
        )) {
            $theme = 'transparent';
        }

        $showHumans = (bool) get_option(
            'seo_tidy_badge_humans',
            true
        );

        $showBots = (bool) get_option(
            'seo_tidy_badge_bots',
            true
        );

        $showLabels = (bool) get_option(
            'seo_tidy_badge_labels',
            true
        );

        $branding = (bool) get_option(
            'seo_tidy_badge_branding',
            false
        );

        if (!$showHumans && !$showBots && !$branding) {
            return '';
        }

        $latvian = str_starts_with(determine_locale(), 'lv');
        $summary = Analytics::summary(30);
        $items = [];

        if ($showHumans) {
            $items[] = self::metric(
                'users',
                $latvian ? 'Skat' . "\u{012B}" . 'jumi' : 'Views',
                (int) ($summary['humanPageviews'] ?? 0),
                $showLabels
            );
        }

        if ($showBots) {
            $items[] = self::metric(
                'bot',
                $latvian ? 'Boti' : 'Bots',
                (int) ($summary['suspectedBotRequests'] ?? 0),
                $showLabels
            );
        }

        $html = '<div class="seo-tidy-badge seo-tidy-badge--' .
            esc_attr($theme) . '" role="group" aria-label="' .
            esc_attr__('SEO-TidY website statistics', 'seo-tidy') .
            '">';

        if ($items) {
            $html .= '<div class="seo-tidy-badge__metrics">';
            $html .= implode('', $items);
            $html .= '</div>';
        }

        if ($branding) {
            $html .= '<a class="seo-tidy-badge__brand" href="' .
                esc_url(
                    'https://github.com/QvarcY/wordpress-seo-tidy'
                ) .
                '" rel="nofollow noopener noreferrer" ' .
                'target="_blank">' .
                esc_html('SEO-TidY') .
                '</a>';
        }

        return $html . '</div>';
    }

    private static function metric(
        string $icon,
        string $label,
        int $value,
        bool $showLabel
    ): string {
        $html = '<span class="seo-tidy-badge__metric" title="' .
            esc_attr($label) . '">';

        $html .= self::icon($icon);
        $html .= '<strong>' .
            esc_html(number_format_i18n($value)) .
            '</strong>';

        if ($showLabel) {
            $html .= '<span class="seo-tidy-badge__label">' .
                esc_html($label) .
                '</span>';
        } else {
            $html .= '<span class="screen-reader-text">' .
                esc_html($label) .
                '</span>';
        }

        return $html . '</span>';
    }

    private static function icon(string $type): string
    {
        $path = $type === 'bot'
            ? '<rect x="5" y="7" width="14" height="12" rx="3"/>' .
              '<path d="M12 3v4M9 12h.01M15 12h.01M9 16h6"/>'
            : '<circle cx="12" cy="8" r="3"/>' .
              '<path d="M5 20v-2a7 7 0 0 1 14 0v2"/>';

        return '<svg class="seo-tidy-badge__icon" ' .
            'viewBox="0 0 24 24" fill="none" ' .
            'stroke="currentColor" stroke-width="1.8" ' .
            'stroke-linecap="round" stroke-linejoin="round" ' .
            'aria-hidden="true" focusable="false">' .
            $path .
            '</svg>';
    }
}