# Issue #29 — Phase C quality and safety evidence

Date: 2026-10-18. Local base: `042afada61870a744cec766349701a62cc46e60d`, branch
`feature/issue-29-smart-topics-prototype`, PR #31. This record supersedes older
unexecuted-check notes only for the checks explicitly recorded here.

**Latest assessment:** the two reproduced concurrency blockers have been fixed.
All four requested safety regressions passed in the reused isolated environment
(**87 assertions, zero failures**). The earlier failure and benchmark evidence is
retained as history below. Broader provider, database-version, rendered-link and
production-load limitations remain; the feature is **not release-ready**.

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

The initial scoped implementation and deterministic local checks pass:
explicit stored anchors are deduplicated; ineligible content, known weak matches
and demonstrated competing pairs are excluded; EN/LV positives remain; scores
remain ranking signals; data/UI compatibility is retained. All confirmed runtime
defects above have regression coverage. The follow-up validates supported real
WordPress routing and MariaDB behavior and demonstrated the freshness failure
recorded below. The final concurrency cycle fixes that failure and passes its
targeted acceptance checks. Broader provider/rendered-link coverage and remaining
release gates still apply; this is not full release acceptance.

## Remaining risks and release blockers

- WordPress 6.8 + MariaDB 11.4.12 scoped integration is recorded below. MySQL and
  MariaDB 10.11 compatibility, custom/multilingual permalink filters, provider
  defaults/filter-based robots/canonicals and browser UI remain unverified.
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
- Synthetic 200/1000-post benchmarks and MariaDB `EXPLAIN` were executed below.
  Concurrent scan/edit validation found defects subsequently fixed below; general option-write
  failure recovery, production load and uninstall integration remain unverified.
- SEO Audit classification is smoke-tested and its source unchanged; the full
  pre-existing WordPress audit integration suite was not executed here.

No production database access, deployment, push, merge or Actions dispatch was
performed. One authorized local commit is recorded below. PR #31 retains its
release blockers. Fixture evidence is not a release-readiness claim.

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
the boundaries described above, with no source changes. These thirteen files
were captured in local commit `253c2cb`. The final concurrency commit also includes
the follow-up integration harness, historical evidence and this updated report.

## Follow-up: real WordPress integration (2026-10-18)

### Commit and isolation

Before committing, `git status --short` contained exactly the thirteen listed
files: seven modified tracked files and six untracked files. All were present;
the tracked and untracked changes were reviewed, no unrelated changes were
found, and `git diff --check` passed apart from line-ending conversion notices.
Local commit **`253c2cb1a9d7ae4fb3c989a9a072dac550990487`** contains all thirteen.
The working tree was clean immediately afterward. No push occurred.

The tested plugin runtime is exactly that commit. No runtime, administration UI
or Smart Topics model changes were made during integration validation.

A fresh official WordPress **6.8** download ran on PHP **8.2.6**, with the installed
MariaDB **11.4.12** binaries and a newly initialized workspace data directory.
The configured `wp-env` MariaDB 10.11 image was not available through Docker;
11.4.12 is the actual engine tested, not an assertion of 10.11/MySQL parity.
The database listened only on `127.0.0.1:33929`, used database `seo_tidy_phase_c`
and a dedicated restricted database user. PHP HTTP listened only on
`127.0.0.1:8896`. No Windows service or existing local website was changed.

The disposable site had external WordPress HTTP blocked, cron/automatic updates
disabled, its own generated accounts/configuration and a junction to the local
plugin checkout. Credentials/configuration are outside the repository and are
not included in the evidence. The downloaded core ZIP SHA-256 is
`6ec3954f27bfbf365b203de360734672605b1075cacc15cc791d79fe83d67c2a`.

Machine-readable measurements and outcomes are retained in
[the integration evidence](evidence/internal-linking-phase-c-wordpress.json).
Detailed local stdout/JSON files are in the disposable environment's `logs/`
directory; the evidence includes the test-file hashes used for the run.
After validation, the dedicated PHP server was stopped and MariaDB shut down
normally after its data-directory identity was checked. The disposable data,
configuration and logs were retained for inspection; no service is left running.

### Executed integration checks

