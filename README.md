SJF JSON Rest API Shortcode
=========

The purpose of this plugin is to give developers a simple block of code for "hello-world-ing" the new WordPress JSON Rest API:  http://wp-api.org/

As of WordPress 4.0, the JSON API is not part of core, so this plugin dies if the blog does not have the JSON API plugin from Ryan McCue: https://wordpress.org/plugins/json-rest-api/

Example shortcode uses:

 * Create a sample draft post: [sjf_tjra_form]
 * Create a new user: [sjf_tjra_form route=users data="{'email':'dude@dude.com','username':'newuser','name':'New User','password':'secret'}"]
 * Browse posts: [sjf_tjra_form route=posts method=get]