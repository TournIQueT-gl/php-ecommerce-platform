# Troubleshooting

## Database connection failed

Confirm MySQL/MariaDB is running, database exists and `config/database.local.php` (or `PERFUME_DB_*`) points to the right host, database, username and password. Run `php -m` and confirm `pdo_mysql` is enabled. **Do not paste database passwords into public GitHub issues.**

## Migration fails / duplicate table or column

Start with a genuinely empty test database. The migration command auto-loads a historical baseline. Don't manually import the baseline then replay it. Don't rename migration files or edit checksums after they have been applied. For existing deployments, make a backup, review the database's applied migrations, and test upgrades on staging.

## Apache 404 on pretty URLs

Enable `mod_rewrite`; ensure `AllowOverride` permits `.htaccess` in the project directory. Confirm your Apache document root and subdirectory match the URL. On non-Apache servers, write equivalent routing and security-deny rules before going live.

## Blank page / PHP 500

Check PHP's **private** error log, `php -v`, enabled extensions and database connectivity. Don't enable verbose debug output publicly. Do not attach unredacted logs or credentials to an issue.

## Admin menu not visible

A normal registered account isn't automatically an owner. Follow the one-time local owner bootstrap in [GETTING-STARTED.md](GETTING-STARTED.md), confirm only one row was updated, sign out/in and verify 2FA.

## Images, media or uploads don't work

Check writable runtime directories and file permissions. Apache must be able to write designated folders without allowing server-side script execution from uploaded content. This repository excludes customer uploads intentionally.

## Emails or background tasks aren't working

Configure email through the application's admin settings and verify delivery in staging. Depending on hosting, configure the CLI scheduler or the bounded web fallback. Check canonical HTTPS URL and private key persistence.

## Can I use an online payment gateway?

Not as a built-in, verified integration. Current code supports COD and manual transfer/InstaPay request flows only. A signed webhook, idempotency handling, refunds and gateway tests are future contribution work.

## I found a security vulnerability

Follow [../SECURITY.md](../SECURITY.md); **do not** post an exploit, valid account credentials, customer data or a real attack log in a public issue.
