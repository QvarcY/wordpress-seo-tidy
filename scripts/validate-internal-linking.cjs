// Local Phase C checks. No network, WordPress bootstrap, build or site database access.
const { spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
let failed = false;
function run(label, executable, args, env = process.env) {
    console.log(`\n${label}`);
    const result = spawnSync(executable, args, { cwd: root, env, encoding: 'utf8' });
    if (result.stdout) process.stdout.write(result.stdout);
    if (result.stderr) process.stderr.write(result.stderr);
    if (result.error) console.error(result.error.message);
    if (result.status !== 0 || result.error) failed = true;
}

for (const file of [
    'includes/InternalLinkingPreview.php', 'includes/InternalLinkingIndex.php',
    'includes/SmartTopics.php', 'includes/SEOAudit.php',
    'includes/InternalLinkingTranslations.php', 'includes/Bootstrap.php', 'seo-tidy.php',
    'tests/fixtures/evaluate-internal-linking.php',
    'tests/fixtures/internal-linking-test-bootstrap.php',
    'tests/fixtures/internal-linking-token-fallback.php',
    'tests/unit/internal-linking-index.php',
]) {
    run(`PHP syntax: ${file}`, php, ['-l', file]);
}

const isolated = { ...process.env };
delete isolated.SEO_TIDY_WP_CORE_ROOT;
delete isolated.SEO_TIDY_TEST_RUNTIME_ROOT;
run('Production PHP fixtures with SQLite/WordPress doubles', php,
    ['tests/fixtures/evaluate-internal-linking.php'], isolated);
run('Missing HTML API fails closed', php,
    ['tests/fixtures/evaluate-internal-linking.php', '--without-html'], isolated);
run('Latvian tokenization without optional extensions', php,
    ['-n', 'tests/fixtures/internal-linking-token-fallback.php', '--without-html'], isolated);
run('Index lifecycle and failure contracts', php, ['tests/unit/internal-linking-index.php'], isolated);
if (process.env.SEO_TIDY_WP_CORE_ROOT) {
    run('Fixtures with standalone WordPress HTML parser (routing/database still doubled)', php,
        ['tests/fixtures/evaluate-internal-linking.php'],
        { ...isolated, SEO_TIDY_WP_CORE_ROOT: process.env.SEO_TIDY_WP_CORE_ROOT });
} else {
    console.log('\nSKIP standalone WordPress parser: SEO_TIDY_WP_CORE_ROOT is unset.');
}
run('Existing updater unit checks', php, ['tests/unit/github-updater.php'], isolated);
run('Existing updater WordPress-boundary doubles', php, ['tests/unit/github-updater-wordpress.php'], isolated);
run('Existing Latvian catalog check', process.execPath, ['scripts/check-i18n.cjs'], isolated);
run('Whitespace validation', 'git', ['diff', '--check'], isolated);
console.log('\nNOT RUN: real WordPress/MySQL integration, browser UI, 200/1000-post benchmarks.');
console.log(failed ? 'Phase C local validation FAILED.' : 'Phase C local validation PASSED (limitations above).');
process.exitCode = failed ? 1 : 0;
