<?php

declare(strict_types=1);

namespace Ecocide\Exceptions;

/**
 * The identifier of a valid module was expected.
 */
class UnknownModuleException extends \InvalidArgumentException
{
    /**
     * @param string $id The unknown module identifier.
     */
    public function __construct( $id )
    {
        parent::__construct( sprintf( 'Module "%s" is not defined.', $id ) );
    }
}
