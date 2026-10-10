const fs = require('node:fs');

const targets = [
    {
        source: 'src/index.js',
        translations: 'languages/seo-tidy-lv-seo-tidy-admin.json',
    },
    {
        source: 'src/editor.js',
        translations: 'languages/seo-tidy-lv-seo-tidy-editor.json',
    },
];

let errors = 0;

for (const target of targets) {
    const source = fs.readFileSync(target.source, 'utf8');
    const raw = fs.readFileSync(target.translations, 'utf8');
    const catalog = JSON.parse(raw);
    const messages = catalog.locale_data?.messages;

    if (!messages || typeof messages !== 'object') {
        throw Error('Invalid translation catalog: ' + target.translations);
    }

    const keys = [...source.matchAll(
        /__\(\s*'((?:\\'|[^'])*)'\s*,\s*'seo-tidy'\s*\)/g
    )].map(match => match[1]);

    const unique = [...new Set(keys)];

    for (const key of unique) {
        const translated = messages[key];

        if (!Array.isArray(translated) ||
            !translated.length ||
            typeof translated[0] !== 'string' ||
            !translated[0].trim()) {
            console.error('MISSING: ' + target.source + ': ' + key);
            ++errors;
        }
    }

    for (const [key, values] of Object.entries(messages)) {
        if (key === '') continue;

        if (!Array.isArray(values)) {
            console.error('INVALID: ' + key);
            ++errors;
            continue;
        }

        for (const value of values) {
            if (typeof value !== 'string') continue;

            if (value.includes('\uFFFD') ||
                /(?:30|70)\?(?:65|160)/.test(value) ||
                /\?(?=\p{L})/u.test(value) ||
                /\p{L}\?\p{L}/u.test(value)) {
                console.error('ENCODING: ' + key + ': ' + value);
                ++errors;
            }
        }
    }

    if (!/[āčēģīķļņšūžĀČĒĢĪĶĻŅŠŪŽ]/u.test(raw)) {
        console.error('NO LATVIAN DIACRITICS: ' + target.translations);
        ++errors;
    }

    console.log(
        target.source + ': ' +
        unique.length + ' translation keys checked'
    );
}

if (errors) {
    console.error('Translation check failed: ' + errors + ' issue(s)');
    process.exit(1);
}

console.log('PASS: Latvian translation coverage and UTF-8 checks');
