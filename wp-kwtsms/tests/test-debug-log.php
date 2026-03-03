<?php
/**
 * Tests for KwtSMS_API debug logging — write_debug_log().
 *
 * Covers:
 *  - DEBUG_LOG_MAX_BYTES constant equals 1 MiB (1 048 576 bytes)
 *  - debug log writes to file when debug_mode=true and WP_CONTENT_DIR is defined
 *  - debug log skips when debug_mode=false (no file write)
 *  - API password NEVER appears in logged output
 *  - Log rotation: when file reaches DEBUG_LOG_MAX_BYTES, old file is renamed
 *  - Log path is WP_CONTENT_DIR . '/kwtsms-debug.log'
 *
 * Strategy:
 *  WP_CONTENT_DIR is a PHP constant and can only be defined once per process.
 *  We define it in setUpBeforeClass() to a stable temp directory that persists
 *  for the lifetime of the test class, and we clean up individual log files
 *  (not the directory) in tearDown() so later tests start with a clean slate.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_DebugLog
 */
class Test_KwtSMS_DebugLog extends TestCase {

	/**
	 * Stable temp directory used as WP_CONTENT_DIR for the whole test class.
	 * Set once in setUpBeforeClass() and never changed.
	 *
	 * @var string
	 */
	private static $content_dir = '';

	/**
	 * Absolute path to the debug log inside $content_dir.
	 *
	 * @var string
	 */
	private static $log_path = '';

	// =========================================================================
	// Class-level setup / teardown
	// =========================================================================

	/**
	 * Create the temp directory and define WP_CONTENT_DIR exactly once.
	 *
	 * PHP constants cannot be redefined; calling this only once for the entire
	 * test class avoids the "already defined" problem.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Use a deterministic subdir inside sys_get_temp_dir() so we can clean it.
		self::$content_dir = sys_get_temp_dir() . '/kwtsms_debuglog_tests';
		if ( ! is_dir( self::$content_dir ) ) {
			mkdir( self::$content_dir, 0777, true );
		}

		self::$log_path = self::$content_dir . '/kwtsms-debug.log';

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', self::$content_dir );
		}
	}

	/**
	 * Remove the temp directory and all its contents after all tests in this
	 * class have finished.
	 */
	public static function tearDownAfterClass(): void {
		foreach ( glob( self::$content_dir . '/kwtsms-debug*' ) as $file ) {
			@unlink( $file );
		}
		@rmdir( self::$content_dir );
		parent::tearDownAfterClass();
	}

	// =========================================================================
	// Per-test setup / teardown
	// =========================================================================

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Remove any log files left by the previous test so each test starts clean.
		foreach ( glob( self::$content_dir . '/kwtsms-debug*' ) as $file ) {
			@unlink( $file );
		}

		// wp_json_encode used by request() when building the payload log line.
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Internal helper
	// =========================================================================

	/**
	 * Create a KwtSMS_API instance with the given debug_mode flag.
	 *
	 * Mocks the WP options functions that the API class constructor path may
	 * trigger (e.g. via append_send_log / append_attempt_log).
	 *
	 * @param bool   $debug_mode
	 * @param string $username   API username (default 'testuser').
	 * @param string $password   API password (default 'S3cr3tP@ssword!').
	 * @return KwtSMS_API
	 */
	private function make_api( $debug_mode, $username = 'testuser', $password = 'S3cr3tP@ssword!' ) {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		return new KwtSMS_API( $username, $password, false, $debug_mode );
	}

	// =========================================================================
	// Constant
	// =========================================================================

	public function test_debug_log_max_bytes_constant_equals_one_mib() {
		$this->assertSame( 1048576, KwtSMS_API::DEBUG_LOG_MAX_BYTES );
	}

	// =========================================================================
	// debug_mode = false → no file write
	// =========================================================================

	public function test_write_debug_log_does_not_write_when_debug_mode_disabled() {
		$api = $this->make_api( false );

		$api->write_debug_log( 'test_context', 'This message must NOT be written.' );

		$this->assertFileDoesNotExist(
			self::$log_path,
			'write_debug_log() must not create a log file when debug_mode=false.'
		);
	}

	// =========================================================================
	// debug_mode = true → file is written
	// =========================================================================

