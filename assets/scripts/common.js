jQuery(
    function() {
        iworks_options_tabulator_init();
        /**
         * Switch button
         */
        if (jQuery.fn.switchButton) {
            jQuery('.iworks_options .switch-button, .iworks-options-switch-button').each(
                function() {
                    var options = {
                        checked: jQuery(this).checked,
                        on_label: jQuery(this).data('on_label') || switch_button.labels.on_label,
                        off_label: jQuery(this).data('off_label') || switch_button.labels.off_label
                    };
                    jQuery(this).switchButton(options);
                }
            );
        }
        /**
         * Color picker
         */
        if (jQuery.fn.wpColorPicker) {
            jQuery('.wpColorPicker').wpColorPicker();
        }
        /**
         * select2
         */
        if (jQuery.fn.select2) {
            jQuery('.iworks-options .select2').select2();
        }
        /**
         * slider
         */
        if (jQuery.fn.slider) {
            jQuery('.iworks-options .slider').each(
                function() {
                    jQuery(this).parent().append('<div class="ui-slider"></div>');
                    var target = jQuery(this);
                    var options = {
                        value: parseInt(target.val()),
                        step: parseInt(target.data('step') || target.attr('step') || 1),
                        min: parseInt(target.data('min') || target.attr('min') || 0),
                        max: parseInt(target.data('max') || target.attr('max') || 100),
                        slide: function(event, ui) {
                            target.val(ui.value);
                        }
                    };
                    jQuery('.ui-slider', jQuery(this).parent()).slider(options);
                }
            );
        }
    }
);
/**
 * Media
 */
jQuery(document).ready(function($) {
    var iworks_options_media = [];
    $('input.iworks_delete_button').on('click', function(e) {
        var $parent = $(this).closest('td');
        e.preventDefault();
        $('img', $parent).attr('src', '');
        $('input[type=hidden]', $parent).val(0);
        $(this).hide();
    });
    $('input.iworks_upload_button').on('click', function(e) {
        var $parent = $(this).closest('td');
        var key = $(this).attr('rel');
        e.preventDefault();
        // Extend the wp.media object
        if (iworks_options_media[key]) {
            iworks_options_media[key].open();
            return;
        }
        iworks_options_media[key] = wp.media.frames.file_frame = wp.media({
            title: window.iworks_options.buttons.select_media,
            button: {
                text: window.iworks_options.buttons.select_media,
                rel: this,
            },
            multiple: false
        });

        // When a file is selected, grab the URL and set it as the text field's value
        iworks_options_media[key].on('select', function() {
            var attachment = iworks_options_media[key].state().get('selection').first().toJSON();
            $('img', $parent).attr('src', attachment.url);
            $('input[type=hidden]', $parent).val(attachment.id);
            $('input.iworks_delete_button', $parent).show();
        });
        // Open the upload dialog
        iworks_options_media[key].open();
    });
});
/**
 * Tabulator Bootup
 */
function iworks_options_tabulator_init() {
    if (!jQuery("#hasadmintabs").length) {
        return;
    }
    jQuery('#hasadmintabs').prepend("<ul><\/ul>");
    jQuery('#hasadmintabs > fieldset').each(
        function(i) {
            id = jQuery(this).attr('id');
            rel = jQuery(this).attr('rel');
            caption = jQuery(this).find('h3').text();
            if (rel) {
                rel = ' class="' + rel + '"';
            } else {
                rel = '';
            }
            jQuery('#hasadmintabs > ul').append('<li><a href="#' + id + '"><span' + rel + '>' + caption + "<\/span><\/a><\/li>");
            jQuery(this).find('h3').hide();
        }
    );
    index = 0;
    jQuery('#hasadmintabs h3').each(
        function(i) {
            if (jQuery(this).hasClass('selected')) {
                index = i;
            }
        }
    );
    if (index < 0) {
        index = 0;
    }
    jQuery("#hasadmintabs").tabs({
        active: index
    });
    jQuery('#hasadmintabs ul a').click(
        function(i) {
            jQuery('#hasadmintabs #last_used_tab').val(jQuery(this).parent().index());
        }
    );
}