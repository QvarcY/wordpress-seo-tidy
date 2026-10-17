# Internal linking recommendations — Issue #29, Phase A design v0.3

Status: **design proposal, not implemented**. Target: incremental MVP, reviewed before coding. This document is intentionally normative where marked "MVP"; thresholds are initial hypotheses, not accuracy claims.

## Chosen architecture: Smart Topics + BM25 (local-first)

The initial feature uses existing WordPress storage and PHP. It does not require Python, PostgreSQL, SQLite extensions, model downloads, commercial APIs, external search services or third-party content transmission. Search-trend or keyword-popularity integrations are not part of issue #29.

**Smart Topics** is a site-local metadata layer. It proposes topical labels from article titles, headings, content and existing categories/tags; the site administrator can review, add, remove, accept or reject these proposals. Approved labels persist independently from public WordPress tags and must not silently generate public tag-archive pages. On existing sites, process published content in resumable batches. Manual changes take precedence over subsequent scans, and automatic proposals must not overwrite administrator-approved labels. An article's topic is independent of external keyword popularity.

**BM25** ranks a bounded set of potentially related articles by the significance of their terms within the site's own content. The index should use normalized title, heading and body terms, with appropriate field boosts and collection-level document frequency. Define smoothing, length normalization, supported tokenization, stopwords and LV inflection handling before finalizing numerical parameters. Candidate discovery combines Smart Topics with lexical evidence; no shared topic by itself guarantees a recommendation. Detect and explain insufficient evidence and possible overlapping intent separately.

**Separation of roles:** topic labels narrow candidate discovery; BM25 orders relevant candidates; existing-link/indexability/canonical exclusions and same-intent warning rules determine whether a suggestion can be displayed confidently. No automatic edits to published articles. No marketing claims about semantic understanding or proven cannibalization detection.

**Future options:** advanced multilingual embedding analysis may be considered independently after measured quality/performance testing, but is not required for this feature, not promised in this release and must not weaken the PHP-only baseline.

### Migration of the preliminary scoring proposal

The provisional 35/25/20/15/5 weighted overlap formula and its 50/70 thresholds are retained below **only as historical experiment context**; they are not the implementation specification. The evaluation work now focuses on BM25 with Smart Topics and separately documented safety gates. BM25 scores are query- and corpus-dependent, so they must not be presented as a universal 0–100 probability or compared across sites without calibration.

## Smart Topics: storage and editorial workflow

### Data representation

For the first release use private, single-value WordPress post metadata, not a new table and not public WordPress tags. Each eligible post/page has one versioned record stored under `_seo_tidy_smart_topics_v1`; the record contains a schema version, a bounded list of approved topics, a bounded list of pending suggestions, an origin for each item (`manual`, `accepted`, `generated`), and the source-content fingerprint used during the last analysis. Use WordPress metadata APIs; register the private field with an explicit capability check, or keep writes behind a dedicated administrator-only endpoint. Never expose pending labels in public page HTML, taxonomies or REST output.

A topic has a normalized stable key and a human-readable label. Normalization uses Unicode-aware lowercase, whitespace collapse and punctuation handling; retain Latvian diacritics in display labels. Do not treat different inflected forms or translations as automatically equivalent. Identical keys merge case/spacing duplicates; administrator-approved aliases may provide explicit equivalence later. Limit labels to a reasonable length and reject empty, unsafe or excessively generic terms. Proposed limits for the first release: up to 12 approved topics and 12 pending suggestions per post; verify with usability testing.

### Suggestions and approval

- Extract candidate terms from article title, headings, high-salience phrases and existing tags/categories. Show a short evidence reason such as “mentioned in the title” or “existing category”, not an invented confidence percentage.
- Existing WordPress tags and categories are **inputs**, not labels automatically written back to the public taxonomy. Do not create public tag archives.
- New proposals are `pending` until accepted. An administrator can accept, reject, add, edit or remove them; save through capability-checked, nonce-protected WordPress requests with sanitization and output escaping.
- Manual edits always win. Automatic rescans may propose new items but cannot silently restore rejected labels, delete approved labels or change manually edited labels.
- Persist rejection history per topic key so a subsequent scan does not repeatedly offer rejected proposals; provide an explicit control to reset it. Keep record size bounded.
- If a post changes, mark its generated evidence stale; retain approved labels and show “Suggestions need refresh” until a new scan completes.
- First installation on an established site uses resumable batches of <=25 posts/pages; incomplete scans must be visible. No analysis is performed automatically during ordinary article page views.
- A site admin can disable Smart Topics suggestions without breaking the existing SEO audit. Disabling does not erase manually approved labels.

