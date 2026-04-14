<?php

/**
 * Logger class for iworks Options.
 *
 * This class handles logging functionality for the iworks Options plugin,
 * integrating with Simple History plugin when available.
 *
 * @package Iworks\Options
 * @since   1.3.0
 */
class iworks_options_logger {

    /**
     * Whether Simple History plugin is active.
     *
     * @since 1.3.0
     * @var bool
     */
    private bool $simple_history_is_active = false;

    /**
     * Registered options to log.
     *
     * @since 1.3.0
     * @var array
     */
    private array $options = array();

    /**
     * Constructor.
     *
     * Sets up the logger by registering necessary WordPress hooks.
     *
     * @since 1.3.0
     */
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
        add_action( 'iworks_options_log', array( $this, 'log' ) );
        add_action( 'iworks_options_register_option_to_log', array( $this, 'register_option_to_log' ) );
    }

    /**
     * Register an option to be logged.
     *
     * @since 1.3.0
     * @param string $option_name The option name to register.
     * @param string $plugin      The plugin name associated with the option.
     * @return void
     */
    public function register_option_to_log( $option_name, $plugin ) {
        $this->options[$option_name] = $plugin;
    }
    
    /**
     * Log a message.
     *
     * Logs a message using Simple History if the plugin is active.
     *
     * @since 1.3.0
     * @param string $message The log message.
     * @param array  $data    Additional data to log. Default empty array.
     * @param string $level   Log level: 'debug', 'warning', 'notice', or 'info'. Default 'notice'.
     * @return void
     */
    public function log( $message, $data = array(), $level = 'notice' ) {
        if ( ! $this->simple_history_is_active ) {
            return;
        }
        $this->simple_history_logger_helper( $message, $data, $level );
    }

    /**
     * Action handler for plugins_loaded.
     *
     * Checks if Simple History plugin is active when all plugins are loaded.
     *
     * @since 1.3.0
     * @return void
     */
    public function action_plugins_loaded() {
        $this->simple_history_is_active = $this->is_simple_history_active();
    }

    /**
     * Check if Simple History plugin is active.
     *
     * @since 1.3.0
     * @return bool True if Simple History is active, false otherwise.
     */
    private function is_simple_history_active() {
        return function_exists( 'SimpleLogger' );
    }

    /**
     * Add current user data to log data.
     *
     * Adds current user information to the log data array if a user is logged in.
     *
     * @since 1.3.0
     * @param array $data Existing log data. Default empty array.
     * @return array Log data with user information added.
     */
    public function add_current_user_to_log_data( $data = array() ) {
        if ( ! is_user_logged_in() ) {
            return $data;
        }
        $user = wp_get_current_user();
        return wp_parse_args(
            $data,
            array(
                'username'    => $user->display_name ?? $user->user_login,
                '_user_id'    => get_current_user_id(),
                '_user_login' => $user->user_login,
                '_user_email' => $user->user_email,
            )
        );
    }
    
    /**
     * Log message using Simple Logger.
     *
     * Logs a message using the Simple Logger plugin if available,
     * including current user information.
     *
     * Read more: https://simple-history.com/docs/logging-api/#using-simpleLogger
     *
     * @since 1.3.0
     * @param string $message Log message.
     * @param array  $data    Additional log data. Default empty array.
     * @param string $level   Log level. Default 'notice'.
     * @return void
     */
    private function simple_history_logger_helper( $message, $data = array(), $level = 'notice' ) {
        if ( ! $this->simple_history_is_active ) {
            return;
        }

        // Add logged in user data to log
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $data = wp_parse_args(
                $data,
                array(
                    'username'    => $user->display_name ?? $user->user_login,
                    '_user_id'    => get_current_user_id(),
                    '_user_login' => $user->user_login,
                    '_user_email' => $user->user_email,
                )
            );
        }

        // Select level and write log
        switch ( $level ) {
            case 'debug':
                SimpleLogger()->debug( $message, $data );
                break;
            case 'warning':
                SimpleLogger()->warning( $message, $data );
                break;
            case 'notice':
                SimpleLogger()->notice( $message, $data );
                break;
            default:
                SimpleLogger()->info( $message, $data );
                break;
        }
    }
}