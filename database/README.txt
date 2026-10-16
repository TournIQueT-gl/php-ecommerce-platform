UNIVERSAL COMMERCE DATABASE (v5 alpha)
======================================

Fresh installations:
1. Create a new, empty MySQL or MariaDB database.
2. Configure config/database.local.php from its .example or use PERFUME_DB_* env values.
3. Run php bin/migrate.php. The migrator imports the v2.3.0 baseline automatically for an empty database and runs forward to migration 051.
4. The baseline provides only schema and feature flags. It does NOT create an owner or any retail products. Register a normal account and promote it to owner locally as described in README.md.

Upgrades:
Back up your database and private encryption keys, deploy code and run migrations on staging first. Do not edit historical SQL migration content: migration checksums are verified.

The historical baseline filename and PERFUME_* environment variable identifiers remain for upgrade compatibility; the product is multi-category and not limited to fragrance.

Database dumps and production backups can contain customer and order information. Never store them in this public repository.
