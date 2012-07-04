<?php
/*
Plugin Name: Previous and Next Post in Same Taxonomy
Plugin URI: http://core.trac.wordpress.org/ticket/17807
Description: Extends the prev/next links to let you limit to same taxonomy. Used for testing WP Core patch, and can be disabled if patch is committed to core.
Author: Bill Erickson
Version: 1.0
Author URI: http://www.billerickson.net
*/


/**
 * Retrieve previous post that is adjacent to current post.
 *
 * @since 1.5.0
 *
 * @param bool $in_same_cat Optional. Whether post should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return mixed Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function be_get_previous_post($in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	return be_get_adjacent_post($in_same_cat, $excluded_categories, true, $taxonomy);
}

/**
 * Retrieve next post that is adjacent to current post.
 *
 * @since 1.5.0
 *
 * @param bool $in_same_cat Optional. Whether post should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return mixed Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function be_get_next_post($in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	return be_get_adjacent_post($in_same_cat, $excluded_categories, false, $taxonomy);
}

/**
 * Retrieve adjacent post.
 *
 * Can either be next or previous post.
 *
 * @since 2.5.0
 *
 * @param bool $in_same_cat Optional. Whether post should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param bool $previous Optional. Whether to retrieve previous post.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return mixed Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function be_get_adjacent_post( $in_same_cat = false, $excluded_categories = '', $previous = true, $taxonomy = 'category' ) {
	global $post, $wpdb;

	if ( empty( $post ) )
		return null;

	$current_post_date = $post->post_date;

	$join = '';
	$posts_in_ex_cats_sql = '';
	if ( $in_same_cat || ! empty( $excluded_categories ) ) {
		$join = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";

		if ( $in_same_cat ) {
			$cat_array = wp_get_object_terms($post->ID, $taxonomy, array('fields' => 'ids'));
			$join .= " AND tt.taxonomy = '$taxonomy' AND tt.term_id IN (" . implode(',', $cat_array) . ")";
		}

		$posts_in_ex_cats_sql = "AND tt.taxonomy = '$taxonomy'";
		if ( ! empty( $excluded_categories ) ) {
			if ( ! is_array( $excluded_categories ) ) {
				// back-compat, $excluded_categories used to be IDs separated by " and "
				if ( strpos( $excluded_categories, ' and ' ) !== false ) {
					_deprecated_argument( __FUNCTION__, '3.3', sprintf( __( 'Use commas instead of %s to separate excluded categories.' ), "'and'" ) );
					$excluded_categories = explode( ' and ', $excluded_categories );
				} else {
					$excluded_categories = explode( ',', $excluded_categories );
				}
			}

			$excluded_categories = array_map( 'intval', $excluded_categories );
				
			if ( ! empty( $cat_array ) ) {
				$excluded_categories = array_diff($excluded_categories, $cat_array);
				$posts_in_ex_cats_sql = '';
			}

			if ( !empty($excluded_categories) ) {
				$posts_in_ex_cats_sql = " AND tt.taxonomy = '$taxonomy' AND tt.term_id NOT IN (" . implode($excluded_categories, ',') . ')';
			}
		}
	}

	$adjacent = $previous ? 'previous' : 'next';
	$op = $previous ? '<' : '>';
	$order = $previous ? 'DESC' : 'ASC';

	$join  = apply_filters( "get_{$adjacent}_post_join", $join, $in_same_cat, $excluded_categories );
	$where = apply_filters( "get_{$adjacent}_post_where", $wpdb->prepare("WHERE p.post_date $op %s AND p.post_type = %s AND p.post_status = 'publish' $posts_in_ex_cats_sql", $current_post_date, $post->post_type), $in_same_cat, $excluded_categories );
	$sort  = apply_filters( "get_{$adjacent}_post_sort", "ORDER BY p.post_date $order LIMIT 1" );

	$query = "SELECT p.* FROM $wpdb->posts AS p $join $where $sort";
	$query_key = 'adjacent_post_' . md5($query);
	$result = wp_cache_get($query_key, 'counts');
	if ( false !== $result )
		return $result;

	$result = $wpdb->get_row("SELECT p.* FROM $wpdb->posts AS p $join $where $sort");
	if ( null === $result )
		$result = '';

	wp_cache_set($query_key, $result, 'counts');
	return $result;
}

/**
 * Get adjacent post relational link.
 *
 * Can either be next or previous post relational link.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param bool $previous Optional, default is true. Whether display link to previous post.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return string
 */
