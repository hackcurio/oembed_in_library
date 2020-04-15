;(function($) {
    $(document).ready(function() {

        /**
         * Load a preview from a media URL
         */
        $('#oembed_in_library_btn_preview').on('click', function(e) {
            e.preventDefault();
            $('#oembed_in_library_preview').empty();
            $('#oembed_in_library_preview').addClass('loading');
            $.post(
                ajax_object.ajax_url, {
                    "action": 'oembed_in_library_preview',
                    "media_url": $('#oembed_url').val()
                }, function(response) {
                    $('#oembed_in_library_preview').removeClass('loading');
                    $('#oembed_in_library_preview').html(response);
                }
            );
        });
    })
})(jQuery);
