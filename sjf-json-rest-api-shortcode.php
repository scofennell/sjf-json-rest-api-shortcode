<?php

/**
 * Plugin Name: SJF Test JSON Rest API
 */

/**
 * Return an HTML form to ping the JSON API.  Upon success, show the response.
 *
 *	Example values:
 *	* [sjf_tjra_form]
 *	* [sjf_tjra_form route=users data="{'email':'dude@dude.com','username':'newuser','name':'New User','password':'secret'}"]
 *	* [sjf_tjra_form route=posts method=get]
 * 
 * @return  string An HTML form with a script to send an ajax request to wp JSON API.
 */
function sjf_tjra_form( $atts ) {
	
    $a = shortcode_atts( array(
        'route'  => 'posts',
        'data'	 => "{ title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' }",
        'method' => 'POST',
    ), $atts );


	// Build a url to which we'll send our data.
	$base = trailingslashit( get_bloginfo( 'url' ) ).'wp-json/';
	$route = sanitize_text_field( $a[ 'route' ] );
	$url = esc_url( $base.$route );

	// Sanitize and format the shortcode args before we pass them to our script.
	$data = sanitize_text_field( $a[ 'data' ] );
	$method = strtoupper( esc_attr( $a[ 'method' ] ) );
	
	// This script will send an ajx request to the JSON API.
	$script = sjf_tjra_script( $url, $data, $method );

	// When we get a response from the JSON API, we'll give it some basic styles.
	$style = sjf_tjra_style();
	
	// To be clear, the form really doesn't do anything.  It's just a UI to trigger the Ajax call.
	$out = "
		<form action='$url' method='$method' id='sjf_tjra_form'>
			<input type='submit' value='Submit' name='submit'>
		</form>
		<div id='output'>
		</div>
		$script
		$style
	";

	return $out;

}
add_shortcode( 'sjf_tjra_form', 'sjf_tjra_form' );

function sjf_tjra_script( $url, $data, $method ) {
	$out = "
		<script>

			// Once the document is ready...
			jQuery( document ).ready(function( $ ) {
				
				// Set us up for a sick show/hide when we get a response from the API.
				$( '#output' ).hide();

				// When the form is submit...
				$( '#sjf_tjra_form' ).on( 'submit', function( event ) {
					
					// Don't actually submit the form or reload the page.
					event.preventDefault();
				
					// Grab the data from the shortcode.
					var data = $data;

					// Build an ajax request.
					$.ajax({

						// The method by which this request will be sent ( get, post, put, etc... ).
						type: '$method',

						// We want to send the data as JSON.
						contentType: 'application/json; charset=utf-8',

						// The url from the shortcode.
						url: '$url',

						// Again, we are passing the data as JSON.
						data: JSON.stringify( data ),

						// When we get the data back to us, we just want it as text to display in the browser.
						dataType: 'text',
					})

					// Upon success, show the data we get back from our request.
					.success( function( data ) {

						// Populate that output div with the data and fade it in.
						$( '#output' ).html( data ).fadeIn();
					})	

					// Upon fail, prompt the user to see some error details...
					.fail( function( data ) {

						// Populate that output div with the data and fade it in.
						$( '#output' ).html( 'Your request failed. See console for details.' ).fadeIn();
					});;
	
				});
				
			});
		</script>
	";
	return $out;
}

/**
 * Return some basic styles for the JSON response.
 */
function sjf_tjra_style() {
	$out = "
		<style>
			#output {
			    background: none repeat scroll 0 0 black;
			    border-radius: 1em;
			    color: white;
			    font-family: courier, monospace;
			    font-size: 12px;
			    line-height: 2em;
			    margin: 2em;
			    padding: 2em;
			}
		</style>
	";
	return $out;
}