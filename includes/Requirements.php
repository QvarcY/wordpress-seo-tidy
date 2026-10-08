<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class Requirements
{
    public static function supported(): bool
    {
        global $wp_version;

        return version_compare(PHP_VERSION, '8.1', '>=')
            && version_compare($wp_version, '6.8', '>=');
    }
}
