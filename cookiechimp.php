<?php
/**
 * Plugin Name: CookieChimp
 * Plugin URI:  https://cookiechimp.com/
 * Description: Adds the CookieChimp consent-management widget to the start of the website head.
 * Version:     1.1.0
 * Author:      Identity Square
 * Author URI:  https://identitysquare.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cookiechimp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COOKIECHIMP_PLUGIN_VERSION', '1.1.0' );

add_action( 'admin_menu', 'cookiechimp_create_menu' );
add_action( 'admin_init', 'cookiechimp_register_settings' );
add_action( 'admin_notices', 'cookiechimp_dependency_notice' );
add_action( 'wp_head', 'cookiechimp_insert_js', -PHP_INT_MAX - 1 );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cookiechimp_settings_link' );
add_action( 'network_admin_menu', 'cookiechimp_create_network_menu' );
add_action( 'network_admin_edit_cookiechimp', 'cookiechimp_save_network_settings' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'cookiechimp_network_settings_link' );

/**
 * Check whether the plugin is network-activated on a multisite installation.
 *
 * When it is, the Account ID is managed in Network Admin and each site may
 * optionally override it. Otherwise every site manages its own Account ID.
 *
 * @return bool
 */
function cookiechimp_is_network_active() {
	if ( ! is_multisite() ) {
		return false;
	}

	$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

	return is_array( $network_plugins ) && isset( $network_plugins[ plugin_basename( __FILE__ ) ] );
}

/**
 * Check that a stored value is a usable CookieChimp Account ID.
 *
 * @param mixed $account_id Stored Account ID.
 * @return bool
 */
function cookiechimp_is_valid_account_id( $account_id ) {
	return is_string( $account_id ) && 1 === preg_match( '/\A[A-Za-z0-9]+\z/', $account_id );
}

/**
 * Get the network-wide Account ID.
 *
 * @return string Network Account ID, or an empty string when none is set.
 */
function cookiechimp_get_network_account_id() {
	$account_id = get_site_option( 'cookiechimp_network_account_id', '' );

	return cookiechimp_is_valid_account_id( $account_id ) ? $account_id : '';
}

/**
 * Get the Account ID that applies to the current site.
 *
 * A site-level Account ID always wins. When the plugin is network-activated,
 * sites without their own Account ID fall back to the network Account ID.
 *
 * @return string Account ID, or an empty string when none applies.
 */
function cookiechimp_get_account_id() {
	$account_id = get_option( 'cookiechimp_account_id', '' );

	if ( cookiechimp_is_valid_account_id( $account_id ) ) {
		return $account_id;
	}

	if ( cookiechimp_is_network_active() ) {
		return cookiechimp_get_network_account_id();
	}

	return '';
}

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
 * Normalize and validate a submitted Account ID.
 *
 * CookieChimp Account IDs are case-sensitive alphanumeric identifiers. Rejecting
 * path characters also ensures that the setting cannot alter the widget URL.
 *
 * Callers must pass an already-unslashed value; options.php does this for the
 * site setting. Unslashing again would strip characters that should be rejected.
 *
 * @param mixed $value Submitted, unslashed value.
 * @return string|null Normalized Account ID (possibly empty), or null when invalid.
 */
function cookiechimp_normalize_account_id( $value ) {
	if ( ! is_scalar( $value ) ) {
		return null;
	}

	$account_id = trim( sanitize_text_field( (string) $value ) );

	if ( '' === $account_id || cookiechimp_is_valid_account_id( $account_id ) ) {
		return $account_id;
	}

	return null;
}

/**
 * Validate a site Account ID before it is stored.
 *
 * @param mixed $value Submitted setting value.
 * @return string Sanitized Account ID, or the previously saved value when invalid.
 */
