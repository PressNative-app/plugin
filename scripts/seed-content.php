<?php
/**
 * Seed WordPress with categories and posts for PressNative demo.
 *
 * Run via WP-CLI from the WordPress root:
 *   wp eval-file wp-content/plugins/pressnative-app/scripts/seed-content.php
 *
 * Or add to wp-config.php temporarily: define('WP_ENVIRONMENT_TYPE','local');
 * Then use the REST API seed script: npm run seed (in www/)
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	echo "Run via: wp eval-file " . __FILE__ . "\n";
	exit( 1 );
}

$categories = array(
	array( 'name' => 'Featured', 'slug' => 'featured', 'description' => 'Hero carousel posts' ),
	array( 'name' => 'News', 'slug' => 'news', 'description' => 'Local news and updates' ),
	array( 'name' => 'Business', 'slug' => 'business', 'description' => 'Business and economy' ),
	array( 'name' => 'Community', 'slug' => 'community', 'description' => 'Neighborhood updates' ),
	array( 'name' => 'Events', 'slug' => 'events', 'description' => 'Local events' ),
);

$posts = array(
	array(
		'title'   => 'City Council Approves New Transit Upgrades',
		'excerpt' => 'Major improvements to bus and rail service will begin this spring.',
		'content' => '<p>City Council voted unanimously Tuesday to approve a $45 million transit improvement package that will add new bus routes, extend rail hours, and upgrade 12 stations.</p><p>"This is a game-changer for commuters," said Mayor Johnson. "We\'re investing in the infrastructure that connects our neighborhoods."</p><p>Construction is expected to begin in March, with phased rollouts through the end of the year.</p>',
		'cats'    => array( 'featured', 'news' ),
		'image'   => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800',
	),
	array(
		'title'   => 'Local Markets See Weekend Surge',
		'excerpt' => 'Shoppers are returning to downtown in record numbers.',
		'content' => '<p>Downtown merchants reported a 23% increase in foot traffic over the holiday weekend compared to last year.</p><p>"The new pedestrian plaza has made a huge difference," said Sarah Chen, owner of Chen\'s Books. "People are staying longer and exploring more shops."</p><p>City officials credit the revitalization program launched two years ago for the turnaround.</p>',
		'cats'    => array( 'featured', 'business' ),
		'image'   => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800',
	),
	array(
		'title'   => 'Community Garden Opens for Spring Season',
		'excerpt' => 'Residents can now reserve plots at the new Riverside Community Garden.',
		'content' => '<p>The Riverside Community Garden officially opened for the spring season on Saturday, with 80 plots available for residents.</p><p>"We\'ve had a waiting list since last fall," said garden coordinator Maria Rodriguez. "It\'s wonderful to see so much interest in urban gardening."</p><p>Plot registration is $25 per season. Applications are available at the community center.</p>',
		'cats'    => array( 'featured', 'community' ),
		'image'   => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800',
	),
	array(
		'title'   => 'Annual Food Festival Returns to Main Street',
		'excerpt' => 'Three days of food, music, and family fun starting Friday.',
		'content' => '<p>The Main Street Food Festival returns for its 12th year this weekend, featuring over 50 restaurants, food trucks, and local vendors.</p><p>New this year: a dedicated kids\' zone with face painting and cooking demos, and extended hours until 10 p.m. on Saturday.</p><p>Admission is free. Proceeds from vendor sales benefit the Downtown Improvement Fund.</p>',
		'cats'    => array( 'news', 'events' ),
		'image'   => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800',
	),
	array(
		'title'   => 'Tech Startup Announces 100 New Jobs',
		'excerpt' => 'Local software company plans major expansion.',
		'content' => '<p>DataFlow Inc., a homegrown analytics startup, announced plans to add 100 new positions over the next 18 months.</p><p>"We\'ve outgrown our current space twice," said CEO James Park. "We\'re committed to staying in the city and hiring locally."</p><p>The company is currently seeking engineers, designers, and customer success specialists.</p>',
		'cats'    => array( 'news', 'business' ),
		'image'   => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800',
	),
	array(
		'title'   => 'Neighborhood Watch Program Expands',
		'excerpt' => 'Five new blocks join the community safety initiative.',
		'content' => '<p>The Neighborhood Watch program expanded to five additional blocks this month, bringing total participation to 42 blocks.</p><p>"Residents are more connected than ever," said Officer Davis. "We\'re seeing fewer incidents and faster response times."</p><p>Interested residents can sign up at the next community meeting on the 15th.</p>',
		'cats'    => array( 'community' ),
		'image'   => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=800',
	),
	array(
		'title'   => 'Summer Concert Series Lineup Revealed',
		'excerpt' => 'Free outdoor concerts every Thursday in July.',
		'content' => '<p>The Parks Department announced the lineup for the annual Summer Concert Series, featuring local bands and a headliner each week.</p><p>Concerts will be held at Riverside Amphitheater at 7 p.m. every Thursday. Bring blankets and chairs—food trucks will be on site.</p><p>Full schedule available at parks.city.gov.</p>',
		'cats'    => array( 'events', 'community' ),
		'image'   => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800',
	),
	array(
		'title'   => 'Small Business Grant Applications Open',
		'excerpt' => '$500,000 in grants available for local entrepreneurs.',
		'content' => '<p>The Economic Development Office is accepting applications for the Small Business Growth Grant through March 31.</p><p>Grants of $5,000 to $25,000 are available for businesses with fewer than 20 employees. Priority is given to first-time applicants and minority-owned businesses.</p><p>Applications and guidelines are available at ed.city.gov/grants.</p>',
		'cats'    => array( 'business', 'news' ),
		'image'   => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800',
	),
	array(
		'title'   => 'Library Launches New Reading Program',
		'excerpt' => 'Adults can earn prizes for reading 20 books this year.',
		'content' => '<p>The Public Library\'s annual Reading Challenge is back, with new categories for fiction, nonfiction, and local authors.</p><p>"We\'ve had over 2,000 participants each year," said librarian Amy Foster. "It\'s a great way to discover new books and connect with other readers."</p><p>Sign up at any branch or online. Participants receive a completion certificate and entry into the grand prize drawing.</p>',
		'cats'    => array( 'community', 'events' ),
		'image'   => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=800',
	),
	array(
		'title'   => 'Transit Upgrades Coming This Spring',
		'excerpt' => 'City planners confirmed a new set of improvements for commuters.',
		'content' => '<p>City planners confirmed a new set of transit improvements for commuters, including real-time bus tracking and expanded bike-share stations.</p><p>"We\'re listening to rider feedback," said Transit Director Lisa Wong. "These changes will make a real difference in daily commutes."</p><p>The improvements are funded by the transit bond passed last November.</p>',
		'cats'    => array( 'news' ),
		'image'   => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800',
	),
);

function seed_ensure_loaded() {
	if ( ! function_exists( 'wp_insert_post' ) ) {
		$search = dirname( __FILE__ );
		for ( $i = 0; $i < 6; $i++ ) {
			$search = dirname( $search );
			$wp_load = $search . '/wp-load.php';
			if ( file_exists( $wp_load ) ) {
				require_once $wp_load;
				return;
			}
		}
		echo "Could not find wp-load.php. Run from WordPress root:\n";
		echo "  wp eval-file wp-content/plugins/pressnative-app/scripts/seed-content.php\n";
		exit( 1 );
	}
}

seed_ensure_loaded();

$cat_ids = array();
foreach ( $categories as $c ) {
	$term = get_term_by( 'slug', $c['slug'], 'category' );
	if ( $term ) {
		$cat_ids[ $c['slug'] ] = (int) $term->term_id;
		echo "  Category \"{$c['name']}\" exists (id={$cat_ids[$c['slug']]})\n";
	} else {
		$r = wp_insert_term( $c['name'], 'category', array( 'slug' => $c['slug'], 'description' => $c['description'] ) );
		if ( ! is_wp_error( $r ) ) {
			$cat_ids[ $c['slug'] ] = (int) $r['term_id'];
			echo "  Created category \"{$c['name']}\" (id={$cat_ids[$c['slug']]})\n";
		} else {
			echo "  Failed: {$c['slug']} - {$r->get_error_message()}\n";
		}
	}
}

foreach ( $posts as $p ) {
	$ids = array();
	foreach ( $p['cats'] as $slug ) {
		if ( isset( $cat_ids[ $slug ] ) ) {
			$ids[] = $cat_ids[ $slug ];
		}
	}
	if ( empty( $ids ) ) {
		$ids[] = 1;
	}

	$post_data = array(
		'post_title'   => $p['title'],
		'post_content' => $p['content'],
		'post_excerpt' => $p['excerpt'],
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'post',
	);

	$post_id = wp_insert_post( $post_data );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		wp_set_post_terms( $post_id, $ids, 'category' );
		$attachment_id = media_sideload_image( $p['image'], $post_id, $p['title'], 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
		echo "  Created post \"{$p['title']}\" (id=$post_id)\n";
	} else {
		echo "  Failed: {$p['title']}\n";
	}
}

echo "Done. Configure Layout Settings (Hero category: featured) and refresh the app.\n";
