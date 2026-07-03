<?php
/**
 * Simply Rest Phase 1 launch gate.
 *
 * Run from the WordPress root with WP-CLI after staging/import work:
 *
 *   wp eval-file simplyrest_phase1_launch_gate.php
 *   wp eval-file simplyrest_phase1_launch_gate.php -- --allow-drafts --skip-http
 *   wp eval-file simplyrest_phase1_launch_gate.php -- --format=json
 *
 * This script is read-only. It does not create pages, publish content, upload
 * media, change redirects, or edit schema. It exits non-zero when the current
 * WordPress/live state is not ready for launch approval.
 *
 * Optional flags:
 * - --allow-drafts: accept draft/private/pending pages for staging review.
 * - --skip-http: skip live HTTP status/redirect checks.
 * - --format=json: print JSON rows instead of the text report.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_launch_gate.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$allow_drafts = in_array('--allow-drafts', $args, true);
$skip_http = in_array('--skip-http', $args, true);
$format_json = in_array('--format=json', $args, true);

$sr_gate_rows = array();

$target_pages = array(
    array(
        'name' => 'Mattress Lab',
        'path' => 'mattress-lab',
        'url' => 'https://simplyrest.com/mattress-lab/',
        'schema_types' => array('WebPage', 'Article', 'FAQPage', 'BreadcrumbList', 'Person', 'Organization'),
        'markers' => array('Simply Rest Lab', 'Ferdie', 'lead hands-on tester', 'affiliate disclosure', 'not medical advice'),
        'links' => array('https://simplyrest.com/how-we-test-mattresses/', 'https://simplyrest.com/ferdie-farhad/', 'https://simplyrest.com/mattress-reviews/'),
    ),
    array(
        'name' => 'How We Test Mattresses',
        'path' => 'how-we-test-mattresses',
        'url' => 'https://simplyrest.com/how-we-test-mattresses/',
        'schema_types' => array('WebPage', 'Article', 'FAQPage', 'BreadcrumbList', 'Person', 'Organization'),
        'markers' => array('How We Test Mattresses', 'pressure relief', 'spinal alignment', 'motion isolation', 'edge support', 'affiliate disclosure', 'not to provide medical advice'),
        'links' => array('https://simplyrest.com/how-we-test-mattresses/'),
    ),
    array(
        'name' => 'Ferdie Farhad',
        'path' => 'ferdie-farhad',
        'url' => 'https://simplyrest.com/ferdie-farhad/',
        'schema_types' => array('ProfilePage', 'Person', 'FAQPage', 'BreadcrumbList', 'Organization'),
        'markers' => array('Ferdie', 'lead hands-on tester', 'author', 'affiliate disclosure', 'medical review'),
        'links' => array('https://simplyrest.com/how-we-test-mattresses/'),
        'requires_visible_media' => true,
    ),
    array(
        'name' => 'Mattress Reviews Hub',
        'path' => 'mattress-reviews',
        'url' => 'https://simplyrest.com/mattress-reviews/',
        'schema_types' => array('CollectionPage', 'ItemList', 'FAQPage', 'BreadcrumbList', 'Person', 'Organization'),
        'markers' => array('Mattress Reviews', 'Simply Rest Lab', 'Amerisleep AS3', 'lead hands-on tester', 'affiliate disclosure', 'not medical advice'),
        'links' => array('https://simplyrest.com/mattress-reviews/amerisleep-as3/', 'https://simplyrest.com/how-we-test-mattresses/'),
    ),
    array(
        'name' => 'Amerisleep AS3 Review',
        'path' => 'mattress-reviews/amerisleep-as3',
        'url' => 'https://simplyrest.com/mattress-reviews/amerisleep-as3/',
        'schema_types' => array('WebPage', 'Review', 'Product', 'Article', 'FAQPage', 'BreadcrumbList', 'Person', 'Organization'),
        'markers' => array('Amerisleep AS3', 'Simply Rest Lab Score', 'Testing Evidence', 'Ferdie', 'first-hand', 'affiliate disclosure', 'not medical advice'),
        'links' => array('https://simplyrest.com/how-we-test-mattresses/'),
        'requires_as3_media' => true,
    ),
);

foreach ($target_pages as $target_page) {
    simplyrest_gate_check_page($target_page, $allow_drafts);
}

simplyrest_gate_check_schema_renderer();
simplyrest_gate_check_rollback_helper_file();
simplyrest_gate_check_redirect_records();

if (!$skip_http) {
    foreach ($target_pages as $target_page) {
        simplyrest_gate_check_live_url($target_page);
    }
} else {
    simplyrest_gate_add('warn', 'live_http', 'http checks', 'Skipped because --skip-http was passed.');
}

$failures = array_values(array_filter($sr_gate_rows, 'simplyrest_gate_is_failure'));

if ($format_json) {
    echo wp_json_encode(array(
        'overall_pass' => empty($failures) ? 'yes' : 'no',
        'failures' => count($failures),
        'rows' => $sr_gate_rows,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    simplyrest_gate_print_text($sr_gate_rows, $failures);
}

exit(empty($failures) ? 0 : 1);

function simplyrest_gate_check_page($target_page, $allow_drafts) {
    $post = get_page_by_path($target_page['path'], OBJECT, array('page', 'post'));
    $area = $target_page['path'];

    simplyrest_gate_pass_if($post instanceof WP_Post, $area, 'page exists', 'Found page/post.', 'Missing page/post.');
    if (!$post instanceof WP_Post) {
        return;
    }

    $accepted_statuses = $allow_drafts ? array('publish', 'draft', 'pending', 'private') : array('publish');
    simplyrest_gate_pass_if(
        in_array($post->post_status, $accepted_statuses, true),
        $area,
        'post status',
        'Status is ' . $post->post_status . '.',
        'Status is ' . $post->post_status . '; expected ' . implode(' or ', $accepted_statuses) . '.'
    );

    $permalink = get_permalink($post);
    if (!$allow_drafts) {
        simplyrest_gate_pass_if(
            simplyrest_gate_normalize_url($permalink) === simplyrest_gate_normalize_url($target_page['url']),
            $area,
            'permalink',
            'Permalink matches expected URL.',
            'Expected ' . $target_page['url'] . '; got ' . $permalink . '.'
        );
    } else {
        simplyrest_gate_add('warn', $area, 'permalink', 'Skipped strict permalink check because --allow-drafts was passed.');
    }

    $canonical = (string) get_post_meta((int) $post->ID, '_yoast_wpseo_canonical', true);
    simplyrest_gate_pass_if(
        simplyrest_gate_normalize_url($canonical) === simplyrest_gate_normalize_url($target_page['url']),
        $area,
        'canonical meta',
        'Yoast canonical matches expected URL.',
        'Expected ' . $target_page['url'] . '; got ' . ($canonical === '' ? '<empty>' : $canonical) . '.'
    );

    $content = (string) $post->post_content;
    $last_imported_at = (string) get_post_meta((int) $post->ID, '_simplyrest_phase1_last_imported_at', true);
    $pre_import_snapshot = (string) get_post_meta((int) $post->ID, '_simplyrest_phase1_pre_import_snapshot', true);
    $created_by_import = (string) get_post_meta((int) $post->ID, '_simplyrest_phase1_created_by_import', true);
    simplyrest_gate_pass_if(
        $last_imported_at !== '',
        $area,
        'import marker',
        'Page has _simplyrest_phase1_last_imported_at.',
        'Missing _simplyrest_phase1_last_imported_at; page may not have been imported by the phase-one script.'
    );
    simplyrest_gate_pass_if(
        $pre_import_snapshot !== '' || $created_by_import === '1',
        $area,
        'rollback marker',
        'Page has a pre-import snapshot or importer-created marker.',
        'Missing rollback marker; expected _simplyrest_phase1_pre_import_snapshot or _simplyrest_phase1_created_by_import.'
    );

    foreach ($target_page['markers'] as $marker) {
        simplyrest_gate_pass_if(
            simplyrest_gate_contains($content, $marker),
            $area,
            'content marker: ' . $marker,
            'Present.',
            'Missing marker.'
        );
    }

    foreach ($target_page['links'] as $link) {
        simplyrest_gate_pass_if(
            strpos($content, $link) !== false,
            $area,
            'required link: ' . $link,
            'Present.',
            'Missing link.'
        );
    }

    $forbidden_markers = array('drive.google.com', 'docs.google.com', 'It seems we can', '/best-online-mattress/');
    foreach ($forbidden_markers as $marker) {
        simplyrest_gate_pass_if(
            !simplyrest_gate_contains($content, $marker),
            $area,
            'forbidden marker: ' . $marker,
            'Not found.',
            'Found forbidden marker.'
        );
    }

    simplyrest_gate_check_schema_meta($area, (int) $post->ID, $target_page, $content);

    if (!empty($target_page['requires_visible_media'])) {
        simplyrest_gate_check_visible_media($area, $post);
    }

    if (!empty($target_page['requires_as3_media'])) {
        simplyrest_gate_check_as3_media($area, $post);
    }
}

function simplyrest_gate_check_schema_meta($area, $post_id, $target_page, $content) {
    $raw_schema = (string) get_post_meta($post_id, '_simplyrest_json_ld', true);
    simplyrest_gate_pass_if($raw_schema !== '', $area, 'schema meta exists', 'Found _simplyrest_json_ld.', 'Missing _simplyrest_json_ld.');
    if ($raw_schema === '') {
        return;
    }

    $schema = json_decode($raw_schema, true);
    simplyrest_gate_pass_if(is_array($schema) && json_last_error() === JSON_ERROR_NONE, $area, 'schema json parses', 'Valid JSON.', 'Invalid JSON: ' . json_last_error_msg() . '.');
    if (!is_array($schema)) {
        return;
    }

    simplyrest_gate_pass_if(
        isset($schema['@context']) && $schema['@context'] === 'https://schema.org',
        $area,
        'schema context',
        'Uses schema.org context.',
        'Missing schema.org context.'
    );

    $types = array();
    simplyrest_gate_collect_schema_types($schema, $types);
    $missing_types = array_values(array_diff($target_page['schema_types'], array_keys($types)));
    simplyrest_gate_pass_if(
        empty($missing_types),
        $area,
        'schema type coverage',
        'Required schema types present: ' . implode(', ', $target_page['schema_types']) . '.',
        'Missing schema types: ' . implode(', ', $missing_types) . '.'
    );

    $schema_text = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    simplyrest_gate_pass_if(
        strpos($schema_text, 'https://simplyrest.com/ferdie-farhad/#person') !== false,
        $area,
        'schema Ferdie entity',
        'References Ferdie Person entity.',
        'Missing Ferdie Person entity reference.'
    );
    simplyrest_gate_pass_if(
        strpos($schema_text, 'https://simplyrest.com/#organization') !== false,
        $area,
        'schema organization entity',
        'References Simply Rest Organization entity.',
        'Missing Simply Rest Organization entity reference.'
    );

    if (!empty($target_page['requires_as3_media'])) {
        simplyrest_gate_pass_if(
            strpos($schema_text, 'https://simplyrest.com/mattress-reviews/amerisleep-as3/#review') !== false &&
            strpos($schema_text, 'https://simplyrest.com/mattress-reviews/amerisleep-as3/#product') !== false &&
            strpos($schema_text, '"Amerisleep"') !== false,
            $area,
            'schema AS3 review/product relationship',
            'Review, Product, and Amerisleep brand are present.',
            'Missing AS3 Review/Product/Amerisleep relationship.'
        );
    }

    simplyrest_gate_check_schema_entity_relationships($area, $schema, $target_page, $content);
}

function simplyrest_gate_check_schema_entity_relationships($area, $schema, $target_page, $content) {
    $nodes = simplyrest_gate_schema_nodes($schema);
    simplyrest_gate_pass_if(!empty($nodes), $area, 'schema graph nodes', 'Schema graph nodes are present.', 'No schema graph nodes found.');
    if (empty($nodes)) {
        return;
    }

    $canonical = $target_page['url'];
    $webpage_id = simplyrest_gate_schema_node_id_for_url($canonical, 'webpage');
    $person_id = 'https://simplyrest.com/ferdie-farhad/#person';
    $organization_id = 'https://simplyrest.com/#organization';
    $content_lower = strtolower((string) $content);

    $ids = array();
    foreach ($nodes as $node) {
        if (is_array($node) && isset($node['@id']) && $node['@id'] !== '') {
            $ids[] = (string) $node['@id'];
        }
    }
    $duplicate_ids = array();
    foreach (array_count_values($ids) as $id => $count) {
        if ($count > 1) {
            $duplicate_ids[] = $id;
        }
    }
    simplyrest_gate_pass_if(empty($duplicate_ids), $area, 'schema duplicate ids', 'No duplicate schema @id values.', 'Duplicate schema @id values: ' . implode(', ', $duplicate_ids) . '.');

    $page_type = simplyrest_gate_schema_page_node_type($target_page['path']);
    $page_nodes = simplyrest_gate_schema_nodes_by_type($nodes, $page_type);
    simplyrest_gate_pass_if(!empty($page_nodes), $area, 'schema ' . $page_type . ' node', $page_type . ' node is present.', 'Missing ' . $page_type . ' node.');
    if (!empty($page_nodes)) {
        $page_node = $page_nodes[0];
        simplyrest_gate_pass_if((string) ($page_node['@id'] ?? '') === $webpage_id, $area, 'schema page node id', 'Page node @id matches canonical #webpage.', 'Expected ' . $webpage_id . '; got ' . (string) ($page_node['@id'] ?? '<empty>') . '.');
        simplyrest_gate_pass_if(simplyrest_gate_normalize_url(simplyrest_gate_schema_node_url($page_node)) === simplyrest_gate_normalize_url($canonical), $area, 'schema page node url', 'Page node URL matches canonical.', 'Expected ' . $canonical . '; got ' . simplyrest_gate_schema_node_url($page_node) . '.');
    }

    $person_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'Person');
    simplyrest_gate_pass_if(!empty($person_nodes), $area, 'schema Person node', 'Person node is present.', 'Missing Person node.');
    if (!empty($person_nodes)) {
        $person = $person_nodes[0];
        simplyrest_gate_pass_if((string) ($person['@id'] ?? '') === $person_id, $area, 'schema Person id', 'Person @id matches Ferdie.', 'Expected ' . $person_id . '; got ' . (string) ($person['@id'] ?? '<empty>') . '.');
        simplyrest_gate_pass_if(stripos((string) ($person['name'] ?? ''), 'Firdous') !== false, $area, 'schema Person name', 'Person name includes Firdous.', 'Person name missing Firdous.');
        simplyrest_gate_pass_if(stripos((string) ($person['alternateName'] ?? ''), 'Ferdie') !== false, $area, 'schema Person alternateName', 'Person alternateName includes Ferdie.', 'Person alternateName missing Ferdie.');
        simplyrest_gate_pass_if(stripos((string) ($person['jobTitle'] ?? ''), 'lead hands-on tester') !== false, $area, 'schema Person jobTitle', 'Person jobTitle includes lead hands-on tester.', 'Person jobTitle missing lead hands-on tester.');
    }

    $organization_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'Organization');
    simplyrest_gate_pass_if(!empty($organization_nodes), $area, 'schema Organization node', 'Organization node is present.', 'Missing Organization node.');
    if (!empty($organization_nodes)) {
        $organization = $organization_nodes[0];
        simplyrest_gate_pass_if((string) ($organization['@id'] ?? '') === $organization_id, $area, 'schema Organization id', 'Organization @id matches Simply Rest.', 'Expected ' . $organization_id . '; got ' . (string) ($organization['@id'] ?? '<empty>') . '.');
        simplyrest_gate_pass_if(simplyrest_gate_normalize_url(simplyrest_gate_schema_node_url($organization)) === 'https://simplyrest.com/', $area, 'schema Organization url', 'Organization URL matches Simply Rest.', 'Expected https://simplyrest.com/; got ' . simplyrest_gate_schema_node_url($organization) . '.');
    }

    foreach (simplyrest_gate_schema_nodes_by_type($nodes, 'Article') as $article) {
        simplyrest_gate_pass_if(simplyrest_gate_schema_ref_id($article['author'] ?? '') === $person_id, $area, 'schema Article author', 'Article author references Ferdie.', 'Article author does not reference Ferdie.');
        simplyrest_gate_pass_if(simplyrest_gate_schema_ref_id($article['publisher'] ?? '') === $organization_id, $area, 'schema Article publisher', 'Article publisher references Simply Rest.', 'Article publisher does not reference Simply Rest.');
        if (array_key_exists('mainEntityOfPage', $article)) {
            simplyrest_gate_pass_if(simplyrest_gate_schema_ref_id($article['mainEntityOfPage']) === $webpage_id, $area, 'schema Article mainEntityOfPage', 'Article mainEntityOfPage references page node.', 'Article mainEntityOfPage does not reference page node.');
        }
    }

    $breadcrumb_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'BreadcrumbList');
    simplyrest_gate_pass_if(!empty($breadcrumb_nodes), $area, 'schema BreadcrumbList node', 'BreadcrumbList node is present.', 'Missing BreadcrumbList node.');
    if (!empty($breadcrumb_nodes)) {
        $crumbs = isset($breadcrumb_nodes[0]['itemListElement']) && is_array($breadcrumb_nodes[0]['itemListElement']) ? $breadcrumb_nodes[0]['itemListElement'] : array();
        simplyrest_gate_pass_if(!empty($crumbs), $area, 'schema Breadcrumb items', 'BreadcrumbList has items.', 'BreadcrumbList has no items.');
        if (!empty($crumbs)) {
            $last_crumb = $crumbs[count($crumbs) - 1];
            $last_item = is_array($last_crumb) && isset($last_crumb['item']) ? (string) $last_crumb['item'] : '';
            simplyrest_gate_pass_if(simplyrest_gate_normalize_url($last_item) === simplyrest_gate_normalize_url($canonical), $area, 'schema Breadcrumb terminal URL', 'Breadcrumb terminal URL matches canonical.', 'Expected ' . $canonical . '; got ' . ($last_item === '' ? '<empty>' : $last_item) . '.');
        }
    }

    $faq_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'FAQPage');
    simplyrest_gate_pass_if(!empty($faq_nodes), $area, 'schema FAQPage node', 'FAQPage node is present.', 'Missing FAQPage node.');
    if (!empty($faq_nodes)) {
        $questions = isset($faq_nodes[0]['mainEntity']) && is_array($faq_nodes[0]['mainEntity']) ? $faq_nodes[0]['mainEntity'] : array();
        simplyrest_gate_pass_if(count($questions) >= 3, $area, 'schema FAQ question count', 'FAQPage has at least 3 questions.', 'FAQPage has fewer than 3 questions: ' . count($questions) . '.');
        $bad_questions = array();
        $missing_visible_questions = array();
        foreach ($questions as $question) {
            if (!is_array($question)) {
                $bad_questions[] = '<non-object>';
                continue;
            }
            $name = trim((string) ($question['name'] ?? ''));
            $answer = isset($question['acceptedAnswer']) && is_array($question['acceptedAnswer']) ? trim((string) ($question['acceptedAnswer']['text'] ?? '')) : '';
            if ($name === '' || $answer === '') {
                $bad_questions[] = $name === '' ? '<missing name>' : $name;
            }
            if ($name !== '' && strpos($content_lower, strtolower($name)) === false) {
                $missing_visible_questions[] = $name;
            }
        }
        simplyrest_gate_pass_if(empty($bad_questions), $area, 'schema FAQ answer completeness', 'FAQ questions have names and answers.', 'Incomplete FAQ questions: ' . implode('; ', $bad_questions) . '.');
        simplyrest_gate_pass_if(empty($missing_visible_questions), $area, 'schema FAQ content alignment', 'FAQ questions appear in page content.', 'FAQ questions missing from visible content: ' . implode('; ', $missing_visible_questions) . '.');
    }

    if ($target_page['path'] === 'mattress-reviews') {
        $item_lists = simplyrest_gate_schema_nodes_by_type($nodes, 'ItemList');
        simplyrest_gate_pass_if(!empty($item_lists), $area, 'schema ItemList node', 'ItemList node is present.', 'Missing ItemList node.');
        if (!empty($item_lists)) {
            $item_list_text = wp_json_encode($item_lists[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            simplyrest_gate_pass_if(strpos((string) $item_list_text, 'https://simplyrest.com/mattress-reviews/amerisleep-as3/') !== false, $area, 'schema ItemList AS3 URL', 'ItemList includes AS3 review URL.', 'ItemList missing AS3 review URL.');
        }
    }

    if ($target_page['path'] === 'mattress-reviews/amerisleep-as3') {
        simplyrest_gate_check_as3_schema_relationships($area, $nodes, $canonical);
    }
}

function simplyrest_gate_check_as3_schema_relationships($area, $nodes, $canonical) {
    $product_id = simplyrest_gate_schema_node_id_for_url($canonical, 'product');
    $review_id = simplyrest_gate_schema_node_id_for_url($canonical, 'review');
    $review_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'Review');
    $product_nodes = simplyrest_gate_schema_nodes_by_type($nodes, 'Product');

    simplyrest_gate_pass_if(!empty($review_nodes), $area, 'schema Review node', 'Review node is present.', 'Missing Review node.');
    simplyrest_gate_pass_if(!empty($product_nodes), $area, 'schema Product node', 'Product node is present.', 'Missing Product node.');

    if (!empty($review_nodes)) {
        $review = $review_nodes[0];
        simplyrest_gate_pass_if((string) ($review['@id'] ?? '') === $review_id, $area, 'schema Review id', 'Review @id matches canonical #review.', 'Expected ' . $review_id . '; got ' . (string) ($review['@id'] ?? '<empty>') . '.');
        simplyrest_gate_pass_if(simplyrest_gate_schema_ref_id($review['itemReviewed'] ?? '') === $product_id, $area, 'schema Review itemReviewed', 'Review itemReviewed references AS3 Product.', 'Review itemReviewed does not reference AS3 Product.');
        $rating = isset($review['reviewRating']) && is_array($review['reviewRating']) ? $review['reviewRating'] : array();
        $rating_value = isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : -1;
        $best_rating = isset($rating['bestRating']) ? (float) $rating['bestRating'] : -1;
        simplyrest_gate_pass_if($rating_value >= 0 && $rating_value <= 10, $area, 'schema Review ratingValue', 'Review ratingValue is within 0-10.', 'Review ratingValue outside 0-10.');
        simplyrest_gate_pass_if($best_rating === 10.0, $area, 'schema Review bestRating', 'Review bestRating is 10.', 'Review bestRating is not 10.');
    }

    if (!empty($product_nodes)) {
        $product = $product_nodes[0];
        simplyrest_gate_pass_if((string) ($product['@id'] ?? '') === $product_id, $area, 'schema Product id', 'Product @id matches canonical #product.', 'Expected ' . $product_id . '; got ' . (string) ($product['@id'] ?? '<empty>') . '.');
        $brand = $product['brand'] ?? '';
        $brand_name = is_array($brand) ? (string) ($brand['name'] ?? '') : (string) $brand;
        simplyrest_gate_pass_if($brand_name === 'Amerisleep', $area, 'schema Product brand', 'Product brand is Amerisleep.', 'Product brand is not Amerisleep.');
    }

    foreach (simplyrest_gate_schema_nodes_by_type($nodes, 'Article') as $article) {
        if (array_key_exists('about', $article)) {
            simplyrest_gate_pass_if(simplyrest_gate_schema_ref_id($article['about']) === $product_id, $area, 'schema Article about', 'Article about references AS3 Product.', 'Article about does not reference AS3 Product.');
        }
    }
}

function simplyrest_gate_schema_node_id_for_url($url, $fragment) {
    return untrailingslashit((string) $url) . '/#' . ltrim((string) $fragment, '#');
}

function simplyrest_gate_schema_page_node_type($path) {
    if ($path === 'ferdie-farhad') {
        return 'ProfilePage';
    }
    if ($path === 'mattress-reviews') {
        return 'CollectionPage';
    }
    return 'WebPage';
}

function simplyrest_gate_schema_nodes($schema) {
    $nodes = array();
    simplyrest_gate_append_schema_nodes($schema, $nodes);
    return $nodes;
}

function simplyrest_gate_append_schema_nodes($value, &$nodes) {
    if (!is_array($value)) {
        return;
    }

    if (isset($value['@graph']) && is_array($value['@graph'])) {
        foreach ($value['@graph'] as $graph_node) {
            simplyrest_gate_append_schema_nodes($graph_node, $nodes);
        }
        return;
    }

    if (isset($value['@type']) || isset($value['@id'])) {
        $nodes[] = $value;
        return;
    }

    foreach ($value as $child) {
        simplyrest_gate_append_schema_nodes($child, $nodes);
    }
}

function simplyrest_gate_schema_nodes_by_type($nodes, $schema_type) {
    $matches = array();
    foreach ($nodes as $node) {
        if (simplyrest_gate_schema_node_has_type($node, $schema_type)) {
            $matches[] = $node;
        }
    }
    return $matches;
}

function simplyrest_gate_schema_node_has_type($node, $schema_type) {
    if (!is_array($node) || !isset($node['@type'])) {
        return false;
    }

    $types = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
    return in_array($schema_type, array_map('strval', $types), true);
}

function simplyrest_gate_schema_ref_id($value) {
    if (is_array($value)) {
        return isset($value['@id']) ? (string) $value['@id'] : '';
    }
    return (string) $value;
}

function simplyrest_gate_schema_node_url($node) {
    if (!is_array($node) || !isset($node['url'])) {
        return '';
    }
    if (is_array($node['url'])) {
        return simplyrest_gate_schema_ref_id($node['url']);
    }
    return (string) $node['url'];
}

function simplyrest_gate_check_visible_media($area, $post) {
    $content = (string) $post->post_content;
    $has_media = has_post_thumbnail($post) || stripos($content, '<img') !== false || stripos($content, '<!-- wp:image') !== false;
    simplyrest_gate_pass_if(
        $has_media,
        $area,
        'visible Ferdie media',
        'Image or featured image is attached.',
        'No image or featured image found for the Ferdie author proof page.'
    );
}

function simplyrest_gate_check_as3_media($area, $post) {
    $content = (string) $post->post_content;
    $media_map = (string) get_post_meta((int) $post->ID, '_simplyrest_as3_media_map', true);
    $decoded = $media_map === '' ? array() : json_decode($media_map, true);
    $token_count = is_array($decoded) && isset($decoded['tokens']) && is_array($decoded['tokens']) ? count($decoded['tokens']) : 0;

    simplyrest_gate_pass_if($token_count >= 13, $area, 'AS3 media map', 'At least 13 media tokens are mapped.', 'Expected 13 mapped AS3 media tokens; got ' . $token_count . '.');
    simplyrest_gate_pass_if(stripos($content, '<video') !== false, $area, 'AS3 video blocks', 'Video blocks are present.', 'No video blocks found.');
    simplyrest_gate_pass_if(stripos($content, '<!-- wp:image') !== false || stripos($content, '<img') !== false, $area, 'AS3 image blocks', 'Image blocks are present.', 'No image blocks found.');
    simplyrest_gate_pass_if(strpos($content, '{{AS3_') === false, $area, 'AS3 unresolved tokens', 'No unresolved media tokens.', 'Unresolved AS3 media token found.');
    simplyrest_gate_pass_if(!simplyrest_gate_contains($content, 'Approved AS3 testing assets should be uploaded'), $area, 'AS3 placeholder copy', 'Placeholder upload copy removed.', 'Placeholder upload copy is still present.');
    simplyrest_gate_pass_if(has_post_thumbnail($post), $area, 'AS3 featured image', 'Featured image is set.', 'Featured image is not set.');
}

function simplyrest_gate_check_schema_renderer() {
    $mu_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    $target = trailingslashit($mu_dir) . 'simplyrest-phase1-jsonld.php';
    simplyrest_gate_pass_if(is_file($target), 'schema_renderer', 'mu-plugin file', 'Renderer file exists: ' . $target . '.', 'Renderer file missing: ' . $target . '.');
    simplyrest_gate_pass_if(function_exists('simplyrest_phase1_render_json_ld'), 'schema_renderer', 'renderer loaded', 'Renderer function is loaded.', 'Renderer function is not loaded. Confirm the file is in mu-plugins and reload WP.');
}

function simplyrest_gate_check_rollback_helper_file() {
    $rollback_path = getcwd() . DIRECTORY_SEPARATOR . 'simplyrest_phase1_wp_rollback.php';
    simplyrest_gate_pass_if(
        is_file($rollback_path),
        'rollback',
        'rollback helper file',
        'Rollback helper exists: ' . $rollback_path . '.',
        'Rollback helper missing from WordPress root: ' . $rollback_path . '.'
    );
}

function simplyrest_gate_check_redirect_records() {
    $records = simplyrest_gate_find_redirect_records();
    $bad_records = array();

    foreach ($records as $record) {
        $detail = strtolower($record['detail']);
        if (strpos($detail, 'amerisleep-as3') !== false && strpos($detail, 'best-online-mattress') !== false) {
            $bad_records[] = $record['source'] . ': ' . $record['detail'];
        }
    }

    simplyrest_gate_pass_if(
        empty($bad_records),
        'redirects',
        'AS3 bad redirect records',
        'No common WordPress redirect records send AS3 to best-online-mattress.',
        'Bad redirect records found: ' . implode(' | ', $bad_records)
    );

    if (empty($records)) {
        simplyrest_gate_add('warn', 'redirects', 'redirect record scan', 'No common WordPress redirect records found. Live HTTP check still matters for server/CDN redirects.');
    } else {
        simplyrest_gate_add('pass', 'redirects', 'redirect record scan', 'Scanned ' . count($records) . ' possible redirect records/options.');
    }
}

function simplyrest_gate_check_live_url($target_page) {
    $response = wp_remote_get($target_page['url'], array(
        'timeout' => 15,
        'redirection' => 0,
        'user-agent' => 'SimplyRestPhase1LaunchGate/1.0',
    ));

    $area = 'live:' . $target_page['path'];
    if (is_wp_error($response)) {
        simplyrest_gate_add('fail', $area, 'live http', 'HTTP error: ' . $response->get_error_message());
        return;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $location = (string) wp_remote_retrieve_header($response, 'location');
    simplyrest_gate_pass_if($status === 200, $area, 'live status', 'Returned 200.', 'Expected 200; got ' . $status . '.');
    simplyrest_gate_pass_if($location === '', $area, 'live redirect', 'No redirect location header.', 'Redirect location: ' . $location . '.');

    if ($target_page['path'] === 'mattress-reviews/amerisleep-as3') {
        simplyrest_gate_pass_if(
            stripos($location, 'best-online-mattress') === false,
            $area,
            'AS3 best-online redirect',
            'Does not redirect to best-online-mattress.',
            'AS3 still redirects to best-online-mattress.'
        );
    }
}

function simplyrest_gate_find_redirect_records() {
    global $wpdb;
    $needles = array('amerisleep-as3', 'best-online-mattress');
    $records = array();

    $tables = array(
        $wpdb->prefix . 'redirection_items' => array('url', 'match_url', 'action_data', 'match_data', 'title'),
        $wpdb->prefix . 'rank_math_redirections' => array('sources', 'url_to', 'from_url', 'destination', 'url', 'regex'),
        $wpdb->prefix . 'rank_math_redirections_cache' => array('sources', 'url_to', 'from_url', 'destination', 'url', 'regex'),
    );

    foreach ($tables as $table => $columns) {
        if (!simplyrest_gate_table_exists($table)) {
            continue;
        }
        $existing_columns = simplyrest_gate_existing_columns($table, $columns);
        if (empty($existing_columns)) {
            continue;
        }
        $where = simplyrest_gate_like_where($existing_columns, $needles);
        $rows = $wpdb->get_results('SELECT * FROM ' . simplyrest_gate_quote_identifier($table) . " WHERE {$where} LIMIT 25", ARRAY_A);
        foreach ((array) $rows as $row) {
            $records[] = array(
                'source' => basename($table),
                'detail' => wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }
    }

    $where = simplyrest_gate_like_where(array('option_name', 'option_value'), $needles);
    $option_rows = $wpdb->get_results('SELECT option_name FROM ' . simplyrest_gate_quote_identifier($wpdb->options) . " WHERE {$where} LIMIT 25", ARRAY_A);
    foreach ((array) $option_rows as $row) {
        $records[] = array(
            'source' => 'wp_options',
            'detail' => 'option_name=' . $row['option_name'],
        );
    }

    $redirect_posts = get_posts(array(
        'post_type' => array('redirect_rule'),
        'post_status' => 'any',
        'posts_per_page' => 25,
        's' => 'amerisleep-as3',
    ));

    foreach ((array) $redirect_posts as $post) {
        $records[] = array(
            'source' => 'safe_redirect_manager',
            'detail' => 'post_id=' . (int) $post->ID . ' title=' . get_the_title($post),
        );
    }

    return $records;
}

function simplyrest_gate_collect_schema_types($value, &$types) {
    if (is_array($value)) {
        if (isset($value['@type'])) {
            if (is_array($value['@type'])) {
                foreach ($value['@type'] as $type) {
                    $types[(string) $type] = true;
                }
            } else {
                $types[(string) $value['@type']] = true;
            }
        }
        foreach ($value as $child) {
            simplyrest_gate_collect_schema_types($child, $types);
        }
    }
}

function simplyrest_gate_table_exists($table) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function simplyrest_gate_existing_columns($table, $candidate_columns) {
    global $wpdb;
    $rows = $wpdb->get_results('DESCRIBE ' . simplyrest_gate_quote_identifier($table), ARRAY_A);
    $existing = array();
    foreach ((array) $rows as $row) {
        if (isset($row['Field']) && in_array($row['Field'], $candidate_columns, true)) {
            $existing[] = $row['Field'];
        }
    }
    return $existing;
}

function simplyrest_gate_like_where($columns, $needles) {
    global $wpdb;
    $parts = array();
    foreach ($columns as $column) {
        $safe_column = simplyrest_gate_quote_identifier($column);
        foreach ($needles as $needle) {
            $parts[] = $wpdb->prepare("{$safe_column} LIKE %s", '%' . $wpdb->esc_like($needle) . '%');
        }
    }

    return empty($parts) ? '(0=1)' : '(' . implode(' OR ', $parts) . ')';
}

function simplyrest_gate_quote_identifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function simplyrest_gate_normalize_url($url) {
    return untrailingslashit((string) $url) . '/';
}

function simplyrest_gate_contains($haystack, $needle) {
    return stripos((string) $haystack, (string) $needle) !== false;
}

function simplyrest_gate_add($status, $area, $check, $detail) {
    global $sr_gate_rows;
    $sr_gate_rows[] = array(
        'status' => $status,
        'area' => $area,
        'check' => $check,
        'detail' => $detail,
    );
}

function simplyrest_gate_pass_if($condition, $area, $check, $pass_detail, $fail_detail) {
    simplyrest_gate_add($condition ? 'pass' : 'fail', $area, $check, $condition ? $pass_detail : $fail_detail);
}

function simplyrest_gate_is_failure($row) {
    return isset($row['status']) && $row['status'] === 'fail';
}

function simplyrest_gate_print_text($rows, $failures) {
    echo "Simply Rest Phase 1 Launch Gate\n";
    echo "===============================\n";
    echo "Overall: " . (empty($failures) ? "PASS" : "FAIL") . "\n";
    echo "Failures: " . count($failures) . "\n\n";

    foreach ($rows as $row) {
        echo strtoupper($row['status']) . " | " . $row['area'] . " | " . $row['check'] . " | " . $row['detail'] . "\n";
    }
}
