<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/**
 * Private editorial topic labels. Suggestions never change public tags.
 */
final class SmartTopics
{
    private const META = '_seo_tidy_smart_topics_v1';

    public static function init(): void
    {
        if (!defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
            return;
        }
        add_action('add_meta_boxes', [self::class, 'registerBox']);
        add_action('save_post', [self::class, 'save'], 10, 2);
        add_action('admin_post_seo_tidy_topics_review', [self::class, 'saveReview']);
    }

    public static function registerBox(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        foreach (['post', 'page'] as $type) {
            add_meta_box('seo-tidy-smart-topics', __('SEO-TidY Smart Topics', 'seo-tidy'),
                [self::class, 'renderBox'], $type, 'side', 'default');
        }
    }

    private static function normalize(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, 64, 'UTF-8') : substr($value, 0, 64);
    }

    private static function key(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function listFromText(string $text): array
    {
        $out = [];
        foreach (preg_split('/[,;\r\n]+/u', $text) ?: [] as $item) {
            $label = self::normalize($item);
            if ($label !== '') {
                $out[self::key($label)] = $label;
            }
            if (count($out) >= 12) {
                break;
            }
        }
        return array_values($out);
    }

    public static function approved(int $postId): array
    {
        $data = get_post_meta($postId, self::META, true);
        return is_array($data) && isset($data['approved']) && is_array($data['approved'])
            ? array_slice(array_values(array_filter($data['approved'], 'is_string')), 0, 12)
            : [];
    }

    public static function suggestions(\WP_Post $post): array
    {
        $data = get_post_meta($post->ID, self::META, true);
        $rejected = is_array($data) && isset($data['rejected']) && is_array($data['rejected'])
            ? $data['rejected'] : [];
        $used = array_fill_keys(array_map([self::class, 'key'], self::approved($post->ID)), true);
        $blocked = array_fill_keys(array_map([self::class, 'key'], $rejected), true);
        $names = wp_get_post_terms($post->ID, ['category', 'post_tag'], ['fields' => 'names']);
        if (is_wp_error($names)) {
            $names = [];
        }
        $candidates = array_merge($names, [$post->post_title]);
        $out = [];
        foreach ($candidates as $name) {
            $label = self::normalize((string) $name);
            $key = self::key($label);
            if ($label === '' || isset($used[$key]) || isset($blocked[$key])) {
                continue;
            }
            $out[$key] = $label;
            if (count($out) >= 12) {
                break;
            }
        }
        return array_values($out);
    }

    public static function renderBox(\WP_Post $post): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_nonce_field('seo_tidy_smart_topics_' . $post->ID, 'seo_tidy_smart_topics_nonce');
        $approved = self::approved($post->ID);
        echo '<p>' . esc_html__('Approved topics (comma separated):', 'seo-tidy') . '</p>';
        echo '<textarea name="seo_tidy_smart_topics_approved" rows="3" style="width:100%">' .
            esc_textarea(implode(', ', $approved)) . '</textarea>';
        $suggestions = self::suggestions($post);
        echo '<p>' . esc_html__('Suggested topics — select to approve:', 'seo-tidy') . '</p>';
        foreach ($suggestions as $label) {
            echo '<label style="display:block"><input type="checkbox" name="seo_tidy_smart_topics_accept[]" value="' .
                esc_attr($label) . '"> ' . esc_html($label) . '</label>';
        }
        echo '<p>' . esc_html__('Rejected topics (comma separated; will not be suggested again):', 'seo-tidy') . '</p>';
        $data = get_post_meta($post->ID, self::META, true);
        $rejected = is_array($data) && isset($data['rejected']) && is_array($data['rejected'])
            ? $data['rejected'] : [];
        echo '<textarea name="seo_tidy_smart_topics_rejected" rows="2" style="width:100%">' .
            esc_textarea(implode(', ', $rejected)) . '</textarea>';
        echo '<p><small>' . esc_html__('Saving the article saves these private topics. They do not create public tag pages.', 'seo-tidy') . '</small></p>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['seo_tidy_smart_topics_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['seo_tidy_smart_topics_nonce'])),
                'seo_tidy_smart_topics_' . $postId) ||
            !current_user_can('manage_options') || !current_user_can('edit_post', $postId) ||
            wp_is_post_revision($postId) || wp_is_post_autosave($postId) ||
            !in_array($post->post_type, ['post', 'page'], true)) {
            return;
        }
        $approvedRaw = isset($_POST['seo_tidy_smart_topics_approved']) &&
            is_string($_POST['seo_tidy_smart_topics_approved'])
            ? wp_unslash($_POST['seo_tidy_smart_topics_approved']) : '';
        $rejectedRaw = isset($_POST['seo_tidy_smart_topics_rejected']) &&
            is_string($_POST['seo_tidy_smart_topics_rejected'])
            ? wp_unslash($_POST['seo_tidy_smart_topics_rejected']) : '';
        $approved = self::listFromText($approvedRaw);
        $rejected = self::listFromText($rejectedRaw);
        if (isset($_POST['seo_tidy_smart_topics_accept']) &&
            is_array($_POST['seo_tidy_smart_topics_accept'])) {
            $allowed = array_fill_keys(self::suggestions($post), true);
            foreach (array_slice(wp_unslash($_POST['seo_tidy_smart_topics_accept']), 0, 12) as $choice) {
                if (is_string($choice) && isset($allowed[$choice])) {
                    $approved[] = $choice;
                }
            }
        }
        $approved = self::listFromText(implode(',', $approved));
        $approvedKeys = array_fill_keys(array_map([self::class, 'key'], $approved), true);
        $rejected = array_values(array_filter($rejected, static fn($x) => !isset($approvedKeys[self::key($x)])));
        update_post_meta($postId, self::META, [
            'version' => 1, 'approved' => $approved, 'rejected' => $rejected
        ]);
    }
    public static function renderReviewPanel(\WP_Post $post): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $approved = self::approved($post->ID);
        $proposals = self::suggestions($post);
        $data = get_post_meta($post->ID, self::META, true);
        $rejected = is_array($data) && !empty($data['rejected']) && is_array($data['rejected'])
            ? $data['rejected'] : [];
        echo '<details><summary>' . esc_html__('Review Smart Topics', 'seo-tidy') . '</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="seo_tidy_topics_review">';
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post->ID) . '">';
        wp_nonce_field('seo_tidy_topics_review_' . $post->ID);
        echo '<p><label>' . esc_html__('Approved topics', 'seo-tidy') .
            '<br><input type="text" name="approved" style="width:100%;max-width:480px" value="' .
            esc_attr(implode(', ', $approved)) . '"></label></p>';
        echo '<p>' . esc_html__('Suggested topics', 'seo-tidy') . '</p>';
        foreach ($proposals as $proposal) {
            echo '<label style="margin-right:16px"><input type="checkbox" name="accept[]" value="' .
                esc_attr($proposal) . '"> ' . esc_html($proposal) . '</label>';
        }
        echo '<p><label>' . esc_html__('Rejected topics', 'seo-tidy') .
            '<br><input type="text" name="rejected" style="width:100%;max-width:480px" value="' .
            esc_attr(implode(', ', $rejected)) . '"></label></p>';
        submit_button(__('Save topics', 'seo-tidy'), 'secondary', 'submit', false);
        echo '</form></details>';
    }

    public static function saveReview(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'seo-tidy'));
        }
        $id = isset($_POST['post_id']) && is_scalar($_POST['post_id'])
            ? absint($_POST['post_id']) : 0;
        $post = get_post($id);
        if (!$post instanceof \WP_Post ||
            !in_array($post->post_type, ['post', 'page'], true) ||
            !current_user_can('edit_post', $id)) {
            wp_die(esc_html__('Invalid article.', 'seo-tidy'));
        }
        check_admin_referer('seo_tidy_topics_review_' . $id);
        $old = get_post_meta($id, self::META, true);
        $approved = isset($_POST['approved']) && is_string($_POST['approved'])
            ? self::listFromText(wp_unslash($_POST['approved'])) : [];
        $rejected = isset($_POST['rejected']) && is_string($_POST['rejected'])
            ? self::listFromText(wp_unslash($_POST['rejected'])) : [];
        $choices = isset($_POST['accept']) && is_array($_POST['accept'])
            ? array_slice(wp_unslash($_POST['accept']), 0, 12) : [];
        $allowed = array_fill_keys(self::suggestions($post), true);
        foreach ($choices as $choice) {
            if (is_string($choice) && isset($allowed[$choice])) {
                $approved[] = $choice;
            }
        }
        $approved = self::listFromText(implode(',', $approved));
        $approvedKeys = array_fill_keys(array_map([self::class, 'key'], $approved), true);
        $rejected = array_values(array_filter($rejected, static fn($t) => !isset($approvedKeys[self::key($t)])));
        $updated = ['version' => 1, 'approved' => $approved, 'rejected' => $rejected];
        if ($updated !== $old) {
            update_post_meta($id, self::META, $updated);
            InternalLinkingIndex::invalidate($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=seo-tidy-linking-preview&topics_saved=1'));
        exit;
    }

}
