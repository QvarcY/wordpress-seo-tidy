<?php
/**
 * Opt-in contracts against a disposable, explicitly identified WordPress/MySQL site.
 * No WordPress doubles are used. Never point this runner at an existing website.
 */
declare(strict_types=1);

use QvarcY\SeoTidy\InternalLinkingIndex as Index;
use QvarcY\SeoTidy\InternalLinkingPreview as Preview;
use QvarcY\SeoTidy\SmartTopics;

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

function phase_c_path(string $path): string
{
    return strtolower(rtrim(str_replace('\\', '/', $path), '/'));
}

$environmentRoot = (string) getenv('SEO_TIDY_PHASE_C_ENV_ROOT');
$wordpressRoot = (string) getenv('SEO_TIDY_PHASE_C_WP_ROOT');
$expectedRoot = dirname(__DIR__, 3) . '/seo-tidy-phase-c-env';
if (getenv('SEO_TIDY_PHASE_C_ISOLATED') !== '1' ||
    phase_c_path($environmentRoot) !== phase_c_path($expectedRoot) ||
    phase_c_path($wordpressRoot) !== phase_c_path($expectedRoot . '/wordpress') ||
    !is_file($environmentRoot . '/isolation-marker.json') ||
    !is_file($wordpressRoot . '/wp-load.php')) {
    fwrite(STDERR, "Refusing bootstrap: explicitly marked disposable Phase C environment required.\n");
    exit(2);
}

require $wordpressRoot . '/wp-load.php';
$home = wp_parse_url(home_url('/'));
if (!defined('SEO_TIDY_PHASE_C_ISOLATED') || SEO_TIDY_PHASE_C_ISOLATED !== true ||
    !defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'local' ||
    wp_get_environment_type() !== 'local' || DB_NAME !== 'seo_tidy_phase_c' ||
    DB_HOST !== '127.0.0.1:33929' || ($home['host'] ?? '') !== '127.0.0.1' ||
    ($home['port'] ?? 0) !== 8896 || !defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') ||
    SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true || !class_exists(Index::class) ||
    !class_exists(Preview::class)) {
    fwrite(STDERR, "Refusing mutation: isolated database, site, plugin and environment guards failed.\n");
    exit(2);
}

$mode = $argv[1] ?? 'contracts';
$started = microtime(true);
$checks = $failures = 0;
$results = [];
$details = [];
$table = $wpdb->prefix . 'seo_tidy_link_terms';
$admin = get_user_by('login', 'phasec_admin');
if (!$admin || !user_can($admin, 'manage_options')) {
    fwrite(STDERR, "Disposable installation administrator phasec_admin is required.\n");
    exit(2);
}

function phase_c_check(bool $condition, string $description): void
{
    global $checks, $failures, $results;
    ++$checks;
    if (!$condition) {
        ++$failures;
    }
    $results[] = ['pass' => $condition, 'description' => $description];
    echo ($condition ? 'PASS ' : 'FAIL ') . $description . PHP_EOL;
}

function phase_c_json(array $result, string $name): void
{
    $json = wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo 'PHASE_C_JSON ' . $json . PHP_EOL;
    $directory = (string) getenv('SEO_TIDY_PHASE_C_LOG_DIR');
    if ($directory !== '' && is_dir($directory)) {
        file_put_contents($directory . '/' . $name . '.json', $json . PHP_EOL);
    }
}

function phase_c_suggestions(int $source): array
{
    return Preview::suggestionsForPost(get_post($source));
}

function phase_c_has(array $suggestions, int $target): bool
{
    foreach ($suggestions as $item) {
        if ($item['post']->ID === $target) {
            return true;
        }
    }
    return false;
}

function phase_c_post(string $slug, string $title, string $content, array $topics = [], array $extra = []): int
{
    global $admin;
    $id = wp_insert_post(array_merge([
        'post_title' => $title, 'post_name' => 'phase-c-' . strtolower($slug),
        'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'post',
        'post_author' => $admin->ID,
    ], $extra), true);
    if (is_wp_error($id)) {
        throw new RuntimeException('Fixture creation failed: ' . $id->get_error_message());
    }
    update_post_meta($id, '_seo_tidy_phase_c_fixture', 'contracts');
    wp_set_object_terms($id, [], 'category');
    if ($topics) {
        update_post_meta($id, '_seo_tidy_smart_topics_v1', [
            'version' => 1, 'approved' => $topics, 'rejected' => ['unrelated fixture topic'],
        ]);
    }
    return (int) $id;
}

