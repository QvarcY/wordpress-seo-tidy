# Wall of Fame profiles — rollout

This feature stays off production until the SQL migration and WordPress plugin update are installed. Existing approved listings and SEO-TidY beta.13 continue working until rollout.

## Order of operations
1. Back up the live MariaDB database and current `public/` files outside public web root. Verify the backups exist.
2. Apply `migrations/002-profiles.sql` **once** to the existing database using the same credentials as `private/config.php`. Verify `SHOW TABLES LIKE 'profile_revisions'`.
3. Deploy `api.php`, `index.php`, `sitemap.php`, `app.js`, `style.css`, `index.html`, `admin.js`, `admin.html`, and `.htaccess` together (atomic rename of staged files where practical).
4. Smoke-test directory `/`, `/api/sites`, `/sitemap.xml`, and moderator login. Existing entries must remain visible.
5. Install an independently tested plugin ZIP containing the updated `includes/WallOfFame.php`; current beta.13 does **not** include the profile editor.
6. Use an approved test website to submit a profile. Assert that `pending` edits do **not** appear publicly; approve through `admin.html`, then verify the generated profile URL, canonical, JSON-LD, sitemap, LV/EN and owner resubmission.

## Privacy and access
- Profile edits require the private owner credentials saved in the site's WordPress options.
- WordPress administrator access and a nonce are required for the editor.
- Editors cannot directly publish revisions; a moderator approves each one.
- Existing published profile remains until a replacement is approved.
- Do not log owner secrets or copy the private config into the web directory.
- Never enable user-authored raw HTML. Render user text only through HTML escaping.

## SEO caveats
A directory listing is not a promise of indexing, ranking improvements, search traffic, or AI citation. Avoid mass creation of thin category or tag landing pages; the sitemap includes only published individual profiles and paginated directory pages.
