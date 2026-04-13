=== WooCommerce Binance Pay Gateway ===
Contributors: mdrahulislammri
Tags: woocommerce, binance, payment, crypto, usdt, binance pay
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept USDT payments via Binance Pay (UID transfer) in WooCommerce. Payments go directly to your Binance account.

== Description ==

**WooCommerce Binance Pay Gateway** allows your customers to pay with USDT via Binance Pay. No third-party payment processor — payments go directly to your Binance account.

= How It Works =

1. Customer selects Binance Pay at checkout
2. Customer sees your Binance UID with a copy button
3. Customer sends exact USDT via Binance Pay (Send → UID)
4. Customer pastes their Order ID from Binance app
5. Plugin verifies via Binance API automatically
6. Order marked as paid — no manual approval needed

= Features =

* Direct Binance Pay (UID transfer) integration
* Automatic payment verification via Binance API
* Amount, coin, and transaction age validation
* Duplicate payment prevention
* Clean checkout UI with copy button
* Binance official logo on checkout
* Read-Only API — no withdrawal permissions needed

= Requirements =

* WooCommerce 4.0 or higher
* PHP 7.4 or higher
* Binance account with Read-Only API access
* cURL extension enabled

== Installation ==

1. Upload the `wc-binance-pay` folder to `/wp-content/plugins/`
2. Activate the plugin in **WordPress → Plugins**
3. Go to **WooCommerce → Settings → Payments**
4. Enable **Binance Pay (USDT)** and click **Set up**
5. Enter your API Key, API Secret, and Binance UID
6. Save changes

= Binance API Setup =

1. Login to [Binance](https://www.binance.com)
2. Go to **Profile → API Management**
3. Create API key with **Read Only** permission only
4. Copy **API Key** and **Secret Key**

== Configuration ==

* **API Key** — Binance API Key (Read-Only permission only)
* **API Secret** — Binance API Secret Key
* **Binance UID** — Your Binance UID (customers send to this)
* **Accepted Coin** — Coin to accept (default: USDT)
* **Max Transaction Age** — Reject transactions older than X hours (default: 24)

== Frequently Asked Questions ==

= Is this safe? =
Yes. The plugin only requires Read-Only API permission. It cannot withdraw funds.

= Which coins are supported? =
USDT by default. You can change it in settings.

= What if verification fails? =
The customer will see an error message and can try again with the correct Order ID.

= Where do I find the Order ID? =
Binance App → Wallets → Transaction History → tap the payment → copy Order ID.

== Screenshots ==

1. Checkout payment form with Binance UID and Order ID input
2. Plugin settings page in WooCommerce

== Changelog ==

= 1.0.0 =
* Initial release
* Binance Pay (UID transfer) payment verification
* Automatic Order ID verification via Binance API
* Amount, coin, and age validation
* Duplicate payment prevention

== Upgrade Notice ==

= 1.0.0 =
Initial release.
