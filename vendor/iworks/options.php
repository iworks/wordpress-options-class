<?php
/*
Class Name: iWorks Options
Class URI: http://iworks.pl/
Description: Option class to manage options.
Version: 2.2.0
Author: Marcin Pietrzak
Author URI: http://iworks.pl/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2011-2014 Marcin Pietrzak (marcin@iworks.pl)

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

if ( class_exists( 'iworks_options' ) ) {
    return;
}

class iworks_options
{
    private $option_function_name;
    private $option_group;
    private $option_prefix;
    private $version;
    private $pagehooks = array();
    public $notices;

    public function __construct()
    {
        $this->notices              = array();
        $this->version              = '2.2.0';
        $this->option_group         = 'index';
        $this->option_function_name = null;
        $this->option_prefix        = null;

        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'admin_menu', array($this, 'admin_menu' ) );
        add_filter( 'screen_layout_columns', array($this, 'screen_layout_columns'), 10, 2);
    }

    public function admin_menu()
    {
        if ( !isset($this->options ) ) {
            return;
        }
        foreach( $this->options as $key => $data ) {
            if ( !array_key_exists( 'menu', $data ) ) {
                $data['menu'] = '';
            }
            switch( $data['menu'] ) 
            {
            case 'comments':
            case 'dashboard':
            case 'links':
            case 'management':
            case 'media':
            case 'options':
            case 'pages':
            case 'plugins':
            case 'posts':
            case 'posts':
            case 'theme':
            case 'users':
                $function = sprintf( 'add_%s_page', $data['menu'] );
                break;
            default:
                $function = 'add_menu_page';
                break;
            }
            $this->pagehooks[$key] = $function(
                $data['page_title'],
                $data['menu_title'],
                'manage_options',
                $this->get_option_name( $key ),
                array( $this, 'show_page' )
            );
            add_action( 'load-'.$this->pagehooks[$key], array( $this, 'load_page' ) );
        }
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
        $options = array();
        if ( array_key_exists( $this->option_group, $options ) && !empty( $options[ $this->option_group ] ) ) {
            $options = apply_filters( $this->option_function_name, $this->options );
            return $options[ $this->option_group ];
        }
        if ( is_callable( $this->option_function_name ) ) {
            $options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
        }
        if ( array_key_exists( $this->option_group, $options ) && !empty( $options[ $this->option_group ] ) ) {
            $this->options[ $this->option_group ] = $options[ $this->option_group ];
            return apply_filters( $this->option_function_name, $this->options[ $this->option_group ] );
        }
        return apply_filters( $this->option_function_name, array() );
    }

    public function build_options($option_group = 'index', $echo = true, $term_id = false)
    {
        $this->option_group = $option_group;
        $options = $this->get_option_array( $option_group );
        /**
         * add some defaults
         */
        $options['show_submit_button'] = true;
        $options['add_table'] = true;
        if ( !array_key_exists( 'type', $options ) ) {
            $options['type'] = 'option';
        }
        /**
         * add defaults for taxonomies
         */
        if ( 'taxonomy' == $options['type'] ) {
            $options['show_submit_button'] = false;
            $options['add_table'] = false;
        }
        /**
         * check options exists?
         */
        if ( !is_array($options['options'] ) ) {
            echo '<div class="below-h2 error"><p><strong>'.__('An error occurred while getting the configuration.', 'iworks_options').'</strong></p></div>';
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
            $top .= sprintf(
                '<div id="hasadmintabs" class="ui-tabs ui-widget ui-widget-content ui-corner-all" data-prefix="%s">',
                $this->option_prefix
            );
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
            /**
                * add default type
                */
            if ( !array_key_exists('type', $option ) ) {
                $option['type'] = 'text';
            }
            /**
             * check show option
             */
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
             * get option name
             */
            $option_name = false;
            if ( array_key_exists( 'name', $option ) && $option['name'] ) {
                $option_name = $option['name'];
                if ( 'taxonomy' == $options['type'] ) {
                    $option_name = sprintf(
                        '%s_%s_%s',
                        $option_group,
                        $term_id,
                        $option_name
                    );
                }
            }
            /**
             * dismiss if have "callback_to_show" and return false
             */
            if ( !preg_match( '/^(heading|info)$/', $option[ 'type' ] ) && isset( $option['callback_to_show'] ) && is_callable( $option['callback_to_show'] ) ) {
                if ( false === $option['callback_to_show']( $this->get_option( $option_name, $option_group ) ) ) {
                    continue;
                }
            }
            /**
             * heading
             */
            if ( preg_match( '/^(heading|page)$/', $option['type'] ) ) {
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
                    'email',
                    'image',
                    'number',
                    'radio',
                    'text',
                    'textarea',
                ) ) ) {
                    $html_element_name = $option_name? $this->option_prefix.$option_name:'';
                    $content .= sprintf (
                        '<input type="hidden" name="%s" value="%s" /> %s',
                        $html_element_name,
                        $this->get_option( $option_name, $option_group ),
                        "\n"
                    );
                }
                continue;
            }
            if ( $option['type'] == 'heading' ) {
                if ( $use_tabs ) {
                    if ( $last_tab != $option['label'] ) {
                        $last_tab = $option['label'];
                        if ( $options['add_table'] ) {
                            $content .= '</tbody></table>';
                        }
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
                    if ( $options['add_table'] ) {
                        $content .= sprintf(
                            '<table class="form-table%s" style="%s">',
                            isset($options['widefat'])? ' widefat':'',
                            isset($options['style'])? $options['style']:''
                        );
                        $content .= '<tbody>';
                    }
                }
                $content .= '<tr><td colspan="2">';
            } elseif ( 'subheading' == $option['type'] ) {
                $content .= '<tr><td colspan="2">';
            } elseif ( 'hidden' != $option['type'] ) {
                $style = '';
                if ( isset($option['related_to'] ) && isset( $related_to[ $option['related_to'] ] ) && $related_to[ $option['related_to'] ] == 0 ) {
                    $style .= 'style="display:none"';
                }
                $content .= sprintf( '<tr valign="top" id="tr_%s"%s>', $option_name? $option_name:'', $style );
                $content .= sprintf( '<th scope="row">%s</th>', isset($option['th']) && $option['th']? $option['th']:'&nbsp;' );
                $content .= '<td>';
            }
            $html_element_name = $option_name? $this->option_prefix.$option_name:'';
            $filter_name = $html_element_name? $option_group.'_'.$html_element_name : null;
            switch ( $option['type'] ) {
            case 'hidden':
                $hidden .= sprintf (
                    '<input type="hidden" name="%s" value="%s" />',
                    $html_element_name,
                    $this->get_option( $option_name, $option_group )
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
                    $this->get_option( $option_name, $option_group ),
                    isset($option['class']) && $option['class']? $option['class']:'',
                    $id,
                    isset($option['min'])?  'min="'.$option['min'].'"':'',
                    isset($option['max'])?  'max="'.$option['max'].'"':'',
                    isset($option['label'])?  $option['label']:''
                );
                break;
            case 'email':
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
                    $this->get_option( $option_name, $option_group ),
                    isset($option['class']) && $option['class']? $option['class']:'',
                    $id,
                    isset($option['label'])? $option['label']:''
                );
                break;
            case 'checkbox':
                $related_to[ $option_name ] = $this->get_option( $option_name, $option_group );
                $checkbox = sprintf (
                    '<label for="%s"><input type="checkbox" name="%s" id="%s" value="1"%s%s /> %s</label>',
                    $html_element_name,
                    $html_element_name,
                    $html_element_name,
                    $related_to[ $option_name ]? ' checked="checked"':'',
                    ( ( isset($option['disabled']) && $option['disabled'] ) or ( isset( $option['need_pro'] ) && $option['need_pro'] ) )? ' disabled="disabled"':'',
                    isset($option['label'])?  $option['label']:''
                );
                $content .= apply_filters( $filter_name, $checkbox );
                break;
            case 'checkbox_group':
                $option_value = $this->get_option($option_name, $option_group );
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
                    $id = sprintf( '%s%d', $option_name, $i++ );
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
                $option_value = $this->get_option( $option_name, $option_group );
                $i = 0;
                /**
                 * check user add "radio" or "options".
                 */
                $radio_options = array();
                if ( array_key_exists('options', $option) ) {
                    $radio_options = $option['options'];
                } else if ( array_key_exists('radio', $option) ) {
                    $radio_options = $option['radio'];
                }
                if ( empty($radio_options) ) {
                    $content .= sprintf(
                        '<p>Error: no <strong>radio</strong> array key for option: <em>%s</em>.</p>',
                        $option_name
                    );
                } else {
                    /**
                     * add extra options, maybe dynamic?
                     */
                    $radio_options = apply_filters( $filter_name.'_data', $radio_options );
                    $radio = apply_filters( $filter_name.'_content', null, $radio_options, $html_element_name, $option_name, $option_value );
                    if ( empty( $radio ) ) {
                        foreach ($radio_options as $value => $input) {
                            $id = sprintf( '%s%d', $option_name, $i++ );
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
                            $radio = sprintf('<ul>%s</ul>', $radio);
                        }
                    }
                    $content .= apply_filters( $filter_name, $radio );
                }
                break;
            case 'select':
                $option_value = $this->get_option( $option_name, $option_group );

                if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
                    $option['options'] = array_merge( $option['options'], $option['extra_options']());
                }
                $option['options'] = apply_filters( $filter_name.'_data', $option['options'], $option_name, $option_value );

                $select = apply_filters( $filter_name.'_content', null, $option['options'], $html_element_name, $option_name, $option_value );
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
                $value = $this->get_option($option_name, $option_group);
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
                    $content .= $option['callback']( $this->get_option( $option_name, $option_group ), $option_name );
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
                        $this->get_option( $option_name, $option_group ),
                        isset($option['class']) && $option['class']? $option['class']:'',
                        $id,
                        ( isset( $option['need_pro'] ) and $option['need_pro'] )? ' disabled="disabled"':'',
                        isset($option['label'])?  $option['label']:'',
                        $html_element_name
                    )
                );
                break;
            case 'image':
                if ( isset ( $option['description'] ) && $option['description'] ) {
                    printf( '<p class="description">%s</p>', $option['description'] );
                }
                $value = $this->get_option($option_name, $option_group);
                $content .= sprintf(
                    '<img id="%s_img" src="%s" alt="" style="%s%sclear:right;display:block;margin-bottom:10px;" />',
                    $html_element_name,
                    $value? $value : '',
                    array_key_exists('max-width', $option) && is_integer($option['max-width'])? sprintf('max-width: %dpx;', $option['max-width']):'',
                    array_key_exists('max-height', $option) && is_integer($option['max-height'])? sprintf('max-height: %dpx;', $option['max-height']):''
                );
                $content .= sprintf(
                    '<input type="hidden" name="%s" id="%s" value="%s" />',
                        $this->get_option( $option_name, $option_group ),
                        $this->get_option( $option_name, $option_group ),
                    $value
                );
                $content .= sprintf(
                    ' <input type="button" class="button iworks_upload_button" value="%s" rel="#%s" />',
                    __( 'Upload image', 'iworks_options' ),
                    $html_element_name
                );
                if ( !empty($value) || ( array_key_exists('default', $option) && $value != $option['default'] ) ) {
                    $content .= sprintf(
                        ' <input type="submit" class="button iworks_delete_button" value="%s" rel="#%s%s" />',
                        __( 'Delete image', 'iworks_options' ),
                        $html_element_name
                    );
                }
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
                if ( $options['add_table'] ) {
                    $content .= '</tbody></table>';
                }
                $content .= '</fieldset>';
                $content = $top.$content;
            } else {
                if ( $options['add_table'] ) {
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
                }
                $content = $top.$content;
                if ( $options['add_table'] ) {
                    $content .= '</tbody></table>';
                }
            }
        }
        if ( $use_tabs ) {
            $content .= '</div>';
        }
        /**
         * submit button
         */
        if ( $options['show_submit_button'] ) {
            $content .= get_submit_button( __( 'Save Changes' ), 'primary', 'submit_button' );
        }
        /* print ? */
        if ( $echo ) {
            echo $content;
            return;
        }
        return $content;
    }

    private function register_setting($options, $option_group)
    {
        foreach ( $options as $option ) {
            /**
             * don't register setting without type and name
             */
            if ( !array_key_exists( 'type', $option ) || !array_key_exists('name', $option ) ) {
                continue;
            }
            /**
             * don't register certain type setting or with empty name
             */
            if ( preg_match( '/^(sub)?heading$/', $option['type'] ) || empty($option['name']) ) {
                continue;
            }
            /**
             * register setting
             */
            register_setting (
                $this->option_prefix.$option_group,
                $this->option_prefix.$option['name'],
                isset($option['sanitize_callback'])? $option['sanitize_callback']:null
            );
        }
    }

    public function options_init()
    {
        $options = array();
        if ( is_callable( $this->option_function_name ) ) {
            $options = call_user_func( $this->option_function_name );
        }
        $options = apply_filters( $this->option_function_name, $options );
        foreach( $options as $key => $data ) {
            if ( isset ( $data['options'] ) && is_array( $data['options'] ) ) {
                $this->register_setting( $data['options'], $key );
            } elseif ( 'options' == $key ) {
                $this->register_setting( $data, 'theme' );
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
        if ( !array_key_exists( 'options', $options ) or !is_array( $options['options'] ) ) {
            return null;
        }
        /**
         * default key name
         */
        $default_option_name = $option_name;
        /**
         * default name for taxonomies
         */
        if ( array_key_exists( 'type', $options ) && 'taxonomy' == $options['type'] ) {
            $re = sprintf( '/^%s_\d+_/', $option_group );
            $default_option_name = preg_replace( $re, '', $default_option_name );
        }
        foreach ( $options['options'] as $option ) {
            if ( isset( $option['name'] ) && $option['name'] == $default_option_name ) {
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
        delete_option( $this->option_prefix.'version' );
        delete_option( $this->option_prefix.'flush_rules' );
    }

    public function settings_fields($option_name, $use_prefix = true)
    {
        if ( $use_prefix ) {
            settings_fields( $this->option_prefix . $option_name );
        } else {
            settings_fields( $option_name );
        }
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
        if ( ( $default_value || $forece_default ) && is_null( $option_value ) ) {
            $option_value = $default_value;
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
     * update taxonomy options
     */

    public function update_taxonomy_options($option_group, $term_id)
    {
        $this->option_group = $option_group;
        $options = $this->get_option_array( $option_group );
        /**
         * only for taxonomies
         */
        if ( !array_key_exists( 'type', $options ) ) {
            return;
        }
        if ( 'taxonomy' != $options['type'] ) {
            return;
        }
        foreach ($options['options'] as $option) {
            if ( !array_key_exists( 'name', $option ) || !$option['name'] ) {
                continue;
            }
            $option_name = sprintf(
                '%s_%s_%s',
                $option_group,
                $term_id,
                $option['name']
            );
            $value = array_key_exists( $this->get_option_name($option_name), $_POST )? $_POST[$this->get_option_name($option_name)]:false;

            if ( array_key_exists( 'sanitize', $option ) && is_callable( $option['sanitize'] ) ) {
                $value = call_user_func( $option['sanitize'], $value );

            }
            if ( $value ) {
                $this->update_option( $option_name, $value  );
            } else {
                delete_option( $option_name );
            }
        }
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

    private function get_option_index_from_screen()
    {
        $screen = get_current_screen();
        $key = explode( $this->option_prefix, $screen->id );
        if ( 2 != count( $key ) ) {
            return false;
        }
        return $key[1];
    }

    public function show_page()
    {
        $option_name = $this->get_option_index_from_screen();
        if ( !$option_name ) {
            return;
        }
        $options = $this->options[$option_name];
        global $screen_layout_columns;
        $data = array();
?>
<div class="wrap">
    <h2><?php echo $options['page_title']; ?></h2>
    <form method="post" action="options.php" id="iworks_upprev_admin_index">
        <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
        <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
        <input type="hidden" name="action" value="save_howto_metaboxes_general" />
        <div id="poststuff" class="metabox-holder<?php echo empty($screen_layout_columns) || 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
            <div id="side-info-column" class="inner-sidebar">
                <?php do_meta_boxes($this->pagehooks[$option_name], 'side', $this); ?>
            </div>
            <div id="post-body" class="has-sidebar">
                <div id="post-body-content" class="has-sidebar-content">
<?php
        $this->settings_fields( $option_name );
        $this->build_options( $option_name );
?>
                </div>
            </div>
            <br class="clear"/>
        </div>
    </form>
</div>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready( function($) {
    // close postboxes that should be closed
    $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
    // postboxes setup
    postboxes.add_postbox_toggles('<?php echo $this->pagehooks[$option_name]; ?>');
});
<?php
        if ( array_key_exists('use_tabs', $this->options[$option_name] ) && $this->options[$option_name]['use_tabs'] ) {
?>
jQuery(function(){iworks_options_tabulator_init();});
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
        jQuery('#hasadmintabs input[name=<?php echo $this->get_option_name('last_used_tab'); ?>]').val(jQuery(this).parent().index());
    });
}
<?php
        }
?>
//]]>
</script>
<?php
    }

    public function load_page()
    {
        $option_name = $this->get_option_index_from_screen();
        if ( !$option_name ) {
            return;
        }
        /**
         * check options for key
         */
        if ( !array_key_exists( $option_name, $this->options ) ) {
            return;
        }
        /**
         * check metaboxes for key
         */
        if ( !array_key_exists( 'metaboxes', $this->options[$option_name] ) ) {
            return;
        }
        if ( !count( $this->options[$option_name]['metaboxes'] ) ) {
            return;
        }
        /**
         * ensure, that the needed javascripts been loaded to allow drag/drop, 
         * expand/collapse and hide/show of boxes
         */
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

        foreach( $this->options[$option_name]['metaboxes'] as $id => $data ) {
            add_meta_box(
                $id,
                $data['title'],
                $data['callback'],
                $this->pagehooks[$option_name],
                $data['context'],
                $data['priority']
            );
        }
        /**
         * wp_enqueue_script
         */
        if ( array_key_exists( 'enqueue_scripts', $this->options[$option_name] ) ) {
            foreach( $this->options[$option_name]['enqueue_scripts'] as $script ) {
                wp_enqueue_script( $script );
            }
        }
        /**
         * wp_enqueue_style
         */
        if ( array_key_exists( 'enqueue_styles', $this->options[$option_name] ) ) {
            foreach( $this->options[$option_name]['enqueue_styles'] as $style ) {
                wp_enqueue_style( $style );
            }
        }
    }

    public function screen_layout_columns($columns, $screen)
    {
        foreach( $this->pagehooks as $option_name => $pagehook ) {
            if ($screen == $pagehook) {
                $columns[$pagehook] = 2;
            }
        }
        return $columns;
    }

}
