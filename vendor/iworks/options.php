<?php
/*
Class Name: iWorks Options
Class URI: http://iworks.pl/
Description: Option class to manage opsions.
Version: trunk
Author: Marcin Pietrzak
Author URI: http://iworks.pl/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2011-2013 Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */

if ( !defined( 'WPINC' ) ) {
    die;
}

if ( class_exists( 'IworksOptions' ) ) {
    return;
}

class IworksOptions
{
    private $option_function_name;
    private $option_group;
    private $option_prefix;
    private $version;
    public $notices;

    public function __construct()
    {
        $this->notices              = array();
        $this->version              = '1.7.4';
        $this->option_group         = 'index';
        $this->option_function_name = null;
        $this->option_prefix        = null;

        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
    }

    public function get_version()
    {
        return $this->version;
    }

    public function set_option_function_name($option_function_name)
    {
        $this->option_function_name = $option_function_name;
    }

    public function set_option_prefix($option_prefix)
    {
        $this->option_prefix = $option_prefix;
    }

    private function get_option_array()
    {
        if ( isset( $this->options[ $this->option_group ] ) ) {
            $options = apply_filters( $this->option_function_name, $this->options );
            return $options[ $this->option_group ];
        }
        $options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
        if ( isset( $options[ $this->option_group ] ) ) {
            $this->options[ $this->option_group ] = $options[ $this->option_group ];
            return $this->options[ $this->option_group ];
        }
        return array();
    }

