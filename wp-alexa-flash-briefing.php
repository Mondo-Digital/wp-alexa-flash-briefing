<?php

/*

* Plugin Name: WP Alexa Flash Briefing
* Plugin URI: https://github.com/andrewfitz/wp-alexa-flash-briefing
* Description: Creates briefing post types and JSON feed endpoint for Alexa flash briefing skill
* Version: 1.7.1
* Tested up to: 5.5.1
* Requires at least: 4.7
* Author: Andrew Fitzgerald
* Author URI: https://github.com/andrewfitz
* Donate link: https://www.paypal.me/andrewfitz
* Contributors: andrewfitz
* License: GPL-2.0+
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain: alexa-fb

*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ALEXA_FB_VERSION', '1.7.0' );

register_activation_hook( __FILE__, 'active_hook' );

function active_hook() {
  
    flush_rewrite_rules();
}

//get the briefing posts and format for JSON and Amazon feed for API 
function init_api1( $data ) {
	//GET variables
	$prm = $data->get_params();
	$b_cat = $prm['category'] ?? '';
	$numc = $prm['limit'] ?? 5;
	$cr = $prm['cache'] ?? 1;
	if ( $cr != '0') $cacher = (empty($cr) ? 1 : $cr);
	else if ($cr == '0') $cacher = 0;
	$tr = 'afb_cached_' . (empty($b_cat) ? 'all' : $b_cat) . '_' . (empty($numc) ? 1 : $numc);

	// Check for transient. If none, then execute WP_Query
	if ( $cacher == 0 || false === ( $gt = get_transient( $tr ) ) ) {

		$argss = array(
			'no_found_rows' => true,
			'post_status' => 'publish',
			'numberposts' => (empty($numc) ? 1 : $numc)
//			'post_type'   => 'briefing'
		);

		if ( ! empty( $b_cat ) ) {
			$argss['tax_query'] = array(
				array(
					'taxonomy' => 'category',
					'field' => 'slug',
					'terms' => $b_cat,
				),
			);
		}

		$posts = get_posts( $argss );

		if ( empty( $posts ) ) {
			return null;
		}

		$gg = [];

		foreach($posts as $post){
			$response = array(
				'uid' => 'urn:uuid:' . wp_generate_uuid4( get_permalink( $post ) ),
				'updateDate' => get_post_modified_time( 'Y-m-d\TH:i:s.\0\Z', true, $post ),
				'titleText' => $post->post_title,
				'mainText' => wp_strip_all_tags( empty($post->post_excerpt) ? $post->post_title : $post->post_excerpt ),
				'redirectionUrl' => get_permalink( $post ),
			);

			$cntnt = $post->post_content;

			//preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $cntnt , $match);

			//$response['mainText'] = wp_strip_all_tags( strip_shortcodes($post->post_content));
//			if(empty($match[0])){
//				$response['mainText'] = wp_strip_all_tags( strip_shortcodes($post->post_content));
//			} else {
//				$response['streamUrl'] = esc_url_raw($match[0][0]);
//			}

			array_push($gg, $response);
		};

		// if only one briefing, do not put out a multi item array
		if ( count( $gg ) === 1 ) {
			$gg = $gg[0];
		}
		// Put the results in a transient. Expire after 1 hour.
		if($cacher == 0){
			if($gt !== false){
				delete_transient($tr);
			}
		}
		else {
			set_transient(  $tr, $gg, $cacher * HOUR_IN_SECONDS );
		}
	} else {
		$gg = $gt;
	}
	return $gg;
}

//register api
add_action( 'rest_api_init', function () {
	register_rest_route( 'alexa-fb/v1', '/briefings/', array(
	'methods' => 'GET',
	'callback' => 'init_api1',
	'permission_callback' => '__return_true'
	));
	
} );


?>