| Check actually executed | Result and scope |
| --- | --- |
| `internal-linking-phase-c.php contracts` | **131 assertions, zero failures** against actual WordPress and MariaDB; no WP/SQL doubles. |
| Schema creation | Real `dbDelta` created the table, composite primary key and term index; term collation was `utf8mb4_bin`. |
| Migration | Changed the test column to nonbinary collation, ran the real migration, confirmed binary collation and preservation of an existing row/frequency. Actual SQL distinguished `mājas` and `majas`. |
| Rebuild and eligibility | Actual scan action processed the fixtures in two 25-ID-bounded batches; unsupported/noindex/canonical/password/draft/private cases had no snapshots or postings. Existing approved/rejected Smart Topics were preserved through rebuilds. |
| WordPress URL resolution | Pretty and plain query permalinks resolved; absolute/root/path/scheme-relative anchors, tracking queries, fragments, HTML entities and encoded dots suppressed existing targets. A saved existing anchor stayed excluded after a real rebuild. |
| Fixture recommendations | All 26 EN/LV labels passed in the real corpus. Negative pair absence alone can be affected by the candidate/result caps; isolated-pair evidence remains in the earlier deterministic suite. Direct eligibility/zero-postings assertions do not depend on ranking. |
| Sequential invalidation | Actual saves, known provider metadata, approved topics, taxonomy, status changes and deletion invalidated snapshots; site noindex excluded both roles. |
| Real in-process REST and scan authorization | Administrator/subscriber/anonymous dispatch, page validation and actual callback nonce rejection passed. |
| `run-internal-linking-phase-c.py http` | **17 checks, zero failures**, using actual HTTP login/cookies, REST nonces and `admin-post.php`. Administrator linking/audit REST returned 200; subscriber REST 403; anonymous REST 401; invalid REST/scan nonces 403. Denied scans left state unchanged; authorized scan returned a dashboard redirect. |

The subscriber scan callback uses the existing `wp_die` default response **500**
with “Permission denied”; access was denied before mutation. This is recorded as
existing behavior, not a claim that 500 is the preferred authorization status.
No browser visual/accessibility audit or complete pre-existing integration suite
was run. Yoast/Rank Math metadata paths were exercised; those plugins' effective
defaults, runtime filters and rendered metadata were not installed/validated.

### Measured 200/1000-post workload

The deterministic corpus alternates EN/LV across five themes and ten intent roles.
It uses readable shared paragraph templates with varied order/introductions.
Bodies contained **446–624 words** (median 535); every article retained the full
**300-term cap**. The 200-post corpus was grown to 1000 once. Each size was indexed
once, in separate actual scan processes, with no index rebuild during measurement.

| Measured value | 200 posts | 1000 posts |
| --- | ---: | ---: |
| Completed 25-ID batches | 8 | 40 |
| Stored term rows / indexed documents | 60,000 / 200 | 300,000 / 1000 |
| Scan execution total after WP bootstrap, including scan setup | 3.706 s | 16.205 s |
| Sum of scan process wall times, including startup/bootstrap | 7.537 s | 35.598 s |
| Scan batch median / p95 | 417.512 / 729.998 ms | 395.180 / 450.128 ms |
| Scan SQL queries across all batches | 2,282 | 11,402 |
| Highest scan PHP peak allocation | 36 MiB | 36 MiB |
| Candidate lookup median / p95 | 1.057 / 11.671 ms | 1.930 / 8.092 ms |
| Recommendation method median / p95 | 90.905 / 101.393 ms | 169.255 / 238.877 ms |
| Recommendation SQL queries median / maximum | 54 / 93 | 93 / 93 |
| Recommendation-process PHP peak allocation | 34 MiB | 36 MiB |

Candidate/recommendation values use the first ten deterministic sources, one
sample per source, in one CLI process per corpus. They exclude HTTP and WP
bootstrap; request-local cache warmth is mixed, not standardized. Memory includes
bootstrap and is PHP allocation, not whole-process RSS. With ten samples, nearest
rank p95 equals the maximum. Scan timing starts after WP bootstrap but includes
administrator lookup/nonce setup before the actual callback; the raw evidence's
`scan_callback_*` field names must be read with this scope. Query counts start
later and do not include all bootstrap/setup queries.

MariaDB `EXPLAIN` used the term index via a range scan and joined posts by primary
key (`eq_ref`). Estimated term rows were 122 and 602 respectively; the grouped
lookup still used a temporary table/filesort. These are single-host, synthetic,
limited-source measurements on a 128 MiB InnoDB buffer pool. They do not establish
production capacity, concurrent throughput, semantic accuracy or cold-cache limits.

### Historical concurrency probes at 253c2cb: failed safety acceptance

After benchmarks, 975 synthetic articles were moved to draft through WordPress,
preserving their Smart Topics. The remaining **25 published articles** allowed
each controlled race to complete in one batch without another large rebuild.
The test-only WordPress `query` filter paused immediately before the first posting
INSERT, after the scan had read its state/vector and deleted the old postings.
The plugin runtime was unchanged.

