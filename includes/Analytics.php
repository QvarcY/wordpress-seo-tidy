<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Analytics
{
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
    }

    public static function enabled(): bool
    {
        return (bool) get_option(
            'seo_tidy_analytics_enabled',
            false
        );
    }
}