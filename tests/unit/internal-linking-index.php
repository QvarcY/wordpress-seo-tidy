<?php

declare(strict_types=1);

/** Deterministic PHP contract tests. The database below is a stub, not MySQL. */
namespace {
    $temporary = sys_get_temp_dir() . '/seo-tidy-index-' . bin2hex(random_bytes(6));
    mkdir($temporary . '/wp-admin/includes', 0700, true);
    file_put_contents($temporary . '/wp-admin/includes/upgrade.php', '<?php');
    define('ABSPATH', $temporary . '/');
    define('ARRAY_A', 'ARRAY_A');
    define('DB_NAME', 'standalone_index_contracts');
    define('SEO_TIDY_INTERNAL_LINKING_PREVIEW', true);
    register_shutdown_function(static function () use ($temporary): void {
        unlink($temporary . '/wp-admin/includes/upgrade.php');
        rmdir($temporary . '/wp-admin/includes');
        rmdir($temporary . '/wp-admin');
        rmdir($temporary);
    });

    final class WP_Post
    {
        public function __construct(
            public int $ID,
            public string $post_type = 'post',
            public string $post_status = 'publish',
            public bool $eligible = true,
            public array $terms = ['accra' => 3, 'museums' => 2]
        ) {}
    }

    final class IndexRedirect extends \RuntimeException {}
    $options = [];
    $metadata = [];
    $posts = [];
    $hooks = [];
    $checks = 0;

