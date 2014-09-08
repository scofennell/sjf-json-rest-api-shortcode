<?php

/**
 * Plugin Name: SJF JSON Rest API Shortcode
 */

function sjf_rest_test() {

	$curl = curl_init( "https://public-api.wordpress.com/oauth2/token" );
	
	curl_setopt( $curl, CURLOPT_POST, true );
	curl_setopt( $curl, CURLOPT_POSTFIELDS, array(
    	'client_id' 	=> 36438,
    	'redirect_uri' 	=> 'http://scottfennell.org/rest-test',
    	'client_secret' => 'DCCoovZs57E81MQgILOXnZNMpYEYJiBpF9eAOFHtg67hAfa3O3sHv1TGPF2pxESG',
    	'code' 			=> $_GET[ 'code' ], // The code from the previous request
    	'grant_type' 	=> 'authorization_code'
	) );

	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1);
	$auth = curl_exec( $curl );
	$secret = json_decode($auth);
	$access_key = $secret -> access_token;

	wp_die( var_dump( $cul ) );

	$options  = array (
		'http' => array (
			'ignore_errors' => true,
			'header' => array (
    			0 => "authorization: Bearer $access_key",
    		),
  		),
	);
 
	$context  = stream_context_create( $options );
	
	$response = file_get_contents(
		'https://public-api.wordpress.com/rest/v1/me/?pretty=1',
		false,
		$context
	);

	$response = json_decode( $response );

	wp_die( var_dump( $response ) );

	return $response;

}
add_shortcode( 'sjf_rest_test', 'sjf_rest_test' );

?>