<?php
/*
Plugin Name: CookieChimp
Plugin URI: https://cookiechimp.com
Description: CookieChimp CMP (Consent Management Platform) plugin that inserts CookieChimp's JS code to the website to add consent banners.
Version: 1.0.2
Author: Identity Square
Author URI: https://identitysquare.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: CookieChimp-WP
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the plugin.php file for is_plugin_active function
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Hook to add menu item in admin panel
add_action('admin_menu', 'cookiechimp_create_menu');

/**
 * Create a menu item for CookieChimp settings in the admin panel.
 */
function cookiechimp_create_menu() {
    // Add a new submenu under Settings
    add_options_page(
        esc_html__('CookieChimp Settings', 'CookieChimp-WP'),    // Page title
        esc_html__('CookieChimp', 'CookieChimp-WP'),             // Menu title
        'manage_options',          // Capability required to see this option
        'cookiechimp',             // Menu slug
        'cookiechimp_settings_page' // Function to display the settings page
    );
    
    // Register settings
    add_action('admin_init', 'cookiechimp_register_settings');
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
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );
}

/**
 * Display the settings page for CookieChimp.
 */
function cookiechimp_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('CookieChimp Settings', 'CookieChimp-WP'); ?></h1>
        <p>
            <?php esc_html_e('To get started, sign up for a CookieChimp account at', 'CookieChimp-WP'); ?> 
            <a href="https://cookiechimp.com" target="_blank">CookieChimp.com</a>. 
            <?php esc_html_e('Once you have an account, enter your CookieChimp Account ID below.', 'CookieChimp-WP'); ?>
        </p>
        <form method="post" action="options.php">
            <?php settings_fields('cookiechimp-settings-group'); ?>
            <?php do_settings_sections('cookiechimp-settings-group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="cookiechimp_account_id"><?php esc_html_e('CookieChimp Account ID', 'CookieChimp-WP'); ?></label></th>
                    <td><input type="text" id="cookiechimp_account_id" name="cookiechimp_account_id" value="<?php echo esc_attr(get_option('cookiechimp_account_id')); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your CookieChimp Account ID', 'CookieChimp-WP'); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2><?php esc_html_e('Important Note', 'CookieChimp-WP'); ?></h2>
        <p>
            <?php esc_html_e('For full functionality, install and activate the', 'CookieChimp-WP'); ?> 
            <strong><?php esc_html_e('WP Consent API', 'CookieChimp-WP'); ?></strong> 
            <?php esc_html_e('plugin. This plugin helps manage user consents and ensures compliance with privacy regulations.', 'CookieChimp-WP'); ?>
        </p>
        <p>
            <?php esc_html_e('You can', 'CookieChimp-WP'); ?> 
            <a href="https://wordpress.org/plugins/wp-consent-api/" target="_blank"><?php esc_html_e('install the WP Consent API plugin', 'CookieChimp-WP'); ?></a> 
            <?php esc_html_e('from the WordPress plugin repository.', 'CookieChimp-WP'); ?>
        </p>
    </div>
    <?php
}

// Hook to insert the CookieChimp JS in the head section with high priority
add_action('wp_head', 'cookiechimp_insert_js', 1);

/**
 * Output the CookieChimp JS directly into the head section.
 */
function cookiechimp_insert_js() {
    $cookiechimp_account_id = get_option('cookiechimp_account_id');
    if ($cookiechimp_account_id) {
        echo '<script src="https://cookiechimp.com/widget/' . esc_attr($cookiechimp_account_id) . '.js"></script>';
    }
}

/**
 * Display an admin notice if the WP Consent API plugin is not active.
 */
function cookiechimp_dependency_notice() {
    if (!is_plugin_active('wp-consent-api/wp-consent-api.php')) {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('CookieChimp recommends installing the WP Consent API plugin for full functionality. Please configure the', 'CookieChimp-WP'); ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=cookiechimp')); ?>"><?php esc_html_e('CookieChimp settings', 'CookieChimp-WP'); ?></a>
                <?php esc_html_e(' and', 'CookieChimp-WP'); ?>
                <a href="https://wordpress.org/plugins/wp-consent-api/" target="_blank"><?php esc_html_e('install the WP Consent API plugin', 'CookieChimp-WP'); ?></a>.
            </p>
        </div>
        <?php
    }
}

// Add the admin notice
add_action('admin_notices', 'cookiechimp_dependency_notice');

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cookiechimp_settings_link');

/**
 * Add a settings link to the plugin page.
 */
function cookiechimp_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=cookiechimp')) . '">' . esc_html__('Settings', 'CookieChimp-WP') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
