<?php
/*
 * Options Page for admin
 */

	function multi_cache_admin() {
	
		$multi_cache_options = multi_cache_options();
	
		$error = false;
		
		if ( empty( $_POST ) || !wp_verify_nonce( $_POST['multi_cache_options'] ,'multi_cache_options' ) )  {
			
			// do nothing
			
		} else {
			
			// delete cache for site when saving from multisite
			if ( isset( $_POST['multi_cache_site_clean'] ) ) {
			    hyper_delete_path( MULTI_CACHE_DIR );
			}
			
			
			$posted = stripslashes_deep( $_POST['options'] );
			
			if ( isset( $posted ) && is_array( $posted ) ) {
				
			    if ( $multi_cache_options['gzip'] !== esc_html( $posted['gzip'] ) ) {
			        hyper_delete_path( MULTI_CACHE_DIR );
			    }
		
				if ( isset( $posted['timeout'] ) ) 
					$multi_cache_options['timeout'] = (int)$posted['timeout'];
				else 
					$multi_cache_options['timeout'] = 60;
	
	
				if ( isset( $posted['clean_interval'] ) ) 
					$multi_cache_options['clean_interval'] = (int)$posted['clean_interval'];
				else 
					$multi_cache_options['clean_interval'] = 60;				
	
				if ( isset( $posted['comment'] ) ) 
					$multi_cache_options['comment'] = (int)$posted['comment'];
				else 
					$multi_cache_options['comment'] = 0;	
	
				if ( isset( $posted['feed'] ) ) 
					$multi_cache_options['feed'] = (int)$posted['feed'];
				else 
					$multi_cache_options['feed'] = 0;
	
				if ( isset( $posted['store_compressed'] ) ) 
					$multi_cache_options['store_compressed'] = (int)$posted['store_compressed'];
				else 
					$multi_cache_options['store_compressed'] = 0;
	
				if ( isset( $posted['gzip'] ) ) 
					$multi_cache_options['gzip'] = (int)$posted['gzip'];
				else 
					$multi_cache_options['gzip'] = 0;
	
				if ( isset( $posted['browsercache'] ) ) 
					$multi_cache_options['browsercache'] = (int)$posted['browsercache'];
				else 
					$multi_cache_options['browsercache'] = 0;
					
				if ( isset( $posted['nocache'] ) ) 
					$multi_cache_options['nocache'] = (int)$posted['nocache'];
				else 
					$multi_cache_options['nocache'] = 0;
	
				if ( isset( $posted['redirects'] ) ) 
					$multi_cache_options['redirects'] = (int)$posted['redirects'];
				else 
					$multi_cache_options['redirects'] = 0;
	
				if ( isset( $posted['mobile_agents'] ) ) 
					$multi_cache_options['mobile_agents'] = esc_textarea( $posted['mobile_agents'] );
				else
					$multi_cache_options['mobile_agents'] = '';
	
				if ( isset( $posted['reject'] ) ) 
					$multi_cache_options['reject'] = esc_textarea( $posted['reject'] );
				else
					$multi_cache_options['reject'] = '';
	
				if ( isset( $posted['reject_agents'] ) ) 
					$multi_cache_options['reject_agents'] = esc_textarea( $posted['reject_agents'] );
				else
					$multi_cache_options['reject_agents'] = '';
	
				if ( isset( $posted['reject_cookies'] ) ) 
					$multi_cache_options['reject_cookies'] = esc_textarea( $posted['reject_cookies'] );
				else
					$multi_cache_options['reject_cookies'] = '';
					
			}
			//print '<pre>'; print_r( $_POST['options'] ); print '</pre>'; 
	
			//print '<pre>'; print_r( $multi_cache_options ); print '</pre>'; 
	
	
		    $buffer = multi_cache_generate_config( $multi_cache_options );
		    
		    $file = @fopen( MULTI_CACHE_CONFIG , 'w' );
		
		    if ( $file ) {
		    	@fwrite( $file , $buffer );
		    	@fclose( $file );
		    } else {
		        $error = true;
		    }
		
			multi_cache_update_options( $multi_cache_options );
		    
			// When the cache does not expire
		    if ( $multi_cache_options['expire_type'] == 'none' ) {
				if ( file_exists( trailingslashit( MULTI_CACHE_DIR ) . '_global.dat' ) )
			        @unlink( trailingslashit( MULTI_CACHE_DIR ) . '_global.dat' );
				if ( file_exists( trailingslashit( MULTI_CACHE_DIR ) . '_archives.dat' ) )
		        	@unlink( trailingslashit( MULTI_CACHE_DIR ) . '_archives.dat' );
		    }
			
			if ($multi_cache_options['mobile_agents'] == '') {
		        $multi_cache_options['mobile_agents'] = "elaine/3.0\niphone\nipod\npalm\neudoraweb\nblazer\navantgo\nwindows ce\ncellphone\nsmall\nmmef20\ndanger\nhiptop\nproxinet\nnewt\npalmos\nnetfront\nsharp-tq-gx10\nsonyericsson\nsymbianos\nup.browser\nup.link\nts21i-10\nmot-v\nportalmmm\ndocomo\nopera mini\npalm\nhandspring\nnokia\nkyocera\nsamsung\nmotorola\nmot\nsmartphone\nblackberry\nwap\nplaystation portable\nlg\nmmp\nopwv\nsymbian\nepoc";
		    }
	
		}
		
		
		?>
	
		
		<div class="wrap">
		
		
		<?php if (!defined('WP_CACHE') || !WP_CACHE) { ?>
		<div class="error">
		    <?php _e('You must add to the file wp-config.php (at its beginning after the &lt;?php) the line of code: <code>define(\'WP_CACHE\', true);</code>.', 'hyper-cache'); ?>
		</div>
		<?php } ?>
		
		<h2>Multi Cache</h2>
		
		<?php
		    if ($error)
		        echo __('<p><strong>Options saved BUT not active because Hyper Cache was not able to update the config file (is it writable?).</strong></p>', 'hyper-cache');
			
			if (!wp_mkdir_p( MULTI_CACHE_DIR ) )
		        echo __('<p><strong>Hyper Cache was not able to create the cache folder. Make it manually setting permissions to 777.</strong></p>', 'hyper-cache');
		
			//print '<pre>'; print_r($multi_cache_options); print '</pre>';
		?>
		
		
		
		<form method="post" action="">
		<?php wp_nonce_field( 'multi_cache_options' , 'multi_cache_options' ); ?>
		
		<p class="submit">
			<?php if ( is_multisite() ) { ?>
			    <input class="button-primary" type="submit" name="multi_cache_site_clean" value="<?php _e('Clear cache for entire site', 'hyper-cache'); ?>">
			<?php } else { ?>
			    <input class="button-primary" type="submit" name="multi_cache_blog_clean" value="<?php _e('Clear cache', 'hyper-cache'); ?>">
			<?php }; ?>
		</p>
		
		<h3><?php _e('Cache status', 'hyper-cache'); ?></h3>
		<table class="form-table">
		<tr valign="top">
		    <th><?php _e('Files in cache (valid and expired)', 'hyper-cache'); ?></th>
		    <td><?php echo hyper_count(); ?></td>
		</tr>
		<tr valign="top">
		    <th><?php _e('Cleaning process', 'hyper-cache'); ?></th>
		    <td>
		        <?php _e('Next run on: ', 'hyper-cache'); ?>
		        <?php
		        $next_scheduled = wp_next_scheduled('hyper_clean');
		        if (empty($next_scheduled)) echo '? (read below)';
		        else echo gmdate(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled + get_option('gmt_offset')*3600);
		        ?>
		        <div class="hints">
					<?php _e('The cleaning process runs hourly and it\'s ok to run it hourly: that grant you an efficient cache. If above there is not a valid next run time, wait 10 seconds and reenter this panel. If nothing change, try to deactivate and reactivate Hyper Cache.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		</table>
		
		
		<h3><?php _e('Configuration', 'hyper-cache'); ?></h3>
		
		<table class="form-table">
		
		<tr valign="top">
		    <th><?php _e('Time to keep Cache', 'hyper-cache'); ?></th>
		    <td>
		        <input type="text" size="5" name="options[timeout]" value="<?php echo htmlspecialchars($multi_cache_options['timeout']); ?>"/>
		        (<?php _e('minutes', 'hyper-cache'); ?>)
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Commenters', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[comment]" value="1" <?php echo $multi_cache_options['comment']?'checked':''; ?>/>
				<?php _e('Disable cache for commenters', 'hyper-cache'); ?>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Feeds', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[feed]" value="1" <?php echo $multi_cache_options['feed']?'checked':''; ?>/>
		        <?php _e('Enable caching of feeds', 'hyper-cache'); ?>
		    </td>    
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Browsers', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[browsercache]" value="1" <?php echo $multi_cache_options['browsercache']?'checked':''; ?>/>
		        <?php _e('Allow browser caching', 'hyper-cache'); ?>
		    </td>
		</tr>
		</table>
		<p class="submit">
		    <input class="button-primary" type="submit" name="save" value="<?php _e('Update'); ?>">
		</p>
		
		<h3><?php _e('Configuration for mobile devices', 'hyper-cache'); ?></h3>
		<table class="form-table">
	
		<tr valign="top">
		    <th><?php _e('Detect mobile devices', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[mobile]" value="1" <?php echo $multi_cache_options['mobile']?'checked':''; ?>/>
		        <div class="hints">
		        <?php _e('When enabled mobile devices will be detected and the cached page stored under different name.', 'hyper-cache'); ?>
		        <?php _e('This makes blogs with different themes for mobile devices to work correctly.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Mobile agent list', 'hyper-cache'); ?></th>
		    <td>
		        <textarea wrap="off" rows="4" cols="70" name="options[mobile_agents]"><?php echo htmlspecialchars($multi_cache_options['mobile_agents']); ?></textarea>
		        <div class="hints">
		        <?php _e('One per line mobile agents to check for when a page is requested.', 'hyper-cache'); ?>
		        <?php _e('The mobile agent string is matched against the agent a device is sending to the server.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		</table>
		<p class="submit">
		    <input class="button" type="submit" name="save" value="<?php _e('Update'); ?>">
		</p>
		
		
		<h3><?php _e('Compression', 'hyper-cache'); ?></h3>
		
		<?php if (!function_exists('gzencode') || !function_exists('gzinflate')) { ?>
		
		<p><?php _e('Your hosting space has not the "gzencode" or "gzinflate" function, so no compression options are available.', 'hyper-cache'); ?></p>
		
		<?php } else { ?>
		
		<table class="form-table">
		<tr valign="top">
		    <th><?php _e('Store compressed pages', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[store_compressed]" value="1" <?php echo $multi_cache_options['store_compressed']?'checked':''; ?>
		            onchange="jQuery('input[name=&quot;options[gzip]&quot;]').attr('disabled', !this.checked)" />
		        <div class="hints">
		        <?php _e('Enable this option to minimize disk space usage and make sending of compressed pages possible with the option below.', 'hyper-cache'); ?>
		        <?php _e('The cache will be a little less performant.', 'hyper-cache'); ?>
		        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'hyper-cache'); ?>
		        <br />
		        <?php _e('If you enable this option, the option below will be available as well.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Send compressed pages', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[gzip]" value="1" <?php echo $multi_cache_options['gzip']?'checked':''; ?>
		            <?php echo $multi_cache_options['store_compressed']?'':'disabled'; ?> />
		        <div class="hints">
		        <?php _e('When possible (i.e. if the browser accepts compression and the page was cached compressed) the page will be sent compressed to save bandwidth.', 'hyper-cache'); ?>
		        <?php _e('Only the textual part of a page can be compressed, not images, so a photo
		        blog will consume a lot of bandwidth even with compression enabled.', 'hyper-cache'); ?>
		        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'hyper-cache'); ?>
		        <br />
		        <?php _e('If you enable this option, the option below will be available as well.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('On-the-fly compression', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[gzip_on_the_fly]" value="1" <?php echo $multi_cache_options['gzip_on_the_fly']?'checked':''; ?> />
		        <div class="hints">
		        <?php _e('When possible (i.e. if the browser accepts compression) use on-the-fly compression to save bandwidth when sending pages which are not compressed.', 'hyper-cache'); ?>
		        <?php _e('Serving of such pages will be a little less performant.', 'hyper-cache'); ?>
		        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		</table>
		<p class="submit">
		    <input class="button" type="submit" name="save" value="<?php _e('Update'); ?>">
		</p>
		<?php } ?>
		
		
		<h3><?php _e('Advanced options', 'hyper-cache'); ?></h3>
		
		<table class="form-table">
	
		<tr valign="top">
		    <th><?php _e('Disable Last-Modified header', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[lastmodified]" value="1" <?php echo $multi_cache_options['lastmodified']?'checked':''; ?>/>
		        <div class="hints">
		        <?php _e('Disable some HTTP headers (Last-Modified) which improve performances but some one is reporting they create problems which some hosting configurations.','hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Redirect caching', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[redirects]" value="1" <?php echo $multi_cache_options['redirects']?'checked':''; ?>/>
		        <br />
		        <?php _e('Cache WordPress redirects.', 'hyper-cache'); ?>
		        <?php _e('WordPress sometime sends back redirects that can be cached to avoid further processing time.', 'hyper-cache'); ?>
		    </td>
		</tr>
	
		
		<tr valign="top">
		    <th><?php _e('Allow browser to bypass cache', 'hyper-cache'); ?></th>
		    <td>
		        <input type="checkbox" name="options[nocache]" value="1" <?php echo $multi_cache_options['nocache']?'checked':''; ?>/>
		        <div class="hints">
		        <?php _e('Do not use cache if browser sends no-cache header (e.g. on explicit page reload).','hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		</table>
		
		
		<h3><?php _e('Filters', 'hyper-cache'); ?></h3>
		<p>
		    <?php _e('Here you can: exclude pages and posts from the cache, specifying their address (URI); disable Hyper Cache for specific
		    User Agents (browsers, bot, mobile devices, ...); disable the cache for users that have specific cookies.', 'hyper-cache'); ?>
		</p>
		
		<table class="form-table">
		<tr valign="top">
		    <th><?php _e('URI to reject', 'hyper-cache'); ?></th>
		    <td>
		        <textarea wrap="off" rows="5" cols="70" name="options[reject]"><?php echo htmlspecialchars($multi_cache_options['reject']); ?></textarea>
		        <div class="hints">
		        <?php _e('Write one URI per line, each URI has to start with a slash.', 'hyper-cache'); ?>
		        <?php _e('A specified URI will match the requested URI if the latter starts with the former.', 'hyper-cache'); ?>
		        <?php _e('If you want to specify a stric matching, surround the URI with double quotes.', 'hyper-cache'); ?>
		
		        <?php
		        $languages = get_option('gltr_preferred_languages');
		        if (is_array($languages))
		        {
		            echo '<br />';
		            $home = get_option('home');
		            $x = strpos($home, '/', 8); // skips http://
		            $base = '';
		            if ($x !== false) $base = substr($home, $x);
		            echo 'It seems you have Global Translator installed. The URI prefixes below can be added to avoid double caching of translated pages:<br />';
		            foreach($languages as $l) echo $base . '/' . $l . '/ ';
		        }
		        ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Agents to reject', 'hyper-cache'); ?></th>
		    <td>
		        <textarea wrap="off" rows="5" cols="70" name="options[reject_agents]"><?php echo htmlspecialchars($multi_cache_options['reject_agents']); ?></textarea>
		        <div class="hints">
		        <?php _e('Write one agent per line.', 'hyper-cache'); ?>
		        <?php _e('A specified agent will match the client agent if the latter contains the former. The matching is case insensitive.', 'hyper-cache'); ?>
		        </div>
		    </td>
		</tr>
		
		<tr valign="top">
		    <th><?php _e('Cookies matching', 'hyper-cache'); ?></th>
		    <td>
		        <textarea wrap="off" rows="5" cols="70" name="options[reject_cookies]"><?php echo htmlspecialchars($multi_cache_options['reject_cookies']); ?></textarea>
		        <div class="hints">
		        <?php _e('Write one cookie name per line.', 'hyper-cache'); ?>
		        <?php _e('When a specified cookie will match one of the cookie names sent bby the client the cache stops.', 'hyper-cache'); ?>
		        <?php if (defined('FBC_APP_KEY_OPTION')) { ?>
		        <br />
		        <?php _e('It seems you have Facebook Connect plugin installed. Add this cookie name to make it works
		        with Hyper Cache:', 'hyper-cache'); ?>
		        <br />
		        <strong><?php echo get_option(FBC_APP_KEY_OPTION); ?>_user</strong>
		        <?php } ?>
		        </div>
		    </td>
		</tr>
		
		</table>
		
		<p class="submit">
		    <input class="button" type="submit" name="save" value="<?php _e('Update'); ?>">
		</p>
		</form>
		</div>
		<?php
	}