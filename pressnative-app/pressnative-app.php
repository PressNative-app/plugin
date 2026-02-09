<?php
/**
 * Plugin Name: PressNative App Plugin
 * Description: REST endpoints and settings for PressNative.app.
 * Version: 1.0.0
 * Author: PressNative
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PressNative_App_Plugin {
	const OPTION_ENABLED_CATEGORIES = 'pressnative_app_enabled_categories';
	const REST_NAMESPACE            = 'pressnative/v1';
	const REST_ROUTE_HOME           = '/home';

	/**
	 * Bootstraps the plugin hooks.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Registers REST API routes for PressNative.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_HOME,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_home_page' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Returns the PressNative home screen layout.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_home_page( WP_REST_Request $request ) {
		$layout_engine = new PressNative_Layout_Engine();
		$data          = $layout_engine->get_home_layout();

		$response = new WP_REST_Response( $data );
		$response->set_header( 'Last-Updated', gmdate( 'c' ) );

		return $response;
	}

	/**
	 * Registers the settings for the admin page.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pressnative_app',
			self::OPTION_ENABLED_CATEGORIES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_category_ids' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Adds the PressNative settings page to WP-Admin.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			'PressNative App',
			'PressNative App',
			'manage_options',
			'pressnative-app',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$categories = get_categories(
			array(
				'hide_empty' => false,
			)
		);
		$enabled_ids = $this->get_enabled_category_ids();
		?>
		<div class="wrap">
			<h1>PressNative App</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'pressnative_app' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">CategoryList</th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_ENABLED_CATEGORIES ); ?>[]" value="0" />
							<?php foreach ( $categories as $category ) : ?>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_ENABLED_CATEGORIES ); ?>[]"
										value="<?php echo esc_attr( $category->term_id ); ?>"
										<?php checked( in_array( $category->term_id, $enabled_ids, true ) ); ?>
									/>
									<?php echo esc_html( $category->name ); ?>
								</label><br />
							<?php endforeach; ?>
							<p class="description">
								Select the categories that appear in the app's CategoryList component.
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitizes category IDs.
	 *
	 * @param mixed $value Submitted category IDs.
	 * @return array
	 */
	public function sanitize_category_ids( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}

		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Returns the enabled category IDs, defaulting to all categories when unset.
	 *
	 * @return array
	 */
	private function get_enabled_category_ids() {
		$option = get_option( self::OPTION_ENABLED_CATEGORIES, null );

		if ( null === $option || false === $option ) {
			$all_ids = get_categories(
				array(
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			return array_map( 'absint', $all_ids );
		}

		if ( ! is_array( $option ) ) {
			return array();
		}

		return array_values( array_map( 'absint', $option ) );
	}
}

final class PressNative_Layout_Engine {
	/**
	 * Builds the PressNative home layout response.
	 *
	 * @return array
	 */
	public function get_home_layout() {
		return array(
			'screen'     => array(
				'id'    => 'home',
				'title' => get_bloginfo( 'name' ),
			),
			'components' => array(
				$this->build_hero_carousel(),
				$this->build_category_list(),
				$this->build_post_grid(),
			),
		);
	}

	/**
	 * Builds the HeroCarousel component.
	 *
	 * @return array
	 */
	private function build_hero_carousel() {
		$items = array();

		foreach ( $this->get_featured_posts( 3 ) as $post ) {
			$item = array(
				'title'     => get_the_title( $post->ID ),
				'image_url' => $this->get_post_image_url( $post->ID, 'large' ),
				'action'    => $this->build_post_action( $post->ID ),
			);

			$subtitle = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );

			if ( '' !== $subtitle ) {
				$item['subtitle'] = $subtitle;
			}

			$items[] = $item;
		}

		return array(
			'id'     => 'hero_carousel',
			'type'   => 'HeroCarousel',
			'styles' => $this->get_default_styles(),
			'content' => array(
				'items' => $items,
			),
		);
	}

	/**
	 * Builds the CategoryList component.
	 *
	 * @return array
	 */
	private function build_category_list() {
		$categories = array();

		foreach ( $this->get_top_level_categories() as $category ) {
			$categories[] = array(
				'category_id' => (string) $category->term_id,
				'name'        => $category->name,
				'action'      => $this->build_category_action( $category->term_id ),
			);
		}

		return array(
			'id'     => 'category_list',
			'type'   => 'CategoryList',
			'styles' => $this->get_default_styles(),
			'content' => array(
				'categories' => $categories,
			),
		);
	}

	/**
	 * Builds the PostGrid component.
	 *
	 * @return array
	 */
	private function build_post_grid() {
		$posts = array();

		foreach ( $this->get_latest_posts( 10 ) as $post ) {
			$post_item = array(
				'post_id'       => (string) $post->ID,
				'title'         => get_the_title( $post->ID ),
				'thumbnail_url' => $this->get_post_image_url( $post->ID, 'medium' ),
				'action'        => $this->build_post_action( $post->ID ),
			);

			$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );

			if ( '' !== $excerpt ) {
				$post_item['excerpt'] = $excerpt;
			}

			$posts[] = $post_item;
		}

		return array(
			'id'     => 'post_grid',
			'type'   => 'PostGrid',
			'styles' => $this->get_default_styles(),
			'content' => array(
				'columns' => 2,
				'posts'   => $posts,
			),
		);
	}

	/**
	 * Returns the default style configuration.
	 *
	 * @return array
	 */
	private function get_default_styles() {
		return array(
			'colors'  => array(
				'background' => '#FFFFFF',
				'text'       => '#111111',
				'accent'     => '#FF6A00',
			),
			'padding' => array(
				'horizontal' => 16,
				'vertical'   => 16,
			),
		);
	}

	/**
	 * Queries and returns the latest posts.
	 *
	 * @param int $per_page Number of posts to include.
	 * @return array
	 */
	private function get_latest_posts( $per_page ) {
		$query = new WP_Query(
			array(
				'posts_per_page'      => absint( $per_page ),
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$posts = $query->posts;

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Queries and returns featured posts.
	 *
	 * @param int $limit Number of posts to include.
	 * @return array
	 */
	private function get_featured_posts( $limit ) {
		$featured_term = get_term_by( 'slug', 'featured', 'category' );

		if ( ! $featured_term ) {
			$featured_term = get_term_by( 'name', 'Featured', 'category' );
		}

		if ( ! $featured_term || is_wp_error( $featured_term ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'posts_per_page'      => absint( $limit ),
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'cat'                 => (int) $featured_term->term_id,
			)
		);

		$posts = $query->posts;

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Returns top-level categories, optionally filtered by settings.
	 *
	 * @return array
	 */
	private function get_top_level_categories() {
		$enabled_ids = get_option( PressNative_App_Plugin::OPTION_ENABLED_CATEGORIES, null );

		if ( null !== $enabled_ids && false !== $enabled_ids && ! is_array( $enabled_ids ) ) {
			$enabled_ids = array();
		}

		$args = array(
			'hide_empty' => false,
			'parent'     => 0,
		);

		$categories = get_categories( $args );

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		if ( is_array( $enabled_ids ) ) {
			$enabled_ids = array_filter( array_map( 'absint', $enabled_ids ) );

			if ( empty( $enabled_ids ) ) {
				return array();
			}

			$categories = array_filter(
				$categories,
				function ( $category ) use ( $enabled_ids ) {
					return in_array( (int) $category->term_id, $enabled_ids, true );
				}
			);
		}

		return array_values( $categories );
	}

	/**
	 * Builds a navigation action for posts.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function build_post_action( $post_id ) {
		return array(
			'type'    => 'open_post',
			'payload' => array(
				'post_id' => (string) absint( $post_id ),
			),
		);
	}

	/**
	 * Builds a navigation action for categories.
	 *
	 * @param int $category_id Category ID.
	 * @return array
	 */
	private function build_category_action( $category_id ) {
		return array(
			'type'    => 'open_category',
			'payload' => array(
				'category_id' => (string) absint( $category_id ),
			),
		);
	}

	/**
	 * Returns a post image URL or a fallback.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size    Image size.
	 * @return string
	 */
	private function get_post_image_url( $post_id, $size ) {
		$image_url = '';

		if ( has_post_thumbnail( $post_id ) ) {
			$image_url = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), $size );
		}

		if ( empty( $image_url ) ) {
			$image_url = $this->get_fallback_image_url();
		}

		return $image_url;
	}

	/**
	 * Returns a fallback image URL.
	 *
	 * @return string
	 */
	private function get_fallback_image_url() {
		$icon_url = get_site_icon_url( 512 );

		if ( $icon_url ) {
			return $icon_url;
		}

		return home_url( '/' );
	}
}

PressNative_App_Plugin::bootstrap();
