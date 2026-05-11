<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Frontend-Texte bearbeiten</h1>

    <div class="geschenkeliste-admin-container">
        <div class="admin-section">
            <h2>Texte für die Geschenkeliste</h2>
            <p>Hier kannst du die zentralen Texte für die Frontend-Ausgabe anpassen.</p>

            <form method="post" action="options.php">
                <?php settings_fields('geschenkeliste_frontend_texts_group'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_title">Titel</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="geschenkeliste_title"
                                name="geschenkeliste_frontend_texts[title]"
                                value="<?php echo esc_attr($frontend_texts['title']); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_intro">Einleitung (geschenkeliste-intro)</label>
                        </th>
                        <td>
                            <textarea
                                id="geschenkeliste_intro"
                                name="geschenkeliste_frontend_texts[intro]"
                                rows="4"
                                class="large-text"
                            ><?php echo esc_textarea($frontend_texts['intro']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_empty">Hinweis bei leerer Liste</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="geschenkeliste_empty"
                                name="geschenkeliste_frontend_texts[empty]"
                                value="<?php echo esc_attr($frontend_texts['empty']); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_reserve_button">Button-Text (reservieren)</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="geschenkeliste_reserve_button"
                                name="geschenkeliste_frontend_texts[reserve_button]"
                                value="<?php echo esc_attr($frontend_texts['reserve_button']); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_modal_title">Modal-Titel</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="geschenkeliste_modal_title"
                                name="geschenkeliste_frontend_texts[modal_title]"
                                value="<?php echo esc_attr($frontend_texts['modal_title']); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geschenkeliste_modal_intro">Modal-Einleitung</label>
                        </th>
                        <td>
                            <textarea
                                id="geschenkeliste_modal_intro"
                                name="geschenkeliste_frontend_texts[modal_intro]"
                                rows="3"
                                class="large-text"
                            ><?php echo esc_textarea($frontend_texts['modal_intro']); ?></textarea>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Texte speichern'); ?>
            </form>
        </div>
    </div>
</div>
