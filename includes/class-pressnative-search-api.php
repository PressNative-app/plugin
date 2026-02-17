<?php
/**
 * Search API endpoint for PressNative.
 *
 * Proxies search to WordPress WP_Query. Plugins can hook into
 * rest_prepare_post or use the pressnative_search_results filter
 * to customize results.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Search_Api
 */
class PressNative_Search_Api {

	/**
	 * Registers the search REST route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'pressnative/v1',
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'default'           => 10,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Search callback. Returns PageResponse with PostGrid of search results.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function search( $request ) {
		$query    = $request->get_param( 'q' );
		$per_page = $request->get_param( 'per_page' );

		$results = apply_filters( 'pressnative_search_results', null, $query, $per_page );
		if ( is_array( $results ) ) {
			$posts = $results;
		} else {
			$posts = self::run_search( $query, $per_page );
		}

		$layout  = new \PressNative_Layout();
		$styles  = array(
			'colors'  => array(
				'background' => '#FFFFFF',
				'text'       => '#111111',
				'accent'     => '#1A73E8',
			),
			'padding' => array( 'horizontal' => 16, 'vertical' => 16 ),
		);

		$post_items = array();
		foreach ( $posts as $post ) {
			$post_items[] = array(
				'post_id'       => (string) $post->ID,
				'title'         => get_the_title( $post ),
				'excerpt'       => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'thumbnail_url' => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
				'importance_score' => 0.5,
				'action'        => array(
					'type'    => 'open_post',
					'payload' => array( 'post_id' => (string) $post->ID ),
				),
			);
		}

		$post_items = apply_filters( 'pressnative_search_post_items', $post_items, $posts );

		$post_grid = array(
			'id'      => 'search-results',
			'type'    => 'PostGrid',
			'styles'  => $styles,
			'content' => array(
				'columns' => 2,
				'posts'   => $post_items,
			),
		);

		$data = array(
			'branding'   => \PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'search',
				'title' => sprintf( /* translators: search query */ __( 'Search: %s', 'pressnative' ), $query ),
			),
			'components' => array( $post_grid ),
		);

		PressNative_Analytics::forward_event_to_registry( 'search', $query, null );
		return rest_ensure_response( $data );
	}

	/**
	 * Runs WP_Query search.
	 *
	 * @param string $query    Search query.
	 * @param int    $per_page Posts per page.
	 * @return \WP_Post[]
	 */
	private static function run_search( $query, $per_page ) {
		$q = new \WP_Query(
			array(
				's'              => $query,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'orderby'        => 'relevance',
				'no_found_rows'  => true,
			)
		);
		$posts = $q->posts;
		wp_reset_postdata();
		return $posts;
	}
}
