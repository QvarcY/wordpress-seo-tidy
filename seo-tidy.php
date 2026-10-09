<?php
/**
 * Plugin Name: SEO-TidY
 * Plugin URI: https://github.com/QvarcY/wordpress-seo-tidy
 * Update URI: https://github.com/QvarcY/wordpress-seo-tidy
 * Description: Free SEO and AI search readiness tools for WordPress.
 * Version: 0.1.0-beta.5
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: QvarcY
 * Author URI: https://github.com/QvarcY
 * License: GPL-2.0-or-later
 * Text Domain: seo-tidy
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('SEO_TIDY_VERSION', '0.1.0-beta.5');
define('SEO_TIDY_PATH', plugin_dir_path(__FILE__));

require_once SEO_TIDY_PATH . 'includes/Requirements.php';
require_once SEO_TIDY_PATH . 'includes/Bootstrap.php';

if (!\QvarcY\SeoTidy\Requirements::supported()) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'SEO-TidY requires WordPress 6.8 or newer and PHP 8.1 or newer.',
            'seo-tidy'
        );
        echo '</p></div>';
    });

    return;
}

\QvarcY\SeoTidy\Bootstrap::init();
