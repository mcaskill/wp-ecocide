<?php

/*
 * This file is part of Ecocide.
 *
 * © Chauncey McAskill <chauncey@mcaskill.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ecocide\Contracts\Modules;

interface Module
{
    /**
     * Boots the class by running `add_action()` and `add_filter()` calls.
     *
     * @access public
     * @param  array $args An array of optional arguments to customize the module.
     * @return void
     */
    public function boot( array $args = [] );

    /**
     * Returns the instance of the class.
     *
     * @access public
     * @return static
     */
    public static function get_instance();
}
