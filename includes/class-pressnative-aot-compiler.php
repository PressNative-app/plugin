<?php
/**
 * Ahead-Of-Time compiler that pre-builds SDUI JSON on every post save.
 *
 * Hooks into save_post so the REST API never has to parse HTML at request
 * time — it reads a single post-meta row instead.
 *
 * Also provides batch warm-up via WP-Cron and an admin-triggered bulk
 * recompile, both with progress tracking stored in options.
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
	 * Post-meta key storing the unix timestamp of last compilation.
	 */
	const META_KEY_COMPILED_AT = '_pressnative_sdui_compiled_at';

	/**
	 * Option key for batch progress tracking.
	 */
	const OPTION_PROGRESS = 'pressnative_aot_progress';

	/**
	 * WP-Cron hook name for batch processing.
	 */
	const CRON_HOOK = 'pressnative_aot_batch';

	/**
	 * Posts to compile per cron batch.
	 */
	const BATCH_SIZE = 20;

	/**
	 * Post types eligible for AOT compilation.
	 */
	private const ALLOWED_POST_TYPES = array( 'post', 'page', 'product' );

	/**
	 * WooCommerce product shortcodes that get replaced with markers.
	 */
	private const WC_PRODUCT_PATTERN = '/\[(?:product_page|product|add_to_cart)\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i';

	/**
	 * Shared parser instance (lazy-loaded).
	 *
	 * @var PressNative_DOM_Parser|null
	 */
	private static $parser = null;

	/**
	 * Register all hooks. Call once from the main plugin file.
	 */
	public static function init(): void {
		add_action( 'save_post', array( new self(), 'compile_on_save' ), 20, 2 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_batch' ) );

		// When a product is updated, invalidate caches for posts referencing it.
		add_action( 'save_post_product', array( __CLASS__, 'invalidate_referencing_posts' ), 25, 1 );

		// Admin action: manual recompile trigger.
		add_action( 'admin_post_pressnative_aot_recompile', array( __CLASS__, 'handle_recompile_action' ) );
	}

	// ─── save_post Hook ─────────────────────────────────────────────────

	/**
	 * Fires on every non-autosave, non-revision save_post.
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

		if ( empty( $post->post_content ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			delete_post_meta( $post_id, self::META_KEY_COMPILED_AT );
			return;
		}

		self::compile_single( $post_id, $post->post_content );
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
		return is_array( $blocks ) ? $blocks : null;
	}

	/**
	 * On-demand fallback: renders, parses, caches, and returns the blocks.
	 *
	 * @param int $post_id Post ID.
	 * @return array Block array (may be empty).
	 */
	public static function compile_on_demand( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return array();
		}
		return self::compile_single( $post_id, $post->post_content );
	}

	/**
	 * Invalidate the SDUI cache for a given post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
		delete_post_meta( $post_id, self::META_KEY_COMPILED_AT );
	}

	/**
	 * Returns the unix timestamp when a post was last compiled, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return int|null
	 */
	public static function get_compiled_at( int $post_id ): ?int {
		$ts = get_post_meta( $post_id, self::META_KEY_COMPILED_AT, true );
		return $ts ? (int) $ts : null;
	}

	// ─── WooCommerce Shortcode Interception ─────────────────────────────

	/**
	 * Replace WooCommerce product shortcodes with HTML comment markers
	 * that survive do_shortcode() and are picked up by the DOM parser
	 * as ProductReference blocks.
	 *
	 * @param string $html Content after do_blocks().
	 * @return string Content with product shortcodes replaced.
	 */
	private static function replace_product_shortcodes( string $html ): string {
		return preg_replace_callback(
			self::WC_PRODUCT_PATTERN,
			function ( array $m ): string {
				return '<!--PRESSNATIVE_PRODUCT:' . $m[1] . '-->';
			},
			$html
		);
	}

	// ─── Core Compilation ───────────────────────────────────────────────

	/**
	 * Compile a single post's content and persist the result.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $raw_content Raw post_content.
	 * @return array The compiled block array.
	 */
	private static function compile_single( int $post_id, string $raw_content ): array {
		$html = $raw_content;

		if ( function_exists( 'do_blocks' ) ) {
			$html = do_blocks( $html );
		}

		// Intercept WooCommerce product shortcodes BEFORE do_shortcode()
		// renders them into heavy HTML that would trigger the bailout rule.
		$html = self::replace_product_shortcodes( $html );

		$html = do_shortcode( $html );

		$blocks = self::get_parser()->parse_html( $html );
		$json   = wp_json_encode( $blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		update_post_meta( $post_id, self::META_KEY, $json );
		update_post_meta( $post_id, self::META_KEY_COMPILED_AT, time() );

		return $blocks;
	}

	// ─── Product Cross-Invalidation ─────────────────────────────────────

	/**
	 * When a WooCommerce product is saved, find any posts/pages whose cached
	 * JSON references that product and clear their caches so the next request
	 * re-compiles with fresh product data.
	 *
	 * @param int $product_id Product post ID.
	 */
	public static function invalidate_referencing_posts( int $product_id ): void {
		global $wpdb;

		$like = '%"product_id":' . $product_id . '%';
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_KEY,
				$like
			)
		);

		foreach ( $post_ids as $pid ) {
			self::invalidate( (int) $pid );
		}
	}

	// ─── Batch / Warm-Up ────────────────────────────────────────────────

	/**
	 * Schedule a full warm-up. Called on plugin activation or from the
	 * admin "Recompile All" button.
	 */
	public static function schedule_warmup(): void {
		$total = self::count_eligible_posts();

		update_option( self::OPTION_PROGRESS, array(
			'status'   => 'running',
			'total'    => $total,
			'compiled' => 0,
			'offset'   => 0,
			'started'  => time(),
			'updated'  => time(),
		), false );

		// Clear any previously scheduled batch.
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( $total > 0 ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		} else {
			update_option( self::OPTION_PROGRESS, array(
				'status'   => 'complete',
				'total'    => 0,
				'compiled' => 0,
				'offset'   => 0,
				'started'  => time(),
				'updated'  => time(),
			), false );
		}
	}

	/**
	 * Process a single batch of posts. Schedules the next batch if needed.
	 */
	public static function process_batch(): void {
		$progress = get_option( self::OPTION_PROGRESS, array() );
		if ( empty( $progress ) || 'running' !== ( $progress['status'] ?? '' ) ) {
			return;
		}

		$offset   = (int) ( $progress['offset'] ?? 0 );
		$compiled = (int) ( $progress['compiled'] ?? 0 );

		$post_ids = self::get_eligible_post_ids( self::BATCH_SIZE, $offset );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && ! empty( $post->post_content ) ) {
				self::compile_single( (int) $post_id, $post->post_content );
			}
			++$compiled;
		}

		$new_offset = $offset + self::BATCH_SIZE;
		$is_done    = count( $post_ids ) < self::BATCH_SIZE;

		$progress['compiled'] = $compiled;
		$progress['offset']   = $new_offset;
		$progress['updated']  = time();

		if ( $is_done ) {
			$progress['status'] = 'complete';
		}

		update_option( self::OPTION_PROGRESS, $progress, false );

		if ( ! $is_done ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK );
		}
	}

	/**
	 * Get the current warm-up / recompile progress.
	 *
	 * @return array{status:string,total:int,compiled:int,offset:int,started:int,updated:int}
	 */
	public static function get_progress(): array {
		$default = array(
			'status'   => 'idle',
			'total'    => 0,
			'compiled' => 0,
			'offset'   => 0,
			'started'  => 0,
			'updated'  => 0,
		);
		$progress = get_option( self::OPTION_PROGRESS, $default );
		return wp_parse_args( $progress, $default );
	}

	/**
	 * Get cache statistics for the admin dashboard.
	 *
	 * @return array{total:int,cached:int,uncached:int,percent:float}
	 */
	public static function get_cache_stats(): array {
		$total   = self::count_eligible_posts();
		$cached  = self::count_cached_posts();
		$percent = $total > 0 ? round( ( $cached / $total ) * 100, 1 ) : 0.0;
		return array(
			'total'    => $total,
			'cached'   => $cached,
			'uncached' => $total - $cached,
			'percent'  => $percent,
		);
	}

	// ─── Admin Action Handler ───────────────────────────────────────────

	/**
	 * Handle the admin_post action for bulk recompile.
	 */
	public static function handle_recompile_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'pressnative_aot_recompile' );

		self::schedule_warmup();

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'pressnative', 'aot' => 'started' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// ─── Queries ────────────────────────────────────────────────────────

	/**
	 * Count all published posts/pages/products eligible for compilation.
	 *
	 * @return int
	 */
	private static function count_eligible_posts(): int {
		global $wpdb;
		$types = self::sql_in_list( self::ALLOWED_POST_TYPES );
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			   AND post_type IN ({$types})
			   AND post_content != ''"
		);
	}

	/**
	 * Count how many eligible posts already have an AOT cache entry.
	 *
	 * @return int
	 */
	private static function count_cached_posts(): int {
		global $wpdb;
		$types = self::sql_in_list( self::ALLOWED_POST_TYPES );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_status = 'publish'
				   AND p.post_type IN ({$types})
				   AND p.post_content != ''",
				self::META_KEY
			)
		);
	}

	/**
	 * Fetch a batch of post IDs eligible for compilation.
	 *
	 * @param int $limit  Max IDs to return.
	 * @param int $offset Offset for pagination.
	 * @return int[]
	 */
	private static function get_eligible_post_ids( int $limit, int $offset ): array {
		global $wpdb;
		$types = self::sql_in_list( self::ALLOWED_POST_TYPES );
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_status = 'publish'
				   AND post_type IN ({$types})
				   AND post_content != ''
				 ORDER BY ID ASC
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		return array_map( 'intval', $rows );
	}

	/**
	 * Build a SQL-safe IN (...) value list from an array of strings.
	 *
	 * @param string[] $items Items to quote.
	 * @return string e.g. "'post','page','product'"
	 */
	private static function sql_in_list( array $items ): string {
		global $wpdb;
		$escaped = array_map( function ( string $item ) use ( $wpdb ): string {
			return $wpdb->prepare( '%s', $item );
		}, $items );
		return implode( ',', $escaped );
	}

	// ─── Internal Helpers ───────────────────────────────────────────────

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
