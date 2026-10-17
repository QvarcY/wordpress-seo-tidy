# Internal linking recommendations — Issue #29, Phase A design v0.1

Status: **design proposal, not implemented**. Target: incremental MVP, reviewed before coding. This document is intentionally normative where marked "MVP"; thresholds are initial hypotheses, not accuracy claims.

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

## MVP proposal: deterministic candidate → evidence → decision pipeline

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
- [x] Draft MVP scope, exclusions, candidate/scoring model, uncertainty, batching and UI contract.
- [x] Prepared and manually classified 14 representative EN/LV scenarios (expected labels only).
- [ ] Calibrate weights and thresholds on representative full-text samples; not yet validated.
- [x] Inspected implemented Yoast/Rank Math noindex handling and SEO-TidY metadata fields.
- [ ] Finalize effective robots/canonical compatibility rules for the new feature.
- [x] Identified existing audit panel and Latvian translation catalog checks.
- [ ] Record representative performance budgets after first measurements.
- [ ] Review and approve Phase A before beginning implementation.

No runtime code changed and no implementation/test claims are made in this document.

Related: https://github.com/QvarcY/wordpress-seo-tidy/issues/29
