<?php

/**
 * Plugin Name: SJF WP API Shortcode
 * Plugin URI: http://scottfennell.org/2014/09/08/wordpress-json-rest-api-shortcode-tutorial/
 * 
 * Author: Scott Fennell
 * Author URI: http://scottfennell.org
 * Description: The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API: http://wp-api.org/.
 * License: GPL2
 * Text Domain: json-rest-api-shortcode
 * Version: 2.0
 * 
 * This plugin will not work without the WP-API plugin, V2.
 * @see https://wordpress.org/plugins/rest-api/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Instantiate the plugin class.
 */
function sjf_wp_api_shortcode() {
	new SJF_WP_API_Shortcode();
}
add_action( 'init', 'sjf_wp_api_shortcode' );

/**
 * Our WP API Shortcode.
 * 
 * Registers a shortcode for drawing an HTML form for subitting requests to the
 * WP API.
 */
class SJF_WP_API_Shortcode {

	/**
	 * A prefix for all of our html tags and css targeting.
	 * 
	 * @var string
	 */
	private $prefix = 'sjf-wp_api-shortcode';

	/**
	 * A camelCase prefix for our translations.
	 * 
	 * @var string
	 */
	private $js_prefix = 'sjfWpApiShortcode';

	/**
	 * The WP API "lives" at this path.  I'm not exactly sure why, or how 
	 * stable that is, but at least it's DRY to this plugin.
	 * 
	 * @var string
	 */
	private $route_prefix = 'wp/v2/';

	/**
	 * This is the url where you can download the WordPress REST API (Version 2)
	 * plugin.
	 * 
	 * @var string
	 */
	private $api_plugin_url = 'https://wordpress.org/plugins/rest-api/';

	/**
	 * You must have at least this version of the Rest API plugin.
	 * 
	 * Typically the version number may also contain a 'beta' string or similar,
	 * but we're going to ignore that.
	 * 
	 * @var string
	 */
	private $min_api_version = '2.0';

	/**
	 * Add actions for our class methods.
	 */
	public function __construct() {

		// Register the shortcode.
		add_shortcode( 'wp_api', array( $this, 'wp_api' ) );

		// Pass our php variables to our JS.
		add_action( 'wp_enqueue_scripts', array( $this, 'localize' ), 999 );
	
	}

	/**
	 * Pass our php variables to our JS file.
	 */
	function localize() {

		// Grab admin-ajax.php URL with same protocol as current page.
		$localize = array(
			'prefix'        => $this -> prefix,
			'always'        => esc_attr__( 'WP API call is happening.', 'json-rest-api-shortcode' ),
			'fail'          => esc_attr__( 'WP API call failed.', 'json-rest-api-shortcode' ),
			'done'          => esc_attr__( 'WP API call is done.', 'json-rest-api-shortcode' ),
			'discover_always' => esc_attr__( 'Attempt to discover WP API URL is happening.', 'json-rest-api-shortcode' ),
			'discover_fail' => esc_attr__( 'Attempt to discover WP API URL failed.', 'json-rest-api-shortcode' ),
			'discover_done' => esc_attr__( 'Attempt to discover WP API URL is done.', 'json-rest-api-shortcode' ),
		);

		// Send to the plugin-wide JS file.  We have to associate it with a script, so we'll just say jquery.
		wp_localize_script( 'jquery', $this -> js_prefix, $localize );

	}

