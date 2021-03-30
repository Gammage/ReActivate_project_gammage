<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Abstract class for cron updates.
 */
abstract class Cron_Any {

	/**
	 * @var int
	 */
	protected $recurrence_fast = 3; // in minutes.
	/**
	 * @var int
	 */
	protected $recurrence_slow = 5; // in minutes.
	/**
	 * Predefined name of event name.
	 * Must be filled in child classes.
	 *
	 * @var string
	 */
	protected $event_name = '';
	/**
	 * Predefined name of transient.
	 * Must be filled in child classes.
	 *
	 * @var string
	 */
	protected $transient_name = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', [ $this, 'cron_schedules_add_interval' ] ); // phpcs:ignore WordPress.VIP.CronInterval.ChangeDetected,WordPress.WP.CronInterval.ChangeDetected
		add_action( $this->event_name, [ $this, 'run_task' ] );
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
		if ( ! isset( $schedules['ahrefs_fast'] ) ) {
			$schedules['ahrefs_fast'] = [
				'interval' => $this->recurrence_fast * MINUTE_IN_SECONDS,
				'display'  => __( 'Internal links: fast update' ),
			];
		}
		if ( ! isset( $schedules['ahrefs_slow'] ) ) {
			$schedules['ahrefs_slow'] = [
				'interval' => $this->recurrence_slow * MINUTE_IN_SECONDS,
				'display'  => __( 'Internal links: slow update' ),
			];
		}
		return $schedules;
	}

	/**
	 * Start links fast or slow updates or change scheduled recurrence/next time.
	 *
	 * @param bool $fast_updates
	 * @return void
	 */
	public function start_tasks( bool $fast_updates = true ) : void {
		Ahrefs_Seo::breadcrumbs( get_called_class() . '::' . __FUNCTION__ . wp_json_encode( func_get_args() ) );
		$recurrence = $fast_updates ? 'ahrefs_fast' : 'ahrefs_slow';
		$next_time  = wp_next_scheduled( $this->event_name );
		if ( ! $next_time ) {
			// once per 5 or 15 minutes, nearest call in 15 seconds.
			wp_schedule_event( time() + 15, $recurrence, $this->event_name );
		} else {
			$existing = wp_get_schedule( $this->event_name );
			// update event if recurrence is different from existing or scheduled call is after longest wait time for fast update.
			$desired_time = time() + $this->recurrence_fast * MINUTE_IN_SECONDS;
			if ( $existing !== $recurrence || $next_time > $desired_time ) {
				wp_schedule_event( $desired_time, $recurrence, $this->event_name );
			}
		}
	}

	/**
	 * Stop tasks, remove scheduled event
	 *
	 * @return void
	 */
	public function stop_tasks() : void {
		Ahrefs_Seo::breadcrumbs( get_called_class() . '::' . __FUNCTION__ );
		if ( wp_next_scheduled( $this->event_name ) ) {
			wp_clear_scheduled_hook( $this->event_name );
		}
	}

	/**
	 * Apply time limits
	 *
	 * @since 0.7.3
	 *
	 * @return void
	 */
	protected function apply_time_limits() : void {
		Ahrefs_Seo::set_time_limit( 300 ); // call it before set transient, because it can update transient time.
	}

	/**
	 * Run the task
	 *
	 * @return bool
	 */
	public function run_task() : bool {
		$this->apply_time_limits();
		Ahrefs_Seo::breadcrumbs( get_called_class() . '::' . __FUNCTION__ . sprintf( ' Transient time: %d', Ahrefs_Seo::transient_time() ) );
		if ( ! get_transient( $this->transient_name ) ) {
			set_transient( $this->transient_name, true, Ahrefs_Seo::transient_time() );
			Ahrefs_Seo::ignore_user_abort( true );

			// run until finished or time limit reached.
			while ( $executed = $this->execute() ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				if ( Ahrefs_Seo::should_finish( null, 33 ) ) { // allow 2/3 of all time to update internal links.
					Ahrefs_Seo::breadcrumbs( get_called_class() . '::' . __FUNCTION__ . ' exit earlier.' );
					break;
				}
			}
			if ( ! $executed ) {
				// Nothing to update now, but there may be tasks, blocked by something.
				if ( $this->has_slow_tasks() ) {
					$this->start_tasks( false ); // switch to slow update mode.
				} else {
					$this->stop_tasks(); // all finished: stop cron task.
				}
			}

			delete_transient( $this->transient_name );
		}
		Ahrefs_Seo::breadcrumbs( get_called_class() . '::' . __FUNCTION__ . ' exit.' );
		return false;
	}

	/**
	 * Execute an update.
	 *
	 * @return bool True if task finished, false if nothing to run.
	 */
	abstract public function execute() : bool;

	/**
	 * Has more tasks, but need to switch to slow mode.
	 *
	 * @return bool True if has incompleted tasks.
	 */
	abstract public function has_slow_tasks() : bool;
}
