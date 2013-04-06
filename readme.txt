=== Multi Cache ===

Tags: cache,chaching,speed,performance,super cache,wp cache,optimization,staticization
Requires at least: 3.5
Tested up to: 3.5
Stable tag: none
Donate link: 

Multi Cache is flexible and easy to configure cache system for WordPress, built especially for MultiSite but will work with Single installs as well.

== Description ==

Settings for Multi site are only for site admins, under the network admin area and will apply to all sites.

Saved settings are then written to WP_CONTENT_DIR/advanced-cache.php, to be used for the actual caching.

NOTE - must add to wp-config:
define ( 'WP_CACHE' , true );

Folders are created under WP_CONTENT_DIR/cache/multi-cache/

One for storing a log of blog_ids:
WP_CONTENT_DIR/cache/multi-cache/bloginfo

One for storing all the cache files:
WP_CONTENT_DIR/cache/multi-cache/pages
( named that way in case we expand to offer page caches in addition to some other type )

How the bloginfo is used…
As each blog loads, we grab the siteurl, and try to find any possible mapped domains, then store an array with keys being the urls, values being the blogid.

Example: array( "mysite.domain.com" => "34" ) 

( plugins or themes will have to able to tie in and filter possible domains. )

These arrays are stored in a file named for the first letter of the domain. Why? Just keep the number of files being searched and the size of the array to filter through small in case we are dealing with large multisite networks.

So in WP_CONTENT_DIR/cache/multi-cache/bloginfo, you should end up with files like:

WP_CONTENT_DIR/cache/multi-cache/bloginfo/a.php
WP_CONTENT_DIR/cache/multi-cache/bloginfo/d.php
WP_CONTENT_DIR/cache/multi-cache/bloginfo/s.php

And in each one an array of stored blogids.

As pages load, we parse the url and try to match it up to the stored blogids under the bloginfo files. ( if done correctly, there should only ever be one match for the url name, so even if the blog has its ID stored under the subdomain, and several mapped domaains, we should be able to find it )

Once we have the blogid, a cached version is created under a directory named for the blog's id, stored in 9 digits to allow for lots of blogs.

Example cache folders:

WP_CONTENT_DIR/cache/multi-cache/pages/000000001
WP_CONTENT_DIR/cache/multi-cache/pages/000000002
WP_CONTENT_DIR/cache/multi-cache/pages/000000345

This makes seacrhing through the cache and deleting the cache much faster too. 

filenames are hashes of the current url and end up with a .dat extension

