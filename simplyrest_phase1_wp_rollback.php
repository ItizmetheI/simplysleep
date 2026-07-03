<?php
/**
 * Simply Rest Phase 1 rollback helper.
 *
 * Run from the WordPress root with WP-CLI:
 *
 *   wp eval-file simplyrest_phase1_wp_rollback.php
 *   wp eval-file simplyrest_phase1_wp_rollback.php -- --apply
 *
 * Defaults to dry-run. Use --apply only after confirming the database backup.
 * This helper does not delete pages. It restores updated pages from the
 * _simplyrest_phase1_pre_import_snapshot marker, or moves importer-created
 * pages back to draft when no pre-import snapshot exists.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_wp_rollback.php
");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$apply = in_array('--apply', $args, true);

$target_paths = array(
    'mattress-lab',
    'how-we-test-mattresses',
    'ferdie-farhad',
    'mattress-reviews',
    'mattress-reviews/amerisleep-as3',
);

function sr_phase1_rollback_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::line($message);
    } else {
        echo $message . PHP_EOL;
    }
}

function sr_phase1_rollback_find_page_by_path($path) {
    $path = trim((string) $path, '/');
    if ($path === '') {
        return null;
    }
    $page = get_page_by_path($path, OBJECT, 'page');
    return $page instanceof WP_Post ? $page : null;
}

function sr_phase1_rollback_restore_meta($post_id, $snapshot) {
    if (!isset($snapshot['meta']) || !is_array($snapshot['meta'])) {
        return;
    }

    foreach ($snapshot['meta'] as $key => $state) {
        if (!is_string($key) || !is_array($state)) {
            continue;
        }
        $existed = !empty($state['exists']);
        if ($existed) {
            update_post_meta($post_id, $key, isset($state['value']) ? $state['value'] : '');
        } else {
            delete_post_meta($post_id, $key);
        }
    }
}

function sr_phase1_rollback_snapshot_postarr($post_id, $snapshot) {
    if (!isset($snapshot['post']) || !is_array($snapshot['post'])) {
        return null;
    }

    $post = $snapshot['post'];
    $required = array('post_title', 'post_name', 'post_content', 'post_status');
    foreach ($required as $field) {
        if (!array_key_exists($field, $post)) {
            return null;
        }
    }

    return array(
        'ID' => $post_id,
        'post_title' => $post['post_title'],
        'post_name' => $post['post_name'],
        'post_content' => $post['post_content'],
        'post_excerpt' => isset($post['post_excerpt']) ? $post['post_excerpt'] : '',
        'post_status' => $post['post_status'],
        'post_parent' => isset($post['post_parent']) ? (int) $post['post_parent'] : 0,
        'menu_order' => isset($post['menu_order']) ? (int) $post['menu_order'] : 0,
        'comment_status' => isset($post['comment_status']) ? $post['comment_status'] : 'closed',
        'ping_status' => isset($post['ping_status']) ? $post['ping_status'] : 'closed',
    );
}

$restored = 0;
$drafted = 0;
$skipped = 0;

sr_phase1_rollback_log("Simply Rest rollback helper. Apply: " . ($apply ? 'yes' : 'no') . ".");

foreach ($target_paths as $path) {
    $page = sr_phase1_rollback_find_page_by_path($path);
    if (!$page) {
        $skipped++;
        sr_phase1_rollback_log("SKIP /" . $path . "/ because no page exists.");
        continue;
    }

    $post_id = (int) $page->ID;
    $expected_path = '/' . trim($path, '/') . '/';
    $snapshot_raw = get_post_meta($post_id, '_simplyrest_phase1_pre_import_snapshot', true);
    $created_by_import = get_post_meta($post_id, '_simplyrest_phase1_created_by_import', true);

    if (is_string($snapshot_raw) && trim($snapshot_raw) !== '') {
        $snapshot = json_decode($snapshot_raw, true);
        if (!is_array($snapshot)) {
            $skipped++;
            sr_phase1_rollback_log("SKIP /" . $path . "/ post ID " . $post_id . " because snapshot JSON is invalid.");
            continue;
        }
        if (isset($snapshot['page_path']) && $snapshot['page_path'] !== $expected_path) {
            $skipped++;
            sr_phase1_rollback_log("SKIP /" . $path . "/ post ID " . $post_id . " because snapshot path mismatch: " . $snapshot['page_path'] . ".");
            continue;
        }

        $postarr = sr_phase1_rollback_snapshot_postarr($post_id, $snapshot);
        if (!$postarr) {
            $skipped++;
            sr_phase1_rollback_log("SKIP /" . $path . "/ post ID " . $post_id . " because snapshot post data is incomplete.");
            continue;
        }

        if (!$apply) {
            sr_phase1_rollback_log("DRY RUN restore /" . $path . "/ post ID " . $post_id . " to status " . $postarr['post_status'] . ".");
            continue;
        }

        $result = wp_update_post($postarr, true);
        if (is_wp_error($result)) {
            $skipped++;
            sr_phase1_rollback_log("ERROR restoring /" . $path . "/ post ID " . $post_id . ": " . $result->get_error_message());
            continue;
        }
        sr_phase1_rollback_restore_meta($post_id, $snapshot);
        update_post_meta($post_id, '_simplyrest_phase1_rollback_restored_at', gmdate('c'));
        $restored++;
        sr_phase1_rollback_log("RESTORED /" . $path . "/ post ID " . $post_id . " from pre-import snapshot.");
        continue;
    }

    if ($created_by_import === '1') {
        if (!$apply) {
            sr_phase1_rollback_log("DRY RUN move importer-created /" . $path . "/ post ID " . $post_id . " to draft.");
            continue;
        }

        $result = wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), true);
        if (is_wp_error($result)) {
            $skipped++;
            sr_phase1_rollback_log("ERROR drafting /" . $path . "/ post ID " . $post_id . ": " . $result->get_error_message());
            continue;
        }
        update_post_meta($post_id, '_simplyrest_phase1_rollback_drafted_at', gmdate('c'));
        $drafted++;
        sr_phase1_rollback_log("DRAFTED importer-created /" . $path . "/ post ID " . $post_id . ".");
        continue;
    }

    $skipped++;
    sr_phase1_rollback_log("SKIP /" . $path . "/ post ID " . $post_id . " because no Simply Rest snapshot or created marker exists.");
}

sr_phase1_rollback_log("Done. Restored: " . $restored . "; Drafted: " . $drafted . "; Skipped: " . $skipped . "; Apply: " . ($apply ? 'yes' : 'no') . ".");
