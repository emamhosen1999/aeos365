<?php
/**
 * Front to the WordPress application.
 * 
 * This file doesn't do anything, but loads wp-blog-header.php 
 * which does and tells WordPress that we are coming from the front end.
 */

// Ensure WordPress is loaded
define('WP_USE_THEMES', true);

require __DIR__ . '/wp-blog-header.php';
