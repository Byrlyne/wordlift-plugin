<?php

require_once 'functions.php';

/**
 * We're going to perform a full-blown test here:
 *  - create a post,
 *  - analyse a post,
 *  - save the entities,
 *  - check that the entities have been created:
 *    -- locally
 *    -- in the cloud
 *  - delete the entities (check deletion)
 *  - delete the post (check deletion)
 */
class PostTest extends WP_UnitTestCase {

	// The filename pointing to the tesst contents.
	const FILENAME = 'post.txt';
	const SLUG = 'tests-post';
	const TITLE = 'Test Post';
	// The number of expected entities (as available in the mock response).
	const EXPECTED_ENTITIES = 8;
	// When true, the remote response is saved locally and kept as a mock-up (be aware that the previous mockup is
	// overwritten).
	const SAVE_REMOTE_RESPONSE = false;

	/**
	 * Set up the test.
	 */
	function setUp() {
		parent::setUp();

		wl_configure_wordpress_test();

		wl_empty_blog();
		$this->assertEquals( 0, count( get_posts( array(
			'posts_per_page' => - 1,
			'post_type'      => 'post',
			'post_status'    => 'any'
		) ) ) );
		$this->assertEquals( 0, count( get_posts( array(
			'posts_per_page' => - 1,
			'post_type'      => 'entity',
			'post_status'    => 'any'
		) ) ) );

		// Empty the remote dataset.
		rl_empty_dataset();

		// Get the count of triples.
		$counts = rl_count_triples();
		$this->assertNotNull( $counts );
		$this->assertFalse( is_wp_error( $counts ) );
//		$this->assertEquals( 0, $counts['subjects'] );
//		$this->assertEquals( 0, $counts['predicates'] );
//		$this->assertEquals( 0, $counts['objects'] );
	}

	/**
	 * Test the plugin configuration.
	 */
	function testConfiguration() {

		// #43: https://github.com/insideout10/wordlift-plugin/issues/43
		// We're using WordLift Server, we do not require a Redlink Key nor a Dataset Name to be set:
		// we now require a WordLift key to be set. In turn, setting WordLift key should set the dataset URI,
		// that we'll continue to check.
		// $this->assertNotNull(wl_configuration_get_redlink_key());
		// $this->assertNotNull( wl_configuration_get_redlink_dataset_name() );
		// $this->assertNotNull( wl_configuration_get_redlink_user_id() );
		$this->assertNotNull( wl_configuration_get_key() );
		$this->assertNotNull( wl_configuration_get_redlink_dataset_uri() );

		$this->assertEquals( WL_CONFIG_DEFAULT_SITE_LANGUAGE, wl_configuration_get_site_language() );
	}

	/**
	 * Test the method to count the number of triples in the remote datastore.
	 */
	function testCountTriples() {

		// Get the count of triples.
		$counts = rl_count_triples();

		$this->assertNotNull( $counts );
		$this->assertTrue( is_array( $counts ) );
		$this->assertEquals( 3, count( $counts ) );
		$this->assertTrue( isset( $counts['subjects'] ) );
		$this->assertTrue( isset( $counts['predicates'] ) );
		$this->assertTrue( isset( $counts['objects'] ) );
	}

	/**
	 * Test create a post and submit it to Redlink for analysis.
	 */
	function testRedlinkAPI() {

		// Create the test post.
		$post_id = $this->createPost();

		// Send the post for analysis.
		$body = wl_analyze_post( $post_id );

		// Save the results to a file.
		if ( self::SAVE_REMOTE_RESPONSE ) {
			$output = dirname( __FILE__ ) . '/' . self::FILENAME . '.json';
			$result = file_put_contents( $output, $body );
			$this->assertFalse( false === $result );
		}

		// Delete the test post.
		$this->deletePost( $post_id );
	}

	/**
	 * Test a simple sparql query against Redlink to check whether SPARQL queries work fine.
	 */
	function testSPARQLQueries() {

		// Get the SPARQL template from the file.
		$filename        = dirname( __FILE__ ) . '/linked_data.sparql.template';
		$sparql_template = file_get_contents( $filename );

		// Get the user ID and dataset name.
		$user_id      = wl_configuration_get_redlink_user_id();
		$dataset_name = wl_configuration_get_redlink_dataset_name();

		// Set the entity URI.
		$uri = sprintf( "http://data.redlink.io/%s/%s/entity/Linked_Open_Data", $user_id, $dataset_name );

		// Apply the URI to the SPARQL template.
		$sparql = str_replace( '{uri}', $uri, $sparql_template );

		// Run the query.
		$result = wl_execute_sparql_query( $sparql );

		$this->assertTrue( $result );

		$this->checkEntityWithData( $uri, 'Linked Open Data', 'http://example.org/?post_type=entity&p=1978' );
	}

