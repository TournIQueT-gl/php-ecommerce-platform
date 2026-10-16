# Architecture and developer orientation

## Project overview

Universal Commerce is a **PHP 8.x + MySQL/MariaDB + Apache** application. Public routes are lightweight `.php` entry points; logic is split among application services, repositories, controllers and templates. The original code evolved from a perfume storefront into a broader retail catalog, so some compatibility names still use `perfume` / `PERFUME_*`.

## Directories

| Location | Purpose |
| --- | --- |
| `index.php`, `product.php`, `cart.php`, `checkout.php` | Storefront entry routes |
| `admin/*.php` | Administrative routes and capability checks |
| `includes/bootstrap.php` | Shared request bootstrap, session/CSRF/security utilities |
| `includes/autoload.php` | Application-class loading |
| `app/Http/Controllers/` | Request-oriented logic |
| `app/Services/` | Services, migrations, checkout and other orchestration |
| `app/Repositories/` | Persistence and data-access classes |
| `app/Catalog/` | Attributes, product variants and legacy catalog mapping |
| `app/Storefront/` | Storefront navigation, themes and modules |
| `views/` | Server-rendered UI templates |
| `assets/` | CSS/JavaScript and public static resources |
| `database/baseline/` | Schema snapshot used for new installs |
| `database/migrations/` | Append-only SQL schema evolution through migration 051 |
| `bin/` | CLI migration, scheduler and diagnostic commands |
| `storage/`, `uploads/` | Runtime-only data and merchant-provided content; not part of the distributable source |

## Request lifecycle (high level)

1. Apache routing resolves a public `.php` route (extensionless routes depend on `.htaccess`).
2. The PHP entry point loads shared bootstrap and applicable authentication/authorization helpers.
3. Controllers/services communicate with repositories and DB; views render HTML using shared layouts and assets.
4. State-changing forms must validate CSRF tokens and admin routes must enforce explicit capabilities.

This is an overview for contributors, **not** a claim that every request currently meets these security invariants; that is part of the security audit in the roadmap.

## Working on the database

- `bin/migrate.php` uses `App\Services\MigrationRunner`.
- An empty DB receives the historical baseline, then newer migrations.
- Migration files are tracked by checksum. **Do not edit applied migrations** or rename legacy schema objects for cosmetic reasons.
- Introduce new schema changes with a new numbered migration and test both clean-install and upgrade paths.

## Local development workflow

1. Create an isolated MySQL database, install via [GETTING-STARTED.md](GETTING-STARTED.md).
2. Reproduce the issue and capture non-sensitive, minimal inputs.
3. Make a focused change; include test coverage where practical.
4. Run `php -l` on modified PHP files, syntax-check modified JS, and exercise the affected browser flow.
5. If checkout/security/stock is affected, add integration tests and report observed behavior. **Syntax validation alone is insufficient**.

## What's still missing

There is no comprehensive automated unit/integration/browser suite. Hosted multi-tenancy, automatic online payment gateways, a guided installer and general-purpose extension API are not implemented or fully verified in this alpha. See [../ROADMAP.md](../ROADMAP.md) and [../CONTRIBUTING.md](../CONTRIBUTING.md).
