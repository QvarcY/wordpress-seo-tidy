# SEO-TidY Wall of Fame — AREA hosting

An independent bilingual public directory using PHP 8.2+, MariaDB and Cloudflare Turnstile. Target URL: https://kas.id.lv/SEO-TidY/Wall-Of-Fame/

## Features

Opt-in application with explicit consent; DNS TXT verification; manual approval; applicant status and permanent withdrawal; private moderator interface; LV/EN responsive directory; only approved sites visible. No visitor analytics or WordPress passwords are copied to the service.

## Deployment layout

Copy ONLY files inside wall-of-fame-area/public/ into the web directory corresponding to kas.id.lv/SEO-TidY/Wall-Of-Fame/.

Copy wall-of-fame-area/private/config.example.php to /home/kasidlv/seo-tidy-wall-of-fame-private/config.php, then fill in database credentials, Turnstile site and secret keys, public origin, and a securely generated random admin token of at least 32 characters.

Import wall-of-fame-area/schema.sql into a new MariaDB database using cPanel/phpMyAdmin. NEVER place config.php, schema.sql or database secrets into the public website directory.

By default, api.php searches for the private configuration three directory levels above its public directory, inside seo-tidy-wall-of-fame-private/config.php. The public path must be /home/kasidlv/<site-docroot>/SEO-TidY/Wall-Of-Fame. If the layout differs, configure SEO_TIDY_WOF_CONFIG in the hosting environment with an absolute path to the private config file. Do not move secrets inside webroot.

The public .htaccess rewrites api/ requests to api.php. The API uses PDO MySQL with utf8mb4. Configure Turnstile for the kas.id.lv hostname, restrict moderator access, and set endpoint rate limits through the host or WAF before accepting registrations.

## Registration

An applicant supplies website name, HTTPS domain and short description and consents to public display after verification and approval. Registration requires Turnstile. The service returns a one-time private 64-character management key, an application ID and a DNS TXT challenge.

Add the DNS TXT record on _seo-tidy.example.com with value seo-tidy-verification=<challenge>. After DNS propagation use Verify DNS. The administrator must independently approve the verified application, at which point the entry appears in the public directory. DNS proof is checked again at approval.

The applicant can check their status and permanently delete the application using their ID and private key. The database stores only the SHA-256 hash of this key. No email recovery is implemented.

## Administration

The page admin.html offers a moderation UI. The administrator token is provided per session, held in browser memory only; it is not persisted to localStorage or cookies. Protect admin.html using additional hosting access controls before launch. All moderator API endpoints require the private server-side bearer token.

## API

GET api/config — Turnstile public site key
GET api/sites?page=1 — approved sites only, paginated
POST api/apply — submit application
POST api/verify — verify DNS challenge
POST api/status — private status
POST api/remove — permanent applicant withdrawal
GET api/admin/submissions — privileged review list
POST api/admin/moderate — approve, reject or delete

## Scope and privacy

The original WordPress plugin's Wall of Fame tab remains local-only. Profiles are NOT automatically sent anywhere. This new PHP site has not been installed or configured on AREA hosting. Final configuration requires explicit approval and a real deployment smoke check. Hosting request logs and Turnstile processing are external to application database storage.

This edition replaces the Worker/D1 infrastructure for the kas.id.lv deployment but does not delete the original standalone prototype.
