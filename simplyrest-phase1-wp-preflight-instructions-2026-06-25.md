# Simply Rest WordPress Preflight Instructions

## Purpose

Run this before any phase-one publish step. It is read-only and checks whether WordPress is ready for the Simply Rest Lab launch package.

## What It Checks

- WordPress site/home URLs, permalink structure, PHP/WP versions, and upload limits.
- Whether the five priority pages already exist.
- Whether the `/mattress-reviews/` parent page exists before creating `/mattress-reviews/amerisleep-as3/`.
- Whether Yoast-compatible SEO fields are present on existing target pages.
- Whether the JSON-LD mu-plugin target path exists.
- Whether common redirect plugin tables/options contain AS3 or `/best-online-mattress/` redirect records.
- Whether Redirection, Safe Redirect Manager, Rank Math, Yoast, or ACF are installed/active.

## Run

Copy `simplyrest_phase1_wp_preflight.php` to the WordPress root, then run:

```bash
wp eval-file simplyrest_phase1_wp_preflight.php
```

For machine-readable output:

```bash
wp eval-file simplyrest_phase1_wp_preflight.php -- --format=json
```

## How To Use Results

- If a priority page is missing, run the phase-one importer in dry-run mode first.
- If `/mattress-reviews/` exists but has legacy content, use `--force-update-published` only after backup and approval.
- If AS3 redirect records appear, remove or disable the redirect in the owning plugin before publishing `/mattress-reviews/amerisleep-as3/`.
- If no redirect records appear but the live redirect persists, check CDN, server config, `.htaccess`, Nginx rules, theme code, or custom plugins.
- If upload limits are below the AS3 MP4 sizes, upload media through SFTP or increase PHP upload limits before running the AS3 media importer.
