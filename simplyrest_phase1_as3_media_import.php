<?php
/**
 * Simply Rest Phase 1 AS3 media importer.
 *
 * Run after the phase-one page importer has created /mattress-reviews/amerisleep-as3/.
 *
 * Example:
 *
 *   unzip simplyrest-as3-optimized-media-2026-06-25.zip -d simplyrest-as3-media
 *   wp eval-file simplyrest_phase1_as3_media_import.php -- \
 *     --media-dir=simplyrest-as3-media \
 *     --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
 *     --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html \
 *     --dry-run
 *
 *   wp eval-file simplyrest_phase1_as3_media_import.php -- \
 *     --media-dir=simplyrest-as3-media \
 *     --manifest=simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv \
 *     --section-template=simplyrest-as3-media-section-replacement-2026-06-25.html \
 *     --update-page
 *
 * Defaults:
 * - Uploads or reuses optimized AS3 assets by _simplyrest_as3_optimized_file.
 * - Sets WordPress media title, caption, alt text, and Simply Rest tracking meta.
 * - Sets AS3 Photo 1 as the featured image when the AS3 page exists.
 * - Renders a token-replaced Gutenberg media section into uploads.
 * - Updates the AS3 page only when --update-page is passed.
 *
 * Optional flags:
 * - --dry-run: report actions without writing.
 * - --update-page: replace the AS3 text-only evidence section with the rendered media section.
 * - --force-update-published: allow direct edits to a published AS3 page.
 * - --force-reupload: upload fresh attachments even if matching Simply Rest media meta exists.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this through WP-CLI: wp eval-file simplyrest_phase1_as3_media_import.php\n");
    exit(1);
}

$args = isset($argv) ? $argv : array();
$dry_run = in_array('--dry-run', $args, true);
$update_page = in_array('--update-page', $args, true);
$force_update_published = in_array('--force-update-published', $args, true);
$force_reupload = in_array('--force-reupload', $args, true);

$media_dir = simplyrest_phase1_resolve_path(simplyrest_phase1_arg_value($args, '--media-dir', ''));
$manifest_path = simplyrest_phase1_resolve_path(simplyrest_phase1_arg_value($args, '--manifest', 'simplyrest-as3-optimized-media-upload-manifest-2026-06-25.tsv'));
$section_template_path = simplyrest_phase1_resolve_path(simplyrest_phase1_arg_value($args, '--section-template', 'simplyrest-as3-media-section-replacement-2026-06-25.html'));

if ($media_dir === '' || !is_dir($media_dir)) {
    simplyrest_phase1_fail('Missing or invalid --media-dir. Unzip simplyrest-as3-optimized-media-2026-06-25.zip first.');
}

if (!is_file($manifest_path)) {
    simplyrest_phase1_fail('Missing manifest file: ' . $manifest_path);
}

if (!is_file($section_template_path)) {
    simplyrest_phase1_fail('Missing section template file: ' . $section_template_path);
}

$rows = simplyrest_phase1_read_manifest($manifest_path);
$as3_post = simplyrest_phase1_find_as3_post();
$as3_post_id = $as3_post ? (int) $as3_post->ID : 0;

if (!$as3_post) {
    fwrite(STDOUT, "WARNING: AS3 page not found. Media can be uploaded, but page content and featured image cannot be attached yet.\n");
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$token_urls = array();
$token_attachment_ids = array();
$uploaded_count = 0;
$reused_count = 0;

foreach ($rows as $row) {
    $filename = $row['optimized_file'];
    $source_path = $media_dir . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($source_path)) {
        simplyrest_phase1_fail('Missing optimized media file: ' . $source_path);
    }

    $existing_attachment_id = $force_reupload ? 0 : simplyrest_phase1_find_existing_attachment($filename);

    if ($dry_run) {
        $placeholder_url = 'https://simplyrest.com/wp-content/uploads/simplyrest-as3-placeholder/' . rawurlencode($filename);
        $token_urls[$row['wp_block_token']] = $placeholder_url;
        fwrite(STDOUT, "DRY RUN: would " . ($existing_attachment_id ? 'reuse/update' : 'upload') . " {$filename} for {$row['target_section']}\n");
        continue;
    }

    if ($existing_attachment_id) {
        $attachment_id = $existing_attachment_id;
        $reused_count++;
    } else {
        $attachment_id = simplyrest_phase1_upload_media($source_path, $row, $as3_post_id);
        $uploaded_count++;
    }

    simplyrest_phase1_update_attachment_meta($attachment_id, $row);

    $url = wp_get_attachment_url($attachment_id);
    if (!$url) {
        simplyrest_phase1_fail('Unable to resolve uploaded media URL for attachment ID ' . $attachment_id);
    }

    $token_urls[$row['wp_block_token']] = $url;
    $token_attachment_ids[$row['wp_block_token']] = $attachment_id;
}

$section_template = file_get_contents($section_template_path);
if (!is_string($section_template) || $section_template === '') {
    simplyrest_phase1_fail('Section template is empty: ' . $section_template_path);
}

$rendered_section = strtr($section_template, $token_urls);

if (preg_match_all('/\{\{[A-Z0-9_]+\}\}/', $rendered_section, $matches)) {
    $unresolved = array_values(array_unique($matches[0]));
    simplyrest_phase1_fail('Unresolved media tokens remain in rendered section: ' . implode(', ', $unresolved));
}

if (!$dry_run) {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        simplyrest_phase1_fail('WordPress upload directory error: ' . $uploads['error']);
    }

    $rendered_path = trailingslashit($uploads['basedir']) . 'simplyrest-as3-media-section-rendered.html';
    if (file_put_contents($rendered_path, $rendered_section) === false) {
        simplyrest_phase1_fail('Unable to write rendered section: ' . $rendered_path);
    }

    fwrite(STDOUT, "Rendered media section: {$rendered_path}\n");
}

if ($as3_post_id && !$dry_run) {
    update_post_meta($as3_post_id, '_simplyrest_as3_media_map', wp_slash(wp_json_encode(array(
        'tokens' => $token_urls,
        'attachments' => $token_attachment_ids,
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)));
    update_post_meta($as3_post_id, '_simplyrest_as3_media_imported_at', gmdate('c'));

    if (!empty($token_attachment_ids['{{AS3_PHOTO_1_URL}}']) && function_exists('set_post_thumbnail')) {
        set_post_thumbnail($as3_post_id, (int) $token_attachment_ids['{{AS3_PHOTO_1_URL}}']);
    }
}

if ($update_page) {
    if (!$as3_post) {
        simplyrest_phase1_fail('Cannot update AS3 page because /mattress-reviews/amerisleep-as3/ was not found.');
    }

    if (!$dry_run && $as3_post->post_status === 'publish' && !$force_update_published) {
        simplyrest_phase1_fail('AS3 page is published. Rerun with --force-update-published after backup and approval.');
    }

    if ($dry_run) {
        fwrite(STDOUT, "DRY RUN: would replace AS3 text-only evidence sections with rendered media section.\n");
    } else {
        $updated_content = simplyrest_phase1_replace_media_section($as3_post->post_content, $rendered_section);
        $result = wp_update_post(array(
            'ID' => $as3_post_id,
            'post_content' => $updated_content,
        ), true);

        if (is_wp_error($result)) {
            simplyrest_phase1_fail('Failed to update AS3 page: ' . $result->get_error_message());
        }

        fwrite(STDOUT, "Updated AS3 page content: post_id={$as3_post_id}\n");
    }
}

fwrite(STDOUT, "Done. uploaded={$uploaded_count} reused={$reused_count} dry_run=" . ($dry_run ? 'yes' : 'no') . " update_page=" . ($update_page ? 'yes' : 'no') . "\n");

function simplyrest_phase1_arg_value($args, $name, $default) {
    foreach ($args as $arg) {
        if (strpos($arg, $name . '=') === 0) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return $default;
}

function simplyrest_phase1_resolve_path($path) {
    if (!is_string($path) || $path === '') {
        return '';
    }

    if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
        return $path;
    }

    return getcwd() . DIRECTORY_SEPARATOR . $path;
}

function simplyrest_phase1_fail($message) {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function simplyrest_phase1_read_manifest($manifest_path) {
    $lines = file($manifest_path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || count($lines) < 2) {
        simplyrest_phase1_fail('Manifest must contain a header row and at least one media row.');
    }

    $headers = str_getcsv(array_shift($lines), "\t");
    $required = array(
        'optimized_file',
        'media_type',
        'wp_media_title',
        'alt_text',
        'caption',
        'target_section',
        'wp_block_token',
    );

    foreach ($required as $column) {
        if (!in_array($column, $headers, true)) {
            simplyrest_phase1_fail('Manifest is missing required column: ' . $column);
        }
    }

    $rows = array();
    foreach ($lines as $line_number => $line) {
        if (trim($line) === '') {
            continue;
        }

        $fields = str_getcsv($line, "\t");
        if (count($fields) !== count($headers)) {
            simplyrest_phase1_fail('Manifest column mismatch on data row ' . ($line_number + 2));
        }

        $row = array_combine($headers, $fields);
        if (!$row || empty($row['optimized_file']) || empty($row['wp_block_token'])) {
            simplyrest_phase1_fail('Invalid manifest row on data row ' . ($line_number + 2));
        }

        $rows[] = $row;
    }

    return $rows;
}

function simplyrest_phase1_find_as3_post() {
    $post = get_page_by_path('mattress-reviews/amerisleep-as3', OBJECT, array('page', 'post'));
    if ($post instanceof WP_Post) {
        return $post;
    }

    $posts = get_posts(array(
        'name' => 'amerisleep-as3',
        'post_type' => array('page', 'post'),
        'post_status' => 'any',
        'posts_per_page' => 1,
    ));

    if (is_array($posts) && !empty($posts[0]) && $posts[0] instanceof WP_Post) {
        return $posts[0];
    }

    return null;
}

function simplyrest_phase1_find_existing_attachment($filename) {
    $ids = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_simplyrest_as3_optimized_file',
        'meta_value' => $filename,
    ));

    if (is_array($ids) && !empty($ids[0])) {
        return (int) $ids[0];
    }

    return 0;
}

function simplyrest_phase1_upload_media($source_path, $row, $parent_post_id) {
    $tmp_path = wp_tempnam($source_path);
    if (!$tmp_path || !copy($source_path, $tmp_path)) {
        simplyrest_phase1_fail('Unable to stage media upload: ' . $source_path);
    }

    $file_array = array(
        'name' => basename($source_path),
        'tmp_name' => $tmp_path,
    );

    $attachment_id = media_handle_sideload($file_array, $parent_post_id, $row['wp_media_title']);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp_path);
        simplyrest_phase1_fail('Media upload failed for ' . basename($source_path) . ': ' . $attachment_id->get_error_message());
    }

    return (int) $attachment_id;
}

function simplyrest_phase1_update_attachment_meta($attachment_id, $row) {
    wp_update_post(array(
        'ID' => $attachment_id,
        'post_title' => $row['wp_media_title'],
        'post_excerpt' => $row['caption'],
        'post_content' => $row['caption'],
    ));

    if ($row['media_type'] === 'image') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $row['alt_text']);
    }

    update_post_meta($attachment_id, '_simplyrest_as3_optimized_file', $row['optimized_file']);
    update_post_meta($attachment_id, '_simplyrest_as3_wp_block_token', $row['wp_block_token']);
    update_post_meta($attachment_id, '_simplyrest_as3_target_section', $row['target_section']);
    update_post_meta($attachment_id, '_simplyrest_as3_alt_text', $row['alt_text']);
}

function simplyrest_phase1_replace_media_section($content, $rendered_section) {
    $start_marker = '<!-- SR-AS3-MEDIA-SECTION-START -->';
    $end_marker = '<!-- SR-AS3-MEDIA-SECTION-END -->';

    $marker_start = strpos($content, $start_marker);
    if ($marker_start !== false) {
        $marker_end = strpos($content, $end_marker, $marker_start);
        if ($marker_end !== false) {
            $marker_end += strlen($end_marker);
            return substr($content, 0, $marker_start) . trim($rendered_section) . substr($content, $marker_end);
        }
    }

    $start = simplyrest_phase1_find_heading_block_offset($content, 'Testing Evidence', 0);
    $end = simplyrest_phase1_find_heading_block_offset($content, 'Best For', $start === false ? 0 : $start + 1);

    if ($start !== false && $end !== false && $end > $start) {
        return substr($content, 0, $start) . trim($rendered_section) . "\n\n" . substr($content, $end);
    }

    if ($end !== false) {
        return substr($content, 0, $end) . trim($rendered_section) . "\n\n" . substr($content, $end);
    }

    return rtrim($content) . "\n\n" . trim($rendered_section) . "\n";
}

function simplyrest_phase1_find_heading_block_offset($content, $heading, $offset) {
    $h2 = '<h2>' . $heading . '</h2>';
    $h2_pos = strpos($content, $h2, $offset);
    if ($h2_pos === false) {
        return false;
    }

    $prefix = substr($content, 0, $h2_pos);
    $block_pos = strrpos($prefix, '<!-- wp:heading');

    return $block_pos === false ? $h2_pos : $block_pos;
}