### Intended WordPress UI

Provide one compact review panel on the editor screen, plus batch review in SEO Audit → Internal Linking. Display approved topics separately from unreviewed proposals; each suggestion shows the source of evidence. Keep full Latvian and English localization. Bulk approval must not approve labels without a visible summary of the affected posts. Topic metadata is for editorial classification; it must not be confused with public hashtags or keyword rankings.

## BM25: reproducible first-release scoring specification

Use **Okapi BM25** for local candidate ranking. Index each eligible published document using a deterministic Unicode tokenizer and separate title, headings and visible body fields. The first implementation can calculate a single weighted document representation, but must record the weighting rules. Initial field multipliers for evaluation: title ×3, headings ×2, body ×1, capped to prevent repeated words from dominating; these are experimental and must be measured. Approved Smart Topics help retrieve candidates and explain context; they do not directly inflate BM25 scores without an explicit separately documented feature.

For a source query, select a bounded set of informative terms from its title, headings and approved topics. Score each candidate target document using:

`BM25(D,Q) = Σ[t ∈ Q] IDF(t) × ( f(t,D) × (k1 + 1) / ( f(t,D) + k1 × (1 - b + b × |D| / avgdl) ) )`

`IDF(t) = ln(1 + (N - df(t) + 0.5)/(df(t) + 0.5))`

Initial values for evaluation: `k1=1.2`, `b=0.75`. Here `N` is eligible document count, `df(t)` counts eligible documents containing term `t`, `f(t,D)` is weighted token frequency in candidate `D`, and `|D|` and `avgdl` are weighted lengths using the same scheme. Deduplicate query tokens. Handle empty collections and `avgdl=0` explicitly. This establishes **ranking**, not an absolute percentage of relevance.

Use a deterministic top-N candidate retrieval strategy: topic-key overlap, explicit target-topic mentions in the source body and inverted term matches. Do not scan all pairs or assume title keywords alone identify all valid relationships. Candidate retrieval may use local indexes or bounded PHP caches, but must avoid quadratic work on large sites. One language-specific short stopword list per EN/LV may be used; unknown or mixed language receives transparent uncertain classification. Latvian morphology requires evaluated normalization/aliases rather than aggressive, unverified suffix stripping. Phrase matches and aliases must be traceable to evidence.

### Decision rules after BM25 ranking

1. Hard exclusions first: same post, existing directed link, unsupported/unpublished/noindex/password-protected content, ambiguous canonical or wrong host.
2. Verify topical evidence: at least one distinctive shared topic, documented target mention in source context, or independently meaningful term evidence. Generic category overlap alone is insufficient.
3. Check potential same-intent overlap independently: near-identical titles combined with similar headings/body or explicit duplicate relationships produce a review warning, not a confident link.
4. Low-evidence, cross-language and thin-content pairs remain uncertain; suppress unsupported automated suggestions.
5. Sort remaining candidates by BM25 **within the source** and present up to five suggestions with an explanation; do not show a global 0–100 “accuracy” score. No insertion happens automatically.

### Evaluation requirements

Before approving thresholds or release, prepare full-text fixtures with headings, categories, approved topics, rejected topics, existing directed links, publication status and language. Include a minimum of 12 LV/EN scenarios plus separate holdout cases. Measure candidate recall (whether useful pairs reach the top-N set), precision of displayed suggestions, false-positive categories and latency/memory for 200 and 1000 items. The earlier short-snippet lexical trial is not a BM25 validation. Record actual results before claiming performance or accuracy.

## Reproducible synthetic BM25 evaluation fixtures

The repository now includes `tests/fixtures/internal-linking-fixtures.json`: fourteen short but structured fictional articles (English and Latvian), with title, heading, body, topics, categories, status, indexability and outgoing-link fields, plus twelve explicitly labeled directed pairs. The independent standard-library Python 3 evaluator is `tests/fixtures/evaluate-internal-linking.py`. It reports BM25 ranking signals and topic overlaps for these labels, without any connection to WordPress, external services or production data.

