<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePostCategory;

/**
 * Disable WordPress Post Categories
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_post_category/';

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
        add_filter( 'category_rewrite_rules', '__return_empty_array', 50 );

        add_filter( 'register_taxonomy_args', [ $this, 'register_taxonomy_args' ], 50, 2 );
    }

    /**
     * Disables public access, querying, and UI to the 'category' taxonomy.
     *
     * @listens WP#filter:register_taxonomy_args
     *
     * @param  array  $args      Array of arguments for registering a taxonomy.
     * @param  string $post_type Taxonomy key.
     * @return array  $args
     */
    public function register_taxonomy_args( array $args, string $taxonomy ) : array
    {
        if ( 'category' === $taxonomy ) {
            $args['rewrite']           = false;
            $args['public']            = false;
            $args['show_ui']           = false;
            $args['show_admin_column'] = false;
        }

        return $args;
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
