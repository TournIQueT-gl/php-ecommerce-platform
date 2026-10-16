# Configuration reference

This is a compatibility-oriented guide. Check the actual PHP config for all supported values before making production changes.

## Database

Either copy `config/database.local.php.example` to `config/database.local.php` (never commit), or set the legacy environment variables:

| Variable | Use |
| --- | --- |
| `PERFUME_DB_HOST` | MySQL hostname |
| `PERFUME_DB_NAME` | Database name |
| `PERFUME_DB_USER` | Database username |
| `PERFUME_DB_PASS` | Database password |
| `PERFUME_DB_CONFIG` | Path to a separate PHP database config file |

Configuration priority is: `PERFUME_DB_CONFIG` → local PHP config → environment variables. All `PERFUME_*` names are **legacy compatibility identifiers** that support any store category.

## Application and operational configuration

| Variable | Use |
| --- | --- |
| `PERFUME_BASE_PATH` | Override base URL path (such as `/shop`) when reverse-proxy/path discovery needs it |
| `PERFUME_CANONICAL_SITE_URL` | HTTPS website origin for canonical links and email URLs |
| `PERFUME_SECRET_DIR` | Private persistent secret directory **outside** the public web root |
| `PERFUME_SCHEDULER_MODE` | `auto`, `web`, or `cli` background-work strategy |

Additional controls live in `config/app.php` and Admin store settings. Do not invent env names based on this shortened list; search configuration use in source for the exact option.

## Security and runtime files

- Keep secrets, backups, logs, analytics runtime files and customer uploads out of Git; check `.gitignore`.
- Use a dedicated restricted DB account and HTTPS in production.
- Back up DB, media and secret-store keys together, then test restoration.
- On Nginx or any stack that ignores `.htaccess`, **deny private directories explicitly** before deployment.
- Admin mail/SMTP values must be configured for real sending; test from staging without exposing recipient addresses.

For deployment validation, see [../DEPLOYMENT-CHECKLIST.md](../DEPLOYMENT-CHECKLIST.md) and [../SECURITY.md](../SECURITY.md).
