<?php
if (!defined('ABSPATH')) exit;

class WC_Binance_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'binance_pay';
        $this->has_fields         = true;
        $this->method_title       = 'Binance Pay (USDT)';
        $this->method_description = 'Accept USDT payments via Binance Pay UID transfer.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title   = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function get_icon(): string
    {
        $icon = '<img src="https://bin.bnbstatic.com/static/images/common/logo.png" alt="Binance Pay" style="height:20px;width:auto;margin-right:4px;vertical-align:middle;" />';
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled'       => ['title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable Binance Pay', 'default' => 'yes'],
            'title'         => ['title' => 'Title', 'type' => 'text', 'default' => 'Binance Pay (USDT)'],
            'description'   => ['title' => 'Description', 'type' => 'textarea', 'default' => 'Pay with USDT via Binance Pay.'],
            'binance_uid'   => ['title' => 'Binance UID', 'type' => 'text', 'description' => 'Your Binance UID.'],
            'api_key'       => ['title' => 'API Key', 'type' => 'text', 'description' => 'Read-Only permission only.'],
            'api_secret'    => ['title' => 'API Secret', 'type' => 'password'],
            'coin'          => ['title' => 'Accepted Coin', 'type' => 'text', 'default' => 'USDT'],
            'max_age_hours' => ['title' => 'Max Transaction Age (hours)', 'type' => 'number', 'default' => '24'],
        ];
    }

    public function payment_fields()
    {
        $uid  = esc_html($this->get_option('binance_uid'));
        $coin = esc_html($this->get_option('coin', 'USDT'));
        // Use esc_js for inline JS to prevent XSS
        $uid_js = esc_js($this->get_option('binance_uid'));

        echo '<p>' . esc_html($this->get_option('description')) . '</p>';
        echo '<div style="background:#fafafa;border:1.5px solid #F0B90B;border-radius:10px;padding:14px 16px;margin:10px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:11px;color:#aaa;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.5px;">Binance UID</div>
                <div style="font-size:22px;font-weight:800;color:#1E2026;letter-spacing:2px;">' . $uid . '</div>
            </div>
            <button type="button" onclick="navigator.clipboard.writeText(\'' . $uid_js . '\');this.innerText=\'Copied\';"
                style="background:#F0B90B;border:none;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;color:#1E2026;cursor:pointer;">
                Copy
            </button>
        </div>';
        echo '<p style="font-size:12px;color:#888;margin:6px 0 10px;">Binance App &rarr; <strong>Pay</strong> &rarr; <strong>Send</strong> &rarr; enter UID &rarr; send exact amount in <strong>' . $coin . '</strong></p>';
        echo '<p style="font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">After sending, paste your Order ID:</p>';

        woocommerce_form_field('binance_order_id', [
            'type'        => 'text',
            'label'       => 'Binance Order ID',
            'placeholder' => 'Paste your Binance Order ID here...',
            'required'    => true,
        ]);
    }

    public function validate_fields(): bool
    {
        $order_id = sanitize_text_field(wp_unslash($_POST['binance_order_id'] ?? ''));

        if (empty($order_id)) {
            wc_add_notice('Please enter your Binance Order ID.', 'error');
            return false;
        }

        // Basic format check — Binance Pay Order ID is numeric, 15+ digits
        if (!ctype_digit($order_id) || strlen($order_id) < 15) {
            wc_add_notice('Invalid Order ID format. Please check and try again.', 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id): array
    {
        $order    = wc_get_order($order_id);
        $binance_order_id = sanitize_text_field(wp_unslash($_POST['binance_order_id'] ?? ''));
        $amount   = (float)$order->get_total();
        $coin     = strtoupper($this->get_option('coin', 'USDT'));
        $maxAge   = (int)$this->get_option('max_age_hours', 24);
        $apiKey   = $this->get_option('api_key');
        $apiSecret = $this->get_option('api_secret');

        if (empty($apiKey) || empty($apiSecret)) {
            wc_add_notice('Payment gateway is not configured. Please contact support.', 'error');
            return ['result' => 'fail'];
        }

        $client = new Binance_Client($apiKey, $apiSecret);
        $result = $client->verifyByOrderId($binance_order_id, $amount, $coin, $maxAge);

        if ($result === null) {
            wc_add_notice($client->getLastError(), 'error');
            return ['result' => 'fail'];
        }

        $order->payment_complete($binance_order_id);
        $order->add_order_note(sprintf(
            'Binance Pay verified. Order ID: %s | Amount: %.2f %s',
            esc_html($binance_order_id), $amount, esc_html($coin)
        ));
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}
