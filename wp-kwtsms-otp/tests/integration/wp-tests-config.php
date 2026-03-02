<?php
/**
 * WordPress integration test configuration.
 * Points to the wordpress_test database in the Docker wp_db container.
 * Used by wp-phpunit/wp-phpunit via WP_PHPUNIT__TESTS_CONFIG env var.
 *
 * DB_HOST is resolved at runtime:
 *   - Inside the Docker network: use 'db' (the service name).
 *   - From the host: use the Docker network gateway IP (172.18.0.2).
 *
 * ABSPATH must point to a WordPress installation so the test bootstrap can
 * load wp-settings.php during table installation. When running inside the
 * wp_site container, /var/www/html is the WP root.
 */

define( 'DB_NAME',     'wordpress_test' );
define( 'DB_USER',     'wordpress' );
define( 'DB_PASSWORD', 'wordpress_password' );

// Resolve DB host: prefer WORDPRESS_DB_HOST env var (set in Docker), fall back
// to the Docker bridge IP so tests can also run from within the wp_site container.
$_kwtsms_db_host = getenv( 'WORDPRESS_DB_HOST' ) ?: 'db';
define( 'DB_HOST',    $_kwtsms_db_host );
unset( $_kwtsms_db_host );

define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

// ABSPATH: WP root needed by install.php when it calls wp-settings.php.
// Inside the wp_site container the WordPress root is /var/www/html.
if ( ! defined( 'ABSPATH' ) ) {
	$_kwtsms_abspath = getenv( 'WP_ABSPATH' );
	if ( ! $_kwtsms_abspath ) {
		// Detect: if /var/www/html/wp-settings.php exists, we're inside the container.
		$_kwtsms_abspath = file_exists( '/var/www/html/wp-settings.php' )
			? '/var/www/html/'
			: dirname( __DIR__, 3 ) . '/';
	}
	define( 'ABSPATH', rtrim( $_kwtsms_abspath, '/' ) . '/' );
	unset( $_kwtsms_abspath );
}

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  'Test Blog' );
define( 'WP_PHP_BINARY',   'php' );
define( 'WPLANG', '' );

$table_prefix = 'wptests_';
