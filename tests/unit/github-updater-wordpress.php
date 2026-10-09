<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('SEO_TIDY_PATH', dirname(__DIR__, 2) . '/');

$GLOBALS['test_hooks'] = [];
$GLOBALS['test_actions'] = [];
$GLOBALS['test_can_update'] = false;
$GLOBALS['test_deleted'] = 0;
$GLOBALS['test_transient'] = false;
$GLOBALS['test_option'] = false;
$GLOBALS['test_response'] = null;
$GLOBALS['test_requests'] = 0;
$GLOBALS['test_cache_seconds'] = 0;

function add_filter($hook, $callback, $priority, $accepted): void
{
    $GLOBALS['test_hooks'][$hook] = [$callback, $accepted];
}

function add_action($hook, $callback, $priority = 10): void
{
    $GLOBALS['test_actions'][$hook] = [$callback, $priority];
}

function hasManualRefreshHook(): bool
{
    return isset($GLOBALS['test_actions']['load-update-core.php']) &&
        $GLOBALS['test_actions']['load-update-core.php'][1] === 1;
}

function current_user_can($capability): bool
{
    return $capability === 'update_plugins' &&
        $GLOBALS['test_can_update'];
}

function delete_transient($name): bool
{
    if ($name !== 'seo_tidy_github_releases') {
        throw new RuntimeException('Unexpected cache deletion');
    }

    ++$GLOBALS['test_deleted'];
    $GLOBALS['test_transient'] = false;
    return true;
}

function plugin_basename($path): string
{
    return 'seo-tidy/seo-tidy.php';
}

function get_option($name, $default = false)
{
    return $GLOBALS['test_option'];
}

function get_transient($name)
{
    return $GLOBALS['test_transient'];
}

function set_transient($name, $value, $seconds): void
{
    $GLOBALS['test_transient'] = $value;
    $GLOBALS['test_cache_seconds'] = $seconds;
}

class WP_Error
{
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function wp_remote_get($url, $arguments)
{
    ++$GLOBALS['test_requests'];

    if (
        $url !==
        'https://api.github.com/repos/' .
        'QvarcY/wordpress-seo-tidy/releases?per_page=100'
    ) {
        throw new RuntimeException('Unexpected API URL');
    }

    if (($arguments['timeout'] ?? null) !== 8) {
        throw new RuntimeException('Unexpected timeout');
    }

    return $GLOBALS['test_response'];
}

function wp_remote_retrieve_response_code($response): int
{
    return (int) ($response['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string
{
    return (string) ($response['body'] ?? '');
}

require_once SEO_TIDY_PATH . 'includes/GitHubUpdater.php';

use QvarcY\SeoTidy\GitHubUpdater;

function checkHook(string $name, bool $passed): void
{
    if (!$passed) {
        fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL);
        exit(1);
    }

    echo 'PASS: ' . $name . PHP_EOL;
}

function fixture(string $version, bool $beta): array
{
    $tag = 'v' . $version;
    $file = 'seo-tidy-' . $version . '.zip';

    return [
        'tag_name' => $tag,
        'draft' => false,
        'prerelease' => $beta,
        'assets' => [[
            'name' => $file,
            'browser_download_url' =>
                'https://github.com/QvarcY/wordpress-seo-tidy' .
                '/releases/download/' . $tag . '/' . $file,
        ]],
    ];
}

function setResponse($body, int $status = 200): void
{
    $GLOBALS['test_transient'] = false;
    $GLOBALS['test_response'] = [
        'code' => $status,
        'body' => is_string($body) ? $body : json_encode($body),
    ];
}

function updateFor(string $version = '0.1.0-beta.3')
{
    return GitHubUpdater::checkUpdate(
        false,
        ['Version' => $version],
        'seo-tidy/seo-tidy.php',
        ['lv_LV']
    );
}

GitHubUpdater::init();

checkHook(
    'Manual refresh hook registered',
    hasManualRefreshHook()
);

$GLOBALS['test_transient'] = ['cached'];
$_GET['force-check'] = '1';
GitHubUpdater::refreshOnManualCheck();

checkHook(
    'Unauthorized check leaves cache intact',
    $GLOBALS['test_deleted'] === 0 &&
    $GLOBALS['test_transient'] === ['cached']
);

$GLOBALS['test_can_update'] = true;
unset($_GET['force-check']);
GitHubUpdater::refreshOnManualCheck();

checkHook(
    'Normal visit leaves cache intact',
    $GLOBALS['test_deleted'] === 0 &&
    $GLOBALS['test_transient'] === ['cached']
);

$_GET['force-check'] = '1';
GitHubUpdater::refreshOnManualCheck();

checkHook(
    'Authorized manual check clears cache',
    $GLOBALS['test_deleted'] === 1 &&
    $GLOBALS['test_transient'] === false
);

unset($_GET['force-check']);

checkHook(
    'WordPress update hook registered',
    isset($GLOBALS['test_hooks']['update_plugins_github.com']) &&
    $GLOBALS['test_hooks']['update_plugins_github.com'][1] === 4
);

setResponse([fixture('0.1.0', false)]);

$update = updateFor();

checkHook(
    'Stable update offered to WordPress',
    is_array($update) &&
    $update['version'] === '0.1.0' &&
    $update['slug'] === 'seo-tidy' &&
    $update['autoupdate'] === false
);

checkHook(
    'Official ZIP supplied',
    $update['package'] ===
        'https://github.com/QvarcY/wordpress-seo-tidy/' .
        'releases/download/v0.1.0/seo-tidy-0.1.0.zip'
);

checkHook(
    'GitHub response cached for 12 hours',
    $GLOBALS['test_cache_seconds'] === 43200
);

$requests = $GLOBALS['test_requests'];
$update = updateFor();

checkHook(
    'Cached response avoids another request',
    is_array($update) &&
    $GLOBALS['test_requests'] === $requests
);

$update = GitHubUpdater::checkUpdate(
    false,
    ['Version' => '0.1.0-beta.3'],
    'different-plugin/plugin.php',
    []
);

checkHook(
    'Other plugins ignored',
    $update === false &&
    $GLOBALS['test_requests'] === $requests
);

checkHook(
    'Missing installed version ignored',
    GitHubUpdater::checkUpdate(
        false,
        [],
        'seo-tidy/seo-tidy.php',
        []
    ) === false
);

setResponse([fixture('0.1.0-beta.4', true)]);

checkHook(
    'Beta excluded by default',
    updateFor() === false
);

$GLOBALS['test_option'] = true;

$update = updateFor();

checkHook(
    'Beta offered with opt-in',
    is_array($update) &&
    $update['version'] === '0.1.0-beta.4'
);

$GLOBALS['test_option'] = false;
setResponse([fixture('0.1.0', false)]);

checkHook(
    'Installed version not downgraded',
    updateFor('0.2.0') === false
);

setResponse([], 403);

checkHook(
    'GitHub HTTP error handled',
    updateFor() === false
);

setResponse('{invalid');

checkHook(
    'Malformed JSON handled',
    updateFor() === false
);

setResponse(['not' => 'a list']);

checkHook(
    'Unexpected API structure rejected',
    updateFor() === false
);

$GLOBALS['test_transient'] = false;
$GLOBALS['test_response'] = new WP_Error();

checkHook(
    'Network error handled',
    updateFor() === false
);

echo PHP_EOL .
    'ALL WORDPRESS UPDATE INTEGRATION TESTS PASSED' .
    PHP_EOL;