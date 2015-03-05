<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'rtbQuery' ) ) {
/**
 * Class to handle common queries used to pull bookings from
 * the database.
 *
 * Bookings can be retrieved with specific date ranges, common
 * date params (today/upcoming), etc. This class is intended for
 * the base plugin as well as extensions or custom projects which
 * need a stable mechanism for reliably retrieving bookings data.
 *
 * Queries return an array of rtbBooking objects.
 *
 * @since 1.4.1
 */
class rtbQuery {

	/**
	 * Query args
	 *
	 * Passed to WP_Query
	 * http://codex.wordpress.org/Class_Reference/WP_Query
	 *
	 * @since 1.4.1
	 */
	public $args = array();

	/**
	 * Query context
	 *
	 * Defines the context in which the query is run.
	 * Useful for hooking into the right query without
	 * tampering with others.
	 *
	 * @since 1.4.1
	 */
	public $context;

	/**
	 * Instantiate the query with an array of arguments
	 *
	 * This supports all WP_Query args as well as several
	 * short-hand arguments for common needs. Short-hands
	 * include:
	 *
	 * schedule string today|upcoming
	 * start_date string don't get bookings before this
	 * end_date string don't get bookings after this
	 *
	 * @see rtbQuery::prepare_args()
	 * @param args array Options to tailor the query
	 * @param context string Context for the query, used
	 *		in filters
	 * @since 1.4.1
	 */
	public function __construct( $args = array(), $context = '' ) {

		global $rtb_controller;

		$defaults = array(
			'post_type'			=> RTB_BOOKING_POST_TYPE,
			'posts_per_page'	=> 10,
			'schedule'			=> 'upcoming',
			'post_status'		=> array_keys( $rtb_controller->cpts->booking_statuses ),
			'order'				=> 'ASC',
			'paged'				=> 1,
		);

		$this->args = wp_parse_args( $args, $defaults );

		$this->context = $context;

	}

	/**
	 * Parse the args array and convert custom arguments
	 * for use by WP_Query
	 *
	 * @since 1.4.1
	 */
	public function prepare_args() {

		$args = $this->args;

		if ( is_string( $args['schedule'] ) ) {

			if ( $args['schedule'] === 'today' ) {
				$today = getdate();
				$args['year'] = $today['year'];
				$args['monthnum'] = $today['mon'];
				$args['day'] = $today['mday'];

			} elseif ( $args['schedule'] === 'upcoming' ) {
				$args['date_query'] = array(
					array(
						'after' => '-1 hour', // show bookings that have just passed
					)
				);
			}
		}

		if ( !empty( $args['start_date'] ) || !empty( $args['end_date'] ) ) {

			$date_query = array();

			if ( !empty( $args['start_date'] ) ) {
				$date_query['after'] = sanitize_text_field( $args['start_date'] );
			}

			if ( !empty( $args['end_date'] ) ) {
				$date_query['before'] = sanitize_text_field( $args['end_date'] );
			}

			if ( count( $date_query ) ) {
				$args['date_query'] = $date_query;
			}
		}

		$this->args = $args;

		return $this->args;
	}

	/**
	 * Parse $_REQUEST args and store in $this->args
	 *
	 * @since 1.4.1
	 */
	public function parse_request_args() {

		$args = array();

		if ( !empty( $_REQUEST['paged'] ) ) {
			$args['paged'] = (int) $_REQUEST['paged'];
		}

		if ( !empty( $_REQUEST['status'] ) ) {
			$args['post_status'] = sanitize_key( $_REQUEST['status'] );
		}

		if ( !empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $_REQUEST['orderby'] );
		}

		if ( !empty( $_REQUEST['order'] ) && $_REQUEST['order'] === 'DESC' ) {
			$args['order'] = $_REQUEST['orderby'];
		}

		if ( !empty( $this->filter_start_date ) ) {
			$args['start_date'] = $this->filter_start_date;
		}

		if ( !empty( $this->filter_end_date ) ) {
			$args['end_date'] = $this->filter_end_date;
		}

		if ( !empty( $_REQUEST['schedule'] ) ) {
			$args['schedule'] = sanitize_key( $_REQUEST['schedule'] );
		}

		$this->args = array_merge( $this->args, $args );
	}

	/**
	 * Retrieve query results
	 *
	 * @since 1.4.1
	 */
	public function get_bookings() {

		$bookings = array();

		$args = apply_filters( 'rtb_query_args', $this->args, $this->context );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			require_once( RTB_PLUGIN_DIR . '/includes/Booking.class.php' );

			while( $query->have_posts() ) {
				$query->the_post();

				$booking = new rtbBooking();
				if ( $booking->load_post( $query->post ) ) {
					$bookings[] = $booking;
				}
			}
		}

		$this->bookings = $bookings;

		return $this->bookings;
	}

}
} // endif
