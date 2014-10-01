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
			 * * "PUT":  http://wp-api.org/#posts_edit-a-meta-for-a-post
			 * * "POST": http://wp-api.org/#users_create-a-user_response
			 * * "GET":  http://wp-api.org/#posts_retrieve-posts
			 */
			'method' => 'GET',

			// Also covered nicely in the docs: http://wp-api.org/#posts_create-a-post_input
			// 'data'	 => "{ title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' }",
			'data'	 => '{}',

			/**
			 * Make a nonce as per docs.
			 * @see http://wp-api.org/guides/authentication.html#cookie-authentication
			 */
			'nonce'  => wp_create_nonce( 'wp_json' ),

		), $atts );

		// Make sure the json api is available.  If not, page will wp_die().
		$this -> version_check();

		// Enqueue jQuery.
		$this -> scripts();

		// Sanitize the shortcode args before sending them to our ajax script.
		$a = array_map( 'esc_attr', $a );

		/**
		 * Make a nonce as per docs.
		 * @see http://wp-api.org/guides/authentication.html#cookie-authentication
		 */
		$nonce = wp_create_nonce( 'wp_json' );
		
		// When we get a response from the JSON API, we'll give it some basic styles.
		$style = $this -> style();
		
		// This div will hold the response we get back after we ping the API.
		$output = "<output id='sjf-tjra-output'></output>";

		// To be clear, the form really doesn't do anything other than get clicked.  It's just a UI to trigger the Ajax call.
		$submit = __( 'Submit' );

		$field_array = array(
			'domain' => __( 'Domain:' ),
			'method' => __( 'Method:' ),
			'route'  => __( 'Route:' ),
			'data'   => __( 'Data:' ),
			'nonce'  => __( 'Nonce:' ),
		);
		$fields = '';
		foreach( $field_array as $k => $v ) {
			$fields .= "<label class='sjf-tjra-form-label' for='$k'>$v</label> <input id='$k' class='sjf-tjra-form-input' name='$k' value='$a[$k]'>";
		}
		$form = "
			<form action='#' method='$method' id='sjf-tjra-form'>
				$fields
				<button class='sjf-tjra-form-button' name='submit'>$submit</button>
			</form>
		";

		/**
		 * @todo Do more intelligent output by reporting each part of the http response, a la console.log( jqxhr );
		 */
		$inline_script = "

			<script>

				function escapeHtmlEntities (str) {
				if (typeof jQuery !== 'undefined') {
				// Create an empty div to use as a container,
				// then put the raw text in and get the HTML
				// equivalent out.
				return jQuery('<div/>').text(str).html();
				}

				// No jQuery, so use string replace.
				return str
				.replace(/&/g, '&amp;')
				.replace(/>/g, '&gt;')
				.replace(/</g, '&lt;')
				.replace(/\"/g, '&quot;');
				}

				( function( $ ) {

				    // Set us up for a sick show/hide when we get a response from the API.
					$( '#sjf-tjra-output' ).hide();

					// When the form is submit...
					$( '#sjf-tjra-form' ).on( 'submit', function( event ) {
			
						// Don't actually submit the form or reload the page.
						event.preventDefault();
	    
	    				// Replace it with loading text.
						$( '.sjf-tjra-form-button' ).replaceWith( 'loading...' );
	        
						// Fade out the form.
        			    $( this ).fadeOut();
            
            			// Get the domain, sans trailing slash.
        			    domain = $( '#domain' ).val();
        			    domain = domain.replace(/\/$/, '');

        			    method = $( '#method' ).val();

        			    // Get the route, sans trailing slash.
        			    route = $( '#route' ).val();
        			    route = route.replace(/\/$/, '');

        			    data = $( '#data' ).val();

        			    nonce = $( '#nonce' ).val();

        			    var url = domain + '/wp-json/' + route;	

						var jqxhr = jQuery.ajax({
							url:	 url,
							type: 	 method,
							data: 	 data,
							headers: {
								'X-WP-Nonce': nonce
							}
						})
						.done(function() {
							console.log( 'done' );
							console.log( jqxhr );
						})
						.fail(function() {
							console.log( 'fail' );
						})
						.always(function() {
							console.log( 'always' );
							
							responseText = jqxhr.responseText;
							readyState = jqxhr.readyState;
							status = jqxhr.status;
							statusText = jqxhr.statusText;
							
							var output ='<h3>readyState</h3> <p>' + readyState + '</p>';
							output += '<h3>status</h3> <p>' + status + '</p>';
							output += '<h3>statusText</h3> <p>' + statusText + '</p>';	
							output += '<h3>responseText</h3> <p>' + escapeHtmlEntities( responseText ) + '</p>';
							
							$( '#sjf-tjra-output' ).html( output ).fadeIn().css( 'display', 'block' );
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
				#sjf-tjra-form,
				#sjf-tjra-output {
					border: 1px solid #000;
					border-radius: 1em;
					font-family: courier, monospace;
					font-size: 12px;
					line-height: 2em;
					margin: 2em;
					padding: 2em;
				}
				#sjf-tjra-output >:first-child {
					margin-top: 0;
					padding-top: 0;
				}
				#sjf-tjra-output >:last-child {
					margin-bottom: 0;
					padding-bottom: 0;
				}

				.sjf-tjra-form-input,
				.sjf-tjra-form-button {
					display: block;
				}
				.sjf-tjra-form-input {
					margin-bottom: 1em;
					width: 100%;
				}
				.sjf-tjra-form-label {
					font-style: italic;
				}
				.sjf-tjra-form-button {
					border-radius: 3px;
					box-shadow: 0px 0px 3px rgba(0,0,0,.25);
				}
				.sjf-tjra-form-button:active {
					border-radius: 3px;
					box-shadow: none;
				}
				#method {
					text-transform: uppercase;
				}
			</style>
		";

		$out = apply_filters( 'sjf_tjra_style', $out );

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