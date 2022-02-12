<?php

/**
 * Plugin Name:       BoxyBird Inertia Adapter
 * Plugin URI:        #
 * Description:       A WordPress adapter for Inertia.js.
 * Version:           0.5.6
 * Author:            Andrew Rhyand
 * Author URI:        andrewrhyand.com
 * License:           GPLv2 or later
*  License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bb-inertia
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('BOXYBIRD_INERTIA_ADAPTER', '0.5.6');

/**
 * Composer autoload
 */
if (!file_exists($autoload = __DIR__ . '/vendor/autoload.php')) {
    die('BoxyBird Inertia Adapter plugin requires to you run "composer install"');
}

require_once $autoload;
