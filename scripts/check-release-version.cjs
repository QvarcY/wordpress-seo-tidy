const fs = require('node:fs');
const path = require('node:path');

const tag = process.argv[2];

if (!/^v\d+\.\d+\.\d+(?:-beta\.\d+)?$/.test(tag || '')) {
    console.error('FAIL: Invalid release tag');
    process.exit(1);
}

const version = tag.slice(1);
const root = path.resolve(__dirname, '..');

const pkg = JSON.parse(
    fs.readFileSync(path.join(root, 'package.json'), 'utf8')
);
const lock = JSON.parse(
    fs.readFileSync(path.join(root, 'package-lock.json'), 'utf8')
);
const php = fs.readFileSync(
    path.join(root, 'seo-tidy.php'),
    'utf8'
);

const expectedConstant =
    "define('SEO_TIDY_VERSION', '" + version + "');";

const checks = [
    pkg.version === version,
    lock.version === version,
    lock.packages?.['']?.version === version,
    new RegExp(
        '^\\s*\\* Version: ' +
        version.replace(/\./g, '\\.') +
        '\\s*$',
        'm'
    ).test(php),
    php.includes(expectedConstant),
];

if (!checks.every(Boolean)) {
    console.error('FAIL: Release versions do not match');
    process.exit(1);
}

console.log('PASS: Release version verified: ' + tag);