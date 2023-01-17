<?php

declare(strict_types=1);

namespace Ecocide;

/**
 * Base Module
 *
 * @psalm-type HookActiveState = bool|array<string, bool>
 */
abstract class Module implements \Ecocide\Contracts\Modules\Module
{
    public const HOOK_PREFIX = 'ecocide/modules/';

    /**
     * Indicates if the module has "booted".
     */
    protected bool $booted = false;

    /**
     * Module customizations.
     *
     * @var ?array<string, mixed>
     */
    protected ?array $options;

    /**
     * Returns the singleton instance of this class.
     *
     * @static static $instance The singleton instance of this class.
     *
     * @return static
     */
    public static function get_instance()
    {
        static $instance = null;

        // If the single instance hasn't been set, set it now.
        if ( null === $instance ) {
            $instance = new static();
        }

        return $instance;
    }

    /**
     * Calls the requested method from the module.
     *
     * @param  string  $method The method to ne called.
     * @param  mixed[] $args   Zero or more parameters to be passed to the method.
     * @return mixed
     */
    public static function __callStatic( string $method, array $args )
    {
        $instance = static::get_instance();

        return $instance ? $instance->$method( ...$args ) : null;
    }

    /**
     * Boots the module.
     *
     * @param array<string, mixed> $options An associative array of options
     *     to customize the module.
     */
    abstract public function boot( array $options = [] ) : void;

    /**
     * Determines if the module has booted.
     */
    public function is_booted() : bool
    {
        return $this->booted;
    }

    /**
     * Adds the $callback to the action $hook, if the hook is not marked
     * as inactive by the module's options.
     */
    protected function add_action(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ) : bool {
        // Bail early if hook is explicitly disabled.
        if ( $this->is_hook_active( $hook, $this->get_callable_name( $callback ) ) ) {
            return false;
        }

        return add_action( $hook, $callback, $priority, $accepted_args );
    }

    /**
     * Adds the $callback to the filter $hook, if the hook is not marked
     * as inactive by the module's options.
     */
    protected function add_filter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ) : bool {
        // Bail early if hook is explicitly disabled.
        if ( $this->is_hook_active( $hook, $this->get_callable_name( $callback ) ) ) {
            return false;
        }

        return add_filter( $hook, $callback, $priority, $accepted_args );
    }

    /**
     * Returns the "callable name" for the given callable value.
     *
     * This method differs from the "callable name" of {@see \is_callable}
     * where in Closures and invokables are not supported and
     * where in a callable of this class' name is omitted.
     *
     * Based on {@see \_wp_filter_build_unique_id()}
     */
    protected function get_callable_name( callable $callback ) : ?string
    {
        if ( is_string( $callback ) ) {
            return $callback;
        }

        if ( ! is_array( $callback ) ) {
            return null;
        }

        if ( $this === $callback[0] ) {
            return $callback[1];
        }

        if ( is_callable( $callback, false, $callable_name ) ) {
            return $callable_name;
        }

        return null;
    }

    /**
     * Determines if the $hook and $callback are active.
     *
     * @param string  $hook          The hook name.
     * @param ?string $callable_name The "callable name" refers to the function
     *     name, method name, or static class and method.
     */
    protected function is_hook_active( string $hook, ?string $callable_name = null ) : bool
    {
        if ( $callable_name ) {
            if (
                isset( $this->options['hooks'][$hook][$callable_name] ) &&
                false === $this->options['hooks'][$hook][$callable_name]
            ) {
                return false;
            }
        }

        if (
            isset( $this->options['hooks'][$hook] ) &&
            false === $this->options['hooks'][$hook]
        ) {
            return false;
        }

        return true;
    }

    /**
     * Declared as protected to prevent creating a new instance
     * outside of static creator methods.
     */
    protected function __construct()
    {
        // Do nothing.
    }

    /**
     * Declared as private to prevent cloning of the instance.
     */
    final private function __clone()
    {
        // Disallow.
    }
}
