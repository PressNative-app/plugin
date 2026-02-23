<?php
/**
 * Thin wrapper for QR code SVG generation.
 * Uses splitbrain/php-qrcode (MIT) for encoding.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

// Load the bundled QR encoder (splitbrain/php-qrcode, MIT licensed).
require_once __DIR__ . '/QRCode.php';

use splitbrain\phpQRCode\QRCode;

/**
 * Class QrEncoder
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- thin wrapper around third-party lib (splitbrain/php-qrcode)
class QrEncoder {

	/**
	 * Encode a string to QR code SVG (error correction level M).
	 *
	 * @param string $data   Data to encode (e.g. URL).
	 * @param string $ecc    Error correction: 'qrl', 'qrm', 'qrq', 'qrh'. Default 'qrm'.
	 * @return string SVG markup.
	 */
	public static function to_svg( $data, $ecc = 'qrm' ) {
		return QRCode::svg( $data, array( 's' => $ecc ) );
	}
}
