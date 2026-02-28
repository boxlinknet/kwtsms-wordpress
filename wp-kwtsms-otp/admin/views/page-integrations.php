<?php
/**
 * Admin View: Integrations Page.
 *
 * Shows the status of each supported third-party plugin integration.
 * Provides per-integration toggle settings.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */
$woo_active       = class_exists( 'WooCommerce' );
$cf7_active       = class_exists( 'WPCF7' );
$wpforms_active   = function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' );
$elementor_active = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );

$settings        = $this->plugin->settings;
$woo_checkout_otp = (bool) $settings->get( 'general.woo_checkout_otp', 0 );
?>
<div class="wrap kwtsms-admin-wrap">

    <?php $this->render_page_notices(); ?>

    <div class="kwtsms-admin-header">
        <img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
        <h1><?php esc_html_e( 'kwtSMS OTP — Integrations', 'wp-kwtsms-otp' ); ?></h1>
    </div>

    <p style="max-width:800px;font-size:14px;">
        <?php esc_html_e( 'These integrations send SMS notifications and/or require OTP verification for supported third-party plugins. Each integration is automatically activated when its plugin is detected as active.', 'wp-kwtsms-otp' ); ?>
    </p>

    <!-- ===== Integration Status Table ===== -->
    <div style="max-width:800px;background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;margin-bottom:24px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Plugin Status', 'wp-kwtsms-otp' ); ?></h2>
        <table class="widefat" style="font-size:13px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Plugin', 'wp-kwtsms-otp' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
                    <th><?php esc_html_e( 'Features', 'wp-kwtsms-otp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>WooCommerce</strong></td>
                    <td>
                        <?php if ( $woo_active ) : ?>
                            <span style="color:#46b450;font-weight:600;">&#10003; <?php esc_html_e( 'Active', 'wp-kwtsms-otp' ); ?></span>
                        <?php else : ?>
                            <span style="color:#888;">&#9675; <?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'Order status SMS, registration phone field, checkout OTP gate', 'wp-kwtsms-otp' ); ?></td>
                </tr>
                <tr>
                    <td><strong>Contact Form 7</strong></td>
                    <td>
                        <?php if ( $cf7_active ) : ?>
                            <span style="color:#46b450;font-weight:600;">&#10003; <?php esc_html_e( 'Active', 'wp-kwtsms-otp' ); ?></span>
                        <?php else : ?>
                            <span style="color:#888;">&#9675; <?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'SMS confirmation after successful form submission (requires [tel kwtsms_phone] field)', 'wp-kwtsms-otp' ); ?></td>
                </tr>
                <tr>
                    <td><strong>WPForms</strong></td>
                    <td>
                        <?php if ( $wpforms_active ) : ?>
                            <span style="color:#46b450;font-weight:600;">&#10003; <?php esc_html_e( 'Active', 'wp-kwtsms-otp' ); ?></span>
                        <?php else : ?>
                            <span style="color:#888;">&#9675; <?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'SMS confirmation after successful form submission (requires Phone field)', 'wp-kwtsms-otp' ); ?></td>
                </tr>
                <tr>
                    <td><strong>Elementor</strong></td>
                    <td>
                        <?php if ( $elementor_active ) : ?>
                            <span style="color:#46b450;font-weight:600;">&#10003; <?php esc_html_e( 'Active', 'wp-kwtsms-otp' ); ?></span>
                        <?php else : ?>
                            <span style="color:#888;">&#9675; <?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'SMS confirmation after Elementor Pro form submission (requires Phone/Tel field)', 'wp-kwtsms-otp' ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===== WooCommerce Settings ===== -->
    <?php if ( $woo_active ) : ?>
    <div style="max-width:800px;background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;margin-bottom:24px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'WooCommerce Settings', 'wp-kwtsms-otp' ); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'kwtsms_otp_general_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Checkout OTP Gate', 'wp-kwtsms-otp' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kwtsms_otp_general[woo_checkout_otp]" value="1" <?php checked( $woo_checkout_otp, 1 ); ?> />
                            <?php esc_html_e( 'Require OTP verification before placing an order', 'wp-kwtsms-otp' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When enabled, customers must verify their billing phone with an OTP before checkout completes. Recommended for reducing fraudulent orders.', 'wp-kwtsms-otp' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save WooCommerce Settings', 'wp-kwtsms-otp' ) ); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- ===== CF7 Usage ===== -->
    <?php if ( $cf7_active ) : ?>
    <div style="max-width:800px;background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;margin-bottom:24px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Contact Form 7 — Setup', 'wp-kwtsms-otp' ); ?></h2>
        <p style="font-size:14px;">
            <?php esc_html_e( 'To enable SMS confirmation for a CF7 form, add a tel field named kwtsms_phone:', 'wp-kwtsms-otp' ); ?>
        </p>
        <pre style="background:#f8f8f8;border:1px solid #ddd;padding:10px;font-size:13px;">[tel kwtsms_phone placeholder "e.g. 96598765432"]</pre>
        <p style="font-size:14px;"><?php esc_html_e( 'A confirmation SMS is sent automatically when the form is submitted successfully.', 'wp-kwtsms-otp' ); ?></p>
    </div>
    <?php endif; ?>

</div>