    public function build_options($option_group = 'index', $echo = true)
    {
        $this->option_group = $option_group;
        $options = $this->get_option_array( $option_group );
        /**
         * check options exists?
         */
        if ( !is_array($options['options'] ) ) {
            echo '<div class="below-h2 error"><p><strong>'.__('An error occurred while getting the configuration.', 'iworks').'</strong></p></div>';
            return;
        }
        $is_simple = 'simple' == $this->get_option( 'configuration', 'index', 'advance' );
        $content   = '';
        $hidden    = '';
        $top       = '';
        $use_tabs  = isset( $options['use_tabs'] ) && $options['use_tabs'];
        /**
         * produce options
         */
        if ( $use_tabs ) {
            $top .= '<div id="hasadmintabs" class="ui-tabs ui-widget ui-widget-content ui-corner-all">';
        }
        $i           = 0;
        $label_index = 0;
        $last_tab    = null;
        $related_to  = array();
        $configuration = 'all';
        foreach ($options['options'] as $option) {
            if (isset($option['capability'])) {
                if(!current_user_can($option['capability'])) {
                    continue;
                }
            }
            $show_option = true;
            if ( isset( $option['check_supports'] ) && is_array( $option['check_supports'] ) && count( $option['check_supports'] ) ) {
                foreach ( $option['check_supports'] as $support_to_check ) {
                    if ( !current_theme_supports( $support_to_check ) ) {
                        $show_option = false;
                    }
                }
            }
            if ( !$show_option ) {
                continue;
            }
            /**
             * dismiss on special type
             */
            if ( 'special' == $option['type'] ) {
                continue;
            }
            /**
             * dismiss if have "callback_to_show" and return false
             */
            if ( !preg_match( '/^(heading|info)$/', $option[ 'type' ] ) && isset( $option['callback_to_show'] ) && is_callable( $option['callback_to_show'] ) ) {
                if ( false === $option['callback_to_show']( $this->get_option( $option['name'], $option_group ) ) ) {
                    continue;
                }
            }
            /**
             * heading
             */
            if ( 'heading' == $option['type'] ) {
                if ( isset( $option['configuration'] ) ) {
                    $configuration = $option['configuration'];
                } else {
                    $configuration = 'all';
                }
            }
            if ( ( $is_simple && $configuration == 'advance' ) || ( !$is_simple && $configuration == 'simple' ) ) {
                if ( isset( $option['configuration'] ) && 'both' == $option['configuration'] ) {
                    continue;
                }
                if( in_array( $option['type'], array(
                    'checkbox',
                    'number',
                    'radio',
                    'text',
                    'textarea'
                ) ) ) {
                    $html_element_name = isset($option['name']) && $option['name']? $this->option_prefix.$option['name']:'';
                    $content .= sprintf (
                        '<input type="hidden" name="%s" value="%s" /> %s',
                        $html_element_name,
                        $this->get_option( $option['name'], $option_group ),
                        "\n"
                    );
                }
                continue;
            }
            if ( $option['type'] == 'heading' ) {
                if ( $use_tabs ) {
                    if ( $last_tab != $option['label'] ) {
                        $last_tab = $option['label'];
                        $content .= '</tbody></table>';
                        $content .= '</fieldset>';
                    }
                    $content .= sprintf(
                        '<fieldset id="iworks_%s" class="ui-tabs-panel ui-widget-content ui-corner-bottom"%s>',
                        crc32( $option['label'] ),
                        ( isset( $option['class'] ) && $option['class'] )? ' rel="'.$option['class'].'"':''
                    );
                    if ( !$use_tabs ) {
                        $content .= sprintf( '<h3>%s</h3>', $option['label'] );
                    }
                    $content .= sprintf(
                        '<table class="form-table%s" style="%s">',
                        isset($options['widefat'])? ' widefat':'',
                        isset($options['style'])? $options['style']:''
                    );
                    $content .= '<tbody>';
                }
                $content .= '<tr><td colspan="2">';
            } elseif ( $option['type'] == 'subheading' ) {
                $content .= '<tr><td colspan="2">';
            } elseif ( $option['type'] != 'hidden' ) {
                $style = '';
                if ( isset($option['related_to'] ) && isset( $related_to[ $option['related_to'] ] ) && $related_to[ $option['related_to'] ] == 0 ) {
                    $style .= 'style="display:none"';
                }
                $content .= sprintf( '<tr valign="top" class="%s" id="tr_%s"%s>', $i++%2? 'alternate':'', isset($option['name'])? $option['name']:'', $style );
                $content .= sprintf( '<th scope="row">%s</th>', isset($option['th']) && $option['th']? $option['th']:'&nbsp;' );
                $content .= '<td>';
            }
            $html_element_name = isset($option['name']) && $option['name']? $this->option_prefix.$option['name']:'';
            $filter_name = $html_element_name? $option_group.'_'.$html_element_name : null;
            switch ( $option['type'] ) {
            case 'hidden':
                $hidden .= sprintf
                    (
                        '<input type="hidden" name="%s" value="%s" />',
                        $html_element_name,
                        isset($option['dynamic']) && $option['dynamic']? $this->get_option( $option['name'], $option_group ):$option['default']
                    );
                break;
            case 'number':
                $id = '';
                if ( isset($option['use_name_as_id']) && $option['use_name_as_id']) {
                    $id = sprintf( ' id="%s"', $html_element_name );
                }
                $content .= sprintf (
                    '<input type="%s" name="%s" value="%s" class="%s"%s %s %s /> %s',
                    $option['type'],
                    $html_element_name,
                    $this->get_option( $option['name'], $option_group ),
                    isset($option['class']) && $option['class']? $option['class']:'',
                    $id,
                    isset($option['min'])?  'min="'.$option['min'].'"':'',
                    isset($option['max'])?  'max="'.$option['max'].'"':'',
                    isset($option['label'])?  $option['label']:''
                );
                break;
            case 'password':
            case 'text':
                $id = '';
                if ( isset($option['use_name_as_id']) && $option['use_name_as_id']) {
                    $id = sprintf( ' id="%s"', $html_element_name );
                }
                $content .= sprintf (
                    '<input type="%s" name="%s" value="%s" class="%s"%s /> %s',
                    $option['type'],
                    $html_element_name,
                    $this->get_option( $option['name'], $option_group ),
                    isset($option['class']) && $option['class']? $option['class']:'',
                    $id,
                    isset($option['label'])? $option['label']:''
                );
                break;
            case 'checkbox':
                $related_to[ $option['name'] ] = $this->get_option( $option['name'], $option_group );
                $checkbox = sprintf (
                    '<label for="%s"><input type="checkbox" name="%s" id="%s" value="1"%s%s /> %s</label>',
                    $html_element_name,
                    $html_element_name,
                    $html_element_name,
                    $related_to[ $option['name'] ]? ' checked="checked"':'',
                    ( ( isset($option['disabled']) && $option['disabled'] ) or ( isset( $option['need_pro'] ) && $option['need_pro'] ) )? ' disabled="disabled"':'',
                    isset($option['label'])?  $option['label']:''
                );
                $content .= apply_filters( $filter_name, $checkbox );
                break;
            case 'checkbox_group':
                $option_value = $this->get_option($option['name'], $option_group );
                if ( empty( $option_value ) && isset( $option['defaults'] ) ) {
                    foreach( $option['defaults'] as $default ) {
                        $option_value[ $default ] = $default;
                    }
                }
                $content .= '<ul>';
                $i = 0;
                if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
                    $option['options'] = array_merge( $option['options'], $option['extra_options']());
                }
                foreach ($option['options'] as $value => $label) {
                    $checked = false;
                    if ( is_array( $option_value ) && array_key_exists( $value, $option_value ) ) {
                        $checked = true;
                    }
                    $id = $option['name'].$i++;
                    $content .= sprintf
                        (
                            '<li><label for="%s"><input type="checkbox" name="%s[%s]" value="%s"%s id="%s"/> %s</label></li>',
                            $id,
                            $html_element_name,
                            $value,
                            $value,
                            $checked? ' checked="checked"':'',
                            $id,
                            $label
                        );
                }
                $content .= '</ul>';
                break;
            case 'radio':
                $option_value = $this->get_option( $option['name'], $option_group );
                $i = 0;
                if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
                    $option['radio'] = array_merge( $option['radio'], $option['extra_options']());
                }
                $option['radio'] = apply_filters( $filter_name.'_data', $option['radio'] );
                $radio = apply_filters( $filter_name.'_content', null, $option['radio'], $html_element_name, $option['name'], $option_value );
                if ( empty( $radio ) ) {
                    foreach ($option['radio'] as $value => $input) {
                        $id = $option['name'].$i++;
                        $disabled = '';
                        if ( preg_match( '/\-disabled$/', $value ) ) {
                            $disabled = 'disabled="disabled"';
                        } elseif ( isset( $input['disabled'] ) && $input['disabled'] ) {
                            $disabled = 'disabled="disabled"';
                        }
                        $radio .= sprintf(
                            '<li class="%s%s"><label for="%s"><input type="radio" name="%s" value="%s"%s id="%s" %s/> %s</label>',
                            sanitize_title( $value ),
                            $disabled? ' disabled':'',
                            $id,
                            $html_element_name,
                            $value,
                            ($option_value == $value or ( empty($option_value) and isset($option['default']) and $value == $option['default'] ) )? ' checked="checked"':'',
                            $id,
                            $disabled,
                            $input['label']
                        );
                        if ( isset( $input['description'] ) ) {
                            $radio .= sprintf(
                                '<br /><span class="description">%s</span>',
                                $input['description']
                            );
                        }
                        $radio .= '</li>';
                    }
                    if ( $radio ) {
                        $radio = '<ul>'.$radio.'</ul>';
                    }
                }
                $content .= apply_filters( $filter_name, $radio );
                break;
            case 'select':
                $option_value = $this->get_option( $option['name'], $option_group );

                if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
                    $option['options'] = array_merge( $option['options'], $option['extra_options']());
                }
                $option['options'] = apply_filters( $filter_name.'_data', $option['options'], $option['name'], $option_value );

