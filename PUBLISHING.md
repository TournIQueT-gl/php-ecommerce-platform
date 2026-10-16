# Publish this PHP e-commerce platform to GitHub

**Suggested repo name:** `php-ecommerce-platform`

**Project/product name:** Universal Commerce — Free Open-Source PHP E-Commerce Platform

**Repository description:** `Free open-source PHP/MySQL e-commerce platform for fashion, electronics, cosmetics, perfumes and general retail. Community alpha.`

The project code is a **community alpha**. It is a single-store deployment per installation/database and **not** a finished payment-gateway system or hosted SaaS.

## Step 1 — Create an empty GitHub repository

Log into the `TournIQueT-gl` GitHub account and open:

https://github.com/new?name=php-ecommerce-platform&description=Free%20open-source%20PHP%2FMySQL%20e-commerce%20platform%20for%20fashion%2C%20electronics%2C%20cosmetics%20and%20general%20retail.%20Community%20alpha.

Choose **Public**, repository name `php-ecommerce-platform`, and **do not** auto-add a README, .gitignore, or license (they already exist in this archive). Click **Create repository**. GitHub account creation is a separate user action; the connected GitHub integration currently cannot create a new repository.

## Step 2 — Push the extracted source

Unzip this file. Open **PowerShell in the extracted root directory**, the one containing `README.md`, `index.php`, `LICENSE` and `.gitignore`.

Easiest way on Windows:

```powershell
.\publish-github.ps1
```

If PowerShell blocks local scripts, run the commands below manually instead of changing system execution policy:

```powershell
git init -b main
git add .
git diff --cached --check
git status --short
git commit -m "Initial Universal Commerce community alpha release"
git remote add origin https://github.com/TournIQueT-gl/php-ecommerce-platform.git
git push -u origin main
```

If Git says your identity is not configured, set `git config user.name` and `git config user.email` with values you choose. GitHub may prompt you to authenticate using your browser.

**Do not run these instructions inside a different Git repository.** Do not force-push an existing repository. If the remote already exists, confirm it is the intended empty repository first.

## Step 3 — Make it discoverable

- Check that the URL `https://github.com/TournIQueT-gl/php-ecommerce-platform` opens without logging in.
- In **About**, add a plain-language description and topics: `php`, `mysql`, `ecommerce`, `shopping-cart`, `online-store`, `open-source`, `retail`, `arabic`, `inventory-management`.
- Enable **Issues** and consider **Discussions** and **Private vulnerability reporting** in repository Settings.
- Create a pre-release tag `v0.5.0-alpha` **only after** verifying the content and disclosure rules. Do not call it `v1.0 stable` yet.
- Verify no local DB credentials, runtime caches, uploads or user data are tracked.
- Link the README, [docs/GETTING-STARTED.md](docs/GETTING-STARTED.md), [ROADMAP.md](ROADMAP.md), and [CONTRIBUTING.md](CONTRIBUTING.md) to contributors.

## How to update later

For subsequent commits after local changes:

```powershell
git add .
git commit -m "Describe the change"
git push
```

Never commit real secrets or customer information. Refer to [SECURITY.md](SECURITY.md).
