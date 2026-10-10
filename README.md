# SEO-TidY

![SEO-TidY by QvarcY](assets/seo-tidy-hero.webp)

**Free, open-source WordPress SEO and AI search readiness plugin by QvarcY.**

Clean SEO. Smarter discovery. Completely free.

## Release status

**Recommended download: [SEO-TidY 0.1.0-beta.13](https://github.com/QvarcY/wordpress-seo-tidy/releases/tag/v0.1.0-beta.13)**

Download **`seo-tidy-0.1.0-beta.13.zip`** from the release's **Assets** section. This is the ready-to-install WordPress plugin. Do **not** download GitHub's automatically generated `Source code (zip)` or `Source code (tar.gz)` files.

Beta.13 is the current recommended tested beta release. It remains a pre-release, not a final stable 1.0 release. Back up your site and test compatibility before enabling it on production websites.

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
- Optional local daily pageview analytics with separate suspected-bot counts
- Configurable statistics badge with shortcode `[seo_tidy_stats]`
- Optional statistics badge in the website footer
- Light, dark and transparent badge themes
- Opt-in Wall of Fame directory with verified applications, administrator approval and a compact paginated website list on the dashboard
- Administrator-controlled statistics deletion and daily data retention

Content recommendations are heuristic and do not guarantee search
rankings, indexing, or inclusion in AI-generated answers.

## Requirements

- WordPress 6.8 or newer
- PHP 8.1 or newer

## Installation

1. Open the [recommended beta.13 release](https://github.com/QvarcY/wordpress-seo-tidy/releases/tag/v0.1.0-beta.13) and expand **Assets**.
2. Download **[seo-tidy-0.1.0-beta.13.zip](https://github.com/QvarcY/wordpress-seo-tidy/releases/download/v0.1.0-beta.13/seo-tidy-0.1.0-beta.13.zip)** (not the GitHub-generated source code archives).
3. In WordPress, open Plugins > Add New Plugin > Upload Plugin.
4. Select the downloaded ZIP and choose Install Now.
5. Activate SEO-TidY.
6. Open SEO-TidY in the WordPress administration menu.

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

## Analytics and privacy

SEO-TidY Analytics is **disabled by default**. Enabling it records
aggregated daily counts of eligible public page requests in the
WordPress database. The analytics engine does not persist raw IP
addresses or create a separate visitor event log.

- Human-classified pageviews are requests, not unique visitors.
- Suspected bots are identified heuristically from user agents.
- The bot classification can be inaccurate or manipulated.
- WordPress administrators and logged-in users are excluded.
- Non-GET and several non-public requests are excluded.
- Full-page caches, CDNs and other caching layers may bypass
  WordPress, so totals may be lower than actual traffic.
- The built-in engine does not calculate reliable unique visitors
  or real-time online visitor counts.
- Statistics are stored by calendar day for up to 365 days.
- Old rows are removed using WordPress scheduled tasks.
  WordPress cron depends on site activity or external cron setup.
- Administrators can explicitly delete all recorded statistics
  from the Analytics screen after a confirmation prompt.

The statistics badge is optional. The footer badge and the
Powered by SEO-TidY attribution are disabled by default.
The shortcode can be used where a badge is wanted.

Wall of Fame is optional. Owners can submit their website for ownership verification and moderator review. Only approved website names, URLs and descriptions appear in the public community directory. The directory is also shown in the SEO-TidY dashboard with pagination.

Site owners remain responsible for providing appropriate privacy
information to their visitors.

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