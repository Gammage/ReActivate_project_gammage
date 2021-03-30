<?php

namespace ahrefs\AhrefsSeo;

/**
 * Base class for all messages: error, notice, tip.
 *
 * @since 0.7.5
 */
abstract class Message {

	const TEMPLATES_BASE = 'dynamic/';
	const TEMPLATE       = 'error';
	/** @var string $id */
	protected $id;
	/** @var string $type */
	protected $type;
	/**  @var string $title */
	protected $title;
	/**  @var string $message */
	protected $message;
	/**  @var string[] $buttons */
	protected $buttons;
	/**  @var string[] $classes */
	protected $classes;
	/**
	 * Create message from fields.
	 *
	 * @param array $message_fields
	 */
	public function __construct( array $message_fields ) {
		$this->type    = isset( $message_fields['type'] ) ? $message_fields['type'] : 'error';
		$this->classes = [];
		if ( isset( $message_fields['classes'] ) ) {
			$this->classes = is_array( $message_fields['classes'] ) ? $message_fields['classes'] : ( is_string( $message_fields['classes'] ) ? [ $message_fields['classes'] ] : [] );
		}
		if ( isset( $message_fields['title'] ) ) {
			$this->title = $message_fields['title'];
		} elseif ( isset( $message_fields['source'] ) ) {
			$this->title = Ahrefs_Seo_Errors::get_title_for_source( $message_fields['source'] );
		}
		$this->message = isset( $message_fields['message'] ) ? $message_fields['message'] : '';
		$this->id      = md5( $this->message );
		$this->buttons = isset( $message_fields['buttons'] ) && is_array( $message_fields['buttons'] ) ? $message_fields['buttons'] : [];
	}
	/**
	 * Return JSON representation of message
	 *
	 * @return string
	 */
	public function save_json() {
		return (string) wp_json_encode( $this->get_fields() );
	}
	/**
	 * Return JSON representation of message
	 *
	 * @return string
	 */
	public function __toString() {
		return (string) wp_json_encode( $this->get_fields() );
	}
	public function get_id() {
		return $this->id;
	}
	/**
	 * Factory method to create message
	 *
	 * @param array $message_fields Message fields.
	 * @return Message_Error|Message_Error_Single|Message_Notice|Message_Tip|Message_Tip_Incompatible Message instance.
	 */
	public static function create( array $message_fields ) {
		switch ( $message_fields['type'] ) {
			case 'tip':
				if ( isset( $message_fields['source'] ) && 'compatibility' === $message_fields['source'] ) {
					return new Message_Tip_Incompatible( $message_fields );
				}
				return new Message_Tip( $message_fields );
			case 'tip-compatibility':
				return new Message_Tip_Incompatible( $message_fields );
			case 'notice':
				return new Message_Notice( $message_fields );
			case 'error':
				return new Message_Error( $message_fields );
			case 'error-single':
				return new Message_Error_Single( $message_fields );
		}
		return new Message_Error( $message_fields );
	}
	/**
	 * Factory method to create message from json
	 *
	 * @param string $json_fields JSON with message fields.
	 * @return Message_Error|Message_Error_Single|Message_Notice|Message_Tip|Message_Tip_Incompatible|null Message instance or null.
	 */
	public static function load_json( $json_fields ) {
		$fields = json_decode( $json_fields, true );
		return ! empty( $fields ) && is_array( $fields ) ? self::create( $fields ) : null;
	}
	/**
	 * Get view instance
	 *
	 * @return Ahrefs_Seo_View
	 */
	protected static function get_view() {
		static $view = null;
		if ( is_null( $view ) ) {
			$view = Ahrefs_Seo::get()->get_view();
		}
		return $view;
	}
	/**
	 * Get template name
	 *
	 * @return string
	 */
	public function get_template() {
		return $this::TEMPLATE;
	}
	/**
	 * Update message, add prefix, update id.
	 *
	 * @param string $prefix
	 * @return Message
	 */
	public function add_message_prefix( $prefix ) {
		if ( 0 !== stripos( $this->message, $prefix ) ) { // if does not have it already.
			$this->message = $prefix . $this->message;
			$this->id      = md5( $this->message );
		}
		return $this;
	}
	/**
	 * Return fields of message.
	 *
	 * @return array<string, string|string[]|bool>
	 */
	protected function get_fields() {
		return [
			'id'       => $this->id,
			'classes'  => $this->classes,
			'type'     => $this->type,
			'template' => $this->get_template(),
			'title'    => $this->title,
			'message'  => $this->message,
			'buttons'  => $this->buttons,
		];
	}
	/**
	 * Show template with message
	 *
	 * @return void
	 */
	public function show() {
		$this::get_view()->show_part( self::TEMPLATES_BASE . $this->get_template(), $this->get_fields() );
	}
	/**
	 * Return account disconnected tip
	 *
	 * @param bool $ahrefs_disconnected
	 * @param bool $google_disconnected
	 * @return Message
	 */
	public static function account_disconnected( $ahrefs_disconnected, $google_disconnected ) {
		$buttons = $ahrefs_disconnected ? [ 'ahrefs' ] : ( $google_disconnected ? [ 'google' ] : [] );
		return self::create(
			[
				'type'    => 'tip',
				'source'  => $ahrefs_disconnected ? 'ahrefs' : 'google',
				'classes' => [ 'tip-warning' ],
				'title'   => 'Some of your accounts have been disconnected',
				'message' => 'The plugin needs Ahrefs, GSC and GA accounts to be connected to run content audits since content suggestion are based on these data. ' . 'You can check your connected accounts in the settings.',
				'buttons' => $buttons,
			]
		);
	}
	/**
	 * Return ahrefs account limited tip
	 *
	 * @return Message
	 */
	public static function ahrefs_limited() {
		$info = Ahrefs_Seo_Api::get()->get_subscription_info( true ); // use cached info.
		$plan = is_array( $info ) && isset( $info['subscription'] ) ? $info['subscription'] : '';
		return self::create(
			[
				'type'    => 'tip',
				'source'  => 'ahrefs',
				'classes' => [ 'tip-warning' ],
				'title'   => 'Your Ahrefs account has been disconnected',
				'message' => sprintf( 'Your Ahrefs account has been disconnected because you have used up all monthly integration rows available on your %s plan. ' . 'Please consider upgrading your plan now or wait until your limits reset. The report will not be updated till the limits are reset.', esc_html( $plan ) ),
				'buttons' => [ 'ahrefs' ],
			]
		);
	}
	/**
	 * Return google account is not suitable tip
	 *
	 * @return Message
	 */
	public static function not_suitable_account() {
		return self::create(
			[
				'type'    => 'tip',
				'source'  => 'google',
				'classes' => [ 'tip-warning' ],
				'title'   => 'Your Google account is connected but profiles selected is not suitable',
				'message' => 'You might have selected the wrong Google profiles or connected the wrong Google account. You can check your connected accounts in the settings.',
				'buttons' => [ 'google' ],
			]
		);
	}
	/**
	 * Return delayed audit with Google or Ahrefs as reason
	 *
	 * @param string $source
	 * @return Message
	 */
	public static function audit_delayed( $source = 'google' ) {
		$service = 'google' === $source ? 'Google Analytics & Search Console API' : 'Ahrefs API';
		return self::create(
			[
				'type'    => 'notice',
				'source'  => 'google',
				'classes' => [],
				'title'   => '',
				'message' => sprintf( 'We are experiencing some downtime from %s so the content audit run is taking a little longer than usual. The audit is still running in the background so please check back in a couple of minutes! The content audit will be delayed by approximately 15 minutes.', $service ),
				'buttons' => [],
			]
		);
	}
	/**
	 * Return WordPress API error tip
	 *
	 * @return Message
	 */
	public static function wordpress_api_error() {
		return self::create(
			[
				'type'    => 'tip',
				'source'  => 'wordpress',
				'classes' => [ 'tip-warning', 'is-dismissible' ],
				'title'   => 'WordPress API Error',
				'message' => 'Something went wrong while querying the WordPress API. Please refresh the page & try again.',
				'buttons' => [
					'refresh_page',
					'close',
				],
			]
		);
	}
	/**
	 * Return GSC disconnected error tip
	 *
	 * @param string $text
	 * @param bool   $is_gsc
	 * @return Message
	 */
	public static function gsc_disconnected( $text = '', $is_gsc = true ) {
		return self::create(
			[
				'type'    => 'tip',
				'source'  => 'google',
				'title'   => $is_gsc ? 'GSC disconnected' : 'GA disconnected',
				'message' => $text,
				'buttons' => [ 'how_to_google' ],
				'classes' => [
					'tip-warning',
					'tip-google',
				],
			]
		);
	}
	public function get_text() {
		return (string) $this->message;
	}
}