Run from the repository root with `python tests/fixtures/evaluate-internal-linking.py`. This utility is a **diagnostic comparison**, not the selected PHP implementation or a test of WordPress compatibility. The parameters and term weights are those currently proposed in the BM25 design. Script execution and reported scores have **not been verified in a local Python runtime in this documentation update**; an actual successful run and inspection of results are required before calibration is considered complete.

The sample corpus is intentionally small and synthetic. Therefore it cannot substantiate user-facing precision, recall, multilingual quality or production performance claims. Next validation must expand the body text and include holdout examples, explicit metadata and exclusion cases, then record real outputs, observed false positives and candidate retrieval failures.

### Independent calculation cross-check (same fixture parameters)

An independent JavaScript calculation of the documented BM25 formula against the fourteen-document fixture produced the following rounded scores: SIGULDA-TRIP → TURAIDA 6.33 (recommend), RIGA-ATTRACTIONS → RIGA-MUSEUMS 5.09 (recommend), RIGA-HOTELS → RIGA-STAY 5.41 (uncertain), GHANA-ITINERARY → ACCRA-ATTRACTIONS 1.67 (recommend), GHANA-BEACH → WP-SECURITY 0.00 (reject). This cross-check is not execution of the committed Python script. The locally available Python environment could not retrieve the GitHub files for direct execution, so Python runtime verification remains open.

These observations demonstrate that high BM25 does not imply a safe recommendation: a competing accommodation page can score similarly to a genuinely useful supporting article. Independently scored reverse links can also rank highly, so directed context and explicit safety gates are indispensable.

### Executed Python baseline check (2026-10-17)

The evaluator logic was reproduced in a local Python 3 environment using the same 14 titles, headings, bodies, topics and 12 directed labels read from the committed fixture. The local reconstruction omitted unused category values; categories are not an input to this evaluator. Python syntax compilation and execution succeeded. All 12 rounded BM25 scores matched the independent calculation (including the values listed above). This is a verified execution of a **local reproduction**, not an execution of the exact GitHub file bytes: the container's direct GitHub network access was unavailable. The committed script was inspected and the calculation-transcription matched, but exact-byte execution remains to be confirmed when the repository is available in a development checkout.

Observed issue: the reverse RIGA-MUSEUMS → RIGA-ATTRACTIONS pair scored 8.03 despite being labeled uncertain; ACCRA-HOTELS → ACCRA-STAY scored 5.17 despite possible competing intent. Therefore sorting solely by BM25 would produce misleading confidence even on this miniature corpus. Ranking and recommendation policy must remain separate.

The fixture is too small to calibrate thresholds or support performance and accuracy claims. The next check is a richer, reserved holdout corpus and explicit same-intent gating; do not treat this as Phase A acceptance.

## Conservative intent and duplicate triage (fixture iteration 2)

A second, purely diagnostic triage has been added to `tests/fixtures/evaluate-internal-linking.py`. The fixture contains 16 directed source/target cases and an explicit editorial `intent` field per article. These labels are **input metadata**, not inferred from the article text, and no automatic intent classifier has been implemented.

The diagnostic applies hard self-link and existing-directed-link exclusions first, then excludes nonpublic targets. If the source and target share at least two exact approved topics **and** the same explicitly provided intent, it returns `possible_same_intent` (uncertain). Pairs with no exact shared topics return `insufficient_topic_evidence` (uncertain). Other pairs become `candidate_only`, meaning **manual review is still required**, not that a link is recommended.

An independent evaluation of this rule structure on the 16 committed labels shows both advantages and limitations: self-link and existing-link examples are excluded, and accommodation-selection pairs receive uncertainty warnings. However, shared broad location topics can allow context-poor pairs through as `candidate_only`; unrelated pairs are currently marked uncertain instead of categorically rejected. These are intentionally conservative triage statuses, not a claim of 16/16 classification accuracy. The evaluator's exact Python file must still be executed in a checkout.

**Implementation consequence:** do not equate `candidate_only` with `recommend`. The next stage needs a directional contextual-evidence rule (“the source actually discusses the target subject”), stronger distinctiveness filtering for generic location topics, and more challenging held-out examples with full texts. No numeric same-intent threshold or decision model is validated for release.

## Implementation handoff

A focused implementation contract, acceptance checklist and release blockers are maintained in [internal-linking-implementation.md](internal-linking-implementation.md). The proposed feature remains read-only and disabled by default until verified. The synthetic fixture includes directional evidence notes; they are editorial expectations, not proven model outputs.

## Verified code baseline (2026-10-17)

