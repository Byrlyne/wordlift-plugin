<?php

define('WL_DEFAULT_THUMBNAIL_PATH', plugins_url( 'js-client/slick/missing-image-150x150.png', __FILE__ ) );

// Database version 
define('WL_DB_VERSION', '1.0');
// Custom table name
define('WL_DB_RELATION_INSTANCES_TABLE_NAME', 'wl_relation_instances');

define('WL_WHAT_RELATION', 'what');
define('WL_WHO_RELATION', 'who');
define('WL_WHERE_RELATION', 'where');
define('WL_WHEN_RELATION', 'when');

// Classification boxes configuration for angularjs edit-post widget
// The array is serialized because array constants are only from php 5.6 on.
define('WL_CORE_POST_CLASSIFICATION_BOXES', serialize(array(
    array(
    	'id' 				=> WL_WHAT_RELATION,
    	'label' 			=> 'What',
    	'registeredTypes' 	=> array('event', 'organization', 'person', 'place', 'thing'),
        'selectedEntities' 	=> array()
    	),
    array(
    	'id' 				=> WL_WHO_RELATION,
    	'label' 			=> 'Who',
    	'registeredTypes' 	=> array('organization', 'person'),
        'selectedEntities' 	=> array()
    	),
    array(
    	'id' 				=> WL_WHERE_RELATION,
    	'label' 			=> 'Where',
    	'registeredTypes' 	=> array('place'),
        'selectedEntities' 	=> array()
    	),
    array(
    	'id' 				=> WL_WHEN_RELATION,
    	'label' 			=> 'When',
    	'registeredTypes' 	=> array('event'),
        'selectedEntities' 	=> array()
    	),
)));