function be_get_adjacent_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $previous = true, $taxonomy = 'category') {
	if ( $previous && is_attachment() && is_object( $GLOBALS['post'] ) )
		$post = & get_post($GLOBALS['post']->post_parent);
	else
		$post = be_get_adjacent_post( $in_same_cat, $excluded_categories, $previous, $taxonomy );

	if ( empty($post) )
		return;

	if ( empty($post->post_title) )
		$post->post_title = $previous ? __('Previous Post') : __('Next Post');

	$date = mysql2date(get_option('date_format'), $post->post_date);

	$title = str_replace('%title', $post->post_title, $title);
	$title = str_replace('%date', $date, $title);
	$title = apply_filters('the_title', $title, $post->ID);

	$link = $previous ? "<link rel='prev' title='" : "<link rel='next' title='";
	$link .= esc_attr( $title );
	$link .= "' href='" . get_permalink($post) . "' />\n";

	$adjacent = $previous ? 'previous' : 'next';
	return apply_filters( "{$adjacent}_post_rel_link", $link );
}

/**
 * Display relational links for the posts adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_adjacent_posts_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	echo be_get_adjacent_post_rel_link($title, $in_same_cat, $excluded_categories = '', true, $taxonomy);
	echo be_get_adjacent_post_rel_link($title, $in_same_cat, $excluded_categories = '', false, $taxonomy);
}

/**
 * Display relational link for the next post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_next_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	echo be_get_adjacent_post_rel_link($title, $in_same_cat, $excluded_categories = '', false, $taxonomy);
}

/**
 * Display relational link for the previous post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_prev_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	echo be_get_adjacent_post_rel_link($title, $in_same_cat, $excluded_categories = '', true, $taxonomy);
}

/**
 * Retrieve boundary post.
 *
 * Boundary being either the first or last post by publish date within the constraints specified
 * by in same category or excluded categories.
 *
 * @since 2.8.0
 *
 * @param bool $in_same_cat Optional. Whether returned post should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param bool $start Optional. Whether to retrieve first or last post.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return object
 */
function be_get_boundary_post( $in_same_cat = false, $excluded_categories = '', $start = true, $taxonomy = 'category' ) {
	global $post;

	if ( empty($post) || ! is_single() || is_attachment() )
		return null;

	$cat_array = array();
	if( ! is_array( $excluded_categories ) )
		$excluded_categories = explode( ',', $excluded_categories );
		
	if ( $in_same_cat || ! empty( $excluded_categories ) ) {
		if ( $in_same_cat )
			$cat_array = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

		if ( ! empty( $excluded_categories ) ) {
			$excluded_categories = array_map( 'intval', $excluded_categories );
			$excluded_categories = array_diff( $excluded_categories, $cat_array );

			$inverse_cats = array();
			foreach ( $excluded_categories as $excluded_category )
				$inverse_cats[] = $excluded_category * -1;
			$excluded_categories = $inverse_cats;
		}
	}

	$categories = implode( ',', array_merge( $cat_array, $excluded_categories ) );

	$order = $start ? 'ASC' : 'DESC';
	
	$args = array(
		'posts_per_page' => '1',
		'order' => $order,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'tax_query' => array(
			array(
				'taxonomy' => $taxonomy,
				'field' => 'id',
				'terms' => $categories
			)
		)
		
	);

	return get_posts( $args );
}