- `includes/SEOAudit.php`: `hasInternalLink()` inspects content HTML via `WP_HTML_Tag_Processor` and only reports whether an eligible internal link exists. It does not resolve recommended pairs.
- `buildIncomingContentLinks()` scans up to 201 published posts/pages and returns unavailable when >200. Cached for five minutes in `seo_tidy_incoming_links_v1`. This implementation is **not** the foundation for a site-wide recommendation scan.
- `SEOAudit::registerRoutes()` exposes read-only `GET /seo-tidy/v1/audit`, gated by `manage_options`.
- `includes/Dashboard.php` already exposes `GET /seo-tidy/v1/content`, paginates by 20 and checks `manage_options`.
- `includes/Bootstrap.php` registers PHP modules/routes, admin assets and translations; `src/index.js` holds the admin sections, including SEO Audit.
- Root plugin currently declares v1.0.0, WordPress >=6.8 and PHP >=8.1; JS is built with `@wordpress/scripts`.

## Scope, guarantees and exclusions

MVP gives **manual-review suggestions**, never edits post content. All analysis stays on the site's WordPress server: no paid external services or third-party content transfer. No statement about actual Google ranking, search-volume, keyword position or proven cannibalization; these are unavailable from local content. Explain each suggestion in plain English and Latvian.

MVP eligibility: local `post` and `page` with `publish` status, public/indexable, resolvable canonical permalink, permitted site host; exclude password-protected posts, attachments, revisions, drafts, private, trash, noindex (from verified supported providers or effective page-level robots rules), invalid or stale targets. Other custom post types are out of scope until explicitly audited. Site-level discouraging search engines must produce a warning, not automatically imply a per-item noindex status. Honor canonical overrides if a supported canonical source can be reliably established; otherwise exclude uncertain items or label unsupported canonical providers — do not silently assert canonical consistency.

Source and target must differ. Already-linked source→target pairs excluded regardless of anchor repetition; reverse link target→source does **not** disqualify source→target (different directed edge). Fragments are not separate targets. Resolve root-relative and absolute URLs, same-site host/port and WordPress permalink variants, stripping fragments/tracking params only for identity; do not treat arbitrary relative or off-domain URLs as safe local links. Use core APIs to resolve post IDs. Where identity is ambiguous, skip.

## Historical scoring proposal: evaluation reference only

1. Snapshot a bounded batch of eligible posts with post ID, canonical URL, title, H1/H2, excerpt, cleaned visible text, category/tag terms, language hint and outgoing resolved post IDs. Never store raw private content.
2. Tokenization: lowercase with Unicode support; preserve LV diacritics; Unicode letters/numbers; discard punctuation and trivial tokens; remove small versioned EN/LV stopword lists. Never translate or strip accents to force matches. Detect language only from explicit metadata where supported; otherwise mark unknown. For mixed/unknown language, penalize confidence, not silently guess. No stemming in MVP.
3. Candidate retrieval first: shared informative title/heading terms or taxonomies. Cap retrieved targets per source BEFORE deeper scoring. Do not compare every post against every post on large sites.
4. Explainable provisional relevance score 0–100 (weights are tunable, not empirically validated):
   - title/title and title/heading distinctive-term overlap: up to 35
   - heading/topic-term overlap: up to 25
   - content-term similarity (bounded normalized keyword frequencies): up to 20
   - matching meaningful categories/tags: up to 15
   - explicit complementary-context evidence from source text: up to 5
   Do not infer popularity, search rankings or semantic relationships from shared taxonomy alone.
5. Negative/uncertainty gate **before showing as high confidence**:
   - Very similar titles + substantial heading/content overlap → flag `possible_same_intent`; no confident linking suggestion.
   - Thin text, few distinctive terms, cross-language mismatch or unresolved URL/canonical → `insufficient_evidence` or excluded; never fake precision.
   - Shared generic travel/business terms only → suppress.
   - Flag is a *review indicator*, not a definitive duplicate or cannibalization finding.
6. Initial display bands **subject to calibration**: 70–100 = reviewable high topical relevance, 50–69 = reviewable moderate, <50 = not suggested. High relevance never overrides negative gate. A clear sentence must list actual positive evidence and uncertainty for each item. Scores are internal heuristics, not SEO success probabilities.
7. Suggest anchors only when the source visibly contains a naturally usable short phrase referring to target; otherwise leave anchor blank with "Choose anchor manually". Do not fabricate exact source quote and never insert automatically.

