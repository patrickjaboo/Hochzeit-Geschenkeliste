<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Geschenkeliste Verwaltung</h1>

    <div class="geschenkeliste-admin-container">
        <div class="admin-section">
            <h2>Neues Geschenk hinzufügen</h2>
            <form id="add-geschenk-form">
                <table class="form-table">
                    <tr>
                        <th><label for="titel">Titel *</label></th>
                        <td><input type="text" id="titel" name="titel" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="beschreibung">Beschreibung</label></th>
                        <td><textarea id="beschreibung" name="beschreibung" rows="4" class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="link">Link (z.B. zum Shop)</label></th>
                        <td><input type="url" id="link" name="link" class="regular-text" placeholder="https://..."></td>
                    </tr>
                    <tr>
                        <th><label for="bild_url">Bild</label></th>
                        <td>
                            <input type="hidden" id="bild_url" name="bild_url">
                            <button type="button" class="button upload-image-button">Bild hochladen</button>
                            <div class="image-preview" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Geschenk hinzufügen</button>
                </p>
            </form>
        </div>

        <div class="admin-section">
            <h2>Alle Geschenke</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">Bild</th>
                        <th>Titel</th>
                        <th>Beschreibung</th>
                        <th>Link</th>
                        <th>Status</th>
                        <th>Reserviert von</th>
                        <th style="width: 150px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($geschenke)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Noch keine Geschenke vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($geschenke as $geschenkeliste_geschenk): ?>
                            <tr data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>">
                                <td>
                                    <?php if ($geschenkeliste_geschenk->bild_url): ?>
                                        <img src="<?php echo esc_url($geschenkeliste_geschenk->bild_url); ?>" style="max-width: 50px; height: auto;">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-format-image" style="font-size: 30px; color: #ccc;"></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html($geschenkeliste_geschenk->titel); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words($geschenkeliste_geschenk->beschreibung, 10)); ?></td>
                                <td>
                                    <?php if ($geschenkeliste_geschenk->link): ?>
                                        <a href="<?php echo esc_url($geschenkeliste_geschenk->link); ?>" target="_blank">Link öffnen</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($geschenkeliste_geschenk->ist_reserviert): ?>
                                        <span class="status-badge reserved">Vergeben</span>
                                    <?php else: ?>
                                        <span class="status-badge available">Verfügbar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($geschenkeliste_geschenk->ist_reserviert): ?>
                                        <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <strong>Bestätigt</strong><br>
                                        <strong><?php echo esc_html($geschenkeliste_geschenk->name ?: 'Nicht angegeben'); ?></strong><br>
                                        <small><?php echo esc_html($geschenkeliste_geschenk->email); ?></small><br>
                                        <small style="color: #999;"><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($geschenkeliste_geschenk->reserviert_am))); ?></small><br>
                                        <button class="button button-small cancel-reservation" data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>" style="margin-top: 5px;">Reservierung aufheben</button>
                                    <?php elseif (isset($geschenkeliste_geschenk->email) && !empty($geschenkeliste_geschenk->email) && $geschenkeliste_geschenk->is_verified == 0): ?>
                                        <span class="dashicons dashicons-clock" style="color: #f0ad4e;"></span> <strong>Warte auf Bestätigung</strong><br>
                                        <small><?php echo esc_html($geschenkeliste_geschenk->email); ?></small><br>
                                        <small style="color: #999;">Seit: <?php echo esc_html(wp_date('d.m.Y H:i', strtotime($geschenkeliste_geschenk->reserviert_am))); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button button-small edit-geschenk" data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>">Bearbeiten</button>
                                    <button class="button button-small button-link-delete delete-geschenk" data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>">Löschen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal für Bearbeiten -->
<div id="edit-geschenk-modal" class="geschenkeliste-modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Geschenk bearbeiten</h2>
        <form id="edit-geschenk-form">
            <input type="hidden" id="edit_id" name="id">
            <table class="form-table">
                <tr>
                    <th><label for="edit_titel">Titel *</label></th>
                    <td><input type="text" id="edit_titel" name="titel" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="edit_beschreibung">Beschreibung</label></th>
                    <td><textarea id="edit_beschreibung" name="beschreibung" rows="4" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="edit_link">Link</label></th>
                    <td><input type="url" id="edit_link" name="link" class="regular-text" placeholder="https://..."></td>
                </tr>
                <tr>
                    <th><label for="edit_bild_url">Bild</label></th>
                    <td>
                        <input type="hidden" id="edit_bild_url" name="bild_url">
                        <button type="button" class="button upload-image-button-edit">Bild hochladen</button>
                        <div class="image-preview-edit" style="margin-top: 10px;"></div>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Speichern</button>
                <button type="button" class="button close-modal">Abbrechen</button>
            </p>
        </form>
    </div>
</div>
