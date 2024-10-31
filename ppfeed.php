<?php

/*
 * Plugin Name: PP.lv feed for WooCommerce
 * Author: PP.lv
 * Author URI: https://pp.lv
 * Version: 1.0.6
 * Description: Adds rest api endpoint used to export products to PP.lv
 */

if ( !function_exists( 'add_action' ) ) {
    echo 'Hi :-)';
    exit;
}

define('PPFEED__PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('PPFEED__PLUGIN_BASENAME', plugin_basename( __FILE__ ));
require_once( PPFEED__PLUGIN_DIR . 'Pplvfeed.php' );
add_action( 'init', ['Pplvfeed', 'init']);
add_action( 'admin_menu', ['Pplvfeed', 'initSettings']);
