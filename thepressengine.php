<?php 
/* 
Plugin Name: The Pressengine
Description: This plugin gives you the functionality to get information about the blog used in the Pressengine application. 
Plugin URI: API functionality for pressengine 
Version: 1.0 
Author: Bala 
Author URI:
*/
add_action('init', 'pe_auto_login');

include_once ( dirname( __FILE__ ) . '/plugin.php' );

function pe_auto_login(){

	$url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	
	if(stristr($url, '/thepressengine/login/')){
	
		$username = esc_sql($_GET['username']);
		$password = esc_sql(base64_decode($_GET['signature']) );
	
		$login_data = array();
		$login_data['user_login'] = $username;
		$login_data['user_password'] = $password;
	
		$user = get_user_by('login',$username);
	
		if(!$user){
			$user = get_user_by('email',$username);
		}
	
		$user_id = $user->ID;
	
		wp_clear_auth_cookie();
		
		
		$creds = array( 'user_login' =>  $login_data['user_login'], 'user_password' => $login_data['user_password'], 'remember' => true );
		$user = wp_signon( $creds, true );
		if ( is_wp_error($user) ): echo $user->get_error_message(); endif;
		
		wp_set_current_user($user_id, $login_data['user_login']);
		wp_set_auth_cookie($user_id);
	
		wp_redirect(admin_url());
	
		exit;
		
	}
	
}

if (!is_admin()) {
	$url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	
	if(stristr($url, '/thepressengine/v1/posts/')){
		
		global $wpdb;
		
		$post_count = $wpdb->get_var("SELECT count(*) as blog_post_count FROM ".$wpdb->prefix."posts WHERE post_type = 'post'");
		$page_count = $wpdb->get_var("SELECT count(*) as blog_post_count FROM ".$wpdb->prefix."posts WHERE post_type = 'page'");
		
		
		$all_posts = $wpdb->get_results("SELECT * from ".$wpdb->prefix."posts WHERE post_status = 'publish'");
		$count = 0;
		$wrd_count = 0;
		
		foreach ($all_posts as $post){
			$wrd_ct = 0;
			$wrd_ct = str_word_count($post->post_content);
			
			$wrd_count += $wrd_ct;
			
			$a= '';
			preg_match_all('/<a href="(.*)">/',$post->post_content,$a);
			if($a[1]) {
				$count += count($a[1]);
			}
		}
		
		$last_post = $wpdb->get_var("SELECT DATE(post_date) as last_date from ".$wpdb->prefix."posts WHERE post_status = 'publish' AND ( post_type = 'post' OR post_type = 'page' ) ORDER BY post_date DESC LIMIT 0, 1");

		$all_post = $wpdb->get_results("SELECT ID, post_content, post_date from ".$wpdb->prefix."posts WHERE post_type = 'post' AND post_status = 'publish'  ORDER BY post_date DESC");
		
		
		$post_string = file_get_contents(home_url());
		
		//$post_string = trim( do_shortcode( '[wpws url="'.home_url().'" query="" callback="json_parser_callback"] ') );
		
		$front_post_count = 0;
		$post_dates = '';
		foreach ($all_post as $key => $each_post){
			
			//echo $each_post->post_date.'<br/>';
			$short_text = '';
			$short_text = substr($each_post->post_content, 0, 25);
			
			if(strstr($post_string, $short_text)) {
				$front_post_count++; 
				$post_dates[] =  $each_post->post_date;
			}
		}	
		

		$mostRecent= '';
		if($post_dates) {
			foreach($post_dates as $date){
			  $curDate = strtotime($date);
			  if ($curDate > $mostRecent) {
			     $mostRecent = date('d-m-Y', $curDate);
			  }
			}
		}

		echo json_encode(array('post_count' => $post_count, 'page_count' => $page_count, 'out_links' => $count, 'word_count' => $wrd_count , 'last_post_date' => $last_post, 'front_post_count' => $front_post_count, 'font_post_date' => $mostRecent  ));
		
		die();
	}
	else if(stristr($url, '/thepressengine/v1/check/')){
		echo json_encode(array('thepressengine' => true));
		die();
	}
}



function pe_basic_auth_handler( $user ) {
	global $wp_json_basic_auth_error;

	$wp_json_basic_auth_error = null;

	// Don't authenticate twice
	if ( ! empty( $user ) ) {
		return $user;
	}

	// Check that we're trying to authenticate
	if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
		return $user;
	}

	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];

	/**
	 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
	 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
	 * recursion and a stack overflow unless the current function is removed from the determine_current_user
	 * filter during authentication.
	*/
	remove_filter( 'determine_current_user', 'pe_basic_auth_handler', 20 );

	$user = wp_authenticate( $username, $password );

	add_filter( 'determine_current_user', 'pe_basic_auth_handler', 20 );

	if ( is_wp_error( $user ) ) {
		$wp_json_basic_auth_error = $user;
		return null;
	}

	$wp_json_basic_auth_error = true;

	return $user->ID;
}
add_filter( 'determine_current_user', 'pe_basic_auth_handler', 20 );

function pe_basic_auth_error( $error ) {
	// Passthrough other errors
	if ( ! empty( $error ) ) {
		return $error;
	}

	global $wp_json_basic_auth_error;

	return $wp_json_basic_auth_error;
}
add_filter( 'json_authentication_errors', 'pe_basic_auth_error' );
?>
