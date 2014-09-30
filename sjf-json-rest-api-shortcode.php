<?php

/**
 * Plugin Name: SJF Test JSON REST API
 * Plugin URI: http://scottfennell.org/2014/09/08/wordpress-json-rest-api-shortcode-tutorial/
 * Description: The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API:  http://wp-api.org/.
 * Version: 1.0.3
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

	// Incement this on every update in order to force the browser to update scripts.
	var $version = '1.0.3';

	/**
	 * Adds actions for our class methods.
	 */
	function __construct() {

		// Register the shortcode.
		add_shortcode( 'json', array( $this, 'json' ) );

	}

	/**
	 * Return an HTML form to ping the JSON API.  Upon success, show the response.
	 *
	 *	Example values:
	 *	* [json]
	 *	* [json route=users data="{'email':'dude@dude.com','username':'newuser','name':'New User','password':'secret'}"]
	 *	* [json route=posts method=get]
	 *
	 * @seehttp://wp-api.org/
	 * @param  $atts An array of strings used as WordPress shortcode args.
	 * @return string An HTML form with a script to send an ajax request to wp JSON API.
	 */
	function json( $atts ) {
		
		// The default args have the affecting of sending a 'POST' request to the 'posts' route, to create a new sample draft post.
		$a = shortcode_atts( array(

			// Explained quite well in the docs: http://wp-api.org/guides/getting-started.html#routes-vs-endpoints
			'domain'  => get_bloginfo( 'url' ),

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

		// Make sure the json api is available.  If not, page will wp_die().
		$this -> version_check();

		// Enqueue jQuery.
		$this -> scripts();

		// Sanitize the shortcode args before sending them to our ajax script.
		$domain = trailingslashit( esc_url( $a[ 'domain' ] ) );
		$route  = urlencode( $a[ 'route' ] );
		$method = strtoupper( esc_attr( $a[ 'method' ] ) );
		$data   = sanitize_text_field( $a[ 'data' ] );

		// Build a url to which we'll send our data.
		$url = $this -> build_url( $domain, $route );

		// Get current page protocol
		$protocol = isset( $_SERVER[ 'HTTPS'] ) ? 'https://' : 'http://';

		// Set up a variable to maybe hold a nonce.
		$nonce = __( '(Not needed for GET requests.)' );
		$nonce_header = 'null';

		// If it's not a get request, we need to do some stuff.
		if( $method != 'GET' ) {

			/*
			$data = array(
				'title' => 			'this is the title',
				'content_raw' => 	'this is the content',
			);

			$data = json_encode( $data );
			*/

			// If you're making anything other than a GET request, you need to supply data.
			if( empty( $data ) || ( $data == '{}' ) ) { wp_die( __( "You must supply data when making a $method request. Example: { title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' } " ) ); }

			
			/**
			 * Make a nonce as per docs.
			 * @see http://wp-api.org/guides/authentication.html#cookie-authentication
			 */
			$nonce = wp_create_nonce( 'wp_json' );
			$nonce_header = '{"X-WP-Nonce" : "'.$nonce.'"}';

		}

		/**
		 * Add the shortcode args to the DOM so we can grab them with jQuery.  Also nice for debugging/clarity.
		 * @todo It would probably be more useful, and be more readable, if these were text inputs in a form.
		 */
		$values = "
			<h3>" . __( "Shortcode args:" ) . "</h3>
			<ul id='values'>
				<li><strong>Domain:</strong> $domain</li>
				<li><strong>Method:</strong> $method</li>
				<li><strong>Route:</strong> $route</li>
				<li><strong>Data:</strong> $data</li>
				<li><strong>Nonce:</strong> $nonce</li>
			</ul>
		";
		
		// When we get a response from the JSON API, we'll give it some basic styles.
		$style = $this -> style();
		
		// This div will hold the response we get back after we ping the API.
		$output = "<output id='output'></output>";

		// To be clear, the form really doesn't do anything other than get clicked.  It's just a UI to trigger the Ajax call.
		$submit = __( 'Submit' );
		$form = "
			<form action='#' method='$method' id='sjf_tjra_form'>
				<button name='submit'>$submit</button>
			</form>
		";

		/**
		 * @todo Do more intelligent output by reporting each part of the http response, a al console.log( jqxhr );
		 */
		$inline_script = "

			<script>

				( function( $ ) {

				    // Set us up for a sick show/hide when we get a response from the API.
					$( '#output' ).hide();

					// When the form is submit...
					$( '#sjf_tjra_form' ).on( 'submit', function( event ) {
			
						// Don't actually submit the form or reload the page.
						event.preventDefault();
	    
	    				// Replace it with loading text.
						$( '#sjf_tjra_form button' ).replaceWith( 'loading...' );
	        
						// Fade out the form.
        			    $( '#sjf_tjra_form' ).fadeOut();
            
						var jqxhr = jQuery.ajax({
							url:	 '$url',
							type: 	 '$method',
							data: 	 '$data',
							headers: $nonce_header
						})
						.done(function() {
							console.log( 'done' );
						})
						.fail(function() {
							console.log( 'fail' );
						})
						.always(function() {
							console.log( 'always' );
							$( '#output' ).html( jqxhr.responseText ).fadeIn().css( 'display', 'block' );
						});

	     			});
		
				})( jQuery );

			</script>

		";

		$out = "
			$values
			$form
			$output
			$script
			$inline_script
			$style
		";

		$out = apply_filters( 'sjf_tjra_json', $out );

		return $out;

	}

	/**
	 * Return some basic styles for the JSON response.
	 *
	 * @return string CSS for styling the HTML we get from the JSON API.
	 */
	function style() {
		$out = "
			<style>
				#output {
					border: 1px solid #000;
					border-radius: 1em;
					font-family: courier, monospace;
					font-size: 12px;
					line-height: 2em;
					margin: 2em;
					padding: 2em;
				}
				#output >:first-child {
					margin-top: 0;
					padding-top: 0;
				}
				#output >:last-child {
					margin-bottom: 0;
					padding-bottom: 0;
				}
			</style>
		";

		$out = apply_filters( 'sjf_tjra_style', $out );

		return $out;
	}

	/**
	 * Build a url to which we'll send our JSON request.
	 * 
	 * @param  string $domain The domain to which we'll submit our JSON request.
	 * @param  string $route  The route at that domain to which we'll submit our JSON request.
	 * @return string A url to which we'll send our JSON request
	 */
	function build_url( $domain, $route ) {
		
		// Sanitize the domain.
		$domain  = trailingslashit( esc_url( $domain ) );
		
		// Append this item, which the WP JSON REST API plugin expects.
		$domain .= 'wp-json/';

		// Append the route.
		$route   = urlencode( $route );
		$out = $domain . $route;

		$out = apply_filters( 'sjf_tjra_build_url', $out );

		return $out;

	}

	/**
	 * Enqueue jQuery.
	 */
	function scripts() {

		// We are gonna need jQuery to send our ajax request.
		wp_enqueue_script( 'jquery' );

	}

	/**
	 * Check to make sure the JSON API is available. If not, wp_die.
	 */
	function version_check() {

		// Grab the version of WP for the current install.
		global $wp_version;

		// Assume we know the first version with JSON API.
		$first_version_with_core_json_api = 4.1;

		// If it's older than the first version with core API and does not have plugin version, die.
		if( ! function_exists( 'json_api_init' ) && ( $wp_version < $first_version_with_core_json_api ) ) {
			wp_die( __( 'You do not have the JSON API available.  Try updating your WordPress version or activating this plugin: https://wordpress.org/plugins/json-rest-api/' ) );
		}

	}

}