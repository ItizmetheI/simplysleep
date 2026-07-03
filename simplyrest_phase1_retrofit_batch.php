<?php
/**
 * Simply Rest retrofit batch staging script.
 *
 * Run from the WordPress root with WP-CLI after the five phase-one proof pages
 * are published and verified.
 *
 * Examples:
 *
 *   wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --dry-run --limit=20
 *   wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0,P1 --dry-run
 *   wp eval-file simplyrest_phase1_retrofit_batch.php -- --slug-file=simplyrest-retrofit-slug-manifest-2026-06-25.tsv --priority=P0 --update-pages
 *
 * Defaults:
 * - Dry-run/reporting only unless --update-pages is passed.
 * - Skips published pages unless --force-update-published is passed.
 * - Adds a bounded Simply Rest retrofit module with author, tester,
 *   methodology, disclosure, FAQ, and claim-safe language.
 * - Stores retrofit JSON-LD separately in _simplyrest_retrofit_json_ld.
 * - Never changes canonical URLs or redirects.
 *
 * Optional flags:
 * - --slug-file=PATH: TSV manifest of live URL slugs and priorities.
 * - --brief-dir=PATH: optional folder containing the 122 retrofit .md briefs.
 * - --priority=P0,P1,P1-claim-sensitive,P2,P3: comma-separated priority filter.
 * - --limit=N: process only N briefs.
 * - --offset=N: skip the first N matching briefs.
 * - --dry-run: report actions without writing. This is the default unless --update-pages is passed.
 * - --update-pages: write content/meta updates.
 * - --force-update-published: allow updates to already-published pages.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_retrofit_batch.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$update_pages = in_array('--update-pages', $args, true);
$dry_run = in_array('--dry-run', $args, true) || !$update_pages;
$force_update_published = in_array('--force-update-published', $args, true);
$slug_file = simplyrest_retrofit_resolve_path(simplyrest_retrofit_arg_value($args, '--slug-file', 'simplyrest-retrofit-slug-manifest-2026-06-25.tsv'));
$brief_dir_arg = simplyrest_retrofit_arg_value($args, '--brief-dir', '');
$brief_dir = $brief_dir_arg === '' ? '' : simplyrest_retrofit_resolve_path($brief_dir_arg);
$priority_filter_raw = simplyrest_retrofit_arg_value($args, '--priority', '');
$limit = max(0, (int) simplyrest_retrofit_arg_value($args, '--limit', '0'));
$offset = max(0, (int) simplyrest_retrofit_arg_value($args, '--offset', '0'));

$priority_filter = array();
if ($priority_filter_raw !== '') {
    foreach (explode(',', $priority_filter_raw) as $priority) {
        $priority = trim($priority);
        if ($priority !== '') {
            $priority_filter[] = $priority;
        }
    }
}

$briefs = array();
if (is_file($slug_file)) {
    $briefs = simplyrest_retrofit_read_slug_manifest($slug_file);
} elseif ($brief_dir !== '' && is_dir($brief_dir)) {
    $briefs = simplyrest_retrofit_read_brief_dir($brief_dir);
} else {
    simplyrest_retrofit_fail('Provide --slug-file or --brief-dir. Missing slug file: ' . $slug_file);
}

$matched = 0;
$processed = 0;
$updated = 0;
$skipped = 0;
$missing = 0;

foreach ($briefs as $brief) {
    $slug = $brief['slug'];
    if (!empty($priority_filter) && !in_array($brief['priority'], $priority_filter, true)) {
        continue;
    }

    if ($matched++ < $offset) {
        continue;
    }

    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    $processed++;
    $post = simplyrest_retrofit_find_post($slug);
    if (!$post) {
        $missing++;
        fwrite(STDOUT, "MISSING\t{$brief['priority']}\t{$slug}\tNo matching WordPress post/page found\n");
        continue;
    }

    $post_id = (int) $post->ID;
    $already_retrofitted = strpos($post->post_content, '<!-- SR-RETROFIT-MODULE-START -->') !== false;
    $will_skip_published = $post->post_status === 'publish' && !$force_update_published;

    if ($already_retrofitted) {
        $skipped++;
        fwrite(STDOUT, "SKIP\t{$brief['priority']}\t{$slug}\tpost_id={$post_id}\talready_retrofitted=yes\n");
        continue;
    }

    if ($will_skip_published && !$dry_run) {
        $skipped++;
        fwrite(STDOUT, "SKIP\t{$brief['priority']}\t{$slug}\tpost_id={$post_id}\tpublished_requires_force=yes\n");
        continue;
    }

    if ($dry_run) {
        $action = $will_skip_published ? 'would_skip_published_without_force' : 'would_update';
        fwrite(STDOUT, "DRY-RUN\t{$brief['priority']}\t{$slug}\tpost_id={$post_id}\t{$action}\tpage_type={$brief['page_type']}\n");
        continue;
    }

    $module = simplyrest_retrofit_build_module($brief, $post);
    $schema = simplyrest_retrofit_build_schema($brief, $post);
    $new_content = simplyrest_retrofit_insert_module($post->post_content, $module);

    $result = wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $new_content,
    ), true);

    if (is_wp_error($result)) {
        fwrite(STDOUT, "ERROR\t{$brief['priority']}\t{$slug}\tpost_id={$post_id}\t" . $result->get_error_message() . "\n");
        continue;
    }

    update_post_meta($post_id, '_simplyrest_retrofit_brief_slug', $slug);
    update_post_meta($post_id, '_simplyrest_retrofit_priority', $brief['priority']);
    update_post_meta($post_id, '_simplyrest_retrofit_page_type', $brief['page_type']);
    update_post_meta($post_id, '_simplyrest_retrofit_claim_sensitive', $brief['claim_sensitive'] ? 'yes' : 'no');
    update_post_meta($post_id, '_simplyrest_retrofit_json_ld', wp_slash(wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)));
    update_post_meta($post_id, '_simplyrest_retrofit_applied_at', gmdate('c'));

    $updated++;
    fwrite(STDOUT, "UPDATED\t{$brief['priority']}\t{$slug}\tpost_id={$post_id}\tpage_type={$brief['page_type']}\n");
}

fwrite(STDOUT, "Done. processed={$processed} updated={$updated} skipped={$skipped} missing={$missing} dry_run=" . ($dry_run ? 'yes' : 'no') . "\n");

function simplyrest_retrofit_arg_value($args, $name, $default) {
    foreach ($args as $arg) {
        if (strpos($arg, $name . '=') === 0) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return $default;
}

function simplyrest_retrofit_resolve_path($path) {
    if (!is_string($path) || $path === '') {
        return '';
    }

    if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
        return $path;
    }

    return getcwd() . DIRECTORY_SEPARATOR . $path;
}

function simplyrest_retrofit_fail($message) {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function simplyrest_retrofit_read_slug_manifest($slug_file) {
    $lines = file($slug_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || count($lines) < 2) {
        simplyrest_retrofit_fail('Slug manifest must contain a header and at least one row: ' . $slug_file);
    }

    $headers = str_getcsv(array_shift($lines), "\t");
    $required = array('slug', 'page_type', 'priority', 'claim_sensitive');
    foreach ($required as $column) {
        if (!in_array($column, $headers, true)) {
            simplyrest_retrofit_fail('Slug manifest is missing required column: ' . $column);
        }
    }

    $briefs = array();
    foreach ($lines as $line_number => $line) {
        $fields = str_getcsv($line, "\t");
        if (count($fields) !== count($headers)) {
            simplyrest_retrofit_fail('Slug manifest column mismatch on row ' . ($line_number + 2));
        }

        $row = array_combine($headers, $fields);
        if (!$row || empty($row['slug'])) {
            simplyrest_retrofit_fail('Invalid slug manifest row ' . ($line_number + 2));
        }

        $briefs[] = array(
            'slug' => $row['slug'],
            'brief_file' => isset($row['brief_file']) ? $row['brief_file'] : '',
            'page_type' => $row['page_type'],
            'priority' => $row['priority'],
            'claim_sensitive' => strtolower($row['claim_sensitive']) === 'yes',
        );
    }

    return $briefs;
}

function simplyrest_retrofit_read_brief_dir($brief_dir) {
    $brief_files = glob(trailingslashit($brief_dir) . '*.md');
    if (!is_array($brief_files) || empty($brief_files)) {
        simplyrest_retrofit_fail('No .md retrofit briefs found in ' . $brief_dir);
    }

    sort($brief_files, SORT_STRING);

    $briefs = array();
    foreach ($brief_files as $brief_file) {
        $slug = basename($brief_file, '.md');
        $briefs[] = simplyrest_retrofit_parse_brief($brief_file, $slug);
    }

    return $briefs;
}

function simplyrest_retrofit_parse_brief($brief_file, $slug) {
    $content = file_get_contents($brief_file);
    if (!is_string($content)) {
        $content = '';
    }

    $page_type = simplyrest_retrofit_extract_line_value($content, 'Page type');
    if ($page_type === '') {
        $page_type = simplyrest_retrofit_guess_page_type($slug);
    }

    $claim_sensitive = (bool) preg_match('/pain|reflux|snor|kidney|oxygen|sleep-quality|restless|jet-lag|health|wellness|recovery|acid/i', $slug . ' ' . $content);
    $priority = simplyrest_retrofit_guess_priority($slug, $page_type, $claim_sensitive);

    return array(
        'slug' => $slug,
        'brief_file' => $brief_file,
        'page_type' => $page_type,
        'priority' => $priority,
        'claim_sensitive' => $claim_sensitive,
    );
}

function simplyrest_retrofit_extract_line_value($content, $label) {
    $pattern = '/^- ' . preg_quote($label, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $content, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function simplyrest_retrofit_guess_page_type($slug) {
    if (strpos($slug, '-vs-') !== false || strpos($slug, 'comparison') !== false) {
        return 'comparison';
    }

    if (strpos($slug, 'mattress-store') !== false || preg_match('/gilbert|denver|fort-worth|austin|dallas|friendswood|glendale|houston|katy|littleton|mesa|phoenix|portland|tucson|lone-tree/i', $slug)) {
        return 'local';
    }

    if (preg_match('/sale|deals|black-friday|cyber-monday|labor-day|4th-july/i', $slug)) {
        return 'sales';
    }

    if (strpos($slug, 'best-') === 0) {
        return 'best-of';
    }

    return 'guide';
}

function simplyrest_retrofit_guess_priority($slug, $page_type, $claim_sensitive) {
    $p0 = array(
        'best-online-mattress',
        'best-mattress',
        'mattress-comparison',
        'alternative-mattress-reviews',
    );

    if (in_array($slug, $p0, true)) {
        return 'P0';
    }

    if ($claim_sensitive) {
        return 'P1-claim-sensitive';
    }

    if ($page_type === 'best-of' || $page_type === 'comparison') {
        return 'P1';
    }

    if ($page_type === 'local' || $page_type === 'sales') {
        return 'P2';
    }

    return 'P3';
}

function simplyrest_retrofit_find_post($slug) {
    $post = get_page_by_path($slug, OBJECT, array('page', 'post'));
    if ($post instanceof WP_Post) {
        return $post;
    }

    $posts = get_posts(array(
        'name' => $slug,
        'post_type' => array('page', 'post'),
        'post_status' => 'any',
        'posts_per_page' => 1,
    ));

    if (is_array($posts) && !empty($posts[0]) && $posts[0] instanceof WP_Post) {
        return $posts[0];
    }

    return null;
}

function simplyrest_retrofit_build_module($brief, $post) {
    $title = get_the_title($post);
    $claim_note = $brief['claim_sensitive']
        ? "\n<!-- wp:paragraph -->\n<p><strong>Health note:</strong> This page is shopping and education guidance. Mattress comfort, support, pressure relief, and sleep-position fit are not substitutes for medical care, and Simply Rest does not diagnose, treat, cure, or guarantee relief for pain, sleep disorders, or medical conditions.</p>\n<!-- /wp:paragraph -->\n"
        : '';

    return <<<HTML
<!-- SR-RETROFIT-MODULE-START -->
<!-- wp:group {"className":"sr-retrofit-proof-module"} -->
<div class="wp-block-group sr-retrofit-proof-module">
<!-- wp:paragraph -->
<p><strong>By Firdous "Ferdie" Farhad</strong> | <strong>Lead hands-on tester: Firdous "Ferdie" Farhad</strong> | <strong>Simply Rest Lab retrofit status:</strong> {$brief['priority']}</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Quick answer:</strong> {$title} has been reviewed through the Simply Rest Lab framework so readers can compare recommendations against testing categories such as pressure relief, sleep-position fit, motion isolation, cooling, edge support, response, policy, and value.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>How we evaluate:</strong> Simply Rest links recommendations back to hands-on testing, product facts, scorecard inputs, and visible evidence where available. See <a href="https://simplyrest.com/mattress-lab/">Simply Rest Lab</a> and <a href="https://simplyrest.com/how-we-test-mattresses/">How We Test Mattresses</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Affiliate disclosure:</strong> Simply Rest may earn a commission when readers buy through some links. Commercial relationships should not change the testing framework, methodology links, or evidence standards used for recommendations.</p>
<!-- /wp:paragraph -->{$claim_note}
<!-- wp:heading -->
<h2>Testing and Methodology FAQ</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>How does Simply Rest evaluate this topic?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Simply Rest uses the same mattress testing framework across commercial guides, reviews, and comparisons: support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Who is the lead hands-on tester?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Firdous "Ferdie" Farhad is the Simply Rest author and lead hands-on tester for mattress testing content.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Where can I see the testing process?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The testing process is documented on <a href="https://simplyrest.com/how-we-test-mattresses/">How We Test Mattresses</a>, with the broader proof layer on <a href="https://simplyrest.com/mattress-lab/">Simply Rest Lab</a>.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- SR-RETROFIT-MODULE-END -->
HTML;
}

function simplyrest_retrofit_insert_module($content, $module) {
    $content = (string) $content;
    $h1_close = strpos($content, '<!-- /wp:heading -->');
    if ($h1_close !== false) {
        $h1_close += strlen('<!-- /wp:heading -->');
        return substr($content, 0, $h1_close) . "\n\n" . trim($module) . "\n\n" . substr($content, $h1_close);
    }

    return trim($module) . "\n\n" . $content;
}

function simplyrest_retrofit_build_schema($brief, $post) {
    $url = get_permalink($post);
    $title = get_the_title($post);
    $description = $title . ' reviewed with Simply Rest Lab methodology, author attribution, disclosure, and testing framework links.';

    return array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => 'Article',
                '@id' => trailingslashit($url) . '#article',
                'headline' => $title,
                'description' => $description,
                'author' => array('@id' => 'https://simplyrest.com/ferdie-farhad/#person'),
                'publisher' => array('@id' => 'https://simplyrest.com/#organization'),
                'mainEntityOfPage' => array('@id' => trailingslashit($url) . '#webpage'),
            ),
            array(
                '@type' => 'FAQPage',
                '@id' => trailingslashit($url) . '#faq',
                'mainEntity' => array(
                    array(
                        '@type' => 'Question',
                        'name' => 'How does Simply Rest evaluate this topic?',
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text' => 'Simply Rest uses a mattress testing framework across commercial guides, reviews, and comparisons: support and sleep fit, value, edge support, motion transfer, cooling and breathability, response time, and trial policy.',
                        ),
                    ),
                    array(
                        '@type' => 'Question',
                        'name' => 'Who is the lead hands-on tester?',
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text' => 'Firdous "Ferdie" Farhad is the Simply Rest author and lead hands-on tester for mattress testing content.',
                        ),
                    ),
                    array(
                        '@type' => 'Question',
                        'name' => 'Where can I see the testing process?',
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text' => 'The testing process is documented on How We Test Mattresses, with the broader proof layer on Simply Rest Lab.',
                        ),
                    ),
                ),
            ),
            array(
                '@type' => 'BreadcrumbList',
                '@id' => trailingslashit($url) . '#breadcrumb',
                'itemListElement' => array(
                    array(
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => 'https://simplyrest.com/',
                    ),
                    array(
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $title,
                        'item' => $url,
                    ),
                ),
            ),
            array(
                '@type' => 'Person',
                '@id' => 'https://simplyrest.com/ferdie-farhad/#person',
                'name' => 'Firdous Farhad',
                'alternateName' => 'Ferdie Farhad',
                'url' => 'https://simplyrest.com/ferdie-farhad/',
                'jobTitle' => 'Simply Rest Lab Lead Hands-On Tester and Author',
                'worksFor' => array('@id' => 'https://simplyrest.com/#organization'),
            ),
            array(
                '@type' => 'Organization',
                '@id' => 'https://simplyrest.com/#organization',
                'name' => 'Simply Rest',
                'url' => 'https://simplyrest.com/',
            ),
        ),
    );
}