	/**
	 * Test saving entities passed via a metabox.
	 */
	function testEntitiesViaArray() {

		// Create a post.
		$post_id = $this->createPost();
		$this->assertTrue( is_numeric( $post_id ) );

		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		// Read the entities from the mock-up analysis.
		$analysis_results = wl_parse_file( dirname( __FILE__ ) . '/' . self::FILENAME . '.json' );
		$this->assertTrue( is_array( $analysis_results ) );

		// For each entity get the label, type, description and thumbnails.
		$this->assertTrue( isset( $analysis_results['entities'] ) );

		// Get a reference to the entities.
		$text_annotations = $analysis_results['text_annotations'];
		$best_entities    = array();
		foreach ( $text_annotations as $id => $text_annotation ) {
			$entity_annotation = wl_get_entity_annotation_best_match( $text_annotation['entities'] );
			$entity            = $entity_annotation['entity'];
			$entity_id         = $entity->{'@id'};

			if ( ! array_key_exists( $entity_id, $best_entities ) ) {
				$best_entities[ $entity_id ] = $entity;
			}
		}

		// Accumulate the entities in an array.
		$entities = array();
		foreach ( $best_entities as $uri => $entity ) {

			// Label
			if ( ! isset( $entity->{'http://www.w3.org/2000/01/rdf-schema#label'}->{'@value'} ) ) {
				var_dump( $entity );
			}
			$this->assertTrue( isset( $entity->{'http://www.w3.org/2000/01/rdf-schema#label'}->{'@value'} ) );
			$label = $entity->{'http://www.w3.org/2000/01/rdf-schema#label'}->{'@value'};
			$this->assertFalse( empty( $label ) );

			// Type
//            $type = wl_get_entity_type($entity);
//            $this->assertFalse(empty($type));
			// Description
			$description = wl_get_entity_description( $entity );
			$this->assertNotNull( $description );

			// Images
			$images = wl_get_entity_thumbnails( $entity );
			$this->assertTrue( is_array( $images ) );

			// Save the entity to the entities array.
			$entities = array_merge_recursive( $entities, array(
				$uri => array(
					'uri'         => $uri,
					'label'       => $label,
					'main_type'   => 'http://schema.org/Thing',
					'type'        => array(),
					'description' => $description,
					'images'      => $images
				)
			) );
		}

		// Save the entities in the array.
		$entity_posts = wl_save_entities( $entities );
		// Publish
		$entity_post_ids = array_map( function ( $item ) {
			return $item->ID;
		}, $entity_posts );

		foreach ( $entity_post_ids as $entity_id ) {
			wp_publish_post( $entity_id );
		}

		// TODO: need to bind entities with posts.
		wl_core_add_relation_instances( $post_id, WL_WHAT_RELATION, $entity_post_ids );

		$this->assertCount( sizeof( $entity_post_ids ), wl_core_get_related_entity_ids( $post_id ) );

		// TODO: synchronize data.
		// NOTICE: this requires a published post!
		wl_linked_data_push_to_redlink( $post_id );

		// Check that the entities are created in WordPress.
		$this->assertCount( count( $entities ), $entity_posts );

		// Check that each entity is bound to the post.
		$entity_ids = array();
		foreach ( $entity_posts as $post ) {
			// Store the entity IDs for future checks.
			array_push( $entity_ids, $post->ID );

			// Get the related posts IDs.
			$rel_posts = wl_core_get_related_post_ids( $post->ID );

			$this->assertCount( 1, $rel_posts );
			// The post must be the one the test created.
			$this->assertEquals( $post_id, $rel_posts[0] );
		}

		// Check that the post references the entities.
		$rel_entities = wl_core_get_related_entity_ids( $post_id );
		$this->assertEquals( count( $entity_ids ), count( $rel_entities ) );
		foreach ( $entity_ids as $id ) {
			$this->assertTrue( in_array( $id, $rel_entities ) );
		}

		// Check that the locally saved entities and the remotely saved ones match.
		$this->checkEntities( $entity_posts );

		// Check that the locally saved post data match the ones on Redlink.
		$this->checkPost( $post_id );

		// Check the post references, that they match between local and remote.
		$this->checkPostReferences( $post_id );

		// Delete the post.
		$this->deletePost( $post_id );
	}

	function testSaveImage() {


		wl_save_image( 'http://upload.wikimedia.org/wikipedia/commons/a/a6/Flag_of_Rome.svg' );

		wl_save_image( 'https://usercontent.googleapis.com/freebase/v1/image/m/04js6kc?maxwidth=4096&maxheight=4096' );
	}

