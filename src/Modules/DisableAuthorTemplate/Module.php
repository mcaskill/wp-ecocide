<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableAuthorTemplate;

use Ecocide\Module;

/**
 * Disable WordPress Author Pages
 *
 * @link       https://github.com/littlebizzy/disable-author-pages
 * @version    1.1.0 littlebizzy/disable-author-pages
 * @copyright  LittleBizzy, Jesse Nickles
 * @license    https://github.com/littlebizzy/disable-author-pages/blob/master/LICENSE GPLv3
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_author_template/';

    /**
     * {@inheritdoc}
     *
     * @param  array $options {
     *     An associative array of options to customize the module.
     *
     *     @type HookActiveState $hooks {
     *         @type HookActiveState $author_link               Replaces the author link URL.
     *         @type HookActiveState $author_rewrite_rules      Clears the compiled author routes.
     *         @type HookActiveState $author_template_hierarchy Clears the compiled author templates.
     *         @type HookActiveState $pll_translated_slugs      Withdraws from Polylang's slug translation.
     *         @type HookActiveState $rewrite_rules             Alias of `author_rewrite_rules`.
     *         @type HookActiveState $template_redirect         Responds with 404 to the author template.
     *         @type HookActiveState $the_author_posts_link     Replaces the HTML author link.
     *     }
     * }
     */
    public function boot( array $options = [] ) : void
    {
        if ( $this->is_booted() ) {
            return;
        }

        $this->booted = true;

        $this->options = $options;

        $this->add_filter( 'author_link', [ $this, 'filter_author_link' ], 99 );

        $this->add_filter( 'author_template_hierarchy', '__return_empty_array', 99 );

        $this->add_filter( 'pll_translated_slugs', [ $this, 'filter_pll_translated_slugs' ], 20 );

        $this->add_filter( 'request', [ $this, 'filter_request' ], 20 );

        if ( $this->is_hook_active( 'rewrite_rules' ) ) {
            $this->add_filter( 'author_rewrite_rules', '__return_empty_array', 99 );
        }

        $this->add_action( 'template_redirect', [ $this, 'action_template_redirect' ], 0 );

        $this->add_filter( 'the_author_posts_link', [ $this, 'filter_the_author_posts_link' ], 99 );
    }

    /**
     * Responds with 404 to the author template and
     * sets the main query to 404.
     *
     * @see wp-includes/template-loader.php
     *
     * @listens action:template_redirect
     *
     * @global WP_Query $wp_the_query WordPress Query object.
     */
    public function action_template_redirect() : void
    {
        if ( $this->is_author() ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
        }
    }

    /**
     * Returns the URL to the home page, or other,
     * instead of the URL to the author page.
     *
     * @see \get_author_posts_url()
     *
     * @listens filter:author_link
     *
     * @param  string $url The URL to the author's page.
     * @return string Home URL.
     */
    public function filter_author_link( string $url ) : string
    {
        $url = esc_url( home_url( '/' ) );

        /**
         * Filters the URL to replace the author's page.
         *
         * @event filter:ecocide/modules/disable_author_template/replace_author_link_url
         *
         * @param string $url The URL to replace the author's page.
         */
        return apply_filters( static::HOOK_PREFIX . 'replace_author_url', $url );
    }

    /**
     * Removes any 'author' slug from Polylang's string translations.
     *
     * @listens filter:pll_translated_slugs
     *
     * @param  array<string, mixed> $slugs The translation slugs.
     * @return array<string, mixed>
     */
    public function filter_pll_translated_slugs( array $slugs ) : array
    {
        unset( $slugs['author'] );

        return $slugs;
    }

    /**
     * Removes any 'author' from the parsed query variables.
     *
     * @listens filter:request
     *
     * @param  array<string, string> $query_vars The array of requested query variables.
     * @return array<string, string>
     */
    public function filter_request( array $query_vars ) : array
    {
        unset( $query_vars['author'] );

        return $query_vars;
    }

    /**
     * Returns the name of the author of the current post
     * instead of an HTML link to the author page.
     *
     * @see \get_the_author_posts_link()
     *
     * @listens filter:the_author_posts_link
     *
     * @param  string $link An HTML link to the author page.
     * @return string The author's display name.
     */
    public function filter_the_author_posts_link( string $link ) : string
    {
        $author_name = get_the_author();

        /**
         * Filters the HTML-formatted string to replace the link to the author page.
         *
         * @event filter:ecocide/modules/disable_author_template/replace_author_link_url
         *
         * @param string $author_name The author's display name.
         */
        return apply_filters( static::HOOK_PREFIX . 'replace_author_link', $author_name );
    }

    /**
     * Determines whether the current query is for an author archive page.
     *
     * Additional check for is made for 'author' request parameter.
     */
    protected function is_author() : bool
    {
        return ( ! empty( $_GET['author'] ) || is_author() );
    }
}
