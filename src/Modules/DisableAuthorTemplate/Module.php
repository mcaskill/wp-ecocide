<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableAuthorTemplate;

/**
 * Disable WordPress Author Pages
 *
 * @link       https://github.com/littlebizzy/disable-author-pages
 * @version    1.1.0 littlebizzy/disable-author-pages
 * @copyright  LittleBizzy, Jesse Nickles
 * @license    https://github.com/littlebizzy/disable-author-pages/blob/master/LICENSE GPLv3
 */
class Module implements \Ecocide\Contracts\Modules\Module
{
    const EVENT_PREFIX = 'ecocide/modules/disable_author_template/';

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
     *     @type array $hooks {
     *         @type bool $template_redirect         Respond with 404 for author templates.
     *         @type bool $author_template_hierarchy Disable querying author templates.
     *         @type bool $author_rewrite_rules      Disable author routes.
     *         @type bool $author_link               Replace author link URL with home URL for get_author_posts_url (for manually builded links).
     *         @type bool $the_author_posts_link     Totally replace author link tag (for kinks, generated with get_the_author_posts_link() fucntion).
     *         @type bool $pll_translated_slugs      Disable Polylang's slug translation.
     *     }
     * }
     * @return void
     */
    public function boot( array $args = [] ) : void
    {
        $defaults = [
            'hooks' => [
                'template_redirect'         => true,
                'author_template_hierarchy' => true,
                'author_rewrite_rules'      => true,
                'author_link'               => true,
                'the_author_posts_link'     => true,
                'pll_translated_slugs'      => true,
            ],
        ];

        if ( ! empty( $defaults['hooks'] ) && ! empty( $args['hooks'] ) ) {
            $args['hooks'] = wp_parse_args( $args['hooks'], $defaults['hooks'] );
        }

        $args = wp_parse_args( $args, $defaults );

        if ( $args['hooks']['template_redirect'] ) {
            add_action( 'template_redirect', [ $this, 'disable_author_template' ], 0 );
        }

        if ( $args['hooks']['author_template_hierarchy'] ) {
            add_filter( 'author_template_hierarchy', '__return_empty_array', 99 );
        }

        if ( $args['hooks']['author_rewrite_rules'] ) {
            add_filter( 'author_rewrite_rules', '__return_empty_array', 99 );
        }

        if ( $args['hooks']['author_link'] ) {
            add_filter( 'author_link', [ $this, 'disable_author_link_url' ], 99 );
        }

        if ( $args['hooks']['the_author_posts_link'] ) {
            add_filter( 'the_author_posts_link', [ $this, 'disable_author_link_tag' ], 99 );
        }

        if ( $args['hooks']['pll_translated_slugs'] ) {
            add_filter( 'pll_translated_slugs', [ $this, 'filter_pll_translated_slugs' ], 20 );
        }
    }

    /**
     * Remove the "author" slug from Polylang's string translations.
     *
     * @listens PLL#filter:pll_translated_slugs
     *
     * @param  array $slugs The translation slugs.
     * @return array
     */
    public function filter_pll_translated_slugs( $slugs )
    {
        unset( $slugs['author'] );

        return $slugs;
    }

    /**
     * Sets the request to 404 for the author page.
     *
     * @see wp-includes/template-loader.php
     *
     * @listens WP#action:template_redirect
     *
     * @return void
     */
    public function disable_author_template() : void
    {
        if ( $this->is_author() ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
        }
    }

    /**
     * Retrieves the home URL instead of the URL to the author page.
     *
     * @see \get_author_posts_url()
     *
     * @listens WP#filter:author_link
     *
     * @param  string $url The URL to the author's page.
     * @return string Home URL.
     */
    public function disable_author_link_url( string $url ) : string
    {
        $url = esc_url( home_url( '/' ) );

        /**
         * Filters the URL to replace the author's page.
         *
         * @event Ecocide#filter:ecocide/modules/disable_author_template/replace_author_link_url
         *
         * @param string $url The URL to replace the author's page.
         */
        return apply_filters( static::EVENT_PREFIX . 'replace_author_url', $url );
    }

    /**
     * Retrieves the name of the author of the current post instead of an HTML link to the author page.
     *
     * @see \get_the_author_posts_link()
     *
     * @listens WP#filter:the_author_posts_link
     *
     * @param  string $link An HTML link to the author page.
     * @return string The author's display name.
     */
    public function disable_author_link_tag( string $link ) : string
    {
        $author_name = get_the_author();

        /**
         * Filters the HTML-formatted string to replace the link to the author page.
         *
         * @event Ecocide#filter:ecocide/modules/disable_author_template/replace_author_link_url
         *
         * @param string $author_name The author's display name.
         */
        return apply_filters( static::EVENT_PREFIX . 'replace_author_link', $author_name );
    }

    /**
     * Determines whether the current query is for an author archive page.
     *
     * Additional check for is made for "author" request parameter.
     *
     * @return bool
     */
    protected function is_author() : bool
    {
        return ! empty( $_GET['author'] ) || is_author();
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