	/**
	 * Create a test post.
	 * @return int
	 */
	function createPost() {

		// Get the post contents.
		$input   = dirname( __FILE__ ) . '/' . self::FILENAME;
		$content = file_get_contents( $input );
		$this->assertTrue( false != $content );

		// Create the post.
		$post_id = wl_create_post( $content, self::SLUG, self::TITLE, 'publish' );
		$this->assertTrue( is_numeric( $post_id ) );

		return $post_id;
	}

	/**
	 * Delete a post.
	 *
	 * @param $post_id
	 */
	function deletePost( $post_id ) {

		// Delete the post.
		$result = wl_delete_post( $post_id );
		$this->assertTrue( false != $result );
	}

	/**
	 * Check the provided entity posts against the remote Redlink datastore.
	 *
	 * @param array $posts The array of entity posts.
	 */
	function checkEntities( $posts ) {

		foreach ( $posts as $post ) {
			$this->checkEntity( $post );
		}
	}

	/**
	 * Check the provided entity post against the remote Redlink datastore.
	 *
	 * @param WP_Post $post The post to check.
	 */
	function checkEntity( $post ) {

		// Get the entity URI.
		$uri = wordlift_esc_sparql( wl_get_entity_uri( $post->ID ) );

		wl_write_log( "checkEntity [ post id :: $post->ID ][ uri :: $uri ]" );

		// Prepare the SPARQL query to select label and URL.
		$sparql = <<<EOF
SELECT DISTINCT ?label ?url ?type
WHERE {
    <$uri> rdfs:label ?label ;
           schema:url ?url ;
           a ?type .
}
EOF;

		// Send the query and get the response.
		$response = rl_sparql_select( $sparql );
		$this->assertFalse( is_wp_error( $response ) );

		$body = $response['body'];

		$matches = array();
		$count   = preg_match_all( '/^(?P<label>.*),(?P<url>.*),(?P<type>[^\r]*)/im', $body, $matches, PREG_SET_ORDER );
		$this->assertTrue( is_numeric( $count ) );

		// Expect only one match (headers + one row).
		if ( 2 !== $count ) {
			wl_write_log( "checkEntity [ post id :: $post->ID ][ uri :: $uri ][ body :: $body ][ count :: $count ][ count (expected) :: 2 ]" );
		}
		$this->assertEquals( 2, $count );

		// Focus on the first row.
		$match = $matches[1];

		// Get the label and URL from the remote answer.
		$label = $match['label'];
		$url   = $match['url'];
		$type  = $match['type'];

		// Get the post title and permalink.
		$post_title = $post->post_title;
		$permalink  = get_permalink( $post->ID );

		// Check for equality.
		$this->assertEquals( $post_title, $label );

		$this->assertEquals( $permalink, $url );
		$this->assertFalse( empty( $type ) );
	}

	/**
	 * Check the provided entity post against the remote Redlink datastore.
	 *
	 * @param string $uri The entity URI.
	 * @param string $title The entity title.
	 * @param string $permalink The entity permalink.
	 */
	function checkEntityWithData( $uri, $title, $permalink ) {

		wl_write_log( "checkEntityWithData [ uri :: $uri ]" );

		// Prepare the SPARQL query to select label and URL.
		$sparql = <<<EOF
SELECT DISTINCT ?label ?url ?type
WHERE {
    <$uri> rdfs:label ?label ;
           schema:url ?url ;
           a ?type .
}
EOF;

		// Send the query and get the response.
		$response = rl_sparql_select( $sparql );
		$this->assertFalse( is_wp_error( $response ) );

		$body = $response['body'];

		$matches = array();
		$count   = preg_match_all( '/^(?P<label>.*),(?P<url>.*),(?P<type>[^\r]*)/im', $body, $matches, PREG_SET_ORDER );
		$this->assertTrue( is_numeric( $count ) );

		// Expect only one match (headers + one row).
		$this->assertEquals( 2, $count );

		// Focus on the first row.
		$match = $matches[1];

		// Get the label and URL from the remote answer.
		$label = $match['label'];
		$url   = $match['url'];
		$type  = $match['type'];

		// Check for equality.
		$this->assertEquals( $title, $label );
		$this->assertEquals( $permalink, $url );
		$this->assertFalse( empty( $type ) );
	}

