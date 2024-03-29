<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePost;

use WP_Query;

/**
 * Disable WordPress Posts
 *
 * This module globally disables the "post" post type.
 *
 * @link       https://github.com/tonykwon/wp-disable-posts
 * @version    0.1 tonykwon/wp-disable-posts
 *     Merges changes from:
 *     - forgetfuljames/wp-disable-posts
 *     - hatsumatsu/wp-disable-posts
 *     - lucatume/wp-disable-posts
 *     - mcaskill/wp-disable-posts
 * @copyright  Tony Kwon
 * @license    https://github.com/tonykwon/wp-disable-posts/blob/master/LICENSE GPLv2
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_post/';

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
        add_filter( 'post_rewrite_rules', '__return_empty_array', 50 );
        add_filter( 'date_rewrite_rules', '__return_empty_array', 50 );

        add_filter( 'rest_url', [ $this, 'filter_rest_url' ], 50, 2 );

        add_filter( 'register_post_type_args', [ $this, 'register_post_type_args' ], 50, 2 );

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'disallow_admin_posts' ] );
            add_action( 'wp_dashboard_setup', [ $this, 'filter_dashboard' ] );
        } else {
            add_action( 'pre_get_posts', [ $this, 'disallow_query_posts' ] );
        }
    }

    /**
     * Customizes the REST URL to test for REST API availability.
     *
     * If this filter is triggered from {@see \WP_Site_Health::get_test_rest_availability()}
     * and the route is `wp/v2/types/post`, apply a {@event filter:ecocide/modules/disable_post/test_rest_availability_url filter}
     * to change the test URL.
     *
     * This hack should be deprecated if ever {@see https://core.trac.wordpress.org/ticket/57440 #57440}
     * is merged and released.
     *
     * @listens WP#filter:rest_url
     *
     * @param  string $url  REST URL.
     * @param  string $path REST route.
     * @return string $url
     */
    public function filter_rest_url( string $url, string $path ) : string
    {
        if ( '/wp/v2/types/post' !== $path ) {
            return $url;
        }

        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 7 );
        foreach ( $trace as $step ) {
            if ( ! isset( $step['function'], $step['class'] ) ) {
                continue;
            }

            if (
                'get_test_rest_availability' !== $step['function'] ||
                'WP_Site_Health' !== $step['class']
            ) {
                continue;
            }

            /**
             * Filters the URL to replace the author's page.
             *
             * @event Ecocide#filter:ecocide/modules/disable_post/test_rest_availability_url
             *
             * @param string $url  REST URL.
             * @param string $path REST route.
             */
            return apply_filters( static::EVENT_PREFIX . 'test_rest_availability_url', $url, $path );
        }

        return $url;
    }

    /**
     * Disables public access, querying, and UI to the 'post' post type.
     *
     * @listens WP#filter:register_post_type_args
     *
     * @param  array  $args      Array of arguments for registering a post type.
     * @param  string $post_type Post type key.
     * @return array  $args
     */
    public function register_post_type_args( array $args, string $post_type ) : array
    {
        if ( 'post' === $post_type ) {
            $args = array_merge( $args, [
                'public'               => false,
                'show_ui'              => false,
                'show_in_rest'         => false,
                'show_in_menu'         => false,
                'show_in_admin_bar'    => false,
                'show_in_nav_menus'    => false,
                'publicly_queryable'   => false,
                'exclude_from_search'  => false,
            ] );
        }

        return $args;
    }

    /**
     * Unregisters the default WordPress Quick Press and Recent Drafts dashboard widgets.
     *
     * @see \wp_dashboard_setup()
     *
     * @listens WP#action:wp_dashboard_setup
     *
     * @return void
     */
    public function filter_dashboard() : void
    {
        remove_meta_box('dashboard_quick_press',   'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
    }

    /**
     * Die if attempting to view 'post' post type in Admin.
     *
     * @listens WP#action:admin_menu
     *
     * @return void
     */
    public function disallow_admin_posts() : void
    {
        global $pagenow;

        switch ( $pagenow ) {
            case 'edit.php':
            case 'edit-tags.php':
            case 'post-new.php':
                if ( ! array_key_exists( 'post_type', $_GET ) && ! array_key_exists( 'taxonomy', $_GET ) && ! $_POST ) {
                    wp_die( __( 'Posts are disabled.' ), '', [ 'response' => 403 ] );
                }
        }
    }

    /**
     * Excludes post type 'post' to be returned from search.
     *
     * @listens WP#action:pre_get_posts
     *
     * @param  WP_Query $wp_query The query object (passed by reference).
     * @return void
     */
    public function disallow_query_posts( WP_Query $wp_query ) : void
    {
        if ( ! is_search() || ! $wp_query->is_main_query() ) {
            return;
        }

        $post_types = (array) $wp_query->get('post_type');
        if ( empty( $post_types ) ) {
            $post_types = get_post_types( [
                'exclude_from_search' => false,
            ] );

            if ( array_key_exists( 'post', $post_types ) ) {
                /* Exclude post_type 'post' from the query results */
                unset( $post_types['post'] );
            }

            $post_types = array_values( $post_types );
            $wp_query->set( 'post_type', $post_types );
        } else {
            $post_types = array_diff( $post_types, [ 'post' ] );
            $wp_query->set( 'post_type', $post_types );
        }
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
