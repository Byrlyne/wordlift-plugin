<?php
/**
 * The Linked Data module provides synchronization of local WordPress data with the remote Linked Data store.
 */

require_once( 'wordlift_linked_data_images.php' );

// TODO: remove this method.
function wordlift_save_post( $post_id ) {
	wl_linked_data_save_post( $post_id );
}

/**
 * Receive events from post saves, and split them according to the post type.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post id.
 */
function wl_linked_data_save_post( $post_id ) {

	// If it's not numeric exit from here.
	if ( ! is_numeric( $post_id ) || is_numeric( wp_is_post_revision( $post_id ) ) ) {
		return;
	}

	// unhook this function so it doesn't loop infinitely
	remove_action( 'save_post', 'wl_linked_data_save_post' );

	// raise the *wordlift_save_post* event.
	do_action( 'wordlift_save_post', $post_id );

	// re-hook this function
	add_action( 'save_post', 'wl_linked_data_save_post' );
}

add_action( 'save_post', 'wl_linked_data_save_post' );

// TODO: remove this function.
function wordlift_save_post_and_related_entities( $post_id ) {
	wl_linked_data_save_post_and_related_entities( $post_id );
}

/**
 * Save the post to the triple store. Also saves the entities locally and on the triple store.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post id being saved.
 */
function wl_linked_data_save_post_and_related_entities( $post_id ) {

	// Ignore auto-saves
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// get the current post.
	$post = get_post( $post_id );

	// Only process posts that are published or scheduled to be published.
	if ( 'publish' !== $post->post_status && 'future' !== $post->post_status ) {
		wl_write_log( "post is not publish [ post id :: $post_id ][ post status :: $post->post_status ]" );

		return;
	}

	remove_action( 'wordlift_save_post', 'wordlift_save_post_and_related_entities' );

	wl_write_log( "[ post id :: $post_id ][ autosave :: false ][ post type :: $post->post_type ]" );

	// Save the entities coming with POST data.
	if ( isset( $_POST['wl_entities'] ) ) {

		wl_write_log( "[ post id :: $post_id ][ POST(wl_entities) :: " );
		wl_write_log( var_export( $_POST['wl_entities'], true ) );
		wl_write_log( "]" );

		$entities_via_post = array_values( $_POST['wl_entities'] );

		wl_write_log( "[ entities_via_post :: " );
		wl_write_log( $entities_via_post );
		wl_write_log( "]" );

		wl_save_entities( $entities_via_post, $post_id );

		// If there are props values, save them.
		if ( isset( $_POST[ WL_POST_ENTITY_PROPS ] ) ) {
			foreach ( $_POST[ WL_POST_ENTITY_PROPS ] as $key => $values ) {
				wl_entity_props_save( $key, $values );
			}
		}
	}

	// Save entities coming as embedded in the text.
//    wordlift_save_entities_embedded_as_spans( $post->post_content, $post_id );

	// Update related entities.
	wl_set_referenced_entities( $post->ID, wl_linked_data_content_get_embedded_entities( $post->post_content ) );

	// Push the post to Redlink.
	wl_push_to_redlink( $post->ID );

	add_action( 'wordlift_save_post', 'wordlift_save_post_and_related_entities', 9 );
}

add_action( 'wordlift_save_post', 'wordlift_save_post_and_related_entities', 9 );

// TODO: remove this method.
function wl_save_entities( $entities, $related_post_id = null ) {
	return wl_linked_data_save_entities( $entities, $related_post_id );
}

/**
 * Save the specified entities to the local storage.
 *
 * @param array $entities An array of entities.
 * @param int $related_post_id A related post ID.
 *
 * @return array An array of posts.
 */
function wl_linked_data_save_entities( $entities, $related_post_id = null ) {

	wl_write_log( "[ entities count :: " . count( $entities ) . " ][ related post id :: $related_post_id ]" );

	// Prepare the return array.
	$posts = array();

	// Save each entity and store the post id.
	foreach ( $entities as $entity ) {
		$uri   = $entity['uri'];
		$label = $entity['label'];

		// This is the main type URI.
		$main_type_uri = $entity['main_type'];

		// the preferred type.
		$type_uris = $entity['type'];

		$description = $entity['description'];
		$images      = ( isset( $entity['image'] ) ?
			( is_array( $entity['image'] )
				? $entity['image']
				: array( $entity['image'] ) )
			: array() );
		$same_as     = ( isset( $entity['sameas'] ) ?
			( is_array( $entity['sameas'] )
				? $entity['sameas']
				: array( $entity['sameas'] ) )
			: array() );

		// Save the entity.
		$post = wl_save_entity( $uri, $label, $main_type_uri, $description, $type_uris, $images, $related_post_id, $same_as );

		// Store the post in the return array if successful.
		if ( null !== $post ) {
			array_push( $posts, $post );
		}
	}

	return $posts;
}

