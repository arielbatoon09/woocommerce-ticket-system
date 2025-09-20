jQuery(document).ready(function($) {
    var mediaUploader;

    $('#upload_ticket_bg').on('click', function(e) {
        e.preventDefault();

        // If the uploader object has already been created, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create the media uploader
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Select Ticket Background',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        // When an image is selected, update the hidden input and preview
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#ticket_bg_image_id').val(attachment.id);
            $('#ticket_bg_preview').attr('src', attachment.url);
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    $('#remove_ticket_bg').on('click', function(e) {
        e.preventDefault();
        $('#ticket_bg_image_id').val('');
        $('#ticket_bg_preview').attr('src', '');
    });
});