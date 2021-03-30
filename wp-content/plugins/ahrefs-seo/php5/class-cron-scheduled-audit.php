<?php

namespace ahrefs\AhrefsSeo;

/**
 * Class for scheduled audit feature.
 *
 * Start, stop and change recurrence of scheduled audit.
 * - We schedule/remove task on Cron_Scheduled_Audit::update_cron() call.
 * - We update/remove cron job on plugin activation/deactivation.
 * On each scheduled audit start:
 * - create new snapshot (note: can not create new snapshot if another new snapshot already exists).
 * - run content updates using Cron Content. Call it in the case, that current execution finished before end.
 * - reschedule next own run, if required by options.
 * - do updates.
 */
class Cron_Scheduled_Audit extends Cron_Any {

	protected $event_name     = 'ahrefs_seo_cron_scheduled_audit';
	protected $transient_name = 'ahrefs-cron-running-scheduled_audit';
	/**
	 * @var Content_Schedule
	 */
	protected $content_schedule;
	public function __construct() {
		parent::__construct();
		$this->content_schedule = new Content_Schedule();
	}
	/**
	 * Add custom schedule interval for Internal links updates.
	 *
	 * @param array<string, array<string, int|string>> $schedules
	 * @return array<string, array<string, int|string>>
	 */
	public function cron_schedules_add_interval( $schedules ) {
		// Note: callback, do not use parameter types.
		if ( ! is_array( $schedules ) ) {
			$schedules = [];
		}
		if ( ! isset( $schedules['ahrefs_daily'] ) ) {
			$schedules['ahrefs_daily'] = [
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Content audit: daily' ),
			];
		}
		if ( ! isset( $schedules['ahrefs_weekly'] ) ) {
			$schedules['ahrefs_weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Content audit: weekly' ),
			];
		}
		if ( ! isset( $schedules['ahrefs_montly'] ) ) {
			$schedules['ahrefs_monthly'] = [
				'interval' => MONTH_IN_SECONDS,
				'display'  => __( 'Content audit: monthly' ),
			];
		}
		return $schedules;
	}
	/**
	 * Execute an update.
	 *
	 * @return bool True if has more tasks, false if everything finihed.
	 */
	public function run_task() {
		Ahrefs_Seo::thread_id( 'scheduled' );
		$this->apply_time_limits();
		Ahrefs_Seo::breadcrumbs( __METHOD__ . sprintf( ' Transient time: %d', Ahrefs_Seo::transient_time() ) );
		if ( ! get_transient( $this->transient_name ) ) {
			$transient_time = Ahrefs_Seo::transient_time();
			set_transient( $this->transient_name, true, $transient_time );
			Ahrefs_Seo::ignore_user_abort( true );
			// check that all required API connected.
			$messages = ! empty( Ahrefs_Seo_Errors::check_stop_status( true ) ) ? Ahrefs_Seo_Errors::check_stop_status( true ) : [];
			if ( 0 === count( $messages ) ) {
				// 1. create new snapshot.
				// Note: new snapshot is not created, if it already exists.
				( new Snapshot() )->create_new_snapshot( true );
				// 2. run content updates using Cron Content. Call it in the case, that current execution finished before end.
				( new Cron_Content_Fast() )->start_tasks( true );
			} else {
				Content_Audit::audit_stop( $messages, true );
			}
			// 3. reschedule next own run.
			$next = $this->content_schedule->next_run_time();
			if ( ! $this->content_schedule->is_enabled() ) {
				$this->stop_tasks();
			} elseif ( $next['reschedule'] ) {
				$this->start_tasks();
			}
			if ( 0 === count( $messages ) ) {
				// 4. do updates.
				$content_audit = new Content_Audit();
				while ( $content_audit->require_update() ) {
					set_transient( $this->transient_name, true, $transient_time ); // refresh transient.
					if ( Ahrefs_Seo::should_finish( 10 ) ) {
						Ahrefs_Seo::breadcrumbs( __METHOD__ . ' exit before all finished' );
						break;
					}
					$content_audit->update_table( true );
					sleep( 150 );
				}
			}
			delete_transient( $this->transient_name );
		}
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ' exit.' );
		return false;
	}
	/**
	 * Has more tasks, but need to switch to slow mode.
	 *
	 * @return bool True if has incompleted tasks.
	 */
	public function has_slow_tasks() {
		return true;
	}
	/**
	 * Start task: schedule next content audit run using option
	 *
	 * @param bool $unused
	 * @return void
	 */
	public function start_tasks( $unused = true ) {
		$next = $this->content_schedule->next_run_time();
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ' next:' . wp_json_encode( $next ) );
		if ( wp_next_scheduled( $this->event_name ) ) {
			wp_clear_scheduled_hook( $this->event_name );
		}
		if ( false === wp_schedule_event( $next['time'], $next['frequency'], $this->event_name ) ) {
			Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( 'Cron schedule failed ' . wp_json_encode( [ $next['time'], $next['frequency'], $this->event_name, time() ] ) ) );
		}
	}
	/**
	 * Required method. Do nothing, as everything executed in run_task().
	 *
	 * @return bool
	 */
	public function execute() {
		return false;
	}
	/**
	 * Return next task time from cron job settings.
	 *
	 * @return int|null Timestamp or null if task is not scheduled.
	 */
	public function next_task_time() {
		$result = wp_next_scheduled( $this->event_name );
		return ! empty( $result ) ? $result : null;
	}
	/**
	 * Update or remove cron task using current settings.
	 *
	 * @return void
	 */
	public function update_cron() {
		if ( $this->content_schedule->is_enabled() ) {
			$this->start_tasks();
		} else {
			$this->stop_tasks();
		}
	}
}
