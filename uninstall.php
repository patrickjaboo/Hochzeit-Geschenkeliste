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

$hochzeit_geschenkeliste_timestamp = wp_next_scheduled('hochzeit_geschenkeliste_cleanup');
if ($hochzeit_geschenkeliste_timestamp) {
    wp_unschedule_event($hochzeit_geschenkeliste_timestamp, 'hochzeit_geschenkeliste_cleanup');
}

if (defined('HOCHZEIT_GESCHENKELISTE_DELETE_DATA_ON_UNINSTALL') && HOCHZEIT_GESCHENKELISTE_DELETE_DATA_ON_UNINSTALL) {
    global $wpdb;

    $hochzeit_geschenkeliste_table_name = esc_sql($wpdb->prefix . 'hochzeit_geschenkeliste');
    $hochzeit_geschenkeliste_table_reservations = esc_sql($wpdb->prefix . 'hochzeit_geschenkeliste_reservierungen');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Optional uninstall cleanup for this plugin's own custom table.
    $wpdb->query('DROP TABLE IF EXISTS ' . $hochzeit_geschenkeliste_table_reservations);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Optional uninstall cleanup for this plugin's own custom table.
    $wpdb->query('DROP TABLE IF EXISTS ' . $hochzeit_geschenkeliste_table_name);
}
