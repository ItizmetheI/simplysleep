# Simply Rest AS3 Media Import Instructions

## Purpose

Use this after the five phase-one pages have been imported into WordPress. It uploads the optimized AS3 proof media, sets title/caption/alt text, renders the AS3 media section with WordPress-hosted URLs, and can replace the text-only evidence area on `/mattress-reviews/amerisleep-as3/`.

## Files

- `simplyrest-as3-optimized-media-2026-06-25.zip` - compressed AS3 images, clips, and poster frames.
- `simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv` - upload titles, captions, alt text, section mapping, and Gutenberg tokens.
- `simplyrest-as3-media-section-replacement-2026-06-25.html` - media-rich Gutenberg section used to replace the text-only AS3 testing block.
- `simplyrest_phase1_as3_media_import.php` - WP-CLI media importer and optional AS3 page patcher.

## Deploy Sequence

1. Copy the four files above to the WordPress root.
2. Unzip the optimized media package:

```bash
unzip simplyrest-as3-optimized-media-2026-06-25.zip -d simplyrest-as3-media
```

3. Dry run the media import:

```bash
wp eval-file simplyrest_phase1_as3_media_import.php -- \
  --media-dir=simplyrest-as3-media \
  --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
  --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html \
  --dry-run
```

4. Upload and attach the media:

```bash
wp eval-file simplyrest_phase1_as3_media_import.php -- \
  --media-dir=simplyrest-as3-media \
  --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
  --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html
```

5. Patch the AS3 page content after reviewing a backup and confirming the page is still draft/private/pending:

```bash
wp eval-file simplyrest_phase1_as3_media_import.php -- \
  --media-dir=simplyrest-as3-media \
  --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
  --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html \
  --update-page
```

6. If the AS3 page is already published, only patch it after approval:

```bash
wp eval-file simplyrest_phase1_as3_media_import.php -- \
  --media-dir=simplyrest-as3-media \
  --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
  --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html \
  --update-page \
  --force-update-published
```

## QA Gates

- AS3 media URLs in rendered HTML should be WordPress uploads, not Drive URLs.
- AS3 Photo 1 should be set as the featured image.
- The old text-only `Testing Evidence` list should be replaced by visible proof media.
- Captions should stay tied to observable testing evidence and avoid medical claims.
- Re-run the live QA checker after publishing and after removing the AS3 redirect.
