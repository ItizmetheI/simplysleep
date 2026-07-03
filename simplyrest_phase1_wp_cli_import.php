<?php
/**
 * Simply Rest Phase 1 Lab page importer.
 *
 * Run from the WordPress root with WP-CLI:
 *
 *   wp eval-file simplyrest_phase1_wp_cli_import.php -- --dry-run
 *   wp eval-file simplyrest_phase1_wp_cli_import.php
 *
 * Defaults:
 * - Creates missing pages as drafts.
 * - Updates existing draft/private/pending pages.
 * - Skips existing published pages unless --force-update-published is passed.
 * - Stores JSON-LD in _simplyrest_json_ld for the companion mu-plugin.
 *
 * Optional flags:
 * - --dry-run: report actions without writing.
 * - --publish: create/update pages as published instead of draft.
 * - --force-update-published: update published pages directly. Use only after backup/approval.
 * - --skip-snapshot: do not save pre-import snapshots before updating existing pages.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_wp_cli_import.php
");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$dry_run = in_array('--dry-run', $args, true);
$publish = in_array('--publish', $args, true);
$force_update_published = in_array('--force-update-published', $args, true);
$skip_snapshot = in_array('--skip-snapshot', $args, true);
$target_status = $publish ? 'publish' : 'draft';

$pages = json_decode(<<<'JSON'
[
  {
    "title": "Simply Rest Lab",
    "slug": "mattress-lab",
    "path": "mattress-lab",
    "parent_path": "",
    "menu_order": 1,
    "content": "<!-- wp:heading {\"level\":1} -->\n<h1>Simply Rest Lab</h1>\n<!-- /wp:heading -->\n\n<!-- SR-FERDIE-AUTHOR-MODULE -->\n<!-- wp:paragraph -->\n<p><strong>By Firdous “Ferdie” Farhad</strong> | <strong>Lead tester: Firdous “Ferdie” Farhad</strong> | <strong>Updated June 19, 2026</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Ferdie authors Simply Rest Lab mattress content from a first-hand, hands-on testing perspective, using pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and comfort observations to support each recommendation.</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Simply Rest Lab is our mattress testing and evidence system. We evaluate mattresses through hands-on field tests, structured scoring, and visual proof so shoppers can compare comfort, support, cooling, motion isolation, edge support, response, and value before they buy.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Our goal is simple: make mattress reviews easier to trust. Instead of relying only on brand claims, we use repeatable tests, product scorecards, and real photos or clips from each mattress evaluation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Disclosure and Review Limits</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. Commercial relationships do not change the Simply Rest Lab scoring formula, and recommendations should be supported by visible testing notes, product facts, and methodology links.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Our mattress testing is not medical advice. We evaluate comfort, support, pressure relief, cooling, motion isolation, edge support, response, setup, and value, but we do not diagnose, treat, or guarantee relief for pain, sleep disorders, or medical conditions.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>What Simply Rest Lab Measures</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li><strong>Pressure relief:</strong> how well the mattress cushions areas like the shoulders, hips, and lower back.</li>\n  <li><strong>Spinal alignment:</strong> how well the mattress supports neutral posture in side, back, and stomach positions.</li>\n  <li><strong>Motion isolation:</strong> how much movement transfers across the mattress.</li>\n  <li><strong>Cooling and breathability:</strong> how warm or cool the surface feels during use.</li>\n  <li><strong>Edge support:</strong> how stable the perimeter feels when sitting or lying near the edge.</li>\n  <li><strong>Response time:</strong> how quickly the mattress rebounds after compression.</li>\n  <li><strong>Setup and usability:</strong> how easy the mattress is to unbox, move, and use.</li>\n  <li><strong>Value:</strong> how the mattress performs relative to price, policies, and category expectations.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>How Our Lab Score Works</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The Simply Rest Lab Score is a weighted rating designed to reflect real buying decisions. Support and sleep fit carry the most weight, followed by value, edge support, motion transfer, cooling, response time, and trial policy.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Category</th><th>Weight</th><th>Why it matters</th></tr></thead><tbody><tr><td>Support / Sleep Fit</td><td>25%</td><td>Determines whether the mattress fits the sleeper’s body type and sleep position.</td></tr><tr><td>Value</td><td>15%</td><td>Compares price, quality, policies, and performance.</td></tr><tr><td>Edge Support</td><td>15%</td><td>Shows usable surface area and perimeter stability.</td></tr><tr><td>Motion Transfer</td><td>15%</td><td>Important for couples and restless sleepers.</td></tr><tr><td>Cooling &amp; Breathability</td><td>10%</td><td>Helps hot sleepers compare temperature comfort.</td></tr><tr><td>Response Time</td><td>10%</td><td>Shows how easy the mattress is to move on.</td></tr><tr><td>Trial Period</td><td>10%</td><td>Reflects buyer risk and return flexibility.</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:heading -->\n<h2>Current Simply Rest Lab Score Database</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>This is the current mattress score source of truth for phase-one reviews and recommendation pages. The Overall column is the public Lab Score; the remaining columns are category-level evidence scores used to explain the recommendation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Model</th><th>Overall</th><th>Value</th><th>Edge Support</th><th>Trial Period</th><th>Response Time</th><th>Motion Transfer</th><th>Cooling &amp; Breathability</th></tr></thead><tbody><tr><td>Amerisleep AS3</td><td>10</td><td>9</td><td>10</td><td>9</td><td>9</td><td>10</td><td>10</td></tr><tr><td>Amerisleep AS3 Hybrid</td><td>10</td><td>9</td><td>10</td><td>9</td><td>9</td><td>9</td><td>10</td></tr><tr><td>Amerisleep AS2</td><td>9</td><td>9</td><td>8</td><td>9</td><td>10</td><td>10</td><td>10</td></tr><tr><td>Amerisleep AS5</td><td>9</td><td>8</td><td>10</td><td>9</td><td>8</td><td>9</td><td>10</td></tr><tr><td>Amerisleep AS5 Hybrid</td><td>9</td><td>8</td><td>9</td><td>9</td><td>8</td><td>9</td><td>10</td></tr><tr><td>Amerisleep Organica</td><td>9</td><td>9</td><td>9</td><td>9</td><td>10</td><td>9</td><td>9</td></tr><tr><td>Amerisleep Organica (Plush)</td><td>9</td><td>8</td><td>8</td><td>9</td><td>10</td><td>9</td><td>9</td></tr><tr><td>Zoma Boost</td><td>10</td><td>10</td><td>10</td><td>9</td><td>9</td><td>10</td><td>9</td></tr><tr><td>Zoma Start</td><td>9</td><td>10</td><td>10</td><td>9</td><td>8</td><td>9</td><td>9</td></tr><tr><td>Zoma Hybrid</td><td>9</td><td>10</td><td>10</td><td>9</td><td>9</td><td>9</td><td>9</td></tr><tr><td>Nolah Natural 11\"</td><td>8</td><td>9</td><td>9</td><td>9</td><td>10</td><td>8</td><td>9</td></tr><tr><td>Nolah Evolution 15\"</td><td>10</td><td>10</td><td>10</td><td>9</td><td>9</td><td>10</td><td>9</td></tr><tr><td>Vaya Hybrid</td><td>8</td><td>10</td><td>8</td><td>9</td><td>10</td><td>8</td><td>8</td></tr><tr><td>Vaya Foam</td><td>8</td><td>10</td><td>7</td><td>9</td><td>9</td><td>9</td><td>8</td></tr><tr><td>Nest Bedding Sparrow</td><td>10</td><td>8</td><td>10</td><td>10</td><td>9</td><td>10</td><td>9</td></tr><tr><td>Brooklyn Bedding Aurora Luxe</td><td>9</td><td>9</td><td>8</td><td>9</td><td>8</td><td>9</td><td>10</td></tr><tr><td>Birch Natural</td><td>8</td><td>9</td><td>9</td><td>9</td><td>10</td><td>8</td><td>9</td></tr><tr><td>Bear Star Hybrid</td><td>9</td><td>9</td><td>9</td><td>9</td><td>8</td><td>9</td><td>9</td></tr><tr><td>GhostBed Luxe</td><td>7</td><td>8</td><td>8</td><td>9</td><td>6</td><td>10</td><td>9</td></tr><tr><td>Purple RestorePlus Hybrid</td><td>9</td><td>7</td><td>9</td><td>9</td><td>10</td><td>9</td><td>9</td></tr><tr><td>Saatva Classic</td><td>10</td><td>7</td><td>10</td><td>10</td><td>10</td><td>9</td><td>9</td></tr><tr><td>Helix Midnight Luxe</td><td>9</td><td>7</td><td>10</td><td>9</td><td>9</td><td>9</td><td>10</td></tr><tr><td>Amerisleep AS6 Black Series</td><td>10</td><td>9</td><td>9</td><td>9</td><td>9</td><td>10</td><td>10</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:heading -->\n<h2>Meet the Lead Tester</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Firdous “Ferdie” Farhad serves as Simply Rest Lab’s lead hands-on tester. Ferdie demonstrates mattress feel, pressure relief, spinal alignment, motion isolation, edge support, response, and setup in first-hand testing clips.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Editorial note:</strong> This page uses Ferdie’s safe approved role: author and lead hands-on tester. It avoids unsupported clinical or credential claims.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Where to Start</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li><a href=\"https://simplyrest.com/how-we-test-mattresses/\">How We Test Mattresses</a></li>\n  <li><a href=\"https://simplyrest.com/ferdie-farhad/\">Firdous “Ferdie” Farhad</a></li>\n  <li><a href=\"https://simplyrest.com/mattress-reviews/\">Mattress Reviews</a></li>\n  <li><a href=\"https://simplyrest.com/mattress-comparison/\">Mattress Comparison</a></li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Ferdie’s First-Hand Testing Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s first-hand testing summary connects this page to the Simply Rest Lab methodology, showing which pressure, alignment, motion, cooling, edge, response, setup, and comfort observations shaped the recommendation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul><li>Pressure relief: shoulder, hip, and lower-back response.</li><li>Spinal alignment: side, back, and stomach position support.</li><li>Motion isolation: movement transfer and partner-disturbance risk.</li><li>Cooling: cover feel, airflow, and thermal comfort notes.</li><li>Edge support: sitting and lying edge compression.</li><li>Response time: bounce-back and ease of movement.</li><li>Evidence: matching Simply Rest Lab photo or clip.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Methodology link:</strong> <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How Simply Rest Lab tests mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Simply Rest Lab FAQ</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>What is Simply Rest Lab?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Simply Rest Lab is the testing and evidence system behind Simply Rest mattress reviews, rankings, and comparisons.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Who tests mattresses for Simply Rest Lab?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Firdous “Ferdie” Farhad is the visible author and lead hands-on tester for Simply Rest Lab mattress content.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>How are Simply Rest Lab scores calculated?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Scores use a weighted formula built around support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy.</p>\n<!-- /wp:paragraph -->\n",
    "meta_title": "Simply Rest Lab | Mattress Testing, Scores, and Field-Tested Reviews",
    "meta_description": "See how Simply Rest Lab tests mattresses with first-hand evaluations, structured scores, and field testing from Firdous \"Ferdie\" Farhad.",
    "canonical": "https://simplyrest.com/mattress-lab/",
    "author_display": "Firdous \"Ferdie\" Farhad",
    "lead_tester": "Firdous \"Ferdie\" Farhad",
    "schema": {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "@id": "https://simplyrest.com/mattress-lab/#webpage",
          "url": "https://simplyrest.com/mattress-lab/",
          "name": "Simply Rest Lab",
          "description": "Simply Rest Lab tests mattresses with first-hand evaluations, structured scores, and field testing evidence.",
          "isPartOf": {
            "@id": "https://simplyrest.com/#website"
          },
          "about": {
            "@id": "https://simplyrest.com/mattress-lab/#article"
          },
          "breadcrumb": {
            "@id": "https://simplyrest.com/mattress-lab/#breadcrumb"
          }
        },
        {
          "@type": "Article",
          "@id": "https://simplyrest.com/mattress-lab/#article",
          "headline": "Simply Rest Lab",
          "description": "How Simply Rest Lab tests mattresses, calculates scores, and uses first-hand evidence in mattress reviews.",
          "author": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "publisher": {
            "@id": "https://simplyrest.com/#organization"
          },
          "datePublished": "2026-06-19",
          "dateModified": "2026-06-19",
          "mainEntityOfPage": {
            "@id": "https://simplyrest.com/mattress-lab/#webpage"
          }
        },
        {
          "@type": "FAQPage",
          "@id": "https://simplyrest.com/mattress-lab/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "What is Simply Rest Lab?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Simply Rest Lab is the testing and evidence system behind Simply Rest mattress reviews, rankings, and comparisons."
              }
            },
            {
              "@type": "Question",
              "name": "Who tests mattresses for Simply Rest Lab?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Firdous Farhad, also known as Ferdie Farhad, is the visible author and lead hands-on tester for Simply Rest Lab mattress content."
              }
            },
            {
              "@type": "Question",
              "name": "How are Simply Rest Lab scores calculated?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Scores use a weighted formula built around support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy."
              }
            }
          ]
        },
        {
          "@type": "BreadcrumbList",
          "@id": "https://simplyrest.com/mattress-lab/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://simplyrest.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Simply Rest Lab",
              "item": "https://simplyrest.com/mattress-lab/"
            }
          ]
        },
        {
          "@type": "Person",
          "@id": "https://simplyrest.com/ferdie-farhad/#person",
          "name": "Firdous Farhad",
          "alternateName": "Ferdie Farhad",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "jobTitle": "Simply Rest Lab Lead Hands-On Tester and Author",
          "worksFor": {
            "@id": "https://simplyrest.com/#organization"
          }
        },
        {
          "@type": "Organization",
          "@id": "https://simplyrest.com/#organization",
          "name": "Simply Rest",
          "url": "https://simplyrest.com/"
        }
      ]
    },
    "source_page_file": "wordpress-deploy/pages/mattress-lab-gutenberg.html",
    "source_schema_file": "wordpress-deploy/schema/mattress-lab-schema.json"
  },
  {
    "title": "How We Test Mattresses",
    "slug": "how-we-test-mattresses",
    "path": "how-we-test-mattresses",
    "parent_path": "",
    "menu_order": 2,
    "content": "<!-- wp:heading {\"level\":1} -->\n<h1>How We Test Mattresses</h1>\n<!-- /wp:heading -->\n\n<!-- SR-FERDIE-AUTHOR-MODULE -->\n<!-- wp:paragraph -->\n<p><strong>By Firdous “Ferdie” Farhad</strong> | <strong>Lead tester: Firdous “Ferdie” Farhad</strong> | <strong>Updated June 19, 2026</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Ferdie authors Simply Rest Lab mattress content from a first-hand, hands-on testing perspective, using pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and comfort observations to support each recommendation.</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Simply Rest Lab uses repeatable hands-on tests to evaluate how mattresses perform in the areas shoppers care about most: pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and long-term comfort.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Our testing process is designed to be practical and visible. When possible, we use photos and video clips so readers can see how each mattress responds instead of relying only on written claims.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Testing Disclosure and Limits</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. Testing notes, scorecards, and recommendations should still be tied to the Simply Rest Lab methodology, product evidence, and clearly labeled commercial relationships.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Our tests are designed to compare mattress performance, not to provide medical advice. Mattress comfort can affect how supported someone feels, but we do not diagnose, treat, cure, or guarantee relief for pain, sleep disorders, or medical conditions.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Pressure Relief Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Pressure relief testing looks at how well a mattress cushions high-pressure areas like the shoulders, hips, and lower back. Our tester lies on the mattress in common sleeping positions and documents how the surface conforms, compresses, and rebounds.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n  <li>Side, back, and stomach position checks</li>\n  <li>Hand impression or surface response photos</li>\n  <li>Close-ups around shoulder and hip zones</li>\n  <li>Notes on contouring, sink, and discomfort</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Spinal Alignment Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Spinal alignment testing checks whether the mattress supports a neutral body position. Our tester uses side-profile framing and, when helpful, a straight edge or yardstick to visually assess alignment.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n  <li>Side sleeping alignment</li>\n  <li>Back sleeping lumbar support</li>\n  <li>Stomach sleeping hip support</li>\n  <li>Visible sagging, lifting, or pressure notes</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Motion Isolation Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Motion isolation testing shows how much movement travels across the mattress. A glass of water may be placed on one side while the tester rolls, shifts, or gets up on the other side.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>This test is especially useful for couples and light sleepers because it shows whether one sleeper’s movement is likely to disturb another.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Temperature Regulation Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Temperature testing looks at how warm or cool the mattress feels during use. We may record the mattress surface temperature before and after lying on it, then combine that with material notes, cover feel, and subjective heat buildup observations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Edge Support Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Edge support testing evaluates the stability of the mattress perimeter. Our tester sits and lies near the edge to check compression, support, and whether the surface feels secure.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Response Time Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Response time testing shows how quickly the mattress recovers after pressure is applied. This helps shoppers understand whether the mattress feels slow and contouring or more responsive and easy to move on.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Noise Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Noise testing is done in a quiet environment while the tester rolls, shifts positions, and sits up. We listen for squeaks, creaks, cover rustling, or other movement sounds that could disturb sleep.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Setup and Usability Test</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Setup testing documents the unboxing or showroom experience, including handling, packaging, expansion, off-gassing, handles, and any usability friction shoppers should know about.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Long-Term Comfort Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>When possible, we add long-term comfort notes based on repeated use, sleep logs, or follow-up testing. These updates help show whether early impressions hold up over time.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>How Scores Are Calculated</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Our final Lab Score is weighted to reflect real-world buying importance. Support and sleep fit carry the most weight, followed by value, edge support, motion transfer, cooling, response time, and trial period.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Category</th><th>Weight</th></tr></thead><tbody><tr><td>Support / Sleep Fit</td><td>25%</td></tr><tr><td>Value</td><td>15%</td></tr><tr><td>Edge Support</td><td>15%</td></tr><tr><td>Motion Transfer</td><td>15%</td></tr><tr><td>Cooling &amp; Breathability</td><td>10%</td></tr><tr><td>Response Time</td><td>10%</td></tr><tr><td>Trial Period</td><td>10%</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:paragraph -->\n<p><strong>Important:</strong> Mattress comfort is personal. Our scores are designed to help shoppers compare performance, but the best mattress for you still depends on your body type, sleep position, comfort preferences, and budget.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Ferdie’s First-Hand Testing Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s first-hand testing summary connects this page to the Simply Rest Lab methodology, showing which pressure, alignment, motion, cooling, edge, response, setup, and comfort observations shaped the recommendation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul><li>Pressure relief: shoulder, hip, and lower-back response.</li><li>Spinal alignment: side, back, and stomach position support.</li><li>Motion isolation: movement transfer and partner-disturbance risk.</li><li>Cooling: cover feel, airflow, and thermal comfort notes.</li><li>Edge support: sitting and lying edge compression.</li><li>Response time: bounce-back and ease of movement.</li><li>Evidence: matching Simply Rest Lab photo or clip.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Methodology link:</strong> <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How Simply Rest Lab tests mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Testing FAQ</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Does Simply Rest test mattresses first hand?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Yes. Simply Rest Lab uses hands-on testing notes, photos, and clips where available to support review scores and recommendations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>What categories affect the Lab Score?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The Lab Score weighs support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Are mattress testing notes medical advice?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>No. Testing notes compare comfort and performance factors, but they do not replace advice from a qualified medical professional.</p>\n<!-- /wp:paragraph -->\n",
    "meta_title": "How Simply Rest Tests Mattresses | Simply Rest Lab Methodology",
    "meta_description": "Learn how Simply Rest Lab tests mattresses for pressure relief, alignment, motion isolation, cooling, edge support, response, setup, and comfort.",
    "canonical": "https://simplyrest.com/how-we-test-mattresses/",
    "author_display": "Firdous \"Ferdie\" Farhad",
    "lead_tester": "Firdous \"Ferdie\" Farhad",
    "schema": {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "@id": "https://simplyrest.com/how-we-test-mattresses/#webpage",
          "url": "https://simplyrest.com/how-we-test-mattresses/",
          "name": "How We Test Mattresses",
          "description": "Simply Rest Lab mattress testing procedures for pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and long-term comfort.",
          "breadcrumb": {
            "@id": "https://simplyrest.com/how-we-test-mattresses/#breadcrumb"
          }
        },
        {
          "@type": "Article",
          "@id": "https://simplyrest.com/how-we-test-mattresses/#article",
          "headline": "How We Test Mattresses",
          "description": "A guide to Simply Rest Lab mattress testing procedures, including pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and long-term comfort.",
          "author": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "publisher": {
            "@id": "https://simplyrest.com/#organization"
          },
          "datePublished": "2026-06-19",
          "dateModified": "2026-06-19",
          "mainEntityOfPage": {
            "@id": "https://simplyrest.com/how-we-test-mattresses/#webpage"
          }
        },
        {
          "@type": "FAQPage",
          "@id": "https://simplyrest.com/how-we-test-mattresses/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "Does Simply Rest test mattresses first hand?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes. Simply Rest Lab uses hands-on testing notes, photos, and clips where available to support review scores and recommendations."
              }
            },
            {
              "@type": "Question",
              "name": "What categories affect the Lab Score?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "The Lab Score weighs support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy."
              }
            },
            {
              "@type": "Question",
              "name": "Are mattress testing notes medical advice?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "No. Testing notes compare comfort and performance factors, but they do not replace advice from a qualified medical professional."
              }
            }
          ]
        },
        {
          "@type": "BreadcrumbList",
          "@id": "https://simplyrest.com/how-we-test-mattresses/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://simplyrest.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "How We Test Mattresses",
              "item": "https://simplyrest.com/how-we-test-mattresses/"
            }
          ]
        },
        {
          "@type": "Person",
          "@id": "https://simplyrest.com/ferdie-farhad/#person",
          "name": "Firdous Farhad",
          "alternateName": "Ferdie Farhad",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "jobTitle": "Simply Rest Lab Lead Hands-On Tester and Author",
          "worksFor": {
            "@id": "https://simplyrest.com/#organization"
          }
        },
        {
          "@type": "Organization",
          "@id": "https://simplyrest.com/#organization",
          "name": "Simply Rest",
          "url": "https://simplyrest.com/"
        }
      ]
    },
    "source_page_file": "wordpress-deploy/pages/how-we-test-mattresses-gutenberg.html",
    "source_schema_file": "wordpress-deploy/schema/how-we-test-schema.json"
  },
  {
    "title": "Firdous \"Ferdie\" Farhad",
    "slug": "ferdie-farhad",
    "path": "ferdie-farhad",
    "parent_path": "",
    "menu_order": 3,
    "content": "<!-- wp:heading {\"level\":1} -->\n<h1>Firdous “Ferdie” Farhad</h1>\n<!-- /wp:heading -->\n\n<!-- SR-FERDIE-AUTHOR-MODULE -->\n<!-- wp:paragraph -->\n<p><strong>By Firdous “Ferdie” Farhad</strong> | <strong>Lead tester: Firdous “Ferdie” Farhad</strong> | <strong>Updated June 19, 2026</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Ferdie authors Simply Rest Lab mattress content from a first-hand, hands-on testing perspective, using pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and comfort observations to support each recommendation.</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Firdous “Ferdie” Farhad is a Simply Rest Lab lead hands-on tester who demonstrates hands-on mattress evaluations for pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and real-world comfort.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s testing role is focused on showing how mattresses behave in practical, visible ways. His demos help shoppers see how a mattress responds to weight, movement, position changes, edge use, and everyday handling.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Role Disclosure</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie is presented as Simply Rest Lab’s author and lead hands-on tester. He demonstrates first-hand mattress testing, but Simply Rest Lab owns the scoring methodology, editorial review process, and final recommendations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. Ferdie’s testing role should not be described as medical review, diagnosis, treatment guidance, or a clinical endorsement unless separately verified and approved.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>What Ferdie Tests</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li>Pressure relief at the shoulders, hips, and lower back</li>\n  <li>Spinal alignment in side, back, and stomach sleeping positions</li>\n  <li>Motion isolation using movement and glass-of-water tests</li>\n  <li>Cooling feel and surface temperature changes</li>\n  <li>Edge support while sitting and lying near the perimeter</li>\n  <li>Response time and ease of movement</li>\n  <li>Setup, handling, and usability</li>\n  <li>Long-term comfort notes when available</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>How Ferdie Fits Into Simply Rest Lab</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie is the lead first-hand testing and demonstration layer. Simply Rest Lab owns the score methodology, editorial recommendations, evidence review, and final rankings.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>That separation matters. Ferdie helps demonstrate what happens during testing, while Simply Rest uses those tests, product data, policy information, and editorial review to make final recommendations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Credential Note</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>This page uses Ferdie’s safe approved role: author and lead hands-on tester for Simply Rest Lab. It avoids unsupported clinical or credential claims.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Testing Philosophy</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Mattress testing should be understandable. Ferdie’s demos are designed to show shoppers what a mattress feels like, where it performs well, and what tradeoffs to consider before buying.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>For the full testing process, see <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How We Test Mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Ferdie’s First-Hand Testing Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s first-hand testing summary connects this page to the Simply Rest Lab methodology, showing which pressure, alignment, motion, cooling, edge, response, setup, and comfort observations shaped the recommendation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul><li>Pressure relief: shoulder, hip, and lower-back response.</li><li>Spinal alignment: side, back, and stomach position support.</li><li>Motion isolation: movement transfer and partner-disturbance risk.</li><li>Cooling: cover feel, airflow, and thermal comfort notes.</li><li>Edge support: sitting and lying edge compression.</li><li>Response time: bounce-back and ease of movement.</li><li>Evidence: matching Simply Rest Lab photo or clip.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Methodology link:</strong> <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How Simply Rest Lab tests mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Ferdie Farhad FAQ</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Who is Firdous “Ferdie” Farhad?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Firdous “Ferdie” Farhad is the Simply Rest Lab author and lead hands-on tester for mattress testing content.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>What does Ferdie test?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie demonstrates pressure relief, spinal alignment, motion isolation, cooling, edge support, response time, setup, and comfort observations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Does Ferdie provide medical advice?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>No. Ferdie’s role is hands-on testing and product demonstration, not medical diagnosis, treatment, or clinical care.</p>\n<!-- /wp:paragraph -->\n",
    "meta_title": "Firdous \"Ferdie\" Farhad | Simply Rest Lab Lead Tester",
    "meta_description": "Meet Firdous \"Ferdie\" Farhad, Simply Rest Lab author and lead hands-on tester for mattress comfort, support, motion, edge, and response testing.",
    "canonical": "https://simplyrest.com/ferdie-farhad/",
    "author_display": "Firdous \"Ferdie\" Farhad",
    "lead_tester": "Firdous \"Ferdie\" Farhad",
    "schema": {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "ProfilePage",
          "@id": "https://simplyrest.com/ferdie-farhad/#webpage",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "name": "Firdous Ferdie Farhad",
          "description": "Profile for Firdous Ferdie Farhad, Simply Rest Lab author and lead hands-on mattress tester.",
          "mainEntity": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "breadcrumb": {
            "@id": "https://simplyrest.com/ferdie-farhad/#breadcrumb"
          }
        },
        {
          "@type": "Person",
          "@id": "https://simplyrest.com/ferdie-farhad/#person",
          "name": "Firdous Farhad",
          "alternateName": "Ferdie Farhad",
          "jobTitle": "Simply Rest Lab Lead Hands-On Tester and Author",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "worksFor": {
            "@id": "https://simplyrest.com/#organization"
          },
          "description": "Firdous Ferdie Farhad demonstrates first-hand mattress testing for Simply Rest Lab. Verify exact professional credentials before publishing additional credential properties."
        },
        {
          "@type": "FAQPage",
          "@id": "https://simplyrest.com/ferdie-farhad/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "Who is Firdous “Ferdie” Farhad?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Firdous “Ferdie” Farhad is the Simply Rest Lab author and lead hands-on tester for mattress testing content."
              }
            },
            {
              "@type": "Question",
              "name": "What does Ferdie test?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Ferdie demonstrates pressure relief, spinal alignment, motion isolation, cooling, edge support, response time, setup, and comfort observations."
              }
            },
            {
              "@type": "Question",
              "name": "Does Ferdie provide medical advice?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "No. Ferdie's role is hands-on testing and product demonstration, not medical diagnosis, treatment, or clinical care."
              }
            }
          ]
        },
        {
          "@type": "BreadcrumbList",
          "@id": "https://simplyrest.com/ferdie-farhad/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://simplyrest.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Firdous Ferdie Farhad",
              "item": "https://simplyrest.com/ferdie-farhad/"
            }
          ]
        },
        {
          "@type": "Organization",
          "@id": "https://simplyrest.com/#organization",
          "name": "Simply Rest",
          "url": "https://simplyrest.com/"
        }
      ]
    },
    "source_page_file": "wordpress-deploy/pages/ferdie-farhad-gutenberg.html",
    "source_schema_file": "wordpress-deploy/schema/ferdie-farhad-schema.json"
  },
  {
    "title": "Mattress Reviews",
    "slug": "mattress-reviews",
    "path": "mattress-reviews",
    "parent_path": "",
    "menu_order": 4,
    "content": "<!-- wp:heading {\"level\":1} -->\n<h1>Mattress Reviews</h1>\n<!-- /wp:heading -->\n\n<!-- SR-FERDIE-AUTHOR-MODULE -->\n<!-- wp:paragraph -->\n<p><strong>By Firdous “Ferdie” Farhad</strong> | <strong>Lead tester: Firdous “Ferdie” Farhad</strong> | <strong>Updated June 19, 2026</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Ferdie authors Simply Rest Lab mattress content from a first-hand, hands-on testing perspective, using pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and comfort observations to support each recommendation.</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Browse Simply Rest Lab mattress reviews, scorecards, and field-tested recommendations. Each review is designed to show how the mattress performs for pressure relief, spinal alignment, motion isolation, cooling, edge support, response, value, and setup.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Review Disclosure</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. Review scores are intended to reflect the Simply Rest Lab scoring methodology, visible testing evidence, product facts, policies, and editorial review.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Mattress recommendations are not medical advice. We discuss support, pressure relief, comfort, and sleep-position fit, but we do not diagnose, treat, cure, or guarantee relief for pain, sleep disorders, or medical conditions.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Featured Tested Mattresses</h2>\n<!-- /wp:heading -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Mattress</th><th>Lab Score</th><th>Best For</th><th>Status</th></tr></thead><tbody><tr><td><a href=\"https://simplyrest.com/mattress-reviews/amerisleep-as3/\">Amerisleep AS3</a></td><td>10/10</td><td>Balanced foam feel, couples, side/back sleepers</td><td>Published first</td></tr><tr><td>Amerisleep AS3 Hybrid</td><td>10/10</td><td>Hybrid feel, broad comfort, combination sleepers</td><td>Queued for review page</td></tr><tr><td>Amerisleep AS2</td><td>9/10</td><td>Firmer foam support, back and stomach sleepers</td><td>Queued for review page</td></tr><tr><td>Amerisleep AS5</td><td>9/10</td><td>Plush foam feel, deep pressure relief, side sleepers</td><td>Queued for review page</td></tr><tr><td>Amerisleep AS5 Hybrid</td><td>9/10</td><td>Plush hybrid, deep cushion, side and back sleepers</td><td>Queued for review page</td></tr><tr><td>Amerisleep Organica</td><td>9/10</td><td>Natural materials, balanced feel, back sleepers</td><td>Queued for review page</td></tr><tr><td>Amerisleep Organica (Plush)</td><td>9/10</td><td>Soft organic feel, eco-conscious shoppers, side sleepers</td><td>Queued for review page</td></tr><tr><td>Zoma Boost</td><td>10/10</td><td>Recovery-focused sleepers, cooling-focused shoppers</td><td>Queued for review page</td></tr><tr><td>Zoma Start</td><td>9/10</td><td>Budget performance, back and combo sleepers</td><td>Queued for review page</td></tr><tr><td>Zoma Hybrid</td><td>9/10</td><td>Hybrid support, value shoppers, recovery-focused sleepers</td><td>Queued for review page</td></tr><tr><td>Nolah Natural 11&quot;</td><td>8/10</td><td>Natural materials, eco-conscious buyers, back sleepers</td><td>Queued for review page</td></tr><tr><td>Nolah Evolution 15&quot;</td><td>10/10</td><td>Pillow-top comfort, couples, side sleepers</td><td>Queued for review page</td></tr><tr><td>Vaya Hybrid</td><td>8/10</td><td>Budget hybrid, back and combo sleepers</td><td>Queued for review page</td></tr><tr><td>Vaya Foam</td><td>8/10</td><td>Budget foam, back sleepers, value-focused shoppers</td><td>Queued for review page</td></tr><tr><td>Nest Bedding Sparrow</td><td>10/10</td><td>Luxury hybrid, edge support, couples</td><td>Queued for review page</td></tr><tr><td>Brooklyn Bedding Aurora Luxe</td><td>9/10</td><td>Cooling performance, hot sleepers, couples</td><td>Queued for review page</td></tr><tr><td>Birch Natural</td><td>8/10</td><td>Organic latex, eco-conscious buyers, back sleepers</td><td>Queued for review page</td></tr><tr><td>Bear Star Hybrid</td><td>9/10</td><td>Active recovery, hybrid support, combination sleepers</td><td>Queued for review page</td></tr><tr><td>GhostBed Luxe</td><td>7/10</td><td>Extreme cooling performance, hot sleepers</td><td>Queued for review page</td></tr><tr><td>Purple RestorePlus Hybrid</td><td>9/10</td><td>Pressure relief, cooling, side and back sleepers</td><td>Queued for review page</td></tr><tr><td>Saatva Classic</td><td>10/10</td><td>Traditional innerspring feel, edge support, trial-policy shoppers</td><td>Queued for review page</td></tr><tr><td>Helix Midnight Luxe</td><td>9/10</td><td>Luxury side-sleeper support, couples, motion isolation</td><td>Queued for review page</td></tr><tr><td>Amerisleep AS6 Black Series</td><td>10/10</td><td>Luxury performance, deep compression, side sleepers</td><td>Queued for review page</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:heading -->\n<h2>How to Read Our Reviews</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li><strong>Lab Score:</strong> public Overall score from the Simply Rest mattress scoring matrix, supported by category scores for value, edge support, motion transfer, cooling, response time, and trial period.</li>\n  <li><strong>Tested by:</strong> identifies the lead hands-on tester and proof assets used.</li>\n  <li><strong>Best for:</strong> explains the sleeper types most likely to fit the mattress.</li>\n  <li><strong>Watch-outs:</strong> flags situations where shoppers may want a different mattress.</li>\n  <li><strong>Evidence:</strong> links the review to hands-on testing photos and clips.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Review Categories</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li><a href=\"https://simplyrest.com/best-mattress-2026/\">Best Mattresses</a></li>\n  <li><a href=\"https://simplyrest.com/best-mattress-for-side-sleepers-2026/\">Best Mattresses for Side Sleepers</a></li>\n  <li><a href=\"https://simplyrest.com/best-cooling-mattress/\">Best Cooling Mattresses</a></li>\n  <li><a href=\"https://simplyrest.com/best-mattress-for-couples/\">Best Mattresses for Couples</a></li>\n  <li><a href=\"https://simplyrest.com/mattress-comparison/\">Mattress Comparison</a></li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>To learn more about our testing process, see <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How We Test Mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Ferdie’s First-Hand Testing Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s first-hand testing summary connects this page to the Simply Rest Lab methodology, showing which pressure, alignment, motion, cooling, edge, response, setup, and comfort observations shaped the recommendation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul><li>Pressure relief: shoulder, hip, and lower-back response.</li><li>Spinal alignment: side, back, and stomach position support.</li><li>Motion isolation: movement transfer and partner-disturbance risk.</li><li>Cooling: cover feel, airflow, and thermal comfort notes.</li><li>Edge support: sitting and lying edge compression.</li><li>Response time: bounce-back and ease of movement.</li><li>Evidence: matching Simply Rest Lab photo or clip.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Methodology link:</strong> <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How Simply Rest Lab tests mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Mattress Reviews FAQ</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>How does Simply Rest choose reviewed mattresses?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Reviews prioritize mattresses with available product facts, policy details, scorecard inputs, and first-hand testing evidence or evidence gaps that can be clearly disclosed.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>What should readers look for in a mattress review?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Readers should compare the Lab Score, sleeper fit, testing evidence, product specs, policies, disclosures, and clear reasons to buy or skip.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Do all reviews link back to the testing methodology?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Yes. Every review should link back to the How We Test Mattresses page so readers can understand how scores and testing notes are produced.</p>\n<!-- /wp:paragraph -->\n",
    "meta_title": "Mattress Reviews | Simply Rest Lab Tested Mattress Reviews",
    "meta_description": "Browse Simply Rest Lab mattress reviews with hands-on testing, scorecards, lead hands-on tester notes, and evidence-backed recommendations.",
    "canonical": "https://simplyrest.com/mattress-reviews/",
    "author_display": "Firdous \"Ferdie\" Farhad",
    "lead_tester": "Firdous \"Ferdie\" Farhad",
    "schema": {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "CollectionPage",
          "@id": "https://simplyrest.com/mattress-reviews/#webpage",
          "name": "Mattress Reviews",
          "url": "https://simplyrest.com/mattress-reviews/",
          "description": "Simply Rest Lab mattress reviews with scorecards, field testing evidence, and buyer guidance.",
          "author": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "breadcrumb": {
            "@id": "https://simplyrest.com/mattress-reviews/#breadcrumb"
          },
          "mainEntity": {
            "@id": "https://simplyrest.com/mattress-reviews/#itemlist"
          }
        },
        {
          "@type": "ItemList",
          "@id": "https://simplyrest.com/mattress-reviews/#itemlist",
          "name": "Featured Simply Rest Lab Mattress Reviews",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Amerisleep AS3 Mattress Review",
              "url": "https://simplyrest.com/mattress-reviews/amerisleep-as3/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Amerisleep AS3 Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 3,
              "name": "Amerisleep AS2 Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 4,
              "name": "Amerisleep AS5 Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 5,
              "name": "Amerisleep AS5 Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 6,
              "name": "Amerisleep Organica Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 7,
              "name": "Amerisleep Organica (Plush) Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 8,
              "name": "Zoma Boost Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 9,
              "name": "Zoma Start Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 10,
              "name": "Zoma Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 11,
              "name": "Nolah Natural 11\" Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 12,
              "name": "Nolah Evolution 15\" Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 13,
              "name": "Vaya Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 14,
              "name": "Vaya Foam Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 15,
              "name": "Nest Bedding Sparrow Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 16,
              "name": "Brooklyn Bedding Aurora Luxe Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 17,
              "name": "Birch Natural Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 18,
              "name": "Bear Star Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 19,
              "name": "GhostBed Luxe Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 20,
              "name": "Purple RestorePlus Hybrid Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 21,
              "name": "Saatva Classic Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 22,
              "name": "Helix Midnight Luxe Mattress Review"
            },
            {
              "@type": "ListItem",
              "position": 23,
              "name": "Amerisleep AS6 Black Series Mattress Review"
            }
          ]
        },
        {
          "@type": "FAQPage",
          "@id": "https://simplyrest.com/mattress-reviews/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "How does Simply Rest choose reviewed mattresses?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Reviews prioritize mattresses with available product facts, policy details, scorecard inputs, and first-hand testing evidence or evidence gaps that can be clearly disclosed."
              }
            },
            {
              "@type": "Question",
              "name": "What should readers look for in a mattress review?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Readers should compare the Lab Score, sleeper fit, testing evidence, product specs, policies, disclosures, and clear reasons to buy or skip."
              }
            },
            {
              "@type": "Question",
              "name": "Do all reviews link back to the testing methodology?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes. Every review should link back to the How We Test Mattresses page so readers can understand how scores and testing notes are produced."
              }
            }
          ]
        },
        {
          "@type": "BreadcrumbList",
          "@id": "https://simplyrest.com/mattress-reviews/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://simplyrest.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Mattress Reviews",
              "item": "https://simplyrest.com/mattress-reviews/"
            }
          ]
        },
        {
          "@type": "Person",
          "@id": "https://simplyrest.com/ferdie-farhad/#person",
          "name": "Firdous Farhad",
          "alternateName": "Ferdie Farhad",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "jobTitle": "Simply Rest Lab Lead Hands-On Tester and Author",
          "worksFor": {
            "@id": "https://simplyrest.com/#organization"
          }
        },
        {
          "@type": "Organization",
          "@id": "https://simplyrest.com/#organization",
          "name": "Simply Rest",
          "url": "https://simplyrest.com/"
        }
      ]
    },
    "source_page_file": "wordpress-deploy/pages/mattress-reviews-hub-gutenberg.html",
    "source_schema_file": "wordpress-deploy/schema/mattress-reviews-hub-schema.json"
  },
  {
    "title": "Amerisleep AS3 Mattress Review",
    "slug": "amerisleep-as3",
    "path": "mattress-reviews/amerisleep-as3",
    "parent_path": "mattress-reviews",
    "menu_order": 5,
    "content": "<!-- wp:heading {\"level\":1} -->\n<h1>Amerisleep AS3 Mattress Review</h1>\n<!-- /wp:heading -->\n\n<!-- SR-FERDIE-AUTHOR-MODULE -->\n<!-- wp:paragraph -->\n<p><strong>By Firdous “Ferdie” Farhad</strong> | <strong>Lead tester: Firdous “Ferdie” Farhad</strong> | <strong>Updated June 19, 2026</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Ferdie authors Simply Rest Lab mattress content from a first-hand, hands-on testing perspective, using pressure relief, spinal alignment, motion isolation, cooling, edge support, response, setup, and comfort observations to support each recommendation.</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Simply Rest Lab Score:</strong> 10/10 | <strong>Lead tester:</strong> Firdous “Ferdie” Farhad | <strong>Best for:</strong> most sleepers, couples, side sleepers, back sleepers, and combination sleepers.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>The Amerisleep AS3 is one of the strongest all-around mattresses in the Simply Rest Lab score database. It earns high marks for pressure relief, motion isolation, edge support, cooling, and overall sleeper compatibility.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Disclosure and Review Limits</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. This review should keep commercial CTAs separate from the scoring explanation and tie recommendations back to testing evidence, product facts, and the Simply Rest Lab methodology.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>This mattress review is not medical advice. We discuss comfort, support, pressure relief, cooling, and sleeper fit, but we do not diagnose, treat, cure, or guarantee relief for pain, sleep disorders, or medical conditions.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Quick Verdict</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 is a strong fit for shoppers who want a balanced foam mattress that cushions pressure points without feeling overly soft or restrictive. It is especially useful for side sleepers, back sleepers, couples, and people who want one mattress with broad appeal.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Strict stomach sleepers or shoppers who prefer a very firm mattress may want a firmer model.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Scorecard</h2>\n<!-- /wp:heading -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Category</th><th>Score</th><th>What it means</th></tr></thead><tbody><tr><td>Overall</td><td>10</td><td>Excellent all-around performance.</td></tr><tr><td>Value</td><td>9</td><td>Strong performance relative to category and expected price range.</td></tr><tr><td>Edge Support</td><td>10</td><td>Stable perimeter for sitting and lying near the edge.</td></tr><tr><td>Trial Period</td><td>9</td><td>Buyer-friendly trial policy.</td></tr><tr><td>Response Time</td><td>9</td><td>Responsive enough for easier position changes.</td></tr><tr><td>Motion Transfer</td><td>10</td><td>Excellent motion isolation for couples.</td></tr><tr><td>Cooling &amp; Breathability</td><td>10</td><td>Strong cooling and airflow performance for a foam mattress.</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:heading -->\n<h2>Testing Evidence</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 Drive proof pack includes product photos, hand impression photos, side/back/stomach sleeper photos, edge support media, a testing clip, an edge support test clip, and a response time clip.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n  <li>AS3 Testing Clip</li>\n  <li>AS3 Edge Support Test</li>\n  <li>AS3 Response Time</li>\n  <li>AS3 Hand Impression</li>\n  <li>AS3 Side Sleeper</li>\n  <li>AS3 Back Sleeper</li>\n  <li>AS3 Stomach Sleeper</li>\n  <li>AS3 Edge Support</li>\n  <li>AS3 Photo 1 and Photo 2</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Media source:</strong> Approved AS3 testing assets should be uploaded to the WordPress media library before publication. Do not expose private Drive folders on the live page.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Pressure Relief</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 is designed to sit in a medium-feel range, which helps it cushion the shoulders and hips while keeping enough support under the torso. In testing, this kind of balance is useful for side sleepers and combination sleepers who need pressure relief without excessive sink.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Spinal Alignment</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 is best suited to sleepers who need balanced contouring and support. Side sleepers should get enough cushioning at the shoulder and hip, while many back sleepers should still feel supported through the midsection.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Motion Isolation</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 earns a 10 for motion transfer, making it one of the better options in this dataset for couples. Foam construction generally helps absorb movement before it travels across the bed.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Edge Support</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 scores a 10 for edge support in the current score table. That makes it a stronger candidate for shoppers who sit near the edge, share a smaller mattress, or want more usable sleep surface.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Cooling and Breathability</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 scores a 10 for cooling and breathability. The live page supports that score with cooling-related testing notes, cover and material visuals, and available thermal observations.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Best For</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li>Side sleepers who want pressure relief without a very soft feel</li>\n  <li>Back sleepers who like a balanced foam mattress</li>\n  <li>Couples who need strong motion isolation</li>\n  <li>Combination sleepers who change positions</li>\n  <li>Shoppers looking for a broad all-around pick</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Consider Another Mattress If</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n  <li>You want an extra-firm mattress</li>\n  <li>You are a strict stomach sleeper who needs more lift</li>\n  <li>You prefer the bounce of a hybrid mattress</li>\n  <li>You want a very plush pillow-top feel</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading -->\n<h2>Bottom Line</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The Amerisleep AS3 is one of the best all-around mattresses in the Simply Rest Lab dataset. Its strongest case is balance: pressure relief, motion isolation, edge support, cooling, and broad sleeper compatibility in one foam mattress.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>For more on the testing process, see <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How We Test Mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Ferdie’s First-Hand Testing Notes</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Ferdie’s first-hand testing notes cover pressure relief, spinal alignment, motion isolation, cooling, edge support, response time, setup, and long-term comfort. Each note is tied to the matching Simply Rest Lab photo or clip so readers can see the evidence behind the score.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul><li>Pressure relief: shoulder, hip, and lower-back response.</li><li>Spinal alignment: side, back, and stomach position support.</li><li>Motion isolation: movement transfer and partner-disturbance risk.</li><li>Cooling: cover feel, airflow, and thermal comfort notes.</li><li>Edge support: sitting and lying edge compression.</li><li>Response time: bounce-back and ease of movement.</li><li>Evidence: matching Simply Rest Lab photo or clip.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><strong>Methodology link:</strong> <a href=\"https://simplyrest.com/how-we-test-mattresses/\">How Simply Rest Lab tests mattresses</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Amerisleep AS3 FAQ</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>What is the Amerisleep AS3 best for?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 is best for shoppers who want a balanced foam feel, broad sleeper compatibility, strong motion isolation, and pressure relief without an extremely soft feel.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Who may want to skip the Amerisleep AS3?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Strict stomach sleepers, shoppers who want an extra-firm mattress, and people who prefer a bouncier hybrid feel may want a different model.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>How was the Amerisleep AS3 scored?</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>The AS3 score uses the Simply Rest scoring matrix, with a 10/10 Overall score and category scores for value, edge support, trial period, response time, motion transfer, cooling, and breathability.</p>\n<!-- /wp:paragraph -->\n",
    "meta_title": "Amerisleep AS3 Mattress Review | Simply Rest Lab Tested",
    "meta_description": "See the Amerisleep AS3 mattress score, testing notes, pressure relief, edge support, motion isolation, cooling, response, and who it is best for.",
    "canonical": "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
    "author_display": "Firdous \"Ferdie\" Farhad",
    "lead_tester": "Firdous \"Ferdie\" Farhad",
    "schema": {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#webpage",
          "url": "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
          "name": "Amerisleep AS3 Mattress Review",
          "description": "Simply Rest Lab review of the Amerisleep AS3 mattress with scorecard, testing evidence, sleeper fit, and methodology links.",
          "breadcrumb": {
            "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#breadcrumb"
          },
          "mainEntity": {
            "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#review"
          }
        },
        {
          "@type": "Review",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#review",
          "headline": "Amerisleep AS3 Mattress Review",
          "url": "https://simplyrest.com/mattress-reviews/amerisleep-as3/",
          "reviewBody": "The Amerisleep AS3 is one of the strongest all-around mattresses in the Simply Rest Lab score database, with high scores for pressure relief, motion isolation, edge support, cooling, and broad sleeper compatibility.",
          "itemReviewed": {
            "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#product"
          },
          "reviewRating": {
            "@type": "Rating",
            "ratingValue": "10",
            "bestRating": "10",
            "worstRating": "1"
          },
          "author": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "publisher": {
            "@id": "https://simplyrest.com/#organization"
          },
          "datePublished": "2026-06-19",
          "dateModified": "2026-06-19",
          "reviewedBy": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          }
        },
        {
          "@type": "Product",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#product",
          "name": "Amerisleep AS3 Mattress",
          "brand": {
            "@type": "Brand",
            "name": "Amerisleep"
          },
          "category": "Mattress"
        },
        {
          "@type": "Article",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#article",
          "headline": "Amerisleep AS3 Mattress Review",
          "description": "A Simply Rest Lab-tested Amerisleep AS3 mattress review covering pressure relief, motion isolation, edge support, cooling, response, sleeper fit, and buying guidance.",
          "author": {
            "@id": "https://simplyrest.com/ferdie-farhad/#person"
          },
          "publisher": {
            "@id": "https://simplyrest.com/#organization"
          },
          "datePublished": "2026-06-19",
          "dateModified": "2026-06-19",
          "mainEntityOfPage": {
            "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#webpage"
          },
          "about": {
            "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#product"
          }
        },
        {
          "@type": "FAQPage",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "What is the Amerisleep AS3 best for?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "The AS3 is best for shoppers who want a balanced foam feel, broad sleeper compatibility, strong motion isolation, and pressure relief without an extremely soft feel."
              }
            },
            {
              "@type": "Question",
              "name": "Who may want to skip the Amerisleep AS3?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Strict stomach sleepers, shoppers who want an extra-firm mattress, and people who prefer a bouncier hybrid feel may want a different model."
              }
            },
            {
              "@type": "Question",
              "name": "How was the Amerisleep AS3 scored?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "The AS3 score uses the Simply Rest Lab formula, weighing support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy."
              }
            }
          ]
        },
        {
          "@type": "BreadcrumbList",
          "@id": "https://simplyrest.com/mattress-reviews/amerisleep-as3/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://simplyrest.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Mattress Reviews",
              "item": "https://simplyrest.com/mattress-reviews/"
            },
            {
              "@type": "ListItem",
              "position": 3,
              "name": "Amerisleep AS3 Mattress Review",
              "item": "https://simplyrest.com/mattress-reviews/amerisleep-as3/"
            }
          ]
        },
        {
          "@type": "Person",
          "@id": "https://simplyrest.com/ferdie-farhad/#person",
          "name": "Firdous Farhad",
          "alternateName": "Ferdie Farhad",
          "url": "https://simplyrest.com/ferdie-farhad/",
          "jobTitle": "Simply Rest Lab Lead Hands-On Tester and Author",
          "worksFor": {
            "@id": "https://simplyrest.com/#organization"
          }
        },
        {
          "@type": "Organization",
          "@id": "https://simplyrest.com/#organization",
          "name": "Simply Rest",
          "url": "https://simplyrest.com/"
        }
      ]
    },
    "source_page_file": "wordpress-deploy/pages/review-amerisleep-as3-gutenberg.html",
    "source_schema_file": "wordpress-deploy/schema/review-amerisleep-as3-schema.json"
  }
]
JSON, true);

if (!is_array($pages)) {
    fwrite(STDERR, "Could not decode embedded Simply Rest page payload.
");
    exit(1);
}

function sr_phase1_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::line($message);
    } else {
        echo $message . PHP_EOL;
    }
}

function sr_phase1_find_page_by_path($path) {
    $path = trim((string) $path, '/');
    if ($path === '') {
        return null;
    }
    $page = get_page_by_path($path, OBJECT, 'page');
    return $page instanceof WP_Post ? $page : null;
}

function sr_phase1_find_parent_id($parent_path) {
    $parent_path = trim((string) $parent_path, '/');
    if ($parent_path === '') {
        return 0;
    }
    $parent = sr_phase1_find_page_by_path($parent_path);
    return $parent ? (int) $parent->ID : 0;
}

function sr_phase1_managed_meta_keys() {
    return array(
        '_simplyrest_phase1_target_url',
        '_simplyrest_full_slug',
        '_simplyrest_author_display',
        '_simplyrest_lead_tester',
        '_simplyrest_json_ld',
        '_simplyrest_source_page_file',
        '_simplyrest_source_schema_file',
        '_simplyrest_phase1_last_imported_at',
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_canonical',
    );
}

function sr_phase1_capture_meta_value($post_id, $key) {
    $exists = metadata_exists('post', $post_id, $key);
    return array(
        'exists' => $exists,
        'value' => $exists ? get_post_meta($post_id, $key, true) : null,
    );
}

function sr_phase1_save_pre_import_snapshot($post_id, $page) {
    global $skip_snapshot;

    if ($skip_snapshot) {
        sr_phase1_log("SKIP snapshot for /" . $page['path'] . "/ because --skip-snapshot was passed.");
        return;
    }

    $existing_snapshot = get_post_meta($post_id, '_simplyrest_phase1_pre_import_snapshot', true);
    if (is_string($existing_snapshot) && trim($existing_snapshot) !== '') {
        sr_phase1_log("Snapshot already exists for /" . $page['path'] . "/ post ID " . $post_id . "; preserving first baseline.");
        return;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        sr_phase1_log("WARN could not snapshot /" . $page['path'] . "/ because post ID " . $post_id . " was not found.");
        return;
    }

    $meta = array();
    foreach (sr_phase1_managed_meta_keys() as $key) {
        $meta[$key] = sr_phase1_capture_meta_value($post_id, $key);
    }

    $snapshot = array(
        'snapshot_version' => 1,
        'created_at' => gmdate('c'),
        'page_path' => '/' . trim($page['path'], '/') . '/',
        'post' => array(
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post->post_status,
            'post_parent' => (int) $post->post_parent,
            'menu_order' => (int) $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
        ),
        'meta' => $meta,
    );

    update_post_meta($post_id, '_simplyrest_phase1_pre_import_snapshot', wp_slash(wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)));
    update_post_meta($post_id, '_simplyrest_phase1_pre_import_snapshot_created_at', $snapshot['created_at']);
    sr_phase1_log("Snapshot saved for /" . $page['path'] . "/ post ID " . $post_id . ".");
}

function sr_phase1_update_meta($post_id, $page) {
    update_post_meta($post_id, '_simplyrest_phase1_last_imported_at', gmdate('c'));
    update_post_meta($post_id, '_simplyrest_phase1_target_url', $page['canonical']);
    update_post_meta($post_id, '_simplyrest_full_slug', '/' . trim($page['path'], '/') . '/');
    update_post_meta($post_id, '_simplyrest_author_display', $page['author_display']);
    update_post_meta($post_id, '_simplyrest_lead_tester', $page['lead_tester']);
    update_post_meta($post_id, '_simplyrest_json_ld', wp_slash(wp_json_encode($page['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)));
    update_post_meta($post_id, '_simplyrest_source_page_file', $page['source_page_file']);
    update_post_meta($post_id, '_simplyrest_source_schema_file', $page['source_schema_file']);

    // Common Yoast fields. If a different SEO stack is active, copy these values into that plugin's fields.
    update_post_meta($post_id, '_yoast_wpseo_title', $page['meta_title']);
    update_post_meta($post_id, '_yoast_wpseo_metadesc', $page['meta_description']);
    update_post_meta($post_id, '_yoast_wpseo_canonical', $page['canonical']);
}

function sr_phase1_mark_created_by_import($post_id, $page) {
    update_post_meta($post_id, '_simplyrest_phase1_created_by_import', '1');
    update_post_meta($post_id, '_simplyrest_phase1_created_by_import_at', gmdate('c'));
    update_post_meta($post_id, '_simplyrest_phase1_created_by_import_path', '/' . trim($page['path'], '/') . '/');
}

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($pages as $page) {
    $existing = sr_phase1_find_page_by_path($page['path']);
    $parent_id = sr_phase1_find_parent_id($page['parent_path']);

    if ($page['parent_path'] && !$parent_id) {
        sr_phase1_log("WARN parent page not found for /" . $page['path'] . "/; expected parent /" . $page['parent_path'] . "/. Import order may be wrong or parent page is not a page.");
    }

    $postarr = array(
        'post_type' => 'page',
        'post_title' => $page['title'],
        'post_name' => $page['slug'],
        'post_content' => $page['content'],
        'post_excerpt' => $page['meta_description'],
        'post_status' => $target_status,
        'post_parent' => $parent_id,
        'menu_order' => (int) $page['menu_order'],
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    );

    if ($existing) {
        if ($existing->post_status === 'publish' && !$force_update_published) {
            $skipped++;
            sr_phase1_log("SKIP published page /" . $page['path'] . "/ exists as post ID " . $existing->ID . ". Re-run with --force-update-published only after approval.");
            continue;
        }
        $postarr['ID'] = (int) $existing->ID;
        if ($dry_run) {
            sr_phase1_log("DRY RUN update /" . $page['path'] . "/ post ID " . $existing->ID . " as " . $target_status);
            continue;
        }
        sr_phase1_save_pre_import_snapshot((int) $existing->ID, $page);
        $post_id = wp_update_post($postarr, true);
        if (is_wp_error($post_id)) {
            sr_phase1_log("ERROR updating /" . $page['path'] . "/: " . $post_id->get_error_message());
            continue;
        }
        sr_phase1_update_meta((int) $post_id, $page);
        $updated++;
        sr_phase1_log("UPDATED /" . $page['path'] . "/ post ID " . $post_id . " as " . $target_status);
    } else {
        if ($dry_run) {
            sr_phase1_log("DRY RUN create /" . $page['path'] . "/ as " . $target_status);
            continue;
        }
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) {
            sr_phase1_log("ERROR creating /" . $page['path'] . "/: " . $post_id->get_error_message());
            continue;
        }
        sr_phase1_update_meta((int) $post_id, $page);
        sr_phase1_mark_created_by_import((int) $post_id, $page);
        $created++;
        sr_phase1_log("CREATED /" . $page['path'] . "/ post ID " . $post_id . " as " . $target_status);
    }
}

sr_phase1_log("Done. Created: " . $created . "; Updated: " . $updated . "; Skipped: " . $skipped . "; Dry run: " . ($dry_run ? 'yes' : 'no') . ".");
