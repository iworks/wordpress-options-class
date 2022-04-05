<?php
/*
Class Name: iWorks Options
Class URI: http://iworks.pl/
Description: Option class to manage options.
Version: 2.8.3
Author: Marcin Pietrzak
Author URI: http://iworks.pl/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2011-2022 Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA

 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( class_exists( 'iworks_options' ) ) {
	return;
}

class iworks_options {

	private $option_function_name;
	private $option_group;
	private $option_prefix;
	private $version;
	private $pagehooks        = array();
	private $scripts_enqueued = array();
	/**
	 * Controll class mode.
	 *
	 * @since 2.6.5
	 */
	private $mode = 'plugin';

	public $notices;

	/**
	 * self file
	 *
	 * @since 2.7.3
	 */
	private $__file__ = __FILE__;

	/**
	 * call from plugin
	 *
	 * @since 2.7.3
	 */
	private $plugin = '-not-set-';

	public function __construct() {
		/**
		 * basic setup
		 */
		$this->notices              = array();
		$this->version              = '2.8.3';
		$this->option_group         = 'index';
		$this->option_function_name = null;
		$this->option_prefix        = null;
		/**
		 * afer basic setup
		 */
		$this->files = $this->get_files();
		/**
		 * hooks
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ), 0 );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_filter( 'screen_layout_columns', array( $this, 'screen_layout_columns' ), 10, 2 );
	}

	public function init() {
		$this->get_option_array();
	}

	/**
	 * Set option class mode.
	 *
	 * @since 2.6.5
	 *
	 * @param string $mode Working mode, possible values "plugin", "theme".
	 */
	public function set_mode( $mode ) {
		if ( preg_match( '/^(plugin|theme)$/', $mode ) ) {
			$this->mode = $mode;
		}
	}

	/**
	 * Get group
	 *
	 * @since 2.6.7
	 *
	 * @param string $option_group Name of config group.
	 */
	public function get_group( $option_group = null ) {
		if ( null === $option_group ) {
			$option_group = $this->option_group;
		}
		return $this->get_option_array( $option_group );
	}

	public function admin_menu() {
		$data = $this->get_option_array();
		if ( ! isset( $this->options ) ) {
			return;
		}
		$pages          = array();
		$pages['index'] = $data;
		if ( isset( $data['pages'] ) ) {
			$pages = $data['pages'] + $pages;
		}
		foreach ( $pages as $key => $data ) {
			$keys_to_sanitize = array( 'menu', 'parent' );
			foreach ( $keys_to_sanitize as $key_to_sanitize ) {
				if ( ! array_key_exists( $key_to_sanitize, $data ) ) {
					$data[ $key_to_sanitize ] = '';
				}
			}
			if ( 'submenu' == $data['menu'] ) {
				if ( ! empty( $data['parent'] ) ) {
					/**
					 * Check callback
					 */
					$callback = array( $this, 'show_page' );
					if ( isset( $data['show_page_callback'] ) && is_callable( $data['show_page_callback'] ) ) {
						$callback = $data['show_page_callback'];
					}
					if ( isset( $data['set_callback_to_null'] ) && $data['set_callback_to_null'] ) {
						$callback = null;
					}
					/**
					 * add submenu
					 */
					$this->pagehooks[ $key ] = add_submenu_page(
						$data['parent'],
						$data['page_title'],
						isset( $data['menu_title'] ) ? $data['menu_title'] : $data['page_title'],
						apply_filters( 'iworks_options_capability', 'manage_options', 'settings' ),
						isset( $data['menu_slug'] ) ? $data['menu_slug'] : $this->get_option_name( $key ),
						$callback
					);
					add_action( 'load-' . $this->pagehooks[ $key ], array( $this, 'load_page' ) );
				}
			} else {
				switch ( $data['menu'] ) {
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
				if ( isset( $data['page_title'] ) ) {
					$this->pagehooks[ $key ] = $function(
						$data['page_title'],
						isset( $data['menu_title'] ) ? $data['menu_title'] : $data['page_title'],
						'manage_options',
						$this->get_option_name( $key ),
						array( $this, 'show_page' )
					);
					add_action( 'load-' . $this->pagehooks[ $key ], array( $this, 'load_page' ) );
				}
			}
		}
	}

	public function get_version() {
		return $this->version;
	}

	public function set_option_function_name( $option_function_name ) {
		$this->option_function_name = $option_function_name;
	}

	public function set_option_prefix( $option_prefix ) {
		$this->option_prefix = $option_prefix;
	}

	private function get_option_array( $option_group = null ) {
		if ( null === $option_group ) {
			$option_group = $this->option_group;
		}
		$options = array();
		if ( array_key_exists( $option_group, $options ) && ! empty( $options[ $option_group ] ) ) {
			$options = apply_filters( $this->option_function_name, $this->options );
			return $options[ $option_group ];
		}
		if ( is_callable( $this->option_function_name ) ) {
			$options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
		}
		if ( array_key_exists( $option_group, $options ) && ! empty( $options[ $option_group ] ) ) {
			$this->options[ $option_group ] = $options[ $option_group ];
			return apply_filters( $this->option_function_name, $this->options[ $option_group ] );
		}
		return apply_filters( $this->option_function_name, array() );
	}

	public function build_options( $option_group = 'index', $echo = true, $term_id = false ) {
		$this->option_group = $option_group;
		$options            = $this->get_option_array();
		/**
		 * add some defaults
		 */
		$options['show_submit_button'] = true;
		$options['add_table']          = true;
		if ( ! array_key_exists( 'type', $options ) ) {
			$options['type'] = 'option';
		}
		/**
		 * add defaults for taxonomies
		 */
		if ( 'taxonomy' == $options['type'] ) {
			$options['show_submit_button'] = false;
			$options['add_table']          = false;
		}
		/**
		 * check options exists?
		 */
		if ( ! is_array( $options['options'] ) ) {
			echo '<div class="below-h2 error"><p><strong>' . __( 'An error occurred while getting the configuration.', 'IWORKS_OPTIONS_TEXTDOMAIN' ) . '</strong></p></div>';
			return;
		}
		/**
		 * proceder
		 */
		$is_simple = 'simple' == $this->get_option( 'configuration', 'index', 'advance' );
		$content   = '';
		$hidden    = '';
		$top       = '';
		$use_tabs  = isset( $options['use_tabs'] ) && $options['use_tabs'];
		/**
		 * add last_used_tab field
		 */
		if ( $use_tabs ) {
			$field = array(
				'type'  => 'hidden',
				'name'  => 'last_used_tab',
				'id'    => 'last_used_tab',
				'value' => $this->get_option( 'last_used_tab' ),
			);
			array_unshift( $options['options'], $field );
		}
		/**
		 * produce options
		 */
		if ( $use_tabs ) {
			$top .= sprintf(
				'<div id="hasadmintabs" class="ui-tabs ui-widget ui-widget-content ui-corner-all" data-prefix="%s">',
				$this->option_prefix
			);
		}
		$i             = 0;
		$label_index   = 0;
		$last_tab      = null;
		$related_to    = array();
		$configuration = 'all';
		foreach ( $options['options'] as $option ) {
			if ( isset( $option['capability'] ) ) {
				if ( ! current_user_can( $option['capability'] ) ) {
					continue;
				}
			}
			/**
				* add default type
				*/
			if ( ! array_key_exists( 'type', $option ) ) {
				$option['type'] = 'text';
			}
			/**
			 * check show option
			 */
			$show_option = true;
			if ( isset( $option['check_supports'] ) && is_array( $option['check_supports'] ) && count( $option['check_supports'] ) ) {
				foreach ( $option['check_supports'] as $support_to_check ) {
					if ( ! current_theme_supports( $support_to_check ) ) {
						$show_option = false;
					}
				}
			}
			if ( ! $show_option ) {
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
			if ( ! preg_match( '/^(heading|info)$/', $option['type'] ) && isset( $option['callback_to_show'] ) && is_callable( $option['callback_to_show'] ) ) {
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
			if ( ( $is_simple && $configuration == 'advance' ) || ( ! $is_simple && $configuration == 'simple' ) ) {
				if ( isset( $option['configuration'] ) && 'both' == $option['configuration'] ) {
					continue;
				}
				if ( in_array(
					$option['type'],
					array(
						'checkbox',
						'email',
						'image',
						'number',
						'radio',
						'text',
						'textarea',
						'url',
					)
				) ) {
					$html_element_name = $option_name ? $this->option_prefix . $option_name : '';
					$content          .= sprintf(
						'<input type="hidden" name="%s" value="%s" /> %s',
						$html_element_name,
						$this->get_option( $option_name, $option_group ),
						"\n"
					);
				}
				continue;
			}
			$tr_classes = array(
				'iworks-options-row',
				sprintf( 'iworks-options-type-%s', esc_attr( strtolower( $option['type'] ) ) ),
			);
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
						( isset( $option['class'] ) && $option['class'] ) ? ' rel="' . $option['class'] . '"' : ''
					);
					if ( ! $use_tabs ) {
						$content .= sprintf( '<h3>%s</h3>', $option['label'] );
					}
					if ( $options['add_table'] ) {
						$content .= sprintf(
							'<table class="form-table%s" style="%s" role="presentation">',
							isset( $options['widefat'] ) ? ' widefat' : '',
							isset( $options['style'] ) ? $options['style'] : ''
						);
						$content .= '<tbody>';
					}
				}
				$content .= sprintf( '<tr class="%s"><td colspan="2">', implode( ' ', $tr_classes ) );
			} elseif ( 'subheading' == $option['type'] ) {
				$content .= '<tr><td colspan="2">';
			} elseif ( 'hidden' != $option['type'] ) {
				if ( isset( $option['related_to'] ) && isset( $related_to[ $option['related_to'] ] ) && $related_to[ $option['related_to'] ] == 0 ) {
					$classes[] = 'hidden';
				}
				$content .= sprintf(
					'<tr valign="top" id="tr_%s" class="%s">',
					esc_attr( $option_name ? $option_name : '' ),
					implode( ' ', $tr_classes )
				);
				$content .= sprintf(
					'<th scope="row">%s%s</th>',
					isset( $option['dashicon'] ) && $option['dashicon'] ? sprintf( '<span class="dashicons dashicons-%s"></span>&nbsp;', $option['dashicon'] ) : '',
					isset( $option['th'] ) && $option['th'] ? $option['th'] : '&nbsp;'
				);
				$content .= '<td>';
			}
			$html_element_name = $option_name ? $this->option_prefix . $option_name : '';
			$filter_name       = $html_element_name ? $option_group . '_' . $html_element_name : null;
			/**
			 * classes
			 */
			$classes   = isset( $option['classes'] ) ? $option['classes'] : ( isset( $option['class'] ) ? explode( ' ', $option['class'] ) : array() );
			$classes[] = sprintf( 'option-%s', $option['type'] );
			/**
			 * build
			 */
			switch ( $option['type'] ) {
				case 'hidden':
					$hidden .= sprintf(
						'<input type="hidden" name="%s" value="%s" id="%s" />',
						esc_attr( $html_element_name ),
						esc_attr( $this->get_option( $option_name, $option_group ) ),
						esc_attr( isset( $option['id'] ) ? $option['id'] : '' )
					);
					break;
				case 'number':
					$args      = array();
					$args_keys = array( 'min', 'max', 'step' );
					foreach ( $args_keys as $arg_key ) {
						if ( isset( $option[ $arg_key ] ) ) {
							$args[ $arg_key ] = $option[ $arg_key ];
						}
					}
					if ( isset( $option['use_name_as_id'] ) && $option['use_name_as_id'] ) {
						$args['id'] = sprintf( ' id="%s"', $html_element_name );
					}
					$content .= sprintf(
						'<input type="%s" name="%s" value="%s" class="%s" %s /> %s',
						$option['type'],
						$html_element_name,
						$this->get_option( $option_name, $option_group ),
						esc_attr( implode( ' ', $classes ) ),
						$this->build_field_attributes( $args ),
						isset( $option['label'] ) ? $option['label'] : ''
					);
					break;
				case 'email':
				case 'password':
				case 'text':
				case 'url':
					$id = '';
					if ( isset( $option['use_name_as_id'] ) && $option['use_name_as_id'] ) {
						$id = sprintf( ' id="%s"', $html_element_name );
					}
					$content .= sprintf(
						'<input type="%s" name="%s" value="%s" class="%s"%s%s%s%s /> %s',
						$option['type'],
						$html_element_name,
						$this->get_option( $option_name, $option_group ),
						esc_attr( implode( ' ', $classes ) ),
						$id,
						isset( $option['maxlength'] ) ? sprintf( ' maxlength="%d"', $option['maxlength'] ) : '',
						isset( $option['placeholder'] ) ? sprintf( ' placeholder="%s"', esc_attr( $option['placeholder'] ) ) : '',
						isset( $option['aria-label'] ) ? sprintf( ' aria-label="%s"', esc_attr( $option['aria-label'] ) ) : '',
						isset( $option['label'] ) ? $option['label'] : ''
					);
					break;
				case 'checkbox':
					$related_to[ $option_name ] = $this->get_option( $option_name, $option_group );
					$checkbox                   = sprintf(
						'<label for="%s"><input type="checkbox" name="%s" id="%s" value="1"%s%s class="%s" /> %s</label>',
						$html_element_name,
						$html_element_name,
						$html_element_name,
						checked( $related_to[ $option_name ], true, false ),
						( ( isset( $option['disabled'] ) && $option['disabled'] ) or ( isset( $option['need_pro'] ) && $option['need_pro'] ) ) ? ' disabled="disabled"' : '',
						esc_attr( implode( ' ', $classes ) ),
						isset( $option['label'] ) ? $option['label'] : ''
					);
					$content                   .= apply_filters( $filter_name, $checkbox );
					break;
				case 'checkbox_group':
					$option_value = $this->get_option( $option_name, $option_group );
					if ( empty( $option_value ) && isset( $option['defaults'] ) ) {
						foreach ( $option['defaults'] as $default ) {
							$option_value[ $default ] = $default;
						}
					}
					$content .= '<ul>';
					$i        = 0;
					if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
						$option['options'] = array_merge( $option['options'], $option['extra_options']() );
					}
					foreach ( $option['options'] as $value => $label ) {
						$checked = false;
						if ( is_array( $option_value ) && array_key_exists( $value, $option_value ) ) {
							$checked = true;
						}
						$id       = sprintf( '%s%d', $option_name, $i++ );
						$content .= sprintf(
							'<li><label for="%s"><input type="checkbox" name="%s[%s]" value="%s"%s id="%s"/> %s</label></li>',
							$id,
							$html_element_name,
							$value,
							$value,
							checked( $checked, true, false ),
							$id,
							$label
						);
					}
					$content .= '</ul>';
					break;
				case 'radio':
					$option_value = $this->get_option( $option_name, $option_group );
					$i            = 0;
					/**
				 * check user add "radio" or "options".
				 */
					$radio_options = array();
					if ( array_key_exists( 'options', $option ) ) {
						$radio_options = $option['options'];
					} elseif ( array_key_exists( 'radio', $option ) ) {
						$radio_options = $option['radio'];
					}
					if ( empty( $radio_options ) ) {
						$content .= sprintf(
							'<p>Error: no <strong>radio</strong> array key for option: <em>%s</em>.</p>',
							$option_name
						);
					} else {
						/**
					 * add extra options, maybe dynamic?
					 */
						$radio_options = apply_filters( $filter_name . '_data', $radio_options );
						$radio         = apply_filters( $filter_name . '_content', null, $radio_options, $html_element_name, $option_name, $option_value );
						if ( empty( $radio ) ) {
							foreach ( $radio_options as $value => $input ) {
								$id       = sprintf( '%s%d', $option_name, $i++ );
								$disabled = '';
								if ( preg_match( '/\-disabled$/', $value ) ) {
									$disabled = 'disabled="disabled"';
								} elseif ( isset( $input['disabled'] ) && $input['disabled'] ) {
									$disabled = 'disabled="disabled"';
								}
								$classes[] = sanitize_title( $value );
								$checked   = $option_value == $value or ( empty( $option_value ) and isset( $option['default'] ) and $value == $option['default'] );
								$radio    .= sprintf(
									'<li class="%s%s"><label for="%s"><input type="radio" name="%s" value="%s"%s id="%s" %s/> %s</label>',
									esc_attr( implode( ' ', $classes ) ),
									$disabled ? ' disabled' : '',
									esc_attr( $id ),
									esc_attr( $html_element_name ),
									esc_attr( $value ),
									checked( $checked, true, false ),
									esc_attr( $id ),
									$disabled,
									esc_html( $input['label'] )
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
								$radio = sprintf( '<ul>%s</ul>', $radio );
							}
						} else {
							$radio = apply_filters( $filter_name, $radio );
							if ( empty( $radio ) ) {
								$content .= sprintf(
									'<p>Error: no <strong>radio</strong> array key for option: <em>%s</em>.</p>',
									$option_name
								);
							}
						}
						$content .= apply_filters( $filter_name, $radio );
					}
					break;
				case 'select':
				case 'select2':
					$extra = $name_sufix = '';
					if ( 'select2' == $option['type'] ) {
						$classes[] = 'select2';
						if ( isset( $option['multiple'] ) && $option['multiple'] ) {
							$extra      = ' multiple="multiple"';
							$name_sufix = '[]';
						}
					}
					/**
					 * Sanitize options.
					 */
					if ( ! isset( $option['options'] ) || ! is_array( $option['options'] ) ) {
						$option['options'] = array();
					}
					$option_value = $this->get_option( $option_name, $option_group );
					if ( isset( $option['extra_options'] ) && is_callable( $option['extra_options'] ) ) {
						$option['options'] = array_merge( $option['options'], $option['extra_options']() );
					}
					$option['options'] = apply_filters( $filter_name . '_data', $option['options'], $option_name, $option_value );
					$select            = apply_filters( $filter_name . '_content', null, $option['options'], $html_element_name, $option_name, $option_value );
					$select            = apply_filters( 'iworks_options_' . $option_name . '_content', null, $option['options'], $html_element_name, $option_name, $option_value );
					if ( empty( $select ) ) {
						foreach ( $option['options'] as $key => $value ) {
							$disabled = '';
							if ( preg_match( '/\-disabled$/', $value ) ) {
								$disabled = 'disabled="disabled"';
							} elseif ( isset( $input['disabled'] ) && $input['disabled'] ) {
								$disabled = 'disabled="disabled"';
							}
							$selected = false;
							if ( is_array( $option_value ) ) {
								if ( empty( $option_value ) ) {
								} else {
									$selected = in_array( $key, $option_value );
								}
							} else {
								$selected = ( $option_value == $key or ( empty( $option_value ) and isset( $option['default'] ) and $key == $option['default'] ) );
							}
							$select .= sprintf(
								'<option %s value="%s" %s %s >%s</option>',
								$disabled ? 'class="disabled"' : '',
								$key,
								selected( $selected, true, false ),
								$disabled,
								$value
							);
						}
						if ( $select ) {
							$select = sprintf(
								'<select id="%s" name="%s%s" class="%s" %s>%s</select>',
								esc_attr( $html_element_name ),
								esc_attr( $html_element_name ),
								esc_attr( $name_sufix ),
								esc_attr( implode( ' ', $classes ) ),
								$extra,
								$select
							);
						}
					}
					$content .= apply_filters( $filter_name, $select );
					break;
				case 'textarea':
					$value    = $this->get_option( $option_name, $option_group );
					$value    = ( ! $value && isset( $option['default'] ) ) ? $option['default'] : $value;
					$args     = array(
						'rows'  => isset( $option['rows'] ) ? $option['rows'] : 3,
						'class' => isset( $option['classes'] ) ? implode( ' ', $option['classes'] ) : '',
					);
					$content .= $this->textarea( $html_element_name, $value, $args );
					break;
				case 'heading':
					if ( isset( $option['label'] ) && $option['label'] ) {
						$classes = array();
						if ( $this->get_option( 'last_used_tab' ) == $label_index ) {
							$classes[] = 'selected';
						}
						$content .= sprintf(
							'<h3 id="options-%s"%s>%s</h3>',
							sanitize_title_with_dashes( remove_accents( $option['label'] ) ),
							count( $classes ) ? ' class="' . implode( ' ', $classes ) . '"' : '',
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
					} elseif ( isset( $option['call_user_func'] ) && isset( $option['call_user_data'] ) && is_callable( $option['call_user_func'] ) ) {
						ob_start();
						call_user_func_array( $option['call_user_func'], $option['call_user_data'] );
						$content .= ob_get_contents();
						ob_end_clean();
					}
					break;
				case 'subheading':
					$content .= sprintf( '<h2 class="title">%s</h2>', $option['label'] );
					break;
				case 'wpColorPicker':
					if ( is_admin() ) {
						wp_enqueue_style( 'wp-color-picker' );
						wp_enqueue_script( 'wp-color-picker' );
					}
					$id = '';
					if ( isset( $option['use_name_as_id'] ) && $option['use_name_as_id'] ) {
						$id = sprintf( ' id="%s"', $html_element_name );
					}
					$content .= apply_filters(
						$filter_name,
						sprintf(
							'<input type="text" name="%s" value="%s" class="wpColorPicker %s"%s%s /> %s',
							$html_element_name,
							$this->get_option( $option_name, $option_group ),
							isset( $option['class'] ) && $option['class'] ? $option['class'] : '',
							$id,
							( isset( $option['need_pro'] ) and $option['need_pro'] ) ? ' disabled="disabled"' : '',
							isset( $option['label'] ) ? $option['label'] : '',
							$html_element_name
						)
					);
					break;
				case 'image':
					if ( is_admin() ) {
						wp_enqueue_media();
					}
					$src = $value    = $this->get_option( $option_name, $option_group );
					if ( preg_match( '/^\d+/', $value ) && 0 < intval( $value ) ) {
						$src = wp_get_attachment_url( $value );
					}
					$content .= sprintf(
						'<img id="%s_img" src="%s" alt="" style="%s%sclear:right;display:block;margin-bottom:10px;" />',
						$html_element_name,
						$src ? $src : '',
						array_key_exists( 'max-width', $option ) && is_integer( $option['max-width'] ) ? sprintf( 'max-width: %dpx;', $option['max-width'] ) : '',
						array_key_exists( 'max-height', $option ) && is_integer( $option['max-height'] ) ? sprintf( 'max-height: %dpx;', $option['max-height'] ) : ''
					);
					$content .= sprintf(
						'<input type="hidden" name="%s" value="%s" />',
						$html_element_name,
						$this->get_option( $option_name, $option_group ),
						$value
					);
					$content .= sprintf(
						' <input type="button" class="button iworks_upload_button" value="%s" rel="#%s" />',
						__( 'Select Image', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
						$html_element_name
					);
					$content .= sprintf(
						' <input type="button" class="button iworks_delete_button" value="%s" rel="#%s" %s/>',
						__( 'Delete image', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
						$html_element_name,
						empty( $value ) ? ' style="display:none"' : ''
					);
					break;
					/**
					 * handle `button` field type
					 *
					 * @since 2.6.9
					 */
				case 'button':
					if ( isset( $option['description'] ) && $option['description'] ) {
						printf( '<p class="description">%s</p>', $option['description'] );
					}
					$classes[] = 'button';
					$content  .= sprintf(
						'<input type="button" name="%s" value="%s" class="%s" data-nonce="%s" />',
						$html_element_name,
						$option['value'],
						implode( ' ', $classes ),
						wp_create_nonce( $html_element_name )
					);
					break;
				default:
					$content .= sprintf( 'not implemented type: %s', $option['type'] );
			}
			if ( 'hidden' !== $option['type'] ) {
				if ( isset( $option['description'] ) && $option['description'] ) {
					if ( isset( $option['label'] ) && $option['label'] && 'subheading' !== $option['type'] ) {
						$content .= '<br />';
					}
					$content .= sprintf( '<p class="description">%s</p>', $option['description'] );
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
		if ( $content ) {
			if ( isset( $options['label'] ) && $options['label'] && ! $use_tabs ) {
				$top .= sprintf( '<h3>%s</h3>', $options['label'] );
			}
			$top .= $hidden;
			if ( $use_tabs ) {
				if ( $options['add_table'] ) {
					$content .= '</tbody></table>';
				}
				$content .= '</fieldset>';
				$content  = $top . $content;
			} else {
				if ( $options['add_table'] ) {
					$top .= sprintf( '<table class="form-table%s" style="%s" role="presentation">', isset( $options['widefat'] ) ? ' widefat' : '', isset( $options['style'] ) ? $options['style'] : '' );
					if ( isset( $options['thead'] ) ) {
						$top .= sprintf( '<thead><tr class="%s">', implode( ' ', $tr_classes ) );
						foreach ( $options['thead'] as $text => $colspan ) {
							$top .= sprintf(
								'<th%s>%s</th>',
								$colspan > 1 ? ' colspan="' . $colspan . '"' : '',
								$text
							);
						}
						$top .= '</tr></thead>';
					}
					$top .= '<tbody>';
				}
				$content = $top . $content;
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
			$content .= get_submit_button( __( 'Save Changes', 'IWORKS_OPTIONS_TEXTDOMAIN' ), 'primary', 'submit_button' );
		}
		/**
		 * iworks-options wrapper
		 */
		$content = sprintf( '<div class="iworks-options">%s</div>', $content );
		/* print ? */
		if ( $echo ) {
			echo $content;
			return;
		}
		return $content;
	}

	private function register_setting( $options, $option_group ) {
		foreach ( $options as $option ) {
			/**
			 * don't register setting without type and name
			 */
			if ( ! is_array( $option ) || ! array_key_exists( 'type', $option ) || ! array_key_exists( 'name', $option ) ) {
				continue;
			}
			/**
			 * don't register certain type setting or with empty name
			 */
			if ( preg_match( '/^(sub)?heading$/', $option['type'] ) || empty( $option['name'] ) ) {
				continue;
			}
			/**
			 * register setting
			 */
			$args = array();
			if ( isset( $option['sanitize_callback'] ) ) {
				$args['sanitize_callback'] = $option['sanitize_callback'];
			}
			if ( isset( $option['description'] ) ) {
				$args['description'] = $option['description'];
			}
			if ( isset( $option['flush_rewrite_rules'] ) ) {
				$action = sprintf( 'update_option_%s%s', $this->option_prefix, $option['name'] );
				add_action( $action, array( $this, 'flush_rewrite_rules' ) );
			}
			/**
			 * register
			 */
			register_setting(
				$this->option_prefix . $option_group,
				$this->option_prefix . $option['name'],
				$args
			);
		}
	}

	public function options_init() {
		$options = $this->get_option_array();
		/**
		 * register_setting last_used_tab field
		 */
		if ( isset( $options['use_tabs'] ) && $options['use_tabs'] ) {
			register_setting(
				$this->option_prefix . 'index',
				$this->option_prefix . 'last_used_tab'
			);
		}
		/**
		 * filter it
		 */
		$options = apply_filters( $this->option_function_name, $options );
		/**
		 * register_setting
		 */
		foreach ( $options as $key => $data ) {
			if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
				$this->register_setting( $data['options'], $key );
			} elseif ( 'options' == $key ) {
				$key = $this->mode;
				if ( ! empty( $this->option_group ) ) {
					$key = $this->option_group;
				}
				$this->register_setting( $data, $key );
			}
		}
	}

	public function get_values( $option_name, $option_group = 'index' ) {
		$this->option_group = $option_group;
		$data               = $this->get_option_array();
		$data               = $data['options'];
		foreach ( $data as $one ) {
			if ( isset( $one['name'] ) && $one['name'] != $option_name ) {
				continue;
			}
			switch ( $one['type'] ) {
				case 'checkbox_group':
					return $one['options'];
				case 'radio':
					return $one['radio'];
			}
		}
		return;
	}

	public function get_default_value( $option_name, $option_group = 'index' ) {
		$this->option_group = $option_group;
		$options            = $this->get_option_array();
		/**
		 * check options exists?
		 */
		if ( ! array_key_exists( 'options', $options ) or ! is_array( $options['options'] ) ) {
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
			$re                  = sprintf( '/^%s_\d+_/', $option_group );
			$default_option_name = preg_replace( $re, '', $default_option_name );
		}
		foreach ( $options['options'] as $option ) {
			if ( isset( $option['name'] ) && $option['name'] == $default_option_name ) {
				return isset( $option['default'] ) ? $option['default'] : null;
			}
		}
		return null;
	}

	public function activate() {
		$options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
		foreach ( $options as $key => $data ) {
			foreach ( $data['options'] as $option ) {
				if (
					( isset( $option['type'] ) && $option['type'] == 'heading' )
					or ! isset( $option['name'] )
					or ! $option['name'] or ! isset( $option['default'] )
				) {
					continue;
				}
				add_option( $this->option_prefix . $option['name'], $option['default'], '', isset( $option['autoload'] ) ? $option['autoload'] : 'yes' );
			}
		}
		add_option( $this->option_prefix . 'cache_stamp', date( 'c' ) );
	}

	public function deactivate() {
		$options = apply_filters( $this->option_function_name, call_user_func( $this->option_function_name ) );
		foreach ( $options as $key => $data ) {
			foreach ( $data['options'] as $option ) {
				if (
					( isset( $option['type'] ) && 'heading' == $option['type'] )
					or ! isset( $option['name'] )
					or ! $option['name']
				) {
					continue;
				}
				/**
				 * prevent special options
				 */
				if ( isset( $option['dont_deactivate'] ) && $option['dont_deactivate'] ) {
					continue;
				}
				delete_option( $this->option_prefix . $option['name'] );
			}
		}
		delete_option( $this->option_prefix . 'cache_stamp' );
		delete_option( $this->option_prefix . 'version' );
		delete_option( $this->option_prefix . 'flush_rules' );
	}

	public function settings_fields( $option_name, $use_prefix = true ) {
		if ( $use_prefix ) {
			settings_fields( $this->option_prefix . $option_name );
		} else {
			settings_fields( $option_name );
		}
	}

	/**
	 * admin_notices
	 */
	public function admin_notices() {
		if ( empty( $this->notices ) ) {
			return;
		}
		foreach ( $this->notices as $notice ) {
			printf( '<div class="error"><p>%s</p></div>', $notice );
		}
	}

	/**
	 * options: add, get, update
	 */
	public function add_option( $option_name, $option_value, $autoload = true ) {
		$autoload = $autoload ? 'yes' : 'no';
		add_option( $this->option_prefix . $option_name, $option_value, null, $autoload );
	}

	public function get_option( $option_name, $option_group = 'index', $default_value = null, $forece_default = false ) {
		$option_value  = get_option( $this->option_prefix . $option_name, null );
		$default_value = $this->get_default_value( $option_name, $option_group );
		if ( ( $default_value || $forece_default ) && is_null( $option_value ) ) {
			$option_value = $default_value;
		}
		return $option_value;
	}

	public function get_all_options() {
		$data    = array();
		$options = $this->get_option_array();
		foreach ( $options['options'] as $option ) {
			if ( ! array_key_exists( 'name', $option ) || ! $option['name'] ) {
				continue;
			}
			$value = $this->get_option( $option['name'] );
			if ( array_key_exists( 'sanitize_callback', $option ) && is_callable( $option['sanitize_callback'] ) ) {
				$value = call_user_func( $option['sanitize_callback'], $value );
			}
			$data[ $option['name'] ] = $value;
		}
		return $data;
	}

	/**
	 * Get option name by adding prefix.
	 *
	 * @since 1.0
	 * @since 2.6.4 Added `hidden` argument.
	 *
	 * @param string  $name Name of option.
	 * @param boolean $hidden If this hidden value.
	 *
	 * @return string $option_name
	 */
	public function get_option_name( $name, $hidden = false ) {
		$option_name = sprintf( '%s%s', $this->option_prefix, $name );
		if ( $hidden ) {
			$option_name = sprintf( '_%s', $option_name );
		}
		return $option_name;
	}

	public function update_option( $option_name, $option_value ) {
		/**
		 * delete if option have a default value
		 */
		$default_value = $this->get_default_value( $this->option_prefix . $option_name );
		if ( $option_value === $default_value ) {
			delete_option( $this->option_prefix . $option_name );
			return;
		}
		update_option( $this->option_prefix . $option_name, $option_value );
	}

	/**
	 * update taxonomy options
	 */
	public function update_taxonomy_options( $option_group, $term_id ) {
		$this->option_group = $option_group;
		$options            = $this->get_option_array();
		/**
		 * only for taxonomies
		 */
		if ( ! array_key_exists( 'type', $options ) ) {
			return;
		}
		if ( 'taxonomy' != $options['type'] ) {
			return;
		}
		foreach ( $options['options'] as $option ) {
			if ( ! array_key_exists( 'name', $option ) || ! $option['name'] ) {
				continue;
			}
			$option_name = sprintf(
				'%s_%s_%s',
				$option_group,
				$term_id,
				$option['name']
			);
			$value       = array_key_exists( $this->get_option_name( $option_name ), $_POST ) ? $_POST[ $this->get_option_name( $option_name ) ] : false;
			if ( array_key_exists( 'sanitize_callback', $option ) && is_callable( $option['sanitize_callback'] ) ) {
				$value = call_user_func( $option['sanitize_callback'], $value );
			}
			if ( $value ) {
				$this->update_option( $option_name, $value );
			} else {
				delete_option( $option_name );
			}
		}
	}

	/**
	 * helpers
	 */
	public function select_page_helper( $name, $show_option_none = false, $post_type = 'page' ) {
		$args = array(
			'echo'             => false,
			'name'             => $this->get_option_name( $name ),
			'selected'         => $this->get_option( $name ),
			'show_option_none' => $show_option_none,
			'post_type'        => $post_type,
		);
		return wp_dropdown_pages( $args );
	}

	public function select_category_helper( $name, $hide_empty = null, $show_option_none = false ) {
		$args = array(
			'echo'         => false,
			'name'         => $this->get_option_name( $name ),
			'selected'     => $this->get_option( $name ),
			'hierarchical' => true,
			'hide_empty'   => $hide_empty,
		);
		if ( $show_option_none ) {
			$args['show_option_none'] = true;
		}
		return wp_dropdown_categories( $args );
	}

	public function get_option_group() {
		return $this->option_group;
	}

	private function get_option_index_from_screen() {
		$screen = get_current_screen();
		$key    = explode( $this->option_prefix, $screen->id );
		if ( 2 != count( $key ) ) {
			return false;
		}
		return $key[1];
	}

	public function show_page( $check_option_name = true, $url = 'options.php' ) {
		$options     = array();
		$option_name = 'index';
		if ( $check_option_name ) {
			$option_name = $this->get_option_index_from_screen();
			if ( ! $option_name ) {
				return;
			}
			$options = $this->options[ $option_name ];
		} else {
			$options = $this->get_option_array();
		}
		global $screen_layout_columns;
		$data = array();
		?>
<div class="wrap iworks_options">
	<h1><?php echo $options['page_title']; ?></h1>
	<form method="post" action="<?php echo esc_url( $url ); ?>" id="<?php echo esc_attr( $this->get_option_name( 'admin_index' ) ); ?>">
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<input type="hidden" name="action" value="save_howto_metaboxes_general" />
		<div class="metabox-holder<?php echo empty( $screen_layout_columns ) || 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
		<?php
		/**
		 * check metaboxes for key
		 */
		if ( array_key_exists( 'metaboxes', $this->options[ $option_name ] ) ) {
			?>
	<div id="side-info-column" class="inner-sidebar">
			<?php do_meta_boxes( $this->pagehooks[ $option_name ], 'side', $this ); ?>
	</div>
<?php } ?>
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
		<?php
		/**
		 * check metaboxes for key
		 */
		if ( array_key_exists( 'metaboxes', $this->options[ $option_name ] ) ) {
			include_once ABSPATH . '/wp-admin/includes/meta-boxes.php';
			?>
<script type="text/javascript" id="<?php echo __CLASS__; ?>">
//<![CDATA[
jQuery(document).ready( function($) {
// close postboxes that should be closed
$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
// postboxes setup
postboxes.add_postbox_toggles('<?php echo $this->pagehooks[ $option_name ]; ?>');
});
//]]>
</script>
			<?php
		}
	}

	public function load_page() {
		$option_name = $this->get_option_index_from_screen();
		if ( ! $option_name ) {
			return;
		}
		/**
		 * check options for key
		 */
		if ( ! array_key_exists( $option_name, $this->options ) ) {
			return;
		}
		/**
		 * check metaboxes for key
		 */
		if ( ! array_key_exists( 'metaboxes', $this->options[ $option_name ] ) ) {
			return;
		}
		if ( ! count( $this->options[ $option_name ]['metaboxes'] ) ) {
			return;
		}
		/**
		 * ensure, that the needed javascripts been loaded to allow drag/drop,
		 * expand/collapse and hide/show of boxes
		 */
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		foreach ( $this->options[ $option_name ]['metaboxes'] as $id => $data ) {
			add_meta_box(
				$id,
				$data['title'],
				$data['callback'],
				$this->pagehooks[ $option_name ],
				$data['context'],
				$data['priority']
			);
		}
		/**
		 * wp_enqueue_script
		 */
		if ( array_key_exists( 'enqueue_scripts', $this->options[ $option_name ] ) ) {
			$scripts = array();
			if ( is_admin() && isset( $this->options[ $option_name ]['enqueue_scripts']['admin'] ) ) {
				$scripts = $this->options[ $option_name ]['enqueue_scripts']['admin'];
			} elseif ( ! is_admin() && isset( $this->options[ $option_name ]['enqueue_scripts']['frontend'] ) ) {
				$scripts = $this->options[ $option_name ]['enqueue_scripts']['frontend'];
			} else {
				$scripts = $this->options[ $option_name ]['enqueue_scripts'];
			}
			foreach ( $scripts as $script ) {
				wp_enqueue_script( $script );
			}
		}
		/**
		 * wp_enqueue_style
		 */
		if ( array_key_exists( 'enqueue_styles', $this->options[ $option_name ] ) ) {
			$styles = array();
			if ( is_admin() && isset( $this->options[ $option_name ]['enqueue_styles']['admin'] ) ) {
				$styles = $this->options[ $option_name ]['enqueue_styles']['admin'];
			} elseif ( ! is_admin() && isset( $this->options[ $option_name ]['enqueue_styles']['frontend'] ) ) {
				$styles = $this->options[ $option_name ]['enqueue_styles']['frontend'];
			} else {
				$styles = $this->options[ $option_name ]['enqueue_styles'];
			}
			foreach ( $styles as $style ) {
				wp_enqueue_style( $style );
			}
		}
	}

	public function screen_layout_columns( $columns, $screen ) {
		foreach ( $this->pagehooks as $option_name => $pagehook ) {
			if ( $screen == $pagehook ) {
				$columns[ $pagehook ] = 2;
			}
		}
		return $columns;
	}

	public function get_options_by_group( $group ) {
		$opts    = array();
		$options = $this->get_option_array();
		if ( ! isset( $options['options'] ) || empty( $options['options'] ) ) {
			return $options;
		}
		foreach ( $options['options'] as $one ) {
			if ( ! isset( $one['name'] ) || ! isset( $one['type'] ) ) {
				continue;
			}
			if ( ! isset( $one['group'] ) || $group != $one['group'] ) {
				continue;
			}
			$opts[] = $one;
		}
		return $opts;
	}

	/**
	 * input types
	 */
	public function get_field_by_type( $type, $name, $value = '', $args = array() ) {
		if ( method_exists( $this, $type ) ) {
			wp_enqueue_style( __CLASS__ );
			if ( ! isset( $args['class'] ) ) {
				switch ( $type ) {
					case 'switch_button':
						wp_enqueue_script( __CLASS__ );
						wp_enqueue_style( 'switch_button' );
						break;
					case 'checkbox':
					case 'radio':
						break;
					default:
						$args['class'] = array( 'large-text' );
						break;
				}
			}
			$args['class'][] = sprintf( 'iworks-options-%s', preg_replace( '/_/', '-', esc_attr( $type ) ) );
			return $this->$type( $name, $value, $args );
		}
		return sprintf( 'wrong type: %s', esc_html( $type ) );
	}

	private function build_field_attributes( $args ) {
		$atts = '';
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', $value );
			}
			$atts .= sprintf( ' %s="%s"', esc_html( $key ), esc_attr( trim( $value ) ) );
		}
		return $atts;
	}

	/**
	 * Print HTML select
	 */
	private function select( $name, $value = '', $args = array(), $type = 'text' ) {
		/**
		 * default value
		 */
		if ( isset( $args['default'] ) ) {
			if ( empty( $value ) ) {
				$value = $args['default'];
			}
			unset( $args['default'] );
		}
		/**
		 * options
		 */
		$options = array();
		if ( isset( $args['options'] ) ) {
			$options = $args['options'];
			unset( $args['options'] );
		}
		if ( empty( $options ) && ! empty( $value ) ) {
			$options[ $value['value'] ] = $value['label'];
		}
		$value_to_check = is_array( $value ) && isset( $value['value'] ) ? $value['value'] : $value;
		$content        = sprintf(
			'<select type="%s" name="%s" %s >',
			esc_attr( $type ),
			esc_attr( $name ),
			$this->build_field_attributes( $args )
		);
		foreach ( $options as $val => $label ) {
			$content .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $val ),
				selected( $val, $value_to_check, false ),
				esc_html( $label )
			);
		}
		$content .= '</select>';
		return $content;
	}

	private function input( $name, $value = '', $args = array(), $type = 'text' ) {
		/**
		 * default value
		 */
		if ( isset( $args['default'] ) ) {
			if ( empty( $value ) ) {
				$value = $args['default'];
			}
			unset( $args['default'] );
		}
		/**
		 * turn off autocomplete
		 */
		if ( 'text' == $type ) {
			if ( ! isset( $args['autocomplete'] ) ) {
				$args['autocomplete'] = 'off';
			}
		}
		/**
		 * before & after
		 */
		$keys = array( 'before', 'after' );
		foreach ( $keys as $key ) {
			$$key = '';
			if ( isset( $args[ $key ] ) ) {
				$$key = $args[ $key ];
				unset( $args[ $key ] );
			}
		}
		/**
		 * produce
		 */
		return sprintf(
			'%s<input type="%s" name="%s" value="%s" %s />%s',
			$before,
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			$this->build_field_attributes( $args ),
			$after
		);
	}

	/**
	 * Checkbox HTML element
	 *
	 * @since 2.6.4
	 */
	private function checkbox( $name, $value = '', $args = array() ) {
		if ( ! empty( $value ) ) {
			$args['checked'] = 'checked';
		}
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	/**
	 * Switch button element (based on checkbox field).
	 *
	 * @since 2.6.4
	 */
	private function switch_button( $name, $value = '', $args = array() ) {
		return $this->checkbox( $name, $value, $args );
	}

	private function text( $name, $value = '', $args = array() ) {
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	private function number( $name, $value = '', $args = array() ) {
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	private function button( $name, $value = '', $args = array() ) {
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	private function submit( $name, $value = '', $args = array() ) {
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	private function hidden( $name, $value = '', $args = array() ) {
		return $this->input( $name, $value, $args, __FUNCTION__ );
	}

	private function date( $name, $value = '', $args = array() ) {
		if ( ! isset( $args['class'] ) ) {
			$args['class'] = array();
		}
		$args['class'][] = 'datepicker';
		return $this->input( $name, $value, $args );
	}

	private function select2( $name, $value = '', $args = array() ) {
		if ( isset( $args['data-nonce-action'] ) ) {
			$args['data-nonce'] = wp_create_nonce( $args['data-nonce-action'] );
			unset( $args['data-nonce-action'] );
		}
		if ( ! isset( $args['class'] ) ) {
			$args['class'] = array();
		}
		$args['class'][] = 'select2';
		return $this->select( $name, $value, $args );
	}

	private function textarea( $name, $value = '', $args = array() ) {
		if ( ! isset( $args['rows'] ) ) {
			$args['rows'] = 3;
		}
		return sprintf(
			'<textarea name="%s" %s>%s</textarea>',
			esc_attr( $name ),
			$this->build_field_attributes( $args ),
			$value
		);
	}

	private function radio( $name, $value = '', $args = array() ) {
		$radio   = '';
		$options = $args['options'];
		unset( $args['options'] );
		/**
		 * default value
		 */
		if ( isset( $args['default'] ) && '' == $value ) {
			$value = $args['default'];
		}
		$i = 0;
		foreach ( $options as $option_value => $input ) {
			$id     = sprintf( '%s%d', $name, $i++ );
			$radio .= sprintf(
				'<li class="%s"><label for="%s"><input type="radio" name="%s" value="%s"%s id="%s"/> %s</label>',
				esc_attr( sanitize_title( $value ) ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $option_value ),
				checked( $option_value, $value, false ),
				esc_attr( $id ),
				esc_html( is_string( $input ) ? $input : $input['label'] )
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
			$radio = sprintf( '<ul>%s</ul>', $radio );
		}
		return $radio;
	}

	private function description( $name, $value = '', $args = array() ) {
		if ( ! isset( $args['value'] ) || empty( $args['value'] ) ) {
			return '';
		}
		return sprintf( '<p class="description">%s</p>', $args['value'] );
	}

	private function money( $name, $value = '', $args = array() ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			$value = array();
		}
		$value   = wp_parse_args(
			$value,
			array(
				'integer'    => 0,
				'fractional' => 0,
				'currency'   => false,
			)
		);
		$args    = wp_parse_args(
			$args,
			array(
				'kind'             => 'complex',
				'currency'         => false,
				'currency_default' => false,
			)
		);
		$content = '';
		/**
		 * Integer
		 */
		$n        = sprintf( '%s[integer]', $name );
		$content .= $this->input( $n, $value['integer'], array( 'min' => 0 ), 'number' );
		if ( 'complex' === $args['kind'] ) {
			/**
			 * fractional
			 */
			$n        = sprintf( '%s[fractional]', $name );
			$content .= $this->input(
				$n,
				$value['fractional'],
				array(
					'min' => 0,
					'max' => 99,
				),
				'number'
			);
		}
		if ( is_array( $args['currency'] ) && ! empty( $args['currency'] ) ) {
			$n        = sprintf( '%s[currency]', $name );
			$atts     = array(
				'default' => $args['currency_default'],
				'options' => $args['currency'],
			);
			$content .= $this->select( $n, $value['currency'], $atts );
		}
		return $content;
	}

	private function location( $name, $value = '', $args = array() ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			$value = array();
		}
		$defaults = array(
			'country' => '',
			'city'    => '',
			'street'  => '',
			'zip'     => '',
		);
		$i18n     = array(
			'country' => __( 'Country', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
			'city'    => __( 'City', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
			'street'  => __( 'Street', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
			'zip'     => __( 'ZIP code', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
		);
		$value    = wp_parse_args( $value, $defaults );
		/**
		 * Content
		 */
		$content = '';
		foreach ( array_keys( $defaults ) as $key ) {
			$content .= sprintf( '<div class="iworks-options-%s">', esc_attr( $key ) );
			$content .= '<label>';
			$content .= $i18n[ $key ];
			$content .= '<br />';
			$content .= $this->input(
				sprintf( '%s[%s]', $name, esc_attr( $key ) ),
				$value[ $key ],
				array(
					'class' => 'large-text iworks-options-text',
				)
			);
			$content .= '</div>';
		}
		return $content;
	}

	public function admin_head() {
		if ( false === $this->check_hooks_to_load_asses() ) {
			return;
		}
		$files = $this->get_files();
		foreach ( $files as $data ) {
			if ( $data['style'] ) {
				wp_enqueue_style( $data['handle'] );
			} else {
				wp_enqueue_script( $data['handle'] );
			}
		}
	}

	/**
	 * Convert color to rgb
	 *
	 * @since 2.4.1
	 *
	 * @param string $hex Hex value of color
	 * @return array RGB array.
	 */
	public function hex2rgb( $hex ) {
		$hex = str_replace( '#', '', $hex );
		if ( strlen( $hex ) == 3 ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} else {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		}
		$rgb = array( $r, $g, $b );
		return $rgb; // returns an array with the rgb values
	}

	public function register_styles() {
		if ( false === $this->check_hooks_to_load_asses() ) {
			return;
		}
		$files = $this->get_files();
		foreach ( $files as $data ) {
			$file = sprintf( 'assets/%s/%s', $data['style'] ? 'styles' : 'scripts', $data['file'] );
			if ( 'theme' == $this->mode ) {
				$url  = str_replace( get_template_directory(), '', dirname( __FILE__ ) );
				$file = get_template_directory_uri() . $url . '/' . $file;
			} else {
				$file = plugins_url( $file, __FILE__ );
			}
			$version   = isset( $data['version'] ) ? $data['version'] : $this->version;
			$deps      = isset( $data['deps'] ) ? $data['deps'] : array();
			$in_footer = isset( $data['in_footer'] ) ? $data['in_footer'] : true;
			if ( $data['style'] ) {
				wp_register_style( $data['handle'], $file, $deps, $version );
			} else {
				wp_register_script( $data['handle'], $file, $deps, $version, $in_footer );
				if ( isset( $data['wp_localize_script'] ) ) {
					wp_localize_script( $data['handle'], $data['handle'], $data['wp_localize_script'] );
				}
			}
		}
	}

	public function get_files() {
		$f = array(
			/**
			 * iworks_options core files
			 */
			array(
				'handle' => __CLASS__,
				'file'   => 'jquery-ui.min.css',
			),
			array(
				'handle'             => __CLASS__,
				'file'               => 'common.js',
				'deps'               => array( 'jquery', 'switch_button', 'jquery-ui-tabs' ),
				'wp_localize_script' => array(
					'buttons' => array(
						'select_media' => __( 'Select Image', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
					),
				),
			),
			/**
			 * switch checkbox
			 */
			array(
				'handle'  => 'switch_button',
				'file'    => 'jquery.switch_button.css',
				'version' => '1.0',
			),
			array(
				'handle'             => 'switch_button',
				'file'               => 'jquery.switch_button.js',
				'version'            => '1.0',
				'deps'               => array( 'jquery', 'jquery-effects-core', 'jquery-ui-widget' ),
				'wp_localize_script' => $this->get_switch_button_data(),
			),
			/**
			 * select2
			 */
			array(
				'handle'  => 'select2',
				'file'    => 'select2.min.css',
				'version' => '4.0.3',
			),
			array(
				'handle'  => 'select2',
				'file'    => 'select2.min.js',
				'version' => '4.0.3',
				'deps'    => array( 'jquery' ),
			),
			/**
			 * options
			 */
			array(
				'handle'  => 'iworks-options',
				'file'    => 'options-admin.css',
				'version' => $this->version,
			),
		);
		$files = array();
		foreach ( $f as $data ) {
			$data['style'] = preg_match( '/css$/', $data['file'] );
			$files[]       = $data;
		}
		return $files;
	}

	public function get_switch_button_data() {
		$data = array(
			'labels' => array(
				'off_label' => __( 'OFF', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
				'on_label'  => __( 'ON', 'IWORKS_OPTIONS_TEXTDOMAIN' ),
			),
		);
		return $data;
	}

	/**
	 * Get option page
	 *
	 * @since 2.6.0
	 */
	public function get_pagehook() {
		return $this->option_prefix . $this->option_group;
	}

	/**
	 * Flush rewrite roles when it is configured
	 *
	 * @since 2.6.7
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	/**
	 * check to register or load assets
	 *
	 * check to register or load assets to avoid loading when it is not needed
	 *
	 * @since 2.8.0
	 */
	private function check_hooks_to_load_asses() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! is_object( $screen ) ) {
			return false;
		}
		return in_array( $screen->id, $this->pagehooks );
	}

	/**
	 * Set plugin value
	 *
	 * @since 2.7.3
	 *
	 * @param string $plugin  Plugin file.
	 */
	public function set_plugin( $plugin ) {
		$this->plugin = $plugin;
	}
}
