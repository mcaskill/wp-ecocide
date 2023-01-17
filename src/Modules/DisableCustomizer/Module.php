<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableCustomizer;

use Ecocide\Module;

/**
 * Disable WordPress Customizer
 *
 * @link       https://github.com/parallelus/customizer-remove-all-parts
 * @version    1.0.3-alpha parallelus/customizer-remove-all-parts
 * @copyright  Parallelus, Andy Wilkerson, Jesse Petersen
 * @license    https://github.com/parallelus/customizer-remove-all-parts/blob/master/LICENSE GPLv2
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_customizer/';

    /**
     * {@inheritdoc}
     *
     * @param  array $options {
     *     An associative array of options to customize the module.
     *
     *     @type array $hooks TODO: Define customizable hooks.
     * }
     * @return void
     */
    public function boot( array $options = [] ) : void
    {
        add_action( 'admin_init', [ $this, 'admin_init' ], 10 );

        add_action( 'init', [ $this, 'init' ], 10 );
    }

    /**
     * Remove customize capability
     *
     * @listens action:init
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
     * @listens action:admin_init
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
     * @listens filter:map_meta_cap
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
     * @listens action:load-{$pagenow}
     *
     * @return void
     */
    public function override_load_customizer_action() : void
    {
        // If accessed directly
        wp_die( __( 'The Customizer is currently disabled.', 'ecocide' ) );
    }
}
