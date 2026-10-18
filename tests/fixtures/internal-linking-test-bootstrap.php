<?php
/**
 * Standalone test doubles, deliberately isolated from a live WordPress install.
 * The production classes and their SQL run unchanged against an in-memory SQLite
 * database. This does not validate MySQL collation, WordPress URL routing or its
 * HTML parser, hooks, capabilities, HTTP responses or administration screens.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/not-a-wordpress-install/');
define('ARRAY_A', 'ARRAY_A');

final class WP_Post
{
    public int $ID;
    public string $post_title = '';
    public string $post_content = '';
    public string $post_status = 'publish';
    public string $post_type = 'post';
    public string $post_password = '';

    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}

// Optional read-only parser validation: load these standalone core classes only,
// never wp-load.php, the site's configuration, its database or any live hooks.
$fixtureCoreRoot = getenv('SEO_TIDY_WP_CORE_ROOT');
if ($fixtureCoreRoot && !in_array('--without-html', $argv, true)) {
    foreach (['attribute-token', 'span', 'text-replacement', 'tag-processor'] as $file) {
        require_once rtrim($fixtureCoreRoot, '/\\') . '/wp-includes/html-api/class-wp-html-' . $file . '.php';
    }
}

// Only the two methods used by the production code are doubled. DOMDocument is
// not the WordPress HTML API; malformed/block-generated HTML needs integration.
if (!in_array('--without-html', $argv, true) && !class_exists('WP_HTML_Tag_Processor')) {
    final class WP_HTML_Tag_Processor
    {
        private array $anchors = [];
        private int $offset = -1;

        public function __construct(string $html)
        {
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            foreach ($dom->getElementsByTagName('a') as $node) {
                $this->anchors[] = $node->hasAttribute('href') ? $node->getAttribute('href') : null;
            }
        }

        public function next_tag($query = null): bool
        {
            return ++$this->offset < count($this->anchors);
        }

        public function get_attribute(string $name): ?string
        {
            return $name === 'href' ? $this->anchors[$this->offset] : null;
        }
    }
}

final class LinkFixtureDatabase
{
    public string $prefix = 'wp_';
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $options = 'wp_options';
    public string $last_error = '';
    private PDO $database;

    public function __construct()
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_status TEXT, post_type TEXT)');
        $this->database->exec('CREATE TABLE wp_seo_tidy_link_terms (post_id INTEGER, term TEXT COLLATE BINARY, frequency INTEGER, PRIMARY KEY (post_id, term))');
    }

    public function prepare(string $sql, ...$values): string
    {
        if (count($values) === 1 && is_array($values[0])) {
            $values = $values[0];
        }
        $offset = 0;
        $result = preg_replace_callback('/%[sdf]/', function (array $match) use ($values, &$offset): string {
            if (!array_key_exists($offset, $values)) {
                throw new RuntimeException('Missing SQL parameter in fixture adapter');
            }
            $value = $values[$offset++];
            return $match[0] === '%s' ? $this->database->quote((string) $value) : (string) (int) $value;
        }, $sql);
        if ($offset !== count($values)) {
            throw new RuntimeException('Extra SQL parameter in fixture adapter');
        }
        return $result;
    }

    public function get_col(string $sql): array
    {
        return $this->database->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function get_var(string $sql)
    {
        // Options share the existing fixture store; production ranking SQL still
        // executes against SQLite. This does not simulate database concurrency.
        if (preg_match("/^SELECT option_value FROM wp_options WHERE option_name='([^']+)'$/", $sql, $match)) {
            return array_key_exists($match[1], $GLOBALS['fixture_options'])
                ? maybe_serialize($GLOBALS['fixture_options'][$match[1]]) : null;
        }
        return $this->database->query($sql)->fetchColumn();
    }

    public function get_row(string $sql, $format = null)
    {
        $row = $this->database->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : ($format === ARRAY_A ? $row : (object) $row);
    }

    public function get_results(string $sql, $format = null): array
    {
        $rows = $this->database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $format === ARRAY_A ? $rows : array_map(static fn(array $row): object => (object) $row, $rows);
    }

    public function query(string $sql)
    {
        return $this->database->exec($sql);
    }

    public function add(WP_Post $post, array $terms): void
    {
        $this->query($this->prepare('INSERT INTO wp_posts VALUES (%d,%s,%s)', $post->ID, $post->post_status, $post->post_type));
        foreach ($terms as $term => $frequency) {
            $this->query($this->prepare('INSERT INTO wp_seo_tidy_link_terms VALUES (%d,%s,%d)', $post->ID, (string) $term, $frequency));
        }
    }
}

$GLOBALS['fixture_posts'] = [];
$GLOBALS['fixture_meta'] = [];
$GLOBALS['fixture_terms'] = [];
$GLOBALS['fixture_urls'] = [];
$GLOBALS['fixture_options'] = [];

function get_option(string $key, $default = false) { return $GLOBALS['fixture_options'][$key] ?? $default; }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function maybe_unserialize($value)
{
    if (!is_string($value)) {
        return $value;
    }
    $decoded = @unserialize($value, ['allowed_classes' => false]);
    return $decoded !== false || $value === 'b:0;' ? $decoded : $value;
}
function get_post_meta(int $id, string $key, bool $single = false) { return $GLOBALS['fixture_meta'][$id][$key] ?? ''; }
function wp_get_post_terms(int $id, $taxonomies, array $args = []): array { return $GLOBALS['fixture_terms'][$id] ?? []; }
function is_wp_error($value): bool { return false; }
function get_post($id) { return $GLOBALS['fixture_posts'][(int) $id] ?? null; }
function wp_strip_all_tags(string $text, bool $removeBreaks = false): string { return strip_tags(preg_replace('@<(script|style)\b[^>]*>.*?</\1>@is', '', $text)); }
function strip_shortcodes(string $text): string { return preg_replace('/\[\/?[a-zA-Z][^\]]*\]/', '', $text); }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function home_url(string $path = ''): string { return rtrim($GLOBALS['fixture_options']['home'] ?? 'https://example.test', '/') . ($path === '' ? '' : '/' . ltrim($path, '/')); }
function get_permalink($post): string { $id = $post instanceof WP_Post ? $post->ID : (int) $post; return $GLOBALS['fixture_urls'][$id] ?? ''; }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }

/** Explicit fixture router: a path map plus core plain-permalink query forms. */
function url_to_postid(string $url): int
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return 0;
    }
    $home = parse_url(home_url());
    if (isset($parts['host']) && strcasecmp($parts['host'], (string) ($home['host'] ?? '')) !== 0) {
        return 0;
    }
    parse_str($parts['query'] ?? '', $query);
    foreach (['p', 'page_id', 'attachment_id'] as $key) {
        if (isset($query[$key]) && is_scalar($query[$key]) && ctype_digit((string) $query[$key])) {
            return isset($GLOBALS['fixture_posts'][(int) $query[$key]]) ? (int) $query[$key] : 0;
        }
    }
    $path = rtrim(rawurldecode($parts['path'] ?? '/'), '/');
    foreach ($GLOBALS['fixture_urls'] as $id => $permalink) {
        if ($path === rtrim(rawurldecode((string) parse_url($permalink, PHP_URL_PATH)), '/')) {
            return $id;
        }
    }
    return 0;
}

