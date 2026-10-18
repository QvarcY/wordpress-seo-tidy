<?php
/** Narrow probes for the previously reproduced isolated Phase C scan races. */

use QvarcY\SeoTidy\InternalLinkingIndex as Index;
use QvarcY\SeoTidy\InternalLinkingPreview as Preview;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

$environmentRoot = realpath((string) getenv('SEO_TIDY_PHASE_C_ENV_ROOT'));
$root = realpath((string) getenv('SEO_TIDY_PHASE_C_WP_ROOT'));
$marker = $environmentRoot && is_file($environmentRoot . '/isolation-marker.json')
    ? json_decode((string) file_get_contents($environmentRoot . '/isolation-marker.json'), true) : null;
if (getenv('SEO_TIDY_PHASE_C_ISOLATED') !== '1' || !$root || !$environmentRoot ||
    !is_array($marker) || realpath((string) ($marker['wordpress_root'] ?? '')) !== $root ||
    ($marker['db_name'] ?? '') !== 'seo_tidy_phase_c' ||
    ($marker['db_host'] ?? '') !== '127.0.0.1:33929' ||
    ($marker['home'] ?? '') !== 'http://127.0.0.1:8896' ||
    $root !== realpath($environmentRoot . '/wordpress') ||
    !is_file($root . '/wp-load.php') || !is_file($root . '/.seo-tidy-phase-c-isolated')) {
    fwrite(STDERR, "Refusing to load an unmarked installation.\n");
    exit(2);
}
require $root . '/wp-load.php';
$home = wp_parse_url(home_url('/'));
if (DB_NAME !== 'seo_tidy_phase_c' || DB_HOST !== '127.0.0.1:33929' ||
    !is_array($home) || ($home['host'] ?? '') !== '127.0.0.1' || (int) ($home['port'] ?? 0) !== 8896 ||
    realpath(ABSPATH) !== $root || !class_exists(Index::class) || !class_exists(Preview::class) ||
    !defined('SEO_TIDY_PHASE_C_ISOLATED') || SEO_TIDY_PHASE_C_ISOLATED !== true ||
    wp_get_environment_type() !== 'local' ||
    !defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
    fwrite(STDERR, "Isolated database, origin, plugin or root guard failed.\n");
    exit(2);
}

function phase_c_concurrency_ids(): array
{
    return array_map('intval', get_posts([
        'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1,
        'meta_key' => '_seo_tidy_phase_c_synthetic', 'orderby' => 'ID', 'order' => 'ASC',
        'fields' => 'ids', 'suppress_filters' => true,
    ]));
}

function phase_c_concurrency_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    global $wpdb;
    $mode = $argv[1] ?? '';
    $ids = phase_c_concurrency_ids();
    $published = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}
        WHERE post_type IN ('post','page') AND post_status='publish'");
    phase_c_concurrency_assert(count($ids) === 25 && $published === 25,
        'Reuse only the existing 25-published-post race corpus; no workload is seeded here.');
    $postId = isset($argv[2]) ? (int) $argv[2] : $ids[0];
    phase_c_concurrency_assert(in_array($postId, $ids, true), 'Expected a published synthetic race post.');
    $post = get_post($postId);

    if ($mode === 'touch' || $mode === 'edit') {
        $markerText = (string) ($argv[3] ?? '');
        phase_c_concurrency_assert((bool) preg_match('/^[a-z0-9-]{1,60}$/', $markerText), 'Invalid marker.');
        $topics = get_post_meta($postId, '_seo_tidy_smart_topics_v1', true);
        $update = ['ID' => $postId, 'post_excerpt' => 'Phase C concurrency ' . $markerText];
        if ($mode === 'edit') {
            $update['post_content'] = '<p>Phasecconcurrencyrevision ' . $markerText .
                ' pārbaude, ģimene, drošība. English replacement evidence with punctuation.</p>';
        }
        $result = wp_update_post($update, true);
        phase_c_concurrency_assert(!is_wp_error($result), 'WordPress save failed.');
        phase_c_concurrency_assert($topics === get_post_meta($postId, '_seo_tidy_smart_topics_v1', true),
            'WordPress save changed Smart Topics.');
    } else {
        phase_c_concurrency_assert($mode === 'inspect', 'Use inspect, touch POST_ID MARKER or edit POST_ID MARKER.');
    }

    $table = $wpdb->prefix . 'seo_tidy_link_terms';
    $mismatched = [];
    $allRows = $wpdb->get_results("SELECT post_id,term,frequency FROM {$table} ORDER BY post_id,term", ARRAY_A);
    $vectors = [];
    foreach ($allRows as $row) {
        $vectors[(int) $row['post_id']][$row['term']] = (int) $row['frequency'];
    }
    foreach ($ids as $id) {
        $actual = $vectors[$id] ?? [];
        $expected = Preview::indexTerms(get_post($id));
        ksort($actual);
        ksort($expected);
        if ($actual !== $expected) {
            $mismatched[] = ['post_id' => $id, 'stored_terms' => count($actual),
                'expected_terms' => count($expected), 'missing' => array_keys(array_diff_key($expected, $actual)),
                'obsolete' => array_keys(array_diff_key($actual, $expected))];
        }
    }
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",
        'seo_tidy_link_index_state_v1'));
    $rawState = maybe_unserialize($raw);
    $duplicates = $wpdb->get_results("SELECT post_id,term,COUNT(*) AS n FROM {$table}
        GROUP BY post_id,term HAVING n>1", ARRAY_A);
    $topicRows = $wpdb->get_results("SELECT post_id,meta_value FROM {$wpdb->postmeta}
        WHERE meta_key='_seo_tidy_smart_topics_v1' ORDER BY post_id,meta_id", ARRAY_A);
    $state = Index::state();
    $corpus = Index::corpusStats();
    $result = [
        'mode' => $mode, 'post_id' => $postId, 'published_posts' => $published,
        'state' => $state, 'raw_state' => $rawState, 'corpus' => $corpus,
        'candidate_ids' => Index::candidateIds(['planning', 'drošība', 'evidence'], $postId),
        'mismatched_vectors' => $mismatched, 'indexed_documents' => count($vectors),
        'postings' => count($allRows), 'duplicate_primary_keys' => $duplicates,
        'smart_topics_sha256' => hash('sha256', serialize($topicRows)),
        'body_sha256' => hash('sha256', get_post($postId)->post_content),
        'snapshot' => Index::entry($postId), 'database_error' => $wpdb->last_error,
        'environment' => ['wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION,
            'database' => $wpdb->get_var('SELECT VERSION()'), 'home' => home_url('/')],
    ];
    echo 'PHASE_C_JSON ' . wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
