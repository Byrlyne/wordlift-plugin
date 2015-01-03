<?php 
 
/**
 * Add custom wp taxonomy for iptc 
 */
function wl_add_iptc_taxonomy() {
    register_taxonomy( 'iptc', 'post', 
    	array( 
    		'hierarchical' => true, 
    		'label' => 'IPTC', 
    		'query_var' => true, 
    		'rewrite' => true 
    	) 
    );
}

add_action( 'init', 'wl_add_iptc_taxonomy', 0 );

/**
 * Iptc tagging for wordpress posts 
 *
 * @param string $post_id The current post ID.
 * @param string $label The iptc label to be added.
 * @param string $code The iptc code to be added.
 *
 * @return boolean true on success, otherwise false
 */
function wl_add_iptc_classification_to_post( $post_id, $label, $code ) {

	$parent_term_id = 0;	
	$labels = wl_split_iptc_label( $label );
	
	foreach ( wl_split_iptc_code( $code ) as $index => $subcode ) {

		// Check if the iptc term exsists
		$current_term = term_exists( $labels[ $index ], "iptc", $parent_term_id );
		
		if ( null == $current_term ) {
			// Create the term 	
			$result = wp_insert_term( $labels[ $index ], "iptc", array(
				"slug" 		=> 	$subcode,
				"parent" 	=> 	$parent_term_id
			) );
			$parent_term_id = $result["term_id"];

		} else {
			$parent_term_id = $current_term["term_id"];
		}
	}

	// Add the last created / loaded term to the current post
	// TODO Maybe parent categories should be added too 
	wp_set_post_terms( $post_id, $parent_term_id, "iptc", false );

	return true;
}

/**
 * Split iptc code 
 *
 * @param string $code The iptc code to be splitted.
 *
 * @return array Splitted codes collection 
 */
function wl_split_iptc_code( $code ) {

	$codes = array();
	$code_pattern = "/(\d{2})(\d{3})(\d{3})/";
	
	if ( preg_match($code_pattern, $code, $matches) ) {

		$third_level 	= 	$matches[3];
		$second_level 	= 	$matches[2];
		$first_level 	= 	$matches[1];

		// First level iptc code
		array_push($codes,  "{$first_level}000000");
		// Second level iptc code
		if ( intval( $second_level ) > 0 ) {
			array_push($codes, "{$first_level}{$second_level}000");
		}
		// Third level iptc code
		if ( intval( $third_level ) > 0 ) {
			array_push($codes, "{$first_level}{$second_level}{$third_level}" );
		}
	}

	return $codes;
}

/**
 * Split iptc label 
 *
 * @param string $label The iptc label to be splitted.
 *
 * @return array Splitted labels collection 
 */
function wl_split_iptc_label( $label ) {

	$label_separator = " - ";
	$labels = explode( $label_separator, $label );
	
	return $labels;
}