<?php
/**
 * Shared functions that can be used by plugin and cache.php file
 */

	/*
	 * Use this to hash everything in case we want to change hash method
	 */
	function multi_cache_hash( $string ){
		
		return md5( $string );
	
	}

	/*
	 * Start the caching process
	 */
	function multi_cache_start( $delete = true ) {
	    
		global $hyper_uri, $hc_file;
				 
	    if ( $delete && $hc_file != '' && is_file( $hc_file ) ) 
			@unlink( $hc_file );
		if ( !defined( 'MULTI_CACHE_DISABLE_WRITING' ) )
			ob_start( 'hyper_cache_callback' );    
		
	}		

	// From here Wordpress starts to process the request
	// Called whenever the page generation is ended
	function hyper_cache_callback( $buffer ) {
	    global $hyper_cache_charset, $hc_file, $hyper_cache_name, $hyper_cache_browsercache, $hyper_cache_timeout, $hyper_cache_lastmodified, $hyper_cache_gzip, $hyper_cache_gzip_on_the_fly;   

	    if ( strpos( $buffer, '</body>' ) === false ) 
			return $buffer;
	
		$buffer = trim( $buffer );
	
	    // Can be a trackback or other things without a body. We do not cache them, WP needs to get those calls.
	    if ( strlen( $buffer ) == 0 ) 
			return '';
	
	    if ( !$hyper_cache_charset ) 
			$hyper_cache_charset = 'UTF-8';
	
	    $buffer .= '<!-- hyper cache: ' . $hyper_cache_name . ' ' . date('y-m-d h:i:s') .' -->';
	
	    $data['html'] = $buffer;
	
	    hyper_cache_write( $data );
	
	    if ( $hyper_cache_browsercache ) {
	        header( 'Cache-Control: max-age=' . $hyper_cache_timeout );
	        header( 'Expires: ' . gmdate( "D, d M Y H:i:s", time() + $hyper_cache_timeout ) . " GMT" );
	    }
	
	    // True if user ask to NOT send Last-Modified
	    if ( !$hyper_cache_lastmodified )
	        header( 'Last-Modified: ' . gmdate( "D, d M Y H:i:s", @filemtime( $hc_file ) ). " GMT" );
	    
	    if ( ( $hyper_cache_gzip && !empty($data['gz'] ) ) || ( $hyper_cache_gzip_on_the_fly && !empty( $data['html'] ) && function_exists(' gzencode' ) ) ) {
	        header('Content-Encoding: gzip');
	        header('Vary: Accept-Encoding');
	        if ( empty( $data['gz'] ) ) {
	            $data['gz'] = gzencode( $data['html'] );
	        }
	        return $data['gz'];
	    }
	
	    return $buffer;
	}
	
	/*
	 * Writes the actual cache file
	 */
	function hyper_cache_write( &$data ) {
	    global $hc_file, $hyper_cache_store_compressed;
	
	    $data['uri'] = $_SERVER['REQUEST_URI'];
	
	    // Look if we need the compressed version
	    if ( $hyper_cache_store_compressed && !empty( $data['html'] ) && function_exists( 'gzencode' ) ) {
	        $data['gz'] = gzencode( $data['html'] );
	        if ( $data['gz'] ) 
				unset( $data['html'] );
	    }
	
	    $file = fopen( $hc_file, 'w' );
	    fwrite( $file, serialize( $data ) );
	    fclose( $file );
	}
	
	/*
	 * Check Mobile Type
	 */
	function hyper_mobile_type() {
	    global $hyper_cache_mobile, $hyper_cache_mobile_agents;
	
	    if ( !isset( $hyper_cache_mobile ) || $hyper_cache_mobile_agents === false ) 
			return '';
	
	    $hyper_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	    foreach ( array($hyper_cache_mobile_agents) as $hyper_a ) {
	        if ( strpos( $hyper_agent, $hyper_a ) !== false ) {
	            if ( strpos( $hyper_agent, 'iphone' ) || strpos( $hyper_agent, 'ipod' ) ) {
	                return 'iphone'; 
	            } else {
	                return 'pda';
	            }
	        }
	    }
	    return '';
	}
	
	function hyper_cache_gzdecode( $data ) {
	
	    $flags = ord(substr($data, 3, 1));
	    $headerlen = 10;
	    $extralen = 0;
	
	    $filenamelen = 0;
	    if ($flags & 4) {
	        $extralen = unpack('v' ,substr($data, 10, 2));
	
	        $extralen = $extralen[1];
	        $headerlen += 2 + $extralen;
	    }
	    if ($flags & 8) // Filename
	
	        $headerlen = strpos($data, chr(0), $headerlen) + 1;
	    if ($flags & 16) // Comment
	
	        $headerlen = strpos($data, chr(0), $headerlen) + 1;
	    if ($flags & 2) // CRC at end of file
	
	        $headerlen += 2;
	    $unpacked = gzinflate(substr($data, $headerlen));
	    return $unpacked;
	}
	
	function hyper_cache_exit() {
	    global $hyper_cache_gzip_on_the_fly;
	
	    if ( $hyper_cache_gzip_on_the_fly && extension_loaded( 'zlib' ) ) 
			ob_start( 'ob_gzhandler' );
	    
		return false;
	}