<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for content audit for any snapshot.
 */
class Content_Audit_Current extends Content_Audit {

	/**
	 * Constructor
	 *
	 * @param int|null $snapshot_id Snapshot ID to bind the instance. Default is 'current' snapshot.
	 */
	public function __construct( ?int $snapshot_id = null ) {
		parent::__construct();
		if ( is_null( $snapshot_id ) ) {
			// snapshot of current view.
			$snapshot_id = Ahrefs_Seo_Data_Content::get()->snapshot_context_get();
		}
		$this->snapshot_id = $snapshot_id;
	}

	/**
	 * Update items, that require update.
	 * Ignore if the snapshot is new.
	 *
	 * @param bool $run_from_cron
	 * @return bool Was something updated.
	 */
	public function maybe_update( bool $run_from_cron = false ) : bool {
		// current snapshot <> new & require update.
		if ( is_null( $this->snapshot_id ) || $this->snapshot->get_new_snapshot_id() === $this->snapshot_id || ! $this->snapshot->is_require_update( $this->snapshot_id ) ) {
			return false;
		}
		return $this->update_table( $run_from_cron );
	}

}
