<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/** Server-side moderation. The admin bearer token is never sent to the browser. */
final class WallModeration
{
    private const API = 'https://kas.id.lv/SEO-TidY/Wall-Of-Fame/api/';

    public static function init(): void
    {
        add_action('admin_menu', static function (): void {
            add_submenu_page('seo-tidy', __('Wall of Fame moderation', 'seo-tidy'),
                __('Moderation', 'seo-tidy'), 'manage_options',
                'seo-tidy-wall-moderation', [self::class, 'render']);
        }, 21);
        add_action('admin_post_seo_tidy_wall_moderate', [self::class, 'moderate']);
    }

    private static function token(): string
    {
        if (defined('SEO_TIDY_WALL_ADMIN_TOKEN') && is_string(SEO_TIDY_WALL_ADMIN_TOKEN)) {
            return SEO_TIDY_WALL_ADMIN_TOKEN;
        }
        // Same-account AREA installation only. Never load this private file on other domains.
        if (wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'kas.id.lv') return '';
        $path = '/home/kasidlv/seo-tidy-wall-of-fame-private/config.php';
        if (!is_file($path) || !is_readable($path)) return '';
        $data = require $path;
        return is_array($data) && is_string($data['admin_token'] ?? null) ? $data['admin_token'] : '';
    }

    private static function request(string $route, ?array $payload = null)
    {
        $token = self::token();
        if (strlen($token) < 32) {
            return new \WP_Error('moderation_unconfigured',
                __('Moderator credentials are not configured on this server.', 'seo-tidy'));
        }
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $headers['X-SEO-Tidy-Admin'] = '1';
        }
        $args = [
            'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            'headers' => $headers,
        ];
        if ($payload !== null) $args['body'] = wp_json_encode($payload);
        $response = $payload === null
            ? wp_remote_get(self::API.$route, $args)
            : wp_remote_post(self::API.$route, $args);
        if (is_wp_error($response)) return $response;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return new \WP_Error('invalid_reply', 'Invalid moderation response');
        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new \WP_Error('moderation_error', sanitize_text_field((string)($data['error'] ?? 'Moderation request failed')));
        }
        return $data;
    }

    public static function moderate(): void
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Access denied', 'seo-tidy'));
        check_admin_referer('seo_tidy_wall_moderate');
        $kind = sanitize_key((string)($_POST['kind'] ?? ''));
        $decision = sanitize_key((string)($_POST['decision'] ?? ''));
        $id = sanitize_text_field(wp_unslash((string)($_POST['id'] ?? '')));
        if (!in_array($kind, ['site', 'profile'], true) ||
            !in_array($decision, ['approve', 'reject'], true) ||
            ($kind === 'site' && !preg_match('/^[a-f0-9-]{36}$/D', $id)) ||
            ($kind === 'profile' && (!ctype_digit($id) || (int)$id < 1))) {
            wp_die(esc_html__('Invalid moderation request', 'seo-tidy'));
        }
        $route = $kind === 'site' ? 'admin/moderate' : 'admin/profile-moderate';
        $result = self::request($route, ['id' => $kind === 'profile' ? (int)$id : $id, 'decision' => $decision]);
        $feedback = is_wp_error($result) ? $result->get_error_message() : __('Moderation saved successfully', 'seo-tidy');
        set_transient('seo_tidy_wall_moderation_'.get_current_user_id(), $feedback, 90);
        wp_safe_redirect(admin_url('admin.php?page=seo-tidy-wall-moderation'));
        exit;
    }

    private static function buttons(string $kind, string $id): void
    {
        foreach (['approve', 'reject'] as $decision) {
            echo '<form method="post" style="display:inline-block;margin:6px 8px 6px 0" action="'.
                esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('seo_tidy_wall_moderate');
            echo '<input type="hidden" name="action" value="seo_tidy_wall_moderate">';
            echo '<input type="hidden" name="kind" value="'.esc_attr($kind).'">';
            echo '<input type="hidden" name="id" value="'.esc_attr($id).'">';
            echo '<input type="hidden" name="decision" value="'.esc_attr($decision).'">';
            echo '<button type="submit" class="button '.($decision === 'approve' ? 'button-primary' : '').'">'.
                esc_html($decision === 'approve' ? __('Approve', 'seo-tidy') : __('Reject', 'seo-tidy')).'</button>';
            echo '</form>';
        }
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>'.esc_html__('Wall of Fame moderation', 'seo-tidy').'</h1>';
        $message = get_transient('seo_tidy_wall_moderation_'.get_current_user_id());
        if (is_string($message)) {
            echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
            delete_transient('seo_tidy_wall_moderation_'.get_current_user_id());
        }
        $sites = self::request('admin/submissions');
        $profiles = self::request('admin/profiles');
        foreach ([$sites, $profiles] as $result) {
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>'.esc_html($result->get_error_message()).'</p></div>';
                echo '</div>';
                return;
            }
        }
        $pendingSites = array_values(array_filter($sites['items'] ?? [], static fn($site): bool =>
            in_array($site['status'] ?? '', ['pending', 'verified'], true)));
        $pendingProfiles = $profiles['items'] ?? [];
        echo '<p>'.esc_html(sprintf(__('Pending sites: %d · Pending profile edits: %d', 'seo-tidy'),
            count($pendingSites), count($pendingProfiles))).'</p>';
        echo '<h2>'.esc_html__('Website applications', 'seo-tidy').'</h2>';
        if (!$pendingSites) echo '<p>'.esc_html__('No pending applications', 'seo-tidy').'</p>';
        foreach ($pendingSites as $site) {
            echo '<div class="postbox" style="padding:16px;max-width:860px"><h3>'.
                esc_html((string)$site['name']).' — '.esc_html((string)$site['host']).'</h3><p>'.
                esc_html((string)$site['description']).'</p>';
            self::buttons('site', (string)$site['id']);
            echo '</div>';
        }
        echo '<h2>'.esc_html__('Pending profile edits', 'seo-tidy').'</h2>';
        if (!$pendingProfiles) echo '<p>'.esc_html__('No pending profile edits', 'seo-tidy').'</p>';
        foreach ($pendingProfiles as $profile) {
            echo '<div class="postbox" style="padding:16px;max-width:860px"><h3>'.
                esc_html((string)$profile['host']).' — '.esc_html((string)$profile['category']).'</h3>';
            echo '<p><strong>'.esc_html((string)$profile['short_description']).'</strong></p>';
            echo '<p style="white-space:pre-wrap">'.esc_html((string)$profile['long_description']).'</p>';
            $tags = json_decode((string)($profile['tags_json'] ?? '[]'), true);
            if (is_array($tags)) echo '<p>'.esc_html(implode(', ', array_filter($tags, 'is_string'))).'</p>';
            self::buttons('profile', (string)$profile['id']);
            echo '</div>';
        }
        echo '</div>';
    }
}
