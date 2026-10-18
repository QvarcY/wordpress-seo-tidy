# Internal Linking Preview — development status

This branch contains an **experimental, opt-in implementation**, not a release-ready internal linking engine.

## Phase C local quality and safety cycle (2026-10-18)

See [Phase C evidence](internal-linking-phase-c.md) for reproduced defects, targeted
fixes, exact executed checks and remaining integration/release blockers. URL
deduplication, shared eligibility, conservative evidence/competition exclusions and
consistent capped BM25 inputs now have assertion-based PHP coverage. Snapshot
revision 5 requires a manual rebuild of the regenerable index; table structure,
Smart Topics v1 data and the administration UI are unchanged. Historical production
checks below were not repeated in this local cycle and do not establish Phase C
algorithm correctness or release readiness.

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

## Indexed postings implementation (2026-10-17)

The previous serialized-postmeta `LIKE` search has been replaced with a dedicated WordPress database table, `{$wpdb->prefix}seo_tidy_link_terms`, created through `dbDelta` on the first administrator-initiated indexing request. Its composite primary key is `(post_id,term)`, with a separate indexed `term` lookup. The scanner processes 25 published article IDs per explicit administrator request, writes at most 300 term-frequency rows per indexed article and maintains a cursor. It does **not** modify public article content or taxonomy terms. Restart or stale re-index clears only its own postings and regenerable index metadata.

Candidate retrieval now reads this term index with bound SQL parameters and a maximum of 30 candidate IDs per source, avoiding serialized metadata substring matching. The BM25 `N`, mean document length and term document frequencies are now queried from the complete indexed postings table after the scan reports complete. The corpus statistics reflect **stored, capped tokens**, not untruncated source bodies. This is a significant approximation: rare useful words omitted by the 300-term cap cannot be discovered.

**Blocking verification:** the exact committed PHP files have still not been brought into the isolated PHP runtime for full-file lint. The database install/indexing/query paths have not been exercised on a real WordPress + MariaDB/MySQL instance, and `dbDelta` schema success, SQL portability, query plans, permission protections and translation output are unverified. The query uses a grouped inverted-index lookup but there is no benchmark at 200/1000/large-post scale. The draft PR must not be merged or deployed until this gate passes.

**Operator verification on a development checkout only:** run `php -l` on `includes/InternalLinkingIndex.php`, `includes/InternalLinkingPreview.php`, `includes/SmartTopics.php`, `includes/InternalLinkingTranslations.php`, and `includes/Bootstrap.php`. Enable `SEO_TIDY_INTERNAL_LINKING_PREVIEW` in a disposable WordPress installation, manually scan until complete, inspect the table and actual suggestions, then change/reject topics, ensure index goes stale, rebuild, and verify no disallowed posts or already linked targets appear. Use a measured 200/1000-post test set; inspect `EXPLAIN` plans before claiming scale. Also verify uninstall cleanup before any release.


## Dashboard integration verified on kas.id.lv (2026-10-18)

The main Internal Links tab now contains the index status, published/processed counts, progress, explicit 25-article scan form, article search, paginated table and editor links. It no longer consists of a link to a separate page. The administrator-only GET `/seo-tidy/v1/internal-linking` supplies the overview. The existing nonce-protected scan handler returns dashboard submissions to `admin.php?page=seo-tidy&section=linking`; the legacy standalone page remains available for existing bookmarks. No indexing is started merely by opening the tab. Primary action text and the support footer have explicit, consistent styling. The Latvian JavaScript catalog includes the complete overview.

Production previously had two active plugin entries: `seo-tidy/seo-tidy.php` and `seo-tidy-FILES-FOR-EXISTING-FOLDER/seo-tidy.php`. The latter supplied the old assets despite updates to the canonical folder. The duplicate was deactivated. The canonical deployment is `/home/kasidlv/public_html/wp-content/plugins/seo-tidy`, and the prior plugin snapshot is outside the web root at `/home/kasidlv/seo-tidy-backup-20261018/seo-tidy`. Inactive older directories were retained. Update the canonical installation rather than activating another differently named package.

Validation for this UI change: local production JavaScript build, complete Latvian translation coverage check, PHP 8.2 parser checks for both modified PHP classes and the asset manifest; saved cPanel file contents compared with local build contents. In the signed-in WordPress UI, the overview loaded 193 published records, search returned filtered results, and page two loaded correctly. No scan/rebuild or article-content write was performed for these checks. This does not clear the algorithm, indexing-database or scalability limitations recorded above.


## Index rebuild and entity display fixes verified (2026-10-18)

The production PHP log identified the actual scan failure: a duplicate primary key for post 49 and term `daudzās`, after the same batch inserted `daudzas`. The site's default accent-insensitive database collation treated those distinct Latvian tokens as the same key. Schema version 4 gives the term column an explicit UTF-8 binary collation, verifies/migrates the existing column because dbDelta does not migrate collation alone, and marks older completed indexes stale. Terms that become equal after the 191-character storage cap are combined before insertion.

The updated scanner was deployed to the canonical installation and the complete rebuild was run through the WordPress UI in eight sequential batches: **193/193 processed, 100%, Complete**. This exercises creation/migration, clearing, term inserts and the resumable cursor on the site's real database, including the previously failing post. It does not constitute a search-quality or scale benchmark.

SEO Audit, Metadata and Answer Readiness REST responses now decode HTML entities in display titles, matching the Internal Links overview. React continues to render titles as text. This replaces visible `&#8211;`, `&#8220;`, `&#8221;` and `&#8230;` with their punctuation characters; stored article content and titles are not rewritten. PHP 8.2 parser checks passed for the four modified classes; the signed-in WordPress tables were checked against the affected example titles. No GitHub Actions run was requested.
