=== Plugin Name ===
Contributors: billerickson
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4L3W6EBBZCLHY
Tags: patch, taxonomy, next, previous, post
Requires at least: 3.2
Tested up to: 3.2.1
Stable tag: trunk

Allows you to get the previous and next post link for posts in the same taxonomy. Simulates the patch to Trac ticket #17807.

== Description ==

If you use next_post_link('%link', '%title', true) or previous_post_link('%link', '%title', true) to get the adjacent post for a custom post type which has a taxonomy assigned to it, it doesn't work as intended.

Those functions are all hardcoded to look to the taxonomy 'category'. This patch let's you change that if you'd like. 

If you have a custom post type 'product' with a taxonomy 'color' and want the prev/next post links on the single product page to go to similarly colored ones, use this:

be_previous_post_link('%link', '%title', true, '', 'color');
be_next_post_link('%link', '%title', true, '', 'color');

Please post any issues you find on the Trac ticket so the core patch can be updated: http://core.trac.wordpress.org/ticket/17807

Functions available:

* be_previous_post_link()
* be_next_post_link()
* be_adjacent_post_link()
* be_get_previous_post()
* be_get_next_post()
* be_get_adjacent_post()
* be_get_adjacent_post_rel_link()
* be_adjacent_posts_rel_link()
* be_next_post_rel_link()
* be_prev_post_rel_link()
* be_get_boundary_post()
* be_get_boundary_post_rel_link()
* be_start_post_rel_link()

Please see the source of the plugin for documentation. They should work just like the original functions, except they now accept an additional parameter, $taxonomy, that's set to 'category' by default.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload 'previous-and-next-post-in-same-taxonomy/' to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place one of the functions in your template. Ex: '<?php be_previous_post_link('%link', '%title', true, '', 'color'); ?>'

== Changelog ==

= 1.0 =
* Initial Release
