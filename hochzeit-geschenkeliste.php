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

class Hochzeit_Geschenkeliste {

    private $table_name;
    private $table_reservations;
    private $frontend_text_option_name = 'geschenkeliste_frontend_texts';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'geschenkeliste';
        $this->table_reservations = $wpdb->prefix . 'geschenkeliste_reservierungen';

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_privacy_policy_content'));
        add_action('admin_init', array($this, 'register_frontend_text_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('geschenkeliste', array($this, 'render_frontend'));

        add_action('wp_ajax_reserve_geschenk', array($this, 'ajax_reserve_geschenk'));
        add_action('wp_ajax_nopriv_reserve_geschenk', array($this, 'ajax_reserve_geschenk'));

        add_action('wp_ajax_add_geschenk', array($this, 'ajax_add_geschenk'));
        add_action('wp_ajax_get_geschenk', array($this, 'ajax_get_geschenk'));
        add_action('wp_ajax_update_geschenk', array($this, 'ajax_update_geschenk'));
        add_action('wp_ajax_delete_geschenk', array($this, 'ajax_delete_geschenk'));
        add_action('wp_ajax_cancel_reservation', array($this, 'ajax_cancel_reservation'));

        add_action('template_redirect', array($this, 'handle_verification'));
        add_action('template_redirect', array($this, 'handle_cancellation'));

        add_action('geschenkeliste_cleanup', array($this, 'cleanup_old_reservations'));

        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_personal_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_personal_data_eraser'));
    }

    public function activate() {
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
        dbDelta($sql_geschenke);
        dbDelta($sql_reservierungen);

        // Prüfen und hinzufügen fehlender Spalten für bestehende Installationen
        $columns = $wpdb->get_col("DESC {$this->table_reservations}", 0);

        if (!in_array('verification_token', $columns)) {
            $wpdb->query("ALTER TABLE {$this->table_reservations} ADD COLUMN verification_token varchar(64) AFTER name");
            $wpdb->query("ALTER TABLE {$this->table_reservations} ADD INDEX (verification_token)");
        }

        if (!in_array('is_verified', $columns)) {
            $wpdb->query("ALTER TABLE {$this->table_reservations} ADD COLUMN is_verified tinyint(1) DEFAULT 0 AFTER verification_token");
        }

        if (!wp_next_scheduled('geschenkeliste_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'geschenkeliste_cleanup');
        }
    }

    public function deactivate() {
        // Cronjob entfernen
        $timestamp = wp_next_scheduled('geschenkeliste_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'geschenkeliste_cleanup');
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('hochzeit-geschenkeliste', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
        global $wpdb;

        $email = sanitize_email($email_address);
        $page = (int) $page;
        $number = 100;
        $offset = ($page - 1) * $number;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.email, r.name, r.reserviert_am, r.is_verified, g.titel
                FROM {$this->table_reservations} r
                LEFT JOIN {$this->table_name} g ON r.geschenk_id = g.id
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
        global $wpdb;

        $email = sanitize_email($email_address);
        $page = (int) $page;
        $number = 100;
        $offset = ($page - 1) * $number;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_reservations}
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
            $deleted = $wpdb->delete(
                $this->table_reservations,
                array('id' => (int) $id),
                array('%d')
            );

            if ($deleted) {
                $items_removed = true;
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
            'geschenkeliste-admin-css',
            plugin_dir_url(__FILE__) . 'css/admin-style.css',
            array(),
            HOCHZEIT_GESCHENKELISTE_VERSION
        );

        if ($hook === 'toplevel_page_hochzeit-geschenkeliste') {
            wp_enqueue_media();
            wp_enqueue_script(
                'geschenkeliste-admin-js',
                plugin_dir_url(__FILE__) . 'js/admin-script.js',
                array('jquery'),
                HOCHZEIT_GESCHENKELISTE_VERSION,
                true
            );
            wp_localize_script('geschenkeliste-admin-js', 'geschenkelisteAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('geschenkeliste_admin_nonce')
            ));
        }
    }

    public function register_frontend_text_settings() {
        register_setting(
            'geschenkeliste_frontend_texts_group',
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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'geschenkeliste')) {
            wp_enqueue_style(
                'geschenkeliste-frontend-css',
                plugin_dir_url(__FILE__) . 'css/frontend-style.css',
                array(),
                HOCHZEIT_GESCHENKELISTE_VERSION
            );
            wp_enqueue_script(
                'geschenkeliste-frontend-js',
                plugin_dir_url(__FILE__) . 'js/frontend-script.js',
                array('jquery'),
                HOCHZEIT_GESCHENKELISTE_VERSION,
                true
            );
            wp_localize_script('geschenkeliste-frontend-js', 'geschenkeliste', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('geschenkeliste_frontend_nonce')
            ));
        }
    }