$fixtureRuntime = getenv('SEO_TIDY_TEST_RUNTIME_ROOT') ?: dirname(__DIR__, 2) . '/includes';
require_once $fixtureRuntime . '/SmartTopics.php';
require_once $fixtureRuntime . '/InternalLinkingPreview.php';
require_once $fixtureRuntime . '/InternalLinkingIndex.php';
require_once $fixtureRuntime . '/SEOAudit.php';

/** Build postings from production tokenization, without invoking scan's redirects. */
function fixture_reset(array $documents): array
{
    $GLOBALS['fixture_posts'] = $GLOBALS['fixture_meta'] = $GLOBALS['fixture_terms'] = $GLOBALS['fixture_urls'] = [];
    $schema = (new ReflectionClass(QvarcY\SeoTidy\InternalLinkingIndex::class))->getConstant('SCHEMA');
    $GLOBALS['fixture_options'] = ['blog_public' => '1', 'home' => 'https://example.test',
        'seo_tidy_link_index_generation_v1' => '0',
        'seo_tidy_link_index_state_v1' => ['complete' => true, 'stale' => false, 'schema' => $schema,
            'generation' => '0']];
    $GLOBALS['wpdb'] = new LinkFixtureDatabase();
    $keys = [];
    foreach ($documents as $offset => $document) {
        $id = $offset + 1;
        $keys[$document['id']] = $id;
        $GLOBALS['fixture_posts'][$id] = new WP_Post([
            'ID' => $id, 'post_title' => $document['title'],
            'post_content' => (isset($document['heading']) ? '<h2>' . $document['heading'] . "</h2>\n" : '') . '<p>' . $document['body'] . '</p>',
            'post_status' => $document['status'] ?? 'publish',
            'post_type' => $document['type'] ?? 'post', 'post_password' => $document['password'] ?? '',
        ]);
        $GLOBALS['fixture_meta'][$id] = array_merge(['_seo_tidy_smart_topics_v1' => [
            'version' => 1, 'approved' => $document['topics'] ?? [], 'rejected' => []]], $document['meta'] ?? []);
        if (isset($document['indexable']) && !$document['indexable']) {
            $GLOBALS['fixture_meta'][$id]['_yoast_wpseo_meta-robots-noindex'] = '1';
        }
        $GLOBALS['fixture_terms'][$id] = $document['categories'] ?? [];
        $GLOBALS['fixture_urls'][$id] = 'https://example.test/' . strtolower($document['id']) . '/';
    }
    foreach ($documents as $offset => $document) {
        $post = $GLOBALS['fixture_posts'][$offset + 1];
        foreach ($document['outgoing'] ?? [] as $key) {
            $post->post_content .= '<a href="' . get_permalink($keys[$key]) . '">Existing destination</a>';
        }
        $terms = QvarcY\SeoTidy\InternalLinkingPreview::indexTerms($post);
        // Keep ineligible rows deliberately: Preview must recheck current posts,
        // even when a previously eligible target has a stale stored posting.
        $GLOBALS['wpdb']->add($post, $terms);
    }
    return $keys;
}

function fixture_suggestions(int $id): array
{
    return QvarcY\SeoTidy\InternalLinkingPreview::suggestionsForPost(get_post($id));
}

function fixture_has(array $suggestions, int $targetId): bool
{
    foreach ($suggestions as $suggestion) {
        if ($suggestion['post']->ID === $targetId) {
            return true;
        }
    }
    return false;
}
