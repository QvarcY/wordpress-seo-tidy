const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');

const root = path.resolve(__dirname, '../..');

const cli = process.env.SEO_TIDY_WP_ENV_CLI ||
    path.join(
        process.env.LOCALAPPDATA || '',
        'npm-cache/_npx/96f61048c5eb8a35/node_modules/@wordpress/env/bin/wp-env'
    );

const dns = process.env.SEO_TIDY_DNS_BOOTSTRAP ||
    path.join(os.tmpdir(), 'seo-tidy-dns-bootstrap.cjs');

if (!fs.existsSync(cli) || !fs.existsSync(dns)) {
    throw new Error(
        'wp-env runner unavailable. Set SEO_TIDY_WP_ENV_CLI and SEO_TIDY_DNS_BOOTSTRAP.'
    );
}

function wp(args) {
    const result = spawnSync(
        process.execPath,
        ['--require', dns, cli, 'run', 'cli', 'wp', ...args],
        {
            cwd: root,
            encoding: 'utf8',
            maxBuffer: 8 * 1024 * 1024,
        }
    );

    if (result.stderr) {
        process.stderr.write(result.stderr);
    }

    if (result.status !== 0 || result.error) {
        if (result.stdout) {
            process.stdout.write(result.stdout);
        }
        throw result.error || new Error(
            `WP-CLI failed: ${args.join(' ')} (${result.status})`
        );
    }

    return result.stdout.trim();
}

function check(condition, message) {
    if (!condition) {
        throw new Error(`FAIL: ${message}`);
    }

    console.log(`PASS: ${message}`);
}

async function runHttpTest() {
    let id = null;
    const title = `SEO-TidY HTTP test ${Date.now()}-${process.pid}`;

    try {
        const output = wp([
            'post', 'create',
            '--post_type=post',
            '--post_status=publish',
            `--post_title=${title}`,
            '--porcelain',
        ]);

        check(/^\d+$/.test(output), 'Temporary post created');
        id = Number(output);

        wp([
            'post', 'meta', 'update',
            String(id), '_seo_tidy_title',
            'SEO-TidY HTML title test',
        ]);

        wp([
            'post', 'meta', 'update',
            String(id), '_seo_tidy_description',
            'SEO-TidY HTML description test',
        ]);

        const response = await fetch(
            `http://localhost:8898/?p=${id}`,
            { signal: AbortSignal.timeout(30000) }
        );

        check(response.status === 200, 'Public page HTTP 200');

        const html = await response.text();

        const titles = [
            ...html.matchAll(/<title\b[^>]*>([\s\S]*?)<\/title>/gi),
        ];

        check(titles.length === 1, 'Exactly one title tag');
        check(
            titles[0][1].trim() === 'SEO-TidY HTML title test',
            'Correct SEO title'
        );

        const descriptions = [
            ...html.matchAll(/<meta\b[^>]*>/gi),
        ].filter(([tag]) =>
            /\bname\s*=\s*(["'])description\1/i.test(tag)
        );

        check(
            descriptions.length === 1,
            'Exactly one meta description'
        );

        check(
            /\bcontent\s*=\s*(["'])SEO-TidY HTML description test\1/i
                .test(descriptions[0][0]),
            'Correct meta description'
        );
    } finally {
        if (id !== null) {
            wp(['post', 'delete', String(id), '--force']);
            console.log(`PASS: Temporary post ${id} removed`);
        }
    }
}

async function main() {
    for (const file of [
        'metadata-smoke.php',
        'metadata-output-smoke.php',
    ]) {
        console.log(`\n=== ${file} ===`);

        const output = wp([
            'eval-file',
            `wp-content/plugins/wordpress-seo-tidy/tests/integration/${file}`,
        ]);

        console.log(output);

        check(
            output.includes('INTEGRATION TEST COMPLETE'),
            `${file} completed`
        );
    }

    console.log('\n=== PUBLIC HTML ===');
    await runHttpTest();

    console.log('\nALL SEO-TIDY INTEGRATION TESTS PASSED');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});