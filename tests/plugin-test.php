<?php
/**
 * Minimal regression tests for the single-file plugin.
 *
 * These tests intentionally stub only the WordPress functions used while the
 * plugin loads or while the functions under test execute.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );

$cookiechimp_test_actions         = array();
$cookiechimp_test_filters         = array();
$cookiechimp_test_options         = array();
$cookiechimp_test_settings_errors = array();
$cookiechimp_test_scripts         = array();
$cookiechimp_test_printed_scripts = array();

function add_action( $hook, $callback, $priority = 10 ) {
	global $cookiechimp_test_actions;
	$cookiechimp_test_actions[ $hook ][] = array( $callback, $priority );
}

function add_filter( $hook, $callback, $priority = 10 ) {
	global $cookiechimp_test_filters;
	$cookiechimp_test_filters[ $hook ][] = array( $callback, $priority );
}

function plugin_basename( $file ) {
	return basename( $file );
}

function get_option( $name, $default = false ) {
	global $cookiechimp_test_options;
	return array_key_exists( $name, $cookiechimp_test_options ) ? $cookiechimp_test_options[ $name ] : $default;
}

function wp_unslash( $value ) {
	return stripslashes( $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}

function add_settings_error( $setting, $code, $message ) {
	global $cookiechimp_test_settings_errors;
	$cookiechimp_test_settings_errors[] = array( $setting, $code, $message );
}

function esc_html__( $text ) {
	return $text;
}

function esc_url( $url ) {
	return $url;
}

function esc_url_raw( $url ) {
	return $url;
}

function wp_enqueue_script( $handle, $src, $dependencies, $version, $in_footer ) {
	global $cookiechimp_test_scripts;
	$cookiechimp_test_scripts[ $handle ] = array( $src, $dependencies, $version, $in_footer );
}

function wp_print_scripts( $handle ) {
	global $cookiechimp_test_printed_scripts;
	$cookiechimp_test_printed_scripts[] = $handle;
}

require dirname( __DIR__ ) . '/cookiechimp.php';

/**
 * Fail the test process when a condition is false.
 *
 * @param bool   $condition Condition to test.
 * @param string $message Failure message.
 */
function cookiechimp_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

cookiechimp_test_assert( isset( $cookiechimp_test_actions['admin_init'] ), 'The setting must be registered on admin_init.' );
cookiechimp_test_assert( 'cookiechimp_register_settings' === $cookiechimp_test_actions['admin_init'][0][0], 'admin_init must register the CookieChimp setting.' );
cookiechimp_test_assert( isset( $cookiechimp_test_actions['wp_head'] ), 'The widget must be attached to wp_head.' );
cookiechimp_test_assert( 'cookiechimp_insert_js' === $cookiechimp_test_actions['wp_head'][0][0], 'wp_head must call the CookieChimp widget renderer.' );
cookiechimp_test_assert( PHP_INT_MIN === $cookiechimp_test_actions['wp_head'][0][1], 'The widget must use the earliest integer hook priority.' );

$cookiechimp_test_options['cookiechimp_account_id'] = 'OldAccount123';
cookiechimp_test_assert( 'ValidAccount234' === cookiechimp_sanitize_account_id( ' ValidAccount234 ' ), 'Valid alphanumeric Account IDs should be normalized and saved.' );
cookiechimp_test_assert( '' === cookiechimp_sanitize_account_id( '' ), 'An empty Account ID should disable the widget.' );
cookiechimp_test_assert( 'OldAccount123' === cookiechimp_sanitize_account_id( '../invalid' ), 'An invalid Account ID should not replace the saved value.' );
cookiechimp_test_assert( 'OldAccount123' === cookiechimp_sanitize_account_id( array( 'invalid' ) ), 'A non-scalar Account ID should not replace the saved value.' );
cookiechimp_test_assert( 2 === count( $cookiechimp_test_settings_errors ), 'Each invalid Account ID should add one settings error.' );

$cookiechimp_test_options['cookiechimp_account_id'] = '';
ob_start();
cookiechimp_insert_js();
$output = ob_get_clean();
cookiechimp_test_assert( '' === $output, 'No widget should be printed without an Account ID.' );

$cookiechimp_test_options['cookiechimp_account_id'] = '../invalid';
ob_start();
cookiechimp_insert_js();
$output = ob_get_clean();
cookiechimp_test_assert( '' === $output, 'No widget should be printed for an invalid Account ID.' );

$cookiechimp_test_options['cookiechimp_account_id'] = 'AbC234';
ob_start();
cookiechimp_insert_js();
$output = ob_get_clean();
cookiechimp_test_assert( '' === $output, 'The plugin should let WordPress render the script tag.' );
cookiechimp_test_assert( isset( $cookiechimp_test_scripts['cookiechimp-widget'] ), 'The widget should be enqueued with a unique handle.' );
cookiechimp_test_assert( 'https://cookiechimp.com/widget/AbC234.js' === $cookiechimp_test_scripts['cookiechimp-widget'][0], 'The widget should use the exact account-specific CookieChimp URL.' );
cookiechimp_test_assert( array() === $cookiechimp_test_scripts['cookiechimp-widget'][1], 'The widget should have no script dependencies.' );
cookiechimp_test_assert( null === $cookiechimp_test_scripts['cookiechimp-widget'][2], 'The dynamic widget URL should not receive a version query parameter.' );
cookiechimp_test_assert( false === $cookiechimp_test_scripts['cookiechimp-widget'][3], 'The widget must be registered as a head script.' );
cookiechimp_test_assert( array( 'cookiechimp-widget' ) === $cookiechimp_test_printed_scripts, 'Only the CookieChimp handle should be printed immediately.' );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/cookiechimp.php' );
$readme_source = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
preg_match( '/^ \* Version:\s+([0-9.]+)$/m', $plugin_source, $plugin_version );
preg_match( '/^Stable tag:\s+([0-9.]+)$/mi', $readme_source, $stable_tag );
cookiechimp_test_assert( ! empty( $plugin_version[1] ), 'The plugin header version should be readable.' );
cookiechimp_test_assert( ! empty( $stable_tag[1] ), 'The readme stable tag should be readable.' );
cookiechimp_test_assert( $plugin_version[1] === $stable_tag[1], 'The plugin version and stable tag must match.' );

fwrite( STDOUT, "CookieChimp plugin tests passed.\n" );
