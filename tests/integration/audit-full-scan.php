<?php

defined('ABSPATH') || exit;

$admins = get_users([
    'role' => 'administrator',
    'number' => 1,
]);

if (empty($admins)) {
    throw new RuntimeException('Administrator missing');
}

$previousUser = get_current_user_id();
$created = [];
wp_set_current_user((int) $admins[0]->ID);

try {
    for ($index = 1; $index <= 23; ++$index) {
        $id = wp_insert_post([
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => 'SEO-TidY pagination test ' . $index,
            'post_content' => 'Temporary pagination test content',
            'post_author' => (int) $admins[0]->ID,
        ], true);

        if (is_wp_error($id) || (int) $id <= 0) {
            throw new RuntimeException('Cannot create test post');
        }

        $created[] = (int) $id;
    }

    $page = 1;
    $processed = 0;
    $categories = [
        'attention' => 0,
        'recommendations' => 0,
        'clear' => 0,
    ];
    $seen = [];
    $total = null;

    do {
        $request = new WP_REST_Request(
            'GET',
            '/seo-tidy/v1/audit'
        );

        $request->set_param('page', $page);

        $response = rest_do_request($request);

        if ($response->get_status() !== 200) {
            throw new RuntimeException('Audit request failed');
        }

        $data = $response->get_data();

        if ($total === null) {
            $total = (int) $data['total'];
        } elseif ($total !== (int) $data['total']) {
            throw new RuntimeException('Audit total changed during scan');
        }

        foreach ($data['items'] as $item) {
            $id = (int) $item['id'];
            $category = $item['category'] ?? '';

            if (isset($seen[$id])) {
                throw new RuntimeException('Duplicate audit item');
            }

            if (!array_key_exists($category, $categories)) {
                throw new RuntimeException('Invalid category');
            }

            $seen[$id] = true;
            ++$categories[$category];
            ++$processed;
        }

        $pages = (int) $data['pages'];

        if ($pages < 0 || $page > max(1, $pages)) {
            throw new RuntimeException('Invalid pagination');
        }

        ++$page;
    } while ($page <= $pages);

    if ($pages < 2) {
        throw new RuntimeException('Second audit page not reached');
    }

    foreach ($created as $id) {
        if (!isset($seen[$id])) {
            throw new RuntimeException(
                'Missing temporary post in full scan: ' . $id
            );
        }
    }

    if ($processed !== $total) {
        throw new RuntimeException(
            'Incomplete scan: ' . $processed . '/' . $total
        );
    }

    if (array_sum($categories) !== $total) {
        throw new RuntimeException('Category totals mismatch');
    }

    echo 'PASS: All 23 temporary posts scanned' . PHP_EOL;
    echo 'PASS: Multiple audit pages processed' . PHP_EOL;
    echo 'PASS: All audit pages processed' . PHP_EOL;
    echo 'PASS: No duplicated records' . PHP_EOL;
    echo 'PASS: Categories cover all records' . PHP_EOL;
    echo 'TOTAL: ' . $total . PHP_EOL;

    foreach ($categories as $name => $count) {
        echo strtoupper($name) . ': ' . $count . PHP_EOL;
    }

    echo 'FULL SCAN INTEGRATION TEST COMPLETE' . PHP_EOL;
} finally {
    foreach ($created as $id) {
        wp_delete_post($id, true);
    }

    wp_set_current_user($previousUser);
}