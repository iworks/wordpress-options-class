<?php

/**
 * Logger class for iworks Options.
 *
 * This class handles logging functionality for the iworks Options plugin,
 * integrating with Simple History plugin when available.
 *
 * @package Iworks\Options
 * @since   3.1.0
 */
class iworks_options_logger {

    /**
     * Whether Simple History plugin is active.
     *
     * @since 3.1.0
     * @var bool
     */
    private bool $simple_history_is_active = false;

    /**
     * Registered options to log.
     *
     * @since 3.1.0
     * @var object
     */
    private object $options;

    /**
     * Registered loggers.
     *
     * @since 3.1.0
     * @var array
     */
    private array $loggers = array();

    /**
     * Constructor.
     *
     * Sets up the logger by registering necessary WordPress hooks.
     *
     * @since 3.1.0
     * @param object $options Options object.
     */
    public function __construct( $options ) {
        $this->options = $options;
        $this->simple_history_is_active = $this->is_simple_history_active();
        /**
         * WordPress hooks
         */
        add_action( 'register_setting', array( $this, 'register_setting' ), 10, 3 );

        /**
         * Custom hooks
         */
        add_action( 'updated_option', array( $this, 'maybe_log_option' ), 10, 3 );
    }

    /**
     * Register a setting for logging.
     *
     * Registers a WordPress setting to be logged when updated.
     *
     * @since 3.1.0
     * @param string $option_group The option group.
     * @param string $option_name  The option name.
     * @param array  $args          Option arguments.
     * @return void
     */
    public function register_setting( $option_group, $option_name, $args ) {
        if ( ! isset( $args['logger'] ) || empty( $args['logger'] ) ) {
            return;
        }
        $this->loggers[ $option_name ] = $args['logger'];
    }
    
    /**
     * Maybe log an option update.
     *
     * Logs an option update if the option is registered for logging and the value changed.
     *
     * @since 3.1.0
     * @param string $option_name The option name.
     * @param mixed  $old_value   The old option value.
     * @param mixed  $new_value   The new option value.
     * @return void
     */
    public function maybe_log_option( $option_name, $old_value, $new_value ) {
        if ( ! isset( $this->loggers[ $option_name ] ) ) {
            return;
        }
        if ( $old_value === $new_value ) {
            return;
        }
        $this->simple_history_logger_helper(
            __( 'Option "{option_name}" has been updated. From "{old_value}" to "{new_value}".', 'opi-security-boost' ),
            array(
                'option_name' => $option_name,
                'old_value'   => $old_value,
                'new_value'   => $new_value,
            )
        );
    }
    
    /**
     * Check if Simple History plugin is active.
     *
     * @since 3.1.0
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
     * @since 3.1.0
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
     * @since 3.1.0
     * @param string $message Log message.
     * @param array  $data    Additional log data. Default empty array.
     * @param string $level   Log level. Default 'notice'.
     * @return void
     */
    private function simple_history_logger_helper( $message, $data = array(), $level = 'notice' ) {
        if ( ! $this->simple_history_is_active ) {
            return;
        }
        // Add occasions ID & _initiator to log data.
        if ( method_exists( $this->options, 'get_option_function_name' ) ) {
            $data = wp_parse_args(
                $data,
                array(
                    '_occasionsID' => $this->options->get_option_function_name(),
                )
            );
        }
        // Add mode dependent data.
        if ( method_exists( $this->options, 'get_mode' ) ) {
            switch ( $this->options->get_mode() ) {
                case 'theme':
                    $data = wp_parse_args(
                        $data,
                        array(
                            'theme_name' => 'THEME_NAME',
                            'theme_version' => 'THEME_VERSION',
                            'theme_url' => 'THEME_URL',
                        )
                    );
                    break;
                case 'plugin':
                    $data = wp_parse_args(
                        $data,
                        array(
                            'plugin_name' => 'PLUGIN_NAME',
                            'plugin_version' => 'PLUGIN_VERSION',
                            'plugin_url' => 'PLUGIN_URL',
                            'plugin_author' => sprintf( '<a href="%s">%s</a>', esc_url( 'AUTHOR_URL' ), esc_html( 'AUTHOR_NAME' ) ),
                        )
                    );
                    break;
            }
        }
        // Add logged in user data to log.
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

        // Select level and write log.
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