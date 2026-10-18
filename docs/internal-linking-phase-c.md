# Issue #29 — Phase C quality and safety evidence

Date: 2026-10-18. Local base: `042afada61870a744cec766349701a62cc46e60d`, branch
`feature/issue-29-smart-topics-prototype`, PR #31. This record supersedes older
unexecuted-check notes only for the checks explicitly recorded here.

## Confirmed defects and changes

| Reproduction | Targeted fix | Regression coverage |
| --- | --- | --- |
| `?p=ID` / `?page_id=ID` lost identity because link detection stripped queries. | Preserve queries and remove fragments during resolution. | Absolute, root-relative and query-only plain permalinks, tracking queries and fragments. |
| Relative links were based at home; subdirectory root paths were double-prefixed. Encoded dot segments escaped deduplication. | Resolve against the source permalink directory and actual origin; handle literal/encoded dots without decoding encoded slashes. | Directory/file-like source URLs, `../`, `%2e`, empty path segments, scheme-relative URLs and subdirectory sites. |
| Invalid/external target permalinks, site noindex and integer Yoast noindex passed. Scanner and preview eligibility differed. | Share eligibility for both ends and indexing: published post/page, no password, site visibility, supported robots metadata and local HTTP(S) permalink. Malformed provider values fail closed. | Both roles, host case, ports, credentials, site visibility and provider values. |
| Canonicals used raw string comparison. | Normalize supported local self-canonical references, host case/default ports and trailing slashes; retain routing queries. Exclude different/unknown identities. | Both providers, relative/absolute self overrides, fragments, distinct plain-query identities. |
| Single locations, repeated words or broad taxonomy sufficed for unrelated suggestions. | Require a target-title term in the body and a second distinct directional term from the title or a destination-specific approved topic. Taxonomy alone cannot corroborate it. | Positive EN/LV pairs retained; weak hotel/transport and reverse-guide negatives; repetition and broad categories. |
| Competing guides required both title/topic overlap; synonyms and unlabelled near-duplicates passed. | Suppress identical titles/bodies, two distinct shared title terms, or identical approved-topic term sets with at least two terms. | EN/LV accommodation pairs, unlabelled near-duplicates, title containment and copied bodies. |
| Scoring and storage used different text lengths and title/topic frequencies. | One authoritative capped vector: body ×1, title ×3, approved topic tokens ×3; merge at 191 characters before capping at 300 terms. Keep BM25 `k1=1.2`, `b=0.75`. | Actual vector/length equality, weights, Unicode cap collisions and term retention. |
| Retrieval used eight query terms while DF/scoring used more. | Share the first 64 distinct terms across retrieval, DF and scoring. Retain 30-candidate/five-result caps; ID tie breaking. Missing/invalid DF adds no invented rarity. | Ninth-term retrieval, caps, stored-corpus statistics and ordering. |
| Read/cursor/clear failures left snapshots usable or falsely complete. | Mark failures stale and suppress results; recheck state before scoring. | Candidate/DF/stat failures, failed clears, cursor/delete/insert failures. |
| Late provider/topic changes, relevant settings and unsupported-type transitions missed invalidation. | Add scoped invalidation hooks without reacting to unrelated metadata or own snapshots. | Watched metadata/settings, no-op updates, type changes and bounded scans. |
| Entities and composed/decomposed Latvian differed; fallback lowercase lost uppercase diacritics. | Decode entities and normalize Latvian Unicode, including without mbstring/intl. Preserve diacritics. | Punctuation, mixed language, entities, decomposition and extension-free cases. |
| Python diagnostic printed a separate algorithm's results without failing. | Retain its entrypoint as a wrapper for assertions against production PHP and SQL. | Failed expectations now produce exit status 1; no second ranking formula. |

Fixture bootstrap deliberately inserts ineligible postings so runtime checks are
tested even with old rows. Separate lifecycle tests check scanner exclusions.
SQLite executes production retrieval/statistics SQL, not MySQL schema migration.

## Compatibility and behavior

Smart Topics v1 metadata, approvals/rejections, public taxonomy and article content
are unchanged. Table/columns, binary collation and option/meta keys are unchanged.
Snapshot revision **5** invalidates older complete **and partial** scans because
their term representation differs. Manual rebuilding is required before results
resume; it clears only regenerable index data. Processed counts still include
excluded published IDs and do not measure accuracy or eligible documents.

No UI markup, styling, labels, feature gate, insertion behavior or SEO Audit code
changed. Every result remains a candidate for manual editorial review. BM25 is
a corpus-relative ordering signal, not confidence or accuracy. Stronger exclusions
deliberately sacrifice recall for ambiguous pairs.

## Reproducible validation

From a development checkout with PHP 8+, PDO SQLite and DOM:

```sh
npm run test:internal-linking
python tests/fixtures/evaluate-internal-linking.py
```

The npm command needs no dependency installation/build and makes no network
requests. It runs syntax checks, production-PHP fixtures, missing-parser and
extension-fallback cases, isolated index lifecycle/failure tests, existing updater
tests, the Latvian catalog check and `git diff --check`. The Python command is an
alternative entrypoint for the PHP assertions, not additional accuracy evidence.

Optionally set `SEO_TIDY_WP_CORE_ROOT` to local WordPress core to repeat fixtures
using its standalone HTML parser. Only HTML API class files load, never
`wp-load.php`, configuration, a site database, plugins or live hooks. Routing and
database services still use doubles. `PHP_BINARY` selects the PHP executable.

