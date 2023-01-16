<?php

declare(strict_types=1);

namespace Ecocide;

use Ecocide\Contracts\Modules\Module;
use Ecocide\Exceptions\UnknownModuleException;

/**
 * Ecocide Container
 */
class Modules
{
    /**
     * Map of module instances.
     *
     * @var array<string, Module>
     */
    protected $modules = [];

    /**
     * The cache of studly-cased words.
     *
     * @var array
     */
    protected static $studlyCache = [];

    /**
     * @param  string $id The module identifier.
     * @return Module
     */
    public function get( string $id ) : Module
    {
        if ( isset( $this->modules[ $id ] ) ) {
            return $this->modules;
        }

        return $this->modules[ $id ] = $this->find( $id );
    }

    /**
     * @param  string $id The module identifier.
     * @return Module
     * @tbrows UnknownModuleException If the module identifier is not found.
     */
    protected function find( string $id ) : Module
    {
        $name  = self::studly( $id );
        $class = 'Ecocide\\Modules\\' . $name . '\\Module';

        if ( ! class_exists( $class ) ) {
            $path = __DIR__ . 'Modules/' . $name . '/Module.php';

            if ( ! file_exists( $path ) ) {
                throw new UnknownModuleException( $id );
            }

            require_once $path;
        }

        return $class::get_instance();
    }

    /**
     * Convert a value to studly caps case.
     *
     * @param  string $input The input string.
     * @return string Returns the studly-cased string.
     */
    protected function studly( string $input ) : string
    {
        $key = $input;

        if ( isset( static::$studlyCache[$key] ) ) {
            return static::$studlyCache[$key];
        }

        $input = ucwords( str_replace( [ '-', '_' ], ' ', $input ) );

        return static::$studlyCache[$key] = str_replace( ' ', '', $input );
    }
}
