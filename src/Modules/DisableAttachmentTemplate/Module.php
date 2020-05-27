<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableAttachmentTemplate;

/**
 * Disable WordPress Attachment Pages
 *
 * @link       https://gist.github.com/gschoppe/6307e7dfdbbca261fdf42f411d660de1
 * @version    2018-06-18 (96d2a49)
 * @copyright  Greg Schoppe
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_attachment_template/';
    const SLUG_PREFIX  = 'wp-attachment-';

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
        add_filter( 'rewrite_rules_array', [ $this, 'remove_attachment_rewrites' ] );

        add_filter( 'wp_unique_post_slug', [ $this, 'wp_unique_post_slug' ], 10, 6 );

        add_filter( 'request', [ $this, 'remove_attachment_query_var' ] );

        add_filter( 'attachment_link'  , [ $this, 'change_attachment_link_to_file' ], 10, 2 );

        // just in case everything else fails, and somehow an attachment page is requested
        add_action( 'template_redirect', [ $this, 'redirect_attachment_pages_to_file' ] );

        // this does nothing currently, but maybe someday will, if WordPress standardizes attachments as a post type
        add_filter( 'register_post_type_args', [ $this, 'make_attachments_private' ], 10, 2 );

        add_filter( 'pll_translated_slugs', [ $this, 'filter_pll_translated_slugs' ], 20 );
    }

    /**
     * Remove the "attachment" slug from Polylang's string translations.
     *
     * @listens PLL#filter:pll_translated_slugs
     *
     * @param  array $slugs The translation slugs.
     * @return array
     */
    public function filter_pll_translated_slugs( $slugs )
    {
        unset( $slugs['attachment'] );

        return $slugs;
    }

    /**
     * Remove any attachment rewrite rules.
     *
     * @listens WP#filter:rewrite_rules_array
     *
     * @param  string[] $rules The compiled array of rewrite rules, keyed by their regex pattern.
     * @return string[]
     */
    public function remove_attachment_rewrites( $rules )
    {
        foreach ( $rules as $pattern => $rewrite ) {
            if ( preg_match( '/([\?&]attachment=\$matches\[)/', $rewrite ) ) {
                unset( $rules[$pattern] );
            }
        }
        return $rules;
    }

    /**
     * Filters the unique post slug.
     *
     * Store the attachnent's desired (i.e. current) slug so it can try to reclaim it
     * if this module is disabled.
     *
     * @see \wp_unique_post_slug() WordPress v5.4.1.
     *
     * @listens WP#filter:wp_unique_post_slug
     *
     *
     * @param   string  $slug           The post slug.
     * @param   int     $post_ID        Post ID.
     * @param   string  $post_status    The post status.
     * @param   string  $post_type      Post type.
     * @param   int     $post_parent    Post parent ID
     * @param   string  $original_slug  The original post slug.
     * @return  string  Unique slug for the post.
     */
    public function wp_unique_post_slug(
        $slug,
        $post_ID,
        $post_status,
        $post_type,
        $post_parent,
        $original_slug
    ) {
        global $wpdb, $wp_rewrite;

        if ( $post_type === 'attachment' ) {
            $prefix = apply_filters( static::EVENT_PREFIX . 'attachment_slug_prefix', static::SLUG_PREFIX, $original_slug, $post_ID, $post_status, $post_type, $post_parent );
            if ( ! $prefix ) {
                return $slug;
            }

            if ( false === strpos( $original_slug, $prefix ) ) {
                $slug = $prefix . $original_slug;
            }

            // remove this filter and rerun with the prefix
            remove_filter( 'wp_unique_post_slug', array( $this, 'wp_unique_post_slug' ), 10 );
            $slug = wp_unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent );
            add_filter( 'wp_unique_post_slug', array( $this, 'wp_unique_post_slug' ), 10, 6 );
            return $slug;
        }

        if ( ! is_post_type_hierarchical( $post_type ) ) {
            return $slug;
        }

        $feeds = $wp_rewrite->feeds;
        if( ! is_array( $feeds ) ) {
            $feeds = array();
        }

        /*
         * NOTE: This is the big change. We are NOT checking attachments along with our post type
         */
        $slug = $original_slug;
        $check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s ) AND ID != %d AND post_parent = %d LIMIT 1";
        $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID, $post_parent ) );

        /**
         * Filters whether the post slug would make a bad hierarchical post slug.
         *
         * @since 3.1.0
         *
         * @param bool   $bad_slug    Whether the post slug would be bad in a hierarchical post context.
         * @param string $slug        The post slug.
         * @param string $post_type   Post type.
         * @param int    $post_parent Post parent ID.
         */
        if (
            $post_name_check ||
            in_array( $slug, $feeds ) ||
            'embed' === $slug ||
            preg_match( "@^({$wp_rewrite->pagination_base})?\d+$@", $slug ) ||
            apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent )
        ) {
            $suffix = 2;
            do {
                $alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( (string) $suffix ) + 1 ) ) . "-{$suffix}";
                $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID, $post_parent ) );
                $suffix++;
            } while ( $post_name_check );
            $slug = $alt_post_name;
        }

        return $slug;
    }

    /**
     * Remove any attachments from the parsed query variables.
     *
     * @listens WP#filter:request
     *
     * @param  array $query_vars The array of requested query variables.
     * @return array
     */
    public function remove_attachment_query_var( $query_vars )
    {
        if ( ! empty( $query_vars['attachment'] ) ) {
            $query_vars['page'] = '';
            $query_vars['name'] = $query_vars['attachment'];
            unset( $query_vars['attachment'] );
        }

        return $query_vars;
    }

    /**
     * Disables public access and querying the 'attachment' post type.
     *
     * @listens WP#filter:register_post_type_args
     *
     * @param  array  $args      Array of arguments for registering a post type.
     * @param  string $post_type Post type key.
     * @return array  $args
     */
    public function make_attachments_private( array $args, string $post_type ) : array
    {
        if ( $post_type === 'attachment' ) {
            $args['public'] = false;
            $args['publicly_queryable'] = false;
        }

        return $args;
    }

    public function change_attachment_link_to_file( $url, $id )
    {
        $attachment_url = wp_get_attachment_url( $id );
        if ( $attachment_url ) {
            return $attachment_url;
        }
        return $url;
    }

    /**
     * Redirect the attachment to the file URL.
     *
     * @listens WP#action:template_redirect
     *
     * @return void
     */
    public function redirect_attachment_pages_to_file()
    {
        if ( is_attachment() ) {
            $id  = get_the_ID();
            $url = wp_get_attachment_url( $id );
            if ( $url ) {
                wp_redirect( $url, 301 );
                die;
            }
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
