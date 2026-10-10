# Changelog

All notable changes to SEO-TidY will be documented here.

## [Unreleased]

## [0.1.0-beta.11] - Pending release

### Added
- One-click opt-in from the WordPress Wall of Fame tab with short-lived ownership proof.
- Approved community directory directly inside WordPress admin.
- Applicant status, review workflow, and self-service removal through WordPress.
- Latvian translations for the new interface.

### Notes
- The separate AREA-hosted PHP API must be updated before activating this plugin version.

## [0.1.0-beta.10] - Pending release

### Fixed
- Corrected the public Latvian statistics badge label Skatījumi using an encoding-safe PHP Unicode escape

## [0.1.0-beta.9] - Pending release

### Fixed
- Restored 59 corrupted Latvian administration translations
- Detect corrupted Latvian translation characters during validation

## [0.1.0-beta.8] - Pending release

### Fixed
- Shortened public statistics badge labels
- Replaced badge preview question marks with SVG icons
- Improved Latvian and English badge presentation

## [0.1.0-beta.7] - Pending release

### M13 ? Analytics, Stats Badge and Community

- Added opt-in, disabled-by-default daily website statistics
- Separated human-classified pageviews from suspected bot requests
- Added protected analytics REST endpoints and administration controls
- Added configurable public statistics badge and shortcode
- Added optional footer placement and optional project attribution
- Added local-only Wall of Fame profile settings
- Added 365-day data retention and confirmed statistics deletion
- Added WordPress integration tests for analytics, access control,
  data cleanup, badge rendering and community settings
- Completed Latvian administration translations for M13

**Limitations:** Counts are requests, not unique visitors.
Bot detection is approximate. Full-page caching may bypass
server-side collection. WordPress cron is traffic-dependent.
The public community catalogue is not yet implemented.

**Status:** Feature branch under review; no public M13 release yet.


## [0.1.0-beta.3] - 2026-10-09

### Administration
- Redesigned Dashboard and administration navigation
- Improved Metadata management and quick editing
- Refined SEO Audit, AI readiness, Schema and Migration screens
- Improved responsive layout and mobile table navigation

### Release quality
- Required admin stylesheet in the installation ZIP
- Strengthened beta version and main-branch verification
- Added PHP syntax and WordPress integration checks before publication

### Validation status
- Automated WordPress integration and ZIP checks passed on M7
- Independent clean ZIP installation still requires verification

### Administration redesign
- Branded WordPress administration header and navigation
- Redesigned Dashboard metrics and actions
- Improved Metadata tables, status indicators and quick editing
- Clearer SEO Audit findings and recommendations
- Updated AI Answer Readiness interface
- Unified Schema and general settings panels
- Redesigned metadata migration interface
- Responsive mobile layout and horizontal table navigation

### Release infrastructure
- Admin stylesheet included in portable WordPress ZIP
- ZIP workflow enabled on feature branches
- Desktop and mobile visual validation
- WordPress integration and translation checks

## [0.1.0-beta.2] - 2026-10-09

### Added
- Live search previews and metadata length guidance in WordPress editors
  and the SEO-TidY administration panel
- Duplicate SEO title and meta description detection
- Site-wide, Yoast SEO and Rank Math noindex review
- Outgoing and incoming internal content link recommendations
- Review of missing or unpublished WordPress post-ID link targets
- Affected link URL details in SEO Audit
- Improved image alternative text checks
- Automated Latvian translation validation and runtime loading

### Improved
- Incoming link analysis and duplicate metadata caching with invalidation
- Audit response consistency and pagination checks
- Latvian administration translations

### Validation
- WordPress functional tests for noindex, metadata duplicates and links
- Cold and cached audit comparison on a small test site
- Audit pagination and incoming-link cutoff tested with 201 published items

### Known limitations
- Incoming content link checks are skipped above 200 published posts/pages
- Link analysis does not cover every dynamically rendered link
- Unsupported custom URLs are not classified as broken links
- Clean WordPress ZIP installation remains to be verified before release



### Added
- Initial project documentation in English and Latvian
- GPL-2.0-or-later licensing
- Initial repository configuration

### Planned
- WordPress plugin bootstrap
- WordPress-native administration interface
- English and Latvian localization infrastructure
- Automated quality and compatibility testing
