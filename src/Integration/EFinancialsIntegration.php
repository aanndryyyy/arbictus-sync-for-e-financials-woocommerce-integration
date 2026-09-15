<?php
/**
 * WooCommerce integration settings for e-Financials.
 *
 * @package Arbictus\EFinancialsPlugin
 */

declare(strict_types=1);

namespace Aanndryyyy\EFinancialsPlugin\Integration;

use Aanndryyyy\EFinancialsPlugin\Api\RemoteLookup;
use Aanndryyyy\EFinancialsPlugin\Settings\SettingsRepository;
use Aanndryyyy\EFinancialsPlugin\Support\ErrorMessage;
use Aanndryyyy\EFinancialsPlugin\Support\Logger;
use EFinancials;
use Throwable;

/**
 * WC_Integration settings screen.
 */
class EFinancialsIntegration extends \WC_Integration {

	public const SETTING_KEY_API_KEY_ID = 'api_key_id';

	public const SETTING_KEY_API_KEY_PUBLIC = 'api_key_public';

	public const SETTING_KEY_API_KEY_PASSWORD = 'api_key_password';

	public const SETTING_KEY_API_ENVIRONMENT = 'api_key_environment';

	public const SETTING_KEY_API_ENVIRONMENT_OPTION_TEST = 'api_environment_test';

	public const SETTING_KEY_API_ENVIRONMENT_OPTION_LIVE = 'api_environment_live';

	public const SETTING_KEY_INVOICE_SERIES_ID = 'invoice_series_id';

	public const SETTING_KEY_TEMPLATE_ID = 'cl_templates_id';

	public const SETTING_KEY_SALE_ARTICLE_ID = 'cl_sale_articles_id';

	public const SETTING_KEY_SALE_ARTICLE_MAP = 'cl_sale_articles_map';

	public const SETTING_KEY_TERM_DAYS = 'term_days';

	public const SETTING_KEY_USE_WC_ORDER_NUMBER = 'use_wc_order_number';

	public const SETTING_KEY_DEFAULT_PAYMENT_MODE = 'default_payment_mode';

	public const SETTING_KEY_DEFAULT_CASH_ACCOUNTS_ID = 'default_cash_accounts_id';

	public const SETTING_KEY_DEFAULT_ACCOUNTS_DIMENSIONS_ID = 'default_accounts_dimensions_id';

	public const SETTING_KEY_GATEWAY_MAP = 'gateway_payment_map';

	public const SETTING_KEY_AUTO_DELIVER = 'auto_deliver';

	public const SETTING_KEY_AUTO_DELIVER_EINVOICE = 'auto_deliver_einvoice';

	public const SETTING_KEY_PRODUCT_AUTO_SYNC = 'product_auto_sync';

	private const OPTIONS_TRANSIENT_PREFIX = 'ef_settings_options_';