	/**
	 * Return an HTML form to ping the JSON API.  Upon success, show the
	 * response.
	 *
	 * This is the primary way to use this plugin.  All the other functions are
	 * in support of this function.
	 * 
	 * Example values:
	 * * [wp_api]
	 * * [wp_api route=users data="{'email':'dude@dude.com','username':'newuser','name':'New User','password':'secret'}"]
	 * * [wp_api route=posts method=get]
	 *
	 * @see    http://v2.wp-api.org/
	 * @param  array $atts An array of strings used as WordPress shortcode args.
	 * @return string An HTML form with a script to send an ajax request to wp JSON API.
	 */
	public function wp_api( $atts ) {
		
		// Make sure the WordPress REST API (Version 2)  is available.  If not, page will wp_die().
		$this -> api_plugin_check();

		// Grab the prefix for name-spacing our front-end code.
		$prefix = $this -> prefix;

		// The default args have the affecting of sending a 'POST' request to the 'posts' route, to create a new sample draft post.
		$a = shortcode_atts( array(

			// Any domain name.  Defaults to the current blog.
			'domain' => home_url(),

			// Any route.  Defaults to an empty string, which is the api root response.
			'route' => '',
	
			// Any REST verb such as GET, POST, etc...
			'method' => 'GET',

			// Any JSON string.
			'data' => '{}',

			/**
			 * Make a nonce as per docs.
			 * @see http://v2.wp-api.org/guide/authentication/
			 */
			'nonce' => wp_create_nonce( 'wp_rest' ),

			// Want some CSS for this stuff?
			'default_styles' => 1,

		), $atts );

		// Sanitize the shortcode args before sending them to our ajax script.
		$a = array_map( 'esc_attr', $a );
		
		// Grab some disclaimer text to make it clear that this thing is live.
		$disclaimer = $this -> get_disclaimer();

		// The fields in our form as key/value pairs.
		$field_array = array(
			'domain' => esc_html__( 'Domain:', 'json-rest-api-shortcode' ),
			'method' => esc_html__( 'Method:', 'json-rest-api-shortcode' ),
			'route'  => esc_html__( 'Route:',  'json-rest-api-shortcode' ),
			'data'   => esc_html__( 'Data:',   'json-rest-api-shortcode' ),
			'nonce'  => esc_html__( 'Nonce:',  'json-rest-api-shortcode' ),
		);

		// For each field, output it as a label/input.
		$fields = '';
		foreach( $field_array as $k => $v ) {
			$val = esc_attr( $a[ $k ] );
			$fields .= "
				<label class='$prefix-label' for='$k'>$v</label>
				<input id='$prefix-$k' class='$prefix-input' name='$prefix-$k' value='$val'>
			";
		}

		// A submit button for the form.
		$submit_text = esc_html__( 'Send a real, live API request!', 'json-rest-api-shortcode' );
		$submit = "<button class='$prefix-button' name='submit'>$submit_text</button>";

		/**
		 * Build the form.
		 * 
		 * To be clear, the form really doesn't do anything other than get
		 * clicked.  It's just a UI to trigger the Ajax call.
		 */
		$form = "
			
			<form action='#' id='$prefix-form'>
				$disclaimer	
				$fields
				$submit
			</form>
		";

		// This div will hold the response we get back after we ping the API.
		$output = "<output id='$prefix-output' class='$prefix-hide'></output>";

		// Enqueue jQuery.
		$this -> scripts();

		// Grab the JS to make our ajax call.
		$inline_script = $this -> get_ajax();

		// When we get a response from the JSON API, we'll give it some basic styles.
		$style = '';
		if( ! empty( $a['default_styles'] ) ) {
			$style = $this -> get_style();
		}

		$out = "
			$form
			$output
			$inline_script
			$style
		";

		$out = apply_filters( $prefix, $out );

		return $out;

	}

	/**
	 * Get some disclaimer text to make sure everyone understands that this
	 * thing really can execute queries.
	 * 
	 * @return string Some disclaimer text.
	 */
	private function get_disclaimer() {
	
		$prefix = $this -> prefix;

		$text = esc_html__( 'This form sends real, live API requests.  Be careful out there!', 'json-rest-api-shortcode' );
		$out  = "<p class='$prefix-disclaimer'>$text</p>";
	
		return $out;

	}