**Design validation gate:** before implementation, hand-label at least 12 EN/LV sample source→target pairs as recommend / do-not-recommend / uncertain. Adjust weights and thresholds once using those cases; record changes here. In particular do not claim that lexical overlap reliably recognizes search intent.

## Example pairs (expected behavior, not test results)

| Source | Target | Expected |
| --- | --- | --- |
| Top places to visit in Ghana | Things to do in Accra | Suggest if the source discusses Accra and is not already linked |
| Best hotels in Accra | Where to stay in Accra | Possible same-intent review; do not present high-confidence suggestion |
| Visiting Riga with children | Family-friendly Riga museums | Suggest with rationale if contextual evidence exists |
| Labākās viesnīcas Rīgā | Kur nakšņot Rīgā | Possible same-intent, even with Latvian inflections; uncertain case |
| Rīgas apskates vietas | Kā nomainīt WordPress tēmu | Suppress |
| Raksts par Rīgu | Riga travel guide | Uncertain unless explicit bilingual metadata/context supports relevance |
| Source already links to target | Same target | Exclude directed duplicate |
| Target links to source, not vice versa | Target | May suggest if otherwise eligible |
| Published source | Noindex/draft/password-protected target | Exclude |
| Thin posts sharing a generic tag | Each other | Suppress or insufficient evidence |


## Review dataset: 14 manually classified scenarios

These labels are specification examples, **not results from the implementation**. They test key rules rather than establish statistical precision. Assumptions: published/indexable content and no existing directed link unless stated.

| ID | Language | Source → target | Expected class | Explanation |
| --- | --- | --- | --- | --- |
| E01 | EN | Ghana itinerary → Things to do in Accra | Recommend | City detail expands a mentioned stop |
| E02 | EN | Things to do in Accra → Accra airport transfer guide | Recommend | Practical supporting guide for visitors |
| E03 | EN | Best hotels in Accra → Where to stay in Accra | Uncertain | Strong likelihood of overlapping user intent |
| E04 | EN | Ghana travel guide → Ghana travel guide 2026 | Uncertain | Possibly revised duplicate, requires content review |
| E05 | EN | Best beaches in Ghana → WordPress security checklist | Reject | No meaningful topical connection |
| E06 | EN | African travel news → Things to do in Accra | Reject | A broad tag alone is not sufficient evidence |
| L01 | LV | Rīgas apskates vietas → Rīgas muzeji ģimenēm | Recommend | Complementary narrower family activity |
| L02 | LV | Ko redzēt Siguldā → Turaidas pils apmeklējums | Recommend | Detailed article covers a listed destination |
| L03 | LV | Labākās viesnīcas Rīgā → Kur nakšņot Rīgā | Uncertain | Similar accommodation search intent |
| L04 | LV | Rīgas ceļvedis → Rīgas ceļvedis 2026 | Uncertain | May be update/duplicate, not a confident link |
| L05 | LV | Rīgas apskates vietas → Kā atjaunināt WordPress | Reject | Shared generic words cannot establish relevance |
| L06 | LV | Ceļojumi Latvijā → Siguldas apskates vietas | Uncertain | Generic topic without specific contextual reference |
| X01 | LV/EN | Latvijas pilis → Castles of Latvia | Uncertain | Translation/near-duplicate risk, absent explicit language metadata |
| X02 | EN | Accra itinerary already links to Accra museums → Accra museums | Reject | Existing directed link excludes repeated suggestion |

**Interpretation:** `Recommend` means *eligible for a reviewable suggestion*, never automatic insertion. `Uncertain` means show as an optional review warning or suppress, never a confident recommendation. `Reject` means no suggestion. Titles alone are insufficient to validate score weights; the 35/25/20/15/5 weights and 70/50 thresholds remain **unvalidated** until representative article text is available and the implementation is exercised. No precision/recall or false-positive rate is claimed.

## Baseline lexical experiment (2026-10-17)

This small **synthetic** experiment probes the limits of direct word matching. It is not an implementation benchmark, does not evaluate full articles, and is not evidence of recommendation accuracy.

Fourteen short source/target content snippets were constructed from the labeled scenarios above. A deliberately simple Unicode lowercase tokenizer removed punctuation, tokens shorter than three characters and a short EN/LV stopword set. For both title and body, unique-token Jaccard similarity was calculated as `|intersection| / |union|`. The diagnostic partial score is `35 × title_Jaccard + 20 × body_Jaccard` (maximum 55). No heading, taxonomy, context, eligibility or uncertainty factors were included. Published numerical values are rounded, and therefore cannot validate the separate 100-point model.

| Scenario | Title/Body diagnostic score out of 55 | Expected outcome |
| --- | ---: | --- |
| E01 — Ghana itinerary → Things to do in Accra | 5.7 | Recommend |
| E02 — Things to do in Accra → airport transfer | 14.7 | Recommend |
| E03 — Best hotels in Accra → Where to stay in Accra | 20.6 | Uncertain |
| E04 — Ghana travel guide → Ghana travel guide 2026 | 38.3 | Uncertain |
| E05 — Ghana beaches → WordPress security | 0.0 | Reject |
| E06 — African travel news → Things to do in Accra | 0.0 | Reject |
| L01 — Rīgas apskates vietas → Rīgas muzeji ģimenēm | 9.5 | Recommend |
| L02 — Ko redzēt Siguldā → Turaidas pils apmeklējums | 2.5 | Recommend |
| L03 — Labākās viesnīcas Rīgā → Kur nakšņot Rīgā | 10.4 | Uncertain |
| L04 — Rīgas ceļvedis → Rīgas ceļvedis 2026 | 40.5 | Uncertain |
| L05 — Rīgas apskates vietas → WordPress atjaunināšana | 1.8 | Reject |
| L06 — Ceļojumi Latvijā → Siguldas apskates vietas | 0.0 | Uncertain |
| X01 — Latvijas pilis → Castles of Latvia | 0.0 | Uncertain |
| X02 — Already linked Accra itinerary → Accra museums | 16.7 | Reject — existing link override |

**Findings:** lexical overlap systematically underestimates useful narrower-topic links and can elevate near-duplicate content. Latvian inflection and multilingual comparisons lower naive overlap further. A high raw lexical score must never overrule existing-link exclusions or possible same-intent warnings. The original thresholds (50/70 on the complete 100-point model) must remain unapproved.

**Design adjustment:** candidate generation should recognize explicit target topic mentions in the *source body*, including normalized destination/entity phrases and a bounded local index; the similarity stage should require independently explainable evidence. Same-intent warnings must be a separate rule before ranking. Avoid asserting semantic intent recognition or canonical equivalents without evidence. Candidate retrieval must have an alternate way to find title/topic matches despite Latvian endings; define and unit-test a conservative normalization strategy before implementation. Translation-equivalent pages must be treated as uncertain unless explicit multilingual relationships are available.

**Next validation:** replace short snippets with a reviewable fixture of full-text examples, including headings, taxonomy, WordPress metadata, canonical IDs and existing links; implement a standalone reproducible evaluator and inspect per-feature contributions, missing candidates and negative gates. Tune weights only after this more realistic corpus exists. Preserve holdout cases to avoid optimizing exclusively on the calibration set.

## Verified WordPress integration details

- `SEOAudit::noindexSource()` handles global `blog_public=0` as a site warning and recognizes `_yoast_wpseo_meta-robots-noindex=1` plus `rank_math_robots` containing `noindex`. This is **not a comprehensive page-level robots or canonical resolver**.
- `Metadata.php` registers only `_seo_tidy_title` and `_seo_tidy_description`; no SEO-TidY-owned noindex/canonical fields were found there. Consequently, do not refer to an existing SEO-TidY noindex setting as implemented.
- Canonical overrides and effective `wp_robots` output require a deliberate compatibility policy before implementation. For MVP, ambiguous overridden canonicals should be excluded with a clear reason; additional SEO provider compatibility requires explicit verification.
- Admin menu is built in `src/index.js` with an existing `audit` section and `SEOAuditOverview` component. Recommended first integration: a distinct **Internal Linking** panel within SEO Audit rather than a new top-level menu. Keep current audit 20-item paging and its 200-item incoming-link limitation unchanged.
- The admin script uses WordPress `__()` with domain `seo-tidy`, and `scripts/check-i18n.cjs` validates Latvian JSON catalogs. All new user-facing strings need translated entries and must pass the existing localization checks.

## MVP implementation decisions

- Use the existing administrator capability `manage_options` for the new read-only REST endpoint; do not expose content to anonymous users.
- Introduce a separate service that can be disabled without changing the existing audit output.
- Use candidate retrieval with capped work per source and show explicit incomplete/paused scanning status. Do not advertise site-wide results until scan completion.
- Retain all classification reasons as plain text suitable for localized display.
- Do not release until corpus-based weight calibration, actual provider behavior and WordPress integration tests are complete.