1. **Two scans overlap.** Both workers reached the same barrier for post 50.
   Worker A was released and completed; worker B was then released and failed
   with `Duplicate entry '50-planning' for key 'PRIMARY'`. No duplicate rows were
   accepted. B's request-local state said stale=true, but a fresh request observed
   complete=true/stale=false/processed=25. A's stored vectors matched current
   content. This proves missing scan serialization and a nonpersistent stale
   transition under cached concurrent state; it does **not** establish corrupted
   postings in that scenario. A likely mechanism, supported by the code, is
   `update_option` comparing B's already-stale cached value and skipping a write.
2. **A real edit occurs during a scan.** An explicit WordPress save first
   invalidated the index. A worker paused at its first INSERT; a separate process
   changed post 50's body with `wp_update_post`. That save marked stale=true and
   removed the snapshot. After release, the scan completed and persisted
   complete=true/stale=false. A fresh process confirmed that the stored vector
   differed from the current article: **two current terms were missing and 296
   old terms remained**. The new marker `phasecconcurrencynewtoken` was absent.
   The stale postings were therefore marked eligible for retrieval/scoring.

The edit-race runner deliberately returned **exit 1** for that safety failure.
The initial combined race stage stopped before the edit barrier because the
paired race left a completed persisted index; the first edit-only attempt then
rejected an overlong worker name before starting a scan. After those harness
preconditions were corrected, only the edit probe was resumed. Neither benchmark
nor the paired scan probe was repeated. The HTTP stage also had an initial
Windows UTF-8 reader error before requests; no pass is attributed to that attempt.

These were **confirmed release blockers at `253c2cb`**. The final cycle below
serializes scans and preserves invalidation, using fresh persisted-state checks.

### Reproduction and remaining scope

The integration scripts intentionally require a separately provisioned disposable
environment. They do not install packages, start services or select arbitrary
databases. Use a fresh WordPress installation in the sibling
`seo-tidy-phase-c-env/wordpress`, a new MariaDB data directory with loopback binding,
database `seo_tidy_phase_c` on `127.0.0.1:33929`, and home/site URL
`http://127.0.0.1:8896`. Enable mysqli in the environment's `php.ini`, activate the
local plugin, and create administrator `phasec_admin` and subscriber
`phasec_subscriber`. Keep generated passwords outside the repository in
`test-users.json` with `admin_password`/`subscriber_password` keys for the HTTP stage.

Set `WP_ENVIRONMENT_TYPE='local'`, `SEO_TIDY_PHASE_C_ISOLATED=true` and
`SEO_TIDY_INTERNAL_LINKING_PREVIEW=true` in that test site's configuration. Its
environment root must have `isolation-marker.json` with `db_name`, `db_host` and
`home` matching the values above; the WordPress root must contain the marker
`.seo-tidy-phase-c-isolated`. The driver requires both markers before proceeding.
Block external WP HTTP and disable cron/automatic updates. Do not reuse an existing
site or its database/configuration.

With environment variables `SEO_TIDY_PHASE_C_ISOLATED=1`,
`SEO_TIDY_PHASE_C_ENV_ROOT` and `SEO_TIDY_PHASE_C_WP_ROOT` set to those directories:

```sh
python tests/integration/run-internal-linking-phase-c.py contracts
python tests/integration/run-internal-linking-phase-c.py http
python tests/integration/run-internal-linking-phase-c.py benchmark200
python tests/integration/run-internal-linking-phase-c.py benchmark1000
python tests/integration/run-internal-linking-phase-c.py races
```

Run each stage once, in order, on a fresh fixture installation. `edit-race` is a
recovery mode for repeating only that probe if combined race orchestration stopped.
The driver stores stage logs/results separately. These commands describe the
historical validation at `253c2cb`; its `races` stage reproduced the old defect.
Use the focused runner below for the fixed runtime's busy-worker protocol. The
earlier full deterministic suite was not rerun during that integration follow-up.

The integration additions are `tests/integration/internal-linking-phase-c.php`,
`tests/integration/internal-linking-phase-c-performance.php`,
`tests/integration/run-internal-linking-phase-c.py`,
`docs/evidence/internal-linking-phase-c-wordpress.json` and this report update.
They are included in the final local concurrency commit. The previously observed
races are fixed below; unverified provider, rendered-link, database-version,
production-load and UI cases still prevent a release-readiness claim. No production
site, production database, deployment, push, merge or GitHub Actions was used.

## Final concurrency safety cycle (2026-10-18)

This cycle used only the two confirmed findings and the existing isolated
WordPress **6.8** / PHP **8.2.6** / MariaDB **11.4.12** environment. It did not run
a new audit, HTTP/contract suite or 200/1000-post benchmark. The database alone
was restarted on loopback; no web server was needed.

### Runtime fix

