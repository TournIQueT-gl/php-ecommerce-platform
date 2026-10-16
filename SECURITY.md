# Security policy

This v5 alpha repository is **not security-certified** and comes with no guarantee of suitability for real-money checkout. Security reports are welcome through **GitHub's private vulnerability reporting** for this repository, if enabled, or by contacting the repository owner privately; **do not publish exploitable secrets or customer information in a public issue**.

## Deployment security essentials

- Protect HTTP access to `app/`, `bin/`, `config/`, `database/`, `includes/`, `storage/`, `views/` and all backups. Apache `.htaccess` is supplied but requires correct server configuration.
- Never commit `config/database.local.php`, `PERFUME_SECRET_DIR` contents, SMTP/DB passwords, runtime caches, logs, support attachments, backups or customer uploads.
- Use HTTPS, production-only database credentials with minimal privileges, secure session settings, password management and 2FA for administrators.
- Configure the canonical domain and reverse proxies correctly. Test admin authorization, CSRF protection, SQL injection defenses, file uploads, guest-order access, refunds and concurrency on staging.
- Never restore a production database into a publicly accessible test system. Preserve encryption keys when updating existing deployments.
- Run `php bin/release-gate.php --json --diagnose` and a valid staging `http-security-smoke.php` check. Their passing results alone do **not** replace an independent security review.

## Known release limitations

Only static source scanning and syntax checks were performed for the preparation of this open-source archive. There was no live MySQL integration, automated browser QA, penetration test, server configuration audit or real-payment checkout verification.
