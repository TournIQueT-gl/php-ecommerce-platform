# Universal Commerce — deployment checklist (v5 alpha)

**Not production-certified.** This is a source release for developers; the following work is required for a public installation.

1. **Back up first.** Make isolated database, uploads, configuration and persistent encryption-key backups. Never copy live PII into public test data.
2. **Separate secrets.** Use restricted database credentials and a private `PERFUME_SECRET_DIR` outside the web root. Never ship real `config/database.local.php` or SMTP secrets.
3. **Check web server protections.** Apache 2.4 `mod_rewrite`, correct `.htaccess` handling, HTTPS and denied HTTP access to private directories, archives and SQL files. Nginx requires equivalent rules.
4. **Configure domain.** Set `PERFUME_CANONICAL_SITE_URL` to the real HTTPS origin. Correct subdirectory detection and trusted proxy configuration if relevant.
5. **Migrate safely.** Create a clean database for first install and run `php bin/migrate.php`. For upgrades, use staging and maintain checksum integrity through migration 051.
6. **Bootstrap owner locally.** Register your account, grant owner role via local DB access (README instructions), sign out/in, enable 2FA and protect recovery codes.
7. **Configure real commerce data.** Store name, currency, tax, delivery fees, shipping zones, approved images, prices, refunds, inventory and region-appropriate policies. Remove or unpublish QA/demo imports before going live.
8. **Validate workflows.** Live customer registration/password reset, emails, catalog search/filter, size/color variants, stock reservations, guest checkout, refunds, order history, returns, mobile navigation, and Arabic RTL.
9. **Operate correctly.** Configure SMTP, CLI scheduler (if available), disk retention, monitoring, alerting, encrypted off-server backups and restore drills.
10. **Release gates.** Run `php bin/v5-preflight.php`, `php bin/release-gate.php --json --diagnose` and `php bin/http-security-smoke.php https://your-staging-domain --json`. Perform load and security tests against authorized staging.

**Do not publish this repository as a working commercial storefront without validating its database, server security and real transaction flows.**