	public function test_write_debug_log_creates_file_when_debug_mode_enabled() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'test_context', 'Hello debug log.' );

		$this->assertFileExists( self::$log_path );
	}

	public function test_write_debug_log_appends_context_to_file() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'my_function', 'Some log entry.' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( 'my_function', $content );
	}

	public function test_write_debug_log_appends_message_to_file() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'ctx', 'Unique test message 8675309.' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( 'Unique test message 8675309.', $content );
	}

	public function test_write_debug_log_includes_kwtsms_otp_tag() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'ctx', 'Any message.' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( '[kwtsms-otp]', $content );
	}

	public function test_write_debug_log_includes_timestamp() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'ctx', 'Timestamped entry.' );

		$content = file_get_contents( self::$log_path );
		// Timestamp format: [YYYY-MM-DD HH:MM:SS]
		$this->assertMatchesRegularExpression(
			'/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/',
			$content
		);
	}

	public function test_write_debug_log_uses_file_append_mode() {
		$api = $this->make_api( true );

		$api->write_debug_log( 'ctx', 'First entry.' );
		$api->write_debug_log( 'ctx', 'Second entry.' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( 'First entry.', $content );
		$this->assertStringContainsString( 'Second entry.', $content );
	}

	// =========================================================================
	// Password never in log output
	// =========================================================================

	/**
	 * The API password must NEVER appear in any debug log output.
	 *
	 * KwtSMS_API::request() logs only the endpoint-specific $payload (which
	 * never contains credentials), not the merged $body (which does).  This
	 * test verifies that write_debug_log() itself does not expose credentials
	 * when called with a normal context/message pair.
	 */
	public function test_password_never_appears_in_debug_log() {
		$password = 'S3cr3tP@ssword!';
		$api      = $this->make_api( true, 'testuser', $password );

		$api->write_debug_log( 'send_sms()', 'type=login phone=96598765432 sender=KWTSMS' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringNotContainsString(
			$password,
			$content,
			'API password must never appear in the debug log.'
		);
	}

	public function test_password_not_logged_when_multiple_entries_written() {
		$password = 'AnotherSecret#99';
		$api      = $this->make_api( true, 'user2', $password );

		// Simulate what request() actually logs — only the payload, NOT the merged body.
		$api->write_debug_log( 'request(send/)', 'POST https://www.kwtsms.com/API/send/ payload={"sender":"KWTSMS","mobile":"96598765432","message":"Your code: 123456"}' );
		$api->write_debug_log( 'send_sms()', 'SUCCESS: msg-id=MSG12345' );

		$content = file_get_contents( self::$log_path );
		$this->assertStringNotContainsString(
			$password,
			$content,
			'Password must not leak into any logged entry.'
		);
	}

	// =========================================================================
	// Log rotation
	// =========================================================================

	public function test_log_rotation_renames_file_when_size_limit_reached() {
		// Pre-create a log file exactly at the rotation threshold.
		$filler = str_repeat( 'X', KwtSMS_API::DEBUG_LOG_MAX_BYTES );
		file_put_contents( self::$log_path, $filler );

		$api = $this->make_api( true );
		$api->write_debug_log( 'ctx', 'Trigger rotation.' );

		$this->assertFileExists(
			self::$log_path . '.1',
			'Log file must be rotated to .1 when it reaches the size limit.'
		);
	}

	public function test_log_rotation_writes_new_entry_to_fresh_log_after_rotation() {
		// Pre-create an oversized log file to force rotation.
		$filler = str_repeat( 'Y', KwtSMS_API::DEBUG_LOG_MAX_BYTES );
		file_put_contents( self::$log_path, $filler );

		$api = $this->make_api( true );
		$api->write_debug_log( 'ctx', 'Post-rotation entry.' );

		// New main log file must contain the new entry (not the old filler).
		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( 'Post-rotation entry.', $content );
		$this->assertStringNotContainsString( 'YYYYYY', $content );
	}

	public function test_log_does_not_rotate_when_file_is_below_limit() {
		// Pre-create a small log file (well below the limit).
		file_put_contents( self::$log_path, "Previous small entry\n" );

		$api = $this->make_api( true );
		$api->write_debug_log( 'ctx', 'New entry.' );

		$this->assertFileDoesNotExist(
			self::$log_path . '.1',
			'Log must not rotate when the file is below the size limit.'
		);

		// Both entries should be in the same file.
		$content = file_get_contents( self::$log_path );
		$this->assertStringContainsString( 'Previous small entry', $content );
		$this->assertStringContainsString( 'New entry.', $content );
	}

	// =========================================================================
	// Source-level assertions (belt-and-suspenders)
	// =========================================================================

	public function test_debug_log_rotation_source_uses_rename() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-api.php' );
		$this->assertStringContainsString( 'rename(', $source );
	}

	public function test_debug_log_rotation_source_checks_debug_log_max_bytes() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-api.php' );
		$this->assertStringContainsString( 'DEBUG_LOG_MAX_BYTES', $source );
	}

	public function test_write_debug_log_uses_wp_content_dir_constant() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-api.php' );
		$this->assertStringContainsString( 'WP_CONTENT_DIR', $source );
	}

	public function test_write_debug_log_source_guards_on_debug_mode() {
		// The method must check $this->debug_mode before writing anything.
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-api.php' );
		$this->assertStringContainsString( '$this->debug_mode', $source );
	}
}