    function get_option(string $key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
    function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
    function maybe_unserialize($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return $decoded !== false || $value === 'b:0;' ? $decoded : $value;
    }
    function wp_cache_delete($key, $group = ''): bool { return true; }
    function update_option(string $key, $value, $autoload = null): bool
    {
        $GLOBALS['options'][$key] = $value;
        return true;
    }
    function get_post_meta(int $id, string $key, bool $single = false)
    {
        return $GLOBALS['metadata'][$id][$key] ?? '';
    }
    function delete_post_meta(int $id, string $key): bool
    {
        unset($GLOBALS['metadata'][$id][$key]);
        return true;
    }
    function update_post_meta(int $id, string $key, $value): bool
    {
        $GLOBALS['metadata'][$id][$key] = $value;
        return true;
    }
    function get_post_type(int $id) { return $GLOBALS['posts'][$id]->post_type ?? false; }
    function get_post(int $id) { return $GLOBALS['posts'][$id] ?? null; }
    function get_post_modified_time($format, $gmt, $post): int { return 100; }
    function add_action($hook, $callback, $priority = 10, $arguments = 1): void
    {
        $GLOBALS['hooks'][$hook] = [$callback, $priority, $arguments];
    }
    function current_user_can($capability): bool { return true; }
    function check_admin_referer($action): void {}
    function admin_url($path): string { return 'https://example.test/wp-admin/' . $path; }
    function add_query_arg($key, $value, $url): string { return $url . '&' . $key . '=' . $value; }
    function wp_safe_redirect($url): void { throw new IndexRedirect($url); }
    function wp_die($message): void { throw new \RuntimeException($message); }
    function esc_html__($message, $domain): string { return $message; }
    function absint($value): int { return abs((int) $value); }
    function dbDelta($sql): void {}

    final class IndexDatabaseStub
    {
        public string $prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $options = 'wp_options';
        public string $charset = 'utf8mb4';
        public string $last_error = '';
        public array $postings = [];
        public array $queries = [];
        public string $failure = '';
        private string $lock = '';

        public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
        public function esc_like(string $value): string { return $value; }
        public function prepare(string $sql, ...$params): string
        {
            return preg_replace_callback('/%[sd]/', static function ($match) use (&$params): string {
                $value = array_shift($params);
                return $match[0] === '%d' ? (string) (int) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            }, $sql);
        }
        private function failed(string $operation): bool
        {
            $this->last_error = $this->failure === $operation ? 'simulated database failure' : '';
            return $this->last_error !== '';
        }
        private function terms(string $sql): array
        {
            preg_match('/(?:t\.)?term IN \((.*?)\)/s', $sql, $match);
            preg_match_all("/'((?:[^']|'')*)'/u", $match[1] ?? '', $terms);
            return array_map(static fn($term) => str_replace("''", "'", $term), $terms[1]);
        }
        public function get_var(string $sql)
        {
            $this->queries[] = $sql;
            $this->last_error = '';
            if (preg_match("/^SELECT option_value FROM wp_options WHERE option_name='([^']+)'$/", $sql, $match)) {
                return array_key_exists($match[1], $GLOBALS['options'])
                    ? maybe_serialize($GLOBALS['options'][$match[1]]) : null;
            }
            if (preg_match("/^SELECT (GET_LOCK|RELEASE_LOCK|IS_USED_LOCK)\('([^']+)'/", $sql, $match)) {
                if ($match[1] === 'GET_LOCK') {
                    $this->lock = $match[2];
                    return '1';
                }
                $owned = $this->lock === $match[2];
                if ($match[1] === 'RELEASE_LOCK' && $owned) {
                    $this->lock = '';
                }
                return $owned ? '1' : null;
            }
            $this->failed('table');
            return $this->last_error === '' ? 'wp_seo_tidy_link_terms' : null;
        }
        public function get_row(string $sql, $format)
        {
            $this->queries[] = $sql;
            if (str_contains($sql, 'SHOW FULL COLUMNS')) {
                $this->failed('column');
                return $this->last_error === '' ? ['Collation' => 'utf8mb4_bin'] : null;
            }
            if ($this->failed('stats')) {
                return null;
            }
            $lengths = array_map('array_sum', array_filter($this->postings));
            return ['n' => count($lengths), 'avgdl' => $lengths ? array_sum($lengths) / count($lengths) : null];
        }
        public function get_results(string $sql, $format)
        {
            $this->queries[] = $sql;
            if ($this->failed('df')) {
                return null;
            }
            $result = [];
            foreach ($this->terms($sql) as $term) {
                $count = count(array_filter($this->postings, static fn($bag) => isset($bag[$term])));
                if ($count) {
                    $result[] = ['term' => $term, 'df' => $count];
                }
            }
            return $result;
        }
        public function get_col(string $sql): array
        {
            $this->queries[] = $sql;
            if (str_contains($sql, 'SELECT ID FROM')) {
                if ($this->failed('cursor')) {
                    return [];
                }
                preg_match('/ID > (\d+)/', $sql, $cursor);
                preg_match('/LIMIT (\d+)/', $sql, $limit);
                $ids = [];
                foreach ($GLOBALS['posts'] as $post) {
                    if ($post->ID > (int) $cursor[1] && in_array($post->post_type, ['post', 'page'], true) &&
                        $post->post_status === 'publish') {
                        $ids[] = $post->ID;
                    }
                }
                sort($ids);
                return array_slice($ids, 0, (int) $limit[1]);
            }
            if ($this->failed('candidates')) {
                return [];
            }
            preg_match('/t.post_id <> (\d+)/', $sql, $source);
            preg_match('/LIMIT (\d+)/', $sql, $limit);
            $terms = $this->terms($sql);
            $matches = [];
            foreach ($this->postings as $id => $bag) {
                $post = get_post($id);
                if (!$post || $id === (int) $source[1] || !in_array($post->post_type, ['post', 'page'], true) ||
                    $post->post_status !== 'publish') {
                    continue;
                }
                $count = count(array_intersect(array_keys($bag), $terms));
                if ($count) {
                    $matches[$id] = $count;
                }
            }
            uksort($matches, static fn($a, $b) => $matches[$b] <=> $matches[$a] ?: $a <=> $b);
            return array_slice(array_keys($matches), 0, (int) $limit[1]);
        }
        public function delete(string $table, array $where, array $format)
        {
            $this->queries[] = ['delete', $table, $where];
            if ($this->failed($table === $this->postmeta ? 'clear_meta' : 'delete_post')) {
                return false;
            }
            if ($table === $this->postmeta) {
                foreach ($GLOBALS['metadata'] as $id => $data) {
                    unset($GLOBALS['metadata'][$id][$where['meta_key']]);
                }
            } else {
                unset($this->postings[$where['post_id']]);
            }
            return 1;
        }
        public function query(string $sql)
        {
            $this->queries[] = $sql;
            $this->last_error = '';
            $quoted = "'((?:[^']|'')*)'";
            if (preg_match('/^INSERT(?: IGNORE)? INTO wp_options/', $sql)) {
                preg_match('/VALUES \(' . $quoted . ',' . $quoted . ",\s*'no'\)/", $sql, $match);
                if (!$match) {
                    throw new \RuntimeException('Unsupported option INSERT in index stub');
                }
                $key = str_replace("''", "'", $match[1]);
                $exists = array_key_exists($key, $GLOBALS['options']);
                if (str_contains($sql, 'CAST(option_value AS UNSIGNED)+1')) {
                    $GLOBALS['options'][$key] = $exists ? (string) ((int) $GLOBALS['options'][$key] + 1) : '1';
                } elseif (!str_contains($sql, 'INSERT IGNORE') || !$exists) {
                    $GLOBALS['options'][$key] = maybe_unserialize(str_replace("''", "'", $match[2]));
                } else {
                    return 0;
                }
                return $exists ? 2 : 1;
            }
            if (str_starts_with($sql, 'UPDATE wp_options s INNER JOIN wp_options g')) {
                preg_match('/g.option_name=' . $quoted . '\s+SET s.option_value=' . $quoted .
                    ' WHERE s.option_name=' . $quoted . ' AND g.option_value=' . $quoted .
                    '\s+AND IS_USED_LOCK\(' . $quoted . '\)=CONNECTION_ID\(\)/s', $sql, $match);
                if (!$match) {
                    throw new \RuntimeException('Unsupported option publication in index stub');
                }
                if ($this->lock !== $match[5] || (string) ($GLOBALS['options'][$match[1]] ?? '') !== $match[4]) {
                    return 0;
                }
                $GLOBALS['options'][$match[3]] = maybe_unserialize(str_replace("''", "'", $match[2]));
                return 1;
            }
            if (str_contains($sql, 'IS_USED_LOCK(') &&
                !str_contains($sql, "IS_USED_LOCK('" . $this->lock . "')=CONNECTION_ID()")) {
                return 0;
            }
            if (str_starts_with($sql, 'DELETE FROM')) {
                if (str_starts_with($sql, 'DELETE FROM wp_postmeta')) {
                    if ($this->failed('clear_meta')) {
                        return false;
                    }
                    foreach ($GLOBALS['metadata'] as $id => $data) {
                        unset($GLOBALS['metadata'][$id]['_seo_tidy_link_index_v1']);
                    }
                    return 1;
                }
                $postDelete = preg_match('/WHERE post_id=(\d+)/', $sql, $post);
                if ($this->failed($postDelete ? 'delete_post' : 'clear_terms')) {
                    return false;
                }
                if ($postDelete) {
                    unset($this->postings[(int) $post[1]]);
                } else {
                    $this->postings = [];
                }
                return 1;
            }
            if ($this->failed('insert')) {
                return false;
            }
            preg_match_all("/SELECT (\d+)(?: AS post_id)?,'((?:[^']|'')*)'(?: AS term)?,(\d+)(?: AS frequency)?/u", $sql, $values, PREG_SET_ORDER);
            if (!$values) {
                throw new \RuntimeException('Unsupported posting INSERT in index stub');
            }
            foreach ($values as $row) {
                $id = (int) $row[1];
                $this->postings[$id][str_replace("''", "'", $row[2])] = (int) $row[3];
            }
            return count($values);
        }
    }

    function indexCheck(string $name, bool $condition): void
    {
        if (!$condition) {
            fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL);
            exit(1);
        }
        $GLOBALS['checks']++;
        echo 'PASS: ' . $name . PHP_EOL;
    }
    function resetIndex(bool $ready = true): void
    {
        $GLOBALS['options'] = ['seo_tidy_link_index_generation_v1' => '0', 'seo_tidy_link_index_state_v1' => [
            'complete' => $ready, 'stale' => false, 'schema' => 5, 'generation' => '0',
        ]];
        $GLOBALS['metadata'] = [];
        $GLOBALS['posts'] = [];
        $GLOBALS['wpdb'] = new IndexDatabaseStub();
        $_POST = [];
    }
    function runIndexScan(): string
    {
        try {
            \QvarcY\SeoTidy\InternalLinkingIndex::scan();
        } catch (IndexRedirect $redirect) {
            return 'redirect';
        } catch (\RuntimeException $error) {
            return $error->getMessage();
        }
        return 'unexpected return';
    }
}

