<?php
/*
 * Performs the actual caching
 *
 * Called by 'wp-content/advanced-cache.php'
 *
 * Must have define('WP_CACHE', true); in wp-config.php
 *
 *
 * Some wp functions are available here but not all.
 * We can use is_admin() , is_multisite() 
 */	

	// just for testing, prevent cache from being written
	//define( 'MULTI_CACHE_DISABLE_WRITING' , false );
	
	// should be added via config file based on options.
	// will tell us to ignore logged in users and skip the cache
	define( 'MULTI_CACHE_IGNORE_USERS' , true );	
	
	// need to save this in config, with network_home_url()
	define( 'MULTI_CACHE_BASE_URL' , 'localhost/_moviefuse/' );

	if ( !defined( 'MULTI_CACHE_PLUGIN_DIR' ) ) 
		define( 'MULTI_CACHE_PLUGIN_DIR' , 	dirname( __FILE__ ) );

	include_once( MULTI_CACHE_PLUGIN_DIR . '/library/functions/shared-functions.php' );
	
	// need to check domain.com ( $_SERVER['HTTP_HOST'] ) and domain.com/something ( $_SERVER['REQUEST_URI'] )
	
	$protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
	
	$urls['base_url'] = MULTI_CACHE_BASE_URL;
	$urls['full_url'] = trim( $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] , '/' );
	
	$folders = str_replace( MULTI_CACHE_BASE_URL , '' , $urls['full_url'] );

	$folders = explode( '/' , $folders );

	if ( is_array( $folders ) && isset( $folders[0] ) && $folders[0] != '' ) {
	
		$this_sub_directory = $folders[0];
		
	} elseif( is_array( $folders ) && isset( $folders[1] ) && $folders[1] != '' ) {
	
		$this_sub_directory = $folders[1];
		
	}
	
	$this_sub_directory = str_replace( '/' , '' , $this_sub_directory );

	$urls['sub_directory'] = MULTI_CACHE_BASE_URL . $this_sub_directory;

	$hyper_uri = $_SERVER['REQUEST_URI'];
	
	$base_domain = str_replace( 'https://' , '' , $urls['base_url'] );
	$base_domain = str_replace( 'http://' , '' , $base_domain  );	
	
	if ( $base_domain != '' && $base_domain != $protocol . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] )
		$first_letters[] = substr( $base_domain , 0 , 1 );

	if ( $this_sub_directory != '' )
		$first_letters[] = substr( $this_sub_directory , 0 , 1 );;
	
	if ( function_exists( 'is_admin' ) && is_admin() )
		return hyper_cache_exit();
	
	// If no-cache header support is enabled and the browser explicitly requests a fresh page, do not cache
	if ( $hyper_cache_nocache && ( ( !empty($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'no-cache' ) || ( !empty($_SERVER['HTTP_PRAGMA']) && $_SERVER['HTTP_PRAGMA'] == 'no-cache')  ) ) 
		return hyper_cache_exit();
	
	// Do not cache post request (comments, plugins and so on)
	if ( $_SERVER["REQUEST_METHOD"] == 'POST' ) 
		return hyper_cache_exit();
	
	// Try to avoid enabling the cache if sessions are managed with request parameters and a session is active
	if ( defined( 'SID' ) && SID != '' ) 
		return hyper_cache_exit();
	
	// don't cache robots.txt
	if ( strpos( $hyper_uri, 'robots.txt' ) !== false ) 
		return hyper_cache_exit();

	// Checks for rejected urls
	if ( $hyper_cache_reject !== false ) {
	    foreach ( $hyper_cache_reject as $uri ) {
			
			// finds exact url
	        if ( substr($uri, 0, 1) == '"') {
	            if ( $uri == '"' . $hyper_uri . '"' ) 
					return hyper_cache_exit();
	        }
			
			// finds start of string - /blogs will skip /blogsite
	        if ( substr( $hyper_uri , 0 , strlen( $uri ) ) == $uri ) 
				return hyper_cache_exit();
	    }
	}

	// do not cache selected user agents	
	if ( $hyper_cache_reject_agents !== false ) {
	    $hyper_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	    foreach ( $hyper_cache_reject_agents as $hyper_a ) {
	        if ( strpos( $hyper_agent, $hyper_a ) !== false ) 
				return hyper_cache_exit();
	    }
	}
	
	// Do nested cycles in this order, usually no cookies are specified
	if ( $hyper_cache_reject_cookies !== false ) {
	    foreach ( $hyper_cache_reject_cookies as $hyper_c ) {
	        foreach ( $_COOKIE as $n=>$v ) {
	            if ( substr( $n, 0, strlen( $hyper_c ) ) == $hyper_c ) 
					return hyper_cache_exit();
	        }
	    }
	}
	
	if ( defined( 'MULTI_CACHE_IGNORE_USERS' ) ) {
		// Do not use or cache pages when a wordpress user is logged on
		foreach ( $_COOKIE as $n=>$v ) {
		
			// If it's required to bypass the cache when the visitor is a commenter, stop.
		    if ( $hyper_cache_comment && substr( $n, 0, 15 ) == 'comment_author_' ) 
				return hyper_cache_exit();
		
		    // This test cookie makes to cache not work!!!
		    if ( $n == 'wordpress_test_cookie' ) 
		 		continue;
		    
			// wp 2.5 and wp 2.3 have different cookie prefix, skip cache if a post password cookie is present, also
		    if (substr($n, 0, 14) == 'wordpressuser_' || substr($n, 0, 10) == 'wordpress_' || substr($n, 0, 12) == 'wp-postpass_' || substr($n, 0, 20) == 'wordpress_logged_in_' ) {
		        return hyper_cache_exit();
		    }
		}
	}

	// Do not cache WP pages, even if those calls typically don't go throught this script
	if ( strpos( $hyper_uri, '/wp-' ) !== false ) 
		return hyper_cache_exit();

	// Multisite - skip files ??
	if ( function_exists( 'is_multisite' ) && is_multisite() && strpos( $hyper_uri, '/files/' ) !== false ) 
		return hyper_cache_exit();

	// Prefix host, and for wordpress 'pretty URLs' strip trailing slash (e.g. '/my-post/' -> 'my-site.com/my-post')
	$hyper_uri = $_SERVER['HTTP_HOST'] . $hyper_uri;
	
	
	if ( !is_array( $first_letters ) )
		return hyper_cache_exit();
					//print_r($first_letters);
	// based on urls, look for saved blog ids under the first letters
	
	foreach ( $urls as $key => $url ) {
	
		$urls[$key] = trim( $protocol.$url , '/' );
	
	}
	
	foreach( $first_letters as $key => $first_letter ) {
		
		$file =  $hyper_cache_path . 'bloginfo/' . $first_letter.'.php';
		if ( file_exists( $file ) ){
		
			$data_orig = file_get_contents( $file );
	        $blog_ids = @eval( $data_orig );

			//print '<pre>'; print_r($urls); print '</pre>'; 	


	        if ( !is_array( $blog_ids ) )
	            $blog_ids = array();	
		

			// check full url first - would catch all homepages correctly...	
			if ( isset( $blog_ids[$urls['full_url']] ) ) {
			
				$blog_id = $blog_ids[$urls['full_url']];
			
			// next check "subdirectories" ( should only effect subdirectory installs )
			// subdomains would not have a subdirectory structure saved at all
			} elseif ( isset( $blog_ids[$urls['sub_directory']] ) ) {
			
				$blog_id = $blog_ids[$urls['sub_directory']];
			
			// last, check "base_url"
			// should get all subdomain installs & not conflict with main site
			// or would work for main site
			} elseif ( isset( $blog_ids[$urls['base_url']] ) ) {
			
				$blog_id = $blog_ids[$urls['base_url']];

			} else {

				$blog_id = NULL;
				
			}

		}
	}


	// if we had no saved ids we can exit
	if ( !isset( $blog_id ) || $blog_id == '' )
		return hyper_cache_exit();
	
	// add 9 digits to allow for huge sites
	$cache_dir = $hyper_cache_path .'pages/'. sprintf("%09s", $blog_id);
	
	if ( !file_exists( $hyper_cache_path .'pages/' ) )
		mkdir( $hyper_cache_path .'pages/' );
		
	if ( !file_exists( $cache_dir ) )
		mkdir( $cache_dir );
	
	// The name of the file with html and other data	
	$hyper_cache_name = multi_cache_hash( $hyper_uri );

	//print '<pre>'; print_r($hyper_uri); print '</pre>'; 

		
	$hc_file = $cache_dir .'/'. $hyper_cache_name . hyper_mobile_type() . '.dat';

	if ( !file_exists( $hc_file ) ) {
	    multi_cache_start( false );
	    return;
	}
	// check file time and age
	$hc_file_time = @filemtime($hc_file);
	$hc_file_age = time() - $hc_file_time;
	
	// if it is too old, create a new one
	if ( $hc_file_age > $hyper_cache_timeout ) {
	    multi_cache_start();
	    return;
	}

	if ( array_key_exists( "HTTP_IF_MODIFIED_SINCE", $_SERVER ) ) {
	    $if_modified_since = strtotime( preg_replace( '/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"] ) );
	    if ( $if_modified_since >= $hc_file_time ) {
	        header( $_SERVER['SERVER_PROTOCOL'] . " 304 Not Modified" );
	        flush();
	        die();
	    }
	}
	
	// Load it and check is it's still valid
	$hyper_data = @unserialize( file_get_contents( $hc_file ) );
	
	if ( !$hyper_data ) {
	    multi_cache_start();
	    return;
	}

	// Valid cache file check ends here
	
	if ( !empty($hyper_data['location'] ) ) {
	    header( 'Location: ' . $hyper_data['location'] );
	    flush();
	    die();
	}
	
	/*
	 * It's time to serve the cached page
	 */ 
	
	// True if browser caching NOT enabled (default)
	if ( !$hyper_cache_browsercache ) {
	    
	    header('Cache-Control: no-cache, must-revalidate, max-age=0');
	    header('Pragma: no-cache');
	    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	
	} else {
	
	    $maxage = $hyper_cache_timeout - $hc_file_age;
	    header('Cache-Control: max-age=' . $maxage);
	    header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $maxage) . " GMT");
	}
	
	// True if user ask to NOT send Last-Modified
	if ( !$hyper_cache_lastmodified ) {
	    header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $hc_file_time). " GMT");
	}
	
	header( 'Content-Type: ' . $hyper_data['mime'] );
	
	if ( isset($hyper_data['status'] ) && $hyper_data['status'] == 404 ) header( $_SERVER['SERVER_PROTOCOL'] . " 404 Not Found" );
	
	// Send the cached html
	if ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && strpos( $_SERVER['HTTP_ACCEPT_ENCODING'] , 'gzip' ) !== false &&
	    ( ( $hyper_cache_gzip && !empty( $hyper_data['gz'] ) ) || ( $hyper_cache_gzip_on_the_fly && function_exists( 'gzencode' ) ) ) ) {
	
	    header( 'Content-Encoding: gzip' );
	    header( 'Vary: Accept-Encoding' );
	
	    if ( !empty( $hyper_data['gz'] ) ) {
	        echo $hyper_data['gz'];
	    } else {
	        echo gzencode( $hyper_data['html'] );
	    }
	
	// No compression accepted, check if we have the plain html or
	// decompress the compressed one.
	} else {

	    if ( !empty($hyper_data['html'] ) ) {
	    
			//header('Content-Length: ' . strlen($hyper_data['html']));
	        echo $hyper_data['html'];
	    
		} elseif ( function_exists( 'gzinflate' ) ) {
	     
	   		$buffer = hyper_cache_gzdecode( $hyper_data['gz'] );
	        if ( $buffer === false ) 
				echo 'Error retrieving the content';
	        else 
				echo $buffer;
	    }
	    else {
	        // Cannot decode compressed data, serve fresh page
	        return false;
	    }
	}
	
	flush();
	die();