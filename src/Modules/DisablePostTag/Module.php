<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePostTag;

use Ecocide\Module;

/**
 * Disable WordPress Post Tags
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_post_tag/';

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
        add_filter( 'post_tag_rewrite_rules', '__return_empty_array', 50 );

        add_filter( 'register_taxonomy_args', [ $this, 'register_taxonomy_args' ], 50, 2 );
    }

    /**
     * Disables public access, querying, and UI to the 'post_tag' taxonomy.
     *
     * @listens filter:register_taxonomy_args
     *
     * @param  array  $args      Array of arguments for registering a taxonomy.
     * @param  string $post_type Taxonomy key.
     * @return array  $args
     */
    public function register_taxonomy_args( array $args, string $taxonomy ) : array
    {
        if ( 'post_tag' === $taxonomy ) {
            $args['rewrite']           = false;
            $args['public']            = false;
            $args['show_ui']           = false;
            $args['show_admin_column'] = false;
        }

        return $args;
    }
}
