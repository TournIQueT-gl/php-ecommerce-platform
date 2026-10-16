# Open-source preparation — 2026-10-16

Source: user-supplied `saas.zip` (current v5 alpha code, through migration `051`). Previous `perfumestore(1).zip` release has **not** been used.

## Changes made for safe publication

- Removed `config/database.local.php` (developer machine-specific settings).
- Removed saved storefront JSON caches and `.web_scheduler_tick` runtime state.
- Kept private runtime folders as empty scaffolding with their `.htaccess` protections.
- Added `.gitignore`, `.gitattributes`, MIT `LICENSE`, README, contribution/security guidance and an Apache-focused install checklist.
- Adjusted several **fallback, non-database** strings in email templates, 2FA issuer and admin store settings to avoid advertising every installation as a perfume shop.
- Preserved the full application, published CSS/JS assets, route files, sample placeholders, historical SQL migrations and clean schema baseline. No migrations, table names or existing database data were changed.
- Kept `PERFUME_*` configuration variable names for backward compatibility; do not rename them unless updating all code paths.

## Checks and caveats

- PHP 8.4 syntax-check covers all included `.php` files.
- Pattern-based source scan found no private-key PEM blocks or obvious GitHub API token literals in the ZIP. It is **not** a comprehensive security audit.
- Included baseline schema contains no customer records; no live DB export was provided.
- No MySQL server and live installation/checkout browser were available for functional verification.
- Legacy perfume defaults still exist in schema history and may appear in a newly migrated empty database. Configure store branding and departments in Admin before launch.
- Brand-labeled placeholder SVG files are shipped only for template compatibility; review `THIRD-PARTY-NOTICE.md` before public/commercial use.
