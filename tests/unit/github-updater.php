<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__, 2) .
    '/includes/GitHubUpdater.php';

use QvarcY\SeoTidy\GitHubUpdater;

function releaseFixture(
    string $version = '0.1.0-beta.4',
    bool $prerelease = true
): array {
    $tag = 'v' . $version;
    $file = 'seo-tidy-' . $version . '.zip';

    return [
        'tag_name' => $tag,
        'draft' => false,
        'prerelease' => $prerelease,
        'assets' => [
            [
                'name' => $file,
                'browser_download_url' =>
                    'https://github.com/QvarcY/wordpress-seo-tidy' .
                    '/releases/download/' . $tag . '/' . $file,
            ],
        ],
    ];
}

function check(
    string $name,
    bool $condition
): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL);
        exit(1);
    }

    echo 'PASS: ' . $name . PHP_EOL;
}

$beta = GitHubUpdater::validateRelease(releaseFixture());

check(
    'Official beta accepted',
    $beta !== null &&
    $beta['version'] === '0.1.0-beta.4' &&
    $beta['prerelease'] === true
);

$stable = GitHubUpdater::validateRelease(
    releaseFixture('0.1.0', false)
);

check(
    'Official stable accepted',
    $stable !== null &&
    $stable['version'] === '0.1.0' &&
    $stable['prerelease'] === false
);

$draft = releaseFixture();
$draft['draft'] = true;

check(
    'Draft rejected',
    GitHubUpdater::validateRelease($draft) === null
);

$wrongChannel = releaseFixture();
$wrongChannel['prerelease'] = false;

check(
    'Incorrect beta status rejected',
    GitHubUpdater::validateRelease($wrongChannel) === null
);

$wrongStable = releaseFixture('0.1.0', true);

check(
    'Incorrect stable status rejected',
    GitHubUpdater::validateRelease($wrongStable) === null
);

$wrongName = releaseFixture();
$wrongName['assets'][0]['name'] = 'source.zip';

check(
    'Unexpected archive name rejected',
    GitHubUpdater::validateRelease($wrongName) === null
);

$externalUrl = releaseFixture();
$externalUrl['assets'][0]['browser_download_url'] =
    'https://example.com/fake.zip';

check(
    'External archive URL rejected',
    GitHubUpdater::validateRelease($externalUrl) === null
);

$wrongRepository = releaseFixture();
$wrongRepository['assets'][0]['browser_download_url'] =
    'https://github.com/Other/Repository/releases/download/' .
    'v0.1.0-beta.4/seo-tidy-0.1.0-beta.4.zip';

check(
    'Wrong GitHub repository rejected',
    GitHubUpdater::validateRelease($wrongRepository) === null
);

$missingAssets = releaseFixture();
$missingAssets['assets'] = [];

check(
    'Missing archive rejected',
    GitHubUpdater::validateRelease($missingAssets) === null
);

$invalidVersion = releaseFixture();
$invalidVersion['tag_name'] = 'v0.1.0-beta.4-extra';

check(
    'Invalid version rejected',
    GitHubUpdater::validateRelease($invalidVersion) === null
);

$unsupportedVersion = releaseFixture('0.1.0-rc.1', true);

check(
    'Unsupported release channel rejected',
    GitHubUpdater::validateRelease($unsupportedVersion) === null
);

$malformedAsset = releaseFixture();
$malformedAsset['assets'] = ['invalid'];

check(
    'Malformed asset rejected',
    GitHubUpdater::validateRelease($malformedAsset) === null
);


$releases = [
    releaseFixture('0.1.0-beta.4', true),
    releaseFixture('0.1.0-beta.10', true),
    releaseFixture('0.1.0', false),
    releaseFixture('0.0.9', false),
];

$selected = GitHubUpdater::selectUpdate(
    '0.1.0-beta.3',
    $releases
);

check(
    'Stable channel chooses stable release',
    $selected !== null &&
    $selected['version'] === '0.1.0'
);

$selected = GitHubUpdater::selectUpdate(
    '0.1.0-beta.3',
    $releases,
    true
);

check(
    'Beta channel chooses highest version',
    $selected !== null &&
    $selected['version'] === '0.1.0'
);

$selected = GitHubUpdater::selectUpdate(
    '0.1.0-beta.3',
    [
        releaseFixture('0.1.0-beta.4', true),
        releaseFixture('0.1.0-beta.10', true),
    ],
    true
);

check(
    'Beta versions compared numerically',
    $selected !== null &&
    $selected['version'] === '0.1.0-beta.10'
);

check(
    'Beta excluded without opt-in',
    GitHubUpdater::selectUpdate(
        '0.1.0-beta.3',
        [releaseFixture('0.1.0-beta.4', true)],
        false
    ) === null
);

check(
    'Installed stable release not downgraded',
    GitHubUpdater::selectUpdate(
        '0.1.0',
        [releaseFixture('0.1.0-beta.10', true)],
        true
    ) === null
);

check(
    'Same version not offered',
    GitHubUpdater::selectUpdate(
        '0.1.0',
        [releaseFixture('0.1.0', false)]
    ) === null
);

check(
    'Malformed release ignored during selection',
    GitHubUpdater::selectUpdate(
        '0.1.0',
        ['invalid', ['tag_name' => 'invalid']]
    ) === null
);

$selected = GitHubUpdater::selectUpdate(
    '0.1.0',
    [
        releaseFixture('0.1.1', false),
        releaseFixture('0.2.0', false),
        releaseFixture('0.1.2', false),
    ]
);

check(
    'Newest stable release selected',
    $selected !== null &&
    $selected['version'] === '0.2.0'
);

echo PHP_EOL . 'ALL GITHUB UPDATER VALIDATION TESTS PASSED' .
    PHP_EOL;