<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePostFormat;

/**
 * Disable WordPress Post Formats
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_post_format/';

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
        remove_filter( 'request', '_post_format_request' );

        add_filter( 'post_format_rewrite_rules', '__return_empty_array', 50 );

        add_filter( 'disable_formats_dropdown', '__return_true', 50 );

        add_filter( 'register_taxonomy_args', [ $this, 'register_taxonomy_args' ], 50, 2 );

        add_action( 'wp_loaded', [ $this, 'filter_post_type_support' ] );

        add_action( 'wp_loaded', [ $this, 'filter_theme_support' ] );
    }

    /**
     * Disables public access, querying, and UI to the 'post_format' taxonomy.
     *
     * @listens WP#filter:register_taxonomy_args
     *
     * @param  array  $args      Array of arguments for registering a taxonomy.
     * @param  string $post_type Taxonomy key.
     * @return array  $args
     */
    public function register_taxonomy_args( array $args, string $taxonomy ) : array
    {
        if ( 'post_format' === $taxonomy ) {
            $args['rewrite']           = false;
            $args['public']            = false;
            $args['show_ui']           = false;
            $args['show_admin_column'] = false;
        }

        return $args;
    }

    /**
     * Removes "post-formats" support from post types.
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
                if ( post_type_supports( $post_type, 'post-formats' ) ) {
                    remove_post_type_support( $post_type, 'post-formats' );
                }
            }
        }
    }

    /**
     * Removes "post-formats" support from theme.
     *
     * @see wp-settings.php
     *
     * @listens WP#action:wp_loaded
     *
     * @return void
     */
    public function filter_theme_support() : void
    {
        remove_theme_support( 'post-formats' );
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
