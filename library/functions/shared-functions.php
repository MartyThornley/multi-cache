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
	    
		global $multi_cache_uri, $hc_file;
				 
	    if ( $delete && $hc_file != '' && is_file( $hc_file ) ) 
			@unlink( $hc_file );
		if ( !defined( 'MULTI_CACHE_DISABLE_WRITING' ) )
			ob_start( 'multi_cache_callback' );    
		
	}		

	// From here Wordpress starts to process the request
	// Called whenever the page generation is ended
	function multi_cache_callback( $buffer ) {
	    global $multi_cache_charset, $hc_file, $multi_cache_name, $multi_cache_browsercache, $multi_cache_timeout, $multi_cache_lastmodified, $multi_cache_gzip, $multi_cache_gzip_on_the_fly;   

	    if ( strpos( $buffer, '</body>' ) === false ) 
			return $buffer;
	
		$buffer = trim( $buffer );
	
	    // Can be a trackback or other things without a body. We do not cache them, WP needs to get those calls.
	    if ( strlen( $buffer ) == 0 ) 
			return '';
	
	    if ( !$multi_cache_charset ) 
			$multi_cache_charset = 'UTF-8';
				
	    $buffer .= '<!-- MultiCache: ' . $multi_cache_name . ' ' . date( 'M d Y H:i:s' ) .' -->';
	
	    $data['html'] = $buffer;
	
	    multi_cache_write( $data );
	
	    if ( $multi_cache_browsercache ) {
	        header( 'Cache-Control: max-age=' . $multi_cache_timeout );
	        header( 'Expires: ' . gmdate( "M d Y H:i:s", time() + $multi_cache_timeout ) . " GMT" );
	    }
	
	    // True if user ask to NOT send Last-Modified
	    if ( !$multi_cache_lastmodified )
	        header( 'Last-Modified: ' . gmdate( "M d Y H:i:s", @filemtime( $hc_file ) ). " GMT" );
	    
	    if ( ( $multi_cache_gzip && !empty($data['gz'] ) ) || ( $multi_cache_gzip_on_the_fly && !empty( $data['html'] ) && function_exists(' gzencode' ) ) ) {
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
	function multi_cache_write( &$data ) {
	    global $hc_file, $multi_cache_store_compressed;
	
	    $data['uri'] = $_SERVER['REQUEST_URI'];
	
	    // Look if we need the compressed version
	    if ( $multi_cache_store_compressed && !empty( $data['html'] ) && function_exists( 'gzencode' ) ) {
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
	function multi_cache_mobile_type() {
	    global $multi_cache_mobile, $multi_cache_mobile_agents;
	
	    if ( !isset( $multi_cache_mobile ) || $multi_cache_mobile_agents === false ) 
			return '';
	
	    $multi_cache_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	    foreach ( array($multi_cache_mobile_agents) as $multi_cache_a ) {
	        if ( strpos( $multi_cache_agent, $multi_cache_a ) !== false ) {
	            if ( strpos( $multi_cache_agent, 'iphone' ) || strpos( $multi_cache_agent, 'ipod' ) ) {
	                return 'iphone'; 
	            } else {
	                return 'pda';
	            }
	        }
	    }
	    return '';
	}
	
	function multi_cache_gzdecode( $data ) {
	
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
	
	function multi_cache_exit() {
	    global $multi_cache_gzip_on_the_fly;
	
	    if ( $multi_cache_gzip_on_the_fly && extension_loaded( 'zlib' ) ) 
			ob_start( 'ob_gzhandler' );
	    
		return false;
	}