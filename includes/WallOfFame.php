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
        add_action('admin_menu', static function (): void {
            add_submenu_page('seo-tidy', __('Wall of Fame profile', 'seo-tidy'),
                __('Wall of Fame profile', 'seo-tidy'), 'manage_options',
                'seo-tidy-wall-profile', [self::class, 'profileEditor']);
        }, 20);
        add_action('admin_post_seo_tidy_wall_profile_save', [self::class, 'saveProfile']);
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
            register_rest_route('seo-tidy/v1', '/wall/profile', [
                'methods' => 'GET',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'profile'],
            ]);
            register_rest_route('seo-tidy/v1', '/wall/profile', [
                'methods' => 'POST',
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
                'callback' => [self::class, 'updateProfile'],
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
            $current = self::status();
            if (is_wp_error($current)) return $current;
            if (($current['status'] ?? '') !== 'not_joined') {
                return new \WP_Error('already_registered', 'This WordPress site has already applied', ['status' => 409]);
            }
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

    private static function isMissingApplication($result): bool
    {
        return is_wp_error($result)
            && $result->get_error_code() === 'wall_remote'
            && (int) ($result->get_error_data()['status'] ?? 0) === 404;
    }

    private static function clearRegistration(): void
    {
        delete_option(self::REGISTRATION_OPTION);
        update_option('seo_tidy_community_opt_in', false, false);
    }

    public static function status()
    {
        $credentials = get_option(self::REGISTRATION_OPTION, []);
        if (empty($credentials['id']) || empty($credentials['secret'])) return ['status' => 'not_joined'];
        $result = self::remote('status', [
            'id' => $credentials['id'], 'secret' => $credentials['secret'],
        ]);
        if (self::isMissingApplication($result)) {
            self::clearRegistration();
            return ['status' => 'not_joined'];
        }
        return $result;
    }

    private static function credentials(): array
    {
        $creds = get_option(self::REGISTRATION_OPTION, []);
        return is_array($creds) ? $creds : [];
    }

    public static function profile()
    {
        $creds = self::credentials();
        if (empty($creds['id']) || empty($creds['secret'])) {
            return new \WP_Error('not_joined', 'Join the Wall of Fame first', ['status' => 409]);
        }
        return self::remote('profile', ['id' => $creds['id'], 'secret' => $creds['secret']]);
    }

    public static function updateProfile(\WP_REST_Request $request)
    {
        $creds = self::credentials();
        if (empty($creds['id']) || empty($creds['secret'])) {
            return new \WP_Error('not_joined', 'Join the Wall of Fame first', ['status' => 409]);
        }
        $tags = $request->get_param('tags');
        if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
        if (!is_array($tags)) $tags = [];
        $tags = array_values(array_filter(array_map('sanitize_text_field', $tags)));
        return self::remote('profile/update', [
            'id' => $creds['id'], 'secret' => $creds['secret'],
            'shortDescription' => sanitize_text_field((string) $request->get_param('shortDescription')),
            'longDescription' => sanitize_textarea_field((string) $request->get_param('longDescription')),
            'category' => sanitize_text_field((string) $request->get_param('category')),
            'tags' => $tags,
            'locale' => in_array($request->get_param('locale'), ['lv','en'],true) ? $request->get_param('locale') : 'lv',
            'country' => strtoupper(sanitize_text_field((string) ($request->get_param('country') ?: 'LV'))),
        ]);
    }

    public static function saveProfile(): void
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed', 'seo-tidy'));
        check_admin_referer('seo_tidy_wall_profile');
        $input = new \WP_REST_Request('POST', '/seo-tidy/v1/wall/profile');
        foreach (['shortDescription','longDescription','category','tags','locale','country'] as $key) {
            $input->set_param($key, wp_unslash($_POST[$key] ?? ''));
        }
        $result = self::updateProfile($input);
        $state = is_wp_error($result) ? 'error' : 'pending';
        set_transient('seo_tidy_wall_profile_feedback_'.get_current_user_id(),
            is_wp_error($result) ? $result->get_error_message() : __('Profile submitted for review', 'seo-tidy'), 90);
        wp_safe_redirect(admin_url('admin.php?page=seo-tidy-wall-profile&state='.$state));
        exit;
    }

    public static function profileEditor(): void
    {
        if (!current_user_can('manage_options')) return;
        $data = self::profile();
        echo '<div class="wrap"><h1>'.esc_html__('Wall of Fame profile','seo-tidy').'</h1>';
        $flash = get_transient('seo_tidy_wall_profile_feedback_'.get_current_user_id());
        if (is_string($flash)) {
            echo '<div class="notice notice-info"><p>'.esc_html($flash).'</p></div>';
            delete_transient('seo_tidy_wall_profile_feedback_'.get_current_user_id());
        }
        if (is_wp_error($data)) {
            echo '<p>'.esc_html($data->get_error_message()).'</p></div>';
            return;
        }
        $profile = $data['latest'] ?? $data['published'] ?? [];
        if (!is_array($profile)) $profile = [];
        $tags = json_decode((string)($profile['tags_json'] ?? '[]'),true);
        if (!is_array($tags)) $tags = [];
        if (($data['latest']['state'] ?? '') === 'pending') {
            echo '<p><strong>'.esc_html__('Your most recent profile changes are awaiting approval.','seo-tidy').'</strong></p>';
        }
        echo '<p>'.esc_html__('Describe your website accurately. Avoid keyword stuffing. Changes are reviewed before publication.','seo-tidy').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('seo_tidy_wall_profile');
        echo '<input type="hidden" name="action" value="seo_tidy_wall_profile_save">';
        $fields = [
            'shortDescription'=>[__('Short description (30–180 characters)','seo-tidy'),$profile['short_description'] ?? '', 'input'],
            'longDescription'=>[__('Extended description (300–2000 characters)','seo-tidy'),$profile['long_description'] ?? '', 'textarea'],
            'category'=>[__('Category','seo-tidy'),$profile['category'] ?? '', 'input'],
            'tags'=>[__('Tags (up to 8, separated by commas)','seo-tidy'),implode(', ', $tags),'input'],
            'locale'=>[__('Language (lv or en)','seo-tidy'),$profile['locale'] ?? 'lv','input'],
            'country'=>[__('Country code','seo-tidy'),$profile['country'] ?? 'LV','input'],
        ];
        foreach ($fields as $key=>$field) {
            echo '<p><label for="'.esc_attr($key).'"><strong>'.esc_html($field[0]).'</strong></label><br>';
            if ($field[2] === 'textarea') {
                echo '<textarea id="'.esc_attr($key).'" name="'.esc_attr($key).'" rows="8" cols="85" maxlength="2000">'.esc_textarea($field[1]).'</textarea>';
            } else {
                echo '<input class="regular-text" type="text" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($field[1]).'">';
            }
            echo '</p>';
        }
        submit_button(__('Submit profile for review','seo-tidy'));
        echo '</form></div>';
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
        if (self::isMissingApplication($response)) {
            self::clearRegistration();
            return ['removed' => true];
        }
        if (is_wp_error($response)) return $response;
        self::clearRegistration();
        return ['removed' => true];
    }
}
