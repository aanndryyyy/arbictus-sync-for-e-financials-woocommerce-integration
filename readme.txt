=== e-Financials for WooCommerce ===
Contributors: aanndryyyy
Tags: accounting, invoices, bookkeeping, e-arveldaja, estonia
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync orders, customers, products and invoices from your shop to e-Financials (e-Arveldaja) bookkeeping.

== Description ==

Background sync so checkout stays fast. Once an order reaches a configured status the plugin:

1. Upserts the customer as an e-Financials client.
2. Ensures every ordered product exists as an e-Financials product.
3. Creates and registers the sale invoice.
4. Records the payment (cash fields and/or a bank transaction), gateway-agnostic.
5. Optionally delivers the invoice to the customer by email or e-invoice.
6. Issues credit invoices on full and partial refunds.

Merchants get a settings screen, an order list column, an order metabox with the invoice PDF,
manual order actions and an optional note on customer emails.

The plugin is compatible with WooCommerce High-Performance Order Storage (HPOS) and queues its
work through Action Scheduler, so no API call ever blocks a shopper.

= External service: e-Financials (e-Arveldaja) =

This plugin is an interface to **e-Financials / e-Arveldaja**, the accounting service operated by
Registrite ja Infosüsteemide Keskus (RIK), an Estonian government agency. It is not affiliated with
or endorsed by RIK.

The plugin sends no data anywhere until you enter your own e-Financials API credentials on the
settings screen. Once configured, it contacts RIK's API at `https://rmp-api.rik.ee` (live) or
`https://demo-rmp-api.rik.ee` (test environment), depending on the environment you select.

Data sent to that service, only for orders your shop processes:

* Billing name, company name, e-mail address, phone number, postal address and country.
* VAT / registry number, when your shop collects one.
* Order line items: product names, SKUs, quantities, prices, VAT rates, shipping and fee lines.
* Order number, order date, payment method identifier and payment amounts.
* Refund amounts and reasons, when you issue a refund.

Data received from that service: client, product, invoice and transaction identifiers, invoice
numbers and invoice PDFs, plus the option lists (invoice series, templates, sale articles, cash
accounts) shown on the settings screen.

If you enable invoice delivery, RIK's service sends the invoice e-mail or e-invoice to your
customer on your behalf.

Using the service requires an agreement with RIK. Please review their terms before enabling the
plugin:

* Service overview: https://www.rik.ee/en/e-financials/introduction-e-financials
* API technical documentation: https://abiinfo.rik.ee/index.php/en/node/304
* API terms of use: https://e-arveldaja.rik.ee/static/web/contracts/API_kasutustingimused.pdf
* e-Arveldaja terms of use: https://e-arveldaja.rik.ee/static/web/contracts/e-arveldaja_kasutustingimused.pdf

All API traffic goes through the `aanndryyyy/e-financials-php-client` library and is signed with
HMAC-SHA-384.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin to `wp-content/plugins/` and activate it.
3. Go to WooCommerce > Settings > Integrations > e-Financials.
4. Enter your API key id, public key and password, then pick the invoice series, template and payment mode map.

== Frequently Asked Questions ==

= Does it work with the demo environment? =

Yes. Choose the test environment on the settings screen to point the plugin at e-Financials' demo
API and try the sync without touching live books.

= Are prices and VAT taken from the shop? =

Yes. VAT rates come from the order lines and are mapped to e-Financials sale articles, which is how
e-Financials books VAT. Every rate used by the shop needs an entry in the VAT rate map.

= Is the plugin free to use in production? =

Yes. It is free software under GPLv2 or later, with no restriction on production use, company size
or revenue, and no feature is locked behind a payment. Updates are delivered through WordPress.org
like any other plugin here. Paid subscriptions, offered separately, cover support and consulting
only — never permission to run the plugin or access to extra functionality.

= Is this an official RIK product? =

No. It is an independent integration built against the public e-Financials REST API, and is not
affiliated with or endorsed by Registrite ja Infosüsteemide Keskus.

= Does the plugin work with High-Performance Order Storage? =

Yes. The plugin declares HPOS compatibility and reads and writes order data through the WooCommerce
CRUD API, so it works with both the legacy post storage and HPOS.

== Screenshots ==

1. Integration settings under WooCommerce > Settings > Integrations > e-Financials.
2. The e-Financials sync column on the order list.
3. The order metabox showing invoice status and the invoice PDF download.

== Changelog ==

= 1.0.0 =
* Initial release.