### Executed results

The consolidated command `node scripts/validate-internal-linking.cjs` completed
with exit status **0** on PHP **8.2.6**. `SEO_TIDY_WP_CORE_ROOT` selected the local
WordPress **6.2** HTML API class files; WordPress itself was not bootstrapped.

| Executed check | Result |
| --- | --- |
| PHP syntax on seven runtime/entrypoint files and four new PHP test files | 11 files passed |
| Production-PHP fixture evaluator, DOM anchor double + SQLite | 375 assertions, zero failures |
| Same evaluator with the actual standalone WordPress 6.2 HTML parser + routing/DB doubles | 375 assertions, zero failures |
| Missing HTML API (separate PHP process) | 2 assertions, zero failures |
| `php -n` without mbstring/intl (separate PHP process) | 4 assertions, zero failures |
| Index lifecycle, retrieval and failure contracts (stub database) | 49 assertions passed |
| Existing GitHub updater validation and WordPress-boundary doubles | Both scripts passed (20 and 18 checks); not real WordPress integration |
| Existing Latvian catalog/UTF-8 check | 247 admin + 10 editor translation keys passed |
| `git diff --check` | Passed; Git emitted line-ending conversion notices only |

There are **26 documents and 26 pair labels** (including two retained duplicate
labels). Every pair is checked in the full corpus and in isolation, preventing
top-five crowding from masquerading as policy rejection. Counts are assertion
coverage, not independent articles, editorial precision or accuracy percentages.

Before fixes, an exact-HEAD temporary include snapshot produced 85 failures in
603 diagnostic assertions, including 10 isolated labelled pair failures. This
baseline uses old `indexTerms()` directly, omitting its scanner's separate topic
boost; its aggregate ranking counts are not an exact old-scan simulation. A
separate exact-HEAD index probe reproduced eight state/retrieval/invalidation
regressions. These were targeted development probes, not additional full-suite
runs or a before/after accuracy estimate.

The initial sandboxed consolidated attempt could not launch child processes
(`EPERM`) and executed no checks. The successful rerun used local process access;
no network or site database access was involved. Unexecuted WordPress integration
checks are not represented as passes. The complete local output is retained in
ignored `test-results/phase-c-validation.log`.

### Phase C acceptance assessment

The scoped implementation and deterministic local acceptance checks pass:
explicit stored anchors are deduplicated; ineligible content, known weak matches
and demonstrated competing pairs are excluded; EN/LV positives remain; scores
remain ranking signals; data/UI compatibility is retained. All confirmed runtime
defects above have regression coverage. **Full environment acceptance remains
open** for real WordPress routing/providers/rendered links and MySQL behavior.
The feature is **not release-ready**; the release blockers below remain.

## Remaining risks and release blockers

- Full WordPress 6.8 + MySQL/MariaDB integration was not run in this cycle. Verify
  dbDelta/migration, binary collation, real routing/redirects, custom/multilingual
  permalinks, provider defaults/filter-based robots/canonicals, hook timing,
  permissions, REST and administration UI.
- Stored anchors are inspected, not rendered shortcodes, reusable/dynamic blocks,
  template/navigation or plugin-generated links. Rendered-link deduplication is
  not established.
- Lexical guards cannot establish semantic complementary intent. Synonymous
  near-duplicates without shared labels/title terms may be missed; useful
  complementary pages may be suppressed. Editorial review and a larger independent
  EN/LV holdout remain necessary.
- No stemming, translation or semantic language matching was added. The no-intl
  fallback covers Latvian composition, not all Unicode. Directional evidence
  retains the existing first-12,000-character body window.
- Caps of 300 stored terms, 64 query terms and 30 initial candidates limit recall;
  excluded candidates are not backfilled. BM25 describes capped documents, not
  complete-site discovery or calibrated relevance probabilities.
- No 200/1000-post benchmark, MySQL `EXPLAIN`, concurrent scan/edit validation,
  option-write failure recovery or uninstall integration was run.
- SEO Audit classification is smoke-tested and its source unchanged; the full
  pre-existing WordPress audit integration suite was not executed here.

No production database access, deployment, commit/push, merge or Actions dispatch
was performed. PR #31 retains its release blockers. Phase C fixture evidence is
not a release-readiness claim.

## Files changed

- Runtime: `includes/InternalLinkingPreview.php`, `includes/InternalLinkingIndex.php`.
- Fixture inputs/entrypoints: `tests/fixtures/internal-linking-fixtures.json`,
  `tests/fixtures/evaluate-internal-linking.py`, `tests/fixtures/evaluate-internal-linking.php`.
- Test support: `tests/fixtures/internal-linking-test-bootstrap.php`,
  `tests/fixtures/internal-linking-token-fallback.php`, `tests/unit/internal-linking-index.php`.
- Validation command: `scripts/validate-internal-linking.cjs`, `package.json`.
- Documentation: `docs/internal-linking-phase-c.md`,
  `docs/internal-linking-preview-status.md`, `docs/internal-linking-implementation.md`.

`includes/SmartTopics.php` and `includes/SEOAudit.php` were reviewed and tested at
the boundaries described above, with no source changes. All changes remain local
and uncommitted in the named feature branch.