	/**
	 * Return the JS for making our ajax call.
	 *
	 * @return string The JS for making our ajax call.
	 */
	private function get_ajax() {

		// Grab the prefix for all of our front-end code.
		$prefix    = $this -> prefix;
		$js_prefix = $this -> js_prefix;

		$route_prefix = $this -> route_prefix;

		// An HTML comment to explain where this blob of JS is coming from.
		$added_by = $this -> get_added_by( __CLASS__, __FUNCTION__, __LINE__ );

		$out = <<<EOT
			$added_by
			<script>

				var always          = $js_prefix.always;
				var done            = $js_prefix.done;
				var fail            = $js_prefix.fail;
				var discover_always = $js_prefix.discover_always;
				var discover_done   = $js_prefix.discover_done;
				var discover_fail   = $js_prefix.discover_fail;
 
				/**
				 * Wrap a title and chunk of content for output.
				 * 
				 * @param  {string} subtitle A subtitle for this element.
				 * @param  {string} content  A brief paragraph of text.
				 * @return {string} The subtitle and content, wrapped with out standard markup.
				 */
				function wrap( subtitle, content ) {

					var subtitle = '<h3 class="$prefix-output-subtitle">' + subtitle + '</h3>';
					var content  = '<p class="$prefix-output-content">' + content + '</p>';

					var out = '<div class="$prefix-output-node">' + subtitle + content + '</div>';
				
					return out;

				}

				/**
				 * Make an ajax call to a domain.
				 * 
				 * This is a long story.  So, whatever domain we are making our
				 * API call to, we first need to call it and dig through its dom
				 * in order to discover the <link> that tells us the WP API url
				 * for that install.  This function allows us to make that first
				 * call, and await for the response, before proceeding with the 
				 * actual API call.
				 * 
				 * @return {object} The result of a jQuery ajax() call.
				 */
				function callDomain( domain ) {

			        return jQuery.ajax({
						url: domain
					});

				}

				/**
				 * Change the submit button to reflect a new status.
 				 * 
				 * @param {string} Text to relay to the user.
				 */
				function sayStatus( text ) {
					jQuery( '.$prefix-button' ).text( text );
				}

				/**
				 * Take the result of an ajax() call and convert it into a
				 * user-friendly output message.
				 *
				 * @param {object} A call to jQuery.ajax().
				 * @return {string} A message explaining the ajax call.
				 */
				function getOutput( jqxhr ) {
					
					// Start by grabbing the readyState, status, and statusText.
					var output = wrap( 'readyState', jqxhr.readyState );
					output += wrap( 'status', jqxhr.status );
					output += wrap( 'statusText', jqxhr.statusText );

					// If we got responseText, great, use it.
					var responseText = jQuery( '<div/>' ).text( jqxhr.responseText ).html();
					if ( responseText != '' ) {
						output += wrap( 'responseText', responseText );
					}

					// If we got responseJSON, great, use it.
					var responseJSON = jQuery( '<div/>' ).text( JSON.stringify( jqxhr.responseJSON ) ).html();
					if ( responseJSON != '' ) {
						output += wrap( 'responseJSON', responseJSON );	
					}

					return output;

				}

				/**
				 * Fade the output element in and give it display block as
				 * opposed to it's default display inline.
				 */
				function showOutput( output ) {
					jQuery( '#$prefix-output' ).html( output ).removeClass( '$prefix-hide' ).addClass( '$prefix-show' );	
				}

				/**
				 * Strip the trailing slash from a url, if it has one.
				 *
				 * @param  {string} url A url.
				 * @return {string} A url with no trailing slash.
				 */
				function removeTrailingSlash( url ) {
					return url.replace( /^\/|\/$/g, '' );
				}

				/**
				 * Determine is a url is external to the current window.
				 *
				 * @param  {string}  A url.
				 * @return {boolean} True if a request is external, else false.
				 */
				function isExternalRequest( apiUrl ) {

				    // Grab the current domain.
    			    var hostname = window.location.hostname;

    			    // If this is an external request...
    			    if ( apiUrl.indexOf( hostname ) < 1 ) { return true; }

    			    return false;

    			}

				/**
				 * Make a call to a WP API url and insert the results into the DOM.
				 *
				 * @param {string} The url for the WP API.
				 */
				function theApiResponse( apiUrl ) {
        
					// Grab the request data, nonce, and type.
					var data   = jQuery( '#$prefix-data' ).val();
    			    var nonce  = jQuery( '#$prefix-nonce' ).val();
					var type   = jQuery( '#$prefix-method' ).val();

					// Convert the request type to an uppercase verb.
    				var method = type.toUpperCase();

    			    // Get the route, sans trailing slash.
    			    var route = jQuery( '#$prefix-route' ).val();
    			    route = removeTrailingSlash( route );

	        		// Build the url to which we'll send our API request.
	        		var url = apiUrl + '$route_prefix' + route;

    			    // Let's assume for now that the dataType should be JSON.
    			    var dataType = 'json';
    			    
    			    // But, if it's an external request...
    			    if( isExternalRequest( apiUrl ) ) {
    			    	
    			    	// And if it's a get request...
    			    	if( method == 'GET' ) {

    			    		// The dataType actually should be jsonp.
        			        dataType = 'jsonp';

        			        // And we have to add this to the url.
		        		   	url += '?_jsonp=?';	

	        			}

	        		}

    			    // Send our ajax request.
					var apiCall = jQuery.ajax({
						url:	    url,
						type: 	    type,
						data: 	    data,
						dataType:   dataType,
						beforeSend: function( xhr ) {
        					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
       					}
					})
					.always( function() {

						console.log( always );

					})
					.fail( function() {

						console.log( fail );

					})
					.done( function() {
						
						console.log( done );
						console.log( apiCall );

						var output = getOutput( apiCall );
									
						showOutput( output );

						sayStatus( done );
					
					});

				}

				// When the form is submit...
				jQuery( '#$prefix-form' ).on( 'submit', function( event ) {
			
						// Don't actually submit the form or reload the page.
						event.preventDefault();

						sayStatus( always );

						// Get the domain, sans trailing slash.
    				    var domain = jQuery( '#$prefix-domain' ).val();
    				    domain = removeTrailingSlash( domain );

						// We have to call the domain to discover the url for the WP API on that particular install.  Once that call is done...
        			   	var preCall = callDomain( domain )
        			   	.always( function() {

							console.log( discover_always );
						
						})
						.done( function( preCall ) {
        			   	
        			   		console.log( discover_done );		

        			   		/**
        			   		 * Dig into the response and look for the <link> that contains the API url.
        			   		 *
        			   		 * @see http://v2.wp-api.org/guide/discovery/
        			   		 */
        			   		var apiLink = jQuery( preCall ).filter( 'link[rel="https://github.com/WP-API/WP-API"]' );
        			   		
        			   		// Grab the API url.
        			   		var apiUrl = jQuery( apiLink ).attr( 'href' );

        			   		// Okay, we have the API url.  Now we can call the API.
        			   		theApiResponse( apiUrl );
        			   		
        			   	})
						.fail( function( preCall ) {
        			   		console.log( discover_fail );

        			   		console.log( preCall );
        			   
        			   		var output = getOutput( preCall );
									
        			   		sayStatus( discover_fail );

							showOutput( output );

        			   	})
		
				});

			</script>
EOT;

		return $out;

	}

