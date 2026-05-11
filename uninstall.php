<?php
/**
 * Uninstall file for Hochzeit Geschenkeliste.
 *
 * If `HOCHZEIT_GESCHENKELISTE_DELETE_DATA_ON_UNINSTALL` is defined and true,
 * custom database tables will be removed as well.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$timestamp = wp_next_scheduled('geschenkeliste_cleanup');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'geschenkeliste_cleanup');
}

if (defined('HOCHZEIT_GESCHENKELISTE_DELETE_DATA_ON_UNINSTALL') && HOCHZEIT_GESCHENKELISTE_DELETE_DATA_ON_UNINSTALL) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'geschenkeliste';
    $table_reservations = $wpdb->prefix . 'geschenkeliste_reservierungen';

    $wpdb->query("DROP TABLE IF EXISTS {$table_reservations}");
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}
