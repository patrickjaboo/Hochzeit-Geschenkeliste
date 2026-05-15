<?php
/**
 * Plugin Name: Hochzeit Geschenkeliste
 * Description: Ein Plugin zur Verwaltung einer Hochzeits-Geschenkeliste mit Frontend-Anzeige, Reservierungen und E-Mail-Verifizierung.
 * Version: 1.1.0
 * Author: Patrick Janssen-Booms
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hochzeit-geschenkeliste
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HOCHZEIT_GESCHENKELISTE_VERSION', '1.1.0');
define('HOCHZEIT_GESCHENKELISTE_DB_VERSION', '1.1.1');

class Hochzeit_Geschenkeliste {

    private const CACHE_GROUP = 'hochzeit_geschenkeliste';
    private const CLEANUP_HOOK = 'hochzeit_geschenkeliste_cleanup';

    private $table_name;
    private $table_reservations;
    private $frontend_text_option_name = 'hochzeit_geschenkeliste_frontend_texts';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hochzeit_geschenkeliste';
        $this->table_reservations = $wpdb->prefix . 'hochzeit_geschenkeliste_reservierungen';

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'maybe_upgrade_database'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_privacy_policy_content'));
        add_action('admin_init', array($this, 'register_frontend_text_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('hochzeit_geschenkeliste', array($this, 'render_frontend'));

        add_action('wp_ajax_hochzeit_geschenkeliste_reserve_geschenk', array($this, 'ajax_reserve_geschenk'));
        add_action('wp_ajax_nopriv_hochzeit_geschenkeliste_reserve_geschenk', array($this, 'ajax_reserve_geschenk'));

        add_action('wp_ajax_hochzeit_geschenkeliste_add_geschenk', array($this, 'ajax_add_geschenk'));
        add_action('wp_ajax_hochzeit_geschenkeliste_get_geschenk', array($this, 'ajax_get_geschenk'));
        add_action('wp_ajax_hochzeit_geschenkeliste_update_geschenk', array($this, 'ajax_update_geschenk'));
        add_action('wp_ajax_hochzeit_geschenkeliste_delete_geschenk', array($this, 'ajax_delete_geschenk'));
        add_action('wp_ajax_hochzeit_geschenkeliste_cancel_reservation', array($this, 'ajax_cancel_reservation'));

        add_action('template_redirect', array($this, 'handle_verification'));
        add_action('template_redirect', array($this, 'handle_cancellation'));

        add_action(self::CLEANUP_HOOK, array($this, 'cleanup_old_reservations'));

        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_personal_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_personal_data_eraser'));
    }

    public function activate() {
        $this->install_database_schema();
    }

    public function maybe_upgrade_database() {
        if (get_option('hochzeit_geschenkeliste_db_version') === HOCHZEIT_GESCHENKELISTE_DB_VERSION) {
            return;
        }

        $this->install_database_schema();
    }

    private function install_database_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql_geschenke = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            titel varchar(255) NOT NULL,
            beschreibung text,
            link varchar(500),
            bild_url varchar(500),
            erstellt_am datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_reservierungen = "CREATE TABLE IF NOT EXISTS {$this->table_reservations} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            geschenk_id mediumint(9) NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255),
            verification_token varchar(64),
            is_verified tinyint(1) DEFAULT 0,
            reserviert_am datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY geschenk_id (geschenk_id),
            KEY verification_token (verification_token)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $this->migrate_legacy_tables();
        dbDelta($sql_geschenke);
        dbDelta($sql_reservierungen);

        // Prüfen und hinzufügen fehlender Spalten für bestehende Installationen.
        $columns = $this->get_reservation_columns(true);

        if (!in_array('verification_token', $columns)) {
            $this->db_query('ALTER TABLE ' . esc_sql($this->table_reservations) . ' ADD COLUMN verification_token varchar(64) AFTER name');
            $this->db_query('ALTER TABLE ' . esc_sql($this->table_reservations) . ' ADD INDEX (verification_token)');
        }

        if (!in_array('is_verified', $columns)) {
            $this->db_query('ALTER TABLE ' . esc_sql($this->table_reservations) . ' ADD COLUMN is_verified tinyint(1) DEFAULT 0 AFTER verification_token');
        }

        $this->clear_cache();

        $this->migrate_legacy_options();
        $this->unschedule_legacy_cleanup_hook();

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CLEANUP_HOOK);
        }

        update_option('hochzeit_geschenkeliste_db_version', HOCHZEIT_GESCHENKELISTE_DB_VERSION);
    }

    public function deactivate() {
        // Cronjob entfernen
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }

        $this->unschedule_legacy_cleanup_hook();
    }

    private function db_prepare($query, ...$args) {
        global $wpdb;

        if (empty($args)) {
            return $query;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Custom table identifiers are sanitized before queries reach this wrapper.
        return $wpdb->prepare($query, $args);
    }

    private function db_get_results($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This plugin stores its own custom table data; callers pass prepared queries and cache reusable reads.
        return $wpdb->get_results($query, $output);
    }

    private function db_get_row($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This plugin stores its own custom table data; callers pass prepared queries and cache reusable reads.
        return $wpdb->get_row($query, $output);
    }

    private function db_get_col($query, $column_offset = 0) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This plugin stores its own custom table data; callers pass prepared queries and cache reusable reads.
        return $wpdb->get_col($query, $column_offset);
    }

    private function db_get_var($query) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This plugin stores its own custom table data; callers pass prepared queries and cache reusable reads.
        return $wpdb->get_var($query);
    }

    private function db_query($query) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table schema and cleanup are managed by this plugin.
        return $wpdb->query($query);
    }

    private function db_insert($table, $data, $format = null) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This plugin stores its own custom table data.
        return $wpdb->insert($table, $data, $format);
    }

    private function db_update($table, $data, $where, $format = null, $where_format = null) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin stores its own custom table data.
        return $wpdb->update($table, $data, $where, $format, $where_format);
    }

    private function db_delete($table, $where, $where_format = null) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin stores its own custom table data.
        return $wpdb->delete($table, $where, $where_format);
    }

    private function get_reservation_columns($force_refresh = false) {
        $cache_key = 'reservation_columns';
        $columns = false;

        if (!$force_refresh) {
            $columns = wp_cache_get($cache_key, self::CACHE_GROUP);
        }

        if (false === $columns) {
            $columns = $this->db_get_col('DESC ' . esc_sql($this->table_reservations), 0);
            wp_cache_set($cache_key, $columns, self::CACHE_GROUP);
        }

        return $columns;
    }

    private function clear_cache() {
        wp_cache_delete('reservation_columns', self::CACHE_GROUP);
        wp_cache_delete('admin_gifts_verified', self::CACHE_GROUP);
        wp_cache_delete('admin_gifts_legacy', self::CACHE_GROUP);
        wp_cache_delete('frontend_gifts_verified', self::CACHE_GROUP);
        wp_cache_delete('frontend_gifts_legacy', self::CACHE_GROUP);
    }

    private function get_query_arg($key) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public email links are authenticated with a single-use random token instead of a nonce.
        if (!isset($_GET[$key])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public email links are authenticated with a single-use random token instead of a nonce.
        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    private function database_table_exists($table_name) {
        global $wpdb;

        $escaped_table = esc_sql($table_name);

        return $escaped_table === $this->db_get_var(
            $this->db_prepare(
                'SHOW TABLES LIKE %s',
                $escaped_table
            )
        );
    }

    private function migrate_legacy_tables() {
        global $wpdb;

        $legacy_table_name = $wpdb->prefix . 'geschenkeliste';
        $legacy_table_reservations = $wpdb->prefix . 'geschenkeliste_reservierungen';

        if ($this->database_table_exists($legacy_table_name) && !$this->database_table_exists($this->table_name)) {
            $this->db_query('RENAME TABLE ' . esc_sql($legacy_table_name) . ' TO ' . esc_sql($this->table_name));
        }

        if ($this->database_table_exists($legacy_table_reservations) && !$this->database_table_exists($this->table_reservations)) {
            $this->db_query('RENAME TABLE ' . esc_sql($legacy_table_reservations) . ' TO ' . esc_sql($this->table_reservations));
        }
    }

    private function migrate_legacy_options() {
        $legacy_option_name = 'geschenkeliste_frontend_texts';
        $legacy_value = get_option($legacy_option_name, null);

        if (null !== $legacy_value && false === get_option($this->frontend_text_option_name, false)) {
            update_option($this->frontend_text_option_name, $legacy_value);
            delete_option($legacy_option_name);
        }
    }

    private function unschedule_legacy_cleanup_hook() {
        $legacy_timestamp = wp_next_scheduled('geschenkeliste_cleanup');

        if ($legacy_timestamp) {
            wp_unschedule_event($legacy_timestamp, 'geschenkeliste_cleanup');
        }
    }

    public function register_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__('Das Plugin "Hochzeit Geschenkeliste" speichert bei Reservierungen optional einen Namen, die E-Mail-Adresse, einen Verifizierungs-Token und den Zeitstempel der Reservierung. Diese Daten werden verwendet, um Reservierungen zu bestätigen, ggf. zu stornieren und Missbrauch zu vermeiden.', 'hochzeit-geschenkeliste') . '</p>';
        $content .= '<p>' . esc_html__('Unbestätigte Reservierungen werden nach 24 Stunden automatisch entfernt. Bestätigte Reservierungen können durch Administratoren oder per Stornierungslink gelöscht werden.', 'hochzeit-geschenkeliste') . '</p>';

        wp_add_privacy_policy_content(
            esc_html__('Hochzeit Geschenkeliste', 'hochzeit-geschenkeliste'),
            wp_kses_post($content)
        );
    }

    public function register_personal_data_exporter($exporters) {
        $exporters['hochzeit-geschenkeliste-reservierungen'] = array(
            'exporter_friendly_name' => esc_html__('Hochzeit Geschenkeliste Reservierungen', 'hochzeit-geschenkeliste'),
            'callback' => array($this, 'personal_data_exporter'),
        );

        return $exporters;
    }

    public function register_personal_data_eraser($erasers) {
        $erasers['hochzeit-geschenkeliste-reservierungen'] = array(
            'eraser_friendly_name' => esc_html__('Hochzeit Geschenkeliste Reservierungen', 'hochzeit-geschenkeliste'),
            'callback' => array($this, 'personal_data_eraser'),
        );

        return $erasers;
    }

    public function personal_data_exporter($email_address, $page = 1) {
        $email = sanitize_email($email_address);
        $page = (int) $page;
        $number = 100;
        $offset = ($page - 1) * $number;
        $reservations_table = esc_sql($this->table_reservations);
        $gifts_table = esc_sql($this->table_name);

        $results = $this->db_get_results(
            $this->db_prepare(
                "SELECT r.id, r.email, r.name, r.reserviert_am, r.is_verified, g.titel
                FROM {$reservations_table} r
                LEFT JOIN {$gifts_table} g ON r.geschenk_id = g.id
                WHERE r.email = %s
                ORDER BY r.id ASC
                LIMIT %d OFFSET %d",
                $email,
                $number,
                $offset
            )
        );

        $data = array();
        foreach ($results as $reservation) {
            $data[] = array(
                'group_id' => 'hochzeit-geschenkeliste',
                'group_label' => esc_html__('Hochzeit Geschenkeliste', 'hochzeit-geschenkeliste'),
                'item_id' => 'reservation-' . (int) $reservation->id,
                'data' => array(
                    array(
                        'name' => esc_html__('E-Mail-Adresse', 'hochzeit-geschenkeliste'),
                        'value' => sanitize_email($reservation->email),
                    ),
                    array(
                        'name' => esc_html__('Name', 'hochzeit-geschenkeliste'),
                        'value' => sanitize_text_field($reservation->name),
                    ),
                    array(
                        'name' => esc_html__('Geschenk', 'hochzeit-geschenkeliste'),
                        'value' => sanitize_text_field($reservation->titel),
                    ),
                    array(
                        'name' => esc_html__('Status', 'hochzeit-geschenkeliste'),
                        'value' => (int) $reservation->is_verified === 1 ? esc_html__('Bestätigt', 'hochzeit-geschenkeliste') : esc_html__('Nicht bestätigt', 'hochzeit-geschenkeliste'),
                    ),
                    array(
                        'name' => esc_html__('Reserviert am', 'hochzeit-geschenkeliste'),
                        'value' => sanitize_text_field($reservation->reserviert_am),
                    ),
                ),
            );
        }

        return array(
            'data' => $data,
            'done' => count($results) < $number,
        );
    }

    public function personal_data_eraser($email_address, $page = 1) {
        $email = sanitize_email($email_address);
        $page = (int) $page;
        $number = 100;
        $offset = ($page - 1) * $number;
        $reservations_table = esc_sql($this->table_reservations);

        $ids = $this->db_get_col(
            $this->db_prepare(
                "SELECT id FROM {$reservations_table}
                WHERE email = %s
                ORDER BY id ASC
                LIMIT %d OFFSET %d",
                $email,
                $number,
                $offset
            )
        );

        $items_removed = false;
        foreach ($ids as $id) {
            $deleted = $this->db_delete(
                $this->table_reservations,
                array('id' => (int) $id),
                array('%d')
            );

            if ($deleted) {
                $items_removed = true;
                $this->clear_cache();
            }
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => false,
            'messages' => array(),
            'done' => count($ids) < $number,
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Geschenkeliste',
            'Geschenkeliste',
            'manage_options',
            'hochzeit-geschenkeliste',
            array($this, 'render_admin_page'),
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'hochzeit-geschenkeliste',
            'Geschenke verwalten',
            'Geschenke',
            'manage_options',
            'hochzeit-geschenkeliste',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'hochzeit-geschenkeliste',
            'Frontend-Texte',
            'Frontend-Texte',
            'manage_options',
            'hochzeit-geschenkeliste-texte',
            array($this, 'render_frontend_text_settings_page')
        );

        add_submenu_page(
            'hochzeit-geschenkeliste',
            'Shortcode-Anleitung',
            'Shortcode-Anleitung',
            'manage_options',
            'hochzeit-geschenkeliste-shortcode',
            array($this, 'render_shortcode_help_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'hochzeit-geschenkeliste') === false) {
            return;
        }

        wp_enqueue_style(
            'hochzeit-geschenkeliste-admin-css',
            plugin_dir_url(__FILE__) . 'css/admin-style.css',
            array(),
            HOCHZEIT_GESCHENKELISTE_VERSION
        );

        if ($hook === 'toplevel_page_hochzeit-geschenkeliste') {
            wp_enqueue_media();
            wp_enqueue_script(
                'hochzeit-geschenkeliste-admin-js',
                plugin_dir_url(__FILE__) . 'js/admin-script.js',
                array('jquery'),
                HOCHZEIT_GESCHENKELISTE_VERSION,
                true
            );
            wp_localize_script('hochzeit-geschenkeliste-admin-js', 'hochzeitGeschenkelisteAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hochzeit_geschenkeliste_admin_nonce')
            ));
        }
    }

    public function register_frontend_text_settings() {
        register_setting(
            'hochzeit_geschenkeliste_frontend_texts_group',
            $this->frontend_text_option_name,
            array($this, 'sanitize_frontend_text_settings')
        );
    }

    public function sanitize_frontend_text_settings($input) {
        $defaults = $this->get_default_frontend_texts();
        $input = is_array($input) ? $input : array();

        return array(
            'title' => isset($input['title']) ? sanitize_text_field($input['title']) : $defaults['title'],
            'intro' => isset($input['intro']) ? sanitize_textarea_field($input['intro']) : $defaults['intro'],
            'empty' => isset($input['empty']) ? sanitize_text_field($input['empty']) : $defaults['empty'],
            'reserve_button' => isset($input['reserve_button']) ? sanitize_text_field($input['reserve_button']) : $defaults['reserve_button'],
            'modal_title' => isset($input['modal_title']) ? sanitize_text_field($input['modal_title']) : $defaults['modal_title'],
            'modal_intro' => isset($input['modal_intro']) ? sanitize_textarea_field($input['modal_intro']) : $defaults['modal_intro'],
        );
    }

    private function get_default_frontend_texts() {
        return array(
            'title' => 'Unsere Geschenkeliste',
            'intro' => 'Vielen Dank, dass ihr an unsere Hochzeit denkt! Hier findet ihr einige Geschenkideen. Wenn ihr euch für etwas entschieden habt, könnt ihr es direkt hier reservieren.',
            'empty' => 'Momentan sind keine Geschenke in der Liste vorhanden.',
            'reserve_button' => 'Geschenk reservieren',
            'modal_title' => 'Geschenk reservieren',
            'modal_intro' => 'Bitte gebt eure Kontaktdaten ein, damit wir euch zuordnen können:',
        );
    }

    private function get_frontend_texts() {
        $defaults = $this->get_default_frontend_texts();
        $saved = get_option($this->frontend_text_option_name, array());

        if (!is_array($saved)) {
            return $defaults;
        }

        return wp_parse_args($saved, $defaults);
    }

    public function enqueue_frontend_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'hochzeit_geschenkeliste')) {
            wp_enqueue_style(
                'hochzeit-geschenkeliste-frontend-css',
                plugin_dir_url(__FILE__) . 'css/frontend-style.css',
                array(),
                HOCHZEIT_GESCHENKELISTE_VERSION
            );
            wp_enqueue_script(
                'hochzeit-geschenkeliste-frontend-js',
                plugin_dir_url(__FILE__) . 'js/frontend-script.js',
                array('jquery'),
                HOCHZEIT_GESCHENKELISTE_VERSION,
                true
            );
            wp_localize_script('hochzeit-geschenkeliste-frontend-js', 'hochzeitGeschenkeliste', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hochzeit_geschenkeliste_frontend_nonce')
            ));
        }
    }

    public function render_admin_page() {
        // Prüfen ob is_verified Spalte existiert
        $columns = $this->get_reservation_columns();
        $has_verification = in_array('is_verified', $columns);
        $cache_key = $has_verification ? 'admin_gifts_verified' : 'admin_gifts_legacy';
        $geschenke = wp_cache_get($cache_key, self::CACHE_GROUP);
        $gifts_table = esc_sql($this->table_name);
        $reservations_table = esc_sql($this->table_reservations);

        if (false === $geschenke && $has_verification) {
            $geschenke = $this->db_get_results("
                SELECT g.*,
                       r.email,
                       r.name,
                       r.reserviert_am,
                       r.is_verified,
                       CASE WHEN r.id IS NOT NULL AND r.is_verified = 1 THEN 1 ELSE 0 END as ist_reserviert
                FROM {$gifts_table} g
                LEFT JOIN {$reservations_table} r ON g.id = r.geschenk_id AND r.is_verified = 1
                ORDER BY g.erstellt_am DESC
            ");
            wp_cache_set($cache_key, $geschenke, self::CACHE_GROUP);
        } elseif (false === $geschenke) {
            // Fallback für alte Installationen ohne Verifizierung
            $geschenke = $this->db_get_results("
                SELECT g.*,
                       r.email,
                       r.name,
                       r.reserviert_am,
                       0 as is_verified,
                       CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as ist_reserviert
                FROM {$gifts_table} g
                LEFT JOIN {$reservations_table} r ON g.id = r.geschenk_id
                ORDER BY g.erstellt_am DESC
            ");
            wp_cache_set($cache_key, $geschenke, self::CACHE_GROUP);
        }

        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }

    public function render_frontend_text_settings_page() {
        $frontend_texts = $this->get_frontend_texts();
        include plugin_dir_path(__FILE__) . 'templates/admin-texts-page.php';
    }

    public function render_shortcode_help_page() {
        include plugin_dir_path(__FILE__) . 'templates/admin-shortcode-help-page.php';
    }

    public function render_frontend() {
        // Prüfen ob is_verified Spalte existiert
        $columns = $this->get_reservation_columns();
        $has_verification = in_array('is_verified', $columns);
        $cache_key = $has_verification ? 'frontend_gifts_verified' : 'frontend_gifts_legacy';
        $geschenke = wp_cache_get($cache_key, self::CACHE_GROUP);
        $gifts_table = esc_sql($this->table_name);
        $reservations_table = esc_sql($this->table_reservations);

        if (false === $geschenke && $has_verification) {
            $geschenke = $this->db_get_results("
                SELECT g.*,
                       CASE WHEN r.id IS NOT NULL AND r.is_verified = 1 THEN 1 ELSE 0 END as ist_reserviert
                FROM {$gifts_table} g
                LEFT JOIN {$reservations_table} r ON g.id = r.geschenk_id AND r.is_verified = 1
                ORDER BY g.erstellt_am ASC
            ");
            wp_cache_set($cache_key, $geschenke, self::CACHE_GROUP);
        } elseif (false === $geschenke) {
            // Fallback für alte Installationen ohne Verifizierung
            $geschenke = $this->db_get_results("
                SELECT g.*,
                       CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as ist_reserviert
                FROM {$gifts_table} g
                LEFT JOIN {$reservations_table} r ON g.id = r.geschenk_id
                ORDER BY g.erstellt_am ASC
            ");
            wp_cache_set($cache_key, $geschenke, self::CACHE_GROUP);
        }

        $frontend_texts = $this->get_frontend_texts();

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/frontend-page.php';
        return ob_get_clean();
    }

    public function ajax_reserve_geschenk() {
        check_ajax_referer('hochzeit_geschenkeliste_frontend_nonce', 'nonce');

        $geschenk_id = isset($_POST['geschenk_id']) ? intval(wp_unslash($_POST['geschenk_id'])) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $reservations_table = esc_sql($this->table_reservations);
        $gifts_table = esc_sql($this->table_name);

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'));
        }

        $already_reserved = $this->db_get_var($this->db_prepare(
            "SELECT id FROM {$reservations_table} WHERE geschenk_id = %d AND is_verified = 1",
            $geschenk_id
        ));

        if ($already_reserved) {
            wp_send_json_error(array('message' => 'Dieses Geschenk wurde bereits reserviert.'));
        }

        // Token generieren
        $verification_token = bin2hex(random_bytes(32));

        $result = $this->db_insert(
            $this->table_reservations,
            array(
                'geschenk_id' => $geschenk_id,
                'email' => $email,
                'name' => $name,
                'verification_token' => $verification_token,
                'is_verified' => 0,
                'reserviert_am' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            $this->clear_cache();

            // Geschenk-Daten holen
            $geschenk = $this->db_get_row($this->db_prepare(
                "SELECT * FROM {$gifts_table} WHERE id = %d",
                $geschenk_id
            ));

            // E-Mail versenden
            $this->send_verification_email($email, $name, $geschenk, $verification_token);

            wp_send_json_success(array('message' => 'Vielen Dank! Bitte überprüfe Deine E-Mails und bestätige die Reservierung über den Link.'));
        } else {
            wp_send_json_error(array('message' => 'Es ist ein Fehler aufgetreten. Bitte versuche es erneut.'));
        }
    }

    public function ajax_add_geschenk() {
        check_ajax_referer('hochzeit_geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $result = $this->db_insert(
            $this->table_name,
            array(
                'titel' => isset($_POST['titel']) ? sanitize_text_field(wp_unslash($_POST['titel'])) : '',
                'beschreibung' => isset($_POST['beschreibung']) ? wp_kses_post(wp_unslash($_POST['beschreibung'])) : '',
                'link' => isset($_POST['link']) ? esc_url_raw(wp_unslash($_POST['link'])) : '',
                'bild_url' => isset($_POST['bild_url']) ? esc_url_raw(wp_unslash($_POST['bild_url'])) : '',
                'erstellt_am' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $this->clear_cache();
            wp_send_json_success(array('message' => 'Geschenk erfolgreich hinzugefügt!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Hinzufügen.'));
        }
    }

    public function ajax_update_geschenk() {
        check_ajax_referer('hochzeit_geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $result = $this->db_update(
            $this->table_name,
            array(
                'titel' => isset($_POST['titel']) ? sanitize_text_field(wp_unslash($_POST['titel'])) : '',
                'beschreibung' => isset($_POST['beschreibung']) ? wp_kses_post(wp_unslash($_POST['beschreibung'])) : '',
                'link' => isset($_POST['link']) ? esc_url_raw(wp_unslash($_POST['link'])) : '',
                'bild_url' => isset($_POST['bild_url']) ? esc_url_raw(wp_unslash($_POST['bild_url'])) : ''
            ),
            array('id' => isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            $this->clear_cache();
            wp_send_json_success(array('message' => 'Geschenk erfolgreich aktualisiert!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aktualisieren.'));
        }
    }

    public function ajax_delete_geschenk() {
        check_ajax_referer('hochzeit_geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $geschenk_id = isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0;

        $this->db_delete($this->table_reservations, array('geschenk_id' => $geschenk_id), array('%d'));
        $result = $this->db_delete($this->table_name, array('id' => $geschenk_id), array('%d'));

        if ($result) {
            $this->clear_cache();
            wp_send_json_success(array('message' => 'Geschenk erfolgreich gelöscht!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Löschen.'));
        }
    }

    public function ajax_cancel_reservation() {
        check_ajax_referer('hochzeit_geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $geschenk_id = isset($_POST['geschenk_id']) ? intval(wp_unslash($_POST['geschenk_id'])) : 0;

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        $result = $this->db_delete(
            $this->table_reservations,
            array('geschenk_id' => $geschenk_id),
            array('%d')
        );

        if ($result) {
            $this->clear_cache();
            wp_send_json_success(array('message' => 'Reservierung erfolgreich aufgehoben!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aufheben der Reservierung.'));
        }
    }

    public function ajax_get_geschenk() {
        check_ajax_referer('hochzeit_geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $geschenk_id = isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0;
        $gifts_table = esc_sql($this->table_name);

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        $geschenk = $this->db_get_row(
            $this->db_prepare(
                "SELECT id, titel, beschreibung, link, bild_url
                FROM {$gifts_table}
                WHERE id = %d",
                $geschenk_id
            ),
            ARRAY_A
        );

        if (!$geschenk) {
            wp_send_json_error(array('message' => 'Geschenk wurde nicht gefunden.'));
        }

        wp_send_json_success($geschenk);
    }

    private function send_verification_email($email, $name, $geschenk, $token) {
        $site_url = home_url();
        $verify_url = add_query_arg(array(
            'action' => 'hochzeit_geschenkeliste_verify_reservation',
            'token' => $token
        ), $site_url);

        $cancel_url = add_query_arg(array(
            'action' => 'hochzeit_geschenkeliste_cancel_reservation_guest',
            'token' => $token
        ), $site_url);

        $anrede = $name ? sprintf('Hallo %s', esc_html($name)) : 'Hallo';
        $verify_url = esc_url($verify_url);
        $cancel_url = esc_url($cancel_url);
        $geschenk_titel = esc_html($geschenk->titel);
        $geschenk_beschreibung = !empty($geschenk->beschreibung) ? wp_kses_post($geschenk->beschreibung) : '';

        $subject = 'Bestätigung Ihrer Geschenk-Reservierung';

        $message = "
        <html>
        <head></head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #0073aa; color: #fff; padding: 20px; text-align: center;'>
                    <h1 style='margin: 0;'>🎁 Geschenk-Reservierung</h1>
                </div>
                <div style='padding: 20px; background: #f9f9f9;'>
                    <p>{$anrede},</p>
                    <p>vielen Dank für Deine Reservierung! Bitte bestätige diese, indem Du auf den untenstehenden Link klickst:</p>

                    <div style='background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;'>
                        Geschenk reservieren:<br>
                        <h3 style='margin: 10px 0;'>{$geschenk_titel}</h3>
                        " . ($geschenk_beschreibung ? "<p>{$geschenk_beschreibung}</p>" : "") . "
                    </div>

                    <p style='text-align: center;'>
                        <a href='{$verify_url}' style='display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px;'>Reservierung jetzt bestätigen</a>
                    </p>

                    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                        Reservierung stornieren?<br>
                        Falls Du es dir anders überlegt hast, kannst Du deine Reservierung hier stornieren:<br>
                        <a href='{$cancel_url}' style='display: inline-block; padding: 12px 24px; background: #999; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px;'>Reservierung stornieren</a>
                    </p>
                </div>
                <div style='text-align: center; padding: 20px; color: #999; font-size: 12px;'>
                    <p style='margin: 0;'>Diese E-Mail wurde automatisch generiert. Bitte antworte nicht darauf.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        wp_mail($email, $subject, $message, $headers);
    }

    public function handle_verification() {
        $action = $this->get_query_arg('action');

        if ('hochzeit_geschenkeliste_verify_reservation' !== $action) {
            return;
        }

        $token = $this->get_query_arg('token');

        if (empty($token)) {
            wp_die('Ungültiger Verifizierungslink.');
        }

        $reservations_table = esc_sql($this->table_reservations);

        $reservation = $this->db_get_row($this->db_prepare(
            "SELECT * FROM {$reservations_table} WHERE verification_token = %s",
            $token
        ));

        if (!$reservation) {
            wp_die('Ungültiger oder abgelaufener Verifizierungslink.');
        }

        if ($reservation->is_verified) {
            wp_die('Diese Reservierung wurde bereits bestätigt.');
        }

        // Prüfen ob das Geschenk inzwischen von jemand anderem reserviert wurde
        $already_verified = $this->db_get_var($this->db_prepare(
            "SELECT id FROM {$reservations_table}
             WHERE geschenk_id = %d AND is_verified = 1 AND id != %d",
            $reservation->geschenk_id,
            $reservation->id
        ));

        if ($already_verified) {
            wp_die('Dieses Geschenk wurde leider bereits von jemand anderem reserviert.');
        }

        // Verifizierung durchführen
        $this->db_update(
            $this->table_reservations,
            array('is_verified' => 1),
            array('id' => $reservation->id),
            array('%d'),
            array('%d')
        );

        // Alte, nicht verifizierte Reservierungen für dieses Geschenk löschen
        $this->db_delete(
            $this->table_reservations,
            array(
                'geschenk_id' => $reservation->geschenk_id,
                'is_verified' => 0
            ),
            array('%d', '%d')
        );

        $this->clear_cache();

        wp_die('
            <h1>✓ Reservierung bestätigt!</h1>
            <p>Vielen Dank! Deine Reservierung wurde erfolgreich bestätigt.</p>
            <p><a href="' . esc_url(home_url()) . '">Zurück zur Website</a></p>
        ');
    }

    public function handle_cancellation() {
        $action = $this->get_query_arg('action');

        if ('hochzeit_geschenkeliste_cancel_reservation_guest' !== $action) {
            return;
        }

        $token = $this->get_query_arg('token');

        if (empty($token)) {
            wp_die('Ungültiger Stornierungslink.');
        }

        $reservations_table = esc_sql($this->table_reservations);

        $reservation = $this->db_get_row($this->db_prepare(
            "SELECT * FROM {$reservations_table} WHERE verification_token = %s",
            $token
        ));

        if (!$reservation) {
            wp_die('Ungültiger oder abgelaufener Stornierungslink.');
        }

        // Reservierung löschen
        $this->db_delete(
            $this->table_reservations,
            array('id' => $reservation->id),
            array('%d')
        );

        $this->clear_cache();

        wp_die('
            <h1>✓ Reservierung storniert</h1>
            <p>Deine Reservierung wurde erfolgreich storniert. Das Geschenk ist nun wieder für andere verfügbar.</p>
            <p><a href="' . esc_url(home_url()) . '">Zurück zur Website</a></p>
        ');
    }

    public function cleanup_old_reservations() {
        // Lösche unbestätigte Reservierungen, die älter als 24 Stunden sind
        $this->db_query("
            DELETE FROM " . esc_sql($this->table_reservations) . "
            WHERE is_verified = 0
            AND reserviert_am < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        $this->clear_cache();
    }
}

new Hochzeit_Geschenkeliste();
