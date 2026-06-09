jQuery(document).ready(function($) {
    // Media uploader logic for admin settings
    $('.md-upload-btn').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const container = button.closest('.md-avatar-upload-box');
        const inputId = container.find('.md-avatar-id-input');
        const previewImg = container.find('.md-avatar-preview');
        const removeBtn = container.find('.md-remove-btn');

        // Create the media frame.
        const file_frame = wp.media({
            title: 'انتخاب یا آپلود آواتار',
            button: {
                text: 'استفاده از این تصویر'
            },
            multiple: false
        });

        // When an image is selected, run a callback.
        file_frame.on('select', function() {
            const attachment = file_frame.state().get('selection').first().toJSON();
            inputId.val(attachment.id);
            previewImg.attr('src', attachment.url).show();
            container.find('.md-avatar-placeholder-svg').hide();
            removeBtn.show();
        });

        // Open the modal.
        file_frame.open();
    });

    // Remove image logic
    $('.md-remove-btn').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const container = button.closest('.md-avatar-upload-box');
        const inputId = container.find('.md-avatar-id-input');
        const previewImg = container.find('.md-avatar-preview');
        const removeBtn = container.find('.md-remove-btn');

        inputId.val('');
        previewImg.attr('src', '').hide();
        container.find('.md-avatar-placeholder-svg').show();
        removeBtn.hide();
    });
});
