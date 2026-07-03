<?php
/**
 * Simply Rest Phase 1 AS3 redirect cleanup helper.
 *
 * Run from the WordPress root with WP-CLI after database backup:
 *
 *   wp eval-file simplyrest_phase1_as3_redirect_cleanup.php
 *   wp eval-file simplyrest_phase1_as3_redirect_cleanup.php -- --apply
 *
 * Defaults to dry-run. With --apply, this disables common WordPress redirect
 * plugin records that clearly send /mattress-reviews/amerisleep-as3/ to
 * /best-online-mattress/. It does not edit server, CDN, or htaccess redirects.
 *
 * Optional flags:
 * - --apply: disable matching WordPress redirect records.
 * - --format=json: print machine-readable report.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_as3_redirect_cleanup.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$apply = in_array('--apply', $args, true);
$format_json = in_array('--format=json', $args, true);

$report = array();
$report = array_merge($report, simplyrest_as3_cleanup_redirection_plugin($apply));
$report = array_merge($report, simplyrest_as3_cleanup_rankmath($apply));
$report = array_merge($report, simplyrest_as3_cleanup_safe_redirect_manager($apply));
$report = array_merge($report, simplyrest_as3_cleanup_options_audit());

$matches = array_values(array_filter($report, 'simplyrest_as3_cleanup_is_match'));
$changed = array_values(array_filter($report, 'simplyrest_as3_cleanup_is_changed'));
$manual = array_values(array_filter($report, 'simplyrest_as3_cleanup_is_manual'));

if ($format_json) {
    echo wp_json_encode(array(
        'mode' => $apply ? 'apply' : 'dry-run',
        'matches' => count($matches),
        'changed' => count($changed),
        'manual_review' => count($manual),
        'rows' => $report,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(empty($manual) ? 0 : 2);
}

echo "Simply Rest AS3 Redirect Cleanup\n";
echo "================================\n";
echo "Mode: " . ($apply ? "APPLY" : "DRY RUN") . "\n";
echo "Matches: " . count($matches) . "\n";
echo "Changed: " . count($changed) . "\n";
echo "Manual review: " . count($manual) . "\n\n";

foreach ($report as $row) {
    echo strtoupper($row['status']) . " | " . $row['source'] . " | " . $row['action'] . " | " . $row['detail'] . "\n";
}

exit(empty($manual) ? 0 : 2);

function simplyrest_as3_cleanup_redirection_plugin($apply) {
    global $wpdb;
    $table = $wpdb->prefix . 'redirection_items';
    if (!simplyrest_as3_cleanup_table_exists($table)) {
        return array();
    }

    $columns = simplyrest_as3_cleanup_existing_columns($table, array('id', 'url', 'match_url', 'action_data', 'match_data', 'action_code', 'action_type', 'status', 'title'));
    $search_columns = array_values(array_intersect($columns, array('url', 'match_url', 'action_data', 'match_data', 'title')));
    if (empty($search_columns)) {
        return array();
    }

    $rows = simplyrest_as3_cleanup_select_rows($table, $columns, $search_columns);
    $report = array();
    foreach ($rows as $row) {
        if (!simplyrest_as3_cleanup_row_targets_bad_redirect($row)) {
            continue;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if (!$id || !in_array('status', $columns, true)) {
            $report[] = simplyrest_as3_cleanup_row('manual', 'redirection_plugin', 'manual_review', 'Could not safely disable row: ' . simplyrest_as3_cleanup_encode($row));
            continue;
        }

        if ($apply) {
            $result = $wpdb->update($table, array('status' => 'disabled'), array('id' => $id), array('%s'), array('%d'));
            if ($result === false) {
                $report[] = simplyrest_as3_cleanup_row('manual', 'redirection_plugin', 'update_failed', 'id=' . $id . ' error=' . $wpdb->last_error);
            } else {
                $report[] = simplyrest_as3_cleanup_row('changed', 'redirection_plugin', 'disabled', 'id=' . $id . ' row=' . simplyrest_as3_cleanup_encode($row));
            }
        } else {
            $report[] = simplyrest_as3_cleanup_row('match', 'redirection_plugin', 'would_disable', 'id=' . $id . ' row=' . simplyrest_as3_cleanup_encode($row));
        }
    }

    return $report;
}

function simplyrest_as3_cleanup_rankmath($apply) {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'rank_math_redirections',
        $wpdb->prefix . 'rank_math_redirections_cache',
    );

    $report = array();
    foreach ($tables as $table) {
        if (!simplyrest_as3_cleanup_table_exists($table)) {
            continue;
        }

        $columns = simplyrest_as3_cleanup_existing_columns($table, array('id', 'sources', 'url_to', 'from_url', 'destination', 'url', 'regex', 'header_code', 'status'));
        $search_columns = array_values(array_intersect($columns, array('sources', 'url_to', 'from_url', 'destination', 'url', 'regex')));
        if (empty($search_columns)) {
            continue;
        }

        $rows = simplyrest_as3_cleanup_select_rows($table, $columns, $search_columns);
        foreach ($rows as $row) {
            if (!simplyrest_as3_cleanup_row_targets_bad_redirect($row)) {
                continue;
            }

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!$id || !in_array('status', $columns, true)) {
                $report[] = simplyrest_as3_cleanup_row('manual', basename($table), 'manual_review', 'Could not safely disable row: ' . simplyrest_as3_cleanup_encode($row));
                continue;
            }

            if ($apply) {
                $result = $wpdb->update($table, array('status' => 'inactive'), array('id' => $id), array('%s'), array('%d'));
                if ($result === false) {
                    $report[] = simplyrest_as3_cleanup_row('manual', basename($table), 'update_failed', 'id=' . $id . ' error=' . $wpdb->last_error);
                } else {
                    $report[] = simplyrest_as3_cleanup_row('changed', basename($table), 'disabled', 'id=' . $id . ' row=' . simplyrest_as3_cleanup_encode($row));
                }
            } else {
                $report[] = simplyrest_as3_cleanup_row('match', basename($table), 'would_disable', 'id=' . $id . ' row=' . simplyrest_as3_cleanup_encode($row));
            }
        }
    }

    return $report;
}

function simplyrest_as3_cleanup_safe_redirect_manager($apply) {
    $query = new WP_Query(array(
        'post_type' => 'redirect_rule',
        'post_status' => 'any',
        'posts_per_page' => 25,
        's' => 'amerisleep-as3',
    ));

    $report = array();
    foreach ((array) $query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $row_text = simplyrest_as3_cleanup_post_redirect_text($post);
        if (!simplyrest_as3_cleanup_text_targets_bad_redirect($row_text)) {
            continue;
        }

        if ($apply) {
            $result = wp_update_post(array(
                'ID' => (int) $post->ID,
                'post_status' => 'draft',
            ), true);
            if (is_wp_error($result)) {
                $report[] = simplyrest_as3_cleanup_row('manual', 'safe_redirect_manager', 'update_failed', 'post_id=' . (int) $post->ID . ' error=' . $result->get_error_message());
            } else {
                $report[] = simplyrest_as3_cleanup_row('changed', 'safe_redirect_manager', 'drafted_redirect_rule', 'post_id=' . (int) $post->ID . ' title=' . get_the_title($post));
            }
        } else {
            $report[] = simplyrest_as3_cleanup_row('match', 'safe_redirect_manager', 'would_draft_redirect_rule', 'post_id=' . (int) $post->ID . ' title=' . get_the_title($post));
        }
    }

    return $report;
}

function simplyrest_as3_cleanup_options_audit() {
    global $wpdb;
    $where = simplyrest_as3_cleanup_like_where(array('option_name', 'option_value'));
    $rows = $wpdb->get_results('SELECT option_name FROM ' . simplyrest_as3_cleanup_quote_identifier($wpdb->options) . " WHERE {$where} LIMIT 25", ARRAY_A);
    $report = array();

    foreach ((array) $rows as $row) {
        $report[] = simplyrest_as3_cleanup_row('manual', 'wp_options', 'manual_review', 'Possible redirect option needs manual review: option_name=' . $row['option_name']);
    }

    return $report;
}

function simplyrest_as3_cleanup_select_rows($table, $select_columns, $search_columns) {
    global $wpdb;
    $select = implode(', ', array_map('simplyrest_as3_cleanup_quote_identifier', $select_columns));
    $where = simplyrest_as3_cleanup_like_where($search_columns);
    return (array) $wpdb->get_results('SELECT ' . $select . ' FROM ' . simplyrest_as3_cleanup_quote_identifier($table) . " WHERE {$where} LIMIT 25", ARRAY_A);
}

function simplyrest_as3_cleanup_like_where($columns) {
    global $wpdb;
    $needles = array(
        'amerisleep-as3',
        'mattress-reviews/amerisleep-as3',
        'best-online-mattress',
    );

    $parts = array();
    foreach ($columns as $column) {
        $safe_column = simplyrest_as3_cleanup_quote_identifier($column);
        foreach ($needles as $needle) {
            $parts[] = $wpdb->prepare("{$safe_column} LIKE %s", '%' . $wpdb->esc_like($needle) . '%');
        }
    }

    return empty($parts) ? '(0=1)' : '(' . implode(' OR ', $parts) . ')';
}

function simplyrest_as3_cleanup_row_targets_bad_redirect($row) {
    return simplyrest_as3_cleanup_text_targets_bad_redirect(simplyrest_as3_cleanup_encode($row));
}

function simplyrest_as3_cleanup_text_targets_bad_redirect($text) {
    $text = strtolower((string) $text);
    return strpos($text, 'amerisleep-as3') !== false && strpos($text, 'best-online-mattress') !== false;
}

function simplyrest_as3_cleanup_post_redirect_text($post) {
    $meta = get_post_meta((int) $post->ID);
    return wp_json_encode(array(
        'id' => (int) $post->ID,
        'title' => get_the_title($post),
        'content' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'meta' => $meta,
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function simplyrest_as3_cleanup_table_exists($table) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function simplyrest_as3_cleanup_existing_columns($table, $candidate_columns) {
    global $wpdb;
    $rows = $wpdb->get_results('DESCRIBE ' . simplyrest_as3_cleanup_quote_identifier($table), ARRAY_A);
    $columns = array();
    foreach ((array) $rows as $row) {
        if (isset($row['Field']) && in_array($row['Field'], $candidate_columns, true)) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function simplyrest_as3_cleanup_quote_identifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function simplyrest_as3_cleanup_encode($value) {
    return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function simplyrest_as3_cleanup_row($status, $source, $action, $detail) {
    return array(
        'status' => $status,
        'source' => $source,
        'action' => $action,
        'detail' => $detail,
    );
}

function simplyrest_as3_cleanup_is_match($row) {
    return isset($row['status']) && in_array($row['status'], array('match', 'changed'), true);
}

function simplyrest_as3_cleanup_is_changed($row) {
    return isset($row['status']) && $row['status'] === 'changed';
}

function simplyrest_as3_cleanup_is_manual($row) {
    return isset($row['status']) && $row['status'] === 'manual';
}
