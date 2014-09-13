/**
 * When the user submits the form, Send the API request.
 * 
 * @package WordPress
 * @subpackage SJF_Test_JSON_REST_API
 * @since SJF_Test_JSON_REST_API 1.0
 */

/**
 * When the user submits the form, Send the API request.
 */
( function( $ ) {

    // Set us up for a sick show/hide when we get a response from the API.
	$( '#output' ).hide();

	// When the form is submit...
	$( '#sjf_tjra_form' ).on( 'submit', function( event ) {
		
		// Don't actually submit the form or reload the page.
		event.preventDefault();
    
    	// Replace it with loading text.
		$( '#sjf_tjra_form [type="submit"]' ).replaceWith( 'loading...' );
        
        // Grab the shortcode args from the DOM.
        domain = $( '#values #domain' ).text();
        method = $( '#values #method' ).text();
        route  = $( '#values #route' ).text();
        data   = $( '#values #data' ).text();
        
		// Send the data to the server.
        var ajaxData = {

            // This value corresponds with a call to "localize script" in the php code.
            action: 'sjf_tjra_ajax',
            
            // Our shortcode args.
        	domain: domain,
			route: route,
			method: method,
			data: data,

        };

		// Make the ajax call.
        $.get( sjf_tjra.ajaxurl, ajaxData, function( ajaxData ) {
            
            // Print the result of the Ajax call in the output div.
            $( '#output' ).html( ajaxData ).fadeIn();

			// Fade out the form.
            $( '#sjf_tjra_form' ).fadeOut();

        });
        
        return false;
    });
})( jQuery );