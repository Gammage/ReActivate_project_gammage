<?php

namespace ahrefs\AhrefsSeo;

/**
 * Logger class for Google API error logging.
 * Used for google apiclient library v2.
 *
 * @since 0.7.1
 */
class Logger extends \Psr\Log\AbstractLogger {

	/**
	 * @var array<array>
	 */
	protected $events = [];
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed   $level
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		// Note: can not use parameter types here.
		$this->events[] = compact( 'level', 'message', 'context' );
	}
	/**
	 * Return events details. Clean events log.
	 *
	 * @return array<array>
	 */
	public function get_events() {
		$data         = $this->events;
		$this->events = [];
		return $data;
	}
}
