<?php
/** Assertion-based checks of production PHP, with isolated WordPress/SQL doubles. */
declare(strict_types=1);

require __DIR__ . '/internal-linking-test-bootstrap.php';

use QvarcY\SeoTidy\InternalLinkingPreview as Preview;
use QvarcY\SeoTidy\SmartTopics;
use QvarcY\SeoTidy\SEOAudit;

$checks = $failures = 0;
function check(bool $ok, string $message): void
{
    global $checks, $failures;
    ++$checks;
    if (!$ok) {
        ++$failures;
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
}

$data = json_decode(file_get_contents(__DIR__ . '/internal-linking-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
$keys = fixture_reset($data['documents']);

if (in_array('--without-html', $argv, true)) {
    check(!class_exists('WP_HTML_Tag_Processor'), 'HTML processor is unavailable in this isolated process');
    check(fixture_suggestions($keys['GHANA-ITINERARY']) === [], 'Missing reliable HTML processor fails closed');
    echo "$checks assertions, $failures failures (missing HTML API mode)." . PHP_EOL;
    exit($failures ? 1 : 0);
}

echo 'Fixture labels: recommend requires inclusion; uncertain/reject requires exclusion.' . PHP_EOL;
echo 'BM25 scores are ranking signals, never accuracy percentages.' . PHP_EOL;
foreach ($data['pairs'] as $pair) {
    $suggestions = fixture_suggestions($keys[$pair['source']]);
    $present = fixture_has($suggestions, $keys[$pair['target']]);
    check($present === ($pair['expected'] === 'recommend'), $pair['source'] . ' -> ' . $pair['target'] . ' [' . $pair['expected'] . ']');
}

// Repeat each label with only its source, destination and existing-link
// dependencies. A negative must pass because evidence is rejected, rather than
// because unrelated fixtures happen to crowd the destination out of the top five.
$documentsByKey = array_column($data['documents'], null, 'id');
foreach ($data['pairs'] as $pair) {
    $pairDocuments = [$pair['source'] => $documentsByKey[$pair['source']], $pair['target'] => $documentsByKey[$pair['target']]];
    foreach (array_values($pairDocuments) as $document) {
        foreach ($document['outgoing'] ?? [] as $outgoing) {
            $pairDocuments[$outgoing] = $documentsByKey[$outgoing];
        }
    }
    $isolatedKeys = fixture_reset(array_values($pairDocuments));
    $present = fixture_has(fixture_suggestions($isolatedKeys[$pair['source']]), $isolatedKeys[$pair['target']]);
    check($present === ($pair['expected'] === 'recommend'), 'Isolated ' . $pair['source'] . ' -> ' . $pair['target'] . ' [' . $pair['expected'] . ']');
}
$keys = fixture_reset($data['documents']);

// Every returned score/context comes from production suggestionsForPost, with
// the real production Index SQL executed by the SQLite adapter.
foreach ($keys as $key => $id) {
    $suggestions = fixture_suggestions($id);
    $previous = INF;
    $seen = [];
    check(count($suggestions) <= 5, $key . ': output capped at five review candidates');
    foreach ($suggestions as $item) {
        check(is_float($item['score']) && is_finite($item['score']) && $item['score'] > 0,
            $key . ': finite positive raw ranking score');
        check($item['score'] <= $previous, $key . ': ranking descending');
        check($item['post']->ID !== $id && !isset($seen[$item['post']->ID]), $key . ': no self or duplicate candidate');
        check($item['context'] !== '', $key . ': nonempty source context');
        $seen[$item['post']->ID] = true;
        $previous = $item['score'];
    }
}

$tokens = Preview::termTokens('RĪGAS — muzeji, ģimenēm! <b>ĀDAŽI</b>; airport-transfer. WordPress + ceļošana; mājas majas.');
foreach (['rīgas', 'muzeji', 'ģimenēm', 'ādaži', 'airport', 'transfer', 'wordpress', 'ceļošana', 'mājas', 'majas'] as $token) {
    check(in_array($token, $tokens, true), 'Unicode/punctuation/mixed language token: ' . $token);
}
check(!in_array('riga', $tokens, true), 'Diacritics are preserved rather than silently transliterated');
check(Preview::termTokens('the AND with vai arī un ar kā') === [], 'English and Latvian stopwords ignored');
check(Preview::termTokens('R&#299;gas &amp; muzeji') === ['rīgas', 'muzeji'], 'HTML entity text and Unicode body text tokenize identically');
check(Preview::termTokens("RI\u{0304}GAS G\u{0327}IMENE\u{0304}M") === Preview::termTokens('RĪGAS ĢIMENĒM'),
    'Decomposed Latvian matches composed Latvian (' . (class_exists('Normalizer') ? 'intl' : 'Latvian fallback') . ')');

$positive = [
    ['id' => 'GUIDE', 'title' => 'Ghana itinerary', 'body' => 'Explore Accra museums, markets and heritage before planning a Ghana itinerary.', 'topics' => ['ghana', 'itinerary']],
    ['id' => 'MUSEUMS', 'title' => 'Accra museums', 'body' => 'Accra museums present Ghana history and heritage exhibits.', 'topics' => ['accra', 'museums']],
];
$keys = fixture_reset($positive);
$source = $keys['GUIDE'];
$target = $keys['MUSEUMS'];
check(fixture_has(fixture_suggestions($source), $target), 'URL exclusion baseline has a reviewable destination');
$sourceContent = get_post($source)->post_content;
$GLOBALS['fixture_urls'][$source] = 'https://example.test/travel/ghana/';
$GLOBALS['fixture_urls'][$target] = 'https://example.test/travel/accra-museums/';
$links = [
    'absolute' => 'https://example.test/travel/accra-museums/',
    'absolute with query and fragment' => 'https://example.test/travel/accra-museums/?utm_source=fixture#hours',
    'root relative' => '/travel/accra-museums/',
    'root relative query fragment' => '/travel/accra-museums/?ref=fixture#hours',
    'scheme relative' => '//example.test/travel/accra-museums/#hours',
    'host case' => 'https://EXAMPLE.TEST/travel/accra-museums/',
    'HTTP same-site alias' => 'http://example.test/travel/accra-museums/',
    'sibling path relative' => '../accra-museums/',
    'dot segment relative' => './../accra-museums/?ref=fixture#hours',
    'root dot segments' => '/travel/ghana/../accra-museums/',
    'encoded parent dot segments' => '%2e%2e/accra-museums/',
    'mixed literal/encoded parent dot segments' => '.%2e/accra-museums/',
    'encoded current then parent dot segments' => '%2E/../accra-museums/',
    'empty path segment then parent' => '/travel//../accra-museums/',
    'plain p permalink' => 'https://example.test/?p=' . $target,
    'plain page_id permalink' => '/?page_id=' . $target . '#hours',
    'query-only plain p permalink' => '?p=' . $target,
    'query-only plain p permalink plus tracking' => '?p=' . $target . '&ref=fixture#hours',
    'HTML entities' => '/travel/accra-museums/?ref=fixture&amp;sort=asc#hours',
    'surrounding href whitespace' => '  /travel/accra-museums/  ',
];
foreach ($links as $label => $href) {
    get_post($source)->post_content = $sourceContent . '<a href="' . $href . '">Museum details</a>';
    check(!fixture_has(fixture_suggestions($source), $target), 'Already linked destination excluded: ' . $label);
}

// Absolute URL resolution uses the document directory with and without trailing
// slash. It must not blindly prefix a relative link with the site home.
$GLOBALS['fixture_urls'][$source] = 'https://example.test/travel/ghana';
get_post($source)->post_content = $sourceContent . '<a href="accra-museums/">Museum details</a>';
check(!fixture_has(fixture_suggestions($source), $target), 'Path relative link uses file-like source permalink directory');
$GLOBALS['fixture_urls'][$source] = 'https://example.test/travel/ghana/';

foreach ([
    'external host' => 'https://other.example/travel/accra-museums/',
    'hostname suffix' => 'https://example.test.evil.example/travel/accra-museums/',
    'non-web mail scheme' => 'mailto:example.test/travel/accra-museums/',
    'non-web javascript scheme' => 'javascript:alert(1)',
    'unrelated local path' => '/travel/different-destination/',
    'source fragment only' => '#hours',
    'source query only' => '?ref=fixture',
    'encoded slash is not path separator' => '..%2faccra-museums/',
] as $label => $href) {
    get_post($source)->post_content = $sourceContent . '<a href="' . $href . '">Read more</a>';
    check(fixture_has(fixture_suggestions($source), $target), 'Unrelated href does not suppress destination: ' . $label);
}
get_post($source)->post_content = $sourceContent;

$GLOBALS['fixture_options']['home'] = 'https://example.test/blog';
$GLOBALS['fixture_urls'][$source] = 'https://example.test/blog/travel/ghana/';
$GLOBALS['fixture_urls'][$target] = 'https://example.test/blog/travel/accra-museums/';
get_post($source)->post_content = $sourceContent . '<a href="/blog/travel/accra-museums/">Museum details</a>';
check(!fixture_has(fixture_suggestions($source), $target), 'Root relative URL on a subdirectory site is not double-prefixed');
get_post($source)->post_content = $sourceContent . '<a href="../accra-museums/">Museum details</a>';
check(!fixture_has(fixture_suggestions($source), $target), 'Path relative URL on a subdirectory site');

// Recheck current eligibility on both ends even when postings remain present.
$exclusions = [
    'draft' => ['status' => 'draft'],
    'private' => ['status' => 'private'],
    'password protected' => ['password' => 'secret'],
    'unsupported attachment' => ['type' => 'attachment'],
    'Yoast noindex string' => ['meta' => ['_yoast_wpseo_meta-robots-noindex' => '1']],
    'Yoast noindex integer' => ['meta' => ['_yoast_wpseo_meta-robots-noindex' => 1]],
    'malformed Yoast robots' => ['meta' => ['_yoast_wpseo_meta-robots-noindex' => ['1']]],
    'Rank Math noindex' => ['meta' => ['rank_math_robots' => ['noindex', 'nofollow']]],
    'malformed Rank Math robots' => ['meta' => ['rank_math_robots' => 'noindex']],
    'Yoast external canonical' => ['meta' => ['_yoast_wpseo_canonical' => 'https://other.example/copy/']],
    'Rank Math different canonical' => ['meta' => ['rank_math_canonical_url' => 'https://example.test/other/']],
];
foreach ($exclusions as $label => $change) {
    foreach (['source' => 0, 'target' => 1] as $role => $offset) {
        $documents = $positive;
        $documents[$offset] = array_replace($documents[$offset], $change);
        $keys = fixture_reset($documents);
        check(!Preview::eligible(get_post($offset + 1)), 'Eligibility rejects ' . $role . ': ' . $label);
        check(!fixture_has(fixture_suggestions($keys['GUIDE']), $keys['MUSEUMS']), 'No recommendation with ineligible ' . $role . ': ' . $label);
    }
}

foreach (['_yoast_wpseo_canonical', 'rank_math_canonical_url'] as $metaKey) {
    $keys = fixture_reset($positive);
    $GLOBALS['fixture_meta'][2][$metaKey] = get_permalink(2);
    check(Preview::eligible(get_post(2)), 'Explicit self canonical remains eligible: ' . $metaKey);
    $GLOBALS['fixture_meta'][2][$metaKey] = untrailingslashit(get_permalink(2));
    check(Preview::eligible(get_post(2)), 'Self canonical trailing slash variation: ' . $metaKey);
    foreach (['/museums/', 'https://EXAMPLE.TEST/museums/', 'https://example.test/museums/#opening-hours', 'https://example.test:443/museums/'] as $canonical) {
        $GLOBALS['fixture_meta'][2][$metaKey] = $canonical;
        check(Preview::eligible(get_post(2)), 'Equivalent self canonical: ' . $canonical . ' (' . $metaKey . ')');
    }
    $GLOBALS['fixture_urls'][2] = 'https://example.test/?p=2';
    $GLOBALS['fixture_meta'][2][$metaKey] = 'https://example.test/?p=2#section';
    check(Preview::eligible(get_post(2)), 'Plain query self canonical eligible: ' . $metaKey);
    $GLOBALS['fixture_meta'][2][$metaKey] = 'https://example.test/?p=1';
    check(!Preview::eligible(get_post(2)), 'Different plain query canonical excluded: ' . $metaKey);
}

$keys = fixture_reset($positive);
$GLOBALS['fixture_options']['blog_public'] = '0';
check(!Preview::eligible(get_post(1)) && !Preview::eligible(get_post(2)), 'Site noindex excludes both roles');
check(fixture_suggestions(1) === [], 'Site noindex produces no suggestions');
foreach ([false, true] as $stale) {
    $keys = fixture_reset($positive);
    $GLOBALS['fixture_options']['seo_tidy_link_index_state_v1']['complete'] = $stale;
    $GLOBALS['fixture_options']['seo_tidy_link_index_state_v1']['stale'] = $stale;
    check(fixture_suggestions(1) === [], $stale ? 'Stale index fails closed' : 'Incomplete index fails closed');
}

foreach (['', 'https://other.example/museums/', 'mailto:museums@example.test', 'https://example.test:8443/museums/', 'https://user:pass@example.test/museums/'] as $url) {
    $keys = fixture_reset($positive);
    $GLOBALS['fixture_urls'][2] = $url;
    check(!Preview::eligible(get_post(2)), 'Ineligible target permalink: ' . ($url ?: '(empty)'));
    check(!fixture_has(fixture_suggestions(1), 2), 'Invalid destination permalink not recommended');
}

// Repeated words do not manufacture multiple distinct context matches.
$weak = $positive;
$weak[0]['body'] = 'Accra, Accra, Accra. A Ghana itinerary discusses a route.';
$keys = fixture_reset($weak);
check(!fixture_has(fixture_suggestions(1), 2), 'Repeated place name alone is weak directional evidence');
$weak[1]['topics'] = ['accra', 'museums'];
$weak[0]['topics'] = ['ghana', 'itinerary', 'museums'];
$keys = fixture_reset($weak);
check(!fixture_has(fixture_suggestions(1), 2), 'Approved topics without body context do not prove a link location');
$repeated = $positive;
$repeated[0]['body'] = 'Accra is part of the Ghana itinerary.';
$repeated[1]['title'] = 'Accra Accra Accra';
$repeated[1]['topics'] = ['accra'];
$keys = fixture_reset($repeated);
check(!fixture_has(fixture_suggestions(1), 2), 'Repeated target title token cannot inflate distinct context evidence');

$duplicate = $positive;
$duplicate[1]['title'] = $duplicate[0]['title'];
$duplicate[1]['body'] = $duplicate[0]['body'];
$duplicate[1]['topics'] = ['ghana', 'other'];
$keys = fixture_reset($duplicate);
check(!fixture_has(fixture_suggestions(1), 2), 'Identical title/body excluded despite different topics');
$duplicate[1]['title'] = 'Explore Accra museums';
$keys = fixture_reset($duplicate);
check(!fixture_has(fixture_suggestions(1), 2), 'Identical body with different title excluded conservatively');
$duplicate = $positive;
$duplicate[0]['title'] = $duplicate[1]['title'] = 'Accra museums';
$keys = fixture_reset($duplicate);
check(!fixture_has(fixture_suggestions(1), 2), 'Identical title excluded with different bodies and topics');
$duplicate[1]['title'] = 'Accra museums guide';
$keys = fixture_reset($duplicate);
check(!fixture_has(fixture_suggestions(1), 2), 'Target title containing all source title terms excluded as potential competition');
$duplicate[0]['title'] = 'Accra museums guide';
$duplicate[1]['title'] = 'Accra museums';
$keys = fixture_reset($duplicate);
check(!fixture_has(fixture_suggestions(1), 2), 'Source title containing all target title terms excluded as potential competition');

// The scorer, postings, average document length and document frequency must use
// the same production vector; assert the actual private document, not a formula copy.
$keys = fixture_reset($positive);
$documentMethod = new ReflectionMethod(Preview::class, 'document');
$document = $documentMethod->invoke(null, get_post(1));
$vector = Preview::indexTerms(get_post(1));
check($document['terms'] === $vector && $document['size'] === array_sum($vector), 'Production document terms/length equal authoritative index vector');
check($vector['ghana'] === 7 && $vector['itinerary'] === 7 && $vector['museums'] === 1,
    'Actual vector combines title weight three, body weight one and approved topic weight three');
$longPrefix = str_repeat('ā', 191);
$cap = [['id' => 'CAP', 'title' => 'Cap priority', 'body' => $longPrefix . 'x ' . $longPrefix . 'y '
    . implode(' ', array_map(static fn(int $i): string => 'term' . $i, range(1, 310))), 'topics' => ['priority']]];
$keys = fixture_reset($cap);
$vector = Preview::indexTerms(get_post(1));
$document = $documentMethod->invoke(null, get_post(1));
check(count($vector) === 300, 'Authoritative vector caps at 300 distinct stored terms');
check(isset($vector[$longPrefix]) && $vector[$longPrefix] === 2, 'Long Unicode terms merge frequencies at the 191-character storage boundary');
check($document['terms'] === $vector && $document['size'] === array_sum($vector), 'Scorer uses capped/merged terms and the identical length');
check($vector['priority'] === 6, 'Strong title/topic term survives vector cap');

// Read-only recommendation generation must preserve approved/rejected v1 data.
$keys = fixture_reset($positive);
$GLOBALS['fixture_meta'][1]['_seo_tidy_smart_topics_v1']['rejected'] = ['irrelevant'];
$beforeMeta = $GLOBALS['fixture_meta'];
fixture_suggestions(1);
check($GLOBALS['fixture_meta'] === $beforeMeta, 'Generating suggestions preserves Smart Topics and all post meta');
check(SmartTopics::approved(1) === ['ghana', 'itinerary'], 'Existing Smart Topics v1 approved labels retained');
check(SEOAudit::classifyIssues(['duplicate_title']) === 'attention' && SEOAudit::classifyIssues([]) === 'clear'
    && SEOAudit::classifyIssues(['missing_description']) === 'recommendations', 'Existing SEO Audit classification API unchanged');

echo "$checks assertions, $failures failures." . PHP_EOL;
echo 'Verified: actual production Preview, SmartTopics read API, Index query methods and SEOAudit classification.' . PHP_EOL;
echo 'Not verified: real WordPress routing/HTML API, MySQL schema/collation, hooks, saves, REST/admin UI or real editorial accuracy.' . PHP_EOL;
exit($failures ? 1 : 0);
