<?php
/**
 * Plugin Name: WooCommerce Binance Pay Gateway
 * Description: Accept USDT payments via Binance Pay (UID transfer) in WooCommerce.
 * Version:     1.0.0
 * Author:      Md Rahul Islam
 * Author URI:  https://github.com/mdrahulislammri
 * License:     GPL-2.0+
 * Text Domain: wc-binance-pay
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>WooCommerce Binance Pay:</strong> WooCommerce must be installed and active.</p></div>';
        });
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-binance-client.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-binance-gateway.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Binance_Gateway';
        return $gateways;
    });
});
