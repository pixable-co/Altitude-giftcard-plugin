<?php
/**
 * Plugin Name: PX Custom Gift Cards
 * Description: Provides a shortcode form for adding gift card product to the cart with selected redeemable product.
 * Version: 1.0
 * Author: Pixable
 */

if (!defined('ABSPATH'))
    exit;

define('PXGC_PATH', plugin_dir_path(__FILE__));
define('PXGC_URL', plugin_dir_url(__FILE__));

/**
 * Recursively load all PHP files inside the plugin folder
 */
function pxgc_require_all($dir)
{

    // Scan the folder
    $files = scandir($dir);

    foreach ($files as $file) {

        // Skip system files
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $file;

        // If directory, recurse
        if (is_dir($path)) {
            pxgc_require_all($path);
        }

        // If PHP file, include it (skip main plugin file)
        if (is_file($path) && substr($file, -4) === '.php' && $file !== basename(__FILE__)) {
            require_once $path;
        }
    }
}

// Load everything
pxgc_require_all(PXGC_PATH);
