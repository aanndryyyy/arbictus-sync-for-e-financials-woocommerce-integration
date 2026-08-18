<?php
/**
 * Seeds the wp-env site with representative data for the WordPress.org
 * screenshots: an Estonian store, a small catalogue, and a few orders in the
 * sync states the plugin actually produces.
 *
 * Run with: npx wp-env run cli wp eval-file <path-to-this-file>
 *
 * This DELETES every existing order and overwrites the integration settings, so
 * run it only on a throwaway wp-env site. The e2e suite asserts against a clean
 * settings state — after taking screenshots, reset it before running the tests:
 *
 *   npx wp-env run cli wp option delete woocommerce_efinancials_integration_settings
 *
 * @package Arbictus\EFinancialsPlugin
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.Security.EscapeOutput

// wp-env has no mail transport; without this every order picks up a pair of
// "email failed to send" notes that would end up in the screenshots.
add_filter( 'pre_wp_mail', '__return_true' );

// Start from a clean order list so leftovers from e2e runs stay out of shot.
foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any', 'return' => 'ids' ) ) as $stale_id ) {
	$stale = wc_get_order( $stale_id );

	if ( $stale ) {
		$stale->delete( true );
	}
}

update_option( 'blogname', 'Näidis Pood' );
update_option( 'woocommerce_store_address', 'Tartu mnt 1' );
update_option( 'woocommerce_store_city', 'Tallinn' );
update_option( 'woocommerce_store_postcode', '10145' );
update_option( 'woocommerce_default_country', 'EE' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'yes' );
update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_task_list_appearance_hidden', 'yes' );

WC_Tax::_insert_tax_rate(
	array(
		'tax_rate_country'  => 'EE',
		'tax_rate'          => '22.0000',
		'tax_rate_name'     => 'KM',
		'tax_rate_priority' => 1,
		'tax_rate_shipping' => 1,
		'tax_rate_class'    => '',
	)
);

// Settings the screenshot should show filled in. The credentials are obvious
// placeholders — never real keys.
$integration = get_option( 'woocommerce_efinancials_integration_settings', array() );

update_option(
	'woocommerce_efinancials_integration_settings',
	array_merge(
		is_array( $integration ) ? $integration : array(),
		array(
			'api_key_id'          => '10023',
			'api_key_public'      => 'PUBLIC-KEY-EXAMPLE',
			'api_key_password'    => 'password-example',
			'api_key_environment'    => 'api_environment_test',
			'invoice_series_id'      => '3',
			'cl_templates_id'        => '17',
			'cl_sale_articles_id'    => '204',
			'cl_sale_articles_map'   => '{"22":204,"9":205,"0":206}',
			'term_days'              => '14',
			'use_wc_order_number'    => 'yes',
			'default_payment_mode'   => 'transaction',
			'gateway_payment_map'    => '{"bacs":{"mode":"transaction","accounts_dimensions_id":4},"cod":{"mode":"cash","cash_accounts_id":1010}}',
			'auto_deliver'           => 'yes',
			'product_auto_sync'      => 'yes',
		)
	)
);

/*
 * The settings screen fills its remote dropdowns from cached option lists. The
 * demo credentials above cannot reach the API, so prime the same transients the
 * plugin writes after a successful lookup — the screenshot then shows what a
 * configured install looks like rather than the "could not load" fallback.
 */
set_transient( 'ef_settings_options_series', array( '3' => 'MK (default)', '4' => 'ARV' ), HOUR_IN_SECONDS );
set_transient( 'ef_settings_options_templates', array( '17' => 'Arve (default)', '18' => 'Invoice EN' ), HOUR_IN_SECONDS );
set_transient(
	'ef_settings_options_articles',
	array( '204' => 'Sales 22%', '205' => 'Sales 9%', '206' => 'Sales 0% (EU)' ),
	HOUR_IN_SECONDS
);

$catalogue = array(
	array( 'Raamatupidamise kiirkursus', 'EF-COURSE', '149.00' ),
	array( 'Beanie', 'EF-BEANIE', '18.00' ),
	array( 'Hoodie', 'EF-HOODIE', '45.00' ),
	array( 'Sunglasses', 'EF-SUNGLASSES', '90.00' ),
);

$product_ids = array();

foreach ( $catalogue as $item ) {
	list( $name, $sku, $price ) = $item;

	$existing = wc_get_product_id_by_sku( $sku );

	if ( $existing ) {
		$product_ids[] = $existing;
		continue;
	}

	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_regular_price( $price );
	$product->set_tax_status( 'taxable' );
	$product->set_status( 'publish' );
	$product_ids[] = $product->save();
}

$address = array(
	'first_name' => 'Kadri',
	'last_name'  => 'Tamm',
	'company'    => 'Näidis OÜ',
	'email'      => 'kadri@example.com',
	'phone'      => '+372 5555 0100',
	'address_1'  => 'Pärnu mnt 12',
	'city'       => 'Tallinn',
	'postcode'   => '10148',
	'country'    => 'EE',
);

/*
 * Each row: order status, [product index => qty], and the sync meta the plugin
 * writes once the background job has run — so the column and metabox show the
 * synced, pending and failed states side by side.
 */
$demo_orders = array(
	array(
		'completed',
		array( 0 => 1, 2 => 2 ),
		array(
			'_ef_sale_invoice_id'     => 48123,
			'_ef_sale_invoice_number' => 'MK-1041',
			'_ef_payment_mode'        => 'transfer',
			'_ef_delivered_at'        => '2026-08-14 11:02:37',
			'_ef_synced_at'           => '2026-08-14 11:02:31',
			'_ef_sync_complete'       => 'yes',
		),
	),
	array(
		'completed',
		array( 1 => 3, 3 => 1 ),
		array(
			'_ef_sale_invoice_id'     => 48124,
			'_ef_sale_invoice_number' => 'MK-1042',
			'_ef_payment_mode'        => 'cash',
			'_ef_synced_at'           => '2026-08-15 09:41:12',
			'_ef_sync_complete'       => 'yes',
		),
	),
	array( 'processing', array( 2 => 1 ), array() ),
	array(
		'completed',
		array( 3 => 1 ),
		array(
			'_ef_last_error' => 'Client upsert failed: registry code is required for company invoices.',
			'_ef_attempts'   => 2,
		),
	),
);

foreach ( $demo_orders as $demo_order ) {
	list( $status, $lines, $meta ) = $demo_order;

	$order = wc_create_order();

	foreach ( $lines as $index => $quantity ) {
		if ( isset( $product_ids[ $index ] ) ) {
			$product = wc_get_product( $product_ids[ $index ] );

			if ( $product ) {
				$order->add_product( $product, $quantity );
			}
		}
	}

	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );
	$order->set_payment_method_title( 'Bank transfer' );
	$order->calculate_totals();

	foreach ( $meta as $key => $value ) {
		$order->update_meta_data( $key, $value );
	}

	$order->save();
	$order->update_status( $status, 'Demo order for documentation screenshots.' );

	echo 'order ' . $order->get_id() . ' (' . $status . ")\n";
}

echo "seeded\n";