/** The actual admin-post callback exits; each scan therefore uses another PHP process. */
function phase_c_rebuild(): array
{
    $runs = [];
    for ($batch = 0; $batch < 100; ++$batch) {
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, __FILE__, 'scan');
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start isolated scan process.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !preg_match('/^PHASE_C_JSON (.+)$/m', $output, $match)) {
            throw new RuntimeException('Scan child failed: ' . trim($output . ' ' . $error));
        }
        $result = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $runs[] = $result;
        if (!empty($result['fatal']) || !empty($result['database_error'])) {
            throw new RuntimeException('Scan reported failure: ' . wp_json_encode($result));
        }
        // Child scans update options and postmeta in another WordPress process.
        // Drop this disposable runner's request-local cache before observing them.
        wp_cache_flush();
        if (!empty($result['state']['complete']) && empty($result['state']['stale'])) {
            return $runs;
        }
    }
    throw new RuntimeException('Scan did not finish within 100 batches.');
}

if ($mode === 'scan') {
    wp_set_current_user((int) $admin->ID);
    $_REQUEST['_wpnonce'] = wp_create_nonce('seo_tidy_link_scan');
    $_POST['return_to'] = 'dashboard';
    if (in_array('--restart', $argv, true)) {
        $_POST['restart'] = '1';
    }
    $redirect = null;
    add_filter('wp_redirect', static function ($location) use (&$redirect) {
        $redirect = $location;
        return $location;
    });
    $queriesBefore = $wpdb->num_queries;
    register_shutdown_function(static function () use ($started, $queriesBefore, &$redirect): void {
        global $wpdb;
        $error = error_get_last();
        $fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
            ? $error['message'] : null;
        phase_c_json([
            'mode' => 'scan', 'pid' => getmypid(), 'seconds' => microtime(true) - $started,
            'queries' => $wpdb->num_queries - $queriesBefore, 'peak_bytes' => memory_get_peak_usage(true),
            'state' => Index::state(), 'redirect' => $redirect, 'database_error' => $wpdb->last_error,
            'fatal' => $fatal,
        ], 'scan-' . getmypid());
    });
    do_action('admin_post_seo_tidy_link_scan');
    fwrite(STDERR, "Scan action unexpectedly returned.\n");
    exit(1);
}

if ($mode !== 'contracts') {
    fwrite(STDERR, "Supported modes: contracts, scan [--restart].\n");
    exit(2);
}

final class PhaseCDie extends RuntimeException {}

