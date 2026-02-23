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
	 * Strips assets that are unnecessary or harmful for WebView rendering.
	 *
	 * The app's native WebView already applies its own body styling (font, size,
	 * line-height, color). WordPress theme global styles and custom font-face
	 * declarations override those, causing font-loading-induced text reflow that
	 * breaks height measurement on Android. Block-specific CSS for unused blocks,
	 * emoji scripts, and speculation rules are also pure bloat.
	 *
	 * On non-product post types WooCommerce tracking scripts are stripped as well
	 * since the WebView doesn't need order attribution or sourcebuster logic.
	 *
	 * @param string  $tags Concatenated script/style/link tags.
	 * @param WP_Post $post The post being rendered.
	 * @return string Filtered tags.
	 */
	private function filter_assets_for_webview( $tags, $post ) {
		if ( empty( $tags ) ) {
			return $tags;
		}

		$needs_wc = ( $post->post_type === 'product' );

		// ── Remove @font-face declarations ──────────────────────────────────
		// Prevents custom fonts from loading in the WebView, which eliminates
		// font-loading reflow that breaks Android height measurement.
		$tags = preg_replace(
			'/<style[^>]*class=["\']wp-fonts-local["\'][^>]*>.*?<\/style>/si',
			'',
			$tags
		);

		// ── Remove global-styles-inline-css ─────────────────────────────────
		// This massive block redefines body font-family, font-size, and
		// line-height, overriding the app's own styling and causing reflow.
		$tags = preg_replace(
			'/<style[^>]*id=["\']global-styles-inline-css["\'][^>]*>.*?<\/style>/si',
			'',
			$tags
		);

		// ── Remove emoji scripts and styles ─────────────────────────────────
		$tags = preg_replace(
			'/<script[^>]*id=["\']wp-emoji-settings["\'][^>]*>.*?<\/script>/si',
			'',
			$tags
		);
		$tags = preg_replace(
			'/<script[^>]*type=["\']module["\'][^>]*>[^<]*wpEmojiSettingsSupports[^<]*<\/script>/si',
			'',
			$tags
		);
		$tags = preg_replace(
			'/<style[^>]*id=["\']wp-emoji-styles-inline-css["\'][^>]*>.*?<\/style>/si',
			'',
			$tags
		);

		// ── Remove speculationrules ─────────────────────────────────────────
		$tags = preg_replace(
			'/<script[^>]*type=["\']speculationrules["\'][^>]*>.*?<\/script>/si',
			'',
			$tags
		);

		// ── Remove inline block CSS for blocks the article doesn't use ──────
		// Keep wp-block-library (base formatting) and woocommerce-* on product pages.
		// Strip all wp-block-*-inline-css except block-library's.
		$tags = preg_replace(
			'/<style[^>]*id=["\']wp-block-(?!library-inline-css)[a-z0-9-]+-inline-css["\'][^>]*>.*?<\/style>/si',
			'',
			$tags
		);

		// ── Remove auto-sizes contain CSS ───────────────────────────────────
		$tags = preg_replace(
			'/<style[^>]*id=["\']wp-img-auto-sizes-contain-inline-css["\'][^>]*>.*?<\/style>/si',
			'',
			$tags
		);

		if ( ! $needs_wc ) {
			// ── Strip WooCommerce tracking/attribution on non-product pages ─
			$tags = preg_replace(
				'/<script[^>]*id=["\']sourcebuster[^"\']*["\'][^>]*>.*?<\/script>/si',
				'',
				$tags
			);
			$tags = preg_replace(
				'/<script[^>]*id=["\']wc-order-attribution[^"\']*["\'][^>]*>.*?<\/script>/si',
				'',
				$tags
			);

			// ── Strip WooCommerce stylesheet links on non-product pages ─────
			$tags = preg_replace(
				'/<link[^>]+id=["\']wc-blocks-style-[^"\']*-css["\'][^>]*\/?>/si',
				'',
				$tags
			);
			$tags = preg_replace(
				'/<link[^>]+id=["\']woocommerce-[^"\']*-css["\'][^>]*\/?>/si',
				'',
				$tags
			);
		}

		return $tags;
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

		$head_tags   = $this->extract_script_style_tags( $assets['head'] );
		$footer_tags = $this->extract_script_style_tags( $assets['footer'] );

		$head_tags   = $this->filter_assets_for_webview( $head_tags, $post );
		$footer_tags = $this->filter_assets_for_webview( $footer_tags, $post );

		return array(
			'head_scripts'   => $globals . "\n" . $head_tags,
			'footer_scripts' => $footer_tags,
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
	 * @param string $surface 'base' (screen background) or 'tile' (card/tile surfaces).
	 * @return array
	 */
	private function get_component_styles( $surface = 'base' ) {
		$branding = PressNative_Options::get_branding();
		$theme   = $branding['theme'] ?? array();
		$surface = in_array( $surface, array( 'base', 'tile' ), true ) ? $surface : 'base';
		if ( 'tile' === $surface ) {
			$bg   = $theme['tile_background_color'] ?? '#F6F7F9';
			$text = $theme['tile_text_color'] ?? '#111111';
		} else {
			$bg   = $theme['background_color'] ?? '#FFFFFF';
			$text = $theme['text_color'] ?? '#111111';
		}
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
	 * When there are more published posts than shown, adds see_more_action so the app can show "See all posts".
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

		$content = array(
			'columns' => $cols,
			'posts'   => $posts,
		);

		// Allow app to show "See all posts" when there are more than the home subset.
		$total_posts = (int) wp_count_posts( 'post' )->publish;
		if ( $total_posts > $per ) {
			$content['see_more_action'] = array(
				'type'    => 'open_posts_list',
				'payload' => array( 'page' => 1 ),
			);
			$content['total_posts'] = $total_posts;
		}

		return array(
			'id'      => 'post-grid',
			'type'    => 'PostGrid',
			'styles'  => $this->get_component_styles( 'tile' ),
			'content' => $content,
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
	 * Whether the given page is a WooCommerce system page (Shop, Cart, Checkout, My Account).
	 * These are excluded from the app's PageList so users don't see duplicate or irrelevant entries.
	 *
	 * @param WP_Post $page Page object.
	 * @return bool
	 */
	private function is_woocommerce_system_page( $page ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		$wc_page_ids = array(
			wc_get_page_id( 'shop' ),
			wc_get_page_id( 'cart' ),
			wc_get_page_id( 'checkout' ),
			wc_get_page_id( 'myaccount' ),
		);
		foreach ( $wc_page_ids as $wc_id ) {
			if ( $wc_id > 0 && (int) $page->ID === $wc_id ) {
				return true;
			}
		}
		$wc_slugs = array( 'shop', 'cart', 'checkout', 'my-account', 'woocommerce' );
		return in_array( $page->post_name, $wc_slugs, true );
	}

	/**
	 * PageList component from get_pages.
	 * WooCommerce default pages (Cart, Checkout, My Account, Shop) are excluded.
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
				if ( $this->is_woocommerce_system_page( $page ) ) {
					continue;
				}
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
	 * Paginated posts for "All Posts" screen. Uses WP_Query for paged results.
	 *
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Posts per page.
	 * @return array{posts: array, total: int, has_next_page: bool, next_page: int|null}
	 */
	private function get_posts_page( $page = 1, $per_page = 20 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 50, absint( $per_page ) ) );

		$query = new \WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'ignore_sticky_posts' => true,
				'fields'              => 'ids',
			)
		);

		$total   = (int) $query->found_posts;
		$ids     = $query->posts;
		$posts   = array();
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_status !== 'publish' ) {
				continue;
			}
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

		$has_next = ( $page * $per_page ) < $total;
		return array(
			'posts'         => $posts,
			'total'         => $total,
			'has_next_page' => $has_next,
			'next_page'     => $has_next ? $page + 1 : null,
			'page'          => $page,
			'per_page'      => $per_page,
		);
	}

	/**
	 * Layout for the "All Posts" screen (paginated). Used by GET /layout/posts?page=1&per_page=20.
	 *
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Posts per page.
	 * @return array Layout array with screen id 'posts' and one PostGrid component.
	 */
	public function get_posts_list_layout( $page = 1, $per_page = 20 ) {
		$cols  = PressNative_Layout_Options::get_post_grid_columns();
		$data  = $this->get_posts_page( $page, $per_page );

		$content = array(
			'columns'        => $cols,
			'posts'          => $data['posts'],
			'page'           => $data['page'],
			'per_page'       => $data['per_page'],
			'total_posts'    => $data['total'],
			'has_next_page'  => $data['has_next_page'],
			'next_page'      => $data['next_page'],
		);

		$post_grid = array(
			'id'      => 'posts-list',
			'type'    => 'PostGrid',
			'styles'  => $this->get_component_styles( 'tile' ),
			'content' => $content,
		);

		$layout = array(
			'api_url'    => rest_url( 'pressnative/v1/' ),
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'posts',
				'title' => __( 'All Posts', 'pressnative-apps' ),
			),
			'components' => array( $post_grid ),
		);
		return $this->inject_shop_config( $layout );
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
	 * Content blocks are sourced from the AOT cache when available. When the
	 * cache is empty the DOM parser compiles them on-the-fly and persists the
	 * result so subsequent requests are instant.
	 *
	 * @param int        $post_id        Post ID.
	 * @param array|null $cached_blocks  Pre-compiled SDUI blocks from AOT cache (optional).
	 * @return array|null Layout data or null if not found.
	 */
	public function get_post_layout( $post_id, ?array $cached_blocks = null ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return null;
		}

		$shortcode_blocks = PressNative_Shortcodes::extract_shortcode_blocks( $post->post_content );
		$styles           = $this->get_component_styles();

		// AOT cache → on-demand DOM parse → empty fallback.
		if ( is_array( $cached_blocks ) && ! empty( $cached_blocks ) ) {
			$content_blocks = $cached_blocks;
		} else {
			$content_blocks = PressNative_AOT_Compiler::compile_on_demand( (int) $post_id );
		}

		// Resolve ProductReference markers into native product components.
		$content_blocks = $this->resolve_product_references( $content_blocks, $styles );

		// If parser output has no native product blocks but raw content has WC product shortcodes,
		// surface them as native ProductCardCompact components so cards are tappable in-app.
		$product_shortcode_components = array();
		$product_ids_from_shortcodes  = array();
		$content_source_for_html      = $post->post_content;
		if (
			! $this->blocks_have_native_product_content( $content_blocks ) &&
			$this->has_product_shortcodes( $post->post_content )
		) {
			$product_shortcode_components = $this->extract_product_shortcode_components( $post->post_content, $styles, 'post-' . $post_id );
			if ( ! empty( $product_shortcode_components ) ) {
				$product_ids_from_shortcodes = $this->collect_product_ids_from_content( $post->post_content );
				$content_source_for_html     = $this->strip_product_page_shortcodes( $content_source_for_html );
			}
		}

		$content_blocks = $this->inject_sponsors( $content_blocks );

		// When content_blocks is empty (e.g. parser failed or classic content), send raw HTML so the app can render it in a WebView fallback.
		$content_html = ( count( $content_blocks ) === 0 && ! empty( $content_source_for_html ) )
			? apply_filters( 'the_content', $content_source_for_html )
			: '';
		if ( ! empty( $content_html ) && ! empty( $product_ids_from_shortcodes ) ) {
			$content_html = $this->strip_wc_product_html( $content_html, $product_ids_from_shortcodes );
		}

		$date_format = get_option( 'date_format', 'F j, Y' );
		$time_format = get_option( 'time_format', 'g:i a' );
		$date_display = get_the_date( $date_format, $post );
		$time_display = get_the_time( $time_format, $post );

		$post_detail = array(
			'id'      => 'post-detail-' . $post_id,
			'type'    => 'PostDetail',
			'styles'  => $styles,
			'content' => array(
				'post_id'        => (string) $post_id,
				'title'          => get_the_title( $post ),
				'excerpt'        => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ?: '',
				'content'        => $content_html,
				'content_blocks' => $content_blocks,
				'image_url'      => $this->get_post_image_url( $post_id, 'large', $post->post_content ),
				'date'           => get_the_date( 'c', $post ),
				'date_display'   => $date_display,
				'time_display'   => $time_display,
				'author'         => get_the_author_meta( 'display_name', $post->post_author ),
				'show_author'    => true,
			),
		);

		$components = $this->build_shortcode_components( $shortcode_blocks, $styles, 'post-' . $post_id );
		if ( ! empty( $product_shortcode_components ) ) {
			$components = array_merge( $components, $product_shortcode_components );
		}
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only when WP_DEBUG_LOG enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[PressNative] get_page_layout: page not found or not published, slug=' . $slug );
			}
			return null;
		}

		$shortcode_blocks = PressNative_Shortcodes::extract_shortcode_blocks( $page->post_content );
		$styles           = $this->get_component_styles();

		// AOT cache → on-demand DOM parse → empty fallback.
		$content_blocks = PressNative_AOT_Compiler::get_cached_blocks( (int) $page->ID );
		if ( null === $content_blocks ) {
			$content_blocks = PressNative_AOT_Compiler::compile_on_demand( (int) $page->ID );
		}

		// Resolve ProductReference markers into native product components.
		$content_blocks = $this->resolve_product_references( $content_blocks, $styles );

		// If parser output has no native product blocks but raw content has WC product shortcodes,
		// surface them as native ProductCardCompact components so cards are tappable in-app.
		$product_shortcode_components = array();
		$product_ids_from_shortcodes  = array();
		$content_source_for_html      = $page->post_content;
		if (
			! $this->blocks_have_native_product_content( $content_blocks ) &&
			$this->has_product_shortcodes( $page->post_content )
		) {
			$product_shortcode_components = $this->extract_product_shortcode_components( $page->post_content, $styles, 'page-' . $page->ID );
			if ( ! empty( $product_shortcode_components ) ) {
				$product_ids_from_shortcodes = $this->collect_product_ids_from_content( $page->post_content );
				$content_source_for_html     = $this->strip_product_page_shortcodes( $content_source_for_html );
			}
		}

		$content_blocks = $this->inject_sponsors( $content_blocks );

		// When content_blocks is empty, send raw HTML so the app can render it in a WebView fallback.
		$content_html = ( count( $content_blocks ) === 0 && ! empty( $content_source_for_html ) )
			? apply_filters( 'the_content', $content_source_for_html )
			: '';
		if ( ! empty( $content_html ) && ! empty( $product_ids_from_shortcodes ) ) {
			$content_html = $this->strip_wc_product_html( $content_html, $product_ids_from_shortcodes );
		}

		$date_format = get_option( 'date_format', 'F j, Y' );
		$time_format = get_option( 'time_format', 'g:i a' );
		$date_display = get_the_date( $date_format, $page );
		$time_display = get_the_time( $time_format, $page );

		$post_detail = array(
			'id'      => 'page-detail-' . $page->ID,
			'type'    => 'PostDetail',
			'styles'  => $styles,
			'content' => array(
				'post_id'        => (string) $page->ID,
				'title'          => get_the_title( $page ),
				'excerpt'        => trim( wp_strip_all_tags( get_the_excerpt( $page ) ) ) ?: '',
				'content'        => $content_html,
				'content_blocks' => $content_blocks,
				'image_url'      => $this->get_post_image_url( $page->ID, 'large', $page->post_content ),
				'date'           => get_the_date( 'c', $page ),
				'date_display'   => $date_display,
				'time_display'   => $time_display,
				'author'         => get_the_author_meta( 'display_name', $page->post_author ),
				'show_author'    => true,
				'page_slug'      => $page->post_name,
			),
		);

		$components = $this->build_shortcode_components( $shortcode_blocks, $styles, 'page-' . $page->ID );
		if ( ! empty( $product_shortcode_components ) ) {
			$components = array_merge( $components, $product_shortcode_components );
		}
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
	 * Inject BlockSponsor blocks every 5th position in content_blocks.
	 *
	 * @param array $content_blocks Existing content blocks.
	 * @return array Content blocks with sponsors injected.
	 */
	private function inject_sponsors( array $content_blocks ): array {
		$result = array();
		$count  = 0;
		foreach ( $content_blocks as $block ) {
			$result[] = $block;
			$count++;
			if ( $count % 5 === 0 ) {
				$sponsor = PressNative_Sponsors::get_random_active_sponsor();
				if ( $sponsor !== null ) {
					$result[] = $sponsor;
				}
			}
		}
		return $result;
	}

	// ─── Product Reference Resolution ───────────────────────────────────

	/**
	 * Walk a content_blocks array and replace every ProductReference with
	 * live product data from WooCommerce. Consecutive product references are
	 * grouped into a single ProductCardCompact component.
	 *
	 * Runs at request time so prices, images and stock are always fresh.
	 *
	 * @param array $blocks Content blocks (may contain ProductReference items).
	 * @param array $styles Component styles for the product card.
	 * @return array Blocks with ProductReference items resolved or removed.
	 */
	private function resolve_product_references( array $blocks, array $styles ): array {
		if ( ! class_exists( 'PressNative_WooCommerce' ) || ! PressNative_WooCommerce::is_active() ) {
			return array_values( array_filter( $blocks, function ( $b ) {
				return ( $b['type'] ?? '' ) !== 'ProductReference';
			} ) );
		}

		$display_style = get_option( 'pressnative_product_in_post_style', 'compact_row' );
		$resolved      = array();
		$product_batch = array();

		foreach ( $blocks as $block ) {
			if ( ( $block['type'] ?? '' ) === 'ProductReference' ) {
				$product_batch[] = (int) $block['product_id'];
				continue;
			}

			// Flush any accumulated product references as a group.
			if ( ! empty( $product_batch ) ) {
				$card = $this->build_product_card_from_ids( $product_batch, $styles, $display_style );
				if ( $card ) {
					$resolved[] = $card;
				}
				$product_batch = array();
			}

			$resolved[] = $block;
		}

		// Trailing product references at end of content.
		if ( ! empty( $product_batch ) ) {
			$card = $this->build_product_card_from_ids( $product_batch, $styles, $display_style );
			if ( $card ) {
				$resolved[] = $card;
			}
		}

		return $resolved;
	}

	/**
	 * Whether parsed content blocks already include native product content.
	 *
	 * @param array $blocks Parsed block array.
	 * @return bool
	 */
	private function blocks_have_native_product_content( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			$type = is_array( $block ) ? ( $block['type'] ?? '' ) : '';
			if ( 'ProductReference' === $type || 'ProductCardCompact' === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build a ProductCardCompact content block from a list of product IDs.
	 *
	 * @param int[]  $product_ids  Product IDs.
	 * @param array  $styles       Component styles.
	 * @param string $display_style Display style preference.
	 * @return array|null The product card block or null if no products found.
	 */
	private function build_product_card_from_ids( array $product_ids, array $styles, string $display_style ): ?array {
		$products = array();
		foreach ( $product_ids as $pid ) {
			$p = PressNative_WooCommerce::get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			$products[] = array(
				'product_id'         => $p['product_id'],
				'title'              => $p['title'],
				'price'              => $p['price'],
				'image_url'          => $p['image_url'],
				'categories'         => $p['categories'] ?? array(),
				'action'             => array(
					'type'    => 'open_product',
					'payload' => array( 'product_id' => $p['product_id'] ),
				),
				'add_to_cart_action' => $p['add_to_cart_action'],
			);
		}

		if ( empty( $products ) ) {
			return null;
		}

		return array(
			'type'    => 'ProductCardCompact',
			'styles'  => $styles,
			'content' => array(
				'title'         => __( 'Shop these products', 'pressnative-apps' ),
				'products'      => $products,
				'display_style' => $display_style,
			),
		);
	}

	// ─── Native Content Block Parsing ────────────────────────────────────

	/**
	 * Progressive enhancement parser for post/page body content.
	 *
	 * Strategy:
	 * 1) Elementor override -> one BlockHtml wrapper for the full rendered HTML.
	 * 2) Parse Gutenberg blocks and map known "Core 8" blocks to native JSON.
	 * 3) Intercept WooCommerce "Big 3" shortcodes into native JSON components.
	 * 4) Unknown/custom blocks fall back to BlockHtml using render_block().
	 *
	 * @param WP_Post $post        Source post/page.
	 * @param string  $raw_content Raw post_content to parse.
	 * @return array
	 */
	private function parse_content_to_native_blocks( $post, $raw_content ) {
		if ( empty( $post ) || empty( $raw_content ) ) {
			return array();
		}

		$is_elementor = (bool) get_post_meta( $post->ID, '_elementor_edit_mode', true );
		if ( $is_elementor ) {
			$rendered = apply_filters( 'the_content', $raw_content );
			if ( ! empty( trim( wp_strip_all_tags( $rendered ) ) ) || ! empty( trim( $rendered ) ) ) {
				return array(
					array(
						'type' => 'BlockHtml',
						'html' => $this->wrap_content_with_assets( $rendered, $post ),
					),
				);
			}
			return array();
		}

		if ( ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		$blocks  = parse_blocks( $raw_content );
		$output  = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( $this->is_empty_block( $block ) ) {
				continue;
			}
			$mapped = $this->map_block_progressive( $block, $post );
			if ( empty( $mapped ) ) {
				continue;
			}
			foreach ( $mapped as $component ) {
				if ( ! empty( $component ) && is_array( $component ) ) {
					$output[] = $component;
				}
			}
		}

		return $output;
	}

	/**
	 * True when block is null-name and only whitespace content.
	 *
	 * @param array $block Parsed block.
	 * @return bool
	 */
	private function is_empty_block( $block ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : null;
		$inner_html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		$stripped = preg_replace( '#<script[^>]*>.*?</script>#si', '', (string) $inner_html );
		$stripped = preg_replace( '#<style[^>]*>.*?</style>#si', '', $stripped );
		$stripped = trim( wp_strip_all_tags( $stripped ) );

		return ( null === $block_name || '' === $block_name ) && '' === $stripped;
	}

	/**
	 * Maps one parsed block into one or more SDUI components.
	 *
	 * @param array   $block Parsed block.
	 * @param WP_Post $post  Source post/page.
	 * @return array
	 */
	private function map_block_progressive( $block, $post ) {
		$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$inner_html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
		$attrs      = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();

		$shortcode_mapped = $this->map_woocommerce_shortcode_block( $block_name, $inner_html, $attrs );
		if ( ! empty( $shortcode_mapped ) ) {
			return $shortcode_mapped;
		}

		$core_mapped = $this->map_core_block( $block_name, $inner_html, $attrs, $block );
		if ( ! empty( $core_mapped ) ) {
			return $core_mapped;
		}

		$fallback_html = '';
		if ( ! empty( $block_name ) && function_exists( 'render_block' ) ) {
			$fallback_html = (string) render_block( $block );
		} else {
			$fallback_html = $inner_html;
			if ( ! empty( $fallback_html ) && false !== strpos( $fallback_html, '[' ) && function_exists( 'do_shortcode' ) ) {
				$fallback_html = do_shortcode( $fallback_html );
			}
		}

		$fallback_html = preg_replace( '#<script[^>]*>.*?</script>#si', '', $fallback_html );
		$fallback_html = preg_replace( '#<style[^>]*>.*?</style>#si', '', $fallback_html );

		if ( '' === trim( wp_strip_all_tags( $fallback_html ) ) ) {
			return array();
		}

		return array(
			array(
				'type' => 'BlockHtml',
				'html' => $fallback_html,
			),
		);
	}

	/**
	 * Core 8 Gutenberg block mapping.
	 *
	 * @param string $block_name Block name.
	 * @param string $inner_html Inner html.
	 * @param array  $attrs      Block attrs.
	 * @param array  $block      Full block.
	 * @return array
	 */
	private function map_core_block( $block_name, $inner_html, $attrs, $block ) {
		switch ( $block_name ) {
			case 'core/paragraph':
				$plain_text = trim( wp_strip_all_tags( $inner_html ) );
				if ( $this->contains_shortcode_like_text( $plain_text ) ) {
					return array();
				}
				$allowed = strip_tags( $inner_html, '<a><b><i><strong><em>' );
				$text    = trim( wp_strip_all_tags( $allowed ) );
				if ( '' === $text ) {
					return array();
				}
				return array(
					array(
						'type'         => 'BlockText',
						'html_content' => trim( $allowed ),
						'text'         => $text,
						'style'        => 'paragraph',
					),
				);

			case 'core/heading':
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
				$level = max( 1, min( 6, $level ) );
				$allowed = strip_tags( $inner_html, '<a><b><i><strong><em>' );
				return array(
					array(
						'type'         => 'BlockText',
						'html_content' => trim( $allowed ),
						'text'         => trim( wp_strip_all_tags( $allowed ) ),
						'style'        => 'h' . $level,
					),
				);

			case 'core/image':
				$image = $this->parse_image_block( $block );
				return empty( $image ) ? array() : array( $image );

			case 'core/list':
				$items = array();
				if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/si', $inner_html, $matches ) ) {
					foreach ( $matches[1] as $item_html ) {
						$item_text = trim( wp_strip_all_tags( $item_html ) );
						if ( '' !== $item_text ) {
							$items[] = $item_text;
						}
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

			case 'core/quote':
				$quote = $this->parse_quote_block( $block );
				return empty( $quote ) ? array() : array( $quote );

			case 'core/buttons':
				$buttons = array();
				if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					foreach ( $block['innerBlocks'] as $inner ) {
						$button = $this->parse_button_like_html( isset( $inner['innerHTML'] ) ? (string) $inner['innerHTML'] : '' );
						if ( ! empty( $button ) ) {
							$buttons[] = $button;
						}
					}
				}
				if ( empty( $buttons ) ) {
					$button = $this->parse_button_like_html( $inner_html );
					if ( ! empty( $button ) ) {
						$buttons[] = $button;
					}
				}
				return $buttons;

			case 'core/gallery':
				$images = array();
				if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					foreach ( $block['innerBlocks'] as $inner ) {
						$img = $this->parse_image_block( $inner );
						if ( ! empty( $img ) ) {
							$images[] = $img;
						}
					}
				}
				if ( empty( $images ) && preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $inner_html, $matches ) ) {
					foreach ( $matches[1] as $url ) {
						$images[] = array(
							'type'   => 'BlockImage',
							'url'    => esc_url_raw( $url ),
							'width'  => 0,
							'height' => 0,
							'alt'    => '',
						);
					}
				}
				if ( empty( $images ) ) {
					return array();
				}
				return array(
					array(
						'type'   => 'BlockGallery',
						'images' => $images,
					),
				);

			case 'core/embed':
				$provider_url = '';
				if ( isset( $attrs['url'] ) ) {
					$provider_url = (string) $attrs['url'];
				} elseif ( preg_match( '/https?:\/\/[^\s"\']+/i', $inner_html, $m ) ) {
					$provider_url = $m[0];
				}
				if ( '' === $provider_url ) {
					return array();
				}
				return array(
					array(
						'type'         => 'BlockVideo',
						'provider_url' => esc_url_raw( $provider_url ),
					),
				);
		}

		return array();
	}

	/**
	 * Detects and maps WooCommerce "Big 3" shortcodes.
	 *
	 * @param string $block_name Block name.
	 * @param string $inner_html Inner html.
	 * @param array  $attrs      Block attrs.
	 * @return array
	 */
	private function map_woocommerce_shortcode_block( $block_name, $inner_html, $attrs ) {
		$shortcode_text = '';
		if ( 'core/shortcode' === $block_name ) {
			$shortcode_text = trim( wp_strip_all_tags( $inner_html ) );
		} elseif ( 'core/paragraph' === $block_name ) {
			$paragraph_text = trim( wp_strip_all_tags( $inner_html ) );
			if ( $this->contains_shortcode_like_text( $paragraph_text ) ) {
				$shortcode_text = $paragraph_text;
			}
		}

		if ( '' === $shortcode_text ) {
			return array();
		}

		if ( ! preg_match( '/^\[([a-zA-Z0-9_]+)([^\]]*)\]$/', $shortcode_text, $m ) ) {
			return array();
		}

		$shortcode = strtolower( $m[1] );
		$attr_str  = isset( $m[2] ) ? trim( $m[2] ) : '';
		$parsed    = array();
		if ( '' !== $attr_str ) {
			$maybe = shortcode_parse_atts( $attr_str );
			$parsed = is_array( $maybe ) ? $maybe : array();
		}

		if ( 'products' === $shortcode ) {
			return array(
				array(
					'type'    => 'ProductGrid',
					'params'  => array(
						'ids'      => isset( $parsed['ids'] ) ? (string) $parsed['ids'] : '',
						'category' => isset( $parsed['category'] ) ? (string) $parsed['category'] : '',
						'limit'    => isset( $parsed['limit'] ) ? (string) $parsed['limit'] : '',
						'columns'  => isset( $parsed['columns'] ) ? (string) $parsed['columns'] : '',
					),
					'source'  => 'shortcode',
				),
			);
		}

		if ( 'product_categories' === $shortcode ) {
			return array(
				array(
					'type'   => 'ProductCategoryList',
					'params' => array(
						'ids'     => isset( $parsed['ids'] ) ? (string) $parsed['ids'] : '',
						'number'  => isset( $parsed['number'] ) ? (string) $parsed['number'] : '',
						'parent'  => isset( $parsed['parent'] ) ? (string) $parsed['parent'] : '',
						'columns' => isset( $parsed['columns'] ) ? (string) $parsed['columns'] : '',
					),
					'source' => 'shortcode',
				),
			);
		}

		if ( 'add_to_cart' === $shortcode ) {
			$product_id = isset( $parsed['id'] ) ? (string) $parsed['id'] : '';
			if ( '' === $product_id ) {
				return array();
			}
			return array(
				array(
					'type' => 'BlockButton',
					'text' => isset( $parsed['text'] ) ? (string) $parsed['text'] : __( 'Add to cart', 'pressnative-apps' ),
					'action' => array(
						'type'    => 'add_to_cart',
						'payload' => array(
							'product_id' => $product_id,
						),
					),
				),
			);
		}

		return array();
	}

	/**
	 * Parse a single anchor/button html snippet into BlockButton.
	 *
	 * @param string $html HTML snippet.
	 * @return array|null
	 */
	private function parse_button_like_html( $html ) {
		if ( '' === trim( $html ) ) {
			return null;
		}
		$text = trim( wp_strip_all_tags( $html ) );
		$url  = '';
		if ( preg_match( '/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$url = esc_url_raw( $m[1] );
		}
		if ( '' === $text && '' === $url ) {
			return null;
		}
		return array(
			'type' => 'BlockButton',
			'text' => $text,
			'url'  => $url,
		);
	}

	/**
	 * Simple shortcode-like detector for paragraph/shortcode content.
	 *
	 * @param string $value Value to test.
	 * @return bool
	 */
	private function contains_shortcode_like_text( $value ) {
		return (bool) preg_match( '/\[[a-zA-Z0-9_]+[^\]]*\]/', (string) $value );
	}

	/**
	 * Extracts image data from a core/image block.
	 *
	 * @param array $block Parsed image block.
	 * @return array|null BlockImage object or null.
	 */
	private function parse_image_block( $block ) {
		$url    = '';
		$width  = 0;
		$height = 0;
		$alt    = '';
		$html   = $block['innerHTML'] ?? '';

		if ( ! empty( $block['attrs']['id'] ) ) {
			$attachment_id = (int) $block['attrs']['id'];
			$image = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( $image ) {
				$url    = $image[0];
				$width  = (int) $image[1];
				$height = (int) $image[2];
			}
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
		}

		if ( empty( $url ) && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m ) ) {
			$url = $m[1];
		}
		if ( empty( $width ) && preg_match( '/<img[^>]+width=["\']?(\d+)/', $html, $m ) ) {
			$width = (int) $m[1];
		}
		if ( empty( $height ) && preg_match( '/<img[^>]+height=["\']?(\d+)/', $html, $m ) ) {
			$height = (int) $m[1];
		}
		if ( empty( $alt ) && preg_match( '/<img[^>]+alt=["\']([^"\']*)["\']/', $html, $m ) ) {
			$alt = $m[1];
		}
		if ( empty( $width ) && ! empty( $block['attrs']['width'] ) ) {
			$width = (int) $block['attrs']['width'];
		}
		if ( empty( $height ) && ! empty( $block['attrs']['height'] ) ) {
			$height = (int) $block['attrs']['height'];
		}

		if ( empty( $url ) ) {
			return null;
		}

		return array(
			'type'   => 'BlockImage',
			'url'    => $url,
			'width'  => $width,
			'height' => $height,
			'alt'    => $alt,
		);
	}

	/**
	 * Extracts text and citation from a core/quote block.
	 *
	 * @param array $block Parsed quote block.
	 * @return array|null BlockQuote object or null.
	 */
	private function parse_quote_block( $block ) {
		$html   = $block['innerHTML'] ?? '';
		$text   = '';
		$author = '';

		if ( preg_match( '/<blockquote[^>]*>(.*?)<\/blockquote>/s', $html, $bq ) ) {
			$inner = $bq[1];
			if ( preg_match( '/<cite>(.*?)<\/cite>/s', $inner, $cite ) ) {
				$author = trim( wp_strip_all_tags( $cite[1] ) );
				$inner  = str_replace( $cite[0], '', $inner );
			}
			$text = trim( wp_strip_all_tags( $inner ) );
		}

		if ( empty( $text ) ) {
			return null;
		}

		return array(
			'type'   => 'BlockQuote',
			'text'   => $text,
			'author' => $author,
		);
	}

	/**
	 * Splits parsed Gutenberg blocks into segments at WooCommerce product
	 * shortcode boundaries. Used for inline-product mode.
	 *
	 * @param string $raw_content Raw post_content.
	 * @return array Segments — each is either ['type'=>'blocks','content_blocks'=>[...]]
	 *               or ['type'=>'product','product_id'=>int].
	 */
	private function split_blocks_at_product_shortcodes( $raw_content ) {
		if ( empty( $raw_content ) || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		$blocks          = parse_blocks( $raw_content );
		$product_pattern = '/\[(?:product_page|product|add_to_cart)\s+id=["\']?(\d+)["\']?[^\]]*\]/i';
		$segments        = array();
		$current_blocks  = array();

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';
			$inner_html = $block['innerHTML'] ?? '';

			if ( preg_match( $product_pattern, $inner_html, $m ) ) {
				if ( ! empty( $current_blocks ) ) {
					$segments[]    = array( 'type' => 'blocks', 'content_blocks' => $current_blocks );
					$current_blocks = array();
				}
				$segments[] = array( 'type' => 'product', 'product_id' => (int) $m[1] );
				continue;
			}

			if ( in_array( $block_name, array( 'core/group', 'core/columns', 'core/column' ), true ) ) {
				if ( ! empty( $block['innerBlocks'] ) ) {
					$current_blocks = array_merge( $current_blocks, $this->map_gutenberg_blocks( $block['innerBlocks'] ) );
				}
				continue;
			}

			$mapped = $this->map_single_block( $block );
			if ( null !== $mapped ) {
				$current_blocks[] = $mapped;
			}
		}

		if ( ! empty( $current_blocks ) ) {
			$segments[] = array( 'type' => 'blocks', 'content_blocks' => $current_blocks );
		}

		return $segments;
	}

	/**
	 * Returns true if the content contains any WooCommerce product shortcodes.
	 *
	 * @param string $content Raw post content.
	 * @return bool
	 */
	private function has_product_shortcodes( $content ) {
		if ( empty( $content ) ) {
			return false;
		}
		return (bool) preg_match(
			'/\[(?:product_page|product|add_to_cart)\s+id=["\']?\d+/i',
			$content
		);
	}

	/**
	 * Replaces WooCommerce product shortcodes with unique HTML-comment markers
	 * that survive the_content filters.
	 *
	 * e.g. [product_page id="76"] → <!--PRESSNATIVE_PRODUCT:76-->
	 *
	 * @param string $content Raw post content (after native shortcodes are stripped).
	 * @return string Content with product shortcodes replaced by markers.
	 */
	private function replace_product_shortcodes_with_markers( $content ) {
		return preg_replace_callback(
			'/\[(?:product_page|product|add_to_cart)\s+id=["\']?(\d+)["\']?[^\]]*\]/i',
			function( $m ) {
				return '<!--PRESSNATIVE_PRODUCT:' . $m[1] . '-->';
			},
			$content
		);
	}

	/**
	 * Splits processed HTML at <!--PRESSNATIVE_PRODUCT:X--> comment markers,
	 * returning an ordered array of segments.
	 *
	 * Each element is either:
	 *   [ 'type' => 'html',    'content'    => '<p>...</p>' ]
	 *   [ 'type' => 'product', 'product_id' => '76' ]
	 *
	 * @param string $html Fully processed HTML (after the_content + asset wrapping).
	 * @return array Ordered segments.
	 */
	private function split_html_at_product_markers( $html ) {
		$segments = array();
		$marker_pattern = '/<!--PRESSNATIVE_PRODUCT:(\d+)-->/';

		$parts = preg_split( $marker_pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE );

		// preg_split with PREG_SPLIT_DELIM_CAPTURE interleaves:
		// [ text, product_id, text, product_id, text, ... ]
		for ( $i = 0; $i < count( $parts ); $i++ ) {
			if ( $i % 2 === 0 ) {
				// HTML segment
				$html_chunk = $parts[ $i ];
				if ( '' !== trim( wp_strip_all_tags( $html_chunk ) ) ) {
					$segments[] = array( 'type' => 'html', 'content' => $html_chunk );
				} elseif ( '' !== trim( $html_chunk ) ) {
					// Keep script/style tags even if no visible text (asset wrapping).
					// Only add if it has <script> or <link> or <style> — skip pure whitespace.
					if ( preg_match( '/<(script|link|style)/i', $html_chunk ) ) {
						$segments[] = array( 'type' => 'html', 'content' => $html_chunk );
					}
				}
			} else {
				// Product ID captured group
				$segments[] = array( 'type' => 'product', 'product_id' => $parts[ $i ] );
			}
		}

		return $segments;
	}

	/**
	 * Collects product IDs from WooCommerce shortcodes in content.
	 *
	 * @param string $content Raw post content.
	 * @return array List of product IDs found.
	 */
	private function collect_product_ids_from_content( $content ) {
		$ids = array();
		if ( empty( $content ) ) {
			return $ids;
		}
		$patterns = array(
			'/\[product_page\s+id=["\']?(\d+)["\']?/i',
			'/\[product\s+id=["\']?(\d+)["\']?/i',
			'/\[add_to_cart\s+id=["\']?(\d+)["\']?/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches ) ) {
				foreach ( $matches[1] as $pid ) {
					$ids[] = (int) $pid;
				}
			}
		}
		return array_unique( $ids );
	}

	/**
	 * Extracts [product_page id="X"] shortcodes and builds native ProductCardCompact components.
	 *
	 * @param string $content Raw post content.
	 * @param array  $styles Component styles.
	 * @param string $prefix Component ID prefix.
	 * @return array List of ProductCardCompact component arrays.
	 */
	private function extract_product_shortcode_components( $content, $styles, $prefix ) {
		$components = array();
		if ( ! PressNative_WooCommerce::is_active() || empty( $content ) ) {
			return $components;
		}

		// Match multiple WooCommerce shortcode variants.
		$combined_pattern = '/\[(?:product_page|product|add_to_cart)\s+id=["\']?(\d+)["\']?[^\]]*\]/i';
		if ( ! preg_match_all( $combined_pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return $components;
		}
		$products = array();
		$seen    = array();
		foreach ( $matches as $m ) {
			$pid = (int) $m[1];
			if ( $pid > 0 && ! isset( $seen[ $pid ] ) ) {
				$seen[ $pid ] = true;
				$p = PressNative_WooCommerce::get_product( $pid );
				if ( $p ) {
					$products[] = array(
						'product_id'         => $p['product_id'],
						'title'              => $p['title'],
						'price'              => $p['price'],
						'image_url'          => $p['image_url'],
						'categories'         => isset( $p['categories'] ) ? $p['categories'] : array(),
						'action'             => array(
							'type'    => 'open_product',
							'payload' => array( 'product_id' => $p['product_id'] ),
						),
						'add_to_cart_action' => $p['add_to_cart_action'],
					);
				}
			}
		}
		if ( ! empty( $products ) ) {
			// Get the display style preference for in-post products
			$display_style = get_option( 'pressnative_product_in_post_style', 'compact_row' );
			
			$components[] = array(
				'id'      => $prefix . '-products',
				'type'    => 'ProductCardCompact',
				'styles'  => $styles,
				'content' => array(
					'title'        => __( 'Shop these products', 'pressnative-apps' ),
					'products'     => $products,
					'display_style' => $display_style,
				),
			);
		}
		return $components;
	}

	/**
	 * Strips [product_page ...] shortcodes from content.
	 *
	 * @param string $content Raw content.
	 * @return string Content with product_page shortcodes removed.
	 */
	private function strip_product_page_shortcodes( $content ) {
		// Strip all WooCommerce product shortcode variants.
		$patterns = array(
			'/\[product_page[^\]]*\]/i',
			'/\[product\s[^\]]*\]/i',
			'/\[add_to_cart[^\]]*\]/i',
			'/\[add_to_cart_url[^\]]*\]/i',
		);
		$content = preg_replace( $patterns, '', $content );

		return $content;
	}

	/**
	 * Strips WooCommerce product HTML that may have been rendered by the_content filters.
	 * Applied after apply_filters('the_content') to catch any expanded shortcode output.
	 *
	 * @param string $html Processed HTML content.
	 * @param array  $product_ids Array of product IDs that have been extracted as native components.
	 * @return string Cleaned HTML content.
	 */
	private function strip_wc_product_html( $html, $product_ids ) {
		if ( empty( $product_ids ) || empty( $html ) ) {
			return $html;
		}

		// Remove WooCommerce product containers that were rendered from shortcodes.
		// These have classes like .product, .woocommerce, .single-product.
		foreach ( $product_ids as $pid ) {
			// Remove product wrapper divs that match the product ID.
			$html = preg_replace(
				'/<div[^>]*class="[^"]*(?:product|woocommerce)[^"]*"[^>]*>.*?<\/div>\s*<\/div>/is',
				'',
				$html,
				1
			);
		}

		// Inject CSS to hide any remaining WooCommerce product elements that leak through.
		$hide_css = '<style>'
			. '.woocommerce .product, .woocommerce-page .product,'
			. '.product .summary, .product .cart,'
			. '.product .woocommerce-product-details__short-description,'
			. '.product .product_meta, .product .woocommerce-tabs,'
			. '.single-product .product .quantity,'
			. '.single-product .product .single_add_to_cart_button,'
			. 'form.cart, .woocommerce div.product { display: none !important; }'
			. '</style>';

		// Prepend the CSS if any product IDs were extracted.
		return $hide_css . $html;
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
			return array( 'id' => 'product-grid', 'type' => 'ProductGrid', 'styles' => $this->get_component_styles( 'tile' ), 'content' => array( 'columns' => 2, 'products' => array(), 'display_style' => 'card' ) );
		}
		$cols = PressNative_Layout_Options::get_product_grid_columns();
		$per  = PressNative_Layout_Options::get_product_grid_per_page();
		$products = PressNative_WooCommerce::get_products( array( 'limit' => $per ) );
		$grid_style = get_option( 'pressnative_product_grid_style', 'card' );
		return array(
			'id'      => 'product-grid',
			'type'    => 'ProductGrid',
			'styles'  => $this->get_component_styles( 'tile' ),
			'content' => array(
				'columns'       => $cols,
				'products'      => $products,
				'display_style' => $grid_style,
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
				'title' => _x( 'Shop', 'Screen title', 'pressnative-apps' ),
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
				'images'              => isset( $product['images'] ) ? $product['images'] : array(),
				'add_to_cart_action'  => $product['add_to_cart_action'],
			),
		);

		// Related products (native UI — proper images, Add to Cart buttons)
		$related_ids = function_exists( 'wc_get_related_products' ) ? wc_get_related_products( $product_id, 6 ) : array();
		$related_products = array();
		foreach ( $related_ids as $rid ) {
			$rp = PressNative_WooCommerce::get_product( (int) $rid );
			if ( $rp ) {
				$related_products[] = array(
					'product_id'         => $rp['product_id'],
					'title'              => $rp['title'],
					'price'              => $rp['price'],
					'image_url'          => $rp['image_url'],
					'action'             => array(
						'type'    => 'open_product',
						'payload' => array( 'product_id' => $rp['product_id'] ),
					),
					'add_to_cart_action' => $rp['add_to_cart_action'],
				);
			}
		}

		$components = array( $detail );
		if ( ! empty( $related_products ) ) {
			$components[] = array(
				'id'      => 'product-related-' . $product_id,
				'type'    => 'ProductCardCompact',
				'styles'  => $styles,
				'content' => array(
					'title'    => __( 'Related products', 'pressnative-apps' ),
					'products' => $related_products,
				),
			);
		}

		$layout = array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'product-' . $product_id,
				'title' => $product['title'],
			),
			'components' => $components,
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
		$grid_style = get_option( 'pressnative_product_grid_style', 'card' );
		$grid = array(
			'id'      => 'product-category-grid',
			'type'    => 'ProductGrid',
			'styles'  => $this->get_component_styles(),
			'content' => array(
				'columns'       => $cols,
				'products'      => $products,
				'display_style' => $grid_style,
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

	/**
	 * Documentation layout: returns the documentation screen layout.
	 *
	 * @return array Documentation layout with all recent updates.
	 */
	public function get_documentation_layout() {
		$documentation = array(
			'id'      => 'documentation',
			'type'    => 'Documentation',
			'styles'  => array(
				'colors' => array(
					'background' => '#ffffff',
					'text'       => '#111111',
					'accent'     => '#1A73E8',
				),
				'padding' => array(
					'horizontal' => 16,
					'vertical'   => 16,
				),
			),
			'content' => array(
				'title'    => 'PressNative Documentation',
				'sections' => array(
					array(
						'id'      => 'overview',
						'title'   => 'What is PressNative?',
						'icon'    => 'info',
						'content' => 'PressNative transforms WordPress sites into beautiful native mobile apps with award-winning UX and seamless content management.' . "\n\n" .
									'Built with modern native technologies (Jetpack Compose for Android, SwiftUI for iOS), PressNative delivers a premium app experience that rivals the best mobile apps in the App Store and Google Play.',
					),
					array(
						'id'      => 'features',
						'title'   => 'Key Features',
						'icon'    => 'star',
						'content' => '• Native iOS & Android apps with 60fps performance' . "\n" .
									'• Real-time content synchronization' . "\n" .
									'• Complete WooCommerce e-commerce integration' . "\n" .
									'• Push notifications for new content' . "\n" .
									'• Offline reading capabilities' . "\n" .
									'• Custom branding and theming' . "\n" .
									'• Deep linking and universal links' . "\n" .
									'• Analytics and user engagement tracking' . "\n" .
									'• Multi-site support with favorites' . "\n" .
									'• Search functionality' . "\n" .
									'• Category and tag filtering',
					),
					array(
						'id'      => 'woocommerce',
						'title'   => 'WooCommerce Support',
						'icon'    => 'shopping_cart',
						'content' => 'Award-winning e-commerce experience with:' . "\n\n" .
									'• Native product browsing with ProductGrid and ProductCarousel components' . "\n" .
									'• Instant "Quick Add" buttons with real-time cart badge updates' . "\n" .
									'• Native cart management with persistent storage' . "\n" .
									'• Seamless checkout via WebView with session cookie injection' . "\n" .
									'• Shoppable content - embed products directly in blog posts' . "\n" .
									'• Product categories and filtering' . "\n" .
									'• Product detail screens with image galleries' . "\n" .
									'• Speculative loading for instant checkout experience' . "\n" .
									'• Support for product variations and inventory' . "\n" .
									'• Cart abandonment recovery' . "\n" .
									'• Revenue analytics and conversion tracking',
					),
					array(
						'id'      => 'architecture',
						'title'   => 'Technical Architecture',
						'icon'    => 'settings',
						'content' => 'PressNative follows a modern, scalable architecture:' . "\n\n" .
									'• Contract-driven development with schema validation' . "\n" .
									'• WordPress Plugin as data provider' . "\n" .
									'• Core Registry Service for coordination' . "\n" .
									'• Native mobile shells (Android/iOS)' . "\n" .
									'• RESTful API with JSON schema validation' . "\n" .
									'• Offline-first data persistence' . "\n" .
									'• Real-time push notifications via Firebase' . "\n" .
									'• Custom update system for premium features' . "\n" .
									'• Freemium distribution model',
					),
					array(
						'id'      => 'setup',
						'title'   => 'Getting Started',
						'icon'    => 'settings',
						'content' => 'Quick setup in 5 steps:' . "\n\n" .
									'1. Install the PressNative WordPress plugin' . "\n" .
									'2. Configure your app branding and colors' . "\n" .
									'3. Set up home screen layout components' . "\n" .
									'4. Enable WooCommerce integration (optional)' . "\n" .
									'5. Download and configure the mobile apps' . "\n\n" .
									'For WooCommerce sites:' . "\n" .
									'• Ensure WooCommerce Store API is enabled' . "\n" .
									'• Configure product categories and featured products' . "\n" .
									'• Set up payment gateways (Stripe, PayPal, etc.)' . "\n" .
									'• Test the cart and checkout flow',
					),
					array(
						'id'      => 'distribution',
						'title'   => 'Plugin Distribution',
						'icon'    => 'info',
						'content' => 'PressNative uses a hybrid distribution strategy:' . "\n\n" .
									'• Lite version available on WordPress.org (GPL licensed)' . "\n" .
									'• Premium version with full features via custom update system' . "\n" .
									'• License key validation for premium features' . "\n" .
									'• Automatic updates for premium users' . "\n" .
									'• Revenue generation through premium subscriptions' . "\n" .
									'• Maximum reach while protecting business logic',
					),
					array(
						'id'      => 'support',
						'title'   => 'Support & Resources',
						'icon'    => 'help',
						'content' => 'Get help and stay updated:' . "\n\n" .
									'• Documentation: pressnative.app/docs' . "\n" .
									'• Video tutorials: pressnative.app/tutorials' . "\n" .
									'• Community support: pressnative.app/community' . "\n" .
									'• Feature requests: pressnative.app/feedback' . "\n" .
									'• GitHub repository: github.com/pressnative' . "\n" .
									'• Email support: support@pressnative.app' . "\n\n" .
									'For developers:' . "\n" .
									'• API documentation' . "\n" .
									'• Component reference' . "\n" .
									'• Schema validation tools' . "\n" .
									'• Custom component examples',
					),
				),
				'version'      => '2.0.0',
				'last_updated' => gmdate( 'Y-m-d' ),
			),
		);

		$layout = array(
			'branding'   => PressNative_Options::get_branding(),
			'screen'     => array(
				'id'    => 'documentation',
				'title' => 'Documentation',
			),
			'components' => array( $documentation ),
		);

		// Include shop config if WooCommerce is active.
		if ( PressNative_WooCommerce::is_active() ) {
			return $this->inject_shop_config( $layout );
		}

		return $layout;
	}
}
