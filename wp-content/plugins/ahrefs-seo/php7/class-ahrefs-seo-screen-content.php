<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Content audit screen class.
 */
class Ahrefs_Seo_Screen_Content extends Ahrefs_Seo_Screen_With_Table {

	public function register_ajax_handlers() : void {
		add_action( 'wp_ajax_ahrefs_seo_content_details', [ $this, 'ajax_content_details' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_set_active', [ $this, 'ajax_content_set_active' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_bulk', [ $this, 'ajax_bulk_actions' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_ping', [ $this, 'ajax_content_ping' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_manual_update', [ $this, 'ajax_content_manual_update' ] );
		add_action( 'wp_ajax_ahrefs_content_set_keyword', [ $this, 'ajax_content_set_keyword' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_approve_keyword', [ $this, 'ajax_content_approve_keyword' ] );
		add_action( 'wp_ajax_ahrefs_content_get_keyword_popup', [ $this, 'ajax_content_get_keyword_popup' ] );
		add_action( 'wp_ajax_ahrefs_content_get_fresh_suggestions', [ $this, 'ajax_content_get_fresh_suggestions' ] );
		add_action( 'wp_ajax_ahrefs_seo_content_tip_close', [ $this, 'ajax_content_tip_close' ] );
		add_action( 'wp_ajax_ahrefs_seo_keyword_tip_close', [ $this, 'ajax_keyword_tip_close' ] );
		add_filter( 'heartbeat_received', [ $this, 'ajax_receive_heartbeat' ], 10, 2 );
		parent::register_ajax_handlers();
	}

	public function show() : void {
		// if audit paused, check, maybe some plugin already deactivated and we can run?
		if ( Content_Audit::audit_is_paused() && ( new Content_Audit() )->require_update() ) {
			Ahrefs_Seo_Compatibility::recheck_saved_incompatibility();
			if ( empty( Content_Audit::audit_get_paused_messages() ) ) { // no saved reasons exists.
				Content_Audit::audit_resume();
			}
		}
		$params = [
			'last_audit_stopped'   => Content_Audit::audit_get_paused_messages( true ), // Message[]|null type.
			'stop'                 => Ahrefs_Seo_Errors::check_stop_status( false ), // Message[]|null type.
			'header_class'         => [ 'content' ],
			'hide_compatibility'   => true,
			'no_ahrefs_account'    => Ahrefs_Seo_Api::get()->is_disconnected(),
			'ahrefs_limited'       => ! Ahrefs_Seo_Api::get()->is_disconnected() && Ahrefs_Seo_Api::get()->is_limited_account( true ),
			'no_google_account'    => ! Ahrefs_Seo_Analytics::get()->is_token_set() || ! Ahrefs_Seo_Analytics::get()->is_ua_set() || ! Ahrefs_Seo_Analytics::get()->is_gsc_set(),
			'not_suitable_account' => false === Ahrefs_Seo_Analytics::get()->is_gsc_account_correct() || false === Ahrefs_Seo_Analytics::get()->is_ga_account_correct(),
		];

		$last_audit_time           = Ahrefs_Seo_Data_Content::get()->get_last_audit_time();
		$params['last_audit_time'] = $last_audit_time;
		// last audit was never executed or run more that a month ago and no content audit is running.
		$params['show_last_audit_tip'] = ( is_null( $last_audit_time ) || absint( $last_audit_time - time() ) > MONTH_IN_SECONDS ) && is_null( ( new Snapshot() )->get_new_snapshot_id() );

		$this->view->show( 'content', 'Content Audit', $params, $this, 'content' );
	}

	/**
	 * Get prefix for ajax requests to tables
	 *
	 * @return string
	 */
	protected function get_ajax_table_prefix() : string {
		return 'ahrefs_seo_table_content';
	}

	/**
	 * Create new table
	 *
	 * @return Ahrefs_Seo_Table
	 */
	protected function new_table_instance() : Ahrefs_Seo_Table {
		return new Ahrefs_Seo_Table_Content();
	}

	/**
	 * Print navigation and placeholder for future table
	 *
	 * @return void
	 */
	public function show_table_placeholder() : void {
		$current_tab = isset( $_REQUEST['tab'] ) ? absint( $_REQUEST['tab'] ) : 1; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		if ( ! is_null( $this->table ) ) {
			?>
			<div class="wrap content-wrap">
				<form id="content_form" class="table-form wp-clearfix" method="get">
					<div>
						<?php
						$this->table->views();
						?>
						<div class="clear"></div>
					</div>

					<div class="table-wrap">
						<div id="table_loader" style="display: none;"><div class="loader"></div></div>
						<div id="content_table">
							<!-- place for table -->
						</div>
					</div>
					<?php
					wp_nonce_field( Ahrefs_Seo_Table::ACTION, 'table_nonce' );
					?>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Ajax handler for content of expanded view with recommended actions
	 *
	 * @return void
	 */
	public function ajax_content_details() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['id'] ) ) {
			$result = '';
			$post   = get_post( absint( $_REQUEST['id'] ) );
			if ( ! empty( $post ) && ( $post instanceof \WP_Post ) && 'publish' === $post->post_status ) {
				$result  = 'Will be soon.';
				$content = Ahrefs_Seo_Data_Content::get();
				$action  = $content->get_post_action( $post->ID );
				if ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING ], true ) ) {
					$action = Ahrefs_Seo_Data_Content::ACTION4_ANALYZING; // substitute correct template for initial actions.
				}
				ob_start();
				$this->view->show_part( 'actions/' . $action, [ 'post_id' => $post->ID ] );
				$result = ob_get_clean();
			} else {
				$result = 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.';
			}
			wp_send_json_success( $result );
		}
	}

