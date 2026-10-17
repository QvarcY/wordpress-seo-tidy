# Internal Linking Preview — development status

This branch contains an **experimental, opt-in implementation**, not a release-ready internal linking engine.

## Enable only on a disposable development site

Define `SEO_TIDY_INTERNAL_LINKING_PREVIEW` as `true` in `wp-config.php`. The default is disabled. The SEO-TidY administrator menu then exposes **Internal Linking Preview**. On post/page editing screens, administrators see **SEO-TidY Smart Topics**, where they can save up to 12 approved labels, accept proposals derived from existing category/tag names or the title, and maintain up to 12 rejected labels.

Smart Topics are saved to the private post meta key `_seo_tidy_smart_topics_v1` and **never** create public WordPress tags. The save handler checks a nonce, administrator permissions, post edit permissions, revision/autosave state and post type. The preview combines approved labels with existing taxonomy hints. Existing posts are **not bulk-classified yet**.

The preview runs a local BM25 variant against at most 200 candidate posts/pages and shows at most five suggestions for each of up to 25 source posts per page. A visible warning states that larger sites have incomplete results. This is an intentionally incomplete initial approach, **not** the agreed scalable site-wide indexing implementation.

## Known limitations and release blockers

- Not tested in a real WordPress installation; **exact committed PHP files have not been syntax-linted** in a checked-out repository.
- Editors without `manage_options` cannot use the private topic box, even if they can edit posts. This is a deliberate experimental capability restriction.
- The preview currently derives candidates from lexical terms and private topics but has no full topical phrase/entity index, WordPress-wide incremental batches, persisted scan state or accurate resumption.
- The conservative competing-intent screen only excludes certain overlapping cases; it is not a reliable cannibalization assessment.
- Existing-link resolution covers common anchor/permalink cases, but relative paths, permalink variants, language routing, query strings and canonical overrides are not comprehensively tested.
- Yoast/Rank Math noindex/canonical metadata checks are incomplete relative to effective robots and other SEO plugins. Do not infer indexability from current checks alone.
- Proposals are generated on editor load; there is no indexed background generator, formal evidence history or bulk-review workflow.
- New UI strings use the `seo-tidy` translation domain but EN/LV catalog completeness has not been verified.
- The present candidate cap is intentionally incomplete and may bias BM25 document frequency statistics; scores do not transfer across sites or scans.

## Focused acceptance before any production installation

1. Run `php -l` on `includes/SmartTopics.php`, `includes/InternalLinkingPreview.php` and `includes/Bootstrap.php` using the actual branch files.
2. Test nonce and capability denial; test saving approved labels, accepting suggestions, rejecting labels, and editing an existing label.
3. Test public pages remain unchanged and that no tag archives are introduced.
4. Verify URL/link exclusions on absolute, root-relative, permalink, query and fragment variants.
5. Verify noindex and canonical behavior for WordPress core and installed SEO providers.
6. Compare BM25 and suggestion ordering against the fixture labels; check false positives.
7. Check Latvian/English interface strings and disabled-by-default behavior.
8. Replace the candidate cap with resumable scalable indexing and benchmark >200 and >=1000 items.

No automatic edits or site-wide bulk writes are implemented. Keep this PR draft; do not deploy on a production website.
