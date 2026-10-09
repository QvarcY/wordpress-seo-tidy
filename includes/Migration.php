<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Migration
{
    private const SOURCES = [
        'yoast' => [
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
        ],
        'rank_math' => [
            'rank_math_title',
            'rank_math_description',
        ],
    ];

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        foreach (['GET', 'POST'] as $method) {
            register_rest_route('seo-tidy/v1', '/migration', [
                'methods' => $method,
                'callback' => $method === 'GET'
                    ? [self::class, 'preview']
                    : [self::class, 'importBatch'],
                'permission_callback' => static function (): bool {
                    return current_user_can('manage_options');
                },
                'args' => [
                    'source' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => array_keys(self::SOURCES),
                    ],
                    'cursor' => [
                        'default' => 0,
                        'type' => 'integer',
                        'minimum' => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);
        }
    }

    private static function permissionKey(string $source): string
    {
        return 'seo_tidy_migration_' .
            get_current_user_id() . '_' . $source;
    }

    private static function keys(\WP_REST_Request $request): array
    {
        return self::SOURCES[$request->get_param('source')];
    }

    private static function candidates(string $source, string $target): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} AS p
             INNER JOIN {$wpdb->postmeta} AS src
                ON src.post_id = p.ID
                AND src.meta_key = %s
                AND TRIM(src.meta_value) <> ''
             WHERE p.post_status = 'publish'
                AND p.post_type IN ('post', 'page')
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} AS dest
                    WHERE dest.post_id = p.ID
                        AND dest.meta_key = %s
                        AND TRIM(dest.meta_value) <> ''
                )",
            $source,
            $target
        ));
    }

    public static function preview(\WP_REST_Request $request): \WP_REST_Response
    {
        [$titleKey, $descriptionKey] = self::keys($request);

        $token = wp_generate_password(48, false, false);

        set_transient(
            self::permissionKey((string) $request->get_param('source')),
            $token,
            15 * MINUTE_IN_SECONDS
        );

        return new \WP_REST_Response([
            'token' => $token,
            'titles' => self::candidates($titleKey, '_seo_tidy_title'),
            'descriptions' => self::candidates(
                $descriptionKey,
                '_seo_tidy_description'
            ),
        ]);
    }

    private static function cleanValue($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim(sanitize_text_field($value));

        // Neimportejam neatrisinatus avota mainigos
        if (
            $value === '' ||
            preg_match('/%%[^%]+%%|%[a-zA-Z_][a-zA-Z0-9_]*%/', $value)
        ) {
            return '';
        }

        return $value;
    }

    public static function importBatch(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        global $wpdb;

        $source = (string) $request->get_param('source');
        $token = $request->get_param('token');
        $stored = get_transient(self::permissionKey($source));

        if (
            $request->get_param('confirmed') !== true ||
            !is_string($token) ||
            !is_string($stored) ||
            $stored === '' ||
            !hash_equals($stored, $token)
        ) {
            return new \WP_Error(
                'seo_tidy_migration_not_confirmed',
                'Migration requires a valid preview and explicit confirmation.',
                ['status' => 403]
            );
        }

        [$titleKey, $descriptionKey] = self::keys($request);
        $cursor = (int) $request->get_param('cursor');

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE ID > %d
                AND post_type IN ('post', 'page')
                AND post_status = 'publish'
             ORDER BY ID ASC
             LIMIT 20",
            $cursor
        ));

        $importedTitles = 0;
        $importedDescriptions = 0;

        foreach ($ids as $id) {
            $id = (int) $id;
            $cursor = $id;

            foreach ([
                [$titleKey, '_seo_tidy_title', 'title'],
                [$descriptionKey, '_seo_tidy_description', 'description'],
            ] as [$sourceKey, $targetKey, $field]) {
                if (trim((string) get_post_meta($id, $targetKey, true)) !== '') {
                    continue;
                }

                $value = self::cleanValue(
                    get_post_meta($id, $sourceKey, true)
                );

                if ($value === '') {
                    continue;
                }

                if (update_post_meta($id, $targetKey, $value)) {
                    if ($field === 'title') {
                        ++$importedTitles;
                    } else {
                        ++$importedDescriptions;
                    }
                }
            }
        }

        if (count($ids) < 20) {
            delete_transient(self::permissionKey($source));
        }

        return new \WP_REST_Response([
            'cursor' => $cursor,
            'processed' => count($ids),
            'done' => count($ids) < 20,
            'importedTitles' => $importedTitles,
            'importedDescriptions' => $importedDescriptions,
        ]);
    }
}