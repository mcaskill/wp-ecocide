<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableComments;

use WP_Admin_Bar;

/**
 * Disable WordPress Comments
 *
 * This module globally disables comments, pingbacks, and trackbacks.
 *
 * Any widgets or menu items and REST endpoints are disabled.
 *
 * @link       https://github.com/solarissmoke/disable-comments
 * @version    1.10.2 solarissmoke/disable-comments
 * @copyright  Samir Shah
 * @license    https://github.com/solarissmoke/disable-comments/blob/master/LICENSE GPLv2
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_comments/';

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
        add_filter( 'wp_headers', [ $this, 'filter_wp_headers' ] );

        add_action( 'widgets_init', [ $this, 'filter_widgets' ] );

        add_action( 'add_admin_bar_menus', [ $this, 'filter_admin_bar' ] );

        add_filter( 'rest_endpoints', [ $this, 'filter_rest_endpoints' ] );

        add_filter( 'comments_array', '__return_empty_array', 99 );

        add_filter( 'comments_open', '__return_false', 99 );

        add_filter( 'pings_open', '__return_false', 99 );

        add_filter( 'get_comments_number', '__return_zero', 99 );

        add_filter( 'comments_rewrite_rules', '__return_empty_array', 99 );

        add_filter( 'rewrite_rules_array', [ $this, 'filter_rewrite_rules' ], 99 );

        add_action( 'wp_loaded', [ $this, 'filter_post_type_support' ] );

        remove_action( 'init', 'register_block_core_latest_comments' );

        if ( is_admin() ) {
            // Delay action as late as possible
            add_action( 'admin_menu', [ $this, 'filter_admin_menu' ], 99 );

            add_action( 'admin_print_styles-index.php', [ $this, 'admin_css' ] );

            add_action( 'admin_print_styles-profile.php', [ $this, 'admin_css' ] );

            add_action( 'wp_dashboard_setup', [ $this, 'filter_dashboard' ] );

            add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
        } else {
            // Ensure action fires before 'redirect_canonical'
            add_action( 'template_redirect', [ $this, 'disable_comment_feed' ], 9 );

            add_action( 'template_redirect', [ $this, 'check_comment_template' ] );

            add_filter( 'post_comments_feed_link', '__return_false' );

            add_filter( 'comments_link_feed', '__return_false' );

            add_filter( 'comment_link', '__return_false' );

            add_filter( 'feed_links_show_comments_feed', '__return_false' );
        }
    }

    /**
     * Disables the comment template, comment-reply script, and comment feed links.
     *
     * @see wp-includes/template-loader.php
     *
     * @listens WP#action:template_redirect
     *
     * @return void
     */
    public function check_comment_template() : void
    {
        if ( is_singular() ) {
            // Kill the comments template. This will deal with themes that don't check comment stati properly!
            add_filter( 'comments_template', [ $this, 'dummy_comments_template' ], 20 );

            // Remove comment-reply script for themes that include it indiscriminately
            wp_deregister_script( 'comment-reply' );

            // Remove feed action
            remove_action( 'wp_head', 'feed_links_extra', 3 );
        }
    }

    /**
     * Removes the "X-Pingback" HTTP header.
     *
     * @see \WP::send_headers()
     *
     * @listens WP#filter:wp_headers
     *
     * @param  string[] $headers Associative array of HTTP headers to be sent.
     * @return string[]
     */
    public function filter_wp_headers( array $headers ) : array
    {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Removes the Comments endpoint from the REST API.
     *
     * @see \WP_REST_Server::get_routes()
     *
     * @listens WP#filter:rest_endpoints
     *
     * @param  string[] $endpoints Associative array of available REST endpoints.
     * @return string[]
     */
    public function filter_rest_endpoints( array $endpoints ) : array
    {
        unset( $endpoints['comments'] );
        return $endpoints;
    }

    /**
     * Removes pagination routes for Comments.
     *
     * @see \WP_Rewrite::rewrite_rules()
     *
     * @listens WP#filter:rewrite_rules_array
     *
     * @param  string[] $rules The compiled array of rewrite rules, keyed by their regex pattern.
     * @return string[]
     */
    public function filter_rewrite_rules( array $rules ) : array
    {
        foreach ( $rules as $pattern => $rewrite ) {
            if ( preg_match( '/[\?&]cpage=\$matches\[/', $rewrite ) ) {
                unset( $rules[$pattern] );
            }
        }

        return $rules;
    }

    /**
     * Sets the request to 404 for the comment feed.
     *
     * @see wp-includes/template-loader.php
     *
     * @listens WP#action:template_redirect
     *
     * @return void
     */
    public function disable_comment_feed() : void
    {
        if ( is_comment_feed() ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
        }
    }

    /**
     * Unregisters the default WordPress Recent Comments widget.
     *
     * @see \wp_widgets_init()
     *
     * @listens WP#action:widgets_init
     *
     * @return void
     */
    public function filter_widgets() : void
    {
        unregister_widget( 'WP_Widget_Recent_Comments' );

        /**
         * The widget has added a style action when it was constructed - which will
         * still fire even if we unregister the widget... so filter that out.
         */
        add_filter( 'show_recent_comments_widget_style', '__return_false' );
    }

    /**
     * Unregisters the default WordPress Recent Comments dashboard widget.
     *
     * @see \wp_dashboard_setup()
     *
     * @listens WP#action:wp_dashboard_setup
     *
     * @return void
     */
    public function filter_dashboard() : void
    {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }

    /**
     * Injects CSS to hide comment-related components.
     *
     * @see wo-admin/admin-header.php
     *
     * @listens WP#action:admin_print_styles-{$hook_suffix}
     *
     * @return void
     */
    public function admin_css() : void
    {
        $styles  = '<style>';
        $styles .= '#dashboard_right_now .comment-count, ';
        $styles .= '#dashboard_right_now .comment-mod-count, ';
        $styles .= '#latest-comments, ';
        $styles .= '#welcome-panel .welcome-comments, ';
        $styles .= '.user-comment-shortcuts-wrap ';
        $styles .= '{ display: none !important; }';
        $styles .= '</style>';

        echo $styles;
    }

    /**
     * Removes Comments and Discussions menu items from the WordPress menu.
     *
     * @see wp-admin/includes/menu.php
     * @see wp-admin/menu.php
     *
     * @listens WP#action:admin_menu
     *
     * @return void
     */
    public function filter_admin_menu() : void
    {
        global $pagenow;

        if ( in_array( $pagenow, [ 'comment.php', 'edit-comments.php', 'options-discussion.php' ] ) ) {
            wp_die( __( 'Comments are closed.' ), '', [ 'response' => 403 ] );
        }

        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    /**
     * Removes edit comments link in WordPress Admin Bar.
     *
     * @see \WP_Admin_Bar::add_menus()
     * @see \wp_admin_bar_comments_menu()
     *
     * @listens WP#action:add_admin_bar_menus
     *
     * @return void
     */
    public function filter_admin_bar() : void
    {
        remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );

        if ( is_user_logged_in() && is_multisite() ) {
            add_action( 'admin_bar_menu', [ $this, 'disable_admin_bar_network_comments' ], 500 );
        }
    }

    /**
     * Removes edit comments link in WordPress Admin Bar.
     *
     * @see \WP_Admin_Bar::add_menus()
     *
     * @listens WP#action:add_admin_bar_menus
     *
     * @param  WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar.
     * @return void
     */
    public function disable_admin_bar_network_comments( WP_Admin_Bar $wp_admin_bar ) : void
    {
        foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
            $node_id = 'blog-' . $blog->userblog_id . '-c';

            $wp_admin_bar->remove_node( $node_id );
        }
    }

    /**
     * Removes "comments" and "trackbacks" support from post types.
     *
     * @see wp-settings.php
     *
     * @listens WP#action:wp_loaded
     *
     * @return void
     */
    public function filter_post_type_support() : void
    {
        $post_types = array_keys( get_post_types( [ 'public' => true ], 'objects' ) );
        if ( ! empty( $post_types ) ) {
            foreach ( $post_types as $post_type ) {
                if ( post_type_supports( $post_type, 'comments' ) ) {
                    remove_post_type_support( $post_type, 'comments' );
                    remove_post_type_support( $post_type, 'trackbacks' );
                }
            }
        }
    }

    /**
     * Retrieves the path to the dummy comments template.
     *
     * @return string
     */
    public static function dummy_comments_template() : string
    {
        return __DIR__ . '/noop.php';
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
