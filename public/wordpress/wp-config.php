<?php
/**
 * WordPress Configuration File
 * Place this in: public/wordpress/wp-config.php
 * 
 * This config routes WordPress to work from /wordpress/ subfolder
 * while keeping Laravel at the root
 */

// Database Settings (Update with your DB credentials)
define('DB_NAME', 'aeos365_landing');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// WordPress URLs (Clean URLs - WordPress hidden in /wordpress/ subfolder)
define('WP_HOME', 'http://aeos365.test');              // Public URL visible to users (clean!)
define('WP_SITEURL', 'http://aeos365.test/wordpress'); // Actual WordPress location

// Content directory (WordPress assets stored in /wordpress/wp-content/)
define('WP_CONTENT_URL', 'http://aeos365.test/wordpress/wp-content');
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');

/**
 * Authentication Unique Keys and Salts.
 */
define('AUTH_KEY',         'aeros365#!wordpress@secure_key_auth_2024');
define('SECURE_AUTH_KEY',  'aeros365#!wordpress@secure_key_secure_auth_2024');
define('LOGGED_IN_KEY',    'aeros365#!wordpress@secure_key_logged_in_2024');
define('NONCE_KEY',        'aeros365#!wordpress@secure_key_nonce_2024');
define('AUTH_SALT',        'aeros365#!wordpress@secure_key_auth_salt_2024');
define('SECURE_AUTH_SALT', 'aeros365#!wordpress@secure_key_secure_auth_salt_2024');
define('LOGGED_IN_SALT',   'aeros365#!wordpress@secure_key_logged_in_salt_2024');
define('NONCE_SALT',       'aeros365#!wordpress@secure_key_nonce_salt_2024');

/**
 * WordPress Database Table prefix.
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 */
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

/**
 * Force HTTPS (Uncomment when on production)
 */
// define('FORCE_SSL_ADMIN', true);
// define('FORCE_SSL_LOGIN', true);

/**
 * Memory Limit
 */
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

/**
 * Disable gutenberg if you prefer classic editor
 */
// define('GUTENBERG_PHP_VERSION', PHP_VERSION);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
