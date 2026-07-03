# Simply Rest Phase-One Static Preview QA

## Scope

Static preview folder: `simplyrest-phase1-static-preview-2026-06-25`

Pages included:

- `mattress-lab.html`
- `how-we-test-mattresses.html`
- `ferdie-farhad.html`
- `mattress-reviews.html`
- `amerisleep-as3.html`

## Browser QA

Checked regenerated preview pages through the local preview server at:

`http://127.0.0.1:8765/`

Pages checked:

- `mattress-lab.html`
- `ferdie-farhad.html`
- `amerisleep-as3.html`

Desktop viewport, 1280 x 900:

- Mattress Lab: 1/1 image loaded, score database present, no horizontal overflow.
- Ferdie page: 2/2 images loaded, approved `ferdie-farhad-headshot.jpg` present, no horizontal overflow.
- AS3 review: 7/7 images loaded, 3/3 video blocks present, `Simply Rest Lab Score: 10/10` present, no horizontal overflow.
- Screenshots: `simplyrest-phase1-static-preview-desktop-2026-06-25.png`, `simplyrest-phase1-ferdie-preview-desktop-2026-06-25.png`, `simplyrest-phase1-lab-score-preview-desktop-2026-06-25.png`

Mobile viewport, 390 x 844:

- Mattress Lab: 1/1 image loaded, score database present, no horizontal overflow.
- Ferdie page: 2/2 images loaded, approved `ferdie-farhad-headshot.jpg` present, no horizontal overflow.
- AS3 review: 7/7 images loaded, 3/3 video blocks present, `Simply Rest Lab Score: 10/10` present, no horizontal overflow.
- Screenshot: `simplyrest-phase1-static-preview-mobile-2026-06-25.png`

## Source Update

The preview now reflects the supplied scoring matrix and Drive-verified Ferdie headshot:

- AS3 visible score and Review schema are `10/10`.
- Mattress Lab includes the current 23-row score database.
- Ferdie page shows the approved headshot and avatar crop from `Firdous Headshot.png`.
- The old Ferdie action crop remains optional and approval-gated.

## Claim-Safety Correction

The source preview initially included an explicit credential phrase in a negated sentence. That phrase was removed from the publishable WordPress source pages, the WP-CLI importer, the WXR import file, and the regenerated static preview. The QA checkers still keep that phrase as a forbidden public marker.

## Link Correction

The static preview builder now localizes longer Simply Rest URLs before shorter parent URLs, preventing the Mattress Reviews preview from rewriting the AS3 review link as `mattress-reviews.htmlamerisleep-as3/`.

## Limits

This static preview is not launch proof. Final QA still requires WordPress rendering, uploaded WordPress media URLs, schema validation, canonical checks, redirect checks, and commercial CTA disclosure review on the live site.
