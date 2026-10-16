# Universal Commerce — Open-Source PHP E-Commerce Platform

**Free, open-source PHP + MySQL e-commerce platform for clothing, electronics, cosmetics, perfumes and general retail.**

Universal Commerce is a customizable online-store foundation for **fashion, footwear, beauty, perfumes, electronics, accessories and general retail**. An individual deployment has its own database and configuration; this repository is **not** a hosted, multi-tenant SaaS service.

> **Release status — v5 alpha snapshot.** Source code is provided for testing, learning, modification and community development. This release has **not** passed a complete live database, payment, security, performance, or browser acceptance audit. Do not deploy as a public production shop without those checks.


## Documentation

**New to the project?** Start with [installation and first-store setup](docs/GETTING-STARTED.md), then follow [store customization](docs/STORE-CUSTOMIZATION.md) to choose your retail industry.

| Guide | Who it helps |
| --- | --- |
| [Getting started](docs/GETTING-STARTED.md) | Developers installing locally on XAMPP/Apache and MySQL |
| [Store customization](docs/STORE-CUSTOMIZATION.md) | Merchants configuring clothing, beauty, electronics and other categories |
| [Configuration reference](docs/CONFIGURATION.md) | Developers working with DB settings, environment variables and URLs |
| [Architecture](docs/ARCHITECTURE.md) | Contributors exploring PHP controllers, services, models and migrations |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | New users diagnosing setup, login, email, images or URL issues |
| [Roadmap / feature status](ROADMAP.md) | Anyone checking what is implemented, untested or planned |
| [How to contribute](CONTRIBUTING.md) | People opening bugs and pull requests |
| [Security reporting](SECURITY.md) | Users and researchers reporting issues responsibly |

**Repository for sharing:** `php-ecommerce-platform` · **License:** MIT · **Stage:** community alpha (not production-certified).

## Features in this source

- Departments, categories, brands, products, inventory and configurable product attributes
- Variant combinations and SKU-specific image fields (e.g. clothing size/color or electronic storage)
- Admin universal product editor, catalog, inventory, orders and store settings
- Navigation and homepage configuration, responsive storefront, search and filters
- Customer accounts, guest orders, cart, wishlist, order tracking and returns
- Admin roles, 2FA support, audit logs, customer support and notifications
- English/Arabic localization support, content-translation table and SEO settings
- Visitor analytics, scheduler fallback and command-line migration/release checks

Some features are **partial or industry-specific**. Perfume-related models and routes remain for backward compatibility. New integrations, payment methods and many storefront experiences require further engineering and live verification.

## Stack and requirements

- **PHP 8.1+** recommended; bundled source syntax-checked with PHP 8.4 (older versions not run-tested)
- **MySQL/MariaDB** with InnoDB and utf8mb4
- **Apache 2.4** with `mod_rewrite` / `.htaccess` rules enabled (and `mod_headers` recommended)
- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `json`
- HTTPS and a mail service for a real deployment
- No Composer or Node dependency installation is required by the provided runtime sources

**Important:** Apache access rules protect internal directories and configuration. Do not publish the repository as-is behind a server that ignores `.htaccess` (including some Nginx/shared-host setups) without separately denying HTTP access to `app/`, `bin/`, `config/`, `database/`, `includes/`, `storage/`, and `views/`.

## Quick start (local XAMPP / Apache)

1. Put the project in `C:\xampp\htdocs\universal-commerce-php` or your Apache web root.
2. Start Apache and MySQL. Create a **new, empty** MySQL database, e.g. `universal_commerce`, using phpMyAdmin or MySQL CLI.
3. Copy `config/database.local.php.example` to `config/database.local.php`. Set the database host, name, username and password. **Never commit the local file.** Or set `PERFUME_DB_*` environment variables instead.
4. In a terminal with PHP on `PATH`, run from the project directory:

   ```bash
   php bin/migrate.php
   ```

   The migrator **automatically installs** the included legacy schema baseline on an empty database, then runs all later migrations through **051**. Do **not** manually import the baseline and then expect it to seed products or an administrator account.
5. Open `http://localhost/universal-commerce-php/`. Register an account at `/register`.
6. Bootstrap **your own first owner account** using phpMyAdmin / the MySQL CLI **on your local installation only**, replacing the username with the account you just registered:

   ```sql
   UPDATE users SET is_admin = 1, admin_role = 'owner'
   WHERE username = 'YOUR_REGISTERED_USERNAME' AND is_active = 1;
   ```

   Confirm one row was updated. Sign out/in and visit `/admin`. Enable and test 2FA. **No preset owner password or default admin user is shipped.**
7. In Admin, change the store name, currency, navigation, categories, shipping, site content, email settings, and homepage for your chosen industry. Fresh installs have no product catalog; populate your own authorized data.

### Linux / hosted environments

Configure your own HTTPS document root, writable upload/runtime directories, PHP extensions, database permissions and cron/mail. Prefer environment variables or a database config **outside** the web root; `PERFUME_DB_CONFIG` can point to such a file. Set `PERFUME_CANONICAL_SITE_URL=https://your-real-domain` before sending mail or generating canonical URLs. Set `PERFUME_SECRET_DIR` to persistent, access-restricted storage outside the public document root. Back up its contents with your database. These `PERFUME_*` names are **legacy compatibility environment names**; they do not mean this product is perfume-only.

Read [DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md) and [SECURITY.md](SECURITY.md) before exposing any installation to real customers.

## Catalog configuration

The `046`–`051` migrations add reusable attributes, variant definitions, multi-department mappings, SKU images and localized content. They include sample *attribute definitions* such as size, color, storage, fit and fragrance, and department definitions for fashion, perfumes, beauty, electronics and general retail; they **do not** create a live product catalog. The admin can use these definitions to build different shops.

## Repository map

| Path | Description |
|---|---|
| `app/` | Application services, repositories, domain/catalog logic and controllers |
| `admin/` | Administrative route entry points |
| `assets/` | CSS, JavaScript and generic static assets |
| `config/` | Application and sample database configuration |
| `database/baseline/` | Historic schema baseline for fresh installations |
| `database/migrations/` | Append-only SQL migration history |
| `includes/` | Web bootstrap, authentication, layouts and security helpers |
| `views/` | Storefront, account and admin templates |
| `bin/` | Migration, scheduling, diagnostic and release tools |
| `storage/` | Private runtime state (empty directory scaffolding only) |
| `uploads/` | Generated assets and merchant uploads (only mockup SVG placeholders tracked) |

## Verification

```bash
# PHP source syntax
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l

# Database and environment dependent; use a disposable test database first
php bin/v5-preflight.php
php bin/release-gate.php --json --diagnose

# Against your authorized staging URL, after deployment
php bin/http-security-smoke.php https://your-staging-domain --json
```

Successful syntax checks are **not** proof of secure checkout or release readiness. See [OPEN-SOURCE-RELEASE-NOTES.md](OPEN-SOURCE-RELEASE-NOTES.md) for the audit scope and remaining work.

## Is this finished?

**No.** See [ROADMAP.md](ROADMAP.md) for implemented-but-unverified functionality, confirmed limitations, release blockers, priorities and volunteer-friendly contributions. This is a community alpha, not a production-ready replacement for hosted e-commerce services.

## Contributing and license

Pull requests and issues welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) and [THIRD-PARTY-NOTICE.md](THIRD-PARTY-NOTICE.md).

Original project source is released under the **[MIT License](LICENSE)** (subject to applicable third-party rights). **No trademark ownership, commercial image license, or endorsement by any featured brand is implied.**
