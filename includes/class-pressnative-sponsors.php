<?php
/**
 * Direct Native Sponsorship: Custom Post Type and helpers for BlockSponsor.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Sponsors
 */
class PressNative_Sponsors {

	const POST_TYPE = 'pressnative_sponsor';
	const META_KEY_URL = '_pressnative_sponsor_url';
	const HEIGHT_ASPECT_RATIO = 0.3;

	/**
	 * Register the sponsor CPT and meta box.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the pressnative_sponsor post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'               => _x( 'Sponsors', 'post type general name', 'pressnative-apps' ),
					'singular_name'      => _x( 'Sponsor', 'post type singular name', 'pressnative-apps' ),
					'menu_name'          => _x( 'Sponsors', 'admin menu', 'pressnative-apps' ),
					'add_new'            => _x( 'Add New', 'sponsor', 'pressnative-apps' ),
					'add_new_item'       => __( 'Add New Sponsor', 'pressnative-apps' ),
					'edit_item'          => __( 'Edit Sponsor', 'pressnative-apps' ),
					'new_item'           => __( 'New Sponsor', 'pressnative-apps' ),
					'view_item'          => __( 'View Sponsor', 'pressnative-apps' ),
					'search_items'       => __( 'Search Sponsors', 'pressnative-apps' ),
					'not_found'          => __( 'No sponsors found.', 'pressnative-apps' ),
					'not_found_in_trash' => __( 'No sponsors found in Trash.', 'pressnative-apps' ),
				),
				'public'             => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'          => 'dashicons-megaphone',
				'capability_type'    => 'post',
				'supports'           => array( 'title', 'thumbnail' ),
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Add meta box for sponsor URL.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_meta_box( $post_type ) {
		if ( $post_type !== self::POST_TYPE ) {
			return;
		}
		add_meta_box(
			'pressnative_sponsor_url',
			__( 'Sponsor URL', 'pressnative-apps' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal'
		);
	}

	/**
	 * Render the sponsor URL meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'pressnative_sponsor_url_nonce', 'pressnative_sponsor_url_nonce' );
		$url = get_post_meta( $post->ID, self::META_KEY_URL, true );
		?>
		<p>
			<label for="pressnative_sponsor_url"><?php esc_html_e( 'Click URL', 'pressnative-apps' ); ?></label><br />
			<input type="url" id="pressnative_sponsor_url" name="pressnative_sponsor_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://" />
			<span class="description"><?php esc_html_e( 'URL to open when the sponsor banner is tapped in the app.', 'pressnative-apps' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Save sponsor URL meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['pressnative_sponsor_url_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressnative_sponsor_url_nonce'] ) ), 'pressnative_sponsor_url_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = isset( $_POST['pressnative_sponsor_url'] ) ? esc_url_raw( wp_unslash( $_POST['pressnative_sponsor_url'] ) ) : '';
		update_post_meta( $post_id, self::META_KEY_URL, $url );
	}

	/**
	 * Get one random published sponsor as a BlockSponsor content array, or null.
	 *
	 * Uses featured image for image_url, title for sponsor_name, and meta for click_url.
	 *
	 * @return array|null BlockSponsor content array (type, image_url, click_url, sponsor_name, height_aspect_ratio) or null.
	 */
	public static function get_random_active_sponsor() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'rand',
				'fields'         => 'ids',
			)
		);
		if ( empty( $posts ) ) {
			return null;
		}
		$post_id = $posts[0];
		$url     = get_post_meta( $post_id, self::META_KEY_URL, true );
		if ( empty( $url ) ) {
			return null;
		}
		$thumb_id = get_post_thumbnail_id( $post_id );
		$image_url = '';
		if ( $thumb_id ) {
			$image = wp_get_attachment_image_src( $thumb_id, 'large' );
			if ( ! empty( $image[0] ) ) {
				$image_url = $image[0];
			}
		}
		if ( empty( $image_url ) ) {
			return null;
		}
		$post = get_post( $post_id );
		$sponsor_name = $post ? get_the_title( $post ) : '';
		return array(
			'type'               => 'BlockSponsor',
			'image_url'          => $image_url,
			'click_url'          => $url,
			'sponsor_name'       => $sponsor_name,
			'height_aspect_ratio' => (float) self::HEIGHT_ASPECT_RATIO,
		);
	}
}

PressNative_Sponsors::init();
