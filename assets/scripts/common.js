jQuery(function(){
    iworks_options_tabulator_init();
    if ( jQuery.fn.switchButton ) {
        jQuery('.iworks_options .switch-button').each(function() {
            var options = {
                checked: jQuery(this).checked,
                on_label: switch_button.labels.on_label,
                off_label: switch_button.labels.off_label
            };
            jQuery(this).switchButton(options);
        });
    }
    if ( jQuery.fn.wpColorPicker ) {
        jQuery('.wpColorPicker').wpColorPicker();
    }
    /**
     * select2
     */
    if ( jQuery.fn.select2 ) {
        jQuery('.iworks-options .select2').select2();
    }
    /**
     * slider
     */
    if ( jQuery.fn.slider ) {
        jQuery('.iworks-options .slider').each( function() {
            jQuery(this).parent().append('<div class="ui-slider"></div>' );
            var target = jQuery(this);
            var options = {
                value: target.val(),
                step:  parseInt( jQuery(this).data('step') || jQuery(this).attr('step') || 1 ),
                min:   parseInt( jQuery(this).data('min') || jQuery(this).attr('min') || 100 ),
                max:   parseInt( jQuery(this).data('max') || jQuery(this).attr('max') || 100 ),
                slide: function( event, ui ) {
                    target.val( ui.value );
                }
            };
            l(options);


            jQuery('.ui-slider', jQuery(this).parent()).slider( options );
        });
    }
});
/**
 * Tabulator Bootup
 */
function iworks_options_tabulator_init()
{
    if (!jQuery("#hasadmintabs").length) {
        return;
    }
    jQuery('#hasadmintabs').prepend("<ul><\/ul>");
    jQuery('#hasadmintabs > fieldset').each(function(i){
        id      = jQuery(this).attr('id');
        rel     = jQuery(this).attr('rel');
        caption = jQuery(this).find('h3').text();
        if ( rel ) {
            rel = ' class="'+rel+'"';
        }
        jQuery('#hasadmintabs > ul').append('<li><a href="#'+id+'"><span'+rel+'>'+caption+"<\/span><\/a><\/li>");
        jQuery(this).find('h3').hide();
    });
    index = 0;
    jQuery('#hasadmintabs h3').each(function(i){
        if ( jQuery(this).hasClass( 'selected' ) ) {
            index = i;
        }
    });
    if ( index < 0 ) index = 0;
    jQuery("#hasadmintabs").tabs({ active: index });
    jQuery('#hasadmintabs ul a').click(function(i){
        jQuery('#hasadmintabs #last_used_tab').val(jQuery(this).parent().index());
    });
}

