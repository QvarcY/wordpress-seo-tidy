<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Analytics
{
    private const SCHEMA_VERSION = '1';

    public static function init(): void
    {
        add_action(
            'template_redirect',
            [self::class, 'recordRequest'],
            20
        );

        add_action(
            'rest_api_init',
            [self::class, 'registerRoutes']
        );

        add_action(
            'seo_tidy_analytics_daily_cleanup',
            [self::class, 'cleanupOldData']
        );

        if (
            self::enabled() &&
            !wp_next_scheduled('seo_tidy_analytics_daily_cleanup')
        ) {
            wp_schedule_event(
                time() + HOUR_IN_SECONDS,
                'daily',
                'seo_tidy_analytics_daily_cleanup'
            );
        }
    }

    public static function registerRoutes(): void
    {
        self::registerResetRoute();

        register_rest_route(
            'seo-tidy/v1',
            '/analytics',
            [
                'methods' => 'GET',
                'permission_callback' => static function (): bool {
                    return current_user_can('manage_options');
                },
                'callback' => [self::class, 'getRestSummary'],
                'args' => [
                    'days' => [
                        'default' => 30,
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 365,
                    ],
                ],
            ]
        );
    }

    public static function registerResetRoute(): void
    {
        register_rest_route(
            'seo-tidy/v1',
            '/analytics/reset',
            [
                'methods' => 'POST',
                'permission_callback' => static function (): bool {
                    return current_user_can('manage_options');
                },
                'callback' => [self::class, 'resetStatistics'],
                'args' => [
                    'confirm' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ]
        );
    }

    public static function resetStatistics(
        \WP_REST_Request $request
    ): \WP_REST_Response {
        if ($request->get_param('confirm') !== 'DELETE') {
            return new \WP_REST_Response(
                ['message' => 'Explicit deletion confirmation required.'],
                400
            );
        }

        global $wpdb;

        $table = self::tableName();

        if (!self::tableExists()) {
            return new \WP_REST_Response(
                ['message' => 'Statistics table does not exist.'],
                404
            );
        }

        $deleted = $wpdb->query("DELETE FROM {$table}");

        if ($deleted === false) {
            return new \WP_REST_Response(
                ['message' => 'Could not delete statistics.'],
                500
            );
        }

        return new \WP_REST_Response([
            'deleted' => true,
            'deletedRows' => (int) $deleted,
        ]);
    }

    public static function getRestSummary(
        \WP_REST_Request $request
    ): \WP_REST_Response {
        $days = min(
            365,
            max(1, (int) $request->get_param('days'))
        );

        return new \WP_REST_Response(
            self::summary($days)
        );
    }

    public static function tableExists(): bool
    {
        global $wpdb;

        $table = self::tableName();

        return $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($table)
            )
        ) === $table;
    }

    public static function cleanupOldData(): void
    {
        global $wpdb;

        if (!self::tableExists()) {
            return;
        }

        $timezone = wp_timezone();
        $cutoff = (new \DateTimeImmutable('today', $timezone))
            ->modify('-364 days')
            ->format('Y-m-d');

        $table = self::tableName();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE stat_date < %s",
                $cutoff
            )
        );
    }

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'seo_tidy_stats_daily';
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            stat_date date NOT NULL,
            human_pageviews bigint(20) unsigned NOT NULL DEFAULT 0,
            suspected_bot_requests bigint(20) unsigned NOT NULL DEFAULT 0,
            unique_estimate bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY stat_date (stat_date)
        ) {$charset};";

        dbDelta($sql);

        // Mark installed only when the table really exists.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($table)
            )
        );

        if ($existing === $table) {
            update_option(
                'seo_tidy_analytics_schema_version',
                self::SCHEMA_VERSION,
                false
            );
        }
    }

    public static function enabled(): bool
    {
        return (bool) get_option(
            'seo_tidy_analytics_enabled',
            false
        );
    }

    public static function recordRequest(): void
    {
        if (!self::enabled() || !self::shouldCount()) {
            return;
        }

        global $wpdb;

        if (
            get_option(
                'seo_tidy_analytics_schema_version',
                ''
            ) !== self::SCHEMA_VERSION
        ) {
            self::install();

            if (
                get_option(
                    'seo_tidy_analytics_schema_version',
                    ''
                ) !== self::SCHEMA_VERSION
            ) {
                return;
            }
        }

        $agent = isset($_SERVER['HTTP_USER_AGENT'])
            && is_string($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512)
            : '';

        $bot = self::suspectedBot($agent);

        $column = $bot
            ? 'suspected_bot_requests'
            : 'human_pageviews';

        $date = wp_date('Y-m-d');
        $updated = current_time('mysql');
        $table = self::tableName();

        $sql = "INSERT INTO {$table}
            (stat_date, {$column}, updated_at)
            VALUES (%s, 1, %s)
            ON DUPLICATE KEY UPDATE
                {$column} = {$column} + 1,
                updated_at = VALUES(updated_at)";

        $wpdb->query(
            $wpdb->prepare($sql, $date, $updated)
        );
    }

    public static function shouldCount(): bool
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ||
            is_admin() ||
            is_user_logged_in() ||
            wp_doing_ajax() ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            is_404() ||
            is_feed() ||
            is_preview() ||
            is_trackback() ||
            is_robots()
        ) {
            return false;
        }

        return true;
    }

    public static function suspectedBot(string $agent): bool
    {
        if ($agent === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|' .
            'chatgpt-user|gptbot|claudebot|bytespider|headless|' .
            'curl\/|wget\/|python-requests|go-http-client|' .
            'uptimerobot|monitoring/i',
            $agent
        );
    }

    public static function summary(int $days = 30): array
    {
        global $wpdb;

        $days = min(365, max(1, $days));
        $from = wp_date(
            'Y-m-d',
            time() - (($days - 1) * DAY_IN_SECONDS)
        );

        $table = self::tableName();

        if (!self::enabled()) {
            return [
                'enabled' => false,
                'humanPageviews' => 0,
                'suspectedBotRequests' => 0,
                'periodDays' => $days,
            ];
        }

        if (
            get_option(
                'seo_tidy_analytics_schema_version',
                ''
            ) !== self::SCHEMA_VERSION
        ) {
            self::install();
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(human_pageviews), 0) AS humans,
                    COALESCE(SUM(suspected_bot_requests), 0) AS bots
                FROM {$table}
                WHERE stat_date >= %s",
                $from
            ),
            ARRAY_A
        );

        return [
            'enabled' => true,
            'humanPageviews' => (int) ($row['humans'] ?? 0),
            'suspectedBotRequests' => (int) ($row['bots'] ?? 0),
            'periodDays' => $days,
        ];
    }
}