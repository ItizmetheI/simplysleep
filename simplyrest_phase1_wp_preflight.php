<?php
/**
 * Simply Rest Phase 1 WordPress preflight and AS3 redirect audit.
 *
 * Run from the WordPress root with WP-CLI before launch edits:
 *
 *   wp eval-file simplyrest_phase1_wp_preflight.php
 *   wp eval-file simplyrest_phase1_wp_preflight.php -- --format=json
 *
 * This script is read-only. It does not create pages, change redirects, update
 * schema, upload media, or edit settings.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_wp_preflight.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$format_json = in_array('--format=json', $args, true);

$target_pages = array(
    array('name' => 'Mattress Lab', 'path' => 'mattress-lab', 'url' => 'https://simplyrest.com/mattress-lab/'),
    array('name' => 'How We Test Mattresses', 'path' => 'how-we-test-mattresses', 'url' => 'https://simplyrest.com/how-we-test-mattresses/'),
    array('name' => 'Ferdie Farhad', 'path' => 'ferdie-farhad', 'url' => 'https://simplyrest.com/ferdie-farhad/'),
    array('name' => 'Mattress Reviews Hub', 'path' => 'mattress-reviews', 'url' => 'https://simplyrest.com/mattress-reviews/'),
    array('name' => 'Amerisleep AS3 Review', 'path' => 'mattress-reviews/amerisleep-as3', 'url' => 'https://simplyrest.com/mattress-reviews/amerisleep-as3/'),
);

$report = array(
    'environment' => simplyrest_preflight_environment(),
    'target_pages' => array(),
    'plugins' => simplyrest_preflight_plugins(),
    'schema_renderer' => simplyrest_preflight_schema_renderer(),
    'upload_limits' => simplyrest_preflight_upload_limits(),
    'redirect_audit' => simplyrest_preflight_redirect_audit(),
    'rewrite_notes' => simplyrest_preflight_rewrite_notes(),
);

foreach ($target_pages as $target_page) {
    $report['target_pages'][] = simplyrest_preflight_page_status($target_page);
}

if ($format_json) {
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

simplyrest_preflight_print_text($report);

function simplyrest_preflight_environment() {
    global $wpdb;

    return array(
        'site_url' => get_site_url(),
        'home_url' => get_home_url(),
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'database_prefix' => $wpdb->prefix,
        'multisite' => is_multisite() ? 'yes' : 'no',
        'permalink_structure' => (string) get_option('permalink_structure'),
        'timezone' => (string) get_option('timezone_string'),
    );
}

function simplyrest_preflight_page_status($target_page) {
    $post = get_page_by_path($target_page['path'], OBJECT, array('page', 'post'));
    $parent_path = '';
    $parent_exists = 'n/a';

    if (strpos($target_page['path'], '/') !== false) {
        $parts = explode('/', $target_page['path']);
        array_pop($parts);
        $parent_path = implode('/', $parts);
        $parent_exists = get_page_by_path($parent_path, OBJECT, array('page', 'post')) ? 'yes' : 'no';
    }

    if (!$post) {
        return array(
            'name' => $target_page['name'],
            'path' => $target_page['path'],
            'expected_url' => $target_page['url'],
            'exists' => 'no',
            'post_id' => '',
            'post_type' => '',
            'post_status' => '',
            'actual_permalink' => '',
            'parent_path' => $parent_path,
            'parent_exists' => $parent_exists,
            'has_phase1_schema_meta' => 'no',
            'has_retrofit_schema_meta' => 'no',
            'yoast_canonical' => '',
            'yoast_title_present' => 'no',
            'yoast_description_present' => 'no',
        );
    }

    $post_id = (int) $post->ID;
    return array(
        'name' => $target_page['name'],
        'path' => $target_page['path'],
        'expected_url' => $target_page['url'],
        'exists' => 'yes',
        'post_id' => $post_id,
        'post_type' => $post->post_type,
        'post_status' => $post->post_status,
        'actual_permalink' => get_permalink($post),
        'parent_path' => $parent_path,
        'parent_exists' => $parent_exists,
        'has_phase1_schema_meta' => simplyrest_preflight_yes_no((string) get_post_meta($post_id, '_simplyrest_json_ld', true) !== ''),
        'has_retrofit_schema_meta' => simplyrest_preflight_yes_no((string) get_post_meta($post_id, '_simplyrest_retrofit_json_ld', true) !== ''),
        'yoast_canonical' => (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true),
        'yoast_title_present' => simplyrest_preflight_yes_no((string) get_post_meta($post_id, '_yoast_wpseo_title', true) !== ''),
        'yoast_description_present' => simplyrest_preflight_yes_no((string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true) !== ''),
    );
}

function simplyrest_preflight_plugins() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    $active = (array) get_option('active_plugins', array());
    $interesting = array(
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'wordpress-seo-premium/wp-seo-premium.php' => 'Yoast SEO Premium',
        'seo-by-rank-math/rank-math.php' => 'Rank Math SEO',
        'redirection/redirection.php' => 'Redirection',
        'safe-redirect-manager/safe-redirect-manager.php' => 'Safe Redirect Manager',
        'advanced-custom-fields/acf.php' => 'Advanced Custom Fields',
        'advanced-custom-fields-pro/acf.php' => 'Advanced Custom Fields Pro',
    );

    $result = array();
    foreach ($interesting as $file => $label) {
        $result[] = array(
            'plugin' => $label,
            'file' => $file,
            'installed' => isset($plugins[$file]) ? 'yes' : 'no',
            'active' => in_array($file, $active, true) ? 'yes' : 'no',
        );
    }

    return $result;
}

function simplyrest_preflight_schema_renderer() {
    if (!function_exists('get_mu_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $mu_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    $target = trailingslashit($mu_dir) . 'simplyrest-phase1-jsonld.php';

    return array(
        'mu_plugin_dir' => $mu_dir,
        'mu_plugin_dir_exists' => is_dir($mu_dir) ? 'yes' : 'no',
        'target_renderer_path' => $target,
        'target_renderer_exists' => is_file($target) ? 'yes' : 'no',
        'mu_plugins_loaded' => function_exists('get_mu_plugins') ? count(get_mu_plugins()) : 0,
    );
}

function simplyrest_preflight_upload_limits() {
    $upload_dir = wp_upload_dir();

    return array(
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'uploads_writable' => wp_is_writable($upload_dir['basedir']) ? 'yes' : 'no',
        'uploads_basedir' => $upload_dir['basedir'],
    );
}

function simplyrest_preflight_rewrite_notes() {
    return array(
        'redirection_note' => 'If /mattress-reviews/amerisleep-as3/ redirects to /best-online-mattress/, check Redirection plugin, Safe Redirect Manager, Rank Math redirects, Yoast Premium redirects, server-level rules, and theme/plugin custom redirect code.',
        'preflight_is_read_only' => 'yes',
    );
}

function simplyrest_preflight_redirect_audit() {
    global $wpdb;

    $needle_paths = array(
        '/mattress-reviews/amerisleep-as3/',
        'mattress-reviews/amerisleep-as3',
        '/best-online-mattress/',
        'best-online-mattress',
    );

    $findings = array();
    $findings = array_merge($findings, simplyrest_preflight_redirection_plugin_findings($needle_paths));
    $findings = array_merge($findings, simplyrest_preflight_safe_redirect_findings($needle_paths));
    $findings = array_merge($findings, simplyrest_preflight_rankmath_findings($needle_paths));
    $findings = array_merge($findings, simplyrest_preflight_options_findings($needle_paths));

    if (empty($findings)) {
        $findings[] = array(
            'source' => 'common_wp_locations',
            'status' => 'no_matching_records_found',
            'detail' => 'No matching AS3 redirect records found in common plugin tables/options. Check server/CDN/.htaccess/custom code if live redirect persists.',
        );
    }

    return $findings;
}

function simplyrest_preflight_redirection_plugin_findings($needle_paths) {
    global $wpdb;
    $table = $wpdb->prefix . 'redirection_items';
    if (!simplyrest_preflight_table_exists($table)) {
        return array();
    }

    $search_columns = simplyrest_preflight_existing_columns($table, array('url', 'match_url', 'action_data', 'match_data', 'title'));
    $select_columns = simplyrest_preflight_existing_columns($table, array('id', 'url', 'match_url', 'action_type', 'action_code', 'action_data', 'match_type', 'match_data', 'group_id', 'status', 'title'));
    if (empty($search_columns)) {
        return array();
    }

    $where = simplyrest_preflight_like_where($search_columns, $needle_paths);
    $select = empty($select_columns) ? '*' : implode(', ', array_map('simplyrest_preflight_quote_identifier', $select_columns));
    $sql = 'SELECT ' . $select . ' FROM ' . simplyrest_preflight_quote_identifier($table) . " WHERE {$where} LIMIT 25";
    $rows = $wpdb->get_results($sql, ARRAY_A);

    $findings = array();
    foreach ((array) $rows as $row) {
        $findings[] = array(
            'source' => 'redirection_plugin',
            'status' => 'match',
            'detail' => wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    return $findings;
}

function simplyrest_preflight_safe_redirect_findings($needle_paths) {
    $query = new WP_Query(array(
        'post_type' => 'redirect_rule',
        'post_status' => 'any',
        'posts_per_page' => 25,
        's' => 'amerisleep-as3',
        'fields' => 'ids',
    ));

    $findings = array();
    foreach ((array) $query->posts as $post_id) {
        $findings[] = array(
            'source' => 'safe_redirect_manager',
            'status' => 'possible_match',
            'detail' => 'redirect_rule post_id=' . (int) $post_id . ' title=' . get_the_title($post_id),
        );
    }

    return $findings;
}

function simplyrest_preflight_rankmath_findings($needle_paths) {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'rank_math_redirections',
        $wpdb->prefix . 'rank_math_redirections_cache',
    );
    $findings = array();

    foreach ($tables as $table) {
        if (!simplyrest_preflight_table_exists($table)) {
            continue;
        }
        $search_columns = simplyrest_preflight_existing_columns($table, array('sources', 'url_to', 'from_url', 'destination', 'url', 'regex', 'header_code'));
        if (empty($search_columns)) {
            continue;
        }
        $where = simplyrest_preflight_like_where($search_columns, $needle_paths);
        $rows = $wpdb->get_results('SELECT * FROM ' . simplyrest_preflight_quote_identifier($table) . " WHERE {$where} LIMIT 25", ARRAY_A);
        foreach ((array) $rows as $row) {
            $findings[] = array(
                'source' => basename($table),
                'status' => 'match',
                'detail' => wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }
    }

    return $findings;
}

function simplyrest_preflight_options_findings($needle_paths) {
    global $wpdb;
    $where = simplyrest_preflight_like_where(array('option_name', 'option_value'), $needle_paths);
    $rows = $wpdb->get_results('SELECT option_name FROM ' . simplyrest_preflight_quote_identifier($wpdb->options) . " WHERE {$where} LIMIT 25", ARRAY_A);
    $findings = array();

    foreach ((array) $rows as $row) {
        $findings[] = array(
            'source' => 'wp_options',
            'status' => 'possible_match',
            'detail' => 'option_name=' . $row['option_name'],
        );
    }

    return $findings;
}

function simplyrest_preflight_table_exists($table) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function simplyrest_preflight_table_columns($table) {
    global $wpdb;
    $rows = $wpdb->get_results('DESCRIBE ' . simplyrest_preflight_quote_identifier($table), ARRAY_A);
    $columns = array();
    foreach ((array) $rows as $row) {
        if (isset($row['Field'])) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function simplyrest_preflight_existing_columns($table, $candidate_columns) {
    $columns = simplyrest_preflight_table_columns($table);
    if (empty($columns)) {
        return array();
    }

    return array_values(array_intersect($candidate_columns, $columns));
}

function simplyrest_preflight_like_where($columns, $needles) {
    global $wpdb;
    if (empty($columns) || empty($needles)) {
        return '(0=1)';
    }

    $parts = array();
    foreach ($columns as $column) {
        $safe_column = simplyrest_preflight_quote_identifier($column);
        foreach ($needles as $needle) {
            $parts[] = $wpdb->prepare("{$safe_column} LIKE %s", '%' . $wpdb->esc_like($needle) . '%');
        }
    }

    return '(' . implode(' OR ', $parts) . ')';
}

function simplyrest_preflight_quote_identifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function simplyrest_preflight_yes_no($value) {
    return $value ? 'yes' : 'no';
}

function simplyrest_preflight_print_text($report) {
    echo "Simply Rest Phase 1 WordPress Preflight\n";
    echo "======================================\n\n";

    echo "Environment\n";
    foreach ($report['environment'] as $key => $value) {
        echo "- {$key}: {$value}\n";
    }

    echo "\nTarget Pages\n";
    foreach ($report['target_pages'] as $page) {
        echo "- {$page['name']} | path={$page['path']} | exists={$page['exists']} | status={$page['post_status']} | post_id={$page['post_id']} | parent_exists={$page['parent_exists']} | schema_meta={$page['has_phase1_schema_meta']} | permalink={$page['actual_permalink']}\n";
    }

    echo "\nPlugins\n";
    foreach ($report['plugins'] as $plugin) {
        echo "- {$plugin['plugin']} | installed={$plugin['installed']} | active={$plugin['active']} | file={$plugin['file']}\n";
    }

    echo "\nSchema Renderer\n";
    foreach ($report['schema_renderer'] as $key => $value) {
        echo "- {$key}: {$value}\n";
    }

    echo "\nUpload Limits\n";
    foreach ($report['upload_limits'] as $key => $value) {
        echo "- {$key}: {$value}\n";
    }

    echo "\nAS3 Redirect Audit\n";
    foreach ($report['redirect_audit'] as $finding) {
        echo "- {$finding['source']} | {$finding['status']} | {$finding['detail']}\n";
    }

    echo "\nRewrite Notes\n";
    foreach ($report['rewrite_notes'] as $key => $value) {
        echo "- {$key}: {$value}\n";
    }
}