                $select = apply_filters( $filter_name.'_content', null, $option['options'], $html_element_name, $option['name'], $option_value );
                if ( empty( $select ) ) {
                    foreach ($option['options'] as $key => $value ) {
                        $disabled = '';
                        if ( preg_match( '/\-disabled$/', $value ) ) {
                            $disabled = 'disabled="disabled"';
                        } elseif ( isset( $input['disabled'] ) && $input['disabled'] ) {
                            $disabled = 'disabled="disabled"';
                        }
                        $select .= sprintf
                            (
                                '<option %s value="%s" %s %s >%s</option>',
                                $disabled? 'class="disabled"':'',
                                $key,
                                ($option_value == $key or ( empty( $option_value ) and isset( $option['default'] ) and $key == $option['default'] ) )? ' selected="selected"':'',
                                $disabled,
                                $value
                            );
                    }
                    if ( $select ) {
                        $select = sprintf
                            (
                                '<select id="%s" name="%s">%s</select>',
                                $html_element_name,
                                $html_element_name,
                                $select
                            );
                    }
                }
                $content .= apply_filters( $filter_name, $select );
                break;
            case 'textarea':
                $value = $this->get_option($option['name'], $option_group);
                $content .= sprintf
                    (
                        '<textarea name="%s" class="%s" rows="%d">%s</textarea>',
                        $html_element_name,
                        $option['class'],
                        isset($option['rows'])? $option['rows']:3,
                        (!$value && isset($option['default']))? $option['default']:$value
                    );
                break;
            case 'heading':
                if ( isset( $option['label'] ) && $option['label'] ) {
                    $classes = array();
                    if ( $this->get_option( 'last_used_tab' ) == $label_index ) {
                        $classes[] = 'selected';
                    }
                    $content .= sprintf(
                        '<h3 id="options-%s"%s>%s</h3>',
                        sanitize_title_with_dashes(remove_accents($option['label'])),
                        count( $classes )? ' class="'.implode( ' ', $classes ).'"':'',
                        $option['label']
                    );
                    $label_index++;
                    $i = 0;
                }
                break;
            case 'info':
                $content .= $option['value'];
                break;
            case 'serialize':
                if ( isset( $option['callback'] ) && is_callable( $option['callback'] ) ) {
                    $content .= $option['callback']( $this->get_option( $option['name'], $option_group ), $option['name'] );
                } elseif ( isset( $option[ 'call_user_func' ] ) && isset( $option[ 'call_user_data' ] ) && is_callable( $option[ 'call_user_func' ] ) ) {
                    ob_start();
                    call_user_func_array( $option[ 'call_user_func' ], $option[ 'call_user_data' ] );
                    $content .= ob_get_contents();
                    ob_end_clean();
                }
                break;
            case 'subheading':
                $content .= sprintf( '<h4 class="title">%s</h4>', $option['label'] );
                break;
            case 'wpColorPicker':
                if ( is_admin() ) {
                    wp_enqueue_style( 'wp-color-picker' );
                    wp_enqueue_script( 'wp-color-picker' );
                }
                $id = '';
                if ( isset($option['use_name_as_id']) && $option['use_name_as_id']) {
                    $id = sprintf( ' id="%s"', $html_element_name );
                }
                $content .= apply_filters(
                    $filter_name,
                    sprintf (
                        '<input type="text" name="%s" value="%s" class="wpColorPicker %s"%s%s /> %s',
                        $html_element_name,
                        $this->get_option( $option['name'], $option_group ),
                        isset($option['class']) && $option['class']? $option['class']:'',
                        $id,
                        ( isset( $option['need_pro'] ) and $option['need_pro'] )? ' disabled="disabled"':'',
                        isset($option['label'])?  $option['label']:'',
                        $html_element_name
                    )
                );
                break;
            default:
                $content .= sprintf('not implemented type: %s', $option['type']);
            }
            if ( $option['type'] != 'hidden' ) {
                if ( isset ( $option['description'] ) && $option['description'] ) {
                    if ( isset ( $option['label'] ) && $option['label'] ) {
                        $content .= '<br />';
                    }
                    $content .= sprintf('<span class="description">%s</span>', $option['description']);
                }
                $content .= '</td>';
                $content .= '</tr>';
            }
        }
        /**
         * filter
         */
        if ( isset( $option['filter'] ) ) {
            $content .= apply_filters( $option['filter'], '' );
        }
        /**
         * content
         */
        if ($content) {
            if ( isset ( $options['label'] ) && $options['label'] && !$use_tabs ) {
                $top .= sprintf('<h3>%s</h3>', $options['label']);
            }
            $top .= $hidden;
            if ( $use_tabs ) {
                $content .= '</tbody></table>';
                $content .= '</fieldset>';
                $content = $top.$content;
            } else {
                $top .= sprintf( '<table class="form-table%s" style="%s">', isset($options['widefat'])? ' widefat':'', isset($options['style'])? $options['style']:'' );
                if ( isset( $options['thead'] ) ) {
                    $top .= '<thead><tr>';
                    foreach( $options['thead'] as $text => $colspan ) {
                        $top .= sprintf
                            (
                                '<th%s>%s</th>',
                                $colspan > 1? ' colspan="'.$colspan.'"':'',
                                $text
                            );
                    }
                    $top .= '</tr></thead>';
                }
                $top .= '<tbody>';
                $content = $top.$content;
                $content .= '</tbody></table>';
            }
        }
        if ( $use_tabs ) {
            $content .= '</div>';
        }
        /**
         * submit button
         */
        $content .= get_submit_button( __( 'Save Changes' ), 'primary', 'submit_button' );
        /* print ? */
        if ( $echo ) {
            echo $content;
            return;
        }
        return $content;
    }

    public function options_init()
    {
        $options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
        foreach( $options as $key => $data ) {
            if ( isset ( $data['options'] ) && is_array( $data['options'] ) ) {
                $option_group = $this->option_prefix.$key;
                foreach ( $data['options'] as $option ) {
                    if ( $option['type'] == 'heading' || !isset($option['name']) ) {
                        continue;
                    }
                    register_setting (
                        $option_group,
                        $this->option_prefix.$option['name'],
                        isset($option['sanitize_callback'])? $option['sanitize_callback']:null
                    );
                }
            }
        }
    }

    public function get_values($option_name, $option_group = 'index')
    {
        $this->option_group = $option_group;
        $data = $this->get_option_array( $option_group );
        $data = $data['options'];
        foreach( $data as $one ) {
            if ( isset( $one[ 'name' ] ) && $one[ 'name' ] != $option_name ) {
                continue;
            }
            switch( $one['type'] ) {
            case 'checkbox_group':
                return $one['options'];
            case 'radio':
                return $one['radio'];
            }
        }
        return;
    }

    public function get_default_value($option_name, $option_group = 'index')
    {
        $this->option_group = $option_group;
        $options = $this->get_option_array( $option_group );
        /**
         * check options exists?
         */
        if ( !is_array( $options['options'] ) ) {
            return null;
        }
        foreach ( $options['options'] as $option ) {
            if ( isset( $option['name'] ) && $option['name'] == $option_name ) {
                return isset( $option['default'] )? $option['default']:null;
            }
        }
        return null;
    }

    public function activate()
    {
        $options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
        foreach( $options as $key => $data ) {
            foreach ( $data['options'] as $option ) {
                if ( $option['type'] == 'heading' or !isset( $option['name'] ) or !$option['name'] or !isset( $option['default'] ) ) {
                    continue;
                }
                add_option( $this->option_prefix.$option['name'], $option['default'], '', isset($option['autoload'])? $option['autoload']:'yes' );
            }
        }
        add_option( $this->option_prefix.'cache_stamp', date('c') );
    }

    public function deactivate()
    {
        $options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
        foreach( $options as $key => $data ) {
            foreach ( $data['options'] as $option ) {
                if ( 'heading' == $option['type'] or !isset( $option['name'] ) or !$option['name'] ) {
                    continue;
                }
                /**
                 * prevent special options
                 */
                if ( isset( $option[ 'dont_deactivate' ] ) && $option[ 'dont_deactivate' ] ) {
                    continue;
                }
                delete_option( $this->option_prefix.$option['name'] );
            }
        }
        delete_option( $this->option_prefix.'cache_stamp' );
    }

    public function settings_fields($option_name)
    {
        settings_fields( $this->option_prefix . $option_name );
    }

    /**
     * admin_notices
     */

    public function admin_notices()
    {
        if ( empty( $this->notices ) ) {
            return;
        }
        foreach( $this->notices as $notice ) {
            printf( '<div class="error"><p>%s</p></div>', $notice );
        }
    }

    /**
     * options: add, get, update
     */

    public function add_option($option_name, $option_value, $autoload = true)
    {
        $autoload = $autoload? 'yes':'no';
        add_option( $this->option_prefix.$option_name, $option_value, null, $autoload );
    }

    public function get_option($option_name, $option_group = 'index', $default_value = null, $forece_default = false)
    {
        $option_value = get_option( $this->option_prefix.$option_name, null );
        $default_value = $this->get_default_value( $option_name, $option_group );
        if ( $forece_default && null == $option_value ) {
            $option_value = $default_value;
        }
        if ( null == $option_value && !empty( $default_value ) ) {
            return $default_value;
        }
        return $option_value;
    }

    public function get_option_name($name)
    {
        return sprintf( '%s%s', $this->option_prefix, $name );
    }

    public function update_option($option_name, $option_value)
    {
        /**
         * delete if option have a default value
         */
        $default_value = $this->get_default_value( $this->option_prefix.$option_name );
        if ( $option_name === $default_value ) {
            delete_option( $this->option_prefix.$option_name );
            return;
        }
        update_option( $this->option_prefix.$option_name, $option_value );
    }

    /**
     * helpers
     */

    public function select_page_helper($name, $show_option_none = false, $post_type = 'page')
    {
        $args = array(
            'echo' => false,
            'name' => $this->get_option_name( $name ),
            'selected' => $this->get_option( $name ),
            'show_option_none' => $show_option_none,
            'post_type' => $post_type,
        );
        return wp_dropdown_pages( $args );
    }

    public function select_category_helper($name, $hide_empty = null)
    {
        $args = array(
            'name'         => $this->get_option_name( $name ),
            'selected'     => $this->get_option( $name ),
            'hierarchical' => true,
            'hide_empty'   => $hide_empty
        );
        wp_dropdown_categories( $args );
    }

    public function get_option_group()
    {
        return $this->option_group;
    }
}

/**

== Changelog ==

= 1.7.4 (2013-08-27) =

* #IMPROVMENT: added filter to change options
* #IMPROVMENT: added get_option_group function
* #IMPROVMENT: added helper for wp_dropdown_categories
* #IMPROVMENT: added helper for wp_dropdown_pages

= 1.7.3 (2013-06-04) =

* #BUGFIX: repair get_option, to prevent return always default if null
* #IMPROVMENT: added force_default to get_option method

= 1.7.2 (2013-05-23) =

* IMPROVMENT: add min/max attributes to filed type "number"
* #IMPROVMENT: add get_option_name method

 */
