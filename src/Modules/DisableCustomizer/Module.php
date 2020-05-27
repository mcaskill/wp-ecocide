<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableCustomizer;

/**
 * Disable WordPress Customizer
 *
 * @link       https://github.com/parallelus/customizer-remove-all-parts
 * @version    1.0.3-alpha parallelus/customizer-remove-all-parts
 * @copyright  Parallelus, Andy Wilkerson, Jesse Petersen
 * @license    https://github.com/parallelus/customizer-remove-all-parts/blob/master/LICENSE GPLv2
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_customizer/';

    /**
     * A reference to an instance of this class.
     *
     * @var static
     */
    private static $instance;

    /**
     * Boots the module.
     *
     * @access public
     * @param  array $args {
     *     An array of optional arguments to customize the module.
     *
     *     @type array $hooks TODO: Define customizable hooks.
     * }
     * @return void
     */
    public function boot( array $args = [] ) : void
    {
        add_action( 'admin_init', [ $this, 'admin_init' ], 10 );

        add_action( 'init', [ $this, 'init' ], 10 );
    }

    /**
     * Remove customize capability
     *
     * @listens WP#action:init
     *
     * @return void
     */
    public function init() : void
    {
        // Remove customize capability
        add_filter( 'map_meta_cap', [ $this, 'filter_to_remove_customize_capability' ], 10, 2 );
    }

    /**
     * Run all of our plugin stuff on admin init.
     *
     * @listens WP#action:admin_init
     *
     * @return void
     */
    public function admin_init() : void
    {
        // Drop some customizer actions
        remove_action( 'plugins_loaded', '_wp_customize_include', 10);
        remove_action( 'admin_enqueue_scripts', '_wp_customize_loader_settings', 11);

        // Manually overrid Customizer behaviors
        add_action( 'load-customize.php', [ $this, 'override_load_customizer_action' ] );
    }

    /**
     * Remove customize capability
     *
     * This needs to be in public so the admin bar link for 'customize' is hidden.
     *
     * @listens WP#filter:map_meta_cap
     *
     * @param  array  $caps Returns the user's actual capabilities.
     * @param  string $cap  Capability name.
     * @return array
     */
    public function filter_to_remove_customize_capability( array $caps = [], $cap = '' ) : array
    {
        if ( $cap === 'customize' ) {
            return [ 'nope' ]; // thanks @ScreenfeedFr, http://bit.ly/1KbIdPg
        }

        return $caps;
    }

    /**
     * Manually overriding specific Customizer behaviors.
     *
     * @listens WP#action:load-{$pagenow}
     *
     * @return void
     */
    public function override_load_customizer_action() : void
    {
        // If accessed directly
        wp_die( __( 'The Customizer is currently disabled.', 'ecocide' ) );
    }

    /**
     * Returns the instance of the module.
     *
     * @access public
     * @return static
     */
    public static function get_instance()
    {
        // If the single instance hasn't been set, set it now.
        if ( null === static::$instance ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Calls the requested method from the module.
     *
     * @param  string  $method The method to ne called.
     * @param  array   $args   Zero or more parameters to be passed to the method.
     * @return mixed
     */
    public static function __callStatic( $method, $args )
    {
        $instance = static::get_instance();

        return $instance ? $instance->$method( ...$args ) : null;
    }
}
