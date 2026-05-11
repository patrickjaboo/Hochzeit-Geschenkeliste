<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="geschenkeliste-frontend">
    <h2><?php echo esc_html($frontend_texts['title']); ?></h2>
    <p class="geschenkeliste-intro"><?php echo nl2br(esc_html($frontend_texts['intro'])); ?></p>

    <?php if (empty($geschenke)): ?>
        <p class="no-geschenke"><?php echo esc_html($frontend_texts['empty']); ?></p>
    <?php else: ?>
        <div class="geschenke-grid">
            <?php foreach ($geschenke as $geschenkeliste_geschenk): ?>
                <div class="geschenk-item <?php echo $geschenkeliste_geschenk->ist_reserviert ? 'reserved' : 'available'; ?>" data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>">
                    <div class="geschenk-image">
                        <?php if ($geschenkeliste_geschenk->bild_url): ?>
                            <img src="<?php echo esc_url($geschenkeliste_geschenk->bild_url); ?>" alt="<?php echo esc_attr($geschenkeliste_geschenk->titel); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
                                    <rect fill="#f0f0f0" width="200" height="200"/>
                                    <g fill="#c0c0c0">
                                        <path d="M100 50 L120 70 L140 50 L140 80 L160 100 L140 100 L140 130 L120 110 L100 130 L80 110 L60 130 L60 100 L40 100 L60 80 L60 50 L80 70 Z"/>
                                        <circle cx="100" cy="100" r="5" fill="#a0a0a0"/>
                                        <path d="M85 140 Q100 150 115 140" stroke="#a0a0a0" stroke-width="3" fill="none"/>
                                    </g>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <?php if ($geschenkeliste_geschenk->ist_reserviert): ?>
                            <div class="reserved-overlay">
                                <span class="reserved-badge">Bereits vergeben</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="geschenk-content">
                        <h3 class="geschenk-titel"><?php echo esc_html($geschenkeliste_geschenk->titel); ?></h3>

                        <?php if ($geschenkeliste_geschenk->beschreibung): ?>
                            <p class="geschenk-beschreibung"><?php echo wp_kses_post($geschenkeliste_geschenk->beschreibung); ?></p>
                        <?php endif; ?>

                        <?php if ($geschenkeliste_geschenk->link): ?>
                            <p class="geschenk-link">
                                <a href="<?php echo esc_url($geschenkeliste_geschenk->link); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-admin-links"></span> Zum Shop
                                </a>
                            </p>
                        <?php endif; ?>

                        <div class="geschenk-actions">
                            <?php if ($geschenkeliste_geschenk->ist_reserviert): ?>
                                <span class="status-info reserved-info">
                                    <span class="dashicons dashicons-yes-alt"></span> Vergeben
                                </span>
                            <?php else: ?>
                                <button class="button-reserve" data-id="<?php echo esc_attr($geschenkeliste_geschenk->id); ?>">
                                    <?php echo esc_html($frontend_texts['reserve_button']); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal für Reservierung -->
<div id="reserve-modal" class="geschenkeliste-modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><?php echo esc_html($frontend_texts['modal_title']); ?></h3>
        <p><?php echo nl2br(esc_html($frontend_texts['modal_intro'])); ?></p>
        <form id="reserve-form">
            <input type="hidden" id="reserve_geschenk_id" name="geschenk_id">
            <div class="form-group">
                <label for="reserve_name">Name (optional)</label>
                <input type="text" id="reserve_name" name="name" class="form-control" placeholder="Max Mustermann">
            </div>
            <div class="form-group">
                <label for="reserve_email">E-Mail-Adresse *</label>
                <input type="email" id="reserve_email" name="email" class="form-control" placeholder="max@beispiel.de" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Jetzt reservieren</button>
                <button type="button" class="button-secondary close-modal">Abbrechen</button>
            </div>
        </form>
    </div>
</div>
