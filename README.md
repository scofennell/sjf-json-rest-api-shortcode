SJF WP API Shortcode
=========

The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API:  http://wp-api.org/

As of WordPress 4.0, the JSON API is not part of core, so this plugin dies if the blog does not have the JSON API plugin from Ryan McCue: http://v2.wp-api.org/

Example shortcode uses:

 * Default form for pinging the api root: [wp_api]
 * Browse posts: [wp_api route=posts]
 * Browse users: [wp_api route=users]