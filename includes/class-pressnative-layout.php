<?php
/**
 * Builds the home layout matching www/contract.json structure.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Layout
 */
class PressNative_Layout {

	/**
	 * Returns component styles from branding theme (live from App Settings).
	 *
	 * @return array
	 */
	private function get_component_styles() {
		$branding = PressNative_Options::get_branding();
		$theme   = $branding['theme'] ?? array();
		$bg      = $theme['background_color'] ?? '#FFFFFF';
		$text    = $theme['text_color'] ?? '#111111';
		$accent  = $theme['accent_color'] ?? '#34C759';
		return array(
			'colors'  => array(
				'background' => $bg,
				'text'       => $text,
				'accent'     => $accent,
			),
			'padding' => array(
				'horizontal' => 16,
				'vertical'   => 16,
			),
		);
	}

	/**
	 * Returns the home screen layout (contract structure) with branding and components from Layout Settings.
	 *
	 * @return array
	 */
	public function get_home_layout() {
		$builders = array(
			'hero-carousel'  => array( $this, 'build_hero_carousel' ),
			'post-grid'      => array( $this, 'build_post_grid' ),
			'category-list'  => array( $this, 'build_category_list' ),
			'ad-slot-1'      => array( $this, 'build_ad_placement' ),
		);

		$enabled = PressNative_Layout_Options::get_enabled_components();
		$components = array();

		// When WordPress uses a static front page, prepend its hero to the hero carousel.
		$front_page_hero = $this->get_front_page_hero_item();
		if ( $front_page_hero ) {
			$hero_builder = $builders['hero-carousel'];
			$hero = call_user_func( $hero_builder );
			if ( isset( $hero['content']['items'] ) && is_array( $hero['content']['items'] ) ) {
				array_unshift( $hero['content']['items'], $front_page_hero );
				$components[] = $hero;
			} else {
				$components[] = call_user_func( $hero_builder );
			}
		}

		foreach ( $enabled as $id ) {
			if ( 'hero-carousel' === $id && $front_page_hero ) {
				continue; // Already added above.
			}
			if ( isset( $builders[ $id ] ) ) {
				$components[] = call_user_func( $builders[ $id ] );
			}
		}

		return array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'home',
				'title' => get_bloginfo( 'name' ) ?: 'PressNative',
			),
			'components' => $components,
		);
	}

	/**
	 * When WordPress front page is a static page, returns a hero item for it.
	 *
	 * @return array|null Hero item array or null if front page is not a static page.
	 */
	private function get_front_page_hero_item() {
		if ( get_option( 'show_on_front' ) !== 'page' ) {
			return null;
		}
		$page_id = (int) get_option( 'page_on_front', 0 );
		if ( $page_id < 1 ) {
			return null;
		}
		$page = get_post( $page_id );
		if ( ! $page || $page->post_status !== 'publish' ) {
			return null;
		}
		$slug = $page->post_name ?: 'home';
		return array(
			'title'     => get_the_title( $page ),
			'subtitle'  => trim( wp_strip_all_tags( get_the_excerpt( $page ) ) ) ?: '',
			'image_url' => $this->get_post_image_url( $page_id, 'large', $page->post_content ),
			'action'    => array(
				'type'    => 'open_page',
				'payload' => array( 'page_slug' => $slug ),
			),
		);
	}

	/**
	 * HeroCarousel component (contract-style).
	 *
	 * @return array
	 */
	private function build_hero_carousel() {
		$items = array();
		$limit = PressNative_Layout_Options::get_hero_max_items();
		$slug  = PressNative_Layout_Options::get_hero_category_slug();

		foreach ( $this->get_featured_posts( $limit, $slug ) as $post ) {
			$items[] = array(
				'title'     => get_the_title( $post->ID ),
				'subtitle'  => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'image_url' => $this->get_post_image_url( $post->ID, 'large', $post->post_content ),
				'action'    => array(
					'type'    => 'open_post',
					'payload' => array( 'post_id' => (string) $post->ID ),
				),
			);
		}

		if ( empty( $items ) ) {
			$items = $this->get_default_hero_items();
		}

		return array(
			'id'      => 'hero-carousel',
			'type'    => 'HeroCarousel',
			'styles'  => $this->get_component_styles(),
			'content' => array( 'items' => $items ),
		);
	}

	/**
	 * Default hero items when no featured posts exist.
	 * Uses latest post ID when available; otherwise links to home.
	 *
	 * @return array
	 */
	private function get_default_hero_items() {
		$latest = $this->get_latest_posts( 1 );
		$first_post_id = ! empty( $latest ) ? (string) $latest[0]->ID : null;

		$daily_briefing = array(
			'title'     => 'Daily Briefing',
			'subtitle'  => 'Top stories for your city',
			'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee',
		);
		if ( $first_post_id ) {
			$daily_briefing['action'] = array(
				'type'    => 'open_post',
				'payload' => array( 'post_id' => $first_post_id ),
			);
		} else {
			$daily_briefing['action'] = array(
				'type'    => 'open_url',
				'payload' => array( 'url' => home_url( '/' ) ),
			);
		}

		return array(
			$daily_briefing,
			array(
				'title'     => 'Community Spotlight',
				'subtitle'  => 'Neighborhood updates',
				'image_url' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa',
				'action'    => array(
					'type'    => 'open_category',
					'payload' => array( 'category_id' => 'community' ),
				),
			),
		);
	}

	/**
	 * PostGrid component from get_posts.
	 *
	 * @return array
	 */
	private function build_post_grid() {
		$posts  = array();
		$per    = PressNative_Layout_Options::get_post_grid_per_page();
		$cols   = PressNative_Layout_Options::get_post_grid_columns();

		foreach ( $this->get_latest_posts( $per ) as $post ) {
			$posts[] = array(
				'post_id'       => (string) $post->ID,
				'title'         => get_the_title( $post->ID ),
				'excerpt'       => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'thumbnail_url' => $this->get_post_image_url( $post->ID, 'medium', $post->post_content ),
				'action'        => array(
					'type'    => 'open_post',
					'payload' => array( 'post_id' => (string) $post->ID ),
				),
			);
		}

		if ( empty( $posts ) ) {
			$posts = array(
				array(
					'post_id'       => 'post-101',
					'title'         => 'Transit upgrades coming this spring',
					'excerpt'       => 'City planners confirmed a new set of improvements for commuters.',
					'thumbnail_url' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c',
					'action'        => array(
						'type'    => 'open_post',
						'payload' => array( 'post_id' => 'post-101' ),
					),
				),
				array(
					'post_id'       => 'post-102',
					'title'         => 'Local markets see weekend surge',
					'excerpt'       => 'Shoppers are returning in record numbers.',
					'thumbnail_url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085',
					'action'        => array(
						'type'    => 'open_post',
						'payload' => array( 'post_id' => 'post-102' ),
					),
				),
			);
		}

		return array(
			'id'      => 'post-grid',
			'type'    => 'PostGrid',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'columns' => $cols,
				'posts'   => $posts,
			),
		);
	}

	/**
	 * CategoryList component from WP categories.
	 *
	 * @return array
	 */
	private function build_category_list() {
		$categories = array();
		$args       = array( 'hide_empty' => false, 'parent' => 0 );
		$enabled    = PressNative_Layout_Options::get_enabled_category_ids();
		if ( ! empty( $enabled ) ) {
			$args['include'] = $enabled;
		}
		$terms = get_categories( $args );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'category_id' => (string) $term->term_id,
					'name'        => $term->name,
					'icon_url'    => $this->get_category_icon_url( $term ),
					'action'      => array(
						'type'    => 'open_category',
						'payload' => array( 'category_id' => (string) $term->term_id ),
					),
				);
			}
		}

		if ( empty( $categories ) ) {
			$categories = array(
				array(
					'category_id' => 'events',
					'name'        => 'Events',
					'icon_url'    => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05',
					'action'      => array(
						'type'    => 'open_category',
						'payload' => array( 'category_id' => 'events' ),
					),
				),
				array(
					'category_id' => 'business',
					'name'        => 'Business',
					'icon_url'    => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c',
					'action'      => array(
						'type'    => 'open_category',
						'payload' => array( 'category_id' => 'business' ),
					),
				),
			);
		}

		$styles = $this->get_component_styles();
		$styles['padding']['vertical'] = 12;
		return array(
			'id'      => 'category-list',
			'type'    => 'CategoryList',
			'styles'  => $styles,
			'content' => array( 'categories' => $categories ),
		);
	}

	/**
	 * AdPlacement component (contract-style).
	 *
	 * @return array
	 */
	private function build_ad_placement() {
		return array(
			'id'      => 'ad-slot-1',
			'type'    => 'AdPlacement',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'ad_unit_id' => 'pressnative-home-001',
				'provider'   => 'PressNative Ads',
				'action'     => array(
					'type'    => 'open_url',
					'payload' => array( 'url' => 'https://pressnative.app' ),
				),
			),
		);
	}

	/**
	 * Latest published posts via get_posts.
	 *
	 * @param int $per_page Number of posts.
	 * @return array
	 */
	private function get_latest_posts( $per_page = 10 ) {
		return get_posts(
			array(
				'numberposts'         => absint( $per_page ),
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
			)
		);
	}

	/**
	 * Featured posts from category by slug (from Layout Settings).
	 *
	 * @param int    $limit Max posts.
	 * @param string $slug  Category slug (e.g. "featured").
	 * @return array
	 */
	private function get_featured_posts( $limit = 3, $slug = 'featured' ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term ) {
			$term = get_term_by( 'name', ucfirst( $slug ), 'category' );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		return get_posts(
			array(
				'numberposts'      => absint( $limit ),
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'category'         => (int) $term->term_id,
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Post thumbnail or fallback URL.
	 *
	 * Tries, in order: featured image, first img in content, site icon.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $size     Image size.
	 * @param string $content  Optional post content for first-img fallback.
	 * @return string
	 */
	private function get_post_image_url( $post_id, $size = 'medium', $content = '' ) {
		if ( has_post_thumbnail( $post_id ) ) {
			$url = get_the_post_thumbnail_url( $post_id, $size );
			if ( $url ) {
				return $url;
			}
		}
		if ( '' === $content ) {
			$post = get_post( $post_id );
			$content = $post ? $post->post_content : '';
		}
		$first_img = $this->get_first_image_url_from_content( $content );
		if ( $first_img ) {
			return $first_img;
		}
		return get_site_icon_url( 512 ) ?: '';
	}

	/**
	 * Extracts the first image URL from post content.
	 *
	 * @param string $content Post content (HTML).
	 * @return string Empty if none found.
	 */
	private function get_first_image_url_from_content( $content ) {
		if ( empty( $content ) ) {
			return '';
		}
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m ) ) {
			return esc_url_raw( $m[1] );
		}
		return '';
	}

	/**
	 * Category icon (placeholder or term meta if available).
	 *
	 * @param WP_Term $term Category term.
	 * @return string
	 */
	private function get_category_icon_url( $term ) {
		return get_site_icon_url( 512 ) ?: 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05';
	}

	/**
	 * Post detail layout (full article view).
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Layout data or null if not found.
	 */
	public function get_post_layout( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return null;
		}

		$shortcode_blocks = PressNative_Shortcodes::extract_shortcode_blocks( $post->post_content );
		$stripped_content = PressNative_Shortcodes::strip_native_shortcodes( $post->post_content );
		$processed_content = apply_filters( 'the_content', $stripped_content );

		$styles = $this->get_component_styles();
		$post_detail = array(
			'id'      => 'post-detail-' . $post_id,
			'type'    => 'PostDetail',
			'styles'  => $styles,
			'content' => array(
				'post_id'       => (string) $post_id,
				'title'         => get_the_title( $post ),
				'excerpt'       => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'content'       => $processed_content,
				'image_url'     => $this->get_post_image_url( $post_id, 'large', $post->post_content ),
				'date'          => get_the_date( 'c', $post ),
				'author'        => get_the_author_meta( 'display_name', $post->post_author ),
			),
		);

		$components = $this->build_shortcode_components( $shortcode_blocks, $styles, 'post-' . $post_id );
		$components[] = $post_detail;

		return array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'post-' . $post_id,
				'title' => get_the_title( $post ),
			),
			'components' => $components,
		);
	}

	/**
	 * Page layout (WordPress page by slug). Reuses PostDetail structure.
	 *
	 * @param string $slug Page slug (post_name).
	 * @return array|null Layout data or null if not found.
	 */
	public function get_page_layout( $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page || $page->post_status !== 'publish' ) {
			return null;
		}

		$shortcode_blocks = PressNative_Shortcodes::extract_shortcode_blocks( $page->post_content );
		$stripped_content = PressNative_Shortcodes::strip_native_shortcodes( $page->post_content );
		$processed_content = apply_filters( 'the_content', $stripped_content );

		$styles    = $this->get_component_styles();
		$post_detail = array(
			'id'      => 'page-detail-' . $page->ID,
			'type'    => 'PostDetail',
			'styles'  => $styles,
			'content' => array(
				'post_id'       => (string) $page->ID,
				'title'         => get_the_title( $page ),
				'excerpt'       => trim( wp_strip_all_tags( get_the_excerpt( $page ) ) ) ?: '',
				'content'       => $processed_content,
				'image_url'     => $this->get_post_image_url( $page->ID, 'large', $page->post_content ),
				'date'          => get_the_date( 'c', $page ),
				'author'        => get_the_author_meta( 'display_name', $page->post_author ),
				'page_slug'     => $page->post_name,
			),
		);

		$components = $this->build_shortcode_components( $shortcode_blocks, $styles, 'page-' . $page->ID );
		$components[] = $post_detail;

		return array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'page-' . $page->post_name,
				'title' => get_the_title( $page ),
			),
			'components' => $components,
		);
	}

	/**
	 * Builds ShortcodeBlock components from extracted blocks.
	 *
	 * @param array $blocks Shortcode block data from PressNative_Shortcodes::extract_shortcode_blocks.
	 * @param array $styles Component styles.
	 * @param string $prefix Component ID prefix.
	 * @return array
	 */
	private function build_shortcode_components( $blocks, $styles, $prefix ) {
		$components = array();
		foreach ( $blocks as $i => $block ) {
			$components[] = array(
				'id'      => $prefix . '-shortcode-' . $i,
				'type'    => 'ShortcodeBlock',
				'styles'  => $styles,
				'content' => array(
					'shortcode'        => $block['shortcode'],
					'attrs'            => $block['attrs'],
					'html_fallback'    => $block['html_fallback'],
					'native_component' => $block['native_component'],
				),
			);
		}
		return $components;
	}

	/**
	 * Category detail layout (posts in category).
	 *
	 * @param int $category_id Category term ID.
	 * @return array|null Layout data or null if not found.
	 */
	public function get_category_layout( $category_id ) {
		$term = get_term( $category_id, 'category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$per = PressNative_Layout_Options::get_post_grid_per_page();
		$cols = PressNative_Layout_Options::get_post_grid_columns();
		$posts = array();

		$post_list = get_posts(
			array(
				'numberposts'      => absint( $per ),
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'category'         => (int) $category_id,
				'suppress_filters' => false,
			)
		);

		foreach ( $post_list as $post ) {
			$posts[] = array(
				'post_id'       => (string) $post->ID,
				'title'         => get_the_title( $post->ID ),
				'excerpt'       => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'thumbnail_url' => $this->get_post_image_url( $post->ID, 'medium', $post->post_content ),
				'action'        => array(
					'type'    => 'open_post',
					'payload' => array( 'post_id' => (string) $post->ID ),
				),
			);
		}

		$post_grid = array(
			'id'      => 'category-post-grid',
			'type'    => 'PostGrid',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'columns' => $cols,
				'posts'   => $posts,
			),
		);

		return array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'category-' . $category_id,
				'title' => $term->name,
			),
			'components' => array( $post_grid ),
		);
	}
}
