<?php
/**
 * Bulletproof HTML-to-JSON AST parser using PHP's DOMDocument.
 *
 * Replaces all regex / string-matching content parsing with a proper DOM walk.
 * Output block types align with www/contract.json (BlockText, BlockImage,
 * BlockList, BlockHtml).
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_DOM_Parser
 */
class PressNative_DOM_Parser {

	/**
	 * Regex that matches the AOT compiler's product marker comments.
	 * e.g. <!--PRESSNATIVE_PRODUCT:76-->
	 */
	private const PRODUCT_MARKER_PATTERN = '/^PRESSNATIVE_PRODUCT:(\d+)$/';

	/**
	 * Tags that must never be recursed into — their full outer HTML becomes
	 * a BlockHtml so the native app can render them inside a micro-WebView.
	 */
	private const BAILOUT_TAGS = array(
		'table',
		'form',
		'iframe',
		'script',
		'style',
		'video',
	);

	/**
	 * If a <div> carries a class that contains any of these prefixes the
	 * entire node is bailed out as BlockHtml.
	 */
	private const BAILOUT_CLASS_PREFIXES = array(
		'elementor-',
		'woocommerce-',
		'wp-block-',
	);

	/**
	 * Parse an HTML string into an ordered array of SDUI block objects.
	 *
	 * @param string $html_string Rendered HTML (post do_blocks + do_shortcode).
	 * @return array<int, array> Flat array of block objects.
	 */
	public function parse_html( string $html_string ): array {
		$html_string = trim( $html_string );
		if ( '' === $html_string ) {
			return array();
		}

		$previous_errors = libxml_use_internal_errors( true );

		$doc = new DOMDocument( '1.0', 'UTF-8' );

		$wrapped = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
			. '<body>' . $html_string . '</body>';

		$loaded = $doc->loadHTML( $wrapped );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( ! $loaded ) {
			return array();
		}

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body || ! $body->hasChildNodes() ) {
			return array();
		}

		$blocks = array();
		foreach ( $body->childNodes as $node ) {
			$blocks = array_merge( $blocks, $this->walk_node( $node ) );
		}

		return $blocks;
	}

	// ─── Private Recursive Walker ───────────────────────────────────────

	/**
	 * Recursively walks a single DOMNode and returns zero or more SDUI blocks.
	 *
	 * @param DOMNode $node The DOM node to process.
	 * @return array<int, array> Block objects produced from this node and its descendants.
	 */
	private function walk_node( DOMNode $node ): array {

		// ── #text nodes ─────────────────────────────────────────────────
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$text = trim( $node->textContent );
			if ( '' === $text ) {
				return array();
			}
			return array(
				array(
					'type'         => 'BlockText',
					'html_content' => $text,
					'text'         => $text,
					'style'        => 'paragraph',
				),
			);
		}

		// ── #comment nodes — detect product markers, skip the rest ─────
		if ( XML_COMMENT_NODE === $node->nodeType ) {
			$data = trim( $node->textContent );
			if ( preg_match( self::PRODUCT_MARKER_PATTERN, $data, $m ) ) {
				return array(
					array(
						'type'       => 'ProductReference',
						'product_id' => (int) $m[1],
					),
				);
			}
			return array();
		}

		// Only process element nodes from here on.
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return array();
		}

		/** @var DOMElement $node */
		$tag = strtolower( $node->nodeName );

		// ── Bailout: dangerous / complex tags → BlockHtml ───────────────
		if ( in_array( $tag, self::BAILOUT_TAGS, true ) ) {
			return array(
				array(
					'type' => 'BlockHtml',
					'html' => $node->ownerDocument->saveHTML( $node ),
				),
			);
		}

		// ── Bailout: <div> with page-builder / block-editor classes ─────
		if ( 'div' === $tag && $node->hasAttributes() ) {
			$class = $node->getAttribute( 'class' );
			if ( '' !== $class && $this->class_triggers_bailout( $class ) ) {
				return array(
					array(
						'type' => 'BlockHtml',
						'html' => $node->ownerDocument->saveHTML( $node ),
					),
				);
			}
		}

		// ── Paragraphs & Headings (<p>, <h1>–<h6>) → BlockText ─────────
		if ( 'p' === $tag || (bool) preg_match( '/^h[1-6]$/', $tag ) ) {
			$inner_html = $this->get_inner_html( $node );
			$text       = trim( $node->textContent );
			if ( '' === $text ) {
				return array();
			}
			$style = ( 'p' === $tag ) ? 'paragraph' : $tag;
			return array(
				array(
					'type'         => 'BlockText',
					'html_content' => trim( $inner_html ),
					'text'         => $text,
					'style'        => $style,
				),
			);
		}

		// ── Images (<img>) → BlockImage ────────────────────────────────
		if ( 'img' === $tag ) {
			return $this->parse_img_element( $node );
		}

		// ── Lists (<ul>, <ol>) → BlockList ─────────────────────────────
		if ( 'ul' === $tag || 'ol' === $tag ) {
			return $this->parse_list_element( $node );
		}

		// ── Blockquotes (<blockquote>) → BlockQuote ────────────────────
		if ( 'blockquote' === $tag ) {
			return $this->parse_blockquote_element( $node );
		}

		// ── Default: recurse into children (div, section, figure, …) ───
		$blocks = array();
		foreach ( $node->childNodes as $child ) {
			$blocks = array_merge( $blocks, $this->walk_node( $child ) );
		}
		return $blocks;
	}

	// ─── Element-Specific Parsers ───────────────────────────────────────

	/**
	 * @param DOMElement $node An <img> element.
	 * @return array Zero or one BlockImage objects.
	 */
	private function parse_img_element( DOMElement $node ): array {
		if ( ! $node->hasAttributes() ) {
			return array();
		}

		$src = $node->getAttribute( 'src' );
		if ( '' === $src ) {
			return array();
		}

		$width  = $node->hasAttribute( 'width' )  ? (int) $node->getAttribute( 'width' )  : null;
		$height = $node->hasAttribute( 'height' ) ? (int) $node->getAttribute( 'height' ) : null;
		$alt    = $node->hasAttribute( 'alt' )    ? $node->getAttribute( 'alt' )           : '';

		return array(
			array(
				'type'   => 'BlockImage',
				'url'    => $src,
				'width'  => $width,
				'height' => $height,
				'alt'    => $alt,
			),
		);
	}

	/**
	 * @param DOMElement $node A <ul> or <ol> element.
	 * @return array Zero or one BlockList objects.
	 */
	private function parse_list_element( DOMElement $node ): array {
		$items = array();
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			if ( 'li' !== strtolower( $child->nodeName ) ) {
				continue;
			}
			$inner = trim( $this->get_inner_html( $child ) );
			if ( '' !== $inner ) {
				$items[] = $inner;
			}
		}

		if ( empty( $items ) ) {
			return array();
		}

		return array(
			array(
				'type'  => 'BlockList',
				'items' => $items,
			),
		);
	}

	/**
	 * @param DOMElement $node A <blockquote> element.
	 * @return array Zero or one BlockQuote objects.
	 */
	private function parse_blockquote_element( DOMElement $node ): array {
		$author = '';

		$cites = $node->getElementsByTagName( 'cite' );
		if ( $cites->length > 0 ) {
			$cite_node = $cites->item( 0 );
			$author    = trim( $cite_node->textContent );
		}

		$full_text = trim( $node->textContent );
		if ( '' !== $author ) {
			$full_text = trim( str_replace( $author, '', $full_text ) );
		}

		if ( '' === $full_text ) {
			return array();
		}

		return array(
			array(
				'type'   => 'BlockQuote',
				'text'   => $full_text,
				'author' => $author,
			),
		);
	}

	// ─── Helpers ────────────────────────────────────────────────────────

	/**
	 * Returns true if a class string contains any bailout prefix.
	 *
	 * @param string $class_attr The element's class attribute value.
	 * @return bool
	 */
	private function class_triggers_bailout( string $class_attr ): bool {
		foreach ( self::BAILOUT_CLASS_PREFIXES as $prefix ) {
			if ( false !== strpos( $class_attr, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Serialises all child nodes of an element back to an HTML string.
	 *
	 * This preserves inline formatting (<b>, <i>, <a>, <em>, <strong>)
	 * so the native app's rich-text renderer can interpret it.
	 *
	 * @param DOMNode $node Parent node whose children we want as HTML.
	 * @return string The concatenated inner HTML.
	 */
	private function get_inner_html( DOMNode $node ): string {
		if ( ! $node->hasChildNodes() ) {
			return '';
		}
		$html = '';
		$doc  = $node->ownerDocument;
		foreach ( $node->childNodes as $child ) {
			$html .= $doc->saveHTML( $child );
		}
		return $html;
	}
}