	/**
	 * When we print inline JS or CSS, we will also provide an HTML comment to
	 * help the user debug where that stuff is coming from.
	 * 
	 * @param  string $php_class_name  The current php class.
	 * @param  string $php_method_name The current function.
	 * @param  int    $line_number     The current line number.
	 * @return string A comment to explain the origin of our front-end code.
	 */
	private function get_added_by( $php_class_name, $php_method_name, $line_number ) {

		$out = esc_html__( 'Added by: %s, %s(), line %s.', 'json-rest-api-shortcode' );
		$out = sprintf( $out, $php_class_name, $php_method_name, $line_number );
		$out = "<!-- $out -->";

		return $out;

	}

	/**
	 * Return some basic styles for the JSON response.
	 *
	 * @return string CSS for styling the HTML we get from the JSON API.
	 */
	private function get_style() {
		
		$added_by = $this -> get_added_by( __CLASS__, __FUNCTION__, __LINE__ );

		$prefix = $this -> prefix;

		$out = "
			$added_by
			<style>
				
				/* Some basic white-spacing for our form and output. */
				#$prefix-form,
				#$prefix-output {
					line-height: 2em;
					margin: 2em;
					padding: 2em;
					background: rgba( 0, 0, 0, .0375 );
				}
				
				/* A border treatment for our form, output, and inputs. */ 
				#$prefix-form,
				#$prefix-output,
				.$prefix-input,
				.$prefix-disclaimer  {
					border: 1px solid rgba( 0, 0, 0, .5 );
					border-radius: .125em;
				}

				/* Let's just do tubes of content in the form. */
				.$prefix-input,
				.$prefix-button {
					display: block;
					width: 100%;
				}

				/* We're kind of using output like a div -- it needs to display block. */
				#$prefix-output { display: block; }

