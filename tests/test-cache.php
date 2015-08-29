<?php
/**
 * Tests for the widget in /includes/widget.php
 */
class KM_RPBT_Cache_Tests extends WP_UnitTestCase {

	/**
	 * Utils object to create posts with terms.
	 *
	 * @var object
	 */
	private $utils;

	private $args = null;

	private $plugin;


	/**
	 * Set up.
	 */
	function setUp() {
		parent::setUp();

		// Use the utils class to create posts with terms.
		$this->utils = new RPBT_Test_Utils( $this->factory );
	}

	function setup_cache(){
		// Activate cache
		add_filter( 'related_posts_by_taxonomy_cache', '__return_true' );

		$plugin = km_rpbt_plugin();
		if( $plugin ) {
			$plugin->_setup();
			$this->plugin = $plugin;
		}
	}

	/**
	 * Test if cache is enabled by using the filter.
	 * 
	 * @depends KM_RPBT_Functions_Tests::test_km_rpbt_plugin
	 */
	function test_cache_setup() {
		$this->setup_cache();
		$this->assertTrue( class_exists( 'Related_Posts_By_Taxonomy_Cache' )  );
		$this->assertTrue( isset( $this->plugin->cache ) );
		$this->assertTrue( $this->plugin->cache instanceof Related_Posts_By_Taxonomy_Cache );
	}


	/**
	 * Tests if cache filter is set to false (by default).
	 *
	 * @depends KM_RPBT_Functions_Tests::test_km_rpbt_plugin
	 */
	function test_cache_filter() {
		add_filter( 'related_posts_by_taxonomy_cache', array( $this->utils, 'return_bool' ) );
		$plugin = km_rpbt_plugin();
		$plugin->_setup();
		$this->assertFalse( $this->utils->boolean  );
		$this->utils->boolean = null;
	}


	/**
	 * Tests if cache filter display_cache_log is set to false (by default).
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_filter_display_cache_log() {
		$this->setup_cache();
		$this->assertFalse( $this->plugin->cache->cache['display_cache_log']  );
	}


	/**
	 * Test cache.
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_with_shortcode_in_post_content() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->utils->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		// Add a shortcode to cache.
		wp_update_post( array(
				'ID'          => $posts[0],
				'post_content' => '[related_posts_by_tax]',
			)
		);

		// Go to the single post page
		$this->go_to( get_permalink( $posts[0] ) );

		$cache_query = "SELECT $wpdb->postmeta.meta_key FROM $wpdb->postmeta WHERE meta_key LIKE '_rpbt_related_posts%'";

		$meta = $wpdb->get_var( $cache_query );

		// Cache should be empty.
		$this->assertEmpty( $meta  );

		// Trigger cache.
		ob_start();
		the_post();
		the_content();
		$content = ob_get_clean();

		$meta_key = $wpdb->get_var( $cache_query );

		// Cache should be set for the shortcode in $post[0] content.
		$this->assertNotEmpty( $meta_key );

		// Get related post ids for $post[0] from cache.
		// This also tests if the cache is set for the right post ID
		$cache_ids = get_post_meta( $posts[0], $meta_key, true );
		unset( $cache_ids['rpbt_current'] );
		$cache_ids = array_keys( $cache_ids );
		$this->assertNotEmpty( $cache_ids );

		// Get related post ids with function.
		$args = array( 'fields' => 'ids' );
		$taxonomies = array_keys( $this->plugin->taxonomies );
		$related = km_rpbt_related_posts_by_taxonomy( $posts[0], $taxonomies, $args );

		$this->assertEquals( $cache_ids, $related );
	}


	/**
	 * Test manually setting the cache for a post id.
	 *
	 * @depends test_cache_setup
	 */
	function test_manually_cache_related_posts() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->utils->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );
		$related_posts = km_rpbt_cache_related_posts( $posts[1], $taxonomies, $args );

		$cache_query = "SELECT $wpdb->postmeta.meta_key FROM $wpdb->postmeta WHERE meta_key LIKE '_rpbt_related_posts%'";
		$meta_key    = $wpdb->get_var( $cache_query );

		// Cache should be set for $post[1].
		$this->assertNotEmpty( $meta_key );

		// Get cache for $post[1];
		$cache_ids = get_post_meta( $posts[1], $meta_key, true );
		unset( $cache_ids['rpbt_current'] );
		$cache_ids = array_keys( $cache_ids );
		$this->assertNotEmpty( $cache_ids );

		$this->assertEquals( array( $posts[0], $posts[2], $posts[3] ), $cache_ids );

		$related = km_rpbt_related_posts_by_taxonomy( $posts[1], $taxonomies, $args );
		$this->assertEquals( $cache_ids, $related );
	}

	/**
	 * Test manually setting the cache for a post id.
	 *
	 * @depends test_cache_setup
	 */
	function test_flush_cache() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->utils->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );

		// Cache related posts for post 2
		$related_posts = km_rpbt_cache_related_posts( $posts[2], $taxonomies, $args );

		$cache_query = "SELECT $wpdb->postmeta.meta_key FROM $wpdb->postmeta WHERE meta_key LIKE '_rpbt_related_posts%'";
		$meta_key    = $wpdb->get_var( $cache_query );

		// Cache should be set for $post[2].
		$this->assertNotEmpty( $meta_key );

		km_rpbt_flush_cache();

		$meta_key = $wpdb->get_var( $cache_query );

		// Cache should be empty.
		$this->assertEmpty( $meta_key );
	}

}