# Internal linking — implementation handoff

Status: **design-approved baseline for a limited, read-only prototype**. No production feature or accuracy claim is implied.

## Scope of first implementation
Build a PHP-only WordPress module behind an administrator-only feature gate. Keep existing audit endpoints and user-facing behavior unchanged. Start with Smart Topics suggestion generation and read-only candidate inspection; do not auto-create public tags, modify posts or insert links.

## Pipeline
1. Eligibility gate: published post/page, publicly indexable, no password, resolved local canonical and supported provider settings; exclude uncertain canonical identities.
2. Snapshot article title, headings, rendered textual content, categories, existing WordPress tags, existing outgoing links and explicitly approved private topics. Use safe extraction, bounded content and deterministic normalization.
3. Smart Topics: propose normalized, informative phrases and evidence; keep proposals pending until reviewed. Persist approvals/rejections separately from generated state, retaining editorial overrides after content edits.
4. Candidate discovery: build a bounded inverted index from approved topics and meaningful terms; retrieve max 30 candidates per source. Do not rely on matching generic geography/category tags.
5. Directional evidence: require a meaningful target phrase or approved alias in the source title/headings/body, OR clearly specific topical evidence with explanation. Inflected LV names must not be equated by naive suffix stripping. If ambiguous, mark uncertain.
6. BM25: use documented `k1=1.2`, `b=0.75` and experimental title/headings/body boosts (3/2/1) for ordering *within one source*. Never call BM25 a confidence percentage.
7. Decision gate: exclude self/already linked/ineligible; warn on near-duplicate or same-intent similarities, even with high BM25. Generic, thin or cross-language matches are uncertain. A mere shared broad topic does not become a recommendation.
8. Show up to five explanatory, manually reviewable source→target suggestions and explicit incomplete/empty/error states.

## Storage and security
Use versioned private post meta with bounded approved/pending/rejected topic lists; register field with capability restrictions or dedicated privileged routes. All state-changing requests require administrator permissions and nonce handling. Do not expose private labels in public HTML or automatically create taxonomy archives. Retain editor changes on rescans.

## Performance contract
Read-only manual/resumable batches <=25 source posts, <=30 candidates per source. No synchronous entire-site scan on page load, no unbounded pairwise comparisons, no assumed 200-post limit. Record progress and cache invalidation when posts/status/permalink/topic changes.

## Acceptance gate for prototype
- Read-only feature disabled by default; no changes to current SEO Audit.
- Three known positive travel-content pairs are discoverable.
- Accommodation guides with overlapping intent cannot become confident recommendations.
- Self, already-linked and nonpublic targets are excluded.
- Both LV and EN texts render and can be edited/reviewed safely.
- A 200+ article fixture completes via bounded batches; 1000-item performance is measured before release.
- Existing test suite remains passing and changes are documented.

## Remaining before release
Run the exact committed Python diagnostic and the future PHP behavior against the same fixtures; record mismatches. Add richer holdout articles and instrument elapsed time and peak memory. Verify effective WordPress/Yoast/Rank Math robots and canonical behavior with real integration tests. This document is a scope definition, not verification that these checks passed.

Related: https://github.com/QvarcY/wordpress-seo-tidy/issues/29
