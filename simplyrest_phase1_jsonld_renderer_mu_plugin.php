<?php
/**
 * Plugin Name: Simply Rest Phase 1 JSON-LD Renderer
 * Description: Outputs Simply Rest JSON-LD stored in approved Simply Rest page meta keys on singular pages.
 * Version: 1.1.0
 * Author: One Sleep Group
 *
 * Install by placing this file at:
 * wp-content/mu-plugins/simplyrest-phase1-jsonld.php
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', 'simplyrest_phase1_render_json_ld', 20);

function simplyrest_phase1_render_json_ld() {
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $schema_meta_keys = array(
        '_simplyrest_json_ld',
        '_simplyrest_retrofit_json_ld',
    );

    foreach ($schema_meta_keys as $schema_meta_key) {
        $raw_json = get_post_meta($post_id, $schema_meta_key, true);
        if (!is_string($raw_json) || trim($raw_json) === '') {
            continue;
        }

        $decoded = json_decode($raw_json, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }

        $encoded = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            continue;
        }

        $script_id = sanitize_key(str_replace('_', '-', trim($schema_meta_key, '_')));
        echo "\n<script type=\"application/ld+json\" id=\"{$script_id}\">" . $encoded . "</script>\n";
    }
}
