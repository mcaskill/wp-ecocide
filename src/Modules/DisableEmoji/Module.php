<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableEmoji;

use Ecocide\Module;

/**
 * Disable WordPress emoji scripts and styles.
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_emoji/';

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
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );

        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

        remove_action( 'wp_print_styles', 'print_emoji_styles' );

        remove_action( 'admin_print_styles', 'print_emoji_styles' );

        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );

        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

        add_filter( 'emoji_svg_url', '__return_null' );
    }
}
