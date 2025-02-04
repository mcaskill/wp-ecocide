<?php

declare(strict_types=1);

/**
 * @wordpress-plugin
 *
 * Plugin Name:  Ecocide
 * Plugin URI:   https://github.com/mcaskill/wp-ecocide
 * Description:  A collection of modules to clean up or disable features in WordPress.
 * Version:      1.0.0-alpha
 * Author:       Chauncey McAskill
 * Author URI:   https://mcaskill.ca
 * License:      MIT License
 */

if ( ! is_blog_installed() ) {
    return;
}

if ( ! class_exists( 'Ecocide\\Contracts\\Modules\\Module' ) ) {
    require __DIR__ . '/src/Contracts/Modules/Module.php';
}

if ( ! class_exists( 'Ecocide\\Exceptions\\UnknownModuleException' ) ) {
    require __DIR__ . '/src/Exceptions/UnknownModuleException.php';
}

if ( ! class_exists( 'Ecocide\\Modules' ) ) {
    require __DIR__ . '/src/Modules.php';
}
