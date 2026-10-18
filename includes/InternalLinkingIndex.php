<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/** Explicit administrator-driven, resumable metadata indexing. */
final class InternalLinkingIndex
{
    private const STATE = 'seo_tidy_link_index_state_v1';
    private const GENERATION = 'seo_tidy_link_index_generation_v1';
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
        $state = self::rawState();
        $generation = self::generation();
        if ($state && ((int) ($state['schema'] ?? 0) !== self::SCHEMA ||
            !empty($state['in_progress']) || $generation === null ||
            (string) ($state['generation'] ?? '') !== $generation)) {
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
        global $wpdb;
        // A monotonic database value survives request-local option caches and races.
        $changed = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,'1','no')
             ON DUPLICATE KEY UPDATE option_value=CAST(option_value AS UNSIGNED)+1",
            self::GENERATION
        ));
        if ($changed === false) {
            // The reader also fails closed when coordination cannot be read.
            self::writeState(['complete' => false, 'stale' => true]);
            return;
        }
        $state = self::rawState();
        $state['stale'] = true;
        $state['complete'] = false;
        self::writeState($state);
    }

    private static function rawState(): array
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::STATE
        ));
        if (!empty($wpdb->last_error)) {
            return ['complete' => false, 'stale' => true];
        }
        $state = maybe_unserialize($value);
        return is_array($state) ? $state : [];
    }

    private static function generation(): ?string
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::GENERATION
        ));
        return empty($wpdb->last_error) && is_string($value) && ctype_digit($value) ? $value : null;
    }

    private static function writeState(array $state): bool
    {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')
             ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload='no'",
            self::STATE, maybe_serialize($state)
        ));
        self::clearStateCache();
        return $result !== false;
    }

    private static function clearStateCache(): void
    {
        wp_cache_delete(self::STATE, 'options');
        wp_cache_delete(self::GENERATION, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    private static function lockName(): string
    {
        global $wpdb;
        return 'seo_tidy_link_' . md5(DB_NAME . ':' . $wpdb->prefix);
    }

    private static function ownsLock(): bool
    {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare(
            'SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', self::lockName()
        )) === '1';
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
        global $wpdb;
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', self::lockName()));
        if ((string) $locked === '0') {
            wp_safe_redirect(add_query_arg('scan_status', 'busy', $returnUrl));
            exit;
        }
        if ((string) $locked !== '1') {
            wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
        }
        $release = static function (): void {
            global $wpdb;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lockName()));
        };
        // wp_die/exit bypass finally; killed/disconnected workers release their DB lock.
        register_shutdown_function($release);
        try {
            $status = self::scanBatch();
        } finally {
            $release();
        }
        wp_safe_redirect(add_query_arg('scan_status', $status, $returnUrl));
        exit;
    }

    private static function scanBatch(): string
    {
        global $wpdb;
        if ($wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,'0','no')",
            self::GENERATION
        )) === false || ($generation = self::generation()) === null) {
            wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
        }
        $state = self::state();
        if (!self::ensureTable()) {
            self::markStale();
            wp_die(esc_html__('Internal linking index table could not be created.', 'seo-tidy'));
        }
        if (!empty($state['complete']) && empty($state['stale']) && empty($_POST['restart'])) {
            return 'complete';
        }
        $rebuild = !empty($_POST['restart']) || !empty($state['stale']) || !empty($state['in_progress']);
        if ($rebuild) {
            $state = [];
        }
        // Persist before any destructive work. A hard interruption leaves this marker.
        $working = array_merge($state, ['complete' => false, 'stale' => true,
            'in_progress' => true, 'generation' => $generation, 'schema' => self::SCHEMA]);
        if (!self::ownsLock() || !self::writeState($working)) {
            wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
        }
        if ($rebuild) {
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
                if (self::deletePostings($id) === false) {
                    self::markStale();
                    wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
                }
                delete_post_meta($id, self::META);
                continue;
            }
            $tokens = InternalLinkingPreview::indexTerms($post);
            if (self::deletePostings($id) === false) {
                self::markStale();
                wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
            }
            // Preserve the same bounded title/topic-weighted vector used by the scorer.
            foreach (array_chunk($tokens, 50, true) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $term => $frequency) {
                    $values[] = $values ? 'SELECT %d,%s,%d' : 'SELECT %d AS post_id,%s AS term,%d AS frequency';
                    array_push($params, $id, (string) $term, max(1, (int) $frequency));
                }
                // Fence writes inside SQL: wpdb can reconnect and lose GET_LOCK.
                $sql = 'INSERT INTO ' . self::table() .
                    ' (post_id,term,frequency) SELECT batch.post_id,batch.term,batch.frequency FROM (' .
                    implode(' UNION ALL ', $values) . ') batch WHERE IS_USED_LOCK(%s)=CONNECTION_ID()';
                $params[] = self::lockName();
                if ($wpdb->query($wpdb->prepare($sql, ...$params)) !== count($chunk)) {
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
            'in_progress' => false,
            'generation' => $generation,
            'schema' => self::SCHEMA,
        ];
        // Publish only if no edit invalidated this batch, on the lock-owning connection.
        $published = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} s INNER JOIN {$wpdb->options} g ON g.option_name=%s
             SET s.option_value=%s WHERE s.option_name=%s AND g.option_value=%s
             AND IS_USED_LOCK(%s)=CONNECTION_ID()",
            self::GENERATION, maybe_serialize($state), self::STATE, $generation, self::lockName()
        ));
        self::clearStateCache();
        if ($published !== 1) {
            self::markStale();
            wp_die(esc_html__('Could not update the link index. The scan has stopped.', 'seo-tidy'));
        }
        return '1';
    }

    private static function deletePostings(int $id)
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() .
            ' WHERE post_id=%d AND IS_USED_LOCK(%s)=CONNECTION_ID()', $id, self::lockName()));
    }

    private static function clearSnapshots(): void
    {
        global $wpdb;
        // Remove only our private, regenerable index snapshots.
        if ($wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() .
            ' WHERE IS_USED_LOCK(%s)=CONNECTION_ID()', self::lockName())) === false ||
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta}
                WHERE meta_key=%s AND IS_USED_LOCK(%s)=CONNECTION_ID()", self::META, self::lockName())) === false ||
            !self::ownsLock()) {
            wp_die(esc_html__('Could not clear the link index. The scan has stopped.', 'seo-tidy'));
        }
    }

    public static function entry(int $postId): array
    {
        $entry = get_post_meta($postId, self::META, true);
        return is_array($entry) ? $entry : [];
    }
}
