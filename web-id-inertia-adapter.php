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

if (!defined('WPINC')) {
    die;
}

define('WEBID_INERTIA_WORDPRESS_DIR', plugin_dir_path(__FILE__));

if (!defined('INERTIA_SSR_URL')) {
    define('INERTIA_SSR_URL', 'http://127.0.0.1:13714/render');
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/src/functions.php';
