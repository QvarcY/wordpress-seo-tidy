<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class GitHubUpdater
{
    private const REPOSITORY = 'QvarcY/wordpress-seo-tidy';

    public static function init(): void
    {
        add_filter(
            'update_plugins_github.com',
            [self::class, 'checkUpdate'],
            10,
            4
        );

        add_action(
            'load-update-core.php',
            [self::class, 'refreshOnManualCheck'],
            1
        );
    }

    public static function refreshOnManualCheck(): void
    {
        if (
            !isset($_GET['force-check']) ||
            $_GET['force-check'] !== '1' ||
            !current_user_can('update_plugins')
        ) {
            return;
        }

        delete_transient('seo_tidy_github_releases');
    }

    public static function checkUpdate(
        $update,
        array $pluginData,
        string $pluginFile,
        array $locales
    ) {
        unset($locales);

        if (
            $pluginFile !== plugin_basename(
                SEO_TIDY_PATH . 'seo-tidy.php'
            )
        ) {
            return $update;
        }

        $installed = (string) ($pluginData['Version'] ?? '');

        if ($installed === '') {
            return $update;
        }

        $releases = self::fetchReleases();

        if ($releases === null) {
            return $update;
        }

        $candidate = self::selectUpdate(
            $installed,
            $releases,
            (bool) get_option('seo_tidy_beta_updates', false)
        );

        if ($candidate === null) {
            return $update;
        }

        return [
            'slug' => 'seo-tidy',
            'version' => $candidate['version'],
            'url' => 'https://github.com/' . self::REPOSITORY,
            'package' => $candidate['package'],
            'requires_php' => '8.1',
            'autoupdate' => false,
        ];
    }

    private static function fetchReleases(): ?array
    {
        $cached = get_transient('seo_tidy_github_releases');

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' .
                self::REPOSITORY . '/releases?per_page=100',
            [
                'timeout' => 8,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $releases = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        if (!is_array($releases) || !array_is_list($releases)) {
            return null;
        }

        set_transient(
            'seo_tidy_github_releases',
            $releases,
            12 * HOUR_IN_SECONDS
        );

        return $releases;
    }


    public static function selectUpdate(
        string $installedVersion,
        array $releases,
        bool $allowBeta = false
    ): ?array {
        $best = null;

        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }

            $candidate = self::validateRelease($release);

            if ($candidate === null) {
                continue;
            }

            if ($candidate['prerelease'] && !$allowBeta) {
                continue;
            }

            if (
                version_compare(
                    $candidate['version'],
                    $installedVersion,
                    '<='
                )
            ) {
                continue;
            }

            if (
                $best === null ||
                version_compare(
                    $candidate['version'],
                    $best['version'],
                    '>'
                )
            ) {
                $best = $candidate;
            }
        }

        return $best;
    }

    public static function validateRelease(array $release): ?array
    {
        $tag = (string) ($release['tag_name'] ?? '');

        if (!preg_match(
            '/^v([0-9]+\.[0-9]+\.[0-9]+(?:-beta\.[0-9]+)?)$/',
            $tag,
            $matches
        )) {
            return null;
        }

        if (
            !empty($release['draft']) ||
            !empty($release['prerelease']) !==
                str_contains($matches[1], '-beta.')
        ) {
            return null;
        }

        $version = $matches[1];
        $expected = 'seo-tidy-' . $version . '.zip';

        foreach ((array) ($release['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');
            $url = (string) (
                $asset['browser_download_url'] ?? ''
            );

            $expectedUrl = sprintf(
                'https://github.com/%s/releases/download/%s/%s',
                self::REPOSITORY,
                $tag,
                $expected
            );

            if (
                $name === $expected &&
                $url === $expectedUrl
            ) {
                return [
                    'version' => $version,
                    'package' => $url,
                    'prerelease' => !empty($release['prerelease']),
                ];
            }
        }

        return null;
    }
}