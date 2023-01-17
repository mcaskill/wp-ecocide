<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePost;

use Ecocide\Module;
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
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_post/';

    /**
     * {@inheritdoc}
     *
     * @param  array $options {
     *     An associative array of options to customize the module.
     *
     *     @type HookActiveState $dashboard_widgets {
     *         @type HookActiveState $quick_press             Disable Quick Press widget.
     *         @type HookActiveState $recent_drafts           Disable Recent Drafts widget.
     *     }
     *     @type HookActiveState $hooks {
     *         @type HookActiveState $admin_menu              Disable access to admin edit pages.
     *         @type HookActiveState $date_rewrite_rules      Disable date routes.
     *         @type HookActiveState $post_rewrite_rules      Disable post routes.
     *         @type HookActiveState $pre_get_posts           Exclude 'post' post type in {@see \WP_Query}.
     *         @type HookActiveState $register_post_type_args Disable the post type.
     *         @type HookActiveState $rest_availability_url   Replace the REST API URL for the REST API availability health check.
     *         @type HookActiveState $rewrite_rules           Disable all post-related routes.
     *         @type HookActiveState $wp_dashboard_setup      Disable admin dashboard widgets.
     *     }
     * }
     * @return void
     */
    public function boot( array $options = [] ) : void
    {
        if ( $this->is_booted() ) {
            return;
        }

        $this->booted = true;

        $this->options = $options;

        if ( $args['hooks']['register_post_type_args'] ) {
            add_filter( 'register_post_type_args', [ $this, 'filter_register_post_type_args' ], 50, 2 );
        }

        if ( $args['hooks']['rest_availability_url'] ) {
            add_filter( 'rest_url', [ $this, 'filter_rest_url' ], 50, 2 );
        }

        if ( $args['hooks']['rewrite_rules'] ) {
            if ( $args['hooks']['date_rewrite_rules'] ) {
                add_filter( 'date_rewrite_rules', '__return_empty_array', 50 );
            }

            if ( $args['hooks']['post_rewrite_rules'] ) {
                add_filter( 'post_rewrite_rules', '__return_empty_array', 50 );
            }
        }

        if ( is_admin() ) {
            if ( $args['hooks']['admin_menu'] ) {
                add_action( 'admin_menu', [ $this, 'action_admin_menu' ] );
            }

            if ( $args['hooks']['wp_dashboard_setup'] ) {
                add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widgets' ], 50 );
            }
        } else {
            if ( $args['hooks']['pre_get_posts'] ) {
                add_action( 'pre_get_posts', [ $this, 'action_pre_get_posts' ] );
            }
        }
    }

    /**
     * Denies access if attempting to view 'post' post type in Admin.
     *
     * @listens action:admin_menu
     *
     * @return void
     */
    public function action_admin_menu() : void
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
     * @listens action:pre_get_posts
     *
     * @param  WP_Query $wp_query The query object (passed by reference).
     * @return void
     */
    public function action_pre_get_posts( WP_Query $wp_query ) : void
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
     * Disables public access, querying, and UI to the 'post' post type.
     *
     * @listens filter:register_post_type_args
     *
     * @param  array  $args      Array of arguments for registering a post type.
     * @param  string $post_type Post type key.
     * @return array  $args
     */
    public function filter_register_post_type_args( array $args, string $post_type ) : array
    {
        if ( 'post' !== $post_type ) {
            return $args;
        }

        return array_replace( $args, [
            'exclude_from_search'  => false,
            'public'               => false,
            'publicly_queryable'   => false,
            'show_in_admin_bar'    => false,
            'show_in_menu'         => false,
            'show_in_nav_menus'    => false,
            'show_in_rest'         => false,
            'show_ui'              => false,
        ] );
    }

    /**
     * Allows the REST URL to test for REST API availability to be customized.
     *
     * If this filter is triggered from {@see \WP_Site_Health::get_test_rest_availability()}
     * and the route is `wp/v2/types/post`, apply a {@event filter:ecocide/modules/disable_post/test_rest_availability_url filter}
     * to change the test URL.
     *
     * This hack should be deprecated if ever {@see https://core.trac.wordpress.org/ticket/57440 #57440}
     * is merged and released.
     *
     * @listens filter:rest_url
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
             * Filters the REST API URL for the REST API availability health check.
             *
             * @event filter:ecocide/modules/disable_post/test_rest_availability_url
             *
             * @param string $url  REST URL.
             * @param string $path REST route.
             */
            return apply_filters( static::HOOK_PREFIX . 'test_rest_availability_url', $url, $path );
        }

        return $url;
    }

    /**
     * Unregisters the default WordPress Quick Press and Recent Drafts dashboard widgets.
     *
     * @see \wp_dashboard_setup()
     *
     * @listens action:wp_dashboard_setup
     *
     * @return void
     */
    public function remove_dashboard_widgets(): void
    {
        if ( $this->$this->options['dashboard_widgets']['quick_press'] ) {
            remove_meta_box('dashboard_quick_press',   'dashboard', 'side');
        }

        if ( $this->$this->options['dashboard_widgets']['recent_drafts'] ) {
            remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        }
    }
}
