<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

/**
 * Icompatible tip message
 *
 * @since 0.7.5
 */
class Message_Tip_Incompatible extends Message_Tip {

	protected const TEMPLATE = 'tip-incompatible';

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
		$this->plugins = $message_fields['plugins'] ?? [];
		$this->themes  = $message_fields['themes'] ?? [];
	}

	/**
	 * Return fields of message.
	 *
	 * @return array<string, string|string[]|bool>
	 */
	protected function get_fields() : array {
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
	public function show() : void {
		parent::show();
		Ahrefs_Seo_Compatibility::set_message_displayed( $this->message ); // important: set message displayed.
	}

	public function get_plugins() : array {
		return $this->plugins ?? [];
	}

	public function get_themes() : array {
		return $this->themes ?? [];
	}
}
