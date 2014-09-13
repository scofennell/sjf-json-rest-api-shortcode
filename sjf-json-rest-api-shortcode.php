<?php

/**
 * Plugin Name: SJF Test JSON REST API
 * Plugin URI: http://scottfennell.org/2014/09/08/wordpress-json-rest-api-shortcode-tutorial/
 * Description: The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API:  http://wp-api.org/.
 * Version: 1.0
 * Author: scofennell@gmail.com
 * Author URI: http://scottfennell.org
 * License: GPL2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

// A constant to define the path to this plugin file.
define( 'SJF_TJRA_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

// A constant to define the url to this plugin file.
define( 'SJF_TJRA_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Instantiate the plugin class.
 */
function sjf_tjra_init() {
    new SJF_Test_JSON_REST_API();
}
add_action( 'init', 'sjf_tjra_init' );

/**
 * The main plugin class.  Loads javascript to do ajax read more.
 */
class SJF_Test_JSON_REST_API {

	/**
	 * Adds actions for our class methods.
	 */
	function __construct() {
                
		// Ajax handler for non logged in users.
		add_action( 'wp_ajax_nopriv_sjf_tjra_ajax', array( $this, 'sjf_tjra_ajax' ) );

		// Ajax handler for logged in users.
		add_action( 'wp_ajax_sjf_tjra_ajax', array( $this, 'sjf_tjra_ajax' ) );

		// Register the shrotcode.
		add_shortcode( 'sjf_tjra_form', array( $this, 'sjf_tjra_form' ) );

    }

	/**
	 * Return an HTML form to ping the JSON API.  Upon success, show the response.
	 *
	 *	Example values:
	 *	* [sjf_tjra_form]
	 *	* [sjf_tjra_form route=users data="{'email':'dude@dude.com','username':'newuser','name':'New User','password':'secret'}"]
	 *	* [sjf_tjra_form route=posts method=get]
	 *
	 * @see    http://wp-api.org/
	 * @param  $atts An array of strings used as WordPress shortcode args.
	 * @return string An HTML form with a script to send an ajax request to wp JSON API.
	 */
	function sjf_tjra_form( $atts ) {
		
		// The default args have the affecting of sending a 'POST' request to the 'posts' route, to create a new sample draft post.
	    $a = shortcode_atts( array(

	    	// Explained quite well in the docs: http://wp-api.org/guides/getting-started.html#routes-vs-endpoints
	        'domain'  => get_bloginfo(),

	    	// Explained quite well in the docs: http://wp-api.org/guides/getting-started.html#routes-vs-endpoints
	        // 'route'  => 'posts',
	        'route'  => '',
	        
	        /**
	         * As per docs:
	         * * "PUT": http://wp-api.org/#posts_edit-a-meta-for-a-post
	         * * "POST": http://wp-api.org/#users_create-a-user_response
	         * * "GET": http://wp-api.org/#posts_retrieve-posts
	         */
	        'method' => 'GET',

	        // Also covered nicely in the docs: http://wp-api.org/#posts_create-a-post_input
	        // 'data'	 => "{ title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' }",
			'data'	 => '{}',

	    ), $atts );

	    // Make sure the json api is available.
	    global $wp_version;
	    $first_version_with_core_json_api = 4.1;
	    if( ! function_exists( 'json_api_init' ) && ( $wp_version < $first_version_with_core_json_api ) ) {
	    	wp_die( __( 'You do not have the JSON API available.  Try updating your WordPress version or activating this plugin: https://wordpress.org/plugins/json-rest-api/' ) );
	    }

	    // We are gonna need jQuery to send our ajax request.    
		wp_enqueue_script( 'jquery' );
		
		// Our plugin js file that handles the ajax call.
		wp_enqueue_script(
			
			// Script handle.
			'sjf_tjra',

			// Script src.
			SJF_TJRA_URL . 'js/script.js',
			
			// Load it after jquery.
			array( 'jquery' ),
	        
			// No version number.
	        false,

	        // Load it in the footer.
	        true
	    );

		// Get current page protocol
		$protocol = isset( $_SERVER[ 'HTTPS'] ) ? 'https://' : 'http://';

		// Output admin-ajax.php URL with same protocol as current page
		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php', $protocol ),
		);

		// Grab JS values from the script we just registered.
		wp_localize_script( 'sjf_tjra', 'sjf_tjra', $params );

		// Build a url to which we'll send our data.
		$domain  = trailingslashit( esc_url( $a[ 'domain' ] ) );
		$domain .= 'wp-json/';
		$domain  = esc_url( $domain );
		$route   = sanitize_text_field( $a[ 'route' ] );
		$url     = $domain . $route;

		// Sanitize the shortcode args before sending them to our ajax script.
		$method = strtoupper( esc_attr( $a[ 'method' ] ) );
		$route  = sanitize_text_field( $a[ 'route' ] );
		$data   = sanitize_text_field( $a[ 'data' ] );

		// Add the shortcode args to the DOM so we can grab them with jQuery.  Also nice for debugging/clarity.
		$values = "
			<ul id='values'>
				<li id='domain'>$domain</li>
				<li id='method'>$method</li>
				<li id='route'>$route</li>
				<li id='data'>$data</li>
			</ul>
		"; 

		
		// If you're making anything other than a GET request, you need to supply data.
		if( $method != 'GET' ) {
			if( empty( $data ) || ( $data == '{}' ) ) { wp_die( __( "You must supply data when making a $method request. Example: { title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' } " ) ); }
		}

		// This script will send an ajx request to the JSON API.
		// $script = sjf_tjra_script( $url, $method, $domain, $data );

		// When we get a response from the JSON API, we'll give it some basic styles.
		$style = $this -> sjf_tjra_style();
		
		// This div will hold the response we get back after we ping the API.
		$output_div = "<div id='output'></div>";

		// To be clear, the form really doesn't do anything other than get clicked.  It's just a UI to trigger the Ajax call.
		$form = "
			<form action='$url' method='$method' id='sjf_tjra_form'>
				<input type='submit' value='Submit' name='submit'>
			</form>
		";

		$out = "
			$values
			$form
			$output_div
			$script
			$style
		";

		return $out;

	}

	/**
	 * The main ajax function.  This code gets executed on click.
	 */
	function sjf_tjra_ajax() {
	
		// Build a url to which we'll send our data.
		$domain  = trailingslashit( esc_url( $_REQUEST[ 'domain' ] ) );
		$domain  = esc_url( $domain );
		$route   = sanitize_text_field( $a[ 'route' ] );
		$url     = $domain . $route;

		// Sanitize the shortcode args before sending them to our ajax script.
		$route = sanitize_text_field( $_REQUEST[ 'route' ] );
		$method = strtoupper( esc_attr( $_REQUEST[ 'method' ] ) );
		$data = sanitize_text_field( $_REQUEST[ 'data' ] );
		
		// Options to pass to wp_remote_request.
		$args = array();

		// Send our request.
		$request = wp_remote_request( $url, $args );

		echo "$url";

		// The echo the body of our reqest.
		$body = esc_html( $request[ 'body' ] );
		echo $body;

		// This is necessary to avoid outputting a "0" after any ajax call in WordPress.
		die();

	}

	/**
	 * Return some basic styles for the JSON response.
	 *
	 * @return string CSS for styling the HTML we get from the JSON API.
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

}