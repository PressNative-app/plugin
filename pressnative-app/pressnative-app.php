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
	const REST_ROUTE_HOME           = '/page/home';
	const DEFAULT_PER_PAGE          = 10;
	const MAX_PER_PAGE              = 50;

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
					'args'                => array(
						'per_page' => array(
							'type'              => 'integer',
							'default'           => self::DEFAULT_PER_PAGE,
							'sanitize_callback' => array( $this, 'sanitize_per_page' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Returns the PressNative home page contract.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_home_page( WP_REST_Request $request ) {
		$per_page      = $this->sanitize_per_page( $request->get_param( 'per_page' ) );
		$category_ids  = $this->get_enabled_category_ids();
		$layout_engine = new PressNative_Layout_Engine();
		$data          = $layout_engine->build_home_contract( $per_page, $category_ids );

		$response = new WP_REST_Response( $data );
		$response->set_header( 'Last-Updated', gmdate( 'c' ) );

		return $response;
	}

	/**
	 * Sanitizes per_page values for the REST API.
	 *
	 * @param mixed $value Requested per_page.
	 * @return int
	 */
	public function sanitize_per_page( $value ) {
		$value = absint( $value );

		if ( $value < 1 ) {
			return self::DEFAULT_PER_PAGE;
		}

		if ( $value > self::MAX_PER_PAGE ) {
			return self::MAX_PER_PAGE;
		}

		return $value;
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
	 * Builds the PressNative home contract response.
	 *
	 * @param int   $per_page         Number of posts to include.
	 * @param array $category_ids     Category IDs for the CategoryList component.
	 * @return array
	 */
	public function build_home_contract( $per_page, array $category_ids ) {
		$posts      = $this->get_latest_posts( $per_page );
		$categories = $this->get_category_data( $category_ids );

		return array(
			'@contract' => 'pressnative.app/contract.json',
			'version'   => '1.0',
			'page'      => array(
				'id'          => 'home',
				'title'       => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'layout'      => array(
					'category_list',
					'latest_posts',
				),
			),
			'components' => array(
				array(
					'id'   => 'category_list',
					'type' => 'CategoryList',
					'data' => array(
						'title'      => 'Categories',
						'categories' => $categories,
					),
				),
				array(
					'id'   => 'latest_posts',
					'type' => 'PostList',
					'data' => array(
						'title' => 'Latest Posts',
						'posts' => $posts,
					),
				),
			),
		);
	}

	/**
	 * Queries and formats the latest posts.
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

		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post( $post );
		}

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Formats a post for the contract schema.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function format_post( WP_Post $post ) {
		$author_id         = (int) $post->post_author;
		$featured_image_id = get_post_thumbnail_id( $post->ID );
		$featured_image    = '';

		if ( $featured_image_id ) {
			$featured_image = wp_get_attachment_image_url( $featured_image_id, 'large' );
		}

		$categories = array();
		$terms      = get_the_category( $post->ID );

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		return array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post->ID ),
			'excerpt'        => get_the_excerpt( $post ),
			'permalink'      => get_permalink( $post->ID ),
			'date'           => get_the_date( 'c', $post->ID ),
			'modified'       => get_the_modified_date( 'c', $post->ID ),
			'author'         => array(
				'id'   => $author_id,
				'name' => get_the_author_meta( 'display_name', $author_id ),
			),
			'featured_image' => array(
				'url' => $featured_image,
			),
			'categories'     => $categories,
		);
	}

	/**
	 * Formats categories for the CategoryList component.
	 *
	 * @param array $category_ids Category IDs to include.
	 * @return array
	 */
	private function get_category_data( array $category_ids ) {
		if ( empty( $category_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'include'    => $category_ids,
				'hide_empty' => false,
				'orderby'    => 'include',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'link'  => get_category_link( $term->term_id ),
				'count' => (int) $term->count,
			);
		}

		return $categories;
	}
}

PressNative_App_Plugin::bootstrap();
