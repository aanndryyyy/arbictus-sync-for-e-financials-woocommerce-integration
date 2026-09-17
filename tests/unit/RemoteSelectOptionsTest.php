<?php
/**
 * Settings dropdown options filled from e-Financials.
 *
 * @package Arbictus\EFinancialsPlugin
 */

declare(strict_types=1);

namespace Aanndryyyy\EFinancialsPlugin\Tests\Unit;

use Aanndryyyy\EFinancialsPlugin\Integration\RemoteSelectOptions;
use EFinancialsClient\Responses\AccountDimensions\AccountDimensionResponse;
use EFinancialsClient\Responses\Accounts\AccountResponse;
use PHPUnit\Framework\TestCase;

/**
 * A select posts whatever it shows, so what it offers decides what gets saved.
 */
final class RemoteSelectOptionsTest extends TestCase {

	/**
	 * Build an account.
	 *
	 * @param int                  $id       Account number.
	 * @param string               $name_est Estonian name.
	 * @param array<string, mixed> $override Other attributes.
	 */
	private function account( int $id, string $name_est, array $override = [] ): AccountResponse {

		return AccountResponse::fake(
			[
				'id'       => $id,
				'name_est' => $name_est,
				'name_eng' => '',
			] + $override
		);
	}

	/**
	 * Build a dimension.
	 *
	 * @param int                  $id          Dimension id.
	 * @param int                  $accounts_id Parent account number.
	 * @param array<string, mixed> $override    Other attributes.
	 */
	private function dimension( int $id, int $accounts_id, array $override = [] ): AccountDimensionResponse {

		return AccountDimensionResponse::fake(
			[
				'id'          => $id,
				'accounts_id' => $accounts_id,
				'title_est'   => 'Dimension ' . $id,
			] + $override
		);
	}

	/**
	 * A saved id the list does not offer is added right after the blank entry.
	 */
	public function test_missing_saved_value_is_kept_selectable(): void {

		$options = RemoteSelectOptions::with_saved(
			[
				''   => '— Select —',
				'10' => 'Ten',
			],
			'1360'
		);

		$this->assertSame( [ '', 1360, 10 ], \array_keys( $options ) );
		$this->assertSame( 'ID 1360 (currently saved)', $options[1360] );
	}

	/**
	 * When the list failed to load, the saved id still survives a save.
	 */
	public function test_saved_value_survives_failed_load(): void {

		$options = RemoteSelectOptions::with_saved( [ '' => 'Could not load options' ], '1010' );

		$this->assertTrue( RemoteSelectOptions::contains( $options, '1010' ) );
	}

	/**
	 * An offered or empty saved value leaves the options untouched, optgroups included.
	 */
	public function test_offered_or_empty_saved_value_changes_nothing(): void {

		$options = [
			''      => '— Select —',
			'Group' => [ '1010' => '1010 - Kassa' ],
		];

		$this->assertSame( $options, RemoteSelectOptions::with_saved( $options, '1010' ) );
		$this->assertSame( $options, RemoteSelectOptions::with_saved( $options, '' ) );
	}

	/**
	 * Every active account is offered, cash and bank accounts in the first group.
	 */
	public function test_cash_accounts_keep_every_active_account(): void {

		$options = RemoteSelectOptions::cash_accounts(
			[
				$this->account( 1010, 'Sularaha kassas' ),
				$this->account( 1360, 'Arveldused aruandvate isikutega' ),
				$this->account( 1400, 'Stripe kassa' ),
				$this->account( 2900, 'Vana konto', [ 'is_valid' => false ] ),
				$this->account( 2910, 'Suletud konto', [ 'is_disabled' => true ] ),
			]
		);

		$this->assertSame(
			[
				'Cash and bank accounts' => [
					1010 => '1010 - Sularaha kassas',
					1400 => '1400 - Stripe kassa',
				],
				'Other accounts'         => [
					1360 => '1360 - Arveldused aruandvate isikutega',
				],
			],
			$options
		);
	}

	/**
	 * An empty group is left out rather than rendered as an empty optgroup.
	 */
	public function test_empty_group_is_left_out(): void {

		$options = RemoteSelectOptions::cash_accounts( [ $this->account( 1010, 'Sularaha kassas' ) ] );

		$this->assertSame( [ 'Cash and bank accounts' ], \array_keys( $options ) );
	}

	/**
	 * Dimensions of cash and bank accounts come first; the rest stay selectable.
	 */
	public function test_dimensions_are_grouped_by_their_account(): void {

		$options = RemoteSelectOptions::dimensions(
			[
				$this->dimension( 49584, 1020 ),
				$this->dimension( 41458, 5990 ),
				$this->dimension( 50000, 1400 ),
				$this->dimension( 50001, 1020, [ 'is_deleted' => true ] ),
			],
			[ $this->account( 1400, 'Stripe kassa' ) ]
		);

		$this->assertSame( [ 49584, 50000 ], \array_keys( $options['Cash and bank accounts'] ) );
		$this->assertSame( [ 41458 ], \array_keys( $options['Other accounts'] ) );
		$this->assertSame( 'Dimension 49584 (Konto 1020, ID: 49584)', $options['Cash and bank accounts'][49584] );
	}
}
