<?php
/**
 * Plugin Name:       Sync Part Multisite
 * Description:       Sync template parts and patterns across multisite installations.
 * Version:           0.1.0
 * Plugin URI:        https://github.com/dhananjaykuber/syncpart-multisite
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Dhananjay Kuber
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       syncpart-multisite
 *
 * @package           syncpart-multisite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sync patterns across multisite installations.
 *
 * @param int    $post_id The ID of the post being published.
 * @param object $post    The post object being published.
 */
function syncpart_multisite_sync_patterns( $post_id, $post ) {

	// Get the sync status of the pattern.
	$sync_status = get_post_meta( $post_id, 'wp_pattern_sync_status', true );

	// Get all the sites in the multisite network, excluding the current site.
	$site_ids = get_sites(
		array(
			'fields'       => 'ids',
			'site__not_in' => get_current_blog_id(),
			'deleted'      => 0,
		)
	);

	// Remove the action to prevent infinite loop.
	remove_action( 'publish_wp_block', __FUNCTION__, 30, 2 );

	if ( ! empty( $site_ids ) && is_array( $site_ids ) ) {

		foreach ( $site_ids as $site_id ) {

			// Switch to the site.
			switch_to_blog( $site_id );

			// Get the existing pattern by slug.
			$existing_pattern = get_page_by_title( $post->post_title, OBJECT, 'wp_block' );

			// If the pattern exists, update it.
			if ( $existing_pattern ) {
				$pattern_id = wp_update_post(
					array(
						'ID'           => $existing_pattern->ID,
						'post_content' => $post->post_content,
					)
				);
			} else {
				// If the pattern does not exist, create a new one.
				$pattern_id = wp_insert_post(
					array(
						'post_title'     => $post->post_title,
						'post_content'   => $post->post_content,
						'post_type'      => 'wp_block',
						'post_status'    => 'publish',
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					)
				);
			}

			if ( ! is_wp_error( $pattern_id ) ) {

				if ( 'unsynced' === $sync_status ) {
					// Update the sync status to 'synced'.
					update_post_meta( $pattern_id, 'wp_pattern_sync_status', 'synced' );
				}
			}

			restore_current_blog();
		}

		add_action( 'publish_wp_block', __FUNCTION__, 30, 2 );
	}
}

add_action( 'publish_wp_block', 'syncpart_multisite_sync_patterns', 30, 2 );