// TODO: remove this function.
function wl_save_entity( $uri, $label, $type_uri, $description, $entity_types = array(), $images = array(), $related_post_id = null, $same_as = array() ) {
	return wl_linked_data_save_entity( $uri, $label, $type_uri, $description, $entity_types, $images, $related_post_id, $same_as );
}

/**
 * Save the specified data as an entity in WordPress. This method only create new entities. When an existing entity is
 * found (by its URI), then the original post is returned.
 *
 * @param string $uri The entity URI.
 * @param string $label The entity label.
 * @param string $type_uri The entity type URI.
 * @param string $description The entity description.
 * @param array $entity_types An array of entity type URIs.
 * @param array $images An array of image URLs.
 * @param int $related_post_id A related post ID.
 * @param array $same_as An array of sameAs URLs.
 *
 * @return null|WP_Post A post instance or null in case of failure.
 */
function wl_linked_data_save_entity( $uri, $label, $type_uri, $description, $entity_types = array(), $images = array(), $related_post_id = null, $same_as = array() ) {
	// Avoid errors due to null.
	if ( is_null( $entity_types ) ) {
		$entity_types = array();
	}

	wl_write_log( "[ uri :: $uri ][ label :: $label ][ type uri :: $type_uri ][ related post id :: $related_post_id ]" );

	// Check whether an entity already exists with the provided URI.
	$post = wl_get_entity_post_by_uri( $uri );

	// Return the found post, do not overwrite data.
	if ( null !== $post ) {
		wl_write_log( ": post exists [ post id :: $post->ID ][ uri :: $uri ][ label :: $label ][ related post id :: $related_post_id ]" );

		return $post;
	}

	// No post found, create a new one.
	$params = array(
		'post_status'  => ( is_numeric( $related_post_id ) ? get_post_status( $related_post_id ) : 'draft' ),
		'post_type'    => 'entity',
		'post_title'   => $label,
		'post_content' => $description,
		'post_excerpt' => ''
	);

	// create or update the post.
	$post_id = wp_insert_post( $params, true );

	// TODO: handle errors.
	if ( is_wp_error( $post_id ) ) {
		wl_write_log( ': error occurred' );

		// inform an error occurred.
		return null;
	}

	wl_set_entity_main_type( $post_id, $type_uri );

	// Save the entity types.
	wl_set_entity_types( $post_id, $entity_types );

	// Get a dataset URI for the entity.
	$wl_uri = wl_build_entity_uri( $post_id );

	// Save the entity URI.
	wl_set_entity_uri( $post_id, $wl_uri );

	// Add the uri to the sameAs data if it's not a local URI.
	if ( $wl_uri !== $uri ) {
		array_push( $same_as, $uri );
	}
	// Save the sameAs data for the entity.
	wl_set_same_as( $post_id, $same_as );

	// Call hooks.
	do_action( 'wl_save_entity', $post_id );

	// If the coordinates are provided, then set them.
//    if (is_array($coordinates) && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
//        wl_set_coordinates($post_id, $coordinates['latitude'], $coordinates['longitude']);
//    }

	wl_write_log( "[ post id :: $post_id ][ uri :: $uri ][ label :: $label ][ wl uri :: $wl_uri ][ types :: " . implode( ',', $entity_types ) . " ][ images count :: " . count( $images ) . " ][ same_as count :: " . count( $same_as ) . " ]" );

	foreach ( $images as $image_remote_url ) {

		// Check if there is an existing attachment for this post ID and source URL.
		$existing_image = wl_get_attachment_for_source_url( $post_id, $image_remote_url );

		// Skip if an existing image is found.
		if ( null !== $existing_image ) {
			continue;
		}

		// Save the image and get the local path.
		$image = wl_save_image( $image_remote_url );

		// Get the local URL.
		$filename     = $image['path'];
		$url          = $image['url'];
		$content_type = $image['content_type'];

		$attachment = array(
			'guid'           => $url,
			// post_title, post_content (the value for this key should be the empty string), post_status and post_mime_type
			'post_title'     => $label,
			// Set the title to the post title.
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_mime_type' => $content_type
		);

		// Create the attachment in WordPress and generate the related metadata.
		$attachment_id = wp_insert_attachment( $attachment, $filename, $post_id );

		// Set the source URL for the image.
		wl_set_source_url( $attachment_id, $image_remote_url );

		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filename );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Set it as the featured image.
		set_post_thumbnail( $post_id, $attachment_id );
	}

	// Add the related post ID if provided.
	if ( null !== $related_post_id ) {
		// Add related entities or related posts according to the post type.
		wl_add_related( $post_id, $related_post_id );
		// And vice-versa (be aware that relations are pushed to Redlink with wl_push_to_redlink).
		wl_add_related( $related_post_id, $post_id );
	}

	// The entity is pushed to Redlink on save by the function hooked to save_post.
	// save the entity in the triple store.
	wl_push_to_redlink( $post_id );

	// finally return the entity post.
	return get_post( $post_id );
}

