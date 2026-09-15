<?php
/**
 * Derives banner colours from the active theme's palette.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggests a colour scheme for the banner based on the theme.
 *
 * The palette comes from `wp_get_global_settings()`, which reads theme.json —
 * available for block themes and for classic themes that ship one. Parsing the
 * theme's stylesheet was the alternative and is a poor one: colours live in
 * custom properties, preprocessor output and media queries, and guessing which
 * of them is "the brand colour" from CSS text is unreliable in a way that
 * shows up directly in the banner.
 *
 * Slugs vary between themes, so a named slug is preferred when present and
 * relative luminance decides otherwise. That keeps the result sensible on a
 * theme whose palette uses names this plugin has never seen.
 */
class Piensa_Cookie_Consent_Theme_Colors {

	/**
	 * Return the theme's colour palette.
	 *
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	public static function get_palette() {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return [];
		}

		$settings = wp_get_global_settings( [ 'color', 'palette' ] );

		if ( ! is_array( $settings ) ) {
			return [];
		}

		// Theme entries win over the defaults WordPress ships, and custom
		// entries the site owner added win over both.
		$palette = [];
		foreach ( [ 'default', 'theme', 'custom' ] as $origin ) {
			if ( ! empty( $settings[ $origin ] ) && is_array( $settings[ $origin ] ) ) {
				foreach ( $settings[ $origin ] as $entry ) {
					if ( empty( $entry['color'] ) || empty( $entry['slug'] ) ) {
						continue;
					}
					$hex = self::to_hex( $entry['color'] );
					if ( ! $hex ) {
						continue;
					}
					$palette[ $entry['slug'] ] = [
						'slug'  => (string) $entry['slug'],
						'name'  => isset( $entry['name'] ) ? (string) $entry['name'] : (string) $entry['slug'],
						'color' => $hex,
					];
				}
			}
		}

		return array_values( $palette );
	}

	/**
	 * Suggest banner colours from the theme palette.
	 *
	 * @return array<string, string> Empty when the theme exposes no usable palette.
	 */
	public static function suggest() {
		$palette = self::get_palette();

		if ( count( $palette ) < 2 ) {
			return [];
		}

		$background = self::pick( $palette, [ 'base', 'background', 'white', 'light' ], 'lightest' );
		$text       = self::pick( $palette, [ 'contrast', 'foreground', 'black', 'dark' ], 'darkest' );

		if ( ! $background || ! $text ) {
			return [];
		}

		// An accent that is neither the background nor the text, and that
		// stands out against the background. A theme can call a pale tint
		// "accent-1"; used on a button over a near-white panel it reads as no
		// button at all, so candidates too close to the background are passed
		// over even when their slug says they are the accent.
		$accent = self::pick_accent( $palette, $background, $text );

		if ( ! $accent ) {
			$accent = $text;
		}

		return [
			'theme_bg'                  => $background,
			'theme_primary_color'       => $text,
			'theme_secondary_color'     => self::blend( $text, $background, 0.35 ),
			'theme_btn_primary_bg'      => $accent,
			'theme_btn_primary_color'   => self::readable_on( $accent ),
			'theme_btn_secondary_bg'    => self::blend( $accent, $background, 0.85 ),
			'theme_btn_secondary_color' => $text,
		];
	}

	/**
	 * Choose the colour to use for the primary button.
	 *
	 * @param array  $palette    Palette entries.
	 * @param string $background Chosen background.
	 * @param string $text       Chosen text colour.
	 *
	 * @return string Hex colour, or an empty string.
	 */
	private static function pick_accent( array $palette, $background, $text ) {
		$preferred = [ 'primary', 'accent', 'accent-1', 'accent-2', 'accent-3', 'secondary', 'link' ];
		$exclude   = [ $background, $text ];

		// 3:1 is the WCAG threshold for a user interface component against its
		// surroundings, which is exactly what a button is.
		$candidates = [];
		foreach ( $palette as $entry ) {
			if ( in_array( $entry['color'], $exclude, true ) ) {
				continue;
			}
			$candidates[ $entry['slug'] ] = $entry['color'];
		}

		foreach ( $preferred as $slug ) {
			if ( isset( $candidates[ $slug ] ) && self::contrast( $candidates[ $slug ], $background ) >= 3.0 ) {
				return $candidates[ $slug ];
			}
		}

		// No named accent stands out enough: take whichever candidate does,
		// preferring the most saturated among those that pass.
		$best  = '';
		$score = -1.0;
		foreach ( $candidates as $color ) {
			if ( self::contrast( $color, $background ) < 3.0 ) {
				continue;
			}
			$saturation = self::saturation( $color );
			if ( $saturation > $score ) {
				$score = $saturation;
				$best  = $color;
			}
		}

		return $best;
	}

