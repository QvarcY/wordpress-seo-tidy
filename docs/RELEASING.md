# SEO-TidY release procedure

This is a maintainer checklist for preparing **future** releases. It does not authorize an immediate tag, upload or deployment. The latest published stable version at the time this guide was created (2026-10-18) is **v1.0.0**.

## How WordPress updates are delivered

SEO-TidY has its own GitHub release updater (`includes/GitHubUpdater.php`). It accepts an eligible newer GitHub Release only when the tag matches `vMAJOR.MINOR.PATCH` for stable (or `vMAJOR.MINOR.PATCH-beta.N` for an explicitly enabled prerelease) and the release contains the exact asset `seo-tidy-VERSION.zip`.

WordPress normally displays an eligible update in **Dashboard > Updates** / **Plugins**. The release list is cached for up to 12 hours; a manual WordPress update check can refresh it. The plugin updater reports `autoupdate: false`: installations are not silently updated by this mechanism.

**An ordinary push, PR, README edit, ZIP in an Actions artifact, or commit on `main` is not a public WordPress update.** A properly tested and tagged GitHub Release with a matching ZIP asset is required.

## Stable release checklist

1. Confirm that every planned change has been reviewed, acceptance criteria are met, and any release blockers are resolved. Experimental work in [Issue #29](https://github.com/QvarcY/wordpress-seo-tidy/issues/29) must not be declared complete or included prematurely.
2. Choose the next release number (for example `1.1.0`; this is **only an example**, not an authorized release). Use the corresponding `v1.1.0` tag.
3. Update the **same exact version** in `package.json`, `package-lock.json` (top-level and root package), and `seo-tidy.php` (plugin header and `SEO_TIDY_VERSION`). Do not update only one source.
4. Update `CHANGELOG.md`, `README.md` (what actually ships versus what is still planned), user guides and any required compatibility notes. Do not claim unpublished features are available.
5. Run local verification appropriate to the changed files, before considering a tag:
   - `node scripts/check-release-version.cjs v1.1.0` (replace the sample with the selected version).
   - `npm run check:i18n` and `npm run test:updater`.
   - `npm run build`, PHP syntax checks and `npm run test:integration` when a safe isolated WordPress/Docker environment is available.
   - `./scripts/build-release.ps1` in PowerShell after build. Verify ZIP internal paths, package root `seo-tidy/`, required assets and the expected `seo-tidy-VERSION.zip` filename.
   - Verify that the desired tests have actually passed. Never represent missing Docker/services or skipped tests as a pass.
6. Confirm GitHub release prerequisites and credentials (including Docker Hub credentials expected by the current workflow) *without exposing any secrets*. Review `.github/workflows/publish-stable.yml` and `scripts/check-release-version.cjs`.
7. Merge the reviewed source to `main`; ensure the intended release commit is **exactly the current main HEAD**. The stable publishing workflow refuses tags that point anywhere else.
8. **Only after explicit maintainer approval**, create/push the release tag `vMAJOR.MINOR.PATCH` on that exact main commit. A matching tag push starts **SEO-TidY Publish Stable** and consumes GitHub Actions usage. It is not a dry run.
9. Observe the workflow until all release checks complete, including integration tests, ZIP packaging and checksum generation. The workflow must publish the GitHub release asset `seo-tidy-VERSION.zip` and `SHA256SUMS.txt`. Never claim publication until those artifacts exist and the workflow has succeeded.
10. Verify the published release/version, ZIP asset name and checksum. On a separate **disposable** WordPress site, verify a clean installation or update as appropriate. Check WordPress update visibility after cache refresh; verify the correct version and no regression.

## Safety guards currently implemented

- `.github/workflows/publish-stable.yml` listens to `v*.*.*` stable candidate tags and excludes tags containing a hyphen (`!v*.*.*-*`); its runtime Bash check requires strict `vMAJOR.MINOR.PATCH` numbers. Invalid tag candidates are rejected.
- `.github/workflows/publish-beta.yml` separately handles `vMAJOR.MINOR.PATCH-beta.N` prerelease tags; publishing one requires specific authorization.
- The script `scripts/check-release-version.cjs` checks tag, npm manifests and PHP plugin version consistency.
- The existing release is checked to prevent accidental recreation; the tag must point to current `main`.
- ZIP publication depends on successful checks and build; the exact asset name matters to `GitHubUpdater.php`.

## If validation or publication fails

Do **not** change the published version label or attempt to publish the same version through a manual ZIP workaround. Inspect the failed GitHub Actions step and logs, fix the underlying code/workflow and obtain maintainer review. The workflow currently requires a fresh tag pointing to current `main`: after a failure, coordinate a new version/tag or a deliberately reviewed recovery procedure; **do not force-move an existing published tag**.

GitHub Actions credits are limited. Keep routine local tests targeted and avoid unnecessary tag pushes, pull-request builds and repeated full workflows. Documentation-only commits can include `[skip ci]` to avoid running push-triggered builds, but do not use that to bypass required release validation.

## References

- [Stable release workflow](../.github/workflows/publish-stable.yml)
- [Beta release workflow](../.github/workflows/publish-beta.yml)
- [GitHub updater](../includes/GitHubUpdater.php)
- [Version check](../scripts/check-release-version.cjs)
- [ZIP builder](../scripts/build-release.ps1)
- [Release preparation task](https://github.com/QvarcY/wordpress-seo-tidy/issues/35)
