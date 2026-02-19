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
	 * Captures the scripts and styles WordPress would enqueue for a given post/page.
	 * This simulates a page render to trigger wp_enqueue_scripts, then captures
	 * the output of wp_head() and wp_footer() which contain all enqueued JS/CSS.
	 *
	 * @param WP_Post $post The post/page to capture scripts for.
	 * @return array Associative array with 'head' and 'footer' HTML strings.
	 */
	private function capture_enqueued_assets( $post ) {
		global $wp_query, $wp_the_query;

		// Save current query state.
		$original_query     = $wp_query;
		$original_the_query = $wp_the_query;
		$original_post      = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		// Set up the global post and query as if we are rendering this page.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$wp_query = new \WP_Query( array(
			'p'              => $post->ID,
			'post_type'      => $post->post_type,
			'posts_per_page' => 1,
		) );
		$wp_the_query = $wp_query;

		// Fire the enqueue hooks so plugins register their scripts for this page.
		do_action( 'wp_enqueue_scripts' );

		// Capture wp_head output (scripts, styles, inline JS).
		ob_start();
		wp_head();
		$head = ob_get_clean();

		// Capture wp_footer output (deferred scripts, localized data).
		ob_start();
		wp_footer();
		$footer = ob_get_clean();

		// Restore original state.
		$wp_query     = $original_query;
		$wp_the_query = $original_the_query;
		if ( $original_post ) {
			$GLOBALS['post'] = $original_post;
			setup_postdata( $original_post );
		} else {
			unset( $GLOBALS['post'] );
		}

		return array(
			'head'   => $head,
			'footer' => $footer,
		);
	}

	/**
	 * Converts absolute site URLs to root-relative paths so they resolve
	 * correctly against whatever base URL the WebView uses.
	 *
	 * For example, http://localhost:10004/wp-includes/js/jquery.js becomes
	 * /wp-includes/js/jquery.js. This is critical because the mobile WebView
	 * may use a different host (e.g. 10.0.2.2 for Android emulator).
	 *
	 * @param string $html HTML containing absolute URLs.
	 * @return string HTML with site URLs made relative.
	 */
	private function relativize_asset_urls( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}
		$site_url = untrailingslashit( site_url() );
		return str_replace( $site_url, '', $html );
	}

	/**
	 * Extracts only <script> and <style>/<link> tags from captured wp_head/wp_footer output.
	 * All absolute site URLs are converted to root-relative paths.
	 *
	 * @param string $html Raw wp_head or wp_footer output.
	 * @return string Only the script/style/link tags with relativized URLs.
	 */
	private function extract_script_style_tags( $html ) {
		if ( empty( $html ) ) {
			return '';
		}
		$tags = '';
		// Match <script ...>...</script> tags.
		if ( preg_match_all( '/<script[^>]*>.*?<\/script>/si', $html, $matches ) ) {
			$tags .= implode( "\n", $matches[0] );
		}
		// Match <style ...>...</style> tags.
		if ( preg_match_all( '/<style[^>]*>.*?<\/style>/si', $html, $matches ) ) {
			$tags .= "\n" . implode( "\n", $matches[0] );
		}
		// Match <link rel="stylesheet" ...> tags.
		if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*\/?>/si', $html, $matches ) ) {
			$tags .= "\n" . implode( "\n", $matches[0] );
		}
		return $this->relativize_asset_urls( $tags );
	}

	/**
	 * Builds a complete injectable script/style block for WebView rendering.
	 * Includes WordPress globals (ajaxurl, nonces) plus all scripts/styles
	 * that plugins enqueue for the given post.
	 *
	 * Global URLs (ajaxurl, REST root) are kept as root-relative paths so the
	 * WebView resolves them against its own base URL.
	 *
	 * @param WP_Post $post The post/page.
	 * @return array 'head_scripts' and 'footer_scripts' strings.
	 */
	private function get_injectable_assets( $post ) {
		$globals  = sprintf(
			'<script>'
			. 'var ajaxurl="%s";'
			. 'var wpApiSettings={root:"%s",nonce:"%s"};'
			. 'window.wp=window.wp||{};'
			. 'wp.i18n=wp.i18n||{__:function(t){return t},_x:function(t){return t},_n:function(s,p,n){return n===1?s:p},_nx:function(s,p,n){return n===1?s:p},sprintf:function(){var a=arguments;return a[0].replace(/%%s/g,function(){return a[1]})},isRTL:function(){return false}};'
			. '</script>',
			esc_js( '/wp-admin/admin-ajax.php' ),
			esc_js( '/wp-json/' ),
			esc_js( wp_create_nonce( 'wp_rest' ) )
		);

		$assets = $this->capture_enqueued_assets( $post );

		return array(
			'head_scripts'   => $globals . "\n" . $this->extract_script_style_tags( $assets['head'] ),
			'footer_scripts' => $this->extract_script_style_tags( $assets['footer'] ),
		);
	}

	/**
	 * Wraps processed content with the captured page scripts for WebView rendering.
	 * Also relativizes any absolute site URLs in the content itself.
	 *
	 * @param string  $content Processed HTML content.
	 * @param WP_Post $post    The post/page object.
	 * @return string Content with all required scripts injected.
	 */
	private function wrap_content_with_assets( $content, $post ) {
		if ( empty( $content ) ) {
			return $content;
		}
		$assets = $this->get_injectable_assets( $post );
		$content = $this->relativize_asset_urls( $content );
		return $assets['head_scripts'] . "\n" . $content . "\n" . $assets['footer_scripts'];
	}

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
			'page-list'      => array( $this, 'build_page_list' ),
			'ad-slot-1'      => array( $this, 'build_ad_placement' ),
		);
		if ( class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active() ) {
			$builders['product-grid']          = array( $this, 'build_product_grid' );
			$builders['product-category-list'] = array( $this, 'build_product_category_list' );
			$builders['product-carousel']      = array( $this, 'build_product_carousel' );
		}

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

		$layout = array(
			'api_url'    => rest_url( 'pressnative/v1/' ),
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'home',
				'title' => get_bloginfo( 'name' ) ?: 'PressNative',
			),
			'components' => $components,
		);
		return $this->inject_shop_config( $layout );
	}

	/**
	 * Append shop_config to layout when WooCommerce is active (cart_url, checkout_url, nonce, session).
	 *
	 * @param array $layout Layout array with branding, screen, components.
	 * @return array
	 */
	private function inject_shop_config( $layout ) {
		if ( class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active() ) {
			$layout['shop_config'] = PressNative_WooCommerce::get_shop_config();
		}
		return $layout;
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
	 * PageList component from get_pages.
	 *
	 * @return array
	 */
	private function build_page_list() {
		$pages   = get_pages(
			array(
				'sort_order'  => 'ASC',
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
				'number'      => 50,
			)
		);
		$items   = array();
		if ( ! empty( $pages ) && ! is_wp_error( $pages ) ) {
			foreach ( $pages as $page ) {
				$slug   = $page->post_name ?: 'page-' . $page->ID;
				$img_url = $this->get_post_image_url( $page->ID, 'thumbnail', $page->post_content );
				$items[] = array(
					'page_slug' => $slug,
					'name'      => get_the_title( $page ),
					'icon_url'  => ! empty( $img_url ) ? $img_url : null,
					'action'    => array(
						'type'    => 'open_page',
						'payload' => array( 'page_slug' => $slug ),
					),
				);
			}
		}

		$styles = $this->get_component_styles();
		$styles['padding']['vertical'] = 12;
		return array(
			'id'      => 'page-list',
			'type'    => 'PageList',
			'styles'  => $styles,
			'content' => array( 'pages' => $items ),
		);
	}

	/**
	 * AdPlacement component (contract-style).
	 *
	 * Uses the AdMob banner unit ID from App Settings. When no unit ID is
	 * configured, the component falls back to the Google-provided test ad unit
	 * so the layout always includes a valid placement.
	 *
	 * @return array
	 */
	private function build_ad_placement() {
		$unit_id = get_option( PressNative_Options::OPTION_ADMOB_BANNER_UNIT_ID, '' );
		if ( empty( $unit_id ) ) {
			$unit_id = 'ca-app-pub-3940256099942544/6300978111';
		}

		return array(
			'id'      => 'ad-slot-1',
			'type'    => 'AdPlacement',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'ad_unit_id' => $unit_id,
				'format'     => 'banner',
				'provider'   => 'admob',
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

		// Ensure full content is returned even when posts contain <!--more--> or <!--nextpage--> tags.
		// Use $GLOBALS directly to avoid accidental shadowing of any local variable.
		$saved_more  = $GLOBALS['more'] ?? 0;
		$saved_paged = $GLOBALS['page'] ?? 0;
		$GLOBALS['more'] = 1;
		$GLOBALS['page'] = 1;
		$processed_content = $this->wrap_content_with_assets(
			apply_filters( 'the_content', $stripped_content ),
			$post
		);
		$GLOBALS['more'] = $saved_more;
		$GLOBALS['page'] = $saved_paged;

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

		$layout = array(
			'api_url'    => rest_url( 'pressnative/v1/' ),
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'post-' . $post_id,
				'title' => get_the_title( $post ),
			),
			'components' => $components,
		);
		return $this->inject_shop_config( $layout );
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[PressNative] get_page_layout: page not found or not published, slug=' . $slug );
			}
			return null;
		}

		$shortcode_blocks = PressNative_Shortcodes::extract_shortcode_blocks( $page->post_content );
		$stripped_content = PressNative_Shortcodes::strip_native_shortcodes( $page->post_content );

		// Ensure full content is returned even when pages contain <!--more--> or <!--nextpage--> tags.
		// Use $GLOBALS directly to avoid shadowing the local $page (WP_Post) variable.
		$saved_more  = $GLOBALS['more'] ?? 0;
		$saved_paged = $GLOBALS['page'] ?? 0;
		$GLOBALS['more'] = 1;
		$GLOBALS['page'] = 1;
		$processed_content = $this->wrap_content_with_assets(
			apply_filters( 'the_content', $stripped_content ),
			$page
		);
		$GLOBALS['more'] = $saved_more;
		$GLOBALS['page'] = $saved_paged;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$content_len = is_string( $processed_content ) ? strlen( $processed_content ) : 0;
			error_log( sprintf(
				'[PressNative] get_page_layout: slug=%s, post_id=%d, content_len=%d, shortcode_blocks=%d',
				$slug,
				$page->ID,
				$content_len,
				count( $shortcode_blocks )
			) );
		}

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

		$layout = array(
			'api_url'    => rest_url( 'pressnative/v1/' ),
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'page-' . $page->post_name,
				'title' => get_the_title( $page ),
			),
			'components' => $components,
		);
		return $this->inject_shop_config( $layout );
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

		$layout = array(
			'api_url'    => rest_url( 'pressnative/v1/' ),
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'category-' . $category_id,
				'title' => $term->name,
			),
			'components' => array( $post_grid ),
		);
		return $this->inject_shop_config( $layout );
	}

	/**
	 * ProductGrid component (WooCommerce). Only built when WooCommerce is active.
	 *
	 * @return array
	 */
	private function build_product_grid() {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return array( 'id' => 'product-grid', 'type' => 'ProductGrid', 'styles' => $this->get_component_styles(), 'content' => array( 'columns' => 2, 'products' => array() ) );
		}
		$cols = PressNative_Layout_Options::get_product_grid_columns();
		$per  = PressNative_Layout_Options::get_product_grid_per_page();
		$products = PressNative_WooCommerce::get_products( array( 'limit' => $per ) );
		return array(
			'id'      => 'product-grid',
			'type'    => 'ProductGrid',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'columns'   => $cols,
				'products'  => $products,
			),
		);
	}

	/**
	 * ProductCategoryList component (WooCommerce).
	 *
	 * @return array
	 */
	private function build_product_category_list() {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return array( 'id' => 'product-category-list', 'type' => 'ProductCategoryList', 'styles' => $this->get_component_styles(), 'content' => array( 'categories' => array() ) );
		}
		$categories = PressNative_WooCommerce::get_product_categories( array( 'parent' => 0 ) );
		$styles = $this->get_component_styles();
		$styles['padding']['vertical'] = 12;
		return array(
			'id'      => 'product-category-list',
			'type'    => 'ProductCategoryList',
			'styles'  => $styles,
			'content' => array( 'categories' => $categories ),
		);
	}

	/**
	 * ProductCarousel component (WooCommerce featured products).
	 *
	 * @return array
	 */
	private function build_product_carousel() {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return array( 'id' => 'product-carousel', 'type' => 'ProductCarousel', 'styles' => $this->get_component_styles(), 'content' => array( 'items' => array() ) );
		}
		$slug = PressNative_Layout_Options::get_featured_product_cat();
		$args = array( 'limit' => 5, 'status' => 'publish' );
		if ( $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['category'] = array( $term->slug );
			}
		}
		$products = PressNative_WooCommerce::get_products( $args );
		$items = array();
		foreach ( $products as $p ) {
			$items[] = array(
				'product_id'         => $p['product_id'],
				'title'              => $p['title'],
				'subtitle'           => '',
				'image_url'          => $p['image_url'],
				'price'              => $p['price'],
				'action'             => $p['action'],
				'add_to_cart_action'  => $p['add_to_cart_action'],
			);
		}
		return array(
			'id'      => 'product-carousel',
			'type'    => 'ProductCarousel',
			'styles'  => $this->get_component_styles(),
			'content' => array( 'items' => $items ),
		);
	}

	/**
	 * Shop screen layout (ProductCategoryList + ProductGrid). WooCommerce only.
	 *
	 * @return array|null Layout or null if WooCommerce inactive.
	 */
	public function get_shop_layout() {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return null;
		}
		$components = array(
			$this->build_product_category_list(),
			$this->build_product_grid(),
		);
		$layout = array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'shop',
				'title' => _x( 'Shop', 'Screen title', 'pressnative' ),
			),
			'components' => $components,
		);
		return $this->inject_shop_config( $layout );
	}

	/**
	 * Product detail layout. WooCommerce only.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Layout or null if not found or WooCommerce inactive.
	 */
	public function get_product_layout( $product_id ) {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return null;
		}
		$product = PressNative_WooCommerce::get_product( $product_id );
		if ( ! $product ) {
			return null;
		}
		$styles = $this->get_component_styles();
		$detail = array(
			'id'      => 'product-detail-' . $product_id,
			'type'    => 'ProductDetail',
			'styles'  => $styles,
			'content' => array(
				'product_id'         => $product['product_id'],
				'title'               => $product['title'],
				'price'               => $product['price'],
				'description'         => $product['description'],
				'image_url'           => $product['image_url'],
				'add_to_cart_action'  => $product['add_to_cart_action'],
			),
		);
		$layout = array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'product-' . $product_id,
				'title' => $product['title'],
			),
			'components' => array( $detail ),
		);
		return $this->inject_shop_config( $layout );
	}

	/**
	 * Product category layout (ProductGrid for that category). WooCommerce only.
	 *
	 * @param int $category_id Product category term ID.
	 * @return array|null Layout or null if not found or WooCommerce inactive.
	 */
	public function get_product_category_layout( $category_id ) {
		if ( ! PressNative_WooCommerce::is_active() ) {
			return null;
		}
		$term = get_term( $category_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$cols = PressNative_Layout_Options::get_product_grid_columns();
		$per  = PressNative_Layout_Options::get_product_grid_per_page();
		$products = PressNative_WooCommerce::get_products( array(
			'limit'    => $per,
			'category' => array( $term->slug ),
		) );
		$grid = array(
			'id'      => 'product-category-grid',
			'type'    => 'ProductGrid',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'columns'  => $cols,
				'products' => $products,
			),
		);
		$layout = array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'product-category-' . $category_id,
				'title' => $term->name,
			),
			'components' => array( $grid ),
		);
		return $this->inject_shop_config( $layout );
	}
}
