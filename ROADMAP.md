# Roadmap and project status

**Status: v5 alpha / community development release (16 October 2026).** This repository contains a substantial multi-category commerce implementation, but **is not production-certified**. Features listed below are present in source code, **not a claim that they work end-to-end on a fresh deployment**.

## Current functionality visible in source

- Database migrations through 051, including universal attributes, departments, variant media and localization.
- Public storefront, product browsing, cart, wishlist, customer accounts, order tracking and checkout.
- Admin catalog/editor, inventory, orders, shipping, content, support and store settings.
- Localized content and Arabic/English support paths.
- Checkout payment choices: **cash on delivery** and **manual bank transfer / InstaPay request**. **No automatic card/gateway charge** is included.

## Verified vs. unverified

| Area | Evidence / status | Next acceptance gate |
|---|---|---|
| PHP syntax | Pass on included source using PHP 8.4 | Keep CI lint; test supported PHP versions |
| JavaScript syntax | Pass on the packaged scripts | Add browser regression tests |
| Fresh MySQL install and upgrades | **Unverified**; no configured MySQL in packaging environment | Run baseline + migrations 001–051 on clean DB and upgrade fixture |
| Storefront, accounts, admin UI | Source exists; **end-to-end behavior unverified** | Cross-browser and mobile acceptance tests |
| Checkout, payments and stock | Code exists; **real transaction/concurrency test pending** | COD/manual transfer checkout, duplicate submits, concurrent stock protection, returns |
| Security and server protection | Limited static/source checks only | Authorized dynamic audit (CSRF, auth, SQLi, uploads, internal files) |
| RTL and translations | Implementation paths exist, **completeness unverified** | English/Arabic pages on desktop and mobile |
| Online payment gateway | **Not implemented** | Optional gateway adapter with signed webhook/idempotency tests |
| Automated behavioral tests | **Not included**; CI currently runs lint only | Unit, integration and browser test jobs |
| New merchant onboarding | **Manual** DB config, CLI migration, SQL owner elevation | Guided secure setup, no default passwords, failure recovery |
| Vendor/branding neutrality | Legacy `PERFUME_*` config keys and perfume-specific assets remain | Backward-compatible generic defaults and docs |

## Work plan

### P0 — reliable public alpha (before encouraging real merchants to deploy)

1. **Fresh-install acceptance:** disposable MySQL/MariaDB, baseline + all migrations, admin creation, sample product with inventory, shipping, tax and currency. Document exact requirements and fixes.
2. **Checkout correctness:** verify COD/manual transfer totals, discounts, rounding, retries/idempotency, stock atomicity, out-of-stock variants, guest checkout, order emails, cancellation and returns.
3. **Security verification:** authenticated/guest authorization, role boundaries, CSRF, SQLi, file uploads, rate limits, session settings, backup and internal-directory access under Apache and any supported alternatives. Coordinate disclosure privately.
4. **Automated tests:** create isolated unit + DB integration + browser smoke suites; update CI to run them with MySQL/MariaDB services (not just lint).
5. **Public data and assets:** audit licensing/attribution and replace any questionable third-party brand references/media with generic legally reusable placeholders. Keep real customer data out of repositories.

### P1 — easier for community developers

6. **Setup wizard:** safe owner bootstrap, clear env configuration, database readiness checks, guided store type/category creation and failure recovery.
7. **Generic multi-industry starter experiences:** fashion size/color, electronics storage, cosmetics shade, general goods; seed only synthetic example data with clear opt-in.
8. **RTL/mobile/accessibility:** full checkout/admin/storefront QA; keyboard navigation, form messaging, responsive layouts, meaningful translations.
9. **Deployment recipes:** Apache/XAMPP verified instructions, Nginx equivalents, cron/email/backup guidance, and platform matrix.
10. **Contributor experience:** architecture overview, stable test fixtures, coding conventions, migration guide, screenshot demo and release changelog.

### P2 — extensibility / future roadmap

11. Payment adapter system for **optional** third-party gateways, with safe webhooks, reconciliation and tests.
12. Shipping/tax provider interfaces and optional marketplace integrations.
13. Better theming, category presets and plug-in extension points without per-client patches.
14. Performance profiling and realistic concurrency benchmarks against staged infrastructure.

## Definition of a stable v1.0

A release can be called stable only after: a clean reproducible install and upgrade; green automated integration/browser suites; completed P0 security review and resolved critical findings; reproducible payment/order/stock tests; English and Arabic/mobile acceptance; documented production deployment and backup restore; and third-party asset licensing review.

## How to contribute

Choose a `good first issue` or `help wanted` task, reproduce the behavior in a disposable local installation, and propose a focused pull request with tests or explicit verification instructions. For vulnerabilities, follow [SECURITY.md](SECURITY.md) rather than opening a public exploit report. Keep historical SQL migrations append-only. The maintainers should track issue status and release gates publicly instead of claiming features are complete based on source presence.
