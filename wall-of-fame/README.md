# SEO-TidY Wall of Fame

Standalone, bilingual (English/Latvian), moderated website directory. This is a **separate deployable Cloudflare Worker application**, maintained in the SEO-TidY repository until a dedicated site/domain and deployment are approved.

## What works

- Public responsive directory at \`/\` — only sites with \`approved\` status are visible
- Optional self-registration: website name, canonical HTTPS URL and description
- Cloudflare Turnstile on every registration (fail-closed when not configured)
- Proof of domain control via DNS TXT at \`_seo-tidy.<domain>\`
- Domain proof must pass before moderation, and is checked **again** on approval
- Moderator list, approve, reject, and delete endpoints protected with a high-entropy server-side secret
- Private applicant management key (presented once), status lookup, manual verification and permanent withdrawal
- No IP storage, no visitor analytics or WordPress passwords; explicit consent required
- English and Latvian interface
- No payment tiers, advertising, or ranking boosts

**Not automatically published.** The existing WordPress plugin's \`seo_tidy_community_*\` options remain local; there is **no data transfer** from those options and no remote enrollment without a separate explicit user action.

## Requirements

Cloudflare Workers, Cloudflare D1, Cloudflare Turnstile, Node 24+, and a dedicated HTTPS origin. There is no WordPress/PHP runtime dependency on the directory side.

## First deployment (operator only)

1. Choose a final dedicated HTTPS domain.
2. Create a Cloudflare D1 database named \`seo-tidy-wall-of-fame\`.
3. Copy \`wrangler.toml.example\` to \`wrangler.toml\`, insert the D1 database ID, dedicated public origin, and your Turnstile **public site key**. Restrict the Turnstile widget to this hostname.
4. From this folder, run \`npm install\`, then \`npm test\`.
5. Create the database schema: \`npx wrangler d1 execute seo-tidy-wall-of-fame --remote --file=./schema.sql\`.
6. Set the moderator credential using \`npx wrangler secret put ADMIN_TOKEN\`. Generate a random value of **at least 32 characters**, and store it securely outside Git.
7. Set \`npx wrangler secret put TURNSTILE_SECRET\`, with the matching Turnstile secret key. Never place this secret in \`wrangler.toml\`.
8. Run \`npx wrangler deploy\`, configure the dedicated custom domain/route, and check public \`/api/config\` and \`/api/sites\`.

Do **not** execute remote D1 schema creation or Workers deployment without operator approval. \`wrangler.toml.example\` intentionally contains placeholders and cannot deploy unchanged.

## Registration / domain verification

Applicant enters HTTPS domain, name, description and explicit publication consent; completes Turnstile. API returns:

- \`id\`: application ID
- \`ownerSecret\`: private management token, shown **once**, stored in the database only as SHA-256 hash
- \`dnsName\`: \`_seo-tidy.example.org\`
- \`dnsValue\`: \`seo-tidy-verification=<random token>\`

Applicant adds the TXT record to the site's DNS, then clicks **Verify DNS record**. The Worker uses Cloudflare DNS-over-HTTPS (TXT records only; no fetch of the submitted website, avoiding SSRF). DNS propagation may take time. Verification marks the application \`verified\` — still **not public**. Administrator manually approves after reviewing content, at which point the site appears in the directory.

The management key also enables status lookup and permanent deletion at any time, including after approval. Losing it requires contacting the operator; no recovery email or email addresses are collected.

## Moderation

A standalone browser interface is available at `/admin.html`. The operator pastes the administrator token per session; it is kept in JavaScript memory only, not in cookies or localStorage. For stronger access control, protect `/admin.html` with Cloudflare Access before launch. Admin endpoints always require the server-side bearer token regardless of access to the HTML.

### CLI alternative

Use a secret stored only in the moderator's local shell:

\`\`\`bash
export BASE=https://your-wall-of-fame.example
export ADMIN_TOKEN='your-private-token'
curl -fsS -H "Authorization: Bearer $ADMIN_TOKEN" "$BASE/api/admin/submissions"
curl -fsS -X POST -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"id":"APPLICATION-UUID","decision":"approve"}' "$BASE/api/admin/moderate"
\`\`\`

Other decisions: \`reject\` (hides, retains record) and \`delete\` (erases the application). Approval will be rejected if DNS proof is missing. Never insert moderator credentials into the public site, WordPress settings, Git history or screenshots.

## API

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | \`/api/config\` | Public | Turnstile public key |
| GET | \`/api/sites?page=1\` | Public | Approved cards, 12 per page |
| POST | \`/api/apply\` | Turnstile | Submit consented profile |
| POST | \`/api/verify\` | Applicant private key | Verify DNS TXT proof |
| POST | \`/api/status\` | Applicant private key | Check application status |
| POST | \`/api/remove\` | Applicant private key | Withdraw and delete |
| GET | \`/api/admin/submissions\` | Admin bearer token | Review applications |
| POST | \`/api/admin/moderate\` | Admin bearer token | Approve/reject/delete |

## Privacy and operations

Only approved name/URL/description/approval date are returned by the public API. Private management tokens never appear in public listings. D1 stores applicant-provided profile fields, the domain-verification challenge, hashed management key, status and timestamps, but does **not** intentionally store visitor IP addresses. Cloudflare platform logs and abuse controls have their own policies/configuration; review Cloudflare data processing and log retention before launch.

Set Cloudflare rate limits (particularly \`/api/apply\`, \`/api/verify\`, \`/api/status\`, \`/api/remove\`, \`/api/admin/*\`) in zone/WAF settings before launch. Turnstile is mandatory but is not a substitute for rate limiting. Keep backup and incident recovery access for D1 and secrets.

## Scope / future integration

The independent service is implemented; the existing WordPress plugin is **not** automatically enrolled or coupled to a not-yet-chosen public URL. When deployment URL is established, add a clearly labeled external enrollment link to the plugin's Wall of Fame settings, retaining explicit opt-in and local-only settings until the owner submits the application through the external service.

This branch implements the service, not the DNS assignment or production deployment.
