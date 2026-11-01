<?php
/**
 * Removes everything the plugin ever stored. One option row, nothing else:
 * no tables, no post meta, no transients, no scheduled events.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'scc_options' );
delete_site_option( 'scc_options' );

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'scc_options' );
		restore_current_blog();
	}
}
