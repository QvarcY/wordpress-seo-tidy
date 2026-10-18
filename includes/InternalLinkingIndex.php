<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/** Explicit administrator-driven, resumable metadata indexing. */
final class InternalLinkingIndex
{
    private const STATE = 'seo_tidy_link_index_state_v1';
    private const META = '_seo_tidy_link_index_v1';
    private const SIZE = 25;
    // Snapshot/scoring revision; the existing database table and meta keys are unchanged.
    private const SCHEMA = 5;

    public static function init(): void
    {
        if (!defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
            return;
        }
        add_action('admin_post_seo_tidy_link_scan', [self::class, 'scan']);
        add_action('save_post', [self::class, 'invalidate'], 20, 1);
        add_action('before_delete_post', [self::class, 'invalidate'], 20, 1);
        add_action('set_object_terms', [self::class, 'invalidate'], 20, 1);
        add_action('transition_post_status', [self::class, 'statusChanged'], 20, 3);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'metadataChanged'], 20, 4);
        }
        foreach (['home', 'blog_public', 'permalink_structure'] as $option) {
            add_action('update_option_' . $option, [self::class, 'settingsChanged'], 20, 2);
        }
    }

    public static function state(): array
    {
        $state = get_option(self::STATE, []);
        if (!is_array($state)) {
            return [];
        }
        if ($state && (int) ($state['schema'] ?? 0) !== self::SCHEMA) {
            $state['stale'] = true;
            $state['complete'] = false;
        }
        return $state;
    }

    public static function statusChanged(string $new, string $old, \WP_Post $post): void
    {
        if ($new !== $old) {
            self::invalidate($post->ID);
        }
    }

    public static function metadataChanged($metaId, int $postId, string $key, $value): void
    {
        if (in_array($key, ['_yoast_wpseo_meta-robots-noindex', 'rank_math_robots',
            '_yoast_wpseo_canonical', 'rank_math_canonical_url', '_seo_tidy_smart_topics_v1'], true)) {
            self::invalidate($postId);
        }
    }

    public static function settingsChanged($old, $value): void
    {
        if ($old !== $value) {
            self::markStale();
        }
    }

    private static function markStale(): void
    {
        $state = self::state();
        $state['stale'] = true;
        update_option(self::STATE, $state, false);
    }

    /** Keep retrieval, document frequencies and scoring on the same bounded query. */
    public static function queryTerms(array $terms): array
    {
        return array_slice(array_values(array_unique(array_filter($terms,
            static fn($term): bool => is_string($term) && $term !== ''))), 0, 64);
    }

    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'seo_tidy_link_terms';
    }

    private static function ensureTable(): bool
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collation = $wpdb->get_charset_collate();
        // Keep Latvian words with and without diacritics as separate terms
        $charset = $wpdb->charset === 'utf8' ? 'utf8' : 'utf8mb4';
        $termCollation = $charset . '_bin';
        dbDelta("CREATE TABLE {$table} (
            post_id bigint(20) unsigned NOT NULL,
            term varchar(191) CHARACTER SET {$charset} COLLATE {$termCollation} NOT NULL,
            frequency int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (post_id,term),
            KEY term (term)
        ) {$collation};");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return false;
        }
        $column = $wpdb->get_row($wpdb->prepare("SHOW FULL COLUMNS FROM {$table} LIKE %s", 'term'), ARRAY_A);
        if (!is_array($column)) {
            return false;
        }
        // dbDelta does not migrate an existing column's collation
        if (($column['Collation'] ?? '') !== $termCollation) {
            return $wpdb->query("ALTER TABLE {$table} MODIFY term varchar(191)
                CHARACTER SET {$charset} COLLATE {$termCollation} NOT NULL") !== false;
        }
        return true;
    }

    public static function corpusStats(): array
    {
        $state = self::state();
        if (empty($state['complete']) || !empty($state['stale'])) {
            return ['count' => 0, 'avgdl' => 1.0];
        }
        global $wpdb;
        $table = self::table();
        // One row per term/post; sum of frequencies is the document length.
        $row = $wpdb->get_row("SELECT COUNT(*) AS n, AVG(x.length) AS avgdl FROM
            (SELECT post_id, SUM(frequency) AS length FROM {$table} GROUP BY post_id) x", ARRAY_A);
        if (!is_array($row) || !empty($wpdb->last_error)) {
            self::markStale();
            return ['count' => 0, 'avgdl' => 1.0];
        }
        return ['count' => (int) ($row['n'] ?? 0), 'avgdl' => max(1.0, (float) ($row['avgdl'] ?? 1.0))];
    }

    public static function documentFrequency(array $terms): array
    {
        $state = self::state();
        if (empty($state['complete']) || !empty($state['stale'])) {
            return [];
        }
        $terms = self::queryTerms($terms);
        if (!$terms) {
            return [];
        }
        global $wpdb;
        $table = self::table();
        $placeholders = implode(',', array_fill(0, count($terms), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT term, COUNT(*) AS df FROM {$table} WHERE term IN ({$placeholders}) GROUP BY term",
            ...$terms
        ), ARRAY_A);
        if (!is_array($rows) || !empty($wpdb->last_error)) {
            self::markStale();
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['term']] = (int) $row['df'];
        }
        return $result;
    }

    public static function candidateIds(array $terms, int $sourceId, int $limit = 30): array
    {
        $state = self::state();
        if (empty($state['complete']) || !empty($state['stale'])) {
            return [];
        }
        $terms = self::queryTerms($terms);
        if (!$terms) {
            return [];
        }
        global $wpdb;
        $table = self::table();
        $placeholders = implode(',', array_fill(0, count($terms), '%s'));
        $params = array_merge($terms, [$sourceId, max(1, min(30, $limit))]);
        $sql = "SELECT t.post_id FROM {$table} t
                INNER JOIN {$wpdb->posts} p ON p.ID=t.post_id
                WHERE t.term IN ({$placeholders}) AND t.post_id <> %d
                  AND p.post_type IN ('post','page') AND p.post_status='publish'
                GROUP BY t.post_id ORDER BY COUNT(*) DESC, t.post_id ASC LIMIT %d";
        $ids = $wpdb->get_col($wpdb->prepare($sql, ...$params));
        if (!is_array($ids) || !empty($wpdb->last_error)) {
            self::markStale();
            return [];
        }
        return array_map('intval', $ids);
    }

    public static function invalidate(int $postId): void
    {
        if (in_array(get_post_type($postId), ['post', 'page'], true) || self::entry($postId)) {
            // The whole index becomes invalid; a rebuild clears all postings.
            delete_post_meta($postId, self::META);
            self::markStale();
        }
    }

    public static function scan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        check_admin_referer('seo_tidy_link_scan');
        $returnUrl = isset($_POST['return_to']) && $_POST['return_to'] === 'dashboard'
            ? admin_url('admin.php?page=seo-tidy&section=linking')
            : admin_url('admin.php?page=seo-tidy-linking-preview');
        $state = self::state();
        if (!self::ensureTable()) {
            self::markStale();
            wp_die(esc_html__('Internal linking index table could not be created.', 'seo-tidy'));
        }
        if (!empty($_POST['restart'])) {
            $state = [];
            self::clearSnapshots();
        }
        if (!empty($state['complete']) && empty($state['stale']) && empty($_POST['restart'])) {
            wp_safe_redirect(add_query_arg('scan_status', 'complete', $returnUrl));
            exit;
        }
        if (!empty($state['stale'])) {
            $state = [];
            self::clearSnapshots();
        }
        $last = isset($state['last_id']) ? absint($state['last_id']) : 0;
        // Explicit cursor avoids offset shifts when articles change.
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('post','page')
             AND post_status = 'publish' ORDER BY ID ASC LIMIT %d",
            $last, self::SIZE + 1
        ));
        if (!is_array($ids) || !empty($wpdb->last_error)) {
            self::markStale();
            wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
        }
        $hasMore = count($ids) > self::SIZE;
        if ($hasMore) {
            $ids = array_slice($ids, 0, self::SIZE);
        }
        foreach ($ids as $id) {
            $id = absint($id);
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            if (!InternalLinkingPreview::eligible($post)) {
                if ($wpdb->delete(self::table(), ['post_id' => $id], ['%d']) === false) {
                    self::markStale();
                    wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
                }
                delete_post_meta($id, self::META);
                continue;
            }
            $tokens = InternalLinkingPreview::indexTerms($post);
            if ($wpdb->delete(self::table(), ['post_id' => $id], ['%d']) === false) {
                self::markStale();
                wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
            }
            // Preserve the same bounded title/topic-weighted vector used by the scorer.
            foreach (array_chunk($tokens, 50, true) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $term => $frequency) {
                    $values[] = '(%d,%s,%d)';
                    array_push($params, $id, (string) $term, max(1, (int) $frequency));
                }
                $sql = 'INSERT INTO ' . self::table() .
                    ' (post_id,term,frequency) VALUES ' . implode(',', $values);
                if ($wpdb->query($wpdb->prepare($sql, ...$params)) === false) {
                    self::markStale();
                    wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
                }
            }
            update_post_meta($id, self::META, ['version' => 2, 'updated' =>
                (int) get_post_modified_time('U', true, $post)]);
        }
        $processed = isset($state['processed']) ? absint($state['processed']) : 0;
        $state = [
            'last_id' => $ids ? (int) end($ids) : $last,
            'processed' => $processed + count($ids),
            'complete' => !$hasMore,
            'stale' => false,
            'schema' => self::SCHEMA,
        ];
        update_option(self::STATE, $state, false);
        wp_safe_redirect(add_query_arg('scan_status', '1', $returnUrl));
        exit;
    }

    private static function clearSnapshots(): void
    {
        global $wpdb;
        // A failed clear must never leave a previously complete scan usable.
        self::markStale();
        // Remove only our private, regenerable index snapshots.
        if ($wpdb->query('DELETE FROM ' . self::table()) === false ||
            $wpdb->delete($wpdb->postmeta, ['meta_key' => self::META], ['%s']) === false) {
            wp_die(esc_html__('Could not clear the link index. The scan has stopped.', 'seo-tidy'));
        }
    }

    public static function entry(int $postId): array
    {
        $entry = get_post_meta($postId, self::META, true);
        return is_array($entry) ? $entry : [];
    }
}
