<?php

namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

final class WallOfFame
{
    private const ENDPOINT = 'https://kas.id.lv/SEO-TidY/Wall-Of-Fame/api/';
    private const PROOF_OPTION = 'seo_tidy_wall_proof';
    private const REGISTRATION_OPTION = 'seo_tidy_wall_registration';

    public static function init(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('seo-tidy/v1', '/wall', [
                'methods' => 'GET',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'directory'],
            ]);
            register_rest_route('seo-tidy/v1', '/wall/join', [
                'methods' => 'POST',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'join'],
            ]);
            register_rest_route('seo-tidy/v1', '/wall/leave', [
                'methods' => 'POST',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'leave'],
            ]);
            register_rest_route('seo-tidy/v1', '/wall/proof', [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'proof'],
            ]);
            register_rest_route('seo-tidy/v1', '/wall/status', [
                'methods' => 'GET',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'status'],
            ]);
        });
    }

    private static function remote(string $route, ?array $data = null)
    {
        $args = [
            'timeout' => 12,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => ['Accept' => 'application/json'],
        ];
        if ($data !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($data);
        }
        $response = $data === null
            ? wp_remote_get(self::ENDPOINT . $route, $args)
            : wp_remote_post(self::ENDPOINT . $route, $args);
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return new \WP_Error('wall_response', 'Wall of Fame did not return valid JSON', ['status' => 502]);
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('wall_remote', sanitize_text_field($body['error'] ?? 'Wall of Fame unavailable'),
                ['status' => $code >= 500 ? 502 : $code]);
        }
        return $body;
    }

    public static function directory(\WP_REST_Request $request)
    {
        $page = max(1, min(100, absint($request->get_param('page') ?: 1)));
        return self::remote('sites?page=' . $page);
    }

    public static function proof()
    {
        $proof = get_option(self::PROOF_OPTION, []);
        if (!is_array($proof) || empty($proof['token']) || empty($proof['expires']) ||
            time() > (int) $proof['expires']) {
            return new \WP_Error('not_available', 'No active enrollment proof', ['status' => 404]);
        }
        return [
            'token' => $proof['token'],
            'site' => home_url('/'),
            'expires' => (int) $proof['expires'],
        ];
    }

    public static function join(\WP_REST_Request $request)
    {
        $existing = get_option(self::REGISTRATION_OPTION, []);
        if (is_array($existing) && !empty($existing['id'])) {
            return new \WP_Error('already_registered', 'This WordPress site has already applied', ['status' => 409]);
        }
        $consent = $request->get_param('consent');
        if ($consent !== true) {
            return new \WP_Error('consent_required', 'Explicit permission is required', ['status' => 400]);
        }
        $name = sanitize_text_field((string) get_option('seo_tidy_community_name', ''));
        $description = sanitize_textarea_field((string) get_option('seo_tidy_community_description', ''));
        if ($name === '') $name = sanitize_text_field(get_bloginfo('name'));
        if ($description === '') $description = sanitize_textarea_field(get_bloginfo('description'));
        $name = mb_substr($name, 0, 100);
        $description = mb_substr($description, 0, 300);
        if ($name === '' || $description === '') {
            return new \WP_Error('incomplete_profile', 'Enter a website name and description first', ['status' => 400]);
        }
        $url = home_url('/');
        if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return new \WP_Error('https_required', 'Your WordPress site must use HTTPS', ['status' => 400]);
        }
        $proof = ['token' => bin2hex(random_bytes(32)), 'expires' => time() + 300];
        update_option(self::PROOF_OPTION, $proof, false);
        $response = self::remote('wp-apply', [
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'proofUrl' => rest_url('seo-tidy/v1/wall/proof'),
            'proofToken' => $proof['token'],
            'consent' => true,
        ]);
        delete_option(self::PROOF_OPTION);
        if (is_wp_error($response)) return $response;
        if (empty($response['id']) || empty($response['ownerSecret'])) {
            return new \WP_Error('invalid_response', 'Wall of Fame registration incomplete', ['status' => 502]);
        }
        update_option(self::REGISTRATION_OPTION, [
            'id' => sanitize_text_field($response['id']),
            'secret' => sanitize_text_field($response['ownerSecret']),
        ], false);
        update_option('seo_tidy_community_opt_in', true, false);
        return ['status' => 'verified'];
    }

    public static function status()
    {
        $credentials = get_option(self::REGISTRATION_OPTION, []);
        if (empty($credentials['id']) || empty($credentials['secret'])) return ['status' => 'not_joined'];
        return self::remote('status', [
            'id' => $credentials['id'], 'secret' => $credentials['secret'],
        ]);
    }

    public static function leave()
    {
        $credentials = get_option(self::REGISTRATION_OPTION, []);
        if (empty($credentials['id']) || empty($credentials['secret'])) {
            return new \WP_Error('not_joined', 'No saved application', ['status' => 404]);
        }
        $response = self::remote('remove', [
            'id' => $credentials['id'], 'secret' => $credentials['secret'],
        ]);
        if (is_wp_error($response)) return $response;
        delete_option(self::REGISTRATION_OPTION);
        update_option('seo_tidy_community_opt_in', false, false);
        return ['removed' => true];
    }
}