namespace QvarcY\SeoTidy {
    /** Scan tests isolate the public eligibility/vector contract from the actual preview. */
    final class InternalLinkingPreview
    {
        public static function eligible(\WP_Post $post): bool { return $post->eligible; }
        public static function indexTerms(\WP_Post $post): array { return $post->terms; }
    }
}

namespace {
    require_once dirname(__DIR__, 2) . '/includes/InternalLinkingIndex.php';
    use QvarcY\SeoTidy\InternalLinkingIndex as Index;

    resetIndex();
    Index::init();
    foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
        indexCheck($hook . ' observes late provider changes', ($hooks[$hook][2] ?? 0) === 4);
    }
    foreach (['home', 'blog_public', 'permalink_structure'] as $option) {
        indexCheck($option . ' setting registers invalidation', ($hooks['update_option_' . $option][2] ?? 0) === 2);
    }
    Index::settingsChanged('old', 'new');
    indexCheck('changed site URL/indexability/permalink setting invalidates scan', !empty(Index::state()['stale']));
    resetIndex();
    Index::settingsChanged('unchanged', 'unchanged');
    indexCheck('unchanged setting does not invalidate scan', empty(Index::state()['stale']));

    $query = array_merge(['', null, 7, 'accra', 'accra'], array_map(static fn($i) => 'term' . $i, range(1, 80)));
    $bounded = Index::queryTerms($query);
    indexCheck('query terms are nonempty unique strings capped at 64', count($bounded) === 64 &&
        $bounded[0] === 'accra' && end($bounded) === 'term63');

    for ($id = 1; $id <= 40; $id++) {
        $posts[$id] = new WP_Post($id);
        $wpdb->postings[$id] = ['later' => 1];
    }
    $wpdb->postings[2]['first'] = 1;
    $posts[3]->post_status = 'draft';
    $posts[4]->post_type = 'attachment';
    $terms = ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'later'];
    $candidates = Index::candidateIds($terms, 1);
    indexCheck('a specific ninth query term can retrieve candidates', in_array(5, $candidates, true));
    indexCheck('candidate cap remains 30', count($candidates) === 30);
    indexCheck('self/draft/unsupported type excluded before candidate cap', !array_intersect([1, 3, 4], $candidates));
    indexCheck('overlap then ID determines candidate order', array_slice($candidates, 0, 3) === [2, 5, 6]);
    indexCheck('requested smaller candidate limit is respected', Index::candidateIds($terms, 1, 2) === [2, 5]);
    $frequencies = Index::documentFrequency($terms);
    $stats = Index::corpusStats();
    indexCheck('DF uses complete postings rather than 30 returned candidates', $frequencies['later'] === 40);
    indexCheck('corpus statistics use stored frequencies', $stats === ['count' => 40, 'avgdl' => 1.025]);

    foreach (['candidates', 'df', 'stats'] as $failure) {
        resetIndex();
        $wpdb->failure = $failure;
        $result = $failure === 'candidates' ? Index::candidateIds(['accra'], 1) :
            ($failure === 'df' ? Index::documentFrequency(['accra']) : Index::corpusStats());
        indexCheck($failure . ' database failure yields no usable evidence', $result ===
            ($failure === 'stats' ? ['count' => 0, 'avgdl' => 1.0] : []));
        indexCheck($failure . ' database failure invalidates complete state', !empty(Index::state()['stale']));
        $rankingReads = static fn(array $queries): int => count(array_filter($queries,
            static fn($sql): bool => is_string($sql) && str_contains($sql, 'wp_seo_tidy_link_terms')));
        $count = $rankingReads($wpdb->queries);
        Index::candidateIds(['accra'], 1);
        Index::documentFrequency(['accra']);
        Index::corpusStats();
        indexCheck($failure . ' stale state blocks subsequent ranking reads', $rankingReads($wpdb->queries) === $count);
    }

    foreach (['_yoast_wpseo_meta-robots-noindex', 'rank_math_robots', '_yoast_wpseo_canonical',
        'rank_math_canonical_url', '_seo_tidy_smart_topics_v1'] as $key) {
        resetIndex();
        $posts[1] = new WP_Post(1);
        Index::metadataChanged(1, 1, $key, []);
        indexCheck($key . ' metadata change makes the index stale', !empty(Index::state()['stale']));
    }
    resetIndex();
    $posts[1] = new WP_Post(1);
    Index::metadataChanged(1, 1, '_unrelated', 'value');
    Index::metadataChanged(2, 1, '_seo_tidy_link_index_v1', []);
    indexCheck('unrelated metadata and own snapshots do not invalidate scans', empty(Index::state()['stale']));
    $posts[1]->post_type = 'product';
    $metadata[1]['_seo_tidy_link_index_v1'] = ['version' => 2];
    Index::invalidate(1);
    indexCheck('previously indexed post changing to unsupported type invalidates index',
        !empty(Index::state()['stale']) && Index::entry(1) === []);

    resetIndex();
    $options['seo_tidy_link_index_state_v1']['schema'] = 4;
    indexCheck('older scoring snapshot requires rebuild without changing storage keys',
        !Index::state()['complete'] && !empty(Index::state()['stale']));
    $options['seo_tidy_link_index_state_v1']['complete'] = false;
    $options['seo_tidy_link_index_state_v1']['last_id'] = 25;
    indexCheck('partially built older scoring snapshots cannot resume with mixed vectors',
        !empty(Index::state()['stale']));

    resetIndex(false);
    for ($id = 1; $id <= 26; $id++) {
        $posts[$id] = new WP_Post($id);
    }
    $posts[2]->eligible = false;
    $wpdb->postings[2] = ['old' => 1];
    $metadata[2]['_seo_tidy_link_index_v1'] = ['version' => 2];
    indexCheck('first scan redirects after bounded work', runIndexScan() === 'redirect');
    indexCheck('first scan processes exactly 25 IDs and retains cursor', Index::state()['last_id'] === 25 &&
        Index::state()['processed'] === 25 && !Index::state()['complete']);
    indexCheck('scanner uses eligibility contract and removes excluded snapshots/postings',
        !isset($wpdb->postings[2]) && Index::entry(2) === []);
    indexCheck('stored vector equals authoritative preview vector', $wpdb->postings[1] === $posts[1]->terms);
    indexCheck('second scan completes from saved cursor', runIndexScan() === 'redirect' &&
        Index::state()['processed'] === 26 && Index::state()['complete']);

    foreach (['clear_terms', 'clear_meta', 'cursor', 'delete_post', 'insert'] as $failure) {
        resetIndex();
        $posts[1] = new WP_Post(1);
        $wpdb->postings[1] = ['obsolete' => 1];
        $metadata[1]['_seo_tidy_link_index_v1'] = ['version' => 2];
        $wpdb->failure = $failure;
        $_POST = ['restart' => '1'];
        $result = runIndexScan();
        indexCheck($failure . ' scan failure stops visibly', $result !== 'redirect' && str_contains($result, 'stopped'));
        indexCheck($failure . ' scan failure cannot expose previous complete state', !empty(Index::state()['stale']) &&
            Index::candidateIds(['obsolete'], 2) === []);
    }

    echo 'Executed ' . $checks . ' deterministic index contract assertions (stub database; no WordPress/MySQL integration).' . PHP_EOL;
}
