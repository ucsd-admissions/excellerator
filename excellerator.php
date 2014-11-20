<?php
/**
 * Plugin Name: Excellerator
 * Description: Excellerator allows you to easily upload .xls, .xlsx, and .xlsm documents, match columns to post properties or metadata, and create or update posts.
 * Version: 0.0.1
 * Author: Tom Borger <tborger@ucsd.edu>
 * License: Three-Clause BSD
 */

// Main plugin class
include_once( 'class-Excellerator.php' );

// Activation: Update rewrite rules and build list of records.
register_activation_hook( __FILE__, array( 'Excellerator', 'register_uniqid_table' ) );

add_action( 'admin_enqueue_scripts', array( 'Excellerator', 'register_scripts_and_styles' ) );
add_action( 'init', array( 'Excellerator', 'register_post_type' ) );
add_action( 'init', array( 'Excellerator', 'register_taxonomy' ) );