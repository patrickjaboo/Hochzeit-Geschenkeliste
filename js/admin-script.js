jQuery(document).ready(function($) {
    let mediaUploader;

    // Media Uploader für neues Geschenk
    $('.upload-image-button').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Bild auswählen',
            button: {
                text: 'Bild verwenden'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#bild_url').val(attachment.url);
            $('.image-preview').html(
                '<img src="' + attachment.url + '">' +
                '<a href="#" class="remove-image">Entfernen</a>'
            ).show();
        });

        mediaUploader.open();
    });

    // Media Uploader für Bearbeiten
    let mediaUploaderEdit;
    $(document).on('click', '.upload-image-button-edit', function(e) {
        e.preventDefault();

        if (mediaUploaderEdit) {
            mediaUploaderEdit.open();
            return;
        }

        mediaUploaderEdit = wp.media({
            title: 'Bild auswählen',
            button: {
                text: 'Bild verwenden'
            },
            multiple: false
        });

        mediaUploaderEdit.on('select', function() {
            const attachment = mediaUploaderEdit.state().get('selection').first().toJSON();
            $('#edit_bild_url').val(attachment.url);
            $('.image-preview-edit').html(
                '<img src="' + attachment.url + '">' +
                '<a href="#" class="remove-image">Entfernen</a>'
            ).show();
        });

        mediaUploaderEdit.open();
    });

    // Bild entfernen
    $(document).on('click', '.remove-image', function(e) {
        e.preventDefault();
        $(this).closest('.image-preview, .image-preview-edit').hide().html('');
        if ($(this).closest('.image-preview').length) {
            $('#bild_url').val('');
        } else {
            $('#edit_bild_url').val('');
        }
    });

    // Geschenk hinzufügen
    $('#add-geschenk-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: 'add_geschenk',
            nonce: geschenkelisteAdmin.nonce,
            titel: $('#titel').val(),
            beschreibung: $('#beschreibung').val(),
            link: $('#link').val(),
            bild_url: $('#bild_url').val()
        };

        $.post(geschenkelisteAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.data.message);
            }
        });
    });

    // Bearbeiten-Button
    $('.edit-geschenk').on('click', function() {
        const row = $(this).closest('tr');
        const id = $(this).data('id');

        // Hole Daten aus der Zeile
        $.post(geschenkelisteAdmin.ajax_url, {
            action: 'get_geschenk',
            nonce: geschenkelisteAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                const geschenk = response.data;
                $('#edit_id').val(geschenk.id);
                $('#edit_titel').val(geschenk.titel);
                $('#edit_beschreibung').val(geschenk.beschreibung);
                $('#edit_link').val(geschenk.link);
                $('#edit_bild_url').val(geschenk.bild_url);

                if (geschenk.bild_url) {
                    $('.image-preview-edit').html(
                        '<img src="' + geschenk.bild_url + '">' +
                        '<a href="#" class="remove-image">Entfernen</a>'
                    ).show();
                } else {
                    $('.image-preview-edit').hide().html('');
                }

                $('#edit-geschenk-modal').fadeIn();
            }
        });

        // Alternativ: Wenn get_geschenk nicht implementiert ist, aus DOM auslesen
        // Dies ist eine einfachere Lösung, die ohne zusätzliche AJAX-Anfrage auskommt
        const titel = row.find('td:eq(1) strong').text();
        $('#edit_id').val(id);
        $('#edit_titel').val(titel);

        $('#edit-geschenk-modal').fadeIn();
    });

    // Geschenk aktualisieren
    $('#edit-geschenk-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: 'update_geschenk',
            nonce: geschenkelisteAdmin.nonce,
            id: $('#edit_id').val(),
            titel: $('#edit_titel').val(),
            beschreibung: $('#edit_beschreibung').val(),
            link: $('#edit_link').val(),
            bild_url: $('#edit_bild_url').val()
        };

        $.post(geschenkelisteAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.data.message);
            }
        });
    });

    // Geschenk löschen
    $('.delete-geschenk').on('click', function() {
        if (!confirm('Möchtest Du dieses Geschenk wirklich löschen?')) {
            return;
        }

        const id = $(this).data('id');

        $.post(geschenkelisteAdmin.ajax_url, {
            action: 'delete_geschenk',
            nonce: geschenkelisteAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.data.message);
            }
        });
    });

    // Reservierung aufheben
    $('.cancel-reservation').on('click', function() {
        if (!confirm('Möchtest Du diese Reservierung wirklich aufheben? Das Geschenk wird dann wieder für alle verfügbar sein.')) {
            return;
        }

        const geschenkId = $(this).data('id');

        $.post(geschenkelisteAdmin.ajax_url, {
            action: 'cancel_reservation',
            nonce: geschenkelisteAdmin.nonce,
            geschenk_id: geschenkId
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.data.message);
            }
        });
    });

    // Modal schließen
    $('.close-modal').on('click', function() {
        $('.geschenkeliste-modal').fadeOut();
    });

    // Modal bei Klick außerhalb schließen
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('geschenkeliste-modal')) {
            $('.geschenkeliste-modal').fadeOut();
        }
    });
});
