<?php
/**
 * Editorial push: per-post “Notify app users?” metabox + preference gating.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Editorial_Push
 */
class PressNative_Editorial_Push {

	const META_NOTIFY   = '_pressnative_notify_push';
	const META_AUDIENCE = '_pressnative_notify_audience'; // all | categories

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Whether site-level prefs allow this post type to notify.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function site_allows_type( $post_type ) {
		$prefs = PressNative_Options::get_notification_preferences();
		if ( empty( $prefs['enabled'] ) ) {
			return false;
		}
		$map = array(
			'post'    => 'new_posts',
			'page'    => 'new_pages',
			'product' => 'new_products',
		);
		$type_key = isset( $map[ $post_type ] ) ? $map[ $post_type ] : 'new_posts';
		return ! empty( $prefs['types'][ $type_key ]['enabled'] );
	}

	/**
	 * Register metabox on post/page/product.
	 *
	 * @return void
	 */
	public static function add_metabox() {
		foreach ( array( 'post', 'page', 'product' ) as $screen ) {
			add_meta_box(
				'pressnative_editorial_push',
				__( 'PressNative Push', 'pressnative-apps' ),
				array( __CLASS__, 'render_metabox' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Metabox UI.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'pressnative_editorial_push', 'pressnative_editorial_push_nonce' );
		$notify   = get_post_meta( $post->ID, self::META_NOTIFY, true );
		$audience = get_post_meta( $post->ID, self::META_AUDIENCE, true );
		if ( '' === $notify ) {
			$notify = '1'; // Default: notify when publishing (still gated by site prefs).
		}
		if ( '' === $audience ) {
			$audience = 'all';
		}
		$allowed = self::site_allows_type( $post->post_type );
		?>
		<p>
			<label>
				<input type="checkbox" name="pressnative_notify_push" value="1" <?php checked( $notify, '1' ); ?> <?php disabled( ! $allowed ); ?> />
				<?php esc_html_e( 'Notify app users when this is published', 'pressnative-apps' ); ?>
			</label>
		</p>
		<?php if ( ! $allowed ) : ?>
			<p class="description"><?php esc_html_e( 'Enable this content type under PressNative → Push Notifications preferences.', 'pressnative-apps' ); ?></p>
		<?php endif; ?>
		<p>
			<label for="pressnative_notify_audience"><?php esc_html_e( 'Audience', 'pressnative-apps' ); ?></label><br />
			<select name="pressnative_notify_audience" id="pressnative_notify_audience" <?php disabled( ! $allowed ); ?>>
				<option value="all" <?php selected( $audience, 'all' ); ?>><?php esc_html_e( 'All subscribers', 'pressnative-apps' ); ?></option>
				<option value="categories" <?php selected( $audience, 'categories' ); ?>><?php esc_html_e( 'Matching post categories only', 'pressnative-apps' ); ?></option>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Quiet hours and device preferences are respected by PressNative Cloud.', 'pressnative-apps' ); ?></p>
		<?php
	}

	/**
	 * Persist metabox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['pressnative_editorial_push_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressnative_editorial_push_nonce'] ) ), 'pressnative_editorial_push' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$notify = isset( $_POST['pressnative_notify_push'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_NOTIFY, $notify );
		$audience = isset( $_POST['pressnative_notify_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['pressnative_notify_audience'] ) ) : 'all';
		if ( ! in_array( $audience, array( 'all', 'categories' ), true ) ) {
			$audience = 'all';
		}
		update_post_meta( $post_id, self::META_AUDIENCE, $audience );
	}

	/**
	 * Whether this post should trigger a push on publish.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function should_notify( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( ! self::site_allows_type( $post->post_type ) ) {
			return false;
		}
		$notify = get_post_meta( $post->ID, self::META_NOTIFY, true );
		if ( '' === $notify ) {
			return true;
		}
		return '1' === $notify;
	}

	/**
	 * Category slugs for audience filtering (empty = all).
	 *
	 * @param WP_Post $post Post.
	 * @return string[]|null
	 */
	public static function get_audience_categories( $post ) {
		$audience = get_post_meta( $post->ID, self::META_AUDIENCE, true );
		if ( 'categories' !== $audience ) {
			return null;
		}
		$terms = get_the_terms( $post->ID, 'category' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return array_values( wp_list_pluck( $terms, 'slug' ) );
	}
}