	private const OPTIONS_TRANSIENT_TTL = 3600;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {

		$this->id                 = 'efinancials_integration';
		$this->method_title       = __( 'e-Financials', 'arbictus-sync-for-e-financials-woocommerce' );
		$this->method_description = __( 'Sync WooCommerce orders to e-Arveldaja / e-Financials in the background.', 'arbictus-sync-for-e-financials-woocommerce' );

		$this->init_form_fields();
		$this->init_settings();

		// @phpstan-ignore-next-line
		\add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init_form_fields(): void {

		$series_options       = $this->safe_id_options( 'series', [ $this, 'fetch_invoice_series_options' ] );
		$template_options     = $this->safe_id_options( 'templates', [ $this, 'fetch_template_options' ] );
		$article_options      = $this->safe_id_options( 'articles', [ $this, 'fetch_sale_article_options' ] );
		$dimension_options    = $this->safe_id_options( 'dimensions', [ $this, 'fetch_dimension_options' ] );
		$cash_account_options = $this->safe_id_options( 'cash_accounts', [ $this, 'fetch_cash_account_options' ] );

		$this->form_fields = [
			'api_section'                              => [
				'title' => __( 'API connection', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'  => 'title',
			],
			self::SETTING_KEY_API_KEY_ID               => [
				'title'       => __( 'API Key ID', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'View guide <a href="https://abiinfo.rik.ee/en/node/303">here</a>.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'desc_tip'    => false,
				'default'     => '',
			],
			self::SETTING_KEY_API_KEY_PUBLIC           => [
				'title'    => __( 'API Key Public', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => false,
				'default'  => '',
			],
			self::SETTING_KEY_API_KEY_PASSWORD         => [
				'title'    => __( 'API Key Password', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'     => 'password',
				'desc_tip' => false,
				'default'  => '',
			],
			self::SETTING_KEY_API_ENVIRONMENT          => [
				'title'       => __( 'API Environment', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'label'       => __( 'Choose the environment', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => self::SETTING_KEY_API_ENVIRONMENT_OPTION_TEST,
				'description' => __( 'View <a href="https://demo-rmp.rik.ee">test environment</a> or <a href="https://e-arveldaja.rik.ee/">live environment</a>.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'options'     => [
					self::SETTING_KEY_API_ENVIRONMENT_OPTION_TEST => __( 'Test Environment', 'arbictus-sync-for-e-financials-woocommerce' ),
					self::SETTING_KEY_API_ENVIRONMENT_OPTION_LIVE => __( 'Live Environment', 'arbictus-sync-for-e-financials-woocommerce' ),
				],
			],
			'invoice_section'                          => [
				'title' => __( 'Invoicing', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'  => 'title',
			],
			self::SETTING_KEY_INVOICE_SERIES_ID        => [
				'title'       => __( 'Invoice series', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Number prefix of the selected series is sent as number_prefix on every sale invoice. Leave empty to let e-Financials number invoices itself.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'options'     => $series_options,
			],
			self::SETTING_KEY_TEMPLATE_ID              => [
				'title'       => __( 'Invoice template', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Sale invoice template (cl_templates_id). Required before first sync.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'options'     => $template_options,
			],
			self::SETTING_KEY_SALE_ARTICLE_ID          => [
				'title'       => __( 'Default sale article', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Required: e-Financials refuses to create products without a sale account, and books VAT by article. Its VAT rate must match the rate your shop charges.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'options'     => $article_options,
			],
			self::SETTING_KEY_SALE_ARTICLE_MAP         => [
				'title'       => __( 'VAT rate → sale article map (JSON)', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Required for mixed-rate catalogues and for 0% lines — including shops with WooCommerce taxes switched off, where every line is 0%. Example: {"22":1,"9":5,"0":12}. Each order line uses the article mapped to its WooCommerce tax rate; unmapped rates fall back to the default article and sync fails if the rates disagree.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'css'         => 'width:100%;min-height:80px;font-family:monospace',
			],
			self::SETTING_KEY_TERM_DAYS                => [
				'title'   => __( 'Payment term (days)', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'number',
				'default' => '14',
			],
			self::SETTING_KEY_USE_WC_ORDER_NUMBER      => [
				'title'   => __( 'Use WooCommerce order number as invoice suffix', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Push WC order number as number_suffix', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default' => 'yes',
			],
			'payment_section'                          => [
				'title'       => __( 'Payment recording', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Gateway-agnostic: maps WooCommerce payment method ids to cash fields or transactions. No gateway plugin required.', 'arbictus-sync-for-e-financials-woocommerce' ),
			],
			self::SETTING_KEY_DEFAULT_PAYMENT_MODE     => [
				'title'   => __( 'Default payment mode', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'select',
				'default' => SettingsRepository::PAYMENT_MODE_CASH,
				'options' => [
					SettingsRepository::PAYMENT_MODE_CASH => __( 'Cash fields on invoice', 'arbictus-sync-for-e-financials-woocommerce' ),
					SettingsRepository::PAYMENT_MODE_TRANSACTION => __( 'Payment transaction', 'arbictus-sync-for-e-financials-woocommerce' ),
					SettingsRepository::PAYMENT_MODE_OFF  => __( 'Off (invoice only)', 'arbictus-sync-for-e-financials-woocommerce' ),
				],
			],
			self::SETTING_KEY_DEFAULT_CASH_ACCOUNTS_ID => [
				'title'       => __( 'Default cash account', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Used for Option A (paid_in_cash) when the gateway map does not override.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'options'     => $cash_account_options,
			],
			self::SETTING_KEY_DEFAULT_ACCOUNTS_DIMENSIONS_ID => [
				'title'       => __( 'Default accounts dimension', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Used for Option B (transactions) when the gateway map does not override.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'options'     => $dimension_options,
			],
			self::SETTING_KEY_GATEWAY_MAP              => [
				'title'       => __( 'Per-gateway payment map (JSON)', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Example: {"bacs":{"mode":"transaction","accounts_dimensions_id":4},"cod":{"mode":"cash","cash_accounts_id":1010}}. Empty uses built-in defaults for bacs/cheque/cod.', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default'     => '',
				'css'         => 'width:100%;min-height:120px;font-family:monospace',
			],
			'delivery_section'                         => [
				'title' => __( 'Delivery & products', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'  => 'title',
			],
			self::SETTING_KEY_AUTO_DELIVER             => [
				'title'   => __( 'Auto-deliver invoice email after register', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send PDF email via e-Financials deliver API', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default' => 'no',
			],
			self::SETTING_KEY_AUTO_DELIVER_EINVOICE    => [
				'title'   => __( 'Also send e-invoice (XML) when available', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'send_einvoice=true when can_send_einvoice', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default' => 'no',
			],
			self::SETTING_KEY_PRODUCT_AUTO_SYNC        => [
				'title'   => __( 'Auto-sync products on save', 'arbictus-sync-for-e-financials-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Upsert e-Financials products when WooCommerce products are saved', 'arbictus-sync-for-e-financials-woocommerce' ),
				'default' => 'no',
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function process_admin_options(): bool { // phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing -- WC parent signature

		$result = parent::process_admin_options();

		$this->flush_option_cache();
		$this->maybe_connection_ping();

		return $result;
	}

	/**
	 * Drop cached remote option lists after a settings change.
	 */
	private function flush_option_cache(): void {

		foreach ( [ 'series', 'templates', 'articles', 'dimensions', 'cash_accounts' ] as $bucket ) {
			\delete_transient( self::OPTIONS_TRANSIENT_PREFIX . $bucket );
		}

		// Credentials or environment may have changed; ids cached for the old
		// tenant must never be reused on the new one.
		foreach ( RemoteLookup::cache_keys() as $key ) {
			\delete_transient( $key );
		}
	}

	/**
	 * Whether the current request is this integration's settings screen.
	 *
	 * Remote option lists are only worth loading there; every other admin
	 * request that instantiates integrations must stay HTTP-free.
	 */
	private function is_own_settings_screen(): bool {

		if ( ! \is_admin() ) {
			return false;
		}

		$page    = $this->query_arg( 'page' );
		$tab     = $this->query_arg( 'tab' );
		$section = $this->query_arg( 'section' );

		if ( $page !== 'wc-settings' || $tab !== 'integration' ) {
			return false;
		}

		// An empty section means WooCommerce is showing the first integration.
		return $section === '' || $section === $this->id;
	}

	/**
	 * Read a string query argument.
	 *
	 * @param string $key Query key.
	 */
	private function query_arg( string $key ): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Sanitized below once the type is known.
		$value = $_GET[ $key ] ?? '';

		if ( ! \is_string( $value ) ) {
			return '';
		}

		return \sanitize_text_field( \wp_unslash( $value ) );
	}

	/**
	 * Smoke-test credentials after save.
	 */
	private function maybe_connection_ping(): void {

		$id       = (string) $this->get_option( self::SETTING_KEY_API_KEY_ID, '' );
		$public   = (string) $this->get_option( self::SETTING_KEY_API_KEY_PUBLIC, '' );
		$password = (string) $this->get_option( self::SETTING_KEY_API_KEY_PASSWORD, '' );

		if ( $id === '' || $public === '' || $password === '' ) {
			return;
		}

		try {
			$live = $this->get_option( self::SETTING_KEY_API_ENVIRONMENT )
				=== self::SETTING_KEY_API_ENVIRONMENT_OPTION_LIVE;

			$client = EFinancials::factory()
				->withApiKeyId( $id )
				->withApiKeyPublic( $public )
				->withApiKeyPassword( $password )
				->withBaseUri( $live ? 'https://rmp-api.rik.ee' : 'https://demo-rmp-api.rik.ee' )
				->make();

			$client->currencies()->all();

			\WC_Admin_Settings::add_message( __( 'e-Financials connection OK.', 'arbictus-sync-for-e-financials-woocommerce' ) );
		} catch ( Throwable $e ) {
			\WC_Admin_Settings::add_error(
				\sprintf(
					/* translators: %s: error */
					__( 'e-Financials connection failed: %s', 'arbictus-sync-for-e-financials-woocommerce' ),
					ErrorMessage::sanitize( $e->getMessage() )
				)
			);
		}
	}

	/**
	 * Provide arguments.
	 *
	 * @param string                                $bucket   Cache bucket name.
	 * @param callable(): array<int|string, string> $callback Options loader.
	 *
	 * @return array<int|string, string>
	 */
	private function safe_id_options( string $bucket, callable $callback ): array {

		$blank = [ '' => __( '— Select —', 'arbictus-sync-for-e-financials-woocommerce' ) ];

		if ( ! $this->is_own_settings_screen() ) {
			return $blank;
		}

		$cached = \get_transient( self::OPTIONS_TRANSIENT_PREFIX . $bucket );

		if ( \is_array( $cached ) ) {
			/**
			 * Cached option list.
			 *
			 * @var array<int|string, string> $cached
			 */
			return $blank + $cached;
		}

		try {
			$options = $callback();
		} catch ( Throwable $e ) {
			( new Logger() )->error(
				'Failed to load ' . $bucket . ' options: ' . ErrorMessage::sanitize( $e->getMessage() )
			);

			// Replaces the blank entry rather than joining it: both would use the
			// '' key, and an array union keeps the left-hand one, so the merchant
			// would be left staring at an empty dropdown with no explanation.
			return [ '' => __( 'Could not load options — check credentials and the WooCommerce logs', 'arbictus-sync-for-e-financials-woocommerce' ) ];
		}

		\set_transient( self::OPTIONS_TRANSIENT_PREFIX . $bucket, $options, self::OPTIONS_TRANSIENT_TTL );

		return $blank + $options;
	}

	/**
	 * Get value.
	 *
	 * @return array<int|string, string>
	 */
	private function fetch_invoice_series_options(): array {

		$client  = $this->make_client_from_posted_or_saved();
		$list    = $client->invoices()->all();
		$options = [];

		foreach ( $list->data as $series ) {
			if ( $series->id === null ) {
				continue;
			}

			$label                           = \trim( $series->numberPrefix );
			$extra                           = $series->isDefault ? ' (default)' : '';
			$options[ (string) $series->id ] = ( $label !== '' ? $label : (string) $series->id ) . $extra;
		}

		return $options;
	}

	/**
	 * Get value.
	 *
	 * @return array<int|string, string>
	 */
	private function fetch_template_options(): array {

		$client  = $this->make_client_from_posted_or_saved();
		$list    = $client->templates()->all();
		$options = [];

		foreach ( $list->data as $template ) {
			$label                             = $template->name !== '' ? $template->name : (string) $template->id;
			$options[ (string) $template->id ] = $label . ( $template->isDefault ? ' (default)' : '' );
		}

		return $options;
	}

	/**
	 * Get value.
	 *
	 * @return array<int|string, string>
	 */
	private function fetch_sale_article_options(): array {

		$client  = $this->make_client_from_posted_or_saved();
		$list    = $client->salesArticles()->all();
		$options = [];

		foreach ( $list->data as $article ) {
			if ( $article->id === null ) {
				continue;
			}

			$label                            = $article->nameEng !== '' ? $article->nameEng : $article->nameEst;
			$options[ (string) $article->id ] = $label !== '' ? $label : (string) $article->id;
		}

		return $options;
	}

	/**
	 * Fetch remote account dimension options for bank/transaction accounts.
	 *
	 * @return array<int|string, string>
	 */
	private function fetch_dimension_options(): array {

		$client  = $this->make_client_from_posted_or_saved();
		$list    = $client->accountDimensions()->all();
		$options = [];

		foreach ( $list->data as $dimension ) {
			if ( $dimension->id === null || $dimension->isDeleted === true ) {
				continue;
			}

			$label = $dimension->titleEst !== '' ? $dimension->titleEst : ( $dimension->titleEng ?? '' );
			$options[ (string) $dimension->id ] = sprintf(
				'%s (Konto %d, ID: %d)',
				$label !== '' ? $label : (string) $dimension->id,
				$dimension->accountsId,
				$dimension->id
			);
		}

		return $options;
	}

	/**
	 * Fetch remote cash account options for cash payments.
	 *
	 * @return array<int|string, string>
	 */
	private function fetch_cash_account_options(): array {

		$client  = $this->make_client_from_posted_or_saved();
		$list    = $client->accounts()->all();
		$options = [];

		foreach ( $list->data as $account ) {
			if ( $account->id === null || $account->isValid === false || $account->isDisabled === true ) {
				continue;
			}

			$name    = $account->nameEst !== '' ? $account->nameEst : $account->nameEng;
			$is_cash = ( $account->id >= 1000 && $account->id < 1100 )
				|| \str_contains( \strtolower( $name ), 'kassa' )
				|| \str_contains( \strtolower( $name ), 'cash' );

			if ( ! $is_cash ) {
				continue;
			}

			$options[ (string) $account->id ] = sprintf( '%d - %s', $account->id, $name );
		}

		if ( $options === [] ) {
			foreach ( $list->data as $account ) {
				if ( $account->id === null || $account->isValid === false || $account->isDisabled === true ) {
					continue;
				}

				$name                             = $account->nameEst !== '' ? $account->nameEst : $account->nameEng;
				$options[ (string) $account->id ] = sprintf( '%d - %s', $account->id, $name );
			}
		}

		return $options;
	}

	/**
	 * May throw on failure.
	 *
	 * @throws \RuntimeException When credentials missing.
	 */
	private function make_client_from_posted_or_saved(): \EFinancialsClient\Contracts\ClientContract {

		$id       = (string) $this->get_option( self::SETTING_KEY_API_KEY_ID, '' );
		$public   = (string) $this->get_option( self::SETTING_KEY_API_KEY_PUBLIC, '' );
		$password = (string) $this->get_option( self::SETTING_KEY_API_KEY_PASSWORD, '' );

		if ( $id === '' || $public === '' || $password === '' ) {
			throw new \RuntimeException( 'Missing credentials' );
		}

		$live = $this->get_option( self::SETTING_KEY_API_ENVIRONMENT )
			=== self::SETTING_KEY_API_ENVIRONMENT_OPTION_LIVE;

		return EFinancials::factory()
			->withApiKeyId( $id )
			->withApiKeyPublic( $public )
			->withApiKeyPassword( $password )
			->withBaseUri( $live ? 'https://rmp-api.rik.ee' : 'https://demo-rmp-api.rik.ee' )
			->make();
	}
}
