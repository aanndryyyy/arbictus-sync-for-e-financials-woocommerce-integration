<?php
/**
 * Option lists for the settings dropdowns filled from e-Financials.
 *
 * @package Arbictus\EFinancialsPlugin
 */

declare(strict_types=1);

namespace Aanndryyyy\EFinancialsPlugin\Integration;

use EFinancialsClient\Responses\AccountDimensions\AccountDimensionResponse;
use EFinancialsClient\Responses\Accounts\AccountResponse;

/**
 * Builds WooCommerce select options: value => label, or group label =>
 * (value => label) for an optgroup. Kept free of the WC_Integration so the
 * rules can be unit tested.
 */
final class RemoteSelectOptions {

	/**
	 * Whether a value is offered, including inside optgroups.
	 *
	 * @param array<int|string, string|array<int|string, string>> $options Select options.
	 * @param string                                              $value   Value to look for.
	 */
	public static function contains( array $options, string $value ): bool {

		foreach ( $options as $key => $label ) {
			if ( \is_array( $label ) ) {
				if ( \array_key_exists( $value, $label ) ) {
					return true;
				}

				continue;
			}

			if ( (string) $key === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep the saved value selectable when the remote list does not offer it.
	 *
	 * A browser selects the first option when the saved value is missing and
	 * posts that on save, and WooCommerce stores a select value without checking
	 * it against the options. Without this entry, saving any other setting while
	 * the API is unreachable, or after the id dropped out of the list, silently
	 * replaces the saved id with ''.
	 *
	 * @param array<int|string, string|array<int|string, string>> $options Select options, blank entry first.
	 * @param string                                              $saved   Currently saved value.
	 *
	 * @return array<int|string, string|array<int|string, string>>
	 */
	public static function with_saved( array $options, string $saved ): array {

		if ( $saved === '' || self::contains( $options, $saved ) ) {
			return $options;
		}

		$entry = [
			/* translators: %s: saved e-Financials id */
			$saved => \sprintf( __( 'ID %s (currently saved)', 'arbictus-sync-for-e-financials-woocommerce' ), $saved ),
		];

		// Right after the blank entry, where the merchant looks first.
		if ( \array_key_exists( '', $options ) ) {
			return [ '' => $options[''] ] + $entry + $options;
		}

		return $entry + $options;
	}

	/**
	 * Active accounts for paid_in_cash, cash and bank accounts first.
	 *
	 * Every active account stays selectable: the setting also takes clearing
	 * accounts, and no name or number rule can recognise all of those.
	 *
	 * @param array<int, AccountResponse> $accounts Chart of accounts.
	 *
	 * @return array<int|string, string|array<int|string, string>>
	 */
	public static function cash_accounts( array $accounts ): array {

		$cash_or_bank = [];
		$other        = [];

		foreach ( $accounts as $account ) {
			if ( $account->id === null || ! $account->isValid || $account->isDisabled === true ) {
				continue;
			}

			$name  = $account->nameEst !== '' ? $account->nameEst : $account->nameEng;
			$label = \sprintf( '%d - %s', $account->id, $name );

			if ( self::is_cash_or_bank_account( $account ) ) {
				$cash_or_bank[ (string) $account->id ] = $label;
			} else {
				$other[ (string) $account->id ] = $label;
			}
		}

		return self::grouped( $cash_or_bank, $other );
	}

	/**
	 * Account dimensions for transactions, those of cash and bank accounts first.
	 *
	 * @param array<int, AccountDimensionResponse> $dimensions Account dimensions.
	 * @param array<int, AccountResponse>          $accounts   Chart of accounts, to classify each dimension's account.
	 *
	 * @return array<int|string, string|array<int|string, string>>
	 */
	public static function dimensions( array $dimensions, array $accounts ): array {

		$cash_or_bank_accounts = [];

		foreach ( $accounts as $account ) {
			if ( $account->id !== null && self::is_cash_or_bank_account( $account ) ) {
				$cash_or_bank_accounts[ $account->id ] = true;
			}
		}

		$cash_or_bank = [];
		$other        = [];

		foreach ( $dimensions as $dimension ) {
			if ( $dimension->id === null || $dimension->isDeleted === true ) {
				continue;
			}

			$title = $dimension->titleEst !== '' ? $dimension->titleEst : ( $dimension->titleEng ?? '' );
			$label = \sprintf(
				'%s (Konto %d, ID: %d)',
				$title !== '' ? $title : (string) $dimension->id,
				$dimension->accountsId,
				$dimension->id
			);

			if ( isset( $cash_or_bank_accounts[ $dimension->accountsId ] ) || self::is_cash_or_bank_account_number( $dimension->accountsId ) ) {
				$cash_or_bank[ (string) $dimension->id ] = $label;
			} else {
				$other[ (string) $dimension->id ] = $label;
			}
		}

		return self::grouped( $cash_or_bank, $other );
	}

	/**
	 * Cash and bank accounts: the 1000–1099 range of the Estonian chart, or a
	 * name that says so.
	 *
	 * @param AccountResponse $account Account.
	 */
	private static function is_cash_or_bank_account( AccountResponse $account ): bool {

		if ( $account->id !== null && self::is_cash_or_bank_account_number( $account->id ) ) {
			return true;
		}

		$names = \strtolower( $account->nameEst . ' ' . $account->nameEng );

		return \str_contains( $names, 'kassa' ) || \str_contains( $names, 'cash' );
	}

	/**
	 * Whether an account number is in the cash and bank range.
	 *
	 * @param int $number Account number.
	 */
	private static function is_cash_or_bank_account_number( int $number ): bool {

		return $number >= 1000 && $number < 1100;
	}

	/**
	 * Wrap the two lists in optgroups, leaving out an empty one.
	 *
	 * @param array<int|string, string> $cash_or_bank Likely choices.
	 * @param array<int|string, string> $other        Everything else.
	 *
	 * @return array<int|string, string|array<int|string, string>>
	 */
	private static function grouped( array $cash_or_bank, array $other ): array {

		$groups = [];

		if ( $cash_or_bank !== [] ) {
			$groups[ __( 'Cash and bank accounts', 'arbictus-sync-for-e-financials-woocommerce' ) ] = $cash_or_bank;
		}

		if ( $other !== [] ) {
			$groups[ __( 'Other accounts', 'arbictus-sync-for-e-financials-woocommerce' ) ] = $other;
		}

		return $groups;
	}
}