    public function render_admin_page() {
        global $wpdb;

        // Prüfen ob is_verified Spalte existiert
        $columns = $wpdb->get_col("DESC {$this->table_reservations}", 0);
        $has_verification = in_array('is_verified', $columns);

        if ($has_verification) {
            $geschenke = $wpdb->get_results("
                SELECT g.*,
                       r.email,
                       r.name,
                       r.reserviert_am,
                       r.is_verified,
                       CASE WHEN r.id IS NOT NULL AND r.is_verified = 1 THEN 1 ELSE 0 END as ist_reserviert
                FROM {$this->table_name} g
                LEFT JOIN {$this->table_reservations} r ON g.id = r.geschenk_id AND r.is_verified = 1
                ORDER BY g.erstellt_am DESC
            ");
        } else {
            // Fallback für alte Installationen ohne Verifizierung
            $geschenke = $wpdb->get_results("
                SELECT g.*,
                       r.email,
                       r.name,
                       r.reserviert_am,
                       0 as is_verified,
                       CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as ist_reserviert
                FROM {$this->table_name} g
                LEFT JOIN {$this->table_reservations} r ON g.id = r.geschenk_id
                ORDER BY g.erstellt_am DESC
            ");
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
        global $wpdb;

        // Prüfen ob is_verified Spalte existiert
        $columns = $wpdb->get_col("DESC {$this->table_reservations}", 0);
        $has_verification = in_array('is_verified', $columns);

        if ($has_verification) {
            $geschenke = $wpdb->get_results("
                SELECT g.*,
                       CASE WHEN r.id IS NOT NULL AND r.is_verified = 1 THEN 1 ELSE 0 END as ist_reserviert
                FROM {$this->table_name} g
                LEFT JOIN {$this->table_reservations} r ON g.id = r.geschenk_id AND r.is_verified = 1
                ORDER BY g.erstellt_am ASC
            ");
        } else {
            // Fallback für alte Installationen ohne Verifizierung
            $geschenke = $wpdb->get_results("
                SELECT g.*,
                       CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as ist_reserviert
                FROM {$this->table_name} g
                LEFT JOIN {$this->table_reservations} r ON g.id = r.geschenk_id
                ORDER BY g.erstellt_am ASC
            ");
        }

        $frontend_texts = $this->get_frontend_texts();

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/frontend-page.php';
        return ob_get_clean();
    }

    public function ajax_reserve_geschenk() {
        check_ajax_referer('geschenkeliste_frontend_nonce', 'nonce');

        global $wpdb;

        $geschenk_id = isset($_POST['geschenk_id']) ? intval(wp_unslash($_POST['geschenk_id'])) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'));
        }

        $already_reserved = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_reservations} WHERE geschenk_id = %d AND is_verified = 1",
            $geschenk_id
        ));

        if ($already_reserved) {
            wp_send_json_error(array('message' => 'Dieses Geschenk wurde bereits reserviert.'));
        }

        // Token generieren
        $verification_token = bin2hex(random_bytes(32));

        $result = $wpdb->insert(
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
            // Geschenk-Daten holen
            $geschenk = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
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
        check_ajax_referer('geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $result = $wpdb->insert(
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
            wp_send_json_success(array('message' => 'Geschenk erfolgreich hinzugefügt!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Hinzufügen.'));
        }
    }

    public function ajax_update_geschenk() {
        check_ajax_referer('geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $result = $wpdb->update(
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
            wp_send_json_success(array('message' => 'Geschenk erfolgreich aktualisiert!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aktualisieren.'));
        }
    }

    public function ajax_delete_geschenk() {
        check_ajax_referer('geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $geschenk_id = isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0;

        $wpdb->delete($this->table_reservations, array('geschenk_id' => $geschenk_id), array('%d'));
        $result = $wpdb->delete($this->table_name, array('id' => $geschenk_id), array('%d'));

        if ($result) {
            wp_send_json_success(array('message' => 'Geschenk erfolgreich gelöscht!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Löschen.'));
        }
    }

    public function ajax_cancel_reservation() {
        check_ajax_referer('geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $geschenk_id = isset($_POST['geschenk_id']) ? intval(wp_unslash($_POST['geschenk_id'])) : 0;

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        $result = $wpdb->delete(
            $this->table_reservations,
            array('geschenk_id' => $geschenk_id),
            array('%d')
        );

        if ($result) {
            wp_send_json_success(array('message' => 'Reservierung erfolgreich aufgehoben!'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aufheben der Reservierung.'));
        }
    }

    public function ajax_get_geschenk() {
        check_ajax_referer('geschenkeliste_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $geschenk_id = isset($_POST['id']) ? intval(wp_unslash($_POST['id'])) : 0;

        if ($geschenk_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültiges Geschenk.'));
        }

        $geschenk = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, titel, beschreibung, link, bild_url
                FROM {$this->table_name}
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
            'action' => 'verify_reservation',
            'token' => $token
        ), $site_url);

        $cancel_url = add_query_arg(array(
            'action' => 'cancel_reservation_guest',
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
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .geschenk { background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa; }
                .button { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .button-cancel { background: #999; color: #fff !important; }
                .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎁 Geschenk-Reservierung</h1>
                </div>
                <div class='content'>
                    <p>{$anrede},</p>
                    <p>vielen Dank für Deine Reservierung! Bitte bestätige diese, indem Du auf den untenstehenden Link klickst:</p>

                    <div class='geschenk'>
                        Geschenk reservieren:<br>
                        <h3>{$geschenk_titel}</h3>
                        " . ($geschenk_beschreibung ? "<p>{$geschenk_beschreibung}</p>" : "") . "
                    </div>

                    <p style='text-align: center;'>
                        <a href='{$verify_url}' class='button'>Reservierung jetzt bestätigen</a>
                    </p>

                    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                        Reservierung stornieren?<br>
                        Falls Du es dir anders überlegt hast, kannst Du deine Reservierung hier stornieren:<br>
                        <a href='{$cancel_url}' class='button button-cancel'>Reservierung stornieren</a></small>
                    </p>
                </div>
                <div class='footer'>
                    <p>Diese E-Mail wurde automatisch generiert. Bitte antworte nicht darauf.</p>
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
        if (!isset($_GET['action']) || $_GET['action'] !== 'verify_reservation') {
            return;
        }

        if (!isset($_GET['token'])) {
            wp_die('Ungültiger Verifizierungslink.');
        }

        global $wpdb;
        $token = sanitize_text_field(wp_unslash($_GET['token']));

        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_reservations} WHERE verification_token = %s",
            $token
        ));

        if (!$reservation) {
            wp_die('Ungültiger oder abgelaufener Verifizierungslink.');
        }

        if ($reservation->is_verified) {
            wp_die('Diese Reservierung wurde bereits bestätigt.');
        }

        // Prüfen ob das Geschenk inzwischen von jemand anderem reserviert wurde
        $already_verified = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_reservations}
             WHERE geschenk_id = %d AND is_verified = 1 AND id != %d",
            $reservation->geschenk_id,
            $reservation->id
        ));

        if ($already_verified) {
            wp_die('Dieses Geschenk wurde leider bereits von jemand anderem reserviert.');
        }

        // Verifizierung durchführen
        $wpdb->update(
            $this->table_reservations,
            array('is_verified' => 1),
            array('id' => $reservation->id),
            array('%d'),
            array('%d')
        );

        // Alte, nicht verifizierte Reservierungen für dieses Geschenk löschen
        $wpdb->delete(
            $this->table_reservations,
            array(
                'geschenk_id' => $reservation->geschenk_id,
                'is_verified' => 0
            ),
            array('%d', '%d')
        );

        wp_die('
            <h1>✓ Reservierung bestätigt!</h1>
            <p>Vielen Dank! Deine Reservierung wurde erfolgreich bestätigt.</p>
            <p><a href="' . esc_url(home_url()) . '">Zurück zur Website</a></p>
        ');
    }

    public function handle_cancellation() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'cancel_reservation_guest') {
            return;
        }

        if (!isset($_GET['token'])) {
            wp_die('Ungültiger Stornierungslink.');
        }

        global $wpdb;
        $token = sanitize_text_field(wp_unslash($_GET['token']));

        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_reservations} WHERE verification_token = %s",
            $token
        ));

        if (!$reservation) {
            wp_die('Ungültiger oder abgelaufener Stornierungslink.');
        }

        // Reservierung löschen
        $wpdb->delete(
            $this->table_reservations,
            array('id' => $reservation->id),
            array('%d')
        );

        wp_die('
            <h1>✓ Reservierung storniert</h1>
            <p>Deine Reservierung wurde erfolgreich storniert. Das Geschenk ist nun wieder für andere verfügbar.</p>
            <p><a href="' . esc_url(home_url()) . '">Zurück zur Website</a></p>
        ');
    }

    public function cleanup_old_reservations() {
        global $wpdb;

        // Lösche unbestätigte Reservierungen, die älter als 24 Stunden sind
        $wpdb->query("
            DELETE FROM {$this->table_reservations}
            WHERE is_verified = 0
            AND reserviert_am < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
}

new Hochzeit_Geschenkeliste();
