<?php
/**
 * Plugin Name: CookieChimp
 * Plugin URI:  https://cookiechimp.com/
 * Description: Adds the CookieChimp consent-management widget to the start of the website head.
 * Version:     1.0.3
 * Author:      Identity Square
 * Author URI:  https://identitysquare.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cookiechimp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COOKIECHIMP_PLUGIN_VERSION', '1.0.3' );

add_action( 'admin_menu', 'cookiechimp_create_menu' );
add_action( 'admin_init', 'cookiechimp_register_settings' );
add_action( 'admin_notices', 'cookiechimp_dependency_notice' );
add_action( 'wp_head', 'cookiechimp_insert_js', -PHP_INT_MAX - 1 );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cookiechimp_settings_link' );

/**
 * Add the CookieChimp settings page.
 */
function cookiechimp_create_menu() {
	add_options_page(
		esc_html__( 'CookieChimp Settings', 'cookiechimp' ),
		esc_html__( 'CookieChimp', 'cookiechimp' ),
		'manage_options',
		'cookiechimp',
		'cookiechimp_settings_page'
	);
}

/**
 * Register the CookieChimp Account ID setting.
 */
function cookiechimp_register_settings() {
	register_setting(
		'cookiechimp-settings-group',
		'cookiechimp_account_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'cookiechimp_sanitize_account_id',
			'default'           => '',
		)
	);
}

/**
 * Validate an Account ID before it is stored.
 *
 * CookieChimp Account IDs are case-sensitive alphanumeric identifiers. Rejecting
 * path characters also ensures that the setting cannot alter the widget URL.
 *
 * @param mixed $value Submitted setting value.
 * @return string Sanitized Account ID, or the previously saved value when invalid.
 */
function cookiechimp_sanitize_account_id( $value ) {
	if ( is_scalar( $value ) ) {
		$account_id = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );

		if ( '' === $account_id || preg_match( '/\A[A-Za-z0-9]+\z/', $account_id ) ) {
			return $account_id;
		}
	}

	add_settings_error(
		'cookiechimp_account_id',
		'cookiechimp_invalid_account_id',
		esc_html__( 'The CookieChimp Account ID can contain only letters and numbers.', 'cookiechimp' )
	);

	return (string) get_option( 'cookiechimp_account_id', '' );
}

/**
 * Display the CookieChimp settings page.
 */
function cookiechimp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage CookieChimp settings.', 'cookiechimp' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CookieChimp Settings', 'cookiechimp' ); ?></h1>
		<p>
			<?php esc_html_e( 'To get started, sign up for a CookieChimp account at', 'cookiechimp' ); ?>
			<a href="<?php echo esc_url( 'https://cookiechimp.com/' ); ?>" target="_blank" rel="noopener noreferrer">CookieChimp.com</a>.
			<?php esc_html_e( 'Once you have an account, enter your CookieChimp Account ID below.', 'cookiechimp' ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'cookiechimp-settings-group' ); ?>
			<?php do_settings_sections( 'cookiechimp-settings-group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cookiechimp_account_id"><?php esc_html_e( 'CookieChimp Account ID', 'cookiechimp' ); ?></label></th>
					<td><input type="text" id="cookiechimp_account_id" name="cookiechimp_account_id" value="<?php echo esc_attr( get_option( 'cookiechimp_account_id', '' ) ); ?>" class="regular-text" pattern="[A-Za-z0-9]+" autocomplete="off" placeholder="<?php esc_attr_e( 'Enter your CookieChimp Account ID', 'cookiechimp' ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'WP Consent API', 'cookiechimp' ); ?></h2>
		<p>
			<?php esc_html_e( 'CookieChimp recommends installing and activating the WP Consent API plugin for full functionality.', 'cookiechimp' ); ?>
			<a href="<?php echo esc_url( 'https://wordpress.org/plugins/wp-consent-api/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Install WP Consent API', 'cookiechimp' ); ?></a>.
		</p>
	</div>
	<?php
}

/**
 * Output the CookieChimp widget at the earliest wp_head priority.
 *
 * The script is registered with WordPress and then printed immediately. Waiting
 * for the normal script queue would be too late for CookieChimp to intercept
 * scripts that other wp_head callbacks print first.
 */
function cookiechimp_insert_js() {
	$cookiechimp_account_id = get_option( 'cookiechimp_account_id', '' );

	if ( ! is_string( $cookiechimp_account_id ) || ! preg_match( '/\A[A-Za-z0-9]+\z/', $cookiechimp_account_id ) ) {
		return;
	}

	$script_url = 'https://cookiechimp.com/widget/' . rawurlencode( $cookiechimp_account_id ) . '.js';

	wp_enqueue_script(
		'cookiechimp-widget',
		esc_url_raw( $script_url ),
		array(),
		COOKIECHIMP_PLUGIN_VERSION,
		false
	);

	// Print only this dependency-free handle without firing the global print hook.
	$cookiechimp_scripts = wp_scripts();
	$cookiechimp_scripts->do_items( 'cookiechimp-widget' );
}

/**
 * Display an admin notice if WP Consent API is not active.
 */
function cookiechimp_dependency_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( 'wp-consent-api/wp-consent-api.php' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'CookieChimp recommends installing the WP Consent API plugin for full functionality. Please configure the', 'cookiechimp' ); ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cookiechimp' ) ); ?>"><?php esc_html_e( 'CookieChimp settings', 'cookiechimp' ); ?></a>
			<?php esc_html_e( 'and', 'cookiechimp' ); ?>
			<a href="<?php echo esc_url( 'https://wordpress.org/plugins/wp-consent-api/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'install WP Consent API', 'cookiechimp' ); ?></a>.
		</p>
	</div>
	<?php
}

/**
 * Add a settings link to the Plugins screen.
 *
 * @param string[] $links Existing plugin action links.
 * @return string[] Updated plugin action links.
 */
function cookiechimp_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=cookiechimp' ) ) . '">' . esc_html__( 'Settings', 'cookiechimp' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}