/**
 * Save the entity properties.
 *
 * @uses wl_entity_props_save_prop to save a specific property.
 *
 * @param string An entity URI.
 * @param array $props An array of entity properties.
 */
function wl_entity_props_save( $entity_uri, $props ) {

	$mappings = wl_entity_props_get_mappings();

	// Get the post by the URI.
	$post = wl_get_entity_post_by_uri( $entity_uri );

	// Return if there's no post.
	if ( null === $post ) {
		wl_write_log( "wl_entity_props_save : no post found [ entity uri :: $entity_uri ]" );

		return;
	}

	// Save each property.
	foreach ( $props as $key => $values ) {
		wl_entity_props_save_prop( $post->ID, $key, $values, $mappings );
	}
}

/**
 * Save the specified prop.
 *
 * @param int $post_id The post ID.
 * @param string $key The property name.
 * @param string $values The property values.
 * @param array $mappings An array of mappings from property URIs to field names.
 */
function wl_entity_props_save_prop( $post_id, $key, $values, $mappings ) {

	// The property is not found in mappings, then exit.
	if ( ! isset( $mappings[ $key ] ) ) {
		wl_write_log( "property not found in mappings [ post ID :: $post_id ][ key :: $key ][ values count :: " . count( $values ) . " ]" );

		return;
	}

	// Get the custom field name.
	$custom_field_name = $mappings[ $key ];

	wl_write_log( "[ post ID :: $post_id ][ custom field name :: $custom_field_name ][ key :: $key ][ values count :: " . count( $values ) . " ]" );

	// Don't overwrite existing data.
	$existing = get_post_meta( $post_id, $custom_field_name );
	if ( ! empty( $existing ) ) {
		return;
	}

	// Delete existing values for that custom field.
	delete_post_meta( $post_id, $custom_field_name );

	// Save the values.
	foreach ( $values as $value ) {
		add_post_meta( $post_id, $custom_field_name, $value );
	}
}


/**
 * Get an array of entities from the *itemid* attributes embedded in the provided content.
 *
 * @since 3.0.0
 *
 * @param string $content The content with itemid attributes.
 *
 * @return array An array of entity posts.
 */
function wl_linked_data_content_get_embedded_entities( $content ) {


	// Remove quote escapes.
	$content = str_replace( '\\"', '"', $content );

	// Match all itemid attributes.
	$pattern = '/<\w+[^>]*\sitemid="([^"]+)"[^>]*>/im';

	wl_write_log( "Getting entities embedded into content [ pattern :: $pattern ][ content :: $content ]" );

	// Remove the pattern while it is found (match nested annotations).
	$matches = array();

	// In case of errors, return an empty array.
	if ( false === preg_match_all( $pattern, $content, $matches ) ) {
		wl_write_log( "Found no entities embedded in content" );

		return array();
	}

//    wl_write_log("wl_update_related_entities [ content :: $content ][ data :: " . var_export($data, true). " ][ matches :: " . var_export($matches, true) . " ]");

	// Collect the entities.
	$entities = array();
	foreach ( $matches[1] as $uri ) {
		$uri_d = html_entity_decode( $uri );
		$entity = wl_get_entity_post_by_uri( $uri_d );
		if ( null !== $entity ) {
			array_push( $entities, $entity->ID );
		}
	}

	$count = sizeof( $entities );
	wl_write_log( "Found $count entities embedded in content" );

	return $entities;
}


// TODO: remove this function.
function wl_push_to_redlink( $post_id ) {
	wl_linked_data_push_to_redlink( $post_id );
}

/**
 * Push the post with the specified ID to Redlink.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post ID.
 */
function wl_linked_data_push_to_redlink( $post_id ) {

	// Get the post.
	$post = get_post( $post_id );

	wl_write_log( "wl_push_to_redlink [ post id :: $post_id ][ post type :: $post->post_type ]" );

	// Call the method on behalf of the post type.
	switch ( $post->post_type ) {
		case 'entity':
			wl_push_entity_post_to_redlink( $post );
			break;
		default:
			wl_push_post_to_redlink( $post );
	}

	// Reindex the triple store if buffering is turned off.
	if ( false === WL_ENABLE_SPARQL_UPDATE_QUERIES_BUFFERING ) {
		wordlift_reindex_triple_store();
	}
}
