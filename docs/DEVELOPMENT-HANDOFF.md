# SEO-TidY — development handoff and pause

**Checkpoint:** 2026-10-18. **Status:** feature development voluntarily paused while awaiting contributors, targeted independent review, and a later explicit maintainer decision. This is a **resume note**, not a shipping checklist or declaration of completion.

## Released versus experimental

| Area | Actual status | Where |
| --- | --- | --- |
| SEO-TidY stable plugin | **v1.0.0**, available for normal installation and WordPress in-dashboard updates from eligible GitHub Releases; not changed by this pause | [v1.0.0 release](https://github.com/QvarcY/wordpress-seo-tidy/releases/tag/v1.0.0), [README](../README.md) |
| Relevance-aware internal linking and Smart Topics | **Experimental**, not part of v1.0.0. The latest code, tests, and documentation are on the remote feature branch, but acceptance remains open | [Issue #29](https://github.com/QvarcY/wordpress-seo-tidy/issues/29), [Draft PR #31](https://github.com/QvarcY/wordpress-seo-tidy/pull/31) |
| International article-language management | **Planned, not implemented.** Avoid a hard-coded LV/EN default. Use explicit editor choice, site/author defaults and conservative legacy-post review; interoperability with WPML/Polylang to be coordinated | [Issue #29](https://github.com/QvarcY/wordpress-seo-tidy/issues/29), [Contributors #32](https://github.com/QvarcY/wordpress-seo-tidy/issues/32) |
| Stable release publishing for later versions | Version-tag filter no longer hardcoded to `v1.0.0`; operational procedure documented. The next release has **not** been tested/published | [Completed #35](https://github.com/QvarcY/wordpress-seo-tidy/issues/35), [RELEASING.md](RELEASING.md) |

## Last confirmed development revision

- Repository: `QvarcY/wordpress-seo-tidy`; target `main`; development branch `feature/issue-29-smart-topics-prototype`.
- Draft PR #31 remote head (verified 2026-10-18): `e2150a3f4a0e06f4b53100b221775f2f454433db`.
- Substantive Codex concurrency fix: `6b54576b1012d8b60f8c7dc05cf05cd52b716abc` (parent of the empty `[skip ci]` head commit).
- Files include `includes/InternalLinkingIndex.php`, the [Phase C report](https://github.com/QvarcY/wordpress-seo-tidy/blob/feature/issue-29-smart-topics-prototype/docs/internal-linking-phase-c.md), [concurrency evidence JSON](https://github.com/QvarcY/wordpress-seo-tidy/blob/feature/issue-29-smart-topics-prototype/docs/evidence/internal-linking-phase-c-concurrency.json), and [concurrency harness](https://github.com/QvarcY/wordpress-seo-tidy/blob/feature/issue-29-smart-topics-prototype/tests/integration/run-internal-linking-concurrency.py).
- Targeted local concurrency run: **87 / 87 assertions reported passed** on an isolated 25-published-post dataset (WordPress 6.8, PHP 8.2.6, MariaDB 11.4.12); Smart Topics metadata remained intact. Final wider benchmark and regression suites were not rerun.
- The user's local checkout was last reported clean following the push. Common local Windows working directory at the time: `C:\Users\mail\Desktop\CODEX\wordpress-seo-tidy`. **Do not assume this folder, worktree, branches or status will remain unchanged; verify before any commands.**
- At verification time, the `[skip ci]` head push created no GitHub Actions workflow runs. **No merge, release tag, deployment or production indexing** was performed.

## Outstanding review and acceptance gates

1. **Database failure during invalidation:** evaluate the static concern that if both the generation increment and fallback state write fail, an old `complete=true, stale=false` state could later be trusted. This is a suspected failure path, **not** a reproduced incident.
2. **Reader/rebuild race:** evaluate the possibility of a reader observing a previous valid state across the beginning of index mutation. No reproduction was established with current evidence.
3. **Broader compatibility and correctness:** forced database reconnects, more MySQL/MariaDB/provider combinations, canonical/noindex/URL rules, dynamically rendered links, permissions, admin accessibility, recovery/cleanup and SEO Audit regressions.
4. **Editorial semantics and language safety:** use existing real published complementary versus competing articles, including LV/EN versions, for editorial checks. Translations are not automatically cannibalization; unconfirmed cross-language recommendations should not be confident. Do **not** alter production content during exploration.
5. **Release process:** update documentation/CHANGELOG and verify accepted package/update only once all relevant acceptance gates pass. Do not infer release readiness from the 87 targeted checks.

**Current decision:** keep #29 open and PR #31 in **Draft**; defer any merge, release or production write.

## Community contribution entry points

- [#32 — help wanted overview](https://github.com/QvarcY/wordpress-seo-tidy/issues/32): multilingual UX design, WPML/Polylang research, documentation/localization, accessibility feedback.
- [#33 — installation and WordPress update QA](https://github.com/QvarcY/wordpress-seo-tidy/issues/33): clean isolated v1.0.0 checks.
- [#34 — existing incoming-link audit above 200 articles](https://github.com/QvarcY/wordpress-seo-tidy/issues/34): separate from experimental BM25 linking.
- [#36 — contribution guide and issue templates](https://github.com/QvarcY/wordpress-seo-tidy/issues/36): onboarding for international contributors.
- [#35 — stable release infrastructure](https://github.com/QvarcY/wordpress-seo-tidy/issues/35): **completed/closed**.

Volunteers should **comment on the relevant issue first**, describe their desired scope and coordinate with the maintainer before opening substantial code PRs. Existing open help-wanted issues are left unassigned so contributors may express interest. A comment or proposal does not itself authorize merging or deployment.

## When development resumes

1. Review new issue comments and PR submissions first. Confirm who is working on what, and avoid duplicating contributors' in-progress tasks.
2. Refresh the repository/PR state; do not rely blindly on the checkpoint SHA. Compare `git status`, `git branch --show-current`, `git log -3 --oneline`, `git fetch`, and relevant remote changes. Never discard local changes or force-push automatically.
3. Read [Issue #29](https://github.com/QvarcY/wordpress-seo-tidy/issues/29), [Draft PR #31](https://github.com/QvarcY/wordpress-seo-tidy/pull/31), the latest Phase C report and targeted evidence before deciding any additional tests or code changes.
4. Prefer one **narrow, justified** follow-up at a time, especially the two remaining static race/failure questions. Keep evidence and limitations in docs.
5. Avoid expensive hosted Actions and redundant full-suite tests while planning. **Do not skip necessary release validation** when actual publication is approved. A `[skip ci]` commit can prevent certain push/PR workflows but is not a substitute for mandatory release checks.
6. Never change production `kas.id.lv`, merge PR #31, or create a GitHub tag/release without an explicit go-ahead. A future version remains unannounced until actually shipped.

### Short continuation prompt

> Continue SEO-TidY from `docs/DEVELOPMENT-HANDOFF.md`, GitHub Issue #29 and Draft PR #31. First check new contributor comments and actual repository/branch status. Respect the stable-versus-experimental boundary, avoid repeated broad tests and GitHub Actions costs, and do not modify production, merge, tag or publish without permission. Recommend the smallest next action based on new evidence.

**Maintainer note:** This checkpoint deliberately freezes *active development*, not discussion. New bug reports and volunteers remain welcome. No artificial deadlines or promise of search-ranking improvements.
