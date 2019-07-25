<?php
/**
 * A custom endpoint for updating a post on Apple News.
 *
 * @package Apple_News
 */
namespace Apple_News\REST;

use \Admin_Apple_News;
use \Admin_Apple_Notice;
use \Apple_Actions\Action_Exception;
use \Apple_Actions\Index\Push;
use \Apple_News;
use \WP_Error;
use \WP_REST_Request;

/**
 * Handle a REST POST request to the /apple-news/v1/update endpoint.
 *
 * @param WP_REST_Request $data Data from query args.
 *
 * @return array|WP_Error Response to the request - either data about a successfully updated article, or error.
 */
function rest_post_update( $data ) {

	// Ensure there is a post ID provided in the data.
	$id = $data->get_param( 'id' );
	if ( empty( $id ) ) {
		return new WP_Error(
			'apple_news_no_post_id',
			esc_html__( 'No post ID was specified.', 'apple-news' ),
			[
				'status' => 400,
			]
		);
	}

	// Try to get the post by ID.
	$post = get_post( $id );
	if ( empty( $post ) ) {
		return new WP_Error(
			'apple_news_bad_post_id',
			esc_html__( 'No post was found with the given ID.', 'apple-news' ),
			[
				'status' => 404,
			]
		);
	}

	// Ensure the user is authorized to make changes to Apple News posts.
	if ( ! current_user_can( apply_filters( 'apple_news_publish_capability', Apple_News::get_capability_for_post_type( 'publish_posts', $post->post_type ) ) ) ) {
		return new WP_Error(
			'apple_news_failed_cap_check',
			esc_html__( 'Your user account is not permitted to update this post on Apple News.', 'apple-news' ),
			[
				'status' => 401,
			]
		);
	}

	// Try to update the article via the API.
	$action = new Push( Admin_Apple_News::$settings, $id );
	try {
		$action->perform();

		// Negotiate the message based on whether update will happen asynchronously or not.
		if ( 'yes' === Admin_Apple_News::$settings->api_async ) {
			Admin_Apple_Notice::success( __( 'Your article will be updated shortly.', 'apple-news' ) );
		}

		return [
			'apiId'        => get_post_meta( $id, 'apple_news_api_id', true ),
			'dateCreated'  => get_post_meta( $id, 'apple_news_api_created_at', true ),
			'dateModified' => get_post_meta( $id, 'apple_news_api_modified_at', true ),
			'publishState' => Admin_Apple_News::get_post_status( $id ),
			'revision'     => get_post_meta( $id, 'apple_news_api_revision', true ),
			'shareUrl'     => get_post_meta( $id, 'apple_news_api_share_url', true ),
		];
	} catch ( Action_Exception $e ) {
		// Add the error message to the list of messages to display to the user using normal means.
		Admin_Apple_Notice::error( $e->getMessage() );

		// Return the error message in the JSON response also.
		return new WP_Error(
			'apple_news_update_failed',
			$e->getMessage()
		);
	}
}
/**
 * Initialize this REST Endpoint.
 */
add_action(
	'rest_api_init',
	function () {
		// Register route count argument.
		register_rest_route(
			'apple-news/v1',
			'/update',
			[
				'methods'  => 'POST',
				'callback' => __NAMESPACE__ . '\rest_post_update',
			]
		);
	}
);