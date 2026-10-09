# SEO-TidY

**Free, open-source WordPress SEO and AI search readiness plugin by QvarcY.**

Clean SEO. Smarter discovery. Completely free.

## Release status

Version: **0.1.0-beta.2**

This is a beta release for testing. It is not yet recommended
for production websites without backups and compatibility testing.

## Features

- Custom SEO titles and meta descriptions for posts and pages
- WordPress block editor and classic editor metadata support
- Quick metadata editing in the SEO-TidY administration panel
- Automatic BlogPosting and WebPage JSON-LD structured data
- Search result previews with title and description guidance
- SEO audit for duplicate metadata, noindex settings, headings and image ALT
- Outgoing and incoming internal content link recommendations
- Review of missing or unpublished WordPress post-ID link targets
- Affected URL details for supported link checks
- Cached incoming link and duplicate metadata analysis
- SEO metadata and content audit
- Answer readiness content-structure suggestions
- Yoast SEO and Rank Math metadata import
- Centralized output and audit settings
- English and Latvian administration interface

Content recommendations are heuristic and do not guarantee search
rankings, indexing, or inclusion in AI-generated answers.

## Requirements

- WordPress 6.8 or newer
- PHP 8.1 or newer

## Installation

1. Download the release ZIP from GitHub Releases.
2. In WordPress, open Plugins > Add New Plugin > Upload Plugin.
3. Select the ZIP and choose Install Now.
4. Activate SEO-TidY.
5. Open SEO-TidY in the WordPress administration menu.

The ZIP already contains compiled JavaScript assets.
Node.js and npm are not required for normal installation.

## Getting started

1. Open Dashboard to see published content statistics.
2. Open Metadata to review and edit SEO titles and descriptions.
3. Open Smart Schema or Settings to control structured data.
4. Open SEO Audit and Answer Readiness for content suggestions.
5. Use Migration only after making a database backup.

Migration imports available metadata into empty SEO-TidY fields.
It does not intentionally delete the source plugin's metadata.
Review the preview and confirm before importing.

Using multiple SEO plugins at once may create duplicate metadata
or structured data. Check the public page output before deployment.

## Development

Install dependencies with `npm ci` and build with `npm run build`.

Source code: https://github.com/QvarcY/wordpress-seo-tidy

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Support

- GitHub: https://github.com/QvarcY
- Report an issue: https://github.com/QvarcY/wordpress-seo-tidy/issues
- Buy Me a Coffee: https://buymeacoffee.com/craftin

Donations are optional. Every SEO-TidY feature remains free.