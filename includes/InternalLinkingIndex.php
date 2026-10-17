<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/** Explicit administrator-driven, resumable metadata indexing. */
final class InternalLinkingIndex
{
    private const STATE = 'seo_tidy_link_index_state_v1';
    private const META = '_seo_tidy_link_index_v1';
    private const SIZE = 25;

    public static function init(): void
    {
        if (!defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
            return;
        }
        add_action('admin_post_seo_tidy_link_scan', [self::class, 'scan']);
        add_action('save_post', [self::class, 'invalidate'], 20, 1);
        add_action('before_delete_post', [self::class, 'invalidate'], 20, 1);
        add_action('set_object_terms', [self::class, 'invalidate'], 20, 1);
    }

    public static function state(): array
    {
        $state = get_option(self::STATE, []);
        return is_array($state) ? $state : [];
    }

    public static function invalidate(int $postId): void
    {
        if (get_post_type($postId) && in_array(get_post_type($postId), ['post', 'page'], true)) {
            delete_post_meta($postId, self::META);
            $state = self::state();
            $state['stale'] = true;
            update_option(self::STATE, $state, false);
        }
    }

    public static function scan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        check_admin_referer('seo_tidy_link_scan');
        $state = self::state();
        if (!empty($_POST['restart'])) {
            $state = [];
        }
        if (!empty($state['complete']) && empty($state['stale'])) {
            wp_safe_redirect(admin_url('admin.php?page=seo-tidy-linking-preview&scan_status=complete'));
            exit;
        }
        if (!empty($state['stale'])) {
            $state = [];
        }
        $last = isset($state['last_id']) ? absint($state['last_id']) : 0;
        // Explicit cursor avoids offset shifts when articles change.
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('post','page')
             AND post_status = 'publish' ORDER BY ID ASC LIMIT %d",
            $last, self::SIZE
        ));
        foreach ($ids as $id) {
            $id = absint($id);
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            if ($post->post_password !== '') {
                delete_post_meta($id, self::META);
                continue;
            }
            $tokens = InternalLinkingPreview::indexTerms($post);
            // Bounded, non-sensitive token counts only; never raw private content.
            update_post_meta($id, self::META, [
                'version' => 1,
                'tokens' => array_slice($tokens, 0, 300, true),
                'updated' => (int) get_post_modified_time('U', true, $post),
            ]);
        }
        $processed = isset($state['processed']) ? absint($state['processed']) : 0;
        $state = [
            'last_id' => $ids ? (int) end($ids) : $last,
            'processed' => $processed + count($ids),
            'complete' => count($ids) < self::SIZE,
            'stale' => false,
        ];
        update_option(self::STATE, $state, false);
        wp_safe_redirect(admin_url('admin.php?page=seo-tidy-linking-preview&scan_status=1'));
        exit;
    }

    public static function entry(int $postId): array
    {
        $entry = get_post_meta($postId, self::META, true);
        return is_array($entry) ? $entry : [];
    }
}
