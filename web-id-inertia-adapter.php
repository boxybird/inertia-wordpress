<?php

/**
 * Plugin Name:       Web^ID Inertia Adapter
 * Plugin URI:        #
 * Description:       A WordPress adapter for Inertia.js.
 * Version:           1.0.0
 * Author:            Web^ID
 * Author URI:        web-id.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       web-id-inertia
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!defined('INERTIA_SSR_URL')) {
    define('INERTIA_SSR_URL', 'http://127.0.0.1:13714/render');
}

require_once __DIR__ . '/vendor/autoload.php';
