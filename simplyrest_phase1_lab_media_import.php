<?php
/**
 * Simply Rest Phase 1 Lab/Ferdie proof media importer.
 *
 * Run after the phase-one page importer has created the proof pages:
 *
 *   unzip simplyrest-lab-proof-media-2026-06-25.zip -d simplyrest-lab-proof-media
 *   wp eval-file simplyrest_phase1_lab_media_import.php -- \
 *     --media-dir=simplyrest-lab-proof-media \
 *     --manifest=simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv \
 *     --dry-run
 *
 *   wp eval-file simplyrest_phase1_lab_media_import.php -- \
 *     --media-dir=simplyrest-lab-proof-media \
 *     --manifest=simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv \
 *     --update-pages
 *
 * Defaults:
 * - Uploads or reuses deployable lab/methodology images and approved Ferdie headshot assets by _simplyrest_lab_proof_file.
 * - Skips the optional candidate Ferdie action image unless --include-candidate-ferdie is passed.
 * - Sets WordPress media title, caption, alt text, and Simply Rest tracking meta.
 * - Updates pages only when --update-pages is passed.
 *
 * Optional flags:
 * - --dry-run: report actions without writing.
 * - --update-pages: insert/replace the proof media block on target pages.
 * - --force-update-published: allow direct edits to published pages after backup/approval.
 * - --include-candidate-ferdie: import and place the optional candidate Ferdie action photo after approval.
 * - --force-reupload: upload fresh attachments even if matching Simply Rest media meta exists.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_lab_media_import.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$dry_run = in_array('--dry-run', $args, true);
$update_pages = in_array('--update-pages', $args, true);
$force_update_published = in_array('--force-update-published', $args, true);
$include_candidate_ferdie = in_array('--include-candidate-ferdie', $args, true);
$force_reupload = in_array('--force-reupload', $args, true);

$media_dir = simplyrest_lab_media_resolve_path(simplyrest_lab_media_arg_value($args, '--media-dir', ''));
$manifest_path = simplyrest_lab_media_resolve_path(simplyrest_lab_media_arg_value($args, '--manifest', 'simplyrest-lab-proof-media-upload-manifest-2026-06-25.tsv'));

if ($media_dir === '' || !is_dir($media_dir)) {
    simplyrest_lab_media_fail('Missing or invalid --media-dir. Unzip simplyrest-lab-proof-media-2026-06-25.zip first.');
}

if (!is_file($manifest_path)) {
    simplyrest_lab_media_fail('Missing manifest file: ' . $manifest_path);
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$rows = simplyrest_lab_media_read_manifest($manifest_path);
$active_rows = array();
$skipped_candidate = 0;

foreach ($rows as $row) {
    if ($row['approval_status'] === 'candidate_requires_approval' && !$include_candidate_ferdie) {
        $skipped_candidate++;
        fwrite(STDOUT, "SKIP candidate media without --include-candidate-ferdie: {$row['optimized_file']}\n");
        continue;
    }
    $active_rows[] = $row;
}

$uploaded_count = 0;
$reused_count = 0;
$attachments_by_page = array();

foreach ($active_rows as $row) {
    $filename = $row['optimized_file'];
    $source_path = $media_dir . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($source_path)) {
        simplyrest_lab_media_fail('Missing proof media file: ' . $source_path);
    }

    $existing_attachment_id = $force_reupload ? 0 : simplyrest_lab_media_find_existing_attachment($filename);

    if ($dry_run) {
        $placeholder_url = 'https://simplyrest.com/wp-content/uploads/simplyrest-lab-proof-placeholder/' . rawurlencode($filename);
        $attachment = array(
            'id' => 0,
            'url' => $placeholder_url,
            'row' => $row,
        );
        fwrite(STDOUT, "DRY RUN: would " . ($existing_attachment_id ? 'reuse/update' : 'upload') . " {$filename} for /{$row['target_page_path']}/\n");
    } else {
        if ($existing_attachment_id) {
            $attachment_id = $existing_attachment_id;
            $reused_count++;
        } else {
            $attachment_id = simplyrest_lab_media_upload_media($source_path, $row);
            $uploaded_count++;
        }

        simplyrest_lab_media_update_attachment_meta($attachment_id, $row);
        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            simplyrest_lab_media_fail('Unable to resolve uploaded media URL for attachment ID ' . $attachment_id);
        }

        $attachment = array(
            'id' => $attachment_id,
            'url' => $url,
            'row' => $row,
        );
    }

    $page_path = trim($row['target_page_path'], '/');
    if (!isset($attachments_by_page[$page_path])) {
        $attachments_by_page[$page_path] = array();
    }
    $attachments_by_page[$page_path][] = $attachment;
}

if ($update_pages) {
    foreach ($attachments_by_page as $page_path => $attachments) {
        $post = simplyrest_lab_media_find_page($page_path);
        if (!$post) {
            simplyrest_lab_media_fail('Cannot update /' . $page_path . '/ because the page was not found.');
        }

        if (!$dry_run && $post->post_status === 'publish' && !$force_update_published) {
            simplyrest_lab_media_fail('/' . $page_path . '/ is published. Rerun with --force-update-published after backup and approval.');
        }

        $section = simplyrest_lab_media_render_section($attachments);
        if ($dry_run) {
            fwrite(STDOUT, "DRY RUN: would insert proof media section on /{$page_path}/.\n");
            continue;
        }

        $updated_content = simplyrest_lab_media_insert_section((string) $post->post_content, $page_path, $section);
        $result = wp_update_post(array(
            'ID' => (int) $post->ID,
            'post_content' => $updated_content,
        ), true);

        if (is_wp_error($result)) {
            simplyrest_lab_media_fail('Failed to update /' . $page_path . '/: ' . $result->get_error_message());
        }

        update_post_meta((int) $post->ID, '_simplyrest_lab_proof_media_imported_at', gmdate('c'));
        fwrite(STDOUT, "Updated proof media section on /{$page_path}/ post_id=" . (int) $post->ID . "\n");
    }
}

fwrite(STDOUT, "Done. uploaded={$uploaded_count} reused={$reused_count} skipped_candidate={$skipped_candidate} dry_run=" . ($dry_run ? 'yes' : 'no') . " update_pages=" . ($update_pages ? 'yes' : 'no') . "\n");

function simplyrest_lab_media_arg_value($args, $name, $default) {
    foreach ($args as $arg) {
        if (strpos($arg, $name . '=') === 0) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return $default;
}

function simplyrest_lab_media_resolve_path($path) {
    if (!is_string($path) || $path === '') {
        return '';
    }

    if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
        return $path;
    }

    return getcwd() . DIRECTORY_SEPARATOR . $path;
}

function simplyrest_lab_media_fail($message) {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function simplyrest_lab_media_read_manifest($manifest_path) {
    $lines = file($manifest_path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || count($lines) < 2) {
        simplyrest_lab_media_fail('Manifest must contain a header row and at least one media row.');
    }

    $headers = str_getcsv(array_shift($lines), "\t");
    $required = array(
        'optimized_file',
        'approval_status',
        'media_type',
        'wp_media_title',
        'target_page_path',
        'alt_text',
        'caption',
    );

    foreach ($required as $column) {
        if (!in_array($column, $headers, true)) {
            simplyrest_lab_media_fail('Manifest is missing required column: ' . $column);
        }
    }

    $rows = array();
    foreach ($lines as $line_number => $line) {
        if (trim($line) === '') {
            continue;
        }

        $fields = str_getcsv($line, "\t");
        if (count($fields) !== count($headers)) {
            simplyrest_lab_media_fail('Manifest column mismatch on data row ' . ($line_number + 2));
        }

        $row = array_combine($headers, $fields);
        if (!$row || empty($row['optimized_file']) || empty($row['target_page_path'])) {
            simplyrest_lab_media_fail('Invalid manifest row on data row ' . ($line_number + 2));
        }

        if ($row['media_type'] !== 'image') {
            simplyrest_lab_media_fail('Only image proof media is supported in this importer: ' . $row['optimized_file']);
        }

        $rows[] = $row;
    }

    return $rows;
}

function simplyrest_lab_media_find_page($path) {
    $post = get_page_by_path(trim($path, '/'), OBJECT, array('page', 'post'));
    return $post instanceof WP_Post ? $post : null;
}

function simplyrest_lab_media_find_existing_attachment($filename) {
    $ids = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_simplyrest_lab_proof_file',
        'meta_value' => $filename,
    ));

    if (is_array($ids) && !empty($ids[0])) {
        return (int) $ids[0];
    }

    return 0;
}

function simplyrest_lab_media_upload_media($source_path, $row) {
    $page = simplyrest_lab_media_find_page($row['target_page_path']);
    $parent_post_id = $page ? (int) $page->ID : 0;
    $tmp_path = wp_tempnam($source_path);
    if (!$tmp_path || !copy($source_path, $tmp_path)) {
        simplyrest_lab_media_fail('Unable to stage media upload: ' . $source_path);
    }

    $file_array = array(
        'name' => basename($source_path),
        'tmp_name' => $tmp_path,
    );

    $attachment_id = media_handle_sideload($file_array, $parent_post_id, $row['wp_media_title']);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp_path);
        simplyrest_lab_media_fail('Media upload failed for ' . basename($source_path) . ': ' . $attachment_id->get_error_message());
    }

    return (int) $attachment_id;
}

function simplyrest_lab_media_update_attachment_meta($attachment_id, $row) {
    wp_update_post(array(
        'ID' => $attachment_id,
        'post_title' => $row['wp_media_title'],
        'post_excerpt' => $row['caption'],
        'post_content' => $row['caption'],
    ));

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $row['alt_text']);
    update_post_meta($attachment_id, '_simplyrest_lab_proof_file', $row['optimized_file']);
    update_post_meta($attachment_id, '_simplyrest_lab_proof_approval_status', $row['approval_status']);
    update_post_meta($attachment_id, '_simplyrest_lab_proof_target_page', $row['target_page_path']);
}

function simplyrest_lab_media_render_section($attachments) {
    $blocks = array("<!-- SR-LAB-PROOF-MEDIA-START -->");
    foreach ($attachments as $attachment) {
        $row = $attachment['row'];
        $attachment_id = (int) $attachment['id'];
        $url = esc_url($attachment['url']);
        $alt = esc_attr($row['alt_text']);
        $caption = esc_html($row['caption']);
        $class = $row['approval_status'] === 'candidate_requires_approval' ? ' simplyrest-candidate-media' : '';

        $blocks[] = '<!-- wp:image ' . wp_json_encode(array_filter(array(
            'id' => $attachment_id ?: null,
            'sizeSlug' => 'large',
            'linkDestination' => 'none',
        )), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' -->';
        $blocks[] = '<figure class="wp-block-image size-large' . $class . '"><img src="' . $url . '" alt="' . $alt . '"' . ($attachment_id ? ' class="wp-image-' . $attachment_id . '"' : '') . '/><figcaption class="wp-element-caption">' . $caption . '</figcaption></figure>';
        $blocks[] = '<!-- /wp:image -->';
    }
    $blocks[] = "<!-- SR-LAB-PROOF-MEDIA-END -->";

    return implode("\n", $blocks);
}

function simplyrest_lab_media_insert_section($content, $page_path, $section) {
    $start_marker = '<!-- SR-LAB-PROOF-MEDIA-START -->';
    $end_marker = '<!-- SR-LAB-PROOF-MEDIA-END -->';

    $start = strpos($content, $start_marker);
    if ($start !== false) {
        $end = strpos($content, $end_marker, $start);
        if ($end !== false) {
            $end += strlen($end_marker);
            return substr($content, 0, $start) . trim($section) . substr($content, $end);
        }
    }

    $heading = 'Disclosure and Review Limits';
    if ($page_path === 'how-we-test-mattresses') {
        $heading = 'Testing Disclosure and Limits';
    } elseif ($page_path === 'ferdie-farhad') {
        $heading = 'Role Disclosure';
    }

    $insert_at = simplyrest_lab_media_find_heading_block_offset($content, $heading);
    if ($insert_at !== false) {
        return substr($content, 0, $insert_at) . trim($section) . "\n\n" . substr($content, $insert_at);
    }

    return rtrim($content) . "\n\n" . trim($section) . "\n";
}

function simplyrest_lab_media_find_heading_block_offset($content, $heading) {
    $h2 = '<h2>' . $heading . '</h2>';
    $h2_pos = strpos($content, $h2);
    if ($h2_pos === false) {
        return false;
    }

    $prefix = substr($content, 0, $h2_pos);
    $block_pos = strrpos($prefix, '<!-- wp:heading');

    return $block_pos === false ? $h2_pos : $block_pos;
}