## Proposed implementation architecture

Prefer separate, testable responsibilities rather than expanding `SEOAudit.php` into a monolith:
- **Eligibility/URL resolver:** public status, supported type, noindex, canonical and existing directed links.
- **Candidate index + scorer:** pure functions where possible; versioned tokenization and weights; independent negative gates.
- **Bounded scan/cache service:** paginated batches, progress state, cache version/invalidation and stale outcomes.
- **Read-only REST/admin presentation:** route gated by `manage_options`, properly escaped output and localized EN/LV strings.

MVP admin: under SEO Audit or a dedicated "Internal Links" section show suggestion count, source title/edit link, target title/permalink, evidence, confidence/uncertainty label, "already linked" omission, scan status, empty/error states and a clear "review manually" notice. No content-writing endpoint.

## Resource policy (targets to verify, not benchmarks already measured)

- Never run full-site synchronous all-pairs scans on ordinary admin page load.
- Start with chunks of <=25 source posts per request/work item; max <=30 candidate targets per source; max 5 shown suggestions per source. Make these configurable internally after measurement.
- Versioned short-lived cache keyed by site/content generation + algorithm version, with refresh on edit, publish/unpublish, trash, link/SEO metadata edits, permalink/home settings. Avoid clearing whole-site cache on unrelated admin page views.
- Large sites (>200, >=1000 items): process in batches, show partial/in-progress state, support resumption, avoid request timeouts; no hard 200-post cap. Define worst-case query and memory bounds in tests.
- No cron or background worker is mandatory in MVP. Use explicit administrator-triggered resumable scanning, safely handling nonces/auth/capabilities as appropriate.
- Under failure show a transparent unavailable/incomplete message and keep old audits unaffected. Never turn a partial scan into an unexplained zero-suggestion count.

## Tests and evidence required before release

Unit: token normalization, LV diacritics/EN stopwords, thresholds/negative gate, thin and mixed-language cases, exact directed pair de-duplication. WordPress integration: permalink variants, fragment/external/relative URLs, published/noindex/private/password/deleted, canonical overrides and role boundaries. UI: loading/partial/empty/error, accessible EN/LV copy. Performance: <=200 and >200, then >=1000 published items with bounded batches, edits invalidating stale entries, restart/resume. Regression: existing `review_internal_links` and `review_incoming_links` behavior unchanged.

Record measured time/memory and sample false positives during Phase C/D; test suite should be targeted during implementation and complete at release gate. Run targeted checks during development and the full relevant suite before release.

## Checkpoint / decision record

- [x] Source code inventory of key audit/REST/bootstrap/UI entrypoints.
- [x] Documented local-first Smart Topics + BM25 as the implementation direction, with the older scoring weights retained as experimental reference.
- [x] Drafted MVP exclusions, uncertainty gates, bounded scanning and UI contract.
- [x] Specified initial BM25 formula, weighting proposals and Smart Topics editorial state/persistence rules.
- [x] Added reproducible bilingual synthetic corpus and standalone diagnostic scoring script.
- [x] Executed a Python reproduction of the evaluator on the reconstructed 14-document corpus; all 12 scores matched independent calculations.
- [ ] Verify the exact committed evaluator in a development checkout and validate against holdout full-text cases before approving numerical parameters.
- [x] Prepared and manually classified 14 representative EN/LV scenarios (expected labels only).
- [ ] Calibrate weights and thresholds on representative full-text samples; not yet validated.
- [x] Inspected implemented Yoast/Rank Math noindex handling and SEO-TidY metadata fields.
- [ ] Finalize effective robots/canonical compatibility rules for the new feature.
- [x] Identified existing audit panel and Latvian translation catalog checks.
- [ ] Record representative performance budgets after first measurements.
- [x] Added conservative intent/existing-link triage and inspected outputs across 16 synthetic pair labels.
- [ ] Establish directional contextual evidence and reject-vs-uncertain policy on held-out examples.
- [x] Produced a limited implementation handoff with risk boundaries and acceptance tests.
- [ ] Verify the exact evaluator and held-out data before claiming scoring quality; full feature is not yet release-ready.

No runtime code changed and no implementation/test claims are made in this document.

Related: https://github.com/QvarcY/wordpress-seo-tidy/issues/29