	/**
	 * Handler for 'Run Content Audit again'.
	 * Created new snapshot if it was not exists.
	 *
	 * @return void
	 */
	public function ajax_content_manual_update() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			// check compatibility issues.
			$message = null;
			$tips    = [];
				Ahrefs_Seo_Api::get()->get_subscription_info(); // update Ahrefs account details (update 'is limited account' value).

			if ( ! Ahrefs_Seo_Errors::has_stop_error( true ) ) {
				$snapshots = new Snapshot();
				if ( is_null( $snapshots->get_new_snapshot_id() ) ) {
					$snapshots->create_new_snapshot();
					wp_send_json_success( [ 'ok' => true ] );
				}
			} else {
				// fill message with all stop errors.
				ob_start();
				Ahrefs_Seo_Errors::show_stop_errors( Ahrefs_Seo_Errors::check_stop_status( true ) );
				$tips['stop'] = ob_get_clean();
				wp_send_json_error( [ 'tips' => $tips ] );
			}
				ob_start();
				$this->view->show_part( 'content-tips/already-run' );
				$tips['stop'] = ob_get_clean();

			wp_send_json_error( [ 'tips' => $tips ] );
		}
	}

	/**
	 * Bulk actions handler.
	 *
	 * @return void
	 */
	public function ajax_bulk_actions() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['doaction'] ) && isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] ) ) {
			$doaction = sanitize_text_field( wp_unslash( $_REQUEST['doaction'] ) );
			$ids      = array_map( 'absint', $_REQUEST['ids'] );

			$this->bulk_actions( $doaction, $ids );
		}
	}

	/**
	 * Receive ping message with items shown at the current moment and their versions ('ver' field of query).
	 * Run update of all pending items.
	 * Check current version of items and return all updated items (rows) at the 'data.updated' field of json answer.
	 * If 'data.status' is true - should run next ajax request.
	 * If nothing updated, simply return json success.
	 */
	public function ajax_content_ping() : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ );
		Ahrefs_Seo::thread_id( 'ping' );

		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['items'] ) && ( is_array( $_REQUEST['items'] ) || 'false' === $_REQUEST['items'] ) ) {
			$fields                = [];
			$fields['timeout']     = 2 + ( intval( ini_get( 'max_execution_time' ) ) ?: 120 ); // set this value before update_table call (it can modify default value).
			$items_first_or_sub    = isset( $_POST['first_or_sub'] ) ? sanitize_text_field( wp_unslash( $_POST['first_or_sub'] ) ) : ''; // what tip is displayed in first-or-subsequent block.
			$stop                  = isset( $_POST['stop'] ) ? sanitize_text_field( wp_unslash( $_POST['stop'] ) ) : ''; // what tip is displayed in stop block.
			$content               = Ahrefs_Seo_Data_Content::get();
			$content_audit         = new Content_Audit();
			$content_audit_current = new Content_Audit_Current();

			if ( ! empty( $_POST['unpause_audit'] ) ) { // try to resume audit.
				Content_Audit::audit_resume();
			}

			if ( ! Content_Audit::audit_is_paused() ) { // if not paused.
				// 1. Do update if we have any pending items.
				// return value is true if we updated something and can run next request from JS.
				$something_updated = $content_audit_current->maybe_update();
				$waiting_time      = $content_audit_current->get_waiting_time(); // from current snapshot.
				if ( ! $something_updated ) {
					$something_updated = $content_audit->update_table();
					$waiting_time_new  = $content_audit->get_waiting_time(); // from new snapshot.
					if ( ! is_null( $waiting_time_new ) && ( is_null( $waiting_time ) || ( $waiting_time_new > $waiting_time ) ) ) {
						$waiting_time = $waiting_time_new;
					}
				}
				$fields['paused'] = Content_Audit::audit_is_paused(); // update after audit executed.
			} else {
				$something_updated = true;
				$waiting_time      = 2 * MINUTE_IN_SECONDS;
				$fields['paused']  = true;
			}

			$fields['delayed']      = $fields['paused'] ? false : Content_Audit::audit_is_delayed(); // do not show "audit delayed" message when audit is paused.
			$fields['new-request']  = $something_updated;
			$fields['audit']        = Ahrefs_Seo_Data_Content::get()->get_statictics();
			$fields['waiting_time'] = ! is_null( $waiting_time ) ? round( $waiting_time, 1 ) : null; // All active workers are paused during this amount of time.

			$fields['tips'] = $this->prepare_tips( $items_first_or_sub, $stop, empty( $_POST['unpause_audit'] ) );
			// 2. Return updated items.
			$sanitized_items = [];
			if ( is_array( $_REQUEST['items'] ) ) {
				foreach ( $_REQUEST['items'] as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we are sanitizing both key and value one line below as integers.
					$sanitized_items[ intval( $key ) ] = intval( $value );
				}
			}

			$updated_ids = $sanitized_items ? $content->get_updated_items( $sanitized_items ) : [];
			$updated     = [];

			$charts = Ahrefs_Seo_Charts::maybe_return_charts();
			if ( isset( $_REQUEST['estimate'] ) ) {
				$fields['estimate'] = Ahrefs_Seo_Data_Content::get()->get_estimate_rows();
			}

			if ( count( $updated_ids ) || ! empty( $charts ) ) {
				// this will return ajax response and terminate.
				if ( ! empty( $charts ) ) {
					$fields['charts'] = $charts;
				}
				$this->initialize_table()->ajax_response_updated( $updated_ids, $fields );
			}

			// Nothing to update: send default ajax response with success and maybe messages.
			wp_send_json_success( $fields );
		}
	}

	/**
	 * Start / stop post analyzing handler.
	 *
	 * @return void
	 */
	public function ajax_content_set_active() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['id'] ) && isset( $_REQUEST['active'] ) ) {
			$success  = true;
			$result   = 'Done.';
			$messages = [];
			$errors   = [];
			$post     = get_post( absint( $_REQUEST['id'] ) );
			if ( ! empty( $post ) && ( $post instanceof \WP_Post ) && 'publish' === $post->post_status ) {
				$result  = 'Will be soon.';
				$content = Ahrefs_Seo_Data_Content::get();
				if ( isset( $_REQUEST['active'] ) && '' !== $_REQUEST['active'] ) {
					if ( ! empty( $_REQUEST['active'] ) ) {
						if ( 0 === count( $content->posts_include( [ $post->ID ] ) ) ) {
							$result = 'Post is included in the audit.';
						} else {
							$errors[] = 'This post is already included in the audit.';
							$success  = false;
						}
					} else {
						if ( 0 === count( $content->posts_exclude( [ $post->ID ] ) ) ) {
							$result = 'Post is excluded from the audit.';
						} else {
							$errors[] = 'This post is already excluded from the audit.';
							$success  = false;
						}
					}
				}
			} else {
				$errors[] = 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.';
				$success  = false;
			}

			$fields = [];
			$events = Ahrefs_Seo_Errors::get_current_messages();  // get error from current actions only.
			foreach ( $events as $event ) {
				/** @var array<string,string> $event We do not use 'buttons' property there, that has type string[]. */
				$errors[] = Ahrefs_Seo_Errors::get_title_for_source( $event['source'] ) . ': ' . $event['message'];
			}
			$messages = Ahrefs_Seo_Analytics::get()->get_message(); // get error from current actions only.
			if ( ! empty( $messages ) ) {
				$errors['Google API error'] = $messages;
			}
			$fields['message'] = $result;
			if ( $errors ) {
				$success            = false;
				$fields['messages'] = $errors;
			}

			if ( $success ) {
				wp_send_json_success( $fields );
			} else {
				wp_send_json_error( $fields );
			}
		}
	}

	/**
	 * Handler for 'Select keyword' buttons and manual keyword field.
	 * Save keyword for post.
	 *
	 * @return void
	 */
	public function ajax_content_set_keyword() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_POST['post'] ) && isset( $_POST['keyword'] ) ) {
			$keyword        = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
			$keyword_manual = isset( $_POST['keyword_manual'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword_manual'] ) ) : null;
			$not_approved   = ! empty( $_POST['not_approved'] );

			$result = Ahrefs_Seo_Keywords::get()->post_keywords_set( Ahrefs_Seo_Data_Content::snapshot_context_get(), absint( $_POST['post'] ), $keyword, $keyword_manual, true );
			if ( $result ) {
				// approve keyword.
				if ( $not_approved && '' !== $keyword ) {
					( new Snapshot() )->analysis_approve_items( [ intval( $_POST['post'] ) ] );
				}
				wp_send_json_success();
			} else {
				wp_send_json_error( [ 'error' => 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.' ] );
			}
		}
	}

	/**
	 * Handler for 'Approve keyword' link.
	 * Approve keyword for post.
	 *
	 * @return void
	 */
	public function ajax_content_approve_keyword() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_POST['post'] ) ) {
			// approve keyword.
			( new Snapshot() )->analysis_approve_items( [ intval( $_POST['post'] ) ] );
			wp_send_json_success();
		}
	}

	/**
	 * Handler for 'Change keywords' buttons.
	 * Show html content of popup dialog.
	 *
	 * @return void
	 */
	public function ajax_content_get_keyword_popup() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['post'] ) ) {
			$time = microtime( true );
			$this->view->show_part( 'popups/keywords-list', [ 'post_id' => absint( $_REQUEST['post'] ) ] );
			Ahrefs_Seo::breadcrumbs( sprintf( 'Keywords for post %d found in %1.3f sec.', absint( $_REQUEST['post'] ), microtime( true ) - $time ) );
			exit;
		}
	}

	/**
	 * Handler for get fresh keywords suggestion, when Keywords popup opened.
	 * Return json content with received from API fresh data.
	 *
	 * @return void
	 */
	public function ajax_content_get_fresh_suggestions() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && isset( $_REQUEST['post'] ) ) {
			$time = microtime( true );
			Ahrefs_Seo::breadcrumbs( __METHOD__ );
			$data = Ahrefs_Seo_Keywords::get()->get_suggestions( Ahrefs_Seo_Data_Content::snapshot_context_get(), absint( $_REQUEST['post'] ), false );
			Ahrefs_Seo::breadcrumbs( sprintf( 'Fresh keywords for post %d found in %1.3f sec.', absint( $_REQUEST['post'] ), microtime( true ) - $time ) );
			wp_send_json_success( $data );
		}
	}

	/**
	 * Handler for content audit suggested keywords tip.
	 * Called on close.
	 *
	 * @return void
	 */
	public function ajax_content_tip_close() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			( new Content_Tips_Content() )->on_closed_by_user();
			wp_send_json_success();
		}
	}

	/**
	 * Handler for content audit suggested keywords tip.
	 * Called on close.
	 *
	 * @return void
	 */
	public function ajax_keyword_tip_close() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			( new Content_Tips_Popup() )->on_closed_by_user();
			wp_send_json_success();
		}
	}

	/**
	 * Do a bulk action and terminate execution
	 *
	 * @param string $doaction Action.
	 * @param int[]  $ids Post ids array.
	 * @return void Print JSON answer and terminate execution.
	 */
	public function bulk_actions( string $doaction, array $ids ) : void {
		$sendback = [];
		switch ( $doaction ) {
			case 'trash':
				$trashed = 0;
				$locked  = 0;
				foreach ( (array) $ids as $post_id ) {
					if ( ! current_user_can( 'delete_post', $post_id ) ) {
						wp_send_json_error( __( 'Sorry, you are not allowed to move this item to the Trash.' ) );
					}

					if ( function_exists( 'wp_check_post_lock ' ) && wp_check_post_lock( $post_id ) ) {
						$locked++;
						continue;
					}

					if ( ! wp_trash_post( $post_id ) ) {
						wp_send_json_error( __( 'Error in moving to Trash.' ) );
					}

					$trashed++;
				}

				$sendback = [
					'trashed' => $trashed,
					'ids'     => join( ',', $ids ),
					'locked'  => $locked,
				];
				break;
			case 'untrash':
				$untrashed = 0;
				foreach ( (array) $ids as $post_id ) {
					if ( ! current_user_can( 'delete_post', $post_id ) ) {
						wp_send_json_error( __( 'Sorry, you are not allowed to restore this item from the Trash.' ) );
					}

					if ( ! wp_untrash_post( $post_id ) ) {
						wp_send_json_error( __( 'Error in restoring from Trash.' ) );
					}

					$untrashed++;
				}
				$sendback = [ 'untrashed' => $untrashed ];
				break;
			case 'start':
				$results = [];
				$ids     = array_map( 'intval', $ids );
				$content = Ahrefs_Seo_Data_Content::get();
				$results = $content->posts_include( $ids );

				if ( $results ) {
					$results             = array_map(
						function( $post_id ) {
							$result = get_the_title( (int) $post_id );
							return $result ? $result : 'Post #' . $post_id;
						},
						$results
					);
					$sendback['message'] = sprintf( _n( 'This post is already included in the audit: %2$s', 'These %1$d posts are already included in the audit: %2$s.', count( $results ) ), count( $results ), implode( ', ', $results ) );
				}
				break;
			case 'stop':
				$results = [];
				$ids     = array_map( 'intval', $ids );
				$content = Ahrefs_Seo_Data_Content::get();
				$results = $content->posts_exclude( $ids );

				if ( $results ) {
					$results             = array_map(
						function( $post_id ) {
							$result = get_the_title( (int) $post_id );
							return $result ? $result : 'Post #' . $post_id;
						},
						$results
					);
					$sendback['message'] = sprintf( _n( 'This post is already excluded from the audit: %2$s', 'These %1$d posts are already excluded from the audit: %2$s.', count( $results ) ), count( $results ), implode( ', ', $results ) );
				}
				break;
			case 'approve':
				$results = [];
				( new Snapshot() )->analysis_approve_items( $ids );

				break;
			default:
				wp_send_json_error( __( 'Unknown action.' ) );
				break;
		}

		wp_send_json_success( $sendback );
	}

	/**
	 * Return status if content audit update run required.
	 *
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function ajax_receive_heartbeat( $response, $data ) {
		// Callback, do not use data types.
		if ( ! empty( $data['ahrefs_seo_content'] ) ) {
			$need_update                    = ( new Content_Audit_Current() )->maybe_update() || ( new Content_Audit() )->require_update();
			$response['ahrefs_seo_content'] = compact( 'need_update' );
		}
		return (array) $response;
	}

	/**
	 * Fill all tips.
	 *
	 * @since 0.7.5
	 *
	 * @param string $first_or_sub What tip is displayed in first-or-subsequent message block.
	 * @param string $stop_displayed Tips already displayed in stop block.
	 * @param bool   $hide_compatibility Do not show compatibility tip.
	 * @return array<string,string|null> Null if nothing updated, string with html code otherwise.
	 */
	protected function prepare_tips( string $first_or_sub, string $stop_displayed = '', bool $hide_compatibility = false ) : array {
		$result = [];

		// show stop messages at the first: other messages are filtered by compatibility text.
		ob_start();
		// show compatibility message, if audit stopped (paused) because of it.
		$saved               = Content_Audit::audit_get_paused_messages( true ) ?? [];
		$status              = Ahrefs_Seo_Errors::check_stop_status( false ) ?? [];
		$need_to_clean_block = Ahrefs_Seo_Errors::show_stop_errors( array_merge( $saved, $status ), $stop_displayed );
		$result['stop']      = (string) ob_get_clean();
		if ( '' === $result['stop'] && ! $need_to_clean_block ) {
			$result['stop'] = null; // no need to clean already displayed messages.
		}

		// audit-tip.
		ob_start();
		$tips = array_map( [ Message::class, 'create' ], Ahrefs_Seo_Errors::get_saved_messages( null, 'tip' ) ?? [] );
		array_walk(
			$tips,
			function( Message $message ) {
				$message->show();
			}
		);
		$result['audit-tip'] = (string) ob_get_clean();

		// errors: api-messages.
		ob_start();
		$this->view->show_part( 'notices/api-messages' );
		$messages_html = (string) ob_get_clean();

		if ( '' !== $messages_html ) {
			$result['api-messages'] = $messages_html;
		}
		// notices: api-delayed.
		$result['api-delayed'] = ''; // clean block by default.
		if ( ( new Content_Audit() )->has_unprocessed_items() ) {
			$notices = array_merge( Ahrefs_Seo_Errors::get_saved_messages( null, 'notice' ), Ahrefs_Seo_Errors::get_saved_messages( null, 'error-single' ) );
			if ( $notices ) {
				ob_start();
				$ids = [];
				foreach ( $notices as $notice ) {
					$message = Message::create( $notice );
					if ( ! in_array( $message->get_id(), $ids, true ) ) { // do not show duplicated messages.
						$message->show();
						$ids[] = $message->get_id();
					}
				}
				unset( $ids );
				$result['api-delayed'] .= (string) ob_get_clean();
			}
		}

		// first-or-subsequent.
		$result['first-or-subsequent'] = ( new Content_Tips_Content() )->maybe_return_tip( $first_or_sub );

		return $result;
	}

}
