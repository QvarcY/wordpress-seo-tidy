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

## WordPress one-click enrollment

The WordPress plugin's Wall of Fame tab lists approved community sites using its authenticated server-side directory proxy. Site administrators can fill a website name/description and select **Join Wall of Fame** (explicit consent). WordPress generates a random, temporary proof valid for five minutes; the Wall of Fame server verifies that proof against the submitting site's public WordPress REST endpoint via HTTPS (DNS/IP restrictions and no redirects), then creates a **verified, non-public** application. A moderator must still approve publication.

No DNS edits, Turnstile form or manually copied management tokens are required for WordPress administrators. Private ownership credentials are saved within the WordPress installation and never exposed to the browser. The administrator can use **Leave Wall of Fame** to delete the application and its public listing.

Both sides must be updated together: `wall-of-fame-area/public/api.php` on AREA hosting **and** a newly released version of the WordPress plugin. The original website registration form remains available as a manual fallback; it still requires DNS ownership proof.

The hosting operator must apply WAF/rate limits to `api/wp-apply` before production use. The server validates that the proof URL belongs to the declared HTTPS domain and connects to a public, pinned IPv4 address without HTTP redirects to reduce SSRF risk.

## Moderation notifications and remembered login

Add `'admin_email' => 'your-real-email@example.com',` to the private `config.php` on the AREA host. The PHP API uses the hosting provider's configured `mail()` transport to send one message when a website first becomes verified and awaits moderation. Each email includes the website hostname and a link to `admin.html`, never the admin secret or a public approval token. Sending may require correctly configured outbound mail/SPF/DKIM at the host; test it with a real verified application. Failed delivery is recorded in the PHP error log, without blocking registration. Do not claim delivery based on a successful submission alone.

The moderation page accepts the existing `admin_token` once and remembers the login for up to seven days using an HTTPS-only, HttpOnly, SameSite=Strict cookie. The cookie is HMAC-signed using the server-side admin token and scoped to the Wall of Fame API. The raw admin token is not saved in localStorage or a JavaScript-accessible cookie. Changing the private admin token invalidates existing signed sessions. Use the **Clear key** button to sign out. Explicitly restrict moderator page access through hosting access controls where possible. Login endpoints should have IP-based rate limiting and use HTTPS only.

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
