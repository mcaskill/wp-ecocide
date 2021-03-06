<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableSearch;

use WP_Admin_Bar;
use WP_Query;

/**
 * Disable WordPress Search
 *
 * Disable the built-in front-end search capabilities of WordPress.
 *
 * Differences:
 * - Disables search rewrite rules via "search_rewrite_rules"
 * - Disables search form via `__return_empty_string()`
 *
 * @link       https://github.com/coffee2code/disable-search
 * @version    1.8.0 coffee2code/disable-search
 * @copyright  Scott Reilly
 * @license    https://github.com/coffee2code/disable-search/blob/master/LICENSE GPLv2
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_search/';

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
        if ( ! is_admin() ) {
            add_action( 'parse_query', [ $this, 'parse_query' ], 5 );
        }

        add_action( 'widgets_init', [ $this, 'disable_search_widget' ], 1 );

        add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 11 );

        add_filter( 'get_search_form', '__return_empty_string', 999 );

        add_filter( 'search_rewrite_rules', '__return_empty_array', 999 );

        add_filter( 'disable_wpseo_json_ld_search', '__return_true', 999 );
    }

    /**
     * Disables the built-in WP search widget.
     *
     * @listens action:widgets_init
     *     Fires after all default WordPress widgets have been registered.
     *
     * @return void
     */
    public function disable_search_widget() : void
    {
        unregister_widget( 'WP_Widget_Search' );
    }

    /**
     * Unsets all search-related variables in WP_Query object and sets the
     * request as a 404 if a search was attempted.
     *
     * @listens action:parse_query
     *     Fires after the main query vars have been parsed.
     *
     * @param  WP_Query $this The WP_Query instance.
     * @return void
     */
    public function parse_query( WP_Query $wp_query ) : void
    {
        if ( ! $wp_query->is_main_query() || ! $wp_query->is_search ) {
            return;
        }

        unset( $_GET['s'] );
        unset( $_POST['s'] );
        unset( $_REQUEST['s'] );
        unset( $wp_query->query['s'] );
        $wp_query->set( 's', '' );
        $wp_query->is_search = false;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }

    /**
     * Removes the search item from the admin bar.
     *
     * @listens action:admin_bar_menu
     *     Load all necessary admin bar items.
     *
     * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
     */
    public function admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) : void
    {
        $wp_admin_bar->remove_menu( 'search' );
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
