<?php
/**
 * WordPress integration test configuration.
 * Points to the wordpress_test database in the Docker wp_db container.
 * Used by wp-phpunit/wp-phpunit via WP_PHPUNIT__TESTS_CONFIG env var.
 */

define( 'DB_NAME',     'wordpress_test' );
define( 'DB_USER',     'wordpress' );
define( 'DB_PASSWORD', 'wordpress_password' );
define( 'DB_HOST',     '127.0.0.1:3306' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  'Test Blog' );
define( 'WP_PHP_BINARY',   'php' );
define( 'WPLANG', '' );

$table_prefix = 'wptests_';
