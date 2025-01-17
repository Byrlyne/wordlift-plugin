<?php
/**
 * This file contains miscellaneous admin-functions.
 */

// Add the Admin menu.
require_once( 'wordlift_admin_menu.php' );

/**
 * Serialize an entity post.
 *
 * @param array $entity The entity post or the entity post id.
 *
 * @return array mixed The entity data array.
 */
function wl_serialize_entity( $entity ) {

	$entity = ( is_numeric( $entity ) ) ? get_post( $entity ) : $entity;
	
	$type   = wl_entity_type_taxonomy_get_type( $entity->ID );
	$images = wl_get_image_urls( $entity->ID );

	return array(
		'id'         	=> wl_get_entity_uri( $entity->ID ),
		'label'      	=> $entity->post_title,
		'description'	=> $entity->post_content,
		'sameAs'     	=> wl_schema_get_value( $entity->ID, 'sameAs' ),
		'mainType'      => str_replace( 'wl-', '', $type['css_class'] ),
		'types'      	=> wl_get_entity_rdf_types( $entity->ID ),
		'images' 		=> $images,

	);
}

/**
 * Removes empty text annotations from the post content.
 *
 * @since 1.0.0
 *
 * @param array $data The post data.
 *
 * @return array mixed The post data array.
 */
function wl_remove_text_annotations( $data ) {

	//    <span class="textannotation" id="urn:enhancement-777cbed4-b131-00fb-54a4-ed9b26ae57ea">
	$pattern = '/<(\w+)[^>]*\sclass=\\\"textannotation(?!\sdisambiguated)[^\\"]*\\\"[^>]*>([^<]+)<\/\1>/im';
	// Remove the pattern while it is found (match nested annotations).
	while ( 1 === preg_match( $pattern, $data['post_content'] ) ) {		
		$data['post_content'] = preg_replace( $pattern, '$2', $data['post_content'], -1, $count ); 
	}

	return $data;
}

add_filter( 'wp_insert_post_data', 'wl_remove_text_annotations', '98', 1 );

/**
 * Adds wl-metabox CSS class to a metabox.
 *
 * @since 3.2.0
 *
 * @param array $classes List of CSS classes already assigned to the metabox.
 *
 * @return array The updated list of CSS classes.
 */
function wl_admin_metaboxes_add_css_class( $classes = array() ){
	
	return array_merge( $classes, array( 'wl-metabox' ) );
}
