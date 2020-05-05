<?php

/**
 * Plugin Name:       BoxyBird Inertia Adapter
 * Plugin URI:        #
 * Description:       A WordPress adapter for Inertia.js.
 * Version:           1.0.0
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
 * Currently plugin version.
 */
define('BOXYBIRD_INERTIA_ADAPTER', '1.0.0');

/**
 * Helper functions
 */
require_once __DIR__ . '/src/functions.php';

/**
 * Composer autoload
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * WordPress hooks
 */
BoxyBird\Inertia\Hooks::init();
