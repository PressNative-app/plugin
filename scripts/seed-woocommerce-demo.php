<?php
/**
 * Seed WooCommerce with PressNative demo data: categories, products, and shoppable posts.
 *
 * Run via WP-CLI from the WordPress root (with WooCommerce active):
 *   wp eval-file wp-content/plugins/pressnative-app/scripts/seed-woocommerce-demo.php
 *
 * Creates product categories, 30+ sample products, and blog posts with
 * embedded [product id="..."] shortcodes for shoppable content.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	echo "Run via: wp eval-file " . __FILE__ . "\n";
	exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "WooCommerce must be active. Activate the plugin and run again.\n";
	exit( 1 );
}

/* ─── Product categories ─────────────────────────────────────────────── */
$product_categories = array(
	array( 'name' => 'Electronics',       'slug' => 'electronics',       'description' => 'Smartphones, laptops, accessories' ),
	array( 'name' => 'Fashion',           'slug' => 'fashion',            'description' => 'Clothing, shoes, jewelry' ),
	array( 'name' => 'Home & Garden',     'slug' => 'home-garden',       'description' => 'Furniture, decor, tools' ),
	array( 'name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors',  'description' => 'Fitness, camping, sports gear' ),
	array( 'name' => 'Books & Media',     'slug' => 'books-media',      'description' => 'Books, games, movies' ),
);

/* ─── Demo products: name, price, category slug, featured ────────────── */
$demo_products = array(
	// Electronics
	array( 'name' => 'iPhone 15 Pro',           'price' => 999,  'category' => 'electronics', 'featured' => true ),
	array( 'name' => 'MacBook Air M3',           'price' => 1299, 'category' => 'electronics', 'featured' => true ),
	array( 'name' => 'AirPods Pro',             'price' => 249,  'category' => 'electronics' ),
	array( 'name' => 'iPad Pro 12.9"',          'price' => 1099, 'category' => 'electronics' ),
	array( 'name' => 'Apple Watch Ultra',       'price' => 799,  'category' => 'electronics' ),
	array( 'name' => 'Wireless Keyboard',       'price' => 99,   'category' => 'electronics' ),
	array( 'name' => 'USB-C Hub 7-in-1',        'price' => 59,   'category' => 'electronics' ),
	// Fashion
	array( 'name' => 'Premium Wool Sweater',    'price' => 89,   'category' => 'fashion', 'featured' => true ),
	array( 'name' => 'Designer Jeans',          'price' => 129,  'category' => 'fashion' ),
	array( 'name' => 'Leather Boots',           'price' => 199,  'category' => 'fashion' ),
	array( 'name' => 'Silk Scarf',              'price' => 45,   'category' => 'fashion' ),
	array( 'name' => 'Classic Blazer',          'price' => 179,   'category' => 'fashion' ),
	array( 'name' => 'Cotton T-Shirt Pack',     'price' => 39,   'category' => 'fashion' ),
	array( 'name' => 'Running Jacket',          'price' => 119,  'category' => 'fashion' ),
	// Home & Garden
	array( 'name' => 'Smart Coffee Maker',      'price' => 179,  'category' => 'home-garden', 'featured' => true ),
	array( 'name' => 'Ergonomic Office Chair',  'price' => 299,  'category' => 'home-garden' ),
	array( 'name' => 'Indoor Plant Collection', 'price' => 49,   'category' => 'home-garden' ),
	array( 'name' => 'Desk Lamp LED',           'price' => 69,   'category' => 'home-garden' ),
	array( 'name' => 'Throw Pillow Set',        'price' => 34,   'category' => 'home-garden' ),
	array( 'name' => 'Garden Tool Set',        'price' => 79,   'category' => 'home-garden' ),
	array( 'name' => 'Bluetooth Speaker',      'price' => 89,   'category' => 'home-garden' ),
	// Sports & Outdoors
	array( 'name' => 'Yoga Mat Pro',            'price' => 79,   'category' => 'sports-outdoors' ),
	array( 'name' => 'Running Shoes',           'price' => 159, 'category' => 'sports-outdoors' ),
	array( 'name' => 'Camping Tent 4-Person',    'price' => 249,  'category' => 'sports-outdoors' ),
	array( 'name' => 'Water Bottle 32oz',       'price' => 29,   'category' => 'sports-outdoors' ),
	array( 'name' => 'Dumbbell Set',            'price' => 129,  'category' => 'sports-outdoors' ),
	array( 'name' => 'Cycling Helmet',          'price' => 59,   'category' => 'sports-outdoors' ),
	array( 'name' => 'Resistance Bands',        'price' => 24,   'category' => 'sports-outdoors' ),
	// Books & Media
	array( 'name' => 'Bestseller Novel',        'price' => 16,   'category' => 'books-media' ),
	array( 'name' => 'Programming Guide',       'price' => 44,   'category' => 'books-media' ),
	array( 'name' => 'Board Game Classic',      'price' => 35,   'category' => 'books-media' ),
	array( 'name' => 'Wireless Earbuds',        'price' => 79,   'category' => 'books-media' ),
	array( 'name' => 'Streaming Stick',         'price' => 49,   'category' => 'books-media' ),
);

$default_description = 'Quality product with great reviews. Perfect for everyday use.';

/* ─── Shoppable posts: title, excerpt, product slugs to embed ─────────── */
$shoppable_posts = array(
	array(
		'title'   => 'Best Tech Gadgets 2026',
		'excerpt' => 'Our top picks for smartphones, laptops, and accessories this year.',
		'slugs'   => array( 'iphone-15-pro', 'macbook-air-m3', 'airpods-pro' ),
	),
	array(
		'title'   => 'Spring Fashion Trends',
		'excerpt' => 'Refresh your wardrobe with these seasonal essentials.',
		'slugs'   => array( 'premium-wool-sweater', 'designer-jeans', 'silk-scarf' ),
	),
	array(
		'title'   => 'Home Office Setup Guide',
		'excerpt' => 'Create a productive workspace with these must-have items.',
		'slugs'   => array( 'smart-coffee-maker', 'ergonomic-office-chair', 'indoor-plant-collection' ),
	),
	array(
		'title'   => 'Fitness Journey Essentials',
		'excerpt' => 'Gear that keeps you motivated and comfortable.',
		'slugs'   => array( 'yoga-mat-pro', 'running-shoes', 'water-bottle-32oz' ),
	),
);

/* ─── Helpers ────────────────────────────────────────────────────────── */
$term_ids_by_slug = array();
$product_ids_by_slug = array();

function seed_wc_ensure_category( $cat ) {
	global $term_ids_by_slug;
	if ( isset( $term_ids_by_slug[ $cat['slug'] ] ) ) {
		return $term_ids_by_slug[ $cat['slug'] ];
	}
	$existing = get_term_by( 'slug', $cat['slug'], 'product_cat' );
	if ( $existing ) {
		$term_ids_by_slug[ $cat['slug'] ] = (int) $existing->term_id;
		return $term_ids_by_slug[ $cat['slug'] ];
	}
	$r = wp_insert_term( $cat['name'], 'product_cat', array(
		'slug'        => $cat['slug'],
		'description' => isset( $cat['description'] ) ? $cat['description'] : '',
	) );
	if ( is_wp_error( $r ) ) {
		return 0;
	}
	$term_ids_by_slug[ $cat['slug'] ] = (int) $r['term_id'];
	return $term_ids_by_slug[ $cat['slug'] ];
}

function seed_wc_create_product( $data ) {
	global $product_ids_by_slug;
	$slug = sanitize_title( $data['name'] );
	if ( isset( $product_ids_by_slug[ $slug ] ) ) {
		return $product_ids_by_slug[ $slug ];
	}
	$post_id = wp_insert_post( array(
		'post_title'   => $data['name'],
		'post_name'    => $slug,
		'post_status'  => 'publish',
		'post_type'    => 'product',
		'post_content' => isset( $data['description'] ) ? $data['description'] : '',
	) );
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return 0;
	}
	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return 0;
	}
	$product->set_regular_price( (string) $data['price'] );
	$product->set_price( (string) $data['price'] );
	$product->set_stock_status( 'instock' );
	$product->set_catalog_visibility( 'visible' );
	if ( ! empty( $data['featured'] ) ) {
		$product->set_featured( true );
	}
	$product->save();
	$cat_id = seed_wc_ensure_category( array( 'name' => $data['category'], 'slug' => $data['category'] ) );
	if ( $cat_id ) {
		wp_set_object_terms( $post_id, array( (int) $cat_id ), 'product_cat' );
	}
	$product_ids_by_slug[ $slug ] = (int) $post_id;
	return $product_ids_by_slug[ $slug ];
}

/* ─── Create categories ───────────────────────────────────────────────── */
foreach ( $product_categories as $cat ) {
	seed_wc_ensure_category( $cat );
}
echo "Product categories created.\n";

/* ─── Create products ─────────────────────────────────────────────────── */
foreach ( $demo_products as $p ) {
	$id = seed_wc_create_product( array_merge( $p, array( 'description' => $default_description ) ) );
	if ( $id ) {
		echo "  Product: {$p['name']} (ID $id)\n";
	}
}
echo "Products created.\n";

/* ─── Create shoppable posts ───────────────────────────────────────────── */
foreach ( $shoppable_posts as $post_data ) {
	$shortcodes = array();
	foreach ( $post_data['slugs'] as $pslug ) {
		if ( isset( $product_ids_by_slug[ $pslug ] ) ) {
			$shortcodes[] = '[product_page id="' . $product_ids_by_slug[ $pslug ] . '"]';
		}
	}
	$content = '<p>' . esc_html( $post_data['excerpt'] ) . '</p>' . "\n\n"
		. implode( "\n\n", $shortcodes ) . "\n\n"
		. '<p>Shop these picks and more in our store.</p>';
	$post_id = wp_insert_post( array(
		'post_title'   => $post_data['title'],
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_content' => $content,
		'post_excerpt' => $post_data['excerpt'],
	) );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		echo "  Shoppable post: {$post_data['title']} (ID $post_id)\n";
	}
}
echo "Shoppable posts created.\n";
echo "Done. WooCommerce demo data seeded.\n";