				/* Some whitespace control within output. */
				#$prefix-output >:first-child {
					margin-top: 0;
					padding-top: 0;
				}

				/* Use a code-y font for labels and output. */
				#$prefix-output,
				.$prefix-label {
					font-family: courier, monospace;
					font-weight: 400;
				}

				/* Some basic whitespacing for form inputs. */ 
				.$prefix-input {
					margin-bottom: 1em;
					padding: .25em .5em;
				}

				/* Achieve some visual contrast for the submit button. */
				.$prefix-button {
					border-radius: 3px;
					margin-top: 2.25em;
					padding: 1.25em;
					text-align: center;
				}

				// Make the method look like it's uppercase, since we'll be forcing it to uppercase anyways.
				#$prefix-method { text-transform: uppercase; }

				.$prefix-disclaimer {
					background: rgba( 255, 255, 0, .25 );
					padding: .25em .5em;
				}

				/* Some more whitespace control within output. */
				#$prefix-output >:last-child {
					margin-bottom: 0;
					padding-bottom: 0;
				}

				.$prefix-output-node:not( :last-child ) {
					border-bottom: 1px dotted rgba( 0, 0, 0, .25 );
					padding-bottom: 1.5em;
					margin-bottom: 2em;
				}

				h3.$prefix-output-subtitle {
					margin-top: 0;
					margin-bottom: .5em;
				}

				p.$prefix-output-content {
					margin-bottom: 0;
					margin-bottom: 0;
				}

				/* Any time our plugin needs to hide something... */ 
				.$prefix-hide {
					opacity: 0;
					margin: 0 !important;
					padding: 0 !important;
					width: 0;
					height: 0;
					overflow: hidden;
					line-height: 0;
					font-size: 0;
				}

				/* Any time our plugin needs to reveal something... */ 
				body .$prefix-show {
					opacity: 1;
					-moz-transition: opacity 1s ease-in-out;
   					-webkit-transition: opacity 1s ease-in-out;
   					transition: opacity 1s ease-in-out;
				}

			</style>
		";

		return $out;
	}

	/**
	 * Enqueue jQuery.
	 */
	private function scripts() {

		// We are gonna need jQuery to send our ajax request.
		wp_enqueue_script( 'jquery' );

	}

	/**
	 * Check to make sure the JSON API is available. If not, wp_die().
	 * 
	 * I know it seems heavy-handed to just die, but that's really the simplest
	 * and fastest way to communicate this requirement.  Again, this plugin is
	 * for devs, not "normal" folks.
	 */
	private function api_plugin_check() {

		// Do we have the WP Rest API plugin?
		$is_api_plugin_active = $this -> is_api_plugin_active();

		// If not...
		if( ! $is_api_plugin_active ) {

			// Here's an error message in case the user lacks the plugin.
			$no_api_error_message = esc_html__( 'You do not have the WordPress Rest API.  Try updating your WordPress version or installing/updating this plugin: %s', 'json-rest-api-shortcode' );
			
			// I'll be cool and provide a link to the plugin.
			$no_api_error_message = sprintf( $no_api_error_message, $this -> api_plugin_url );

			/**
			 * Time to die.
			 * 
			 * @see https://www.youtube.com/watch?v=bIg85wU4Ilg
			 */
			wp_die( sprintf( $no_api_error_message, $plugin_url ) );

		}

		/**
		 * Okay, great, we made it this far.  That means we have the WP Rest API
		 * plugin installed.  Now let's make sure we have the right version.
		 */
		$is_api_plugin_min_version = $this -> is_api_plugin_min_version();

		// If we don't have the min version...
		if( ! $is_api_plugin_min_version ) {

			// Build an error message to explain this awkward situation.
			$api_version_error_message = esc_html__( 'You do not have the minimum required version of the WordPress Rest API. The minimum version is %s. Try updating your WordPress version or installing/updating this plugin: https://wordpress.org/plugins/rest-api/', 'json-rest-api-shortcode' );

			// Be cool and tell the user what the min version is.
			$api_version_error_message = sprintf( $api_version_error_message, $this -> min_api_version );

			/**
			 * Time to die, again.
			 * 
			 * @see http://www.mtv.com/crop-images/2013/09/04/gwar.jpg
			 */ 
			wp_die( $api_version_error_message );

		}

	}

	/**
	 * Do we have the WP Rest API plugin?  Let's assume that checking for this
	 * version number constant is a good way to determine that.
	 * 
	 * @return boolean If no WP Rest API plugin, FALSE. Else, TRUE.
	 */
	private function is_api_plugin_active() {
		
		if( ! defined( 'REST_API_VERSION' ) ) { return FALSE; }

		return TRUE;

	}

	/**
	 * Do we have the min version of the WP Rest API plugin?  Let's assume that 
	 * checking for this in the version number constant is a good way to
	 * determine that.
	 * 
	 * @return boolean If not the min WP Rest API plugin version, FALSE. Else, TRUE.
	 */
	private function is_api_plugin_min_version() {

		/**
		 * Break the version number apart at the hyphen so that it's easier to
		 * compare to the minimum version.
		 */ 
		$current_version_arr = explode( '-', REST_API_VERSION );
	
		// Grab the portion of the version number, before the hyphen.
		$current_version = $current_version_arr[0];

		// Let's see if we have the minimum required version of the API:
		$comp = version_compare( $current_version, $this -> min_api_version, '>=' );

		/**
		 * If we have the min version, $comp will be the integer, 0.
		 * If we have exceeded te min version, $comp will be the integer, 1.
		 * If we are less than the min version, $comp will be -1.
		 */ 
		if( $comp < 0 ) { return FALSE; }

		return TRUE;

	}

}