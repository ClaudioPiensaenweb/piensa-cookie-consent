<?php
/**
 * Decides whether the banner applies to the visitor's region.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Geo {
	public static function should_show_cmp( $settings ) {
		$mode = $settings['geo_mode'] ?? 'all';
		if ( $mode === 'none' ) {
			return false;
		}
		if ( $mode === 'all' ) {
			return true;
		}

		$country = self::get_country_code( $settings['geo_header'] ?? 'auto' );
		if ( ! $country ) {
			return true;
		}

		$country = strtoupper( $country );
		if ( $mode === 'eea' ) {
			$eea = self::get_eea_countries();
			return in_array( $country, $eea, true );
		}

		if ( $mode === 'custom' ) {
			$list = self::parse_country_list( $settings['geo_countries'] ?? '' );
			if ( ! $list ) {
				return true;
			}
			return in_array( $country, $list, true );
		}

		return true;
	}

	public static function get_country_code( $header_setting = 'auto' ) {
		$headers = [
			'CF-IPCountry',
			'X-GeoIP-Country',
			'X-Country-Code',
			'X-Geo-Country',
		];

		if ( $header_setting && $header_setting !== 'auto' ) {
			$headers = [ $header_setting ];
		}

		foreach ( $headers as $header ) {
			$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				if ( $value !== '' && $value !== 'XX' ) {
					return $value;
				}
			}
		}

		return '';
	}

	public static function parse_country_list( $raw ) {
		$raw   = is_string( $raw ) ? $raw : '';
		$parts = preg_split( '/[,\\s]+/', strtoupper( $raw ) );
		$parts = array_filter( array_map( 'trim', $parts ) );
		return array_values( array_unique( $parts ) );
	}

	private static function get_eea_countries() {
		return [
			'AT',
			'BE',
			'BG',
			'HR',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GR',
			'HU',
			'IS',
			'IE',
			'IT',
			'LV',
			'LI',
			'LT',
			'LU',
			'MT',
			'NL',
			'NO',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE',
			'GB',
			'CH',
		];
	}
}