	/**
	 * Check that the local post data and the remote ones match.
	 *
	 * @param int $post_id The post ID to check.
	 */
	function checkPost( $post_id ) {

		// Get the post.
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		// Get the post Redlink URI.
		$uri = wordlift_esc_sparql( wl_get_entity_uri( $post->ID ) );

		wl_write_log( "checkPost [ uri :: $uri ]" );

		// Prepare the SPARQL query to select label and URL.
		$sparql = <<<EOF
SELECT DISTINCT ?author ?dateModified ?datePublished ?interactionCount ?url ?type ?label
WHERE {
    <$uri> schema:author ?author ;
           schema:dateModified ?dateModified ;
           schema:datePublished ?datePublished ;
           schema:interactionCount ?interactionCount ;
           schema:url ?url ;
           a ?type ;
           rdfs:label ?label .
}
EOF;

		// Send the query and get the response.
		$response = rl_sparql_select( $sparql );
		$this->assertFalse( is_wp_error( $response ) );

		$body = $response['body'];

		$matches = array();
		$count   = preg_match_all( '/^(?P<author>.*),(?P<dateModified>.*),(?P<datePublished>.*),(?P<interactionCount>.*),(?P<url>.*),(?P<type>.*),(?P<label>[^\r]*)/im', $body, $matches, PREG_SET_ORDER );
		$this->assertTrue( is_numeric( $count ) );

		// Expect only one match (headers + one row).
		if ( 2 !== $count ) {
			wl_write_log( "checkPost [ uri :: $uri ][ body :: $body ][ count :: $count ][ count (expected) :: 2 ]" );
		}

		// Expect only one match (headers + one row).
		$this->assertEquals( 2, $count );

		// Focus on the first row.
		$match = $matches[1];

		$author            = $match['author'];
		$date_modified     = $match['dateModified'];
		$date_published    = $match['datePublished'];
		$interaction_count = $match['interactionCount'];
		$url               = $match['url'];
		$type              = $match['type'];
		$label             = $match['label'];

		$permalink           = get_permalink( $post_id );
		$post_author_url     = Wordlift_User_Service::get_instance()->get_uri( $post->post_author );
		$post_date_published = wl_tests_time_0000_to_000Z( get_post_time( 'c', false, $post ) );
		$post_date_modified  = wl_tests_time_0000_to_000Z( wl_get_post_modified_time( $post ) );
		$post_comment_count  = 'UserComments:' . $post->comment_count;
		$post_entity_type    = 'http://schema.org/BlogPosting';
		$post_title          = $post->post_title;

		$this->assertEquals( $post_author_url, $author );
		// We expect datetime not to differ more than 5 seconds.
		$this->assertLessThan( 10, wl_tests_get_time_difference_in_seconds( $post_date_published, $date_published ) );
		$this->assertLessThan( 10, wl_tests_get_time_difference_in_seconds( $post_date_modified, $date_modified ) );
		$this->assertEquals( $post_comment_count, $interaction_count );
		$this->assertEquals( $permalink, $url );
		$this->assertEquals( $post_entity_type, $type );
		$this->assertEquals( $post_title, $label );
	}

	/**
	 * Check that the post is referencing the related entities.
	 *
	 * @param int $post_id The post ID.
	 */
	function checkPostReferences( $post_id ) {

		// Get the post.
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		// Get the post Redlink URI.
		$uri = wordlift_esc_sparql( wl_get_entity_uri( $post->ID ) );

		// Prepare the SPARQL query to select label and URL.
		$sparql = "SELECT DISTINCT ?uri WHERE { <$uri> dct:references ?uri . }";

		// Send the query and get the response.
		$response = rl_sparql_select( $sparql );
		$this->assertFalse( is_wp_error( $response ) );

		$body = $response['body'];

		$matches = array();
		$count   = preg_match_all( '/^(?P<uri>[^\r]*)/im', $body, $matches, PREG_SET_ORDER );
		$this->assertTrue( is_numeric( $count ) );

		$entity_ids = wl_core_get_related_entity_ids( $post->ID );

//        wl_write_log( "[ entity IDs :: " . join( ', ', $entity_ids ) . " ][ size of entity IDs :: " . sizeof( $entity_ids ) . " ][ count :: $count ][ post ID :: $post->ID ]" );
//
//        if ( $count !== ( 1 + sizeof( $entity_ids ) ) ) {
//            wl_write_log( "[ sparql :: $sparql ][ body :: $body ]" );
//        }
		// Expect only one match (headers + expected entities).
		$this->assertEquals( $count, sizeof( $entity_ids ) + 1 );

		$entity_uris = wl_post_ids_to_entity_uris( $entity_ids );
		for ( $i = 1; $i < $count; $i ++ ) {
			$entity_uri = $matches[ $i ]['uri'];
			// Check that the URI is in the array.
			$this->assertTrue( in_array( $entity_uri, $entity_uris ) );
		}
	}

}

function getDatasetName() {
	$dataset_name = "$this->dataset_name_prefix-php-" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "-wp-$this->wp_version-ms-$this->wp_multisite";

	return str_replace( '.', '-', $dataset_name );
}