function cookiechimp_sanitize_account_id( $value ) {
	$account_id = cookiechimp_normalize_account_id( $value );

	if ( null !== $account_id ) {
		return $account_id;
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

	$network_active     = cookiechimp_is_network_active();
	$network_account_id = $network_active ? cookiechimp_get_network_account_id() : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CookieChimp Settings', 'cookiechimp' ); ?></h1>
		<?php if ( $network_active ) : ?>
			<p>
				<?php
				if ( '' !== $network_account_id ) {
					printf(
						/* translators: %s: Network-wide CookieChimp Account ID. */
						esc_html__( 'CookieChimp is managed for the whole network and this site uses the network Account ID %s by default.', 'cookiechimp' ),
						'<code>' . esc_html( $network_account_id ) . '</code>'
					);
				} else {
					esc_html_e( 'CookieChimp is managed for the whole network, but a network administrator has not set a network Account ID yet.', 'cookiechimp' );
				}
				?>
				<?php esc_html_e( 'To use a different CookieChimp account on this site only, enter its Account ID below. Leave the field empty to use the network Account ID.', 'cookiechimp' ); ?>
			</p>
		<?php else : ?>
			<p>
				<?php esc_html_e( 'To get started, sign up for a CookieChimp account at', 'cookiechimp' ); ?>
				<a href="<?php echo esc_url( 'https://cookiechimp.com/' ); ?>" target="_blank" rel="noopener noreferrer">CookieChimp.com</a>.
				<?php esc_html_e( 'Once you have an account, enter your CookieChimp Account ID below.', 'cookiechimp' ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cookiechimp-settings-group' ); ?>
			<?php do_settings_sections( 'cookiechimp-settings-group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cookiechimp_account_id">
							<?php
							if ( $network_active ) {
								esc_html_e( 'Site Account ID override', 'cookiechimp' );
							} else {
								esc_html_e( 'CookieChimp Account ID', 'cookiechimp' );
							}
							?>
						</label>
					</th>
					<td><input type="text" id="cookiechimp_account_id" name="cookiechimp_account_id" value="<?php echo esc_attr( get_option( 'cookiechimp_account_id', '' ) ); ?>" class="regular-text" pattern="[A-Za-z0-9]+" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $network_account_id ? $network_account_id : __( 'Enter your CookieChimp Account ID', 'cookiechimp' ) ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php cookiechimp_consent_api_section(); ?>
	</div>
	<?php
}

/**
 * Display the WP Consent API recommendation shown on the settings pages.
 */
function cookiechimp_consent_api_section() {
	?>
	<h2><?php esc_html_e( 'WP Consent API', 'cookiechimp' ); ?></h2>
	<p>
		<?php esc_html_e( 'CookieChimp recommends installing and activating the WP Consent API plugin for full functionality.', 'cookiechimp' ); ?>
		<a href="<?php echo esc_url( 'https://wordpress.org/plugins/wp-consent-api/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Install WP Consent API', 'cookiechimp' ); ?></a>.
	</p>
	<?php
}

/**
 * Add the CookieChimp settings page to Network Admin when network-activated.
 */
function cookiechimp_create_network_menu() {
	if ( ! cookiechimp_is_network_active() ) {
		return;
	}

	add_submenu_page(
		'settings.php',
		esc_html__( 'CookieChimp Settings', 'cookiechimp' ),
		esc_html__( 'CookieChimp', 'cookiechimp' ),
		'manage_network_options',
		'cookiechimp',
		'cookiechimp_network_settings_page'
	);
}

/**
 * Store a submitted network Account ID.
 *
 * @param mixed $value Submitted value.
 * @return bool True when the value was valid and stored.
 */
function cookiechimp_update_network_account_id( $value ) {
	$account_id = cookiechimp_normalize_account_id( $value );

	if ( null === $account_id ) {
		return false;
	}

	update_site_option( 'cookiechimp_network_account_id', $account_id );

	return true;
}

/**
 * Handle the Network Admin settings form.
 */
function cookiechimp_save_network_settings() {
	check_admin_referer( 'cookiechimp-network-settings' );

	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage CookieChimp settings.', 'cookiechimp' ) );
	}

	$saved = false;

	if ( isset( $_POST['cookiechimp_network_account_id'] ) && is_string( $_POST['cookiechimp_network_account_id'] ) ) {
		$saved = cookiechimp_update_network_account_id( sanitize_text_field( wp_unslash( $_POST['cookiechimp_network_account_id'] ) ) );
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'   => 'cookiechimp',
				'status' => $saved ? 'updated' : 'invalid',
			),
			network_admin_url( 'settings.php' )
		)
	);
	exit;
}

/**
 * Display the Network Admin settings page.
 */
function cookiechimp_network_settings_page() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage CookieChimp settings.', 'cookiechimp' ) );
	}

	// Display-only status flag set by cookiechimp_save_network_settings() after its nonce check.
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CookieChimp Network Settings', 'cookiechimp' ); ?></h1>
		<?php if ( 'updated' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'cookiechimp' ); ?></p></div>
		<?php elseif ( 'invalid' === $status ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The CookieChimp Account ID can contain only letters and numbers.', 'cookiechimp' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php esc_html_e( 'To get started, sign up for a CookieChimp account at', 'cookiechimp' ); ?>
			<a href="<?php echo esc_url( 'https://cookiechimp.com/' ); ?>" target="_blank" rel="noopener noreferrer">CookieChimp.com</a>.
			<?php esc_html_e( 'The Account ID below is used by every site in the network. Site administrators can override it for their own site under Settings, CookieChimp.', 'cookiechimp' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=cookiechimp' ) ); ?>">
			<?php wp_nonce_field( 'cookiechimp-network-settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cookiechimp_network_account_id"><?php esc_html_e( 'Network Account ID', 'cookiechimp' ); ?></label></th>
					<td><input type="text" id="cookiechimp_network_account_id" name="cookiechimp_network_account_id" value="<?php echo esc_attr( cookiechimp_get_network_account_id() ); ?>" class="regular-text" pattern="[A-Za-z0-9]+" autocomplete="off" placeholder="<?php esc_attr_e( 'Enter your CookieChimp Account ID', 'cookiechimp' ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php cookiechimp_consent_api_section(); ?>
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
	$cookiechimp_account_id = cookiechimp_get_account_id();

	if ( '' === $cookiechimp_account_id ) {
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

/**
 * Add a settings link to the Network Admin Plugins screen.
 *
 * @param string[] $links Existing plugin action links.
 * @return string[] Updated plugin action links.
 */
function cookiechimp_network_settings_link( $links ) {
	if ( ! cookiechimp_is_network_active() ) {
		return $links;
	}

	$settings_link = '<a href="' . esc_url( network_admin_url( 'settings.php?page=cookiechimp' ) ) . '">' . esc_html__( 'Settings', 'cookiechimp' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}
