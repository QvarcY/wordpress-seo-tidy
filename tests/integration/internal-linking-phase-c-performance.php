<?php
/**
 * Isolated real-WordPress workload and scan-race probes for Issue #29, Phase C.
 * Run with PHP CLI only, using an explicitly marked disposable local installation.
 * This file never rebuilds an index: call the real administrator scan separately.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

$mode = $argv[1] ?? '';
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

use QvarcY\SeoTidy\InternalLinkingIndex as Index;
use QvarcY\SeoTidy\InternalLinkingPreview as Preview;

$home = wp_parse_url(home_url('/'));
if (!defined('DB_NAME') || DB_NAME !== 'seo_tidy_phase_c' ||
    !defined('DB_HOST') || DB_HOST !== '127.0.0.1:33929' ||
    !is_array($home) || ($home['host'] ?? '') !== '127.0.0.1' ||
    (int) ($home['port'] ?? 0) !== 8896 ||
    realpath(ABSPATH) !== $root || !class_exists(Index::class) ||
    !defined('SEO_TIDY_PHASE_C_ISOLATED') || SEO_TIDY_PHASE_C_ISOLATED !== true ||
    wp_get_environment_type() !== 'local' ||
    !defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
    fwrite(STDERR, "Isolated database, origin, plugin or root guard failed.\n");
    exit(2);
}

function phase_c_json(array $data): void
{
    echo "PHASE_C_JSON_BEGIN\n" . json_encode($data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nPHASE_C_JSON_END\n";
}

function phase_c_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase_c_ids(): array
{
    return array_map('intval', get_posts([
        'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1,
        'meta_key' => '_seo_tidy_phase_c_synthetic', 'orderby' => 'ID', 'order' => 'ASC',
        'fields' => 'ids', 'suppress_filters' => true,
    ]));
}

function phase_c_content(int $number): array
{
    $lv = ($number % 2) === 0;
    $groups = $lv ? ['Dārzs', 'Ceļojums', 'Riteņbraukšana', 'Cepšana', 'Mūzika'] :
        ['Garden', 'Travel', 'Cycling', 'Baking', 'Music'];
    $roles = $lv ? ['plānošana', 'aprīkojums', 'kopšana', 'drošība', 'izmaksas',
        'materiāli', 'sezona', 'maršruti', 'tehnika', 'apmācība'] :
        ['planning', 'equipment', 'maintenance', 'safety', 'budget',
        'materials', 'season', 'routes', 'technique', 'training'];
    $group = $groups[intdiv($number - 1, 2) % count($groups)];
    $role = $roles[intdiv($number - 1, 10) % count($roles)];
    $edition = intdiv($number - 1, 100) + 1;
    $sentences = $lv ? [
        'Praktiska plānošana palīdz ģimenei izvēlēties piemērotu laiku, vietu un aprīkojumu. Vispirms salīdzinām vajadzības, pēc tam pārbaudām materiālu kvalitāti un drošību. Šajā ceļvedī pieredze pārvēršas saprotamās darbībās, lai iesācējs varētu sagatavoties mierīgi un izvairīties no biežām kļūdām.',
        'Pavasarī temperatūra, lietus, vējš un dienasgaisma maina darba apstākļus. Vasarā nepieciešama ūdens pudele, cepure, ērti apavi un aizsardzība pret sauli. Rudenī jāpārbauda mitrums, bet ziemā īpaši svarīga ir siltuma saglabāšana un rūpīga glabāšana sausā telpā.',
        'Vietējais speciālists iesaka pierakstīt mērķus, izmērīt pieejamo platību un izveidot nelielu kontrolsarakstu. Darbarīki, rezerves daļas, transportēšana un regulāra tīrīšana jāiekļauj budžetā. Lētākais risinājums reizēm maksā vairāk, ja detaļas bieži jāmaina vai remonts prasa palīdzību.',
        'Ģimenes dalībnieki var sadalīt pienākumus atbilstoši prasmēm un interesēm. Bērniem vajadzīga uzraudzība, skaidri noteikumi un īsi pārtraukumi. Pieaugušie pārbauda stiprinājumus, savienojumus, apgaismojumu un pieejamību, savukārt pieredzējis draugs palīdz novērtēt sarežģītāku situāciju pirms darba sākuma.',
        'Salīdzināšanai izmantojam izmēru, svaru, izturību, garantiju un remonta iespējas. Pārdevēja apraksts jāpārbauda praksē, jo fotogrāfija ne vienmēr parāda virsmas faktūru vai detaļas. Pirms pirkuma noder izmēģinājums, atsauksmes, mērījumi un saruna ar cilvēku, kurš līdzīgu risinājumu lieto ikdienā.',
        'Nelielā eksperimentā vienlaikus mainām tikai vienu parametru un rezultātu atzīmējam piezīmēs. Precīzs datums, ilgums, izmantotais paņēmiens un apstākļi palīdz salīdzināt mēģinājumus. Nākamajā nedēļā izvērtējam progresu, koriģējam grafiku un saglabājam to pieeju, kas sniedza stabilāko rezultātu.',
        'Ilgtspējīga izvēle ietver atkārtotu izmantošanu, šķirošanu un atbildīgu resursu patēriņu. Kaimiņi var aizdot retāk vajadzīgu instrumentu, bibliotēka piedāvā pamācības, bet kopienas nodarbība ļauj iegūt jaunas prasmes. Savlaicīga apkope pagarina kalpošanas laiku un samazina nevajadzīgu atkritumu daudzumu.',
        'Noslēgumā pārskatām sasniegto, saglabājam dokumentus un sagatavojam nākamo soli. Veiksmīgs process ir atkārtojams, saprotams un pielāgojams konkrētajai mājsaimniecībai. Ja apstākļi mainās, prioritātes var pārkārtot, taču uzmanība drošībai, kvalitātei un mācībām paliek svarīga visā darba gaitā.',
        'Pirms došanās ārpus mājas pārbaudām adresi, nokļūšanu, darba laiku un nepieciešamos dokumentus. Maršruta izvēle ir atkarīga no attāluma, laikapstākļiem un dalībnieku iespējām. Rezerves plāns palīdz saglabāt mieru, ja paredzētā vieta ir slēgta vai ceļš pēkšņi kļūst nepieejams.',
        'Kopšanas žurnālā ierakstām pārbaudes datumu, pamanīto problēmu un paveikto darbu. Fotogrāfijas palīdz pamanīt pakāpeniskas pārmaiņas, savukārt čeki noder garantijas jautājumu risināšanai. Ikmēneša pārskatā var atklāt atkārtojošos traucējumus un savlaicīgi pasūtīt piemērotas detaļas pirms aktīvās sezonas sākuma.',
        'Mācību laikā izmantojam nelielus uzdevumus, skaidrus piemērus un praktisku demonstrāciju. Ja paņēmiens neizdodas, samazinām sarežģītību un atkārtojam lēnāk. Patstāvīga izmēģināšana nostiprina izpratni, bet regulāra atgriezeniskā saite palīdz labot ieradumus, pirms kļūdas rada zaudējumus vai nepatīkamas sekas.',
        'Pieejamības pārbaudē pievēršam uzmanību pakāpieniem, satvērieniem, norādēm un pārvietošanās iespējām. Vecākam cilvēkam var būt vajadzīgs ilgāks atpūtas brīdis vai vieglāks priekšmets. Vienkārša pielāgošana bieži uzlabo pieredzi visiem dalībniekiem, tādēļ vajadzības pārrunājam savlaicīgi un izvēli pārbaudām kopā.',
    ] : [
        'Practical planning helps a family choose a suitable time, place and equipment. First compare personal needs, then inspect material quality and safety. This guide turns experience into understandable actions so a beginner can prepare calmly, explain the decisions to a friend and avoid common mistakes before starting.',
        'Spring temperatures, rainfall, wind and daylight change the working conditions. Summer requires drinking water, a hat, comfortable shoes and protection from sunlight. During autumn check moisture carefully; in winter warmth and dry storage become particularly important. Local forecasts provide useful context when deciding whether to continue or postpone an activity.',
        'A local specialist recommends recording goals, measuring available space and preparing a short checklist. Tools, replacement parts, transport and regular cleaning belong in the budget. The cheapest option sometimes costs more when components need frequent replacement or repairs require professional assistance. Compare expected use before committing to a purchase.',
        'Family members can divide responsibilities according to their skills and interests. Children need supervision, clear rules and short breaks. Adults inspect fasteners, connections, lighting and accessibility, while an experienced friend helps evaluate a difficult situation before work begins. Keep essential supplies nearby and allow additional preparation time for unfamiliar tasks.',
        'Compare dimensions, weight, durability, warranty and repair options. Check the supplier description in practice because photographs cannot always show surface texture or construction details. A trial, measurements, customer feedback and a conversation with somebody using similar equipment provide better evidence. Keep notes explaining why a particular choice suits the household.',
        'In a small experiment change one parameter at a time and record the outcome. An accurate date, duration, technique and description of conditions help compare attempts. The following week review progress, adjust the schedule and preserve the approach with the most consistent result. Unexpected observations deserve investigation before drawing a firm conclusion.',
        'A sustainable choice includes reuse, recycling and responsible consumption of resources. Neighbours can lend a rarely needed tool, the library offers instructions and a community workshop helps develop new skills. Timely maintenance extends useful life and reduces unnecessary waste. Store valuable materials carefully and share practical lessons with other participants.',
        'Finally review the outcome, retain documents and prepare the next step. A successful process is repeatable, understandable and adaptable to a particular household. When circumstances change priorities can move, while attention to safety, quality and learning remains useful throughout the activity. Discuss remaining questions and arrange help before attempting an advanced procedure.',
        'Before leaving home check the address, transport, opening hours and necessary documents. The route depends on distance, weather and participant abilities. A backup plan makes unexpected closures or inaccessible paths easier to manage. Share the schedule, confirm meeting points and carry enough supplies for an additional delay during the return journey.',
        'Record inspection dates, observed problems and completed work in a maintenance journal. Photographs reveal gradual changes and receipts support warranty discussions. A monthly review can reveal recurring faults and allow suitable parts to arrive before the busy season. Follow manufacturer instructions when cleaning sensitive surfaces or storing equipment for several months.',
        'During training use small exercises, clear examples and a practical demonstration. If a technique fails reduce complexity and repeat slowly. Independent practice strengthens understanding, while regular feedback corrects habits before mistakes cause loss or discomfort. Recognise steady progress and set achievable goals that match the time available for the next session.',
        'An accessibility review considers steps, handles, signs and movement requirements. An older participant may need longer rest periods or a lighter object. Simple adjustments often improve the experience for everyone, so discuss needs early and evaluate the choice together. Keep pathways clear and provide an alternative task when a specific movement causes difficulty.',
    ];
    // Rotate paragraphs so equal-theme posts do not have identical bodies.
    $offset = $number % count($sentences);
    $sentences = array_merge(array_slice($sentences, $offset), array_slice($sentences, 0, $offset));
    $intro = $lv ? "{$group}: " . implode(', ', $roles) . ". Šīs nodaļas uzmanības centrā ir {$role}; piemērs {$number}." :
        "{$group}: " . implode(', ', $roles) . ". This chapter focuses on {$role}; example {$number}.";
    $body = '<p>' . $intro . '</p><p>' . implode('</p><p>', $sentences) . '</p>';
    return [
        'title' => "{$group} {$role} {$edition}", 'content' => $body,
        'topics' => [$group, $role], 'language' => $lv ? 'lv' : 'en',
    ];
}

function phase_c_stats(): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'seo_tidy_link_terms';
    return [
        'published_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'"),
        'published_pages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'"),
        'synthetic_posts' => count(phase_c_ids()),
        'postings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
        'indexed_documents' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$table}"),
        'state' => Index::state(),
        'corpus' => Index::corpusStats(),
        'sql_error' => $wpdb->last_error,
    ];
}

function phase_c_summary(array $values): array
{
    sort($values, SORT_NUMERIC);
    $n = count($values);
    return ['n' => $n, 'min' => min($values), 'median' => $n % 2 ? $values[intdiv($n, 2)] :
        ($values[$n / 2 - 1] + $values[$n / 2]) / 2, 'p95' => $values[max(0, (int) ceil($n * .95) - 1)],
        'max' => max($values), 'mean' => array_sum($values) / $n];
}

function phase_c_race_paths(string $worker): array
{
    global $root;
    phase_c_assert((bool) preg_match('/^[a-z][a-z0-9-]{0,30}$/', $worker), 'Invalid race worker name.');
    $dir = $root . '/phase-c-races';
    if (!is_dir($dir)) {
        phase_c_assert(mkdir($dir, 0700), 'Cannot create isolated race directory.');
    }
    return ['ready' => $dir . '/' . $worker . '.ready.json',
        'release' => $dir . '/' . $worker . '.release', 'result' => $dir . '/' . $worker . '.result.json'];
}

try {
    if (in_array($mode, ['seed200', 'seed1000'], true)) {
        global $wpdb;
        $target = $mode === 'seed200' ? 200 : 1000;
        $existing = phase_c_ids();
        phase_c_assert(count($existing) <= $target, 'Refusing to shrink or reseed an existing larger workload.');
        $start = microtime(true);
        // Only a disposable installation may contain unrelated fixture content. Remove it
        // explicitly so the benchmark has exactly the stated published post corpus.
        $all = get_posts(['post_type' => ['post', 'page'], 'post_status' => 'publish',
            'numberposts' => -1, 'fields' => 'ids']);
        foreach (array_diff(array_map('intval', $all), $existing) as $id) {
            phase_c_assert(wp_delete_post($id, true) instanceof WP_Post, "Cannot remove fixture {$id}.");
        }
        for ($number = count($existing) + 1; $number <= $target; $number++) {
            $data = phase_c_content($number);
            $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish',
                'post_title' => $data['title'], 'post_name' => 'phase-c-synthetic-' . $number,
                'post_content' => $data['content'], 'post_author' => 1], true);
            phase_c_assert(!is_wp_error($id) && $id > 0, 'Synthetic insert failed.');
            update_post_meta($id, '_seo_tidy_phase_c_synthetic', $number);
            update_post_meta($id, '_seo_tidy_smart_topics_v1', ['version' => 1,
                'approved' => $data['topics'], 'rejected' => []]);
        }
        $ids = phase_c_ids();
        $words = $vectors = $frequencies = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            preg_match_all('/[\p{L}\p{N}]+/u', wp_strip_all_tags($post->post_content), $matches);
            $words[] = count($matches[0]);
            $terms = Preview::indexTerms($post);
            $vectors[] = count($terms);
            $frequencies[] = array_sum($terms);
        }
        phase_c_assert(count($ids) === $target, 'Synthetic corpus size mismatch.');
        phase_c_assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page')") === $target,
            'Published corpus includes unexpected content.');
        phase_c_json(['mode' => $mode, 'seed_ms' => (microtime(true) - $start) * 1000,
            'count' => count($ids), 'first_id' => reset($ids), 'last_id' => end($ids),
            'body_words' => phase_c_summary($words), 'retained_distinct_terms' => phase_c_summary($vectors),
            'weighted_vector_length' => phase_c_summary($frequencies), 'state' => Index::state(),
            'note' => 'Seeded only; the real administrator scan must build the index separately.']);
    } elseif ($mode === 'measure') {
        global $wpdb;
        $state = Index::state();
        phase_c_assert(!empty($state['complete']) && empty($state['stale']), 'Index is not ready.');
        $ids = phase_c_ids();
        phase_c_assert(in_array(count($ids), [200, 1000], true), 'Expected 200 or 1000 synthetic posts.');
        // The same first ten sources cover alternating EN/LV and five content themes.
        $sources = array_slice($ids, 0, 10);
        $samples = $candidateMs = $suggestionMs = $candidateQueries = $suggestionQueries = [];
        $firstSql = '';
        $capture = static function ($sql) use (&$firstSql) {
            if (!$firstSql && str_contains($sql, 'SELECT t.post_id') && str_contains($sql, 'seo_tidy_link_terms')) {
                $firstSql = $sql;
            }
            return $sql;
        };
        add_filter('query', $capture);
        foreach ($sources as $id) {
            $post = get_post($id);
            $topics = QvarcY\SeoTidy\SmartTopics::approved($id);
            $taxonomy = wp_get_post_terms($id, ['category', 'post_tag'], ['fields' => 'names']);
            phase_c_assert(!is_wp_error($taxonomy), 'Taxonomy query failed.');
            $terms = Index::queryTerms(Preview::termTokens($post->post_title . ' ' .
                implode(' ', array_merge($taxonomy, $topics))));
            $q0 = $wpdb->num_queries;
            $t0 = hrtime(true);
            $candidates = Index::candidateIds($terms, $id);
            $candidateElapsed = (hrtime(true) - $t0) / 1e6;
            $cq = $wpdb->num_queries - $q0;
            $q0 = $wpdb->num_queries;
            $t0 = hrtime(true);
            $suggestions = Preview::suggestionsForPost($post);
            $suggestionElapsed = (hrtime(true) - $t0) / 1e6;
            $sq = $wpdb->num_queries - $q0;
            $samples[] = ['source_id' => $id, 'source_title' => $post->post_title,
                'query_terms' => $terms, 'candidate_count' => count($candidates),
                'candidate_ms' => $candidateElapsed, 'candidate_queries' => $cq,
                'suggestion_ms' => $suggestionElapsed, 'suggestion_queries' => $sq,
                'suggestion_ids' => array_map(static fn($item) => $item['post']->ID, $suggestions),
                'scores' => array_column($suggestions, 'score')];
            $candidateMs[] = $candidateElapsed;
            $suggestionMs[] = $suggestionElapsed;
            $candidateQueries[] = $cq;
            $suggestionQueries[] = $sq;
        }
        remove_filter('query', $capture);
        $explain = $firstSql ? $wpdb->get_results('EXPLAIN ' . $firstSql, ARRAY_A) : [];
        phase_c_json(['mode' => $mode, 'stats' => phase_c_stats(),
            'candidate_ms' => phase_c_summary($candidateMs),
            'suggestion_ms' => phase_c_summary($suggestionMs),
            'candidate_queries' => phase_c_summary($candidateQueries),
            'suggestion_queries' => phase_c_summary($suggestionQueries),
            'memory_current_bytes' => memory_get_usage(true), 'memory_peak_bytes' => memory_get_peak_usage(true),
            'candidate_sql' => $firstSql, 'candidate_explain' => $explain, 'samples' => $samples,
            'conditions' => ['sources' => 'first 10 deterministic EN/LV posts', 'repetitions' => 1,
                'cache' => 'one CLI process; initial runtime cache and subsequent warmed objects mixed; no persistent object cache asserted',
                'scope' => 'production methods in PHP; excludes HTTP and WordPress bootstrap time',
                'score' => 'BM25 ranking signal only, not accuracy percentage']]);
    } elseif ($mode === 'race-prepare') {
        $ids = phase_c_ids();
        phase_c_assert(count($ids) === 1000, 'Race preparation requires the completed 1000-post workload.');
        $state = Index::state();
        phase_c_assert(!empty($state['complete']) && empty($state['stale']), 'Run workload measurement before race preparation.');
        $t0 = microtime(true);
        foreach (array_slice($ids, 25) as $id) {
            $topicsBefore = get_post_meta($id, '_seo_tidy_smart_topics_v1', true);
            $result = wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
            phase_c_assert(!is_wp_error($result), "Draft transition failed for {$id}.");
            phase_c_assert($topicsBefore === get_post_meta($id, '_seo_tidy_smart_topics_v1', true),
                "Topic metadata changed for {$id}.");
        }
        $remaining = phase_c_ids();
        phase_c_assert(count($remaining) === 25, 'Race corpus must contain exactly 25 published synthetic posts.');
        phase_c_assert(!empty(Index::state()['stale']), 'Status transitions did not invalidate the index.');
        phase_c_json(['mode' => $mode, 'published_synthetic' => count($remaining), 'transitioned_to_draft' => 975,
            'first_post_id' => reset($remaining), 'last_post_id' => end($remaining),
            'duration_ms' => (microtime(true) - $t0) * 1000, 'state' => Index::state(),
            'smart_topics_preserved' => true,
            'note' => 'Disposable fixture preparation after both benchmarks; no rebuild performed.']);
    } elseif ($mode === 'race-scan') {
        global $wpdb;
        $worker = $argv[2] ?? '';
        $paths = phase_c_race_paths($worker);
        phase_c_assert(!file_exists($paths['ready']) && !file_exists($paths['release']), 'Use a fresh worker name.');
        $administrator = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        phase_c_assert(!empty($administrator), 'No administrator account.');
        wp_set_current_user((int) $administrator[0]);
        $_POST = ['_wpnonce' => wp_create_nonce('seo_tidy_link_scan')];
        if (in_array('--restart', $argv, true)) {
            $_POST['restart'] = '1';
        }
        $_REQUEST = $_POST;
        $table = $wpdb->prefix . 'seo_tidy_link_terms';
        $paused = false;
        $errors = [];
        $t0 = microtime(true);
        $filter = static function ($sql) use ($table, &$paused, $paths, $worker, &$errors) {
            global $wpdb;
            if ($wpdb->last_error !== '') {
                $errors[] = ['sql' => $wpdb->last_query, 'message' => $wpdb->last_error];
            }
            if (!$paused && preg_match('/^INSERT INTO\s+' . preg_quote($table, '/') . '\s+\(post_id,term,frequency\)\s+(?:VALUES\s*\(|SELECT\s+batch\.post_id,batch\.term,batch\.frequency\s+FROM\s*\(SELECT\s+)(\d+)(?:,|\s+AS\s+post_id)/i', $sql, $match)) {
                $paused = true;
                $postId = (int) $match[1];
                $ready = ['worker' => $worker, 'post_id' => $postId,
                    'stage' => 'before first posting INSERT, after state/read/delete',
                    'state' => Index::state(), 'snapshot_content_sha256' => hash('sha256', get_post($postId)->post_content),
                    'captured_terms' => Preview::indexTerms(get_post($postId)), 'time' => microtime(true)];
                file_put_contents($paths['ready'], json_encode($ready, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $deadline = microtime(true) + 45;
                while (!is_file($paths['release'])) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Race barrier timed out after 45 seconds.');
                    }
                    usleep(20000);
                }
            }
            return $sql;
        };
        add_filter('query', $filter);
        set_error_handler(static function ($severity, $message) use (&$errors) {
            if (str_contains($message, 'WordPress database error')) {
                $errors[] = $message;
            }
            return false;
        });
        register_shutdown_function(static function () use ($paths, $worker, &$paused, &$errors, $t0) {
            global $wpdb;
            $result = ['worker' => $worker, 'paused' => $paused, 'duration_ms' => (microtime(true) - $t0) * 1000,
                'queries' => $wpdb->num_queries, 'last_db_error' => $wpdb->last_error,
                'captured_db_errors' => $errors, 'last_php_error' => error_get_last(), 'stats' => phase_c_stats()];
            file_put_contents($paths['result'], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        });
        Index::scan();
    } elseif ($mode === 'race-invalidate') {
        $postId = (int) ($argv[2] ?? 0);
        $post = get_post($postId);
        phase_c_assert($postId > 0 && $post instanceof WP_Post &&
            $post->post_status === 'publish' && get_post_meta($postId, '_seo_tidy_phase_c_synthetic', true) !== '',
            'Expected a published synthetic race post.');
        $bodyHash = hash('sha256', $post->post_content);
        $topics = get_post_meta($postId, '_seo_tidy_smart_topics_v1', true);
        $updated = wp_update_post(['ID' => $postId,
            'post_excerpt' => 'Phase C controlled preflight invalidation'], true);
        phase_c_assert(!is_wp_error($updated), 'Race preflight invalidation edit failed.');
        phase_c_assert(!empty(Index::state()['stale']), 'Preflight edit did not invalidate the index.');
        phase_c_assert($bodyHash === hash('sha256', get_post($postId)->post_content) &&
            $topics === get_post_meta($postId, '_seo_tidy_smart_topics_v1', true),
            'Preflight edit changed the body or Smart Topics.');
        phase_c_json(['mode' => $mode, 'post_id' => $postId, 'state' => Index::state(),
            'content_sha256' => $bodyHash, 'body_and_topics_preserved' => true,
            'snapshot' => Index::entry($postId)]);
    } elseif ($mode === 'race-edit') {
        $postId = (int) ($argv[2] ?? 0);
        phase_c_assert($postId > 0 && get_post($postId) instanceof WP_Post, 'Unknown race edit post.');
        $old = get_post($postId);
        $updated = wp_update_post(['ID' => $postId,
            'post_content' => '<p>Phasecconcurrencynewtoken revisedcontent evidence replacement.</p>'], true);
        phase_c_assert(!is_wp_error($updated), 'Race edit failed.');
        phase_c_json(['mode' => $mode, 'post_id' => $postId, 'before_sha256' => hash('sha256', $old->post_content),
            'after_sha256' => hash('sha256', get_post($postId)->post_content),
            'state' => Index::state(), 'snapshot' => Index::entry($postId)]);
    } elseif ($mode === 'race-report') {
        global $wpdb;
        $postId = (int) ($argv[2] ?? 0);
        phase_c_assert($postId > 0 && get_post($postId) instanceof WP_Post, 'Unknown race report post.');
        $table = $wpdb->prefix . 'seo_tidy_link_terms';
        $stored = $wpdb->get_results($wpdb->prepare("SELECT term,frequency FROM {$table} WHERE post_id=%d ORDER BY term", $postId), ARRAY_A);
        $actual = [];
        foreach ($stored as $row) {
            $actual[$row['term']] = (int) $row['frequency'];
        }
        $expected = Preview::indexTerms(get_post($postId));
        ksort($actual);
        ksort($expected);
        $duplicates = $wpdb->get_results("SELECT post_id,term,COUNT(*) AS n FROM {$table} GROUP BY post_id,term HAVING n>1", ARRAY_A);
        phase_c_json(['mode' => $mode, 'post_id' => $postId, 'stats' => phase_c_stats(),
            'snapshot' => Index::entry($postId), 'current_content_sha256' => hash('sha256', get_post($postId)->post_content),
            'stored_matches_current_vector' => $actual === $expected,
            'missing_current_terms' => array_diff_key($expected, $actual),
            'obsolete_stored_terms' => array_diff_key($actual, $expected),
            'duplicate_primary_keys' => $duplicates, 'sql_error' => $wpdb->last_error]);
    } else {
        throw new RuntimeException('Use seed200, seed1000, measure, race-prepare, race-scan WORKER, race-invalidate POST_ID, race-edit POST_ID or race-report POST_ID.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
