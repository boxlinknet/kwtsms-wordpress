<?php
/**
 * Bootstrap for Tier 2 integration tests (WP_UnitTestCase + real DB).
 *
 * Requires Docker wp_db container running on 127.0.0.1:3306.
 * Run with: vendor/bin/phpunit --configuration phpunit-integration.xml
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

// Point wp-phpunit at our config file.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$_wp_phpunit = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
require_once $_wp_phpunit . '/includes/functions.php';

/**
 * Load the plugin before WordPress sets up.
 */
function _kwtsms_load_plugin() {
    require dirname( __DIR__, 2 ) . '/wp-kwtsms.php';
}
tests_add_filter( 'muplugins_loaded', '_kwtsms_load_plugin' );

// Boot WordPress test environment (installs tables, etc.).
require $_wp_phpunit . '/includes/bootstrap.php';