/**
 * Get boundary post relational link.
 *
 * Can either be start or end post relational link.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param bool $start Optional, default is true. Whether display link to first or last post.
 * @param string $taxonomy Optional. Which taxonomy to use.
 * @return string
 */
function be_get_boundary_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $start = true, $taxonomy = 'category') {
	$posts = be_get_boundary_post($in_same_cat, $excluded_categories, $start, $taxonomy);
	// If there is no post stop.
	if ( empty($posts) )
		return;

	// Even though we limited get_posts to return only 1 item it still returns an array of objects.
	$post = $posts[0];

	if ( empty($post->post_title) )
		$post->post_title = $start ? __('First Post') : __('Last Post');

	$date = mysql2date(get_option('date_format'), $post->post_date);

	$title = str_replace('%title', $post->post_title, $title);
	$title = str_replace('%date', $date, $title);
	$title = apply_filters('the_title', $title, $post->ID);

	$link = $start ? "<link rel='start' title='" : "<link rel='end' title='";
	$link .= esc_attr($title);
	$link .= "' href='" . get_permalink($post) . "' />\n";

	$boundary = $start ? 'start' : 'end';
	return apply_filters( "{$boundary}_post_rel_link", $link );
}

/**
 * Display relational link for the first post.
 *
 * @since 2.8.0
 *
 * @param string $title Optional. Link title format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_start_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	echo be_get_boundary_post_rel_link($title, $in_same_cat, $excluded_categories, true, $taxonomy);
}

/**
 * Display previous post link that is adjacent to the current post.
 *
 * @since 1.5.0
 *
 * @param string $format Optional. Link anchor format.
 * @param string $link Optional. Link permalink format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_previous_post_link($format='&laquo; %link', $link='%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	be_adjacent_post_link($format, $link, $in_same_cat, $excluded_categories, true, $taxonomy);
}

/**
 * Display next post link that is adjacent to the current post.
 *
 * @since 1.5.0
 *
 * @param string $format Optional. Link anchor format.
 * @param string $link Optional. Link permalink format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_next_post_link($format='%link &raquo;', $link='%title', $in_same_cat = false, $excluded_categories = '', $taxonomy = 'category') {
	be_adjacent_post_link($format, $link, $in_same_cat, $excluded_categories, false, $taxonomy);
}

/**
 * Display adjacent post link.
 *
 * Can be either next post link or previous.
 *
 * @since 2.5.0
 *
 * @param string $format Link anchor format.
 * @param string $link Link permalink format.
 * @param bool $in_same_cat Optional. Whether link should be in same category.
 * @param array|string $excluded_categories Optional. Array or comma-separated list of excluded category IDs.
 * @param bool $previous Optional, default is true. Whether display link to previous post.
 * @param string $taxonomy Optional. Which taxonomy to use.
 */
function be_adjacent_post_link($format, $link, $in_same_cat = false, $excluded_categories = '', $previous = true, $taxonomy = 'category') {
	if ( $previous && is_attachment() )
		$post = & get_post($GLOBALS['post']->post_parent);
	else
		$post = be_get_adjacent_post($in_same_cat, $excluded_categories, $previous, $taxonomy);

	if ( !$post )
		return;

	$title = $post->post_title;

	if ( empty($post->post_title) )
		$title = $previous ? __('Previous Post') : __('Next Post');

	$title = apply_filters('the_title', $title, $post->ID);
	$date = mysql2date(get_option('date_format'), $post->post_date);
	$rel = $previous ? 'prev' : 'next';

	$string = '<a href="'.get_permalink($post).'" rel="'.$rel.'">';
	$link = str_replace('%title', $title, $link);
	$link = str_replace('%date', $date, $link);
	$link = $string . $link . '</a>';

	$format = str_replace('%link', $link, $format);

	$adjacent = $previous ? 'previous' : 'next';
	echo apply_filters( "{$adjacent}_post_link", $format, $link );
}
?>