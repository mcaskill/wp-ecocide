<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisablePostFormat;

use Ecocide\Module;

/**
 * Disable WordPress Post Formats
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_post_format/';

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
     * @listens filter:register_taxonomy_args
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
     * @listens action:wp_loaded
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
     * @listens action:wp_loaded
     *
     * @return void
     */
    public function filter_theme_support() : void
    {
        remove_theme_support( 'post-formats' );
    }
}
