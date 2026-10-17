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

MVP eligibility: local `post` and `page` with `publish` status, public/indexable, resolvable canonical permalink, permitted site host; exclude password-protected posts, attachments, revisions, drafts, private, trash, noindex (including SEO-TidY's own flags and known WordPress settings), invalid or stale targets. Other custom post types are out of scope until explicitly audited. Site-level discouraging search engines must produce a warning, not automatically imply a per-item noindex status. Honor canonical overrides if a supported canonical source can be reliably established; otherwise exclude uncertain items or label unsupported canonical providers — do not silently assert canonical consistency.

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
- [ ] Hand-labeled >=12 EN/LV pairs and calibrated thresholds.
- [ ] Decide reliable supported noindex/canonical providers after inspecting `Metadata.php` and existing tests.
- [ ] Check actual admin composition and translation extraction mechanism before coding.
- [ ] Record representative performance budgets after first measurements.
- [ ] Review and approve Phase A before beginning implementation.

No runtime code changed and no implementation/test claims are made in this document.

Related: https://github.com/QvarcY/wordpress-seo-tidy/issues/29
