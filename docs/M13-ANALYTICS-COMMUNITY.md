# M13 — Analytics, Stats Badge & Community

## Goal

Build optional, privacy-conscious website statistics and a
configurable public badge, followed by an opt-in community showcase.

## M13.1 — Analytics Engine

- Disabled by default
- Separate human and suspected bot traffic
- Daily and total statistics
- Clearly define visits, unique visitors and online estimates
- Do not persist raw IP addresses
- Do not make external tracking requests
- Avoid counting administrative requests
- Support data retention and deletion
- Never invent statistics
- Account for page caches and missed server-side requests
- Prefer efficient aggregated storage over unbounded event logs

## M13.2 — Live Stats Badge

- Disabled by default
- Light, dark and transparent themes
- Compact and expanded variants
- Icons with or without visible labels
- Independently configurable metrics
- Optional Powered by SEO-TidY attribution
- Attribution must remain independent from analytics
- Responsive, accessible and theme-compatible markup
- Public output must be escaped and safely cached
- No fake demo values in production

## M13.3 — Wall of Fame

- Explicit opt-in
- No automatic registration or telemetry
- User controls public site name, URL and description
- Verify site ownership before public listing
- Allow withdrawal of consent
- Moderate submissions before publication
- Never expose private analytics or settings
- External catalogue requires a separate reviewed backend

## M13.4 — Quality

- Follow existing SEO-TidY code structure
- Maintain PHP 8.1+ and WordPress 6.8+ compatibility
- Keep existing SEO functionality unchanged
- Test permissions, privacy and data accuracy
- No production deployment without explicit approval

## Implementation sequence

1. Analytics storage and measurement contract
2. Analytics collection and REST summaries
3. Dashboard settings and metrics
4. Public badge and configurable themes
5. Wall of Fame opt-in and local UI
6. Integration checks, documentation and release preparation

## Scope boundary

Do not automatically publish a website to a central catalogue.
Do not add backlinks without the site owner's explicit choice.
Do not claim bot or unique visitor counts are exact.