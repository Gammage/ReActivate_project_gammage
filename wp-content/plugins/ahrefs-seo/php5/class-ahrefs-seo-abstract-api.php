<?php

namespace ahrefs\AhrefsSeo;

/**
 * Class for work APIs together with workers.
 *
 * @since 0.7.3
 */
class Ahrefs_Seo_Abstract_Api {

	/**
	 * Active worker, used to send errors back to caller.
	 *
	 * @var Worker_Any|null
	 */
	protected $active_worker;
	/**
	 * Called on any error from API request received.
	 * Report this error to active worker.
	 *
	 * @since 0.7.3
	 *
	 * @param \Throwable          $e
	 * @param string[]|int[]|null $source_list List of slugs, urls or post id.
	 * @return void
	 */
	public function on_error_received( $e, array $source_list = null ) {
		if ( ! is_null( $this->active_worker ) ) {
			$this->active_worker->on_rate_error( $e, $source_list );
		}
		Ahrefs_Seo::notify( $e );
	}
	/**
	 * Report error to workers.
	 *
	 * @since 0.7.3
	 *
	 * @param Worker_Any|null $worker
	 * @return void
	 */
	public function set_worker( Worker_Any $worker = null ) {
		$this->active_worker = $worker;
	}
}
