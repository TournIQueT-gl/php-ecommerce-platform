# Installation and first store setup

> **Community alpha:** These instructions reflect the included code. A complete clean-install acceptance test with a live MySQL database has not yet been performed. Use a disposable database first; do not start with real customer data.

## 1. Requirements

- Apache 2.4 with `mod_rewrite` enabled, `.htaccess` honored, and HTTPS outside localhost.
- PHP 8.4 was used for the source syntax check. PHP 8.1+ is the intended minimum but has **not** been verified on every supported version.
- PHP extensions: `pdo_mysql`, `openssl`, `fileinfo`, `gd`, `json` (`mbstring` recommended).
- MySQL or MariaDB with InnoDB and `utf8mb4`.
- A local environment such as XAMPP (Windows) or an equivalent Apache/PHP/MySQL stack.
- PHP available on the command line; no Node or Composer install is needed for this source snapshot.

## 2. Prepare a local copy

**Windows / XAMPP:** extract the repository to `C:\xampp\htdocs\php-ecommerce-platform`; start Apache and MySQL in the XAMPP control panel. Open a terminal in that directory. For standalone PHP installations, add the PHP executable directory to `PATH`, or invoke `C:\xampp\php\php.exe` instead of `php` below.

**Linux / Apache:** place the source in your chosen Apache directory and configure your site's document root and overrides. **Do not expose internal project folders through a web server that ignores `.htaccess`**. Check the protection rules in [../SECURITY.md](../SECURITY.md). The supplied rules are tailored to Apache, not Nginx.

## 3. Create the database and local configuration

Create an **empty** database named `universal_commerce`, with a dedicated local database user where practical:

```sql
CREATE DATABASE universal_commerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Copy `config/database.local.php.example` to `config/database.local.php` and update its host/name/user/password fields. This local file is excluded by `.gitignore` and **must never be committed**.

Alternatively use `PERFUME_DB_HOST`, `PERFUME_DB_NAME`, `PERFUME_DB_USER`, `PERFUME_DB_PASS`, or `PERFUME_DB_CONFIG` for an external config file. Those names are legacy configuration interfaces; they work for **all** store types.

## 4. Install the schema

From the project directory:

```bash
php bin/migrate.php
```

The migration runner installs `database/baseline/perfume_store_v2_3_0.sql` on an empty database, marks historical migrations 025–037 as satisfied, and applies migrations up to 051. **Do not import that baseline yourself** before following these instructions. Historic SQL filenames should not be renamed: migrations use version/checksum tracking.

If migration fails, retain the full error and stop. See [TROUBLESHOOTING.md](TROUBLESHOOTING.md). Never attempt repairs against live customer data without a restorable backup.

## 5. Create the first administrator (local only)

Visit `http://localhost/php-ecommerce-platform/`, register your own normal account through `/register`, then run the following SQL **locally** through phpMyAdmin or MySQL CLI, replacing the placeholder:

```sql
UPDATE users SET is_admin = 1, admin_role = 'owner'
WHERE username = 'YOUR_REGISTERED_USERNAME' AND is_active = 1;
```

Confirm **exactly one row** was updated. Log out and in, then open `/admin`. Configure and verify administrator two-factor authentication. This manual owner bootstrap is temporary; a secure first-run wizard is tracked in [../ROADMAP.md](../ROADMAP.md). There is **no built-in default admin account/password**.

## 6. Configure a store

In Admin, set the store display name, branding, language and currency, categories and departments, product attributes and variants, navigation, shipping and order policies. Start with a test product using synthetic data. Follow [STORE-CUSTOMIZATION.md](STORE-CUSTOMIZATION.md) for industry examples.

Fresh installs do **not** come with a working merchant catalog. Never use production credentials, customer details or licensed product photography in sample data.

## 7. Local verification

- Open the homepage, log in/out and view the admin dashboard.
- Create a department/category/product with variant stock; check browsing and search.
- Test cart, totals and **COD / manual bank transfer** checkout with a clearly fake order.
- Test inventory updates and order access with both guest and logged-in sessions.
- Review desktop/mobile, English/Arabic, accessibility, and error handling.
- Run `php bin/v5-preflight.php` and `php bin/release-gate.php --json --diagnose` and review the reported blockers.

**Not included:** automated card charging or verified gateway webhooks. This is **not** a multi-tenant hosted SaaS application; every merchant installs a separate instance and database.
