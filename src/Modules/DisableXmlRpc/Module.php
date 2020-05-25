<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableXmlRpc;

/**
 * Disable WordPress XML-RPC
 *
 * Enabled by default as of WordPress 3.5.0.
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_xmlrpc/';

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
        add_filter( 'xmlrpc_enabled', '__return_false' );

        add_filter( 'xmlrpc_methods', '__return_empty_array' );

        add_filter( 'xmlrpc_element_limit', [ $this, 'filter_xmlrpc_element_limit_handler' ], 999 );
    }

    /**
     * Filters the number of elements to parse in an XML-RPC response.
     *
     * @listens WP#filter:xmlrpc_element_limit
     *
     * @param  int $element_limit Default elements limit.
     * @return int
     */
    public function filter_xmlrpc_element_limit_handler( int $element_limit ) : int
    {
        return 1;
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
