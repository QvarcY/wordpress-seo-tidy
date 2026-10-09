<?php

defined('ABSPATH') || exit;

use QvarcY\SeoTidy\SEOAudit;

function seo_tidy_audit_check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

seo_tidy_audit_check(
    class_exists(SEOAudit::class),
    'SEO audit class loaded'
);

$admins = get_users([
    'role' => 'administrator',
    'number' => 1,
]);

seo_tidy_audit_check(!empty($admins), 'Administrator exists');
seo_tidy_audit_check(
    SEOAudit::classifyIssues(['duplicate_title']) === 'attention',
    'Audit classification: attention'
);

seo_tidy_audit_check(
    SEOAudit::classifyIssues(['missing_description']) === 'recommendations',
    'Audit classification: recommendations'
);

seo_tidy_audit_check(
    SEOAudit::classifyIssues(['default_title']) === 'recommendations',
    'WordPress title fallback is optional'
);

seo_tidy_audit_check(
    SEOAudit::classifyIssues([]) === 'clear',
    'Audit classification: clear'
);

seo_tidy_audit_check(
    SEOAudit::classifyIssues([
        'review_h1',
        'site_noindex',
    ]) === 'attention',
    'Important findings take priority'
);

$previousUser = get_current_user_id();
$created = 0;

try {
    wp_set_current_user((int) $admins[0]->ID);

    $created = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => 'SEO-TidY audit smoke test',
        'post_content' => 'Temporary audit test content',
        'post_author' => (int) $admins[0]->ID,
    ], true);

    seo_tidy_audit_check(
        !is_wp_error($created) && $created > 0,
        'Temporary post created'
    );

    $request = new WP_REST_Request(
        'GET',
        '/seo-tidy/v1/audit'
    );
    $request->set_param('page', 1);

    $response = rest_do_request($request);

    seo_tidy_audit_check(
        $response->get_status() === 200,
        'Administrator can read audit'
    );

    $data = $response->get_data();

    seo_tidy_audit_check(
        isset($data['items']) &&
        is_array($data['items']) &&
        count($data['items']) <= 20,
        'Audit returns at most 20 items'
    );

    seo_tidy_audit_check(
        isset($data['page'], $data['pages'], $data['total']) &&
        (int) $data['page'] === 1 &&
        (int) $data['total'] >= 1,
        'Audit pagination metadata valid'
    );

    foreach ($data['items'] as $item) {
        seo_tidy_audit_check(
            isset($item['id'], $item['issues']) &&
            is_array($item['issues']),
            'Audit item structure valid'
        );
        seo_tidy_audit_check(
            isset($item['category']) &&
            in_array(
                $item['category'],
                ['attention', 'recommendations', 'clear'],
                true
            ) &&
            $item['category'] === SEOAudit::classifyIssues($item['issues']),
            'Audit API category matches issues'
        );
    }

    wp_set_current_user(0);

    $denied = rest_do_request($request);

    seo_tidy_audit_check(
        $denied->get_status() >= 400,
        'Anonymous audit access denied'
    );
} finally {
    if (is_int($created) && $created > 0) {
        wp_delete_post($created, true);
    }

    wp_set_current_user($previousUser);
}

echo 'AUDIT INTEGRATION TEST COMPLETE' . PHP_EOL;
echo 'INTEGRATION TEST COMPLETE' . PHP_EOL;