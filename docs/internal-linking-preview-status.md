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

## Combined development cycle checkpoint (2026-10-17)

The indexing preview now includes an administrator-only, nonce-protected manual endpoint that advances over published post/page IDs in batches of 25. It stores a bounded frequency snapshot as private post metadata, with a cursor and processed count held in a WordPress option. Changes to content or terms mark the scan stale; the next indexing request restarts rather than silently presenting stale completion as current. This is an **initial scanning service**, not a verified full-site search index.

The preview no longer hardcodes the first 200 published posts as the candidate pool. Instead, it performs a WordPress text search for each displayed source and inspects at most 30 returned candidates. This removes that particular cap but **does not establish complete site-wide discovery**. An article with no overlapping query terms may be missed; score document frequencies are computed over the local candidate sample rather than the full site. The persisted scan snapshots are not yet consumed by retrieval, so the planned inverted index and global frequency statistics remain unfinished.

A Unicode tokenization escaping error was identified in the earlier prototype and corrected. A separate installed PHP CLI was used to check that the intended Unicode pattern matches Latvian words with diacritics. This **does not** constitute running `php -l` on the exact current GitHub file bytes, nor WordPress integration verification. Other PHP files and permission boundaries still require targeted validation.

This code remains experimental and disabled by default. The staged scan must not be represented as finished large-site support. The next cycle requires linking the stored snapshots to bounded candidate retrieval, validating canonical/link identification, completing the admin interface and localization, and executing exact-branch PHP/WordPress checks before release.

## Integrated index + topic review cycle (2026-10-17)

The experimental administrator page now reads candidates from the **completed** private index using bounded token lookups rather than running a fresh WordPress full-text search. No suggestions are displayed while the index is absent or stale. The scanner now includes approved private topics in the indexed token set. It can be explicitly restarted from the beginning, and source editing or taxonomy changes invalidate the snapshot. Each source is limited to 30 returned target IDs; article previews remain read-only and require manual review.

Smart Topics can now also be reviewed inline from that same administrator page. Changes are sent through a separate administrator-only, nonce-protected `admin-post.php` handler, with edit-post capability enforcement, normalized lengths, deduplication and a maximum of twelve labels. Accepting suggestions and maintaining a rejection list do not create public taxonomy terms. These edits invalidate the scan, which must then be rerun. Editor-side meta box functionality remains available.

A small, scoped Latvian fallback for the newly added PHP administrator texts is registered alongside the English originals; it is active only in Latvian locale, and only if the experimental feature is enabled. **This is not a verified translation-catalog or accessibility audit.** Full integration into the existing JS translation pipeline should be considered separately if the final interface moves to the main React admin screen.

### Scalability and correctness limitations

The index currently persists at most 300 term frequencies per document as serialized WordPress post metadata. For a completed scan, candidate retrieval queries the serialized metadata using bounded `LIKE` predicates. This verifies that the persisted index is genuinely consumed but does **not** create a proper inverted search index, and may be expensive for large databases. A 1000-post time/memory/database-query benchmark is still required before performance or scalability claims. BM25 document frequencies are still computed only over the retrieved candidate sample, not all indexed documents. The same-intent and canonical/robots checks remain heuristic and incomplete.

**Validation status:** exact current GitHub PHP bytes were not fetched into a runtime for `php -l` or WordPress integration tests, because direct raw-GitHub access from the container is unavailable. The new PHP code has been reviewed but not verified by the PHP interpreter. Do not install on a public/production site, and keep PR #31 in draft until a real local checkout is checked.

## Focused static audit (2026-10-17)

An audit of the current PHP branch identified two concrete implementation hazards and applied narrowly scoped fixes:

- **Stale index snapshots across restarts:** restarting after an edit or manual rebuild previously reset only the cursor, leaving old metadata records eligible for token matching. The scanner now deletes only its regenerable `_seo_tidy_link_index_v1` metadata rows before rebuilding, then repopulates them in bounded batches. Existing Smart Topics approvals and article content are left unchanged.
- **Indexing known noindex content:** the scanner now skips password-protected posts and noindex posts marked through the two known Yoast/Rank Math metadata paths. Full effective robots/canonical policy remains a release blocker.
- **Malformed nonce field:** the Smart Topics editor save handler now checks that the nonce value is a string before sanitization and verification.

The installed command-line PHP interpreter was confirmed present (PHP 8.4), but the container cannot resolve github.com to clone the exact PR branch. Therefore no `php -l` success for these exact committed files, real WordPress behavior, benchmark or beta readiness is claimed. The release must stay blocked until exact-file PHP lint and WordPress integration checks are performed in an accessible checkout.

**Additional algorithm blockers:** candidate retrieval uses expensive serialized metadata `LIKE` queries; the retrieved-article BM25 statistics are not corpus-wide; the 300-token truncation drops terms from long articles; and LV stemming/semantic equivalence is not implemented. Do not interpret completed index progress as validated completeness or performance.
