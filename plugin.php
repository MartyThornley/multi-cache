<?php
/*
Plugin Name: Multi Cache
Plugin URI: http://blogsiteplugins.com
Description: Mutli Cache is a simple to use caching system built to work with multisite.
Version: 0.1
Text Domain: multi-cache
Author: Marty Thornley
Author URI: http://martythornley.com
Disclaimer: Use at your own risk. No warranty expressed or implied is provided.

Copyright 2011  Satollo  (email : info@satollo.net)

based on - Hyper Cache by Stefano Lissa
http://www.satollo.net/plugins/hyper-cache
http://www.satollo.net

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
	
	global $wpdb;
		
	define( 'MULTI_CACHE_DEBUG' , false );
	
	// define to help test local installs
	define( 'MULTICACHE_LOCAL' , true );
	define( 'MULTICACHE_LOCAL_DOMAIN' , 'localhost/' );

	// example of adding a domain to be checked
	add_filter( 'multi_cache_domains' , 'add_to_multi_cache_domains' , 1 , 2 );		

	function add_to_multi_cache_domains( $domains ) {
		//$domains[] = 'http://mydomain.com';
		return $domains;
	}
		

	define( 'MULTI_CACHE_PLUGIN_DIR' , 	dirname( __FILE__ ) );
	define( 'MULTI_CACHE_PLUGIN_URL' ,	plugins_url( basename( dirname( __FILE__ ) ) ) );	
	
	$hyper_invalidated = false;
	$hyper_invalidated_post_id = null;
	

	define( 'MULTI_CACHE_DIR' , 		WP_CONTENT_DIR . '/cache/mutli-cache' );
	define( 'MULTI_CACHE_BLOGINFO' , 	trailingslashit( MULTI_CACHE_DIR ) . 'bloginfo' );
	define( 'MULTI_CACHE_PAGE_CACHE' , 	trailingslashit( MULTI_CACHE_DIR ) . 'pages' );

	define( 'MULTI_CACHE_CONFIG' , 		WP_CONTENT_DIR . '/advanced-cache.php' );
	
	register_deactivation_hook( __FILE__ , 	'hyper_deactivate' );
	
	add_action( 'init' , 			'multi_cache_init' );
	
	/*
	 * Setup initial folders and options
	 */
	function multi_cache_init() {
		
		multicache_initial_options();

		add_action( 'wp_enqueue_scripts' , 	'multi_cache_enqueue' );
		
		add_action( 'hyper_clean' , 		'hyper_clean' );
		add_action( 'switch_theme' , 		'multi_cache_delete_site' , 0 );
				
		add_action( 'save_post' , 			'multi_cache_delete_post' );
		add_action( 'publish_post' , 		'multi_cache_delete_post' );
		add_action( 'delete_post' , 		'multi_cache_delete_post' );

		add_action( 'wp_before_admin_bar_render' , 	'multi_cache_custom_admin_bar' , 99 );

		if ( is_admin() ) {
	
			include( trailingslashit( MULTI_CACHE_PLUGIN_DIR ) . 'admin.php' );
	
			if ( is_multisite() )
				add_action( 'network_admin_menu' , 		'hyper_admin_menu' );
			else
				add_action( 'admin_menu' , 		'hyper_admin_menu' );
		
		} else {
		
			multi_cache_track_blog();
		
		}

	}
	
	function multicache_initial_options() {

		if ( !get_option( 'multi_cache_started' ) || !file_exists( MULTI_CACHE_CONFIG ) ) {
		
			$multi_cache_options = multi_cache_options();
			
		    if ( !is_array( $multi_cache_options ) ) {
		        $multi_cache_options 						= array();
		        $multi_cache_options['comment'] 			= 1;
		        $multi_cache_options['archive'] 			= 1;
		        $multi_cache_options['timeout'] 			= 1440;
		        $multi_cache_options['redirects'] 			= 1;
		        $multi_cache_options['notfound'] 			= 1;
		        $multi_cache_options['clean_interval'] 		= 60;
		        $multi_cache_options['gzip'] 				= 1;
		        $multi_cache_options['store_compressed'] 	= 1;
		        $multi_cache_options['expire_type'] 		= 'post';
	
				multi_cache_update_options( $multi_cache_options );
		    }
		
		    $buffer = multi_cache_generate_config( $multi_cache_options );
		    $file = @fopen( MULTI_CACHE_CONFIG , 'wb');
		    @fwrite( $file, $buffer );
		    @fclose( $file );
			
			if ( ! file_exists( MULTI_CACHE_DIR ) )
			    wp_mkdir_p( MULTI_CACHE_DIR );

			if ( ! file_exists( MULTI_CACHE_BLOGINFO ) )
			    wp_mkdir_p( MULTI_CACHE_BLOGINFO  );	

			if ( ! file_exists( MULTI_CACHE_PAGE_CACHE ) )
			    wp_mkdir_p( MULTI_CACHE_PAGE_CACHE );
						
			update_option( 'multi_cache_started' , time() );
		
		}
	}

	function multi_cache_options() {
		if ( is_multisite() )
			$multi_cache_options = get_site_option( 'multi_cache_options' );
		else
			$multi_cache_options = get_option( 'multi_cache_options' );
		
		return $multi_cache_options;	
	}
	
	function multi_cache_update_options( $multi_cache_options ) {
		if ( is_multisite() )
			update_site_option( 'multi_cache_options' , $multi_cache_options );
		else
			update_option( 'multi_cache_options' , $multi_cache_options );
	}
	
	function hyper_admin_menu() {
		if ( is_multisite() ) 
			add_submenu_page( 'settings.php', 'Multi Cache', 'Multi Cache', 'manage_options', 'multi_cache_admin' , 'multi_cache_admin' );
		else
	    	add_options_page( 'Multi Cache', 'Multi Cache', 'manage_options', 'multi_cache_admin' , 'multi_cache_admin' );
	}

	function multi_cache_enqueue(){
		
		wp_enqueue_style( 'multi-cache-style' , trailingslashit( MULTI_CACHE_PLUGIN_URL ) . 'library/css/style.css' );
	
	}
	
	/*
 	 * Delete single site's cache
 	 */
	function multi_cache_delete( $args ) {
		
		global $wpdb;
		
		$defaults = array(
			'cache' => 'site', 	// site, network, post
			'post'	=> '',		// should be id of post
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$cache_files = array();
		
		switch ( $args['cache'] ) {
		
			case 'site' :
				
				$cache_files[] = trailingslashit( MULTI_CACHE_PAGE_CACHE  ) . sprintf( "%09s", $wpdb->blogid );
			
			break;
			
			case 'post' :
				
				if ( isset( $args['post'] ) ) {
				
					$cache_files = array_merge( $cache_files , multi_cache_get_post_files( $post_id ) );	
				
				}
				
			break;
		
		}

		if ( is_array( $cache_files ) ) {		
	
			foreach( $cache_files as $cache_file ) {

				if ( file_exists( $cache_file ) && is_dir( $cache_file ) && $cache_file != trailingslashit( MULTI_CACHE_PAGE_CACHE  ) ) {
					
					multi_cache_delete_path( $cache_file );
					
				} elseif ( $cache_file != '' && file_exists( $cache_file ) && !is_dir( $cache_file ) )  {
				
					@unlink( $cache_file );
					
				}
			}
		}
	}
	
	/*
 	 * Wrapper functio to delete single site's cache
 	 */
	function multi_cache_delete_site() {
		
		multi_cache_delete( array( 'cache' => 'site' ) ); 
	}
	
	/**
	 * Deletes a single post and eventually the home and archives if
	 * required.
	 */
	function multi_cache_delete_post( $post_id ) {
			
		global $wpdb;
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
    		return $post_id;
		
		multi_cache_delete( array( 'cache' => 'post' , 'post' => $post_id ) ); 
		
	}

	/**
	 * Finds all possible urls that clearing a post cache should delete
	 * 
	 * Should able to possibly find taxonomies, home page, posts pages, anything a changed post may effect
	 *
	 * returns an array of hashed urls.
	 */
	function multi_cache_get_post_files( $post_id ) {
			
		global $wpdb;

		$cache_files = array();
		
		// get the page/post itself
		$ids[] = $_POST['post_ID'];
		
		// get home or front page
		$ids[] = get_option('page_on_front');
		
		// should we find taxonomies, etc...		
		
		foreach( $ids as $id ) {
			
			if ( $id != '' ) {
			
			 	$link = get_permalink( $id );
			 	$link = preg_replace( '~^.*?://~', '', $link );
			
			 	$file = multi_cache_hash( $link ) .'.dat';
				
				$cache_file = trailingslashit( MULTI_CACHE_PAGE_CACHE  ) . sprintf( "%09s", $wpdb->blogid ) . '/'. $file;

			    if ( $cache_file != '' && file_exists( $cache_file ) && !is_dir( $cache_file ) ) {
					$cache_files[] = $cache_file;
				}
				
			}
		
		}
		
		return $cache_files;
		
	}
	
	/*
	 * Completely remove a directory and it's content.
	 */
	function multi_cache_delete_path( $dir ) {
		
		if ( !is_dir( $dir ) || empty( $dir ) )
			return;
		
		if ( strpos( $dir , MULTI_CACHE_DIR ) === false ) 
			return;

		if ( is_dir( $dir ) ) { 
			$objects = scandir( $dir ); 
			foreach ( $objects as $object ) { 
				if ( $object != "." && $object != ".." ) { 
					if ( filetype( $dir . '/' . $object ) == 'dir') 
						multi_cache_delete_path( $dir . '/' . $object ); 
					else 
						@unlink( $dir . '/' . $object ); 
				} 
			} 
	    	reset( $objects ); 
	    	@rmdir( $dir ); 
		} 
	}

	/*
	 * Use this to hash everything in case we want to change hash method
	 */
	function multi_cache_hash( $string ){
		
		return md5( $string );
	
	}
	
	// Counts the number of file in to the hyper cache directory to give an idea of
	// the number of pages cached.
	function hyper_count() {
	    $count = 0;
	 	
		if ($handle = @opendir( MULTI_CACHE_DIR ) ) {
	        while ( $file = readdir( $handle ) ) {
	            if ( $file != '.' && $file != '..' ) {
	                $count++;
	            }
	        }
	        closedir( $handle );
	    }
	    return $count;
	}
	
	function hyper_clean() {
	    // Latest global invalidation (may be false)
	    $invalidation_time = @filemtime( trailingslashit( MULTI_CACHE_DIR ) .'global.dat');
	
	    hyper_log( 'start cleaning' );
	
	    //$options = get_option('hyper');
		$options = multi_cache_options();
		
	    $timeout = $options['timeout']*60;
	
	    if ( $timeout == 0 )
			return;
	
	    $path = MULTI_CACHE_DIR;
	    $time = time();
	
	    $handle = @opendir( $path );
	
	    if ( !$handle ) {
	        hyper_log( 'unable to open cache dir' );
	        return;
	    }
	
	    while ( $file = readdir( $handle ) ) {
	        
			if ( $file == '.' || $file == '..' || $file[0] == '_' ) 
				continue;
	
	        hyper_log( 'checking ' . $file . ' for cleaning' );
	        $t = @filemtime($path . '/' . $file);
	        hyper_log( 'file time ' . $t );
	
	        if ( $time - $t > $timeout || ( $invalidation_time && $t < $invalidation_time ) ) {
	            @unlink( $path . '/' . $file );
	            hyper_log( 'cleaned ' . $file );
	        }
	    }
	
	    closedir( $handle );
	
	    hyper_log( 'end cleaning' );
	}
	
	function hyper_deactivate() {
	
	    wp_clear_scheduled_hook('hyper_clean');
	
	    if ( file_exists( MULTI_CACHE_CONFIG ) )
			unlink( MULTI_CACHE_CONFIG );
		
		if ( file_exists( MULTI_CACHE_DIR ) )
			multi_cache_delete_path( MULTI_CACHE_DIR );
	
		delete_option( 'multi_cache_started' );
	
	}
		
	function hyper_log($text) {
		if ( MULTI_CACHE_DEBUG ) {
			$file = fopen( MULTI_CACHE_DIR . '/log.txt' , 'a');
			fwrite( $file , $text . "\n" );
	    	fclose( $file );
		}
	}

	function multi_cache_write( $file , $text ) {
		if ( $file ) {
			$file = fopen( $file , 'a');
			fwrite( $file , $text . "\n" );
	    	fclose( $file );
		}
	}
	
	/*
	 * Inspired by W3 Total Cache blog tracking
	 */
	function multi_cache_track_blog( $blog_id = false , $force = false ) {
	    
		global $wpdb, $current_blog, $dm_map;
		
		$domains = array();

		if ( is_multisite() && !is_main_site() )
			$blog_id = $current_blog->blog_id;
		else 
			$blog_id = 1;
		
		// get raw siteurl
		if ( is_object( $dm_map ) )	{	
			remove_filter( 'pre_option_siteurl' , array( $dm_map , 'domain_mapping_siteurl' ) );
		}
		
		$domains[] = get_option( 'siteurl' );
		
		if ( is_object( $dm_map ) )	{
			add_filter( 'pre_option_siteurl' , array( $dm_map , 'domain_mapping_siteurl' ) );
		
			$domains[] = get_option( 'siteurl' );
		}
		
		// use this to tie in with other domain mapping
		$domains = apply_filters( 'multi_cache_domains' , $domains , $blog_id );
		
		if ( !get_transient( 'multi_blog_tracking_'.$current_blog->blog_id ) ) {
			foreach( $domains as $domain ) {
				// check siteurl
				if ( $domain != '' ) { 
					
					// will be different for mapped domains	
					if ( is_multisite() && !is_subdomain_install() && !is_main_site() ) {
						$site_name = str_replace( network_home_url() , '' , $domain );
	
					} else {
						$site_name = $domain;
					}
					
					$site_name = str_replace( 'https://' , '' , $site_name );
					$site_name = str_replace( 'http://' , '' , $site_name );
					
					if ( defined( 'MULTICACHE_LOCAL' ) && defined( 'MULTICACHE_LOCAL_DOMAIN' ) )
						$site_name = str_replace( MULTICACHE_LOCAL_DOMAIN , '' , $site_name );
					
					$first_letter = substr( $site_name , 0 , 1 );
					
					if ( $first_letter != '' && $site_name != '' ) {
				    	$filename = trailingslashit( MULTI_CACHE_BLOGINFO ) .$first_letter.'.php';
	
						multi_cache_add_blog_to_log( $filename , $blog_id , $domain );
					}
			    }
		
			}
			
			// use this to check anything else...
			do_action( 'multi_cache_track_blogs' , $blog_id );

			set_transient( 'multi_blog_tracking_'.$current_blog->blog_id , 'stuff' , 2 );
		}
	}
		
	/* 
	 * Actually writes bloginfo into correct file
	 */	
	function multi_cache_add_blog_to_log( $filename , $blog_id , $siteurl ) {

		// if file does not exist yet	
	    if ( !file_exists( $filename ) ) {
	
	        $blog_ids = array();
		
		// if it does, get contents
	    } else {
	     
	   		$data_orig = file_get_contents($filename);
	        $blog_ids = @eval( $data_orig );
	        if ( !is_array( $blog_ids ) )
	            $blog_ids = array();
	    
		}
		
		// if we already have a blogid saved and it matches current blog
	    if ( isset( $blog_ids[$siteurl] ) && $blog_ids[$siteurl] == $blog_id ) {
		
		} else {
			
			if ( isset( $blog_ids[$siteurl] ) )
				unset( $blog_ids[$siteurl] );
			
			// add new blogid
		    $blog_ids_strings[] = "'" . $siteurl . "' => '" .$blog_id . "'";
		    
			// add in the rest of the saved ones
			foreach ( $blog_ids as $key => $value )
		        $blog_ids_strings[] = "'" . $key . "' => '" . $value . "'";
		
		    $data = sprintf( 'return array(%s);' , implode( ', ' , $blog_ids_strings ) );
			
			if ( ! file_exists( MULTI_CACHE_BLOGINFO ) )
	    		wp_mkdir_p( MULTI_CACHE_BLOGINFO  );	
	
			if ( $data_orig != $data ) {
		     	@file_put_contents($filename, $data );		
			}
		
		}	
	
	}

	function multi_cache_custom_admin_bar() {
		
	    global $wp_admin_bar, $current_user;
	    
		if ( !is_admin_bar_showing() )
	        return;
		
		if ( ! current_user_can( 'edit_posts' ) )
			return;
			
		$menu_id = "multi-cache-menu";
		
		// create main menu item
	    $wp_admin_bar->add_menu( array(
	        'id'   => $menu_id,
			'parent' => 'top-secondary',
	        'meta' => array(),
	        'title' => "Cache",
	        'href' => '' ) 
		);
		
		// clear network cache - only for super admins
		if ( is_multisite() && is_super_admin() ) {

			$wp_admin_bar->add_menu( array(
	          	'parent' => $menu_id,
       			'id'   => $menu_id.'-network',
	
	          	'title' => "Clear Network Cache",
	          	'href' => '',
	          	'meta' => array()
	      	) );
		
		}
		
		// clear individual site cache
		$wp_admin_bar->add_menu( array(
          	'parent' => $menu_id,
       		'id'   => $menu_id.'-site',
          	'title' => "Clear Site Cache",
          	'href' => '',
          	'meta' => array()
      	) );
		
		// clear individual post cache
		if ( !is_admin() || strpos( $_SERVER['PHP_SELF'] , 'post.php' ) !== false ) {
		
			$wp_admin_bar->add_menu( array(
	          	'parent' => $menu_id,
		       	'id'   => $menu_id.'-page',
	          	'title' => "Clear Page Cache",
	          	'href' => '',
	          	'meta' => array()
	      	) );

		}

		// link to settings page
		$wp_admin_bar->add_menu( array(
          	'parent' => $menu_id,
	       	'id'   => $menu_id.'-settings',
          	'title' => "Cache Settings",
          	'href' => '',
          	'meta' => array()
      	) );		
	
	}
	
	function multi_cache_generate_config( &$options ) {
	
		global $current_blog;
		
	//	print_r($current_blog);
		
	    $buffer = '';
	
	    $timeout = $options['timeout']*60;
	
	    if ( $timeout == 0 ) 
			$timeout = 2000000000;
	
	    $buffer = "<?php\n";
		
		if ( is_multisite() )
		    $buffer .= '$hyper_cache_network_url = \'' . network_home_url() . '\'' . ";\n";

		// path to cache files
	    $buffer .= '$hyper_cache_path = \'' . trailingslashit( MULTI_CACHE_DIR ) . '\'' . ";\n";
	
	    $buffer .= '$hyper_cache_charset = "' . get_option('blog_charset') . '"' . ";\n";
	
	    // Collect statistics
	    //$buffer .= '$hyper_cache_stats = ' . (isset($options['stats'])?'true':'false') . ";\n";

	    // Do not cache for commenters
	   // $buffer .= '$hyper_cache_blog_id = ' . $wpdb->blog_id . ";\n";
	
	    // Do not cache for commenters
	    $buffer .= '$hyper_cache_comment = ' . ( $options['comment'] == true ? 'true' : 'false' ) . ";\n";
	
	    // Single page timeout
	    $buffer .= '$hyper_cache_timeout = ' . ( $timeout ) . ";\n";

	    // Separate caching for mobile agents?
	    $buffer .= '$hyper_cache_mobile = ' . ( $options['mobile'] == true ?'true' : 'false' ) . ";\n";

	    // Cache the feeds?
	    $buffer .= '$hyper_cache_feed = ' . ( $options['feed'] == true ? 'true' : 'false' ) . ";\n";
	
	    // Disable last modified header
	    $buffer .= '$hyper_cache_lastmodified = ' . ( $options['lastmodified'] == true ? 'true' : 'false' ) . ";\n";
	
	    // Allow browser caching?
	    $buffer .= '$hyper_cache_browsercache = ' . ( $options['browsercache'] == true ? 'true' : 'false' ) . ";\n";
	
	    // Do not use cache if browser sends no-cache header?
	    $buffer .= '$hyper_cache_nocache = ' . ( $options['nocache'] == true ? 'true' : 'false' ) . ";\n";
	
	    if ( $options['gzip'] ) 
			$options['store_compressed'] = 1;
	
	    $buffer .= '$hyper_cache_gzip = ' . ( $options['gzip'] == true ? 'true' : 'false' ) . ";\n";
	
	    $buffer .= '$hyper_cache_gzip_on_the_fly = ' . ( $options['gzip_on_the_fly'] == true ? 'true' : 'false' ) . ";\n";
	
	    $buffer .= '$hyper_cache_store_compressed = ' . ( $options['store_compressed'] == true ? 'true' : 'false' ) . ";\n";
		
	    if ( isset( $options['reject'] ) && trim( $options['reject'] ) != '' ) {
	
	        $options['reject'] = str_replace( ' ' , "\n" , $options['reject'] );
	        $options['reject'] = str_replace( "\r" , "\n" , $options['reject'] );
	        $buffer .= '$hyper_cache_reject = array(';
	        $reject = explode( "\n", $options['reject'] );
	        $options['reject'] = '';
	        foreach ( $reject as $uri ) {
	            $uri = trim( $uri );
	            if ( $uri == '' ) 
					continue;
	            $buffer .= "\"" . addslashes( trim( $uri ) ) . "\",";
	            $options['reject'] .= $uri . "\n";
	        }
	        $buffer = rtrim( $buffer , ',' );
	        $buffer .= ");\n";
	    } else {
	        $buffer .= '$hyper_cache_reject = false;' . "\n";
	    }
	
	    if ( isset( $options['reject_agents'] ) && trim( $options['reject_agents'] ) != '' ) {
	        $options['reject_agents'] = str_replace( ' ' , "\n" , $options['reject_agents'] );
	        $options['reject_agents'] = str_replace( "\r" , "\n" , $options['reject_agents'] );
	        $buffer .= '$hyper_cache_reject_agents = array(';
	        $reject_agents = explode( "\n", $options['reject_agents'] );
	        $options['reject_agents'] = '';
	        foreach ( $reject_agents as $uri ) {
	            $uri = trim( $uri );
	            if ( $uri == '' ) continue;
	            $buffer .= "\"" . addslashes( strtolower( trim( $uri ) ) ) . "\",";
	            $options['reject_agents'] .= $uri . "\n";
	        }
	        $buffer = rtrim( $buffer , ',' );
	        $buffer .= ");\n";
	    } else {
	        $buffer .= '$hyper_cache_reject_agents = false;' . "\n";
	    }
	
	    if ( isset( $options['reject_cookies'] ) && trim( $options['reject_cookies'] ) != '' ) {
	        $options['reject_cookies'] = str_replace( ' ' , "\n" , $options['reject_cookies'] );
	        $options['reject_cookies'] = str_replace( "\r" , "\n" , $options['reject_cookies'] );
	        $buffer .= '$hyper_cache_reject_cookies = array(';
	        $reject_cookies = explode( "\n" , $options['reject_cookies'] );
	        $options['reject_cookies'] = '';
	        foreach ( $reject_cookies as $c ) {
	            $c = trim( $c );
	            if ( $c == '' ) continue;
	            $buffer .= "\"" . addslashes( strtolower( trim( $c ) ) ) . "\",";
	            $options['reject_cookies'] .= $c . "\n";
	        }
	        $buffer = rtrim( $buffer, ',' );
	        $buffer .= ");\n";
	    }  else {
	        $buffer .= '$hyper_cache_reject_cookies = false;' . "\n";
	    }
	
	    if ( isset( $options['mobile'] ) ) {
	        if ( !isset($options['mobile_agents']) || trim( $options['mobile_agents'] ) == '') {
	            $options['mobile_agents'] = "elaine/3.0\niphone\nipod\npalm\neudoraweb\nblazer\navantgo\nwindows ce\ncellphone\nsmall\nmmef20\ndanger\nhiptop\nproxinet\nnewt\npalmos\nnetfront\nsharp-tq-gx10\nsonyericsson\nsymbianos\nup.browser\nup.link\nts21i-10\nmot-v\nportalmmm\ndocomo\nopera mini\npalm\nhandspring\nnokia\nkyocera\nsamsung\nmotorola\nmot\nsmartphone\nblackberry\nwap\nplaystation portable\nlg\nmmp\nopwv\nsymbian\nepoc";
	        }
	
	        if ( trim( $options['mobile_agents'] ) != '' ) {
	            $options['mobile_agents'] = str_replace( ',', "\n", $options['mobile_agents'] );
	            $options['mobile_agents'] = str_replace( "\r", "\n", $options['mobile_agents'] );
	            $buffer .= '$hyper_cache_mobile_agents = array(';
	            $mobile_agents = explode( "\n", $options['mobile_agents'] );
	            $options['mobile_agents'] = '';
	            foreach ( $mobile_agents as $uri ) {
	                $uri = trim( $uri );
	                if ( $uri == '' ) continue;
	                $buffer .= "\"" . addslashes( strtolower( trim( $uri ) ) ) . "\",";
	                $options['mobile_agents'] .= $uri . "\n";
	            }
	            $buffer = rtrim( $buffer, ',' );
	            $buffer .= ");\n";
	        } else {
	            $buffer .= '$hyper_cache_mobile_agents = false;' . "\n";
	        }
	    }
	    
	    $buffer .= "include('".trailingslashit( MULTI_CACHE_PLUGIN_DIR )."cache.php');\n";
	    $buffer .= '?'.'>';
	
	    return $buffer;
	}
?>