- A database/WordPress-prefix-specific MySQL/MariaDB **`GET_LOCK(name,0)`** serializes
  scan requests. A competing request redirects with `scan_status=busy` and performs
  no index writes. Locks are released on normal completion/shutdown and by the
  database when a terminated worker disconnects.
- Before clearing or replacing postings, the scanner durably stores
  `complete=false, stale=true, in_progress=true`. A failed or killed request leaves
  that marker. The next manual scan rebuilds its private index instead of treating
  partial postings as a completed snapshot.
- Invalidation atomically increments a monotonic private database option,
  `seo_tidy_link_index_generation_v1`. State and generation are read directly from
  the database; WordPress request-local option caches are not coordination locks.
  An edit increments the generation before persisting the dirty state.
- Final publication is **one conditional SQL update**, requiring the captured
  generation to equal the current generation and the executing connection to own
  the scan lock. A changed generation prevents completion and leaves a durable
  stale/in-progress state for recovery. State readers also reject generation
  mismatches and in-progress snapshots.
- Posting INSERT/DELETE statements check lock ownership **inside SQL**. If `wpdb`
  reconnects after losing its named lock, a replayed write cannot continue posting
  without ownership. Zero inserted rows is a failed scan, not success.

Existing table/column definitions, binary term collation, original option/meta
keys and snapshot revision 5 are retained. The additional private option and
state fields require no table migration. Older snapshots without a generation
are conservatively treated as needing one manual rebuild. Smart Topics values,
published article content, plugin version, administration markup and visual design
are unchanged. Concurrent saves can conservatively require another rebuild;
they cannot publish the interrupted old generation as healthy.

### Only the requested regressions were executed

Command, with the existing isolation environment variables and database running:

```sh
python tests/integration/run-internal-linking-concurrency.py
```

| Targeted regression | Actual result |
| --- | --- |
| Small sequential indexing | One 25-post batch completed; all 25 persisted vectors matched current content; a second healthy request took the completed/no-op path. |
| Concurrent scan race | Worker A paused before INSERT; B returned busy without SQL/duplicate-key errors or state/posting changes. Fresh readers gated retrieval while A was active; A then completed with all vectors correct. |
| Article edit during active indexing | A separate real WordPress save changed the captured body and invalidated it. The old scan could not publish completion. Fresh API and raw persisted state both remained unusable; statistics/candidate retrieval were blocked. One manual recovery batch indexed current content correctly. |
| Interrupted scan and recovery | A healthy index was explicitly restarted, then the paused PHP worker was forcibly terminated. The persisted in-progress/stale marker survived; retrieval remained blocked. The next scan acquired the released database lock and rebuilt all 25 vectors correctly. |

**87 assertions passed, zero failed; runner exit status 0.** Every probe checked
Smart Topics metadata against the same pre-run SHA-256; values were preserved.
The prior full-size corpus was not reactivated: 25 published synthetic posts were
used and the 975 draft posts were not rebuilt. Fresh processes checked both API
state and raw stored state, so request-local caches could not hide a failed write.
The dedicated MariaDB instance was then shut down normally; test data and logs
were retained. No test web server or database service was left running.

The first runner attempt rejected an underscore in a test-only marker before any
save/scan. The marker was normalized to the harness's existing allowed format;
the complete targeted run then passed. No pass is attributed to the setup error.

[Concurrency evidence](evidence/internal-linking-phase-c-concurrency.json) records
the checks and stage results separately from the historical failed-race and
benchmark evidence. Detailed logs remain in the isolated environment.

PHP syntax checks passed for the changed runtime, integration helpers and two
updated standalone test adapters. The standalone fixture/lifecycle adapters were
updated for direct option SQL and generation/lock coordination, preserving their
existing assertions, but **neither standalone suite was executed** under this
cycle's explicit test restriction. Their earlier pass counts are historical,
not results for the new adapters. No broad deterministic suite was rerun.

### Completion and remaining release gates

The two confirmed concurrency defects and interrupted-scan recovery pass the
requested targeted acceptance checks. Remaining limits include untested MySQL/
MariaDB versions, database drop-ins/proxies and forced `wpdb` reconnect scenarios,
provider defaults/filters, rendered/dynamic links, editorial intent holdouts,
uninstall behavior and production load. Earlier benchmark numbers describe the
pre-coordination runtime and were **not** remeasured after adding fresh state
queries and SQL ownership guards. This cycle makes no new performance claim.

All relevant local runtime, integration, adapter, evidence and documentation files
are included in the final local commit following `253c2cb`; no stable-branch,
version, UI or Smart Topics model change was made. No push, merge, GitHub Actions,
deployment or production access occurred. The feature is **not release-ready**.
