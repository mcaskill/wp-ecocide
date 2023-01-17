<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableXmlRpc;

use Ecocide\Module;

/**
 * Disable WordPress XML-RPC
 *
 * Enabled by default as of WordPress 3.5.0.
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_xmlrpc/';

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
        add_filter( 'xmlrpc_enabled', '__return_false' );

        add_filter( 'xmlrpc_methods', '__return_empty_array' );

        add_filter( 'xmlrpc_element_limit', [ $this, 'filter_xmlrpc_element_limit_handler' ], 999 );
    }

    /**
     * Filters the number of elements to parse in an XML-RPC response.
     *
     * @listens filter:xmlrpc_element_limit
     *
     * @param  int $element_limit Default elements limit.
     * @return int
     */
    public function filter_xmlrpc_element_limit_handler( int $element_limit ) : int
    {
        return 1;
    }
}
