<?php
/**
 * Tests for the patterns the blocker rewrites the page with.
 *
 * These are the patterns that once emptied a whole site: preg_replace_callback()
 * returns null when PCRE runs out of backtracking room, and that null was
 * served as the page body. Nothing about it is visible in the code, and nothing
 * about it shows up on a small test page either, so the sizes below are
 * deliberately realistic — a builder-made page really does run past a megabyte.
 *
 * The class itself needs WordPress; its patterns do not, so they are exercised
 * directly.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

require_once __DIR__ . '/../includes/class-blocker.php';

/**
 * Every pattern the blocker runs, in the order it runs them.
 *
 * @return array<string, string>
 */
function piensa_test_blocker_patterns() {
	return [
		'script'        => Piensa_Cookie_Consent_Blocker::SCRIPT_PATTERN,
		'inline script' => Piensa_Cookie_Consent_Blocker::INLINE_SCRIPT_PATTERN,
		'img'           => Piensa_Cookie_Consent_Blocker::IMG_PATTERN,
		'link'          => Piensa_Cookie_Consent_Blocker::LINK_PATTERN,
		'iframe'        => Piensa_Cookie_Consent_Blocker::IFRAME_PATTERN,
	];
}

return function ( $assert ) {
	$script = Piensa_Cookie_Consent_Blocker::SCRIPT_PATTERN;
	$iframe = Piensa_Cookie_Consent_Blocker::IFRAME_PATTERN;
	$img    = Piensa_Cookie_Consent_Blocker::IMG_PATTERN;

	// Both quote styles. Only double quotes were accepted before, so a tag
	// written with single quotes was served to the visitor unblocked — the
	// plugin reported it was blocking and it was not.
	$assert(
		1 === preg_match( $script, '<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>' ),
		'a double-quoted script src is matched'
	);
	$assert(
		1 === preg_match( $script, "<script src='https://www.googletagmanager.com/gtag/js?id=G-1'></script>" ),
		'a single-quoted script src is matched'
	);
	$assert(
		1 === preg_match( $img, "<img src='https://tracker.test/pixel.gif' alt=''>" ),
		'a single-quoted img src is matched'
	);

	// A script the plugin has already neutralised must not be picked up again.
	$assert(
		0 === preg_match( $script, '<script type="text/plain" data-category="analytics" src="https://x.test/a.js"></script>' ),
		'an already-blocked script is left alone'
	);

	// The URL is captured in group 1, which every callback relies on.
	preg_match( $script, "<script src='https://x.test/a.js'></script>", $matches );
	$assert(
		isset( $matches[1] ) && 'https://x.test/a.js' === $matches[1],
		'the src is captured in the first group'
	);

	// An ordinary embed still gets matched whole, opening tag to closing tag,
	// because the placeholder wraps the entire element.
	$embed = '<iframe src="https://www.youtube.com/embed/abc" width="560"></iframe>';
	$assert( 1 === preg_match( $iframe, $embed, $found ) && $found[0] === $embed, 'a complete iframe is matched whole' );

	// The page that used to go blank: an iframe with no closing tag, followed
	// by a builder's worth of markup. The lazy pattern scanned to the end of
	// the document and back for every start position until PCRE gave up.
	$huge  = '<html><body><iframe src="https://www.youtube.com/embed/abc">';
	$huge .= str_repeat( '<div class="brxe-block"><p>contenido de la seccion</p></div>', 40000 );
	$huge .= '</body></html>';

	$assert( strlen( $huge ) > 2000000, 'the pathological page is over 2 MB' );

	foreach ( piensa_test_blocker_patterns() as $name => $pattern ) {
		$result = preg_replace_callback(
			$pattern,
			static function ( $matches ) {
				return $matches[0];
			},
			$huge
		);

		$assert( null !== $result, "$name: a 2 MB page does not defeat the pattern" );
		$assert( PREG_NO_ERROR === preg_last_error(), "$name: PCRE reports no error on a 2 MB page" );
	}

	// The same page with the iframe properly closed, to be sure the unrolled
	// body still matches across nested markup rather than stopping at the
	// first inner tag.
	$nested = '<iframe src="https://maps.google.com/x"><div><span>a</span></div></iframe>';
	$assert( 1 === preg_match( $iframe, $nested, $found ) && $found[0] === $nested, 'an iframe wrapping nested markup is matched whole' );

	// An inline script body is captured in group 2, and a script containing
	// markup-like text does not end the match early.
	$inline = '<script>var a = "</div>"; gtag("consent");</script>';
	$assert(
		1 === preg_match( Piensa_Cookie_Consent_Blocker::INLINE_SCRIPT_PATTERN, $inline, $found )
			&& false !== strpos( $found[2], 'gtag' ),
		'an inline script body is captured past markup-like text'
	);
};