	/**
	 * WCAG contrast ratio between two colours, 1 to 21.
	 *
	 * @param string $a Hex colour.
	 * @param string $b Hex colour.
	 *
	 * @return float
	 */
	private static function contrast( $a, $b ) {
		$first  = self::luminance( $a );
		$second = self::luminance( $b );

		$lighter = max( $first, $second );
		$darker  = min( $first, $second );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Choose a colour by slug, falling back to a measured strategy.
	 *
	 * @param array    $palette  Palette entries.
	 * @param string[] $slugs    Preferred slugs, in order.
	 * @param string   $strategy One of lightest, darkest, most-saturated.
	 * @param string[] $exclude  Hex values to skip.
	 *
	 * @return string Hex colour, or an empty string.
	 */
	private static function pick( array $palette, array $slugs, $strategy, array $exclude = [] ) {
		foreach ( $slugs as $slug ) {
			foreach ( $palette as $entry ) {
				if ( $entry['slug'] === $slug && ! in_array( $entry['color'], $exclude, true ) ) {
					return $entry['color'];
				}
			}
		}

		$best  = '';
		$score = null;

		foreach ( $palette as $entry ) {
			if ( in_array( $entry['color'], $exclude, true ) ) {
				continue;
			}

			if ( 'most-saturated' === $strategy ) {
				$value = self::saturation( $entry['color'] );
			} else {
				$value = self::luminance( $entry['color'] );
			}

			$better = null === $score
				|| ( 'darkest' === $strategy ? $value < $score : $value > $score );

			if ( $better ) {
				$score = $value;
				$best  = $entry['color'];
			}
		}

		return $best;
	}

	/**
	 * Return black or white, whichever reads better on the given colour.
	 *
	 * Uses the WCAG relative luminance threshold, so the button label stays
	 * legible whatever the theme's accent turns out to be.
	 *
	 * @param string $hex Background colour.
	 *
	 * @return string
	 */
	public static function readable_on( $hex ) {
		return self::luminance( $hex ) > 0.179 ? '#111111' : '#ffffff';
	}

	/**
	 * Mix two colours.
	 *
	 * @param string $from   Hex colour.
	 * @param string $to     Hex colour.
	 * @param float  $amount How far towards $to, 0 to 1.
	 *
	 * @return string Hex colour.
	 */
	private static function blend( $from, $to, $amount ) {
		$a = self::to_rgb( $from );
		$b = self::to_rgb( $to );

		if ( ! $a || ! $b ) {
			return $from;
		}

		$amount = max( 0.0, min( 1.0, (float) $amount ) );

		return sprintf(
			'#%02x%02x%02x',
			(int) round( $a[0] + ( ( $b[0] - $a[0] ) * $amount ) ),
			(int) round( $a[1] + ( ( $b[1] - $a[1] ) * $amount ) ),
			(int) round( $a[2] + ( ( $b[2] - $a[2] ) * $amount ) )
		);
	}

	/**
	 * WCAG relative luminance, 0 to 1.
	 *
	 * @param string $hex Hex colour.
	 *
	 * @return float
	 */
	private static function luminance( $hex ) {
		$rgb = self::to_rgb( $hex );

		if ( ! $rgb ) {
			return 0.0;
		}

		$channels = [];
		foreach ( $rgb as $value ) {
			$value      = $value / 255;
			$channels[] = $value <= 0.03928
				? $value / 12.92
				: pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}

	/**
	 * Saturation, 0 to 1.
	 *
	 * @param string $hex Hex colour.
	 *
	 * @return float
	 */
	private static function saturation( $hex ) {
		$rgb = self::to_rgb( $hex );

		if ( ! $rgb ) {
			return 0.0;
		}

		$max = max( $rgb ) / 255;
		$min = min( $rgb ) / 255;

		if ( $max === $min ) {
			return 0.0;
		}

		$lightness = ( $max + $min ) / 2;

		return $lightness > 0.5
			? ( $max - $min ) / ( 2.0 - $max - $min )
			: ( $max - $min ) / ( $max + $min );
	}

	/**
	 * Normalise a colour to six-digit hex.
	 *
	 * Palettes carry hex, rgb() and rgba(); anything else is skipped rather
	 * than guessed at.
	 *
	 * @param string $color Colour as the palette declares it.
	 *
	 * @return string Hex colour, or an empty string.
	 */
	private static function to_hex( $color ) {
		$color = trim( (string) $color );

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color ) ) {
			$color = strtolower( $color );

			if ( strlen( $color ) === 4 ) {
				return '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
			}

			return $color;
		}

		if ( preg_match( '/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i', $color, $match ) ) {
			return sprintf(
				'#%02x%02x%02x',
				min( 255, (int) $match[1] ),
				min( 255, (int) $match[2] ),
				min( 255, (int) $match[3] )
			);
		}

		return '';
	}

	/**
	 * Split a hex colour into channels.
	 *
	 * @param string $hex Hex colour.
	 *
	 * @return int[]|null
	 */
	private static function to_rgb( $hex ) {
		$hex = self::to_hex( $hex );

		if ( ! $hex ) {
			return null;
		}

		return [
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) ),
		];
	}
}
