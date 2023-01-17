<?php

declare(strict_types=1);

namespace Ecocide\Modules\DisableAttachmentTemplate;

use Ecocide\Module;

/**
 * Disable WordPress Attachment Pages
 *
 * @link       https://gist.github.com/gschoppe/6307e7dfdbbca261fdf42f411d660de1
 * @version    2018-06-18 (96d2a49)
 * @copyright  Greg Schoppe
 *
 * @psalm-import-type HookActiveState from \Ecocide\Contracts\Modules\Module
 */
class Module extends BaseModule
{
    public const HOOK_PREFIX = BaseModule::HOOK_PREFIX . 'disable_attachment_template/';
    public const SLUG_PREFIX = 'wp-attachment-';

    /**
     * {@inheritdoc}
     *
     * @param  array $options {
     *     An associative array of options to customize the module.
     *
     *     @type HookActiveState $hooks {
     *         @type HookActiveState $attachment_link         Replaces the attachment page URL with the attached file URL.
     *         @type HookActiveState $pll_translated_slugs    Withdraws from Polylang's slug translation.
     *         @type HookActiveState $register_post_type_args Disables public access to the post type.
     *         @type HookActiveState $request                 Disables any attachment query var.
     *         @type HookActiveState $rewrite_rules           Alias of `rewrite_rules_array`.
     *         @type HookActiveState $rewrite_rules_array     Clears the compiled attachment routes.
     *         @type HookActiveState $template_redirect       Redirects the attachment page to the attached file.
     *         @type HookActiveState $wp_unique_post_slug     Disables the unique post name.
     *     }
     * }
     * @return void
     */
    public function boot( array $options = [] ) : void
    {
        if ( $this->is_booted() ) {
            return;
        }

        $this->booted = true;

        $this->options = $options;

        $this->add_filter( 'attachment_link', [ $this, 'filter_attachment_link' ], 10, 2 );

        $this->add_filter( 'pll_translated_slugs', [ $this, 'filter_pll_translated_slugs' ], 20 );

        $this->add_filter( 'register_post_type_args', [ $this, 'filter_register_post_type_args' ], 10, 2 );

        $this->add_filter( 'request', [ $this, 'filter_request' ], 20 );

        if ( $this->is_hook_active( 'rewrite_rules' ) ) {
            $this->add_filter( 'rewrite_rules_array', [ $this, 'filter_rewrite_rules_array' ] );
        }

        $this->add_action( 'template_redirect', [ $this, 'action_template_redirect' ] );

        $this->add_filter( 'wp_unique_post_slug', [ $this, 'filter_wp_unique_post_slug' ], 10, 6 );
    }

    /**
     * Redirects the attachment page to the file URL.
     *
     * Just in case everything else fails, and somehow
     * an attachment page is requested.
     *
     * @listens action:template_redirect
     *
     * @return void
     */
    public function action_template_redirect()
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
     * Returns the URL to the attached file
     * instead of the attachment page URL.
     *
     * @see \get_attachment_link()
     *
     * @listens filter:attachment_link
     *
     * @param  string $url The URL to the attachment's page.
     * @param  int    $id  The attachment ID.
     * @return string Attached file URL.
     */
    public function filter_attachment_link( $url, $id )
    {
        $attachment_url = wp_get_attachment_url( $id );
        if ( $attachment_url ) {
            return $attachment_url;
        }

        return $url;
    }

    /**
     * Removes the 'attachment' slug from Polylang's string translations.
     *
     * @listens filter:pll_translated_slugs
     *
     * @param  array<string, mixed> $slugs The translation slugs.
     * @return array<string, mixed>
     */
    public function filter_pll_translated_slugs( array $slugs ) : array
    {
        unset( $slugs['attachment'] );

        return $slugs;
    }

    /**
     * Disables public access and querying the 'attachment' post type.
     *
     * This does little, but maybe someday will, if WordPress standardizes
     * attachments as a post type.
     *
     * @listens filter:register_post_type_args
     *
     * @param  array<string, mixed> $args      Array of arguments for registering a post type.
     * @param  string               $post_type Post type key.
     * @return array<string, mixed> $args
     */
    public function filter_register_post_type_args( array $args, string $post_type ) : array
    {
        if ( $post_type !== 'attachment' ) {
            return $args;
        }

        return array_replace( $args, [
            'public'             => false,
            'publicly_queryable' => false,
        ] );
    }

    /**
     * Removes any 'attachment' from the parsed query variables.
     *
     * @listens filter:request
     *
     * @param  array<string, string> $query_vars The array of requested query variables.
     * @return array<string, string>
     */
    public function filter_request( array $query_vars ) : array
    {
        if ( ! empty( $query_vars['attachment'] ) ) {
            $query_vars['page'] = '';
            $query_vars['name'] = $query_vars['attachment'];
            unset( $query_vars['attachment'] );
        }

        return $query_vars;
    }

    /**
     * Removes any attachment rewrite rules.
     *
     * @listens filter:rewrite_rules_array
     *
     * @param  string[] $rules The compiled array of rewrite rules,
     *     keyed by their regex pattern.
     * @return string[]
     */
    public function filter_rewrite_rules_array( array $rules ) : array
    {
        foreach ( $rules as $pattern => $rewrite ) {
            if ( preg_match( '/([\?&]attachment=\$matches\[)/', $rewrite ) ) {
                unset( $rules[$pattern] );
            }
        }

        return $rules;
    }

    /**
     * Disables generating a unique post slug for attachments.
     *
     * Store the attachnent's desired (i.e. current) slug so it can try
     * to reclaim it if this module is disabled.
     *
     * @see \wp_unique_post_slug() WordPress v5.4.1.
     *
     * @listens filter:wp_unique_post_slug
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
    public function filter_wp_unique_post_slug(
        $slug,
        $post_ID,
        $post_status,
        $post_type,
        $post_parent,
        $original_slug
    ) {
        global $wpdb, $wp_rewrite;

        if ( $post_type === 'attachment' ) {
            $prefix = apply_filters( static::HOOK_PREFIX . 'attachment_slug_prefix', static::SLUG_PREFIX, $original_slug, $post_ID, $post_status, $post_type, $post_parent );
            if ( ! $prefix ) {
                return $slug;
            }

            if ( false === strpos( $original_slug, $prefix ) ) {
                $slug = $prefix . $original_slug;
            }

            // remove this filter and rerun with the prefix
            remove_filter( 'wp_unique_post_slug', [ $this, 'wp_unique_post_slug' ], 10 );
            $slug = wp_unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent );
            add_filter( 'wp_unique_post_slug', [ $this, 'wp_unique_post_slug' ], 10, 6 );
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
}