try {
    wp_set_current_user((int) $admin->ID);
    phase_c_check(has_action('admin_post_seo_tidy_link_scan', [Index::class, 'scan']) !== false,
        'Active plugin registers the real scan action');
    phase_c_check(class_exists('WP_HTML_Tag_Processor'), 'Real WordPress HTML Tag Processor is available');
    $details['versions'] = [
        'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION, 'database' => $wpdb->db_version(),
        'database_server' => $wpdb->get_var('SELECT VERSION()'), 'charset' => $wpdb->charset,
    ];

    // This table contains only disposable, regenerable experimental index data.
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
    delete_option('seo_tidy_link_index_state_v1');
    $ensure = new ReflectionMethod(Index::class, 'ensureTable');
    $ensure->setAccessible(true);
    phase_c_check($ensure->invoke(null) === true, 'Real dbDelta creates the absent index table');
    $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM {$table}", ARRAY_A);
    $columnsByName = array_column($columns, null, 'Field');
    $binaryCollation = ($wpdb->charset === 'utf8' ? 'utf8' : 'utf8mb4') . '_bin';
    phase_c_check(($columnsByName['term']['Collation'] ?? '') === $binaryCollation,
        'New term column uses the required binary collation');
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
    $primary = array_values(array_filter($indexes, static fn($row) => $row['Key_name'] === 'PRIMARY'));
    phase_c_check(array_column($primary, 'Column_name') === ['post_id', 'term'],
        'Real schema has composite post_id/term primary key');
    phase_c_check(in_array('term', array_column($indexes, 'Key_name'), true), 'Real schema has term retrieval index');
    $wpdb->query("ALTER TABLE {$table} MODIFY term varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    $wpdb->insert($table, ['post_id' => 4294000001, 'term' => 'migration-preserved', 'frequency' => 7]);
    phase_c_check($ensure->invoke(null) === true, 'Existing nonbinary term column migrates successfully');
    $migrated = $wpdb->get_row("SHOW FULL COLUMNS FROM {$table} LIKE 'term'", ARRAY_A);
    phase_c_check(($migrated['Collation'] ?? '') === $binaryCollation, 'Migration changes actual term collation to binary');
    phase_c_check((int) $wpdb->get_var("SELECT frequency FROM {$table} WHERE term='migration-preserved'") === 7,
        'Collation migration preserves an existing row and frequency');
    $one = $wpdb->insert($table, ['post_id' => 4294000001, 'term' => 'mājas', 'frequency' => 1]);
    $two = $wpdb->insert($table, ['post_id' => 4294000001, 'term' => 'majas', 'frequency' => 2]);
    phase_c_check($one === 1 && $two === 1 && (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE post_id=4294000001 AND term='mājas'"
    ) === 1, 'Binary term identity distinguishes mājas from majas in actual SQL');
    $wpdb->query("DELETE FROM {$table}");
    $details['schema'] = ['columns' => $columns, 'indexes' => $indexes, 'migrated_term' => $migrated];

    // Remove only this runner's previous fixtures, making a rerun deterministic.
    $old = get_posts(['post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1,
        'meta_key' => '_seo_tidy_phase_c_fixture', 'meta_value' => 'contracts', 'fields' => 'ids']);
    foreach ($old as $id) {
        wp_delete_post((int) $id, true);
    }
    $fixtures = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/internal-linking-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
    $ids = [];
    foreach ($fixtures['documents'] as $document) {
        $ids[$document['id']] = phase_c_post($document['id'], $document['title'],
            $document['body'], $document['topics'] ?? [], ['post_status' => $document['status'] ?? 'publish']);
    }
    foreach ($fixtures['documents'] as $document) {
        $id = $ids[$document['id']];
        if (!empty($document['categories'])) {
            wp_set_object_terms($id, $document['categories'], 'category');
        }
        $content = $document['body'];
        foreach ($document['outgoing'] ?? [] as $outgoing) {
            $content .= ' <a href="' . esc_url(get_permalink($ids[$outgoing])) . '">Fixture destination</a>';
        }
        if ($content !== $document['body']) {
            wp_update_post(['ID' => $id, 'post_content' => $content]);
        }
    }
    $source = phase_c_post('url-guide', 'Ghana itinerary',
        'Explore Accra museums, markets and heritage before planning a Ghana itinerary.', ['ghana', 'itinerary']);
    $target = phase_c_post('url-museums', 'Accra museums',
        'Accra museums present Ghana history and heritage exhibits.', ['accra', 'museums']);
    $sourceContent = get_post($source)->post_content;
    $excluded = [];
    $rules = [
        'yoast-noindex' => ['meta' => ['_yoast_wpseo_meta-robots-noindex' => '1']],
        'rankmath-noindex' => ['meta' => ['rank_math_robots' => ['index', 'noindex']]],
        'external-canonical' => ['meta' => ['_yoast_wpseo_canonical' => 'https://example.invalid/elsewhere/']],
        'other-page-canonical' => ['meta' => ['rank_math_canonical_url' => get_permalink($target)]],
        'password' => ['post' => ['post_password' => 'synthetic-secret']],
        'draft' => ['post' => ['post_status' => 'draft']],
        'private' => ['post' => ['post_status' => 'private']],
    ];
    foreach ($rules as $name => $rule) {
        foreach (['source', 'target'] as $role) {
            $id = phase_c_post('excluded-' . $role . '-' . $name,
                $role === 'source' ? 'Ghana itinerary' : 'Accra museums',
                $role === 'source' ? $sourceContent : get_post($target)->post_content,
                $role === 'source' ? ['ghana', 'itinerary'] : ['accra', 'museums'], $rule['post'] ?? []);
            foreach ($rule['meta'] ?? [] as $key => $value) {
                update_post_meta($id, $key, $value);
            }
            $excluded[$role . '-' . $name] = $id;
            phase_c_check(!Preview::eligible(get_post($id)), 'Actual stored ' . $role . ' excludes ' . $name);
        }
    }
    update_post_meta($source, '_yoast_wpseo_canonical', get_permalink($source) . '#intro');
    update_post_meta($target, 'rank_math_canonical_url', wp_parse_url(get_permalink($target), PHP_URL_PATH));
    phase_c_check(Preview::eligible(get_post($source)) && Preview::eligible(get_post($target)),
        'Absolute self canonical with fragment and root relative self canonical remain eligible');
    flush_rewrite_rules(false);
    $smartTopicsBefore = get_post_meta($source, '_seo_tidy_smart_topics_v1', true);
    $details['initial_scan_batches'] = phase_c_rebuild();
    $state = Index::state();
    phase_c_check(!empty($state['complete']) && empty($state['stale']), 'Actual scan action completes the fixture index');
    phase_c_check(get_post_meta($source, '_seo_tidy_smart_topics_v1', true) === $smartTopicsBefore,
        'Actual rebuild preserves complete existing Smart Topics data');
    phase_c_check(Index::entry($source) !== [] && Index::entry($target) !== [], 'Actual scan writes source and target snapshots');
    foreach ($excluded as $name => $id) {
        phase_c_check(Index::entry($id) === [] && (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE post_id=%d", $id
        )) === 0, 'Actual scan excludes snapshot and SQL postings for ' . $name);
        if (str_starts_with($name, 'source-')) {
            phase_c_check(phase_c_suggestions($id) === [], 'Ineligible ' . $name . ' cannot request suggestions');
        } else {
            phase_c_check(!phase_c_has(phase_c_suggestions($source), $id),
                'Recommendation retrieval omits ineligible ' . $name);
        }
    }
    foreach ($fixtures['pairs'] as $pair) {
        phase_c_check(phase_c_has(phase_c_suggestions($ids[$pair['source']]), $ids[$pair['target']]) ===
            ($pair['expected'] === 'recommend'), 'Real WordPress fixture ' . $pair['source'] . ' -> ' .
            $pair['target'] . ' [' . $pair['expected'] . ']');
    }
    phase_c_check(phase_c_has(phase_c_suggestions($source), $target), 'Actual indexed URL test baseline recommends destination');
    phase_c_check((int) url_to_postid(get_permalink($target)) === $target, 'Core url_to_postid resolves actual pretty permalink');
    phase_c_check((int) url_to_postid(home_url('/?p=' . $target)) === $target, 'Core url_to_postid resolves actual plain p permalink');
    $path = wp_parse_url(get_permalink($target), PHP_URL_PATH);
    $slug = basename(rtrim($path, '/'));
    $links = [
        'absolute' => get_permalink($target),
        'absolute tracking and fragment' => get_permalink($target) . '?utm_source=fixture#hours',
        'root relative' => $path,
        'root relative tracking and fragment' => $path . '?ref=fixture#hours',
        'scheme relative' => '//127.0.0.1:8896' . $path,
        'sibling path relative' => '../' . $slug . '/',
        'dot path relative' => './../' . $slug . '/?ref=fixture#hours',
        'encoded parent dots' => '%2e%2e/' . $slug . '/',
        'mixed encoded parent dots' => '.%2e/' . $slug . '/',
        'encoded current then parent' => '%2E/../' . $slug . '/',
        'plain absolute p permalink' => home_url('/?p=' . $target),
        'plain root page_id permalink' => '/?page_id=' . $target . '#hours',
        'query only p permalink' => '?p=' . $target . '&ref=fixture#hours',
        'HTML entity query separator' => $path . '?ref=fixture&amp;sort=asc#hours',
        'surrounding whitespace' => '  ' . $path . '  ',
    ];
    // Vary stored-HTML input in memory; separate persisted-save checks below exercise invalidation.
    foreach ($links as $name => $href) {
        $post = clone get_post($source);
        $post->post_content = $sourceContent . ' <a href="' . $href . '">Museum details</a>';
        phase_c_check(!phase_c_has(Preview::suggestionsForPost($post), $target),
            'Real parser/resolver excludes existing link: ' . $name);
    }
    foreach (['external host' => 'https://example.invalid' . $path,
        'different local port' => 'http://127.0.0.1:8897' . $path,
        'source fragment' => '#hours', 'nonweb scheme' => 'mailto:fixture@example.invalid'] as $name => $href) {
        $post = clone get_post($source);
        $post->post_content = $sourceContent . ' <a href="' . $href . '">Other link</a>';
        phase_c_check(phase_c_has(Preview::suggestionsForPost($post), $target),
            'Unrelated href does not suppress a valid destination: ' . $name);
    }
    foreach (phase_c_suggestions($source) as $item) {
        phase_c_check($item['post']->ID !== $source && is_finite($item['score']) && $item['score'] > 0,
            'Real indexed result excludes self and exposes a finite raw ranking score');
    }

    $subscriber = get_user_by('login', 'phasec_subscriber');
    if (!$subscriber) {
        $subscriberId = wp_insert_user(['user_login' => 'phasec_subscriber',
            'user_pass' => wp_generate_password(32), 'role' => 'subscriber']);
        if (is_wp_error($subscriberId)) {
            throw new RuntimeException($subscriberId->get_error_message());
        }
        $subscriber = get_user_by('id', $subscriberId);
    }
    rest_get_server();
    foreach (['administrator' => (int) $admin->ID, 'subscriber' => (int) $subscriber->ID, 'anonymous' => 0] as $role => $userId) {
        wp_set_current_user($userId);
        foreach (['/seo-tidy/v1/internal-linking', '/seo-tidy/v1/audit'] as $route) {
            $request = new WP_REST_Request('GET', $route);
            $request->set_param('page', 1);
            $response = rest_do_request($request);
            $expectedStatus = $role === 'administrator' ? 200 : ($role === 'anonymous' ? 401 : 403);
            phase_c_check($response->get_status() === $expectedStatus,
                'Real REST dispatch ' . $route . ' enforces ' . $role . ' permission (' . $response->get_status() . ')');
            if ($role === 'administrator') {
                $data = $response->get_data();
                phase_c_check(is_array($data['items'] ?? null) && isset($data['total']),
                    'Administrator REST result retains expected collection contract: ' . $route);
                if ($route === '/seo-tidy/v1/internal-linking') {
                    phase_c_check(!empty($data['ready']) && wp_verify_nonce($data['scanNonce'], 'seo_tidy_link_scan') !== false,
                        'Real linking REST endpoint reports ready state and usable scan nonce');
                }
            }
        }
    }
    wp_set_current_user((int) $admin->ID);
    $request = new WP_REST_Request('GET', '/seo-tidy/v1/internal-linking');
    $request->set_param('page', 0);
    phase_c_check(rest_do_request($request)->get_status() === 400, 'REST schema rejects page below minimum');

    $dieHandler = static fn() => static function ($message = '', $title = '', $arguments = []): void {
        throw new PhaseCDie(wp_strip_all_tags((string) $message));
    };
    add_filter('wp_die_handler', $dieHandler);
    $stateBeforeDenied = get_option('seo_tidy_link_index_state_v1');
    foreach (['subscriber' => (int) $subscriber->ID, 'anonymous' => 0] as $role => $userId) {
        wp_set_current_user($userId);
        $_REQUEST['_wpnonce'] = wp_create_nonce('seo_tidy_link_scan');
        $denied = false;
        try {
            do_action('admin_post_seo_tidy_link_scan');
        } catch (PhaseCDie $error) {
            $denied = str_contains($error->getMessage(), 'Permission denied');
        }
        phase_c_check($denied && get_option('seo_tidy_link_index_state_v1') === $stateBeforeDenied,
            'Actual scan callback denies ' . $role . ' before index mutation');
    }
    wp_set_current_user((int) $admin->ID);
    $_REQUEST['_wpnonce'] = 'invalid-phase-c-nonce';
    $denied = false;
    try {
        do_action('admin_post_seo_tidy_link_scan');
    } catch (PhaseCDie $error) {
        $denied = true;
    }
    phase_c_check($denied && get_option('seo_tidy_link_index_state_v1') === $stateBeforeDenied,
        'Actual administrator scan rejects invalid nonce before index mutation');
    unset($_REQUEST['_wpnonce']);
    remove_filter('wp_die_handler', $dieHandler);

    // A real wp_update_post must make the successful index unavailable immediately.
    wp_update_post(['ID' => $source, 'post_content' => $sourceContent .
        ' <a href="' . esc_url(get_permalink($target) . '?ref=saved#hours') . '">Museum details</a>']);
    phase_c_check(!empty(Index::state()['stale']) && Index::entry($source) === [],
        'Actual article save marks index stale and removes changed article snapshot');
    phase_c_check(phase_c_suggestions($source) === [], 'Actual article edit blocks recommendations pending rebuild');
    $details['saved_link_rebuild_batches'] = phase_c_rebuild();
    phase_c_check(!phase_c_has(phase_c_suggestions($source), $target),
        'Persisted existing link remains excluded after actual rebuild');
    phase_c_check(get_post_meta($source, '_seo_tidy_smart_topics_v1', true) === $smartTopicsBefore,
        'Article edit and second rebuild preserve Smart Topics data model and values');

    $metaPost = $ids['GHANA-ITINERARY'];
    phase_c_check(Index::entry($metaPost) !== [], 'Metadata invalidation starts from real indexed snapshot');
    update_post_meta($metaPost, '_yoast_wpseo_meta-robots-noindex', '1');
    phase_c_check(!empty(Index::state()['stale']) && Index::entry($metaPost) === [],
        'Actual noindex metadata update invalidates snapshot and index');
    $topicPost = $ids['RIGA-ATTRACTIONS'];
    update_post_meta($topicPost, '_seo_tidy_smart_topics_v1', ['version' => 1,
        'approved' => ['rīga', 'muzeji', 'fixture update'], 'rejected' => []]);
    phase_c_check(Index::entry($topicPost) === [], 'Actual Smart Topics metadata update removes existing snapshot');
    $termPost = $ids['ACCRA-ATTRACTIONS'];
    wp_set_object_terms($termPost, ['phase-c-added-category'], 'category');
    phase_c_check(Index::entry($termPost) === [], 'Actual taxonomy update removes existing snapshot');
    $statusPost = $ids['ACCRA-TRANSPORT'];
    wp_update_post(['ID' => $statusPost, 'post_status' => 'draft']);
    phase_c_check(Index::entry($statusPost) === [] && !Preview::eligible(get_post($statusPost)),
        'Actual transition from publish to draft removes snapshot and eligibility');
    $deletePost = $ids['ACCRA-HOTELS'];
    wp_delete_post($deletePost, true);
    phase_c_check(Index::entry($deletePost) === [] && get_post($deletePost) === null,
        'Actual permanent article deletion removes snapshot');
    update_option('blog_public', '0');
    phase_c_check(!Preview::eligible(get_post($source)) && !Preview::eligible(get_post($target)) &&
        phase_c_suggestions($source) === [], 'Actual site noindex excludes both roles and recommendations');
    update_option('blog_public', '1');
    $details['final_state'] = Index::state();
    $details['fixture_ids'] = array_merge(array_values($ids), [$source, $target], array_values($excluded));
    $details['limitations'] = [
        'REST uses real in-process WordPress dispatch; HTTP authentication/cookie transport is not established here.',
        'Scan authorization invokes registered callback; normal admin-post.php HTTP routing is not established here.',
        'URL variants alter raw stored-content input in memory; one persisted existing link is separately rebuilt and checked.',
        'Only explicit post_content anchors are tested; rendered blocks, shortcodes, menus and theme links remain outside this contract.',
        'Fixture labels and BM25 outputs do not establish semantic accuracy or release readiness.',
    ];
} catch (Throwable $error) {
    phase_c_check(false, get_class($error) . ': ' . $error->getMessage());
    $details['exception'] = ['file' => $error->getFile(), 'line' => $error->getLine()];
}

phase_c_json(['mode' => 'contracts', 'checks' => $checks, 'failures' => $failures,
    'seconds' => microtime(true) - $started, 'peak_bytes' => memory_get_peak_usage(true),
    'results' => $results, 'details' => $details], 'contracts');
echo $checks . ' assertions, ' . $failures . ' failures.' . PHP_EOL;
exit($failures ? 1 : 0);
