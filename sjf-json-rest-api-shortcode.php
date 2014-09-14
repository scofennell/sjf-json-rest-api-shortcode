<?php

/**
 * Plugin Name: SJF Test JSON REST API
 * Plugin URI: http://scottfennell.org/2014/09/08/wordpress-json-rest-api-shortcode-tutorial/
 * Description: The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API:  http://wp-api.org/.
 * Version: 1.0.2
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
	var $version = '1.0.2';

	/**
	 * Adds actions for our class methods.
	 */
	function __construct() {
                
		// Ajax handler for non logged in users.
		add_action( 'wp_ajax_nopriv_sjf_tjra_ajax', array( $this, 'sjf_tjra_ajax' ) );

		// Ajax handler for logged in users.
		add_action( 'wp_ajax_sjf_tjra_ajax', array( $this, 'sjf_tjra_ajax' ) );

		// Register the shortcode.
		add_shortcode( 'json', array( $this, 'json' ) );

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
	function json( $atts ) {
		
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

	    // Make sure the json api is available.  If not, page will wp_die().
	    $this -> version_check();

	    // Enqueue jQuery and the plugin JS file.
		$this -> scripts();

		// Get current page protocol
		$protocol = isset( $_SERVER[ 'HTTPS'] ) ? 'https://' : 'http://';

		// Output admin-ajax.php URL with same protocol as current page
		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php', $protocol ),
		);

		// Grab JS values from the script we just registered.
		wp_localize_script( 'sjf_tjra', 'sjf_tjra', $params );

		// Sanitize the shortcode args before sending them to our ajax script.
		$domain = trailingslashit( esc_url( $a[ 'domain' ] ) );
		$route  = urlencode( $a[ 'route' ] );
		$method = strtoupper( esc_attr( $a[ 'method' ] ) );
		$data   = sanitize_text_field( $a[ 'data' ] );

		/**
		 * Build a url to which we intend to send our data.
		 * This gets used as the form action and therefore does not get used unless JS is disabled.
		 */
		$url = $this -> build_url( $domain, $route );

		/**
		 * Add the shortcode args to the DOM so we can grab them with jQuery.  Also nice for debugging/clarity.
		 * @todo It would probably be more useful, and be more readable, if these were text inputs in a form.
		 */
		$values = "
			<h3>" . __( "Shortcode args:" ) . "</h3>
			<ul id='values'>
				<li><strong>Domain:</strong> <span id='domain'>$domain</span></li>
				<li><strong>Method:</strong> <span id='method'>$method</li>
				<li><strong>Route:</strong> <span id='route'>$route</span></li>
				<li><strong>Data:</strong> <span id='data'>$data</span></li>
			</ul>
		"; 
		
		// If you're making anything other than a GET request, you need to supply data.
		if( $method != 'GET' ) {
			if( empty( $data ) || ( $data == '{}' ) ) { wp_die( __( "You must supply data when making a $method request. Example: { title: 'Hello Worldly Title Here', content_raw: 'This is the content of the new post we are creating.' } " ) ); }
		}

		// When we get a response from the JSON API, we'll give it some basic styles.
		$style = $this -> style();
		
		// This div will hold the response we get back after we ping the API.
		$output = "<output id='output'></output>";

		// To be clear, if JS is enabled, the form really doesn't do anything other than get clicked.  It's just a UI to trigger the Ajax call.
		$form = "
			<form action='$url' method='$method' id='sjf_tjra_form'>
				<input type='submit' value='Submit' name='submit'>
			</form>
		";

		$out = "
			$values
			$form
			$output
			$script
			$style
		";

		$out = apply_filters( 'sjf_tjra_json', $out );

		return $out;

	}

	/**
	 * The main ajax function.  This code gets executed on click.
	 *
	 * Even though this method is in a class, we prefix it since it gets used in our javascript.
	 */
	function sjf_tjra_ajax() {
	
		// Build a url to which we'll send our data.
		$domain  = trailingslashit( esc_url( $_REQUEST[ 'domain' ] ) );
		$route   = urlencode( $_REQUEST[ 'route' ] );
		$url 	 = $this -> build_url( $domain, $route );

		// Sanitize the shortcode args before sending them to our ajax script.
		$method = strtoupper( esc_attr( $_REQUEST[ 'method' ] ) );
		$data = sanitize_text_field( $_REQUEST[ 'data' ] );
		
		// Options to pass to wp_remote_request.
		$args = array();
		$args = apply_filters( 'sjf_tjra_ajax_args', $args );

		// Send our request.
		$request = wp_remote_request( $url, $args );

		$out = $this -> report( $request );

		$out = apply_filters( 'sjf_tjra_ajax', $out );

		echo $out;

		// This is necessary to avoid outputting a "0" after any ajax call in WordPress.
		die();

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
					display: block; /* By default output displays as an inline element */
				    border: 1px solid #000;
				    border-radius: 1em;
				    font-family: courier, monospace;
				    font-size: 12px;
				    line-height: 2em;
				    margin: 2em;
				    padding: 2em;
				}
			</style>
		";

		$out = apply_filters( 'sjf_tjra_style', $out );

		return $out;
	}

	/**
	 * Return HTML to report on an HTTP response.
	 * 
	 * @param  string $request The HTTP response.
	 * @return string HTML to report on an HTTP response.
	 */
	function report( $request ) {

		$out = '';

		// We're assuming that we got an array.
		if( ! is_array( $request ) ) { return __( "There is a problem with your request: It must return an array." ); }

		// We'll report on the response, headers, body, filename, and cookies.
		$out .= $this -> report_array(  $request[ 'response' ], __( 'Response:' ) );
		$out .= $this -> report_array(  $request[ 'headers' ],  __( 'Headers:' ) );
		$out .= $this -> report_string( $request[ 'body' ],     __( 'Body:' ) );
		$out .= $this -> report_string( $request[ 'filename' ], __( 'Filename:' ) );
		$out .= $this -> report_array(  $request[ 'cookies' ],  __( 'Cookies:' ) );
		
		$out = apply_filters( 'sjf_tjra_report', $out );

		return $out;

	}

	/**
	 * Return HTML to report on an array portion of an HTTP response.
	 * 
	 * @param  array  $array A member of an HTTP response array.
	 * @param  string $label Human-readable label used as a heading for the output.
	 * @return string HTML to report on an array portion of an HTTP response.
	 */
	function report_array( $array, $label ) {

		$out = "";

		// Sanitize each member of the array we've been given.
		$array = array_map( 'esc_html', $array );
	
		// For each array member, read it into the output as a key value pair.		
		foreach( $array as $k => $v ) {
			$out .= "<li><strong>$k</strong> => $v</li>";
		}

		if( empty( $out ) ) { return false; }
		
		$out = "<h3>$label</h3><ul>$out</ul>";
		
		return $out;
	}

	/**
	 * Return HTML to report on a string portion of an HTTP response.
	 * 
	 * @param  string $string A member of an HTTP response array.
	 * @param  string $label Human-readable label used as a heading for the output.
	 * @return string HTML to report on a string portion of an HTTP response.
	 */
	function report_string( $string, $label ) {

		$string = esc_html( $string );
		
		if( empty( $string ) ) { return false; }

		$out = "<h3>$label</h3><div>$string</div>";
		
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
		$out     = $domain . $route;

		$out = apply_filters( 'sjf_tjra_build_url', $out );

		return $out;

	}

	/**
	 * Enqueue jQuery and the plugin JS file.
	 */
	function scripts() {

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
	        
			// Add a version number so browsers are forced to grab new version.
	        $this -> version,

	        // Load it in the footer.
	        true
	    );

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