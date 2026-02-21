<?php
/**
 * Ahead-Of-Time compiler that pre-builds SDUI JSON on every post save.
 *
 * Hooks into save_post so the REST API never has to parse HTML at request
 * time — it reads a single post-meta row instead.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_AOT_Compiler
 */
class PressNative_AOT_Compiler {

	/**
	 * Post-meta key that stores the pre-compiled JSON string.
	 */
	const META_KEY = '_pressnative_sdui_cache';

	/**
	 * Post types eligible for AOT compilation.
	 */
	private const ALLOWED_POST_TYPES = array( 'post', 'page', 'product' );

	/**
	 * Shared parser instance (lazy-loaded).
	 *
	 * @var PressNative_DOM_Parser|null
	 */
	private static $parser = null;

	/**
	 * Register the save_post hook. Call once from the main plugin file.
	 */
	public static function init(): void {
		add_action( 'save_post', array( new self(), 'compile_on_save' ), 20, 2 );
	}

	// ─── Hook Callback ──────────────────────────────────────────────────

	/**
	 * Fires on every non-autosave, non-revision save_post.
	 *
	 * Renders the post content through do_blocks() + do_shortcode(), walks
	 * the resulting HTML with PressNative_DOM_Parser, and stores the JSON
	 * AST in post meta so the REST API can serve it without any parsing.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function compile_on_save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::ALLOWED_POST_TYPES, true ) ) {
			return;
		}

		$raw_content = $post->post_content;

		if ( empty( $raw_content ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$blocks = $this->render_and_parse( $raw_content );
		$json   = wp_json_encode( $blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		update_post_meta( $post_id, self::META_KEY, $json );
	}

	// ─── Public API ─────────────────────────────────────────────────────

	/**
	 * Retrieve previously compiled blocks from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Decoded block array, or null when the cache is empty/invalid.
	 */
	public static function get_cached_blocks( int $post_id ): ?array {
		$json = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $json ) || ! is_string( $json ) ) {
			return null;
		}

		$blocks = json_decode( $json, true );
		if ( ! is_array( $blocks ) ) {
			return null;
		}

		return $blocks;
	}

	/**
	 * On-demand fallback: renders, parses, caches, and returns the blocks.
	 *
	 * Used by the REST API when the AOT cache is empty (e.g. posts created
	 * before the plugin was installed, or after a cache flush).
	 *
	 * @param int $post_id Post ID.
	 * @return array Block array (may be empty).
	 */
	public static function compile_on_demand( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return array();
		}

		$instance = new self();
		$blocks   = $instance->render_and_parse( $post->post_content );
		$json     = wp_json_encode( $blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		update_post_meta( $post_id, self::META_KEY, $json );

		return $blocks;
	}

	/**
	 * Invalidate the SDUI cache for a given post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}

	// ─── Internal ───────────────────────────────────────────────────────

	/**
	 * Render raw post_content to final HTML and parse it into SDUI blocks.
	 *
	 * @param string $raw_content Raw post_content (Gutenberg blocks + shortcodes).
	 * @return array Flat array of block objects.
	 */
	private function render_and_parse( string $raw_content ): array {
		$html = $raw_content;

		if ( function_exists( 'do_blocks' ) ) {
			$html = do_blocks( $html );
		}

		$html = do_shortcode( $html );

		return self::get_parser()->parse_html( $html );
	}

	/**
	 * Lazy-load a shared DOM parser instance.
	 *
	 * @return PressNative_DOM_Parser
	 */
	private static function get_parser(): PressNative_DOM_Parser {
		if ( null === self::$parser ) {
			self::$parser = new PressNative_DOM_Parser();
		}
		return self::$parser;
	}
}
