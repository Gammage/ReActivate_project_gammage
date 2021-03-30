<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for fast content audit updates.
 *
 * Start, stop and change recurrence:
 * - We schedule task on Ahrefs_Seo_Cron::get()->start_tasks_content() call.
 * - We change task recurrence from fast to slow when no task executed(Ahrefs_Seo_Data_Content::update_table()), but we have pending tasks (Ahrefs_Seo_Data_Content::has_unprocessed_items())
 *   (mean: some post is locked so we can't update it).
 * - We stop sheduled task when all tasks finished (Ahrefs_Seo_Data_Content::has_unprocessed_items()).
 */
class Cron_Content_Fast extends Cron_Any {

	protected $event_name     = 'ahrefs_seo_cron_content';
	protected $transient_name = 'ahrefs-cron-running-content';

	/**
	 * Execute an update.
	 *
	 * @return bool True if has more tasks, false if everything finihed.
	 */
	public function execute() : bool {
		Ahrefs_Seo::thread_id( 'fast' );
		return ( new Content_Audit_Current() )->maybe_update( true ) || ( new Content_Audit() )->update_table( true );
	}

	/**
	 * Has more tasks, but need to switch to slow mode.
	 *
	 * @return bool True if has incompleted tasks.
	 */
	public function has_slow_tasks() : bool {
		return ( new Content_Audit() )->require_update();
	}

}
