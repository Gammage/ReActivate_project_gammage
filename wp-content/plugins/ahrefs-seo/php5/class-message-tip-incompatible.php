<?php

namespace ahrefs\AhrefsSeo;

/**
 * Icompatible tip message
 *
 * @since 0.7.5
 */
class Message_Tip_Incompatible extends Message_Tip {

	const TEMPLATE = 'tip-incompatible';
	/** @var string[] */
	protected $plugins = [];
	/** @var string[] */
	protected $themes = [];
	/**
	 * Create message from fields.
	 *
	 * @param array $message_fields
	 */
	public function __construct( array $message_fields ) {
		parent::__construct( $message_fields );
		$this->type = 'tip-compatibility'; // overwrite type.
		// what was a reason of incompability.
		$this->plugins = isset( $message_fields['plugins'] ) ? $message_fields['plugins'] : [];
		$this->themes  = isset( $message_fields['themes'] ) ? $message_fields['themes'] : [];
	}
	/**
	 * Return fields of message.
	 *
	 * @return array<string, string|string[]|bool>
	 */
	protected function get_fields() {
		$result            = parent::get_fields();
		$result['plugins'] = $this->plugins;
		$result['themes']  = $this->themes;
		return $result;
	}
	/**
	 * Show template with message
	 *
	 * @return void
	 */
	public function show() {
		parent::show();
		Ahrefs_Seo_Compatibility::set_message_displayed( $this->message ); // important: set message displayed.
	}
	public function get_plugins() {
		return isset( $this->plugins ) ? $this->plugins : [];
	}
	public function get_themes() {
		return isset( $this->themes ) ? $this->themes : [];
	}
}
