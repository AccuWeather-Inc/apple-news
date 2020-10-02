<?php
/**
 * A generic function for handling publish/update/delete actions against the Apple News API.
 *
 * @package Apple_News
 */

namespace Apple_News\REST;

use \Admin_Apple_News;
use \Apple_Actions\Action_Exception;
use \Apple_Actions\Index\Delete;
use \Apple_Actions\Index\Push;
use \Apple_News;
use \WP_Error;

/**
 * Publish, update, or delete a post via the Apple News API given a post ID.
 *
 * @param int    $post_id   The post ID to modify.
 * @param string $operation The operation to perform. One of 'publish', 'update', 'delete'.
 *
 * @return array|WP_Error Response to the request - either data about a successful operation, or error.
 */
function modify_post( $post_id, $operation ) {

	// Ensure there is a post ID provided in the data.
	if ( empty( $post_id ) ) {
		return new WP_Error(
			'apple_news_no_post_id',
			__( 'No post ID was specified.', 'apple-news' ),
			[
				'status' => 400,
			]
		);
	}

	// Try to get the post by ID.
	$post = get_post( $post_id );
	if ( empty( $post ) ) {
		return new WP_Error(
			'apple_news_bad_post_id',
			__( 'No post was found with the given ID.', 'apple-news' ),
			[
				'status' => 404,
			]
		);
	}

	// Ensure the user is authorized to make changes to Apple News posts.
	if ( ! current_user_can(
		apply_filters(
			'apple_news_publish_capability',
			Apple_News::get_capability_for_post_type( 'publish_posts', $post->post_type )
		)
	) ) {
		return new WP_Error(
			'apple_news_failed_cap_check',
			__( 'Your user account is not permitted to modify posts on Apple News.', 'apple-news' ),
			[
				'status' => 401,
			]
		);
	}

	// Try to perform the action for the article against the API.
	switch ( $operation ) {
		case 'publish':
		case 'update':
			$action = new Push( Admin_Apple_News::$settings, $post_id );
			break;
		case 'delete':
			$action = new Delete( Admin_Apple_News::$settings, $post_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_delete
			break;
		default:
			return new WP_Error(
				'apple_news_bad_operation',
				__( 'You specified an invalid API operation.', 'apple-news' )
			);
	}
	try {
		$action->perform();

		// Set any additional notifications that might be required.
		$message = '';
		if ( 'yes' === Admin_Apple_News::$settings->api_async ) {
			$message = __( 'Your changes will be applied shortly.', 'apple-news' );
		} elseif ( 'delete' === $operation ) {
			$message = __( 'This article has been successfully deleted from Apple News.', 'apple-news' );
		}

		return [
			'message'      => $message,
			'publishState' => Admin_Apple_News::get_post_status( $post_id ),
		];
	} catch ( Action_Exception $e ) {
		// Return the error message in the JSON response also.
		return new WP_Error(
			'apple_news_operation_failed',
			$e->getMessage()
		);
	}
}
