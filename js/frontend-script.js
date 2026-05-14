jQuery(document).ready(function($) {

    // Reservieren-Button klicken
    $('.button-reserve').on('click', function() {
        const geschenkId = $(this).data('id');
        $('#reserve_geschenk_id').val(geschenkId);
        $('#reserve-modal').fadeIn();
    });

    // Reservierung absenden
    $('#reserve-form').on('submit', function(e) {
        e.preventDefault();

        const submitButton = $(this).find('button[type="submit"]');
        const originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Wird reserviert...');

        const formData = {
            action: 'hochzeit_geschenkeliste_reserve_geschenk',
            nonce: hochzeitGeschenkeliste.nonce,
            geschenk_id: $('#reserve_geschenk_id').val(),
            email: $('#reserve_email').val(),
            name: $('#reserve_name').val()
        };

        $.post(hochzeitGeschenkeliste.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.data.message);
                submitButton.prop('disabled', false).text(originalText);
            }
        }).fail(function() {
            alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.');
            submitButton.prop('disabled', false).text(originalText);
        });
    });

    // Modal schließen
    $('.close-modal').on('click', function() {
        $('.geschenkeliste-modal').fadeOut();
        $('#reserve-form')[0].reset();
    });

    // Modal bei Klick außerhalb schließen
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('geschenkeliste-modal')) {
            $('.geschenkeliste-modal').fadeOut();
            $('#reserve-form')[0].reset();
        }
    });

    // ESC-Taste zum Schließen
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.geschenkeliste-modal').fadeOut();
            $('#reserve-form')[0].reset();
        }
    });
});
