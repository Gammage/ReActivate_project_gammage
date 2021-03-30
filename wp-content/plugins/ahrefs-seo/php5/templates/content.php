<?php

namespace ahrefs\AhrefsSeo;

/**
* @var array<string, bool|null|Message[]> {
*
*   @type bool $no_ahrefs_account
*   @type bool $no_google_account
*   @type bool $show_last_audit_tip
*   @type Message[] $show_last_audit_tip
* }
*/
$locals         = Ahrefs_Seo_View::get_template_variables();
$view           = Ahrefs_Seo::get()->get_view();
$stop_messages  = is_array( $locals['last_audit_stopped'] ) ? $locals['last_audit_stopped'] : [];
$errors_array   = [];
$all_messages   = [];
$audit          = new Content_Audit();
$saved_messages = ! empty( Ahrefs_Seo_Errors::get_saved_messages() ) ? Ahrefs_Seo_Errors::get_saved_messages() : [];
foreach ( $saved_messages as $value ) {
	$_message = Message::create( $value );
	if ( $_message instanceof Message_Tip_Incompatible ) {
		$stop_messages[] = $_message;
	} elseif ( $_message instanceof Message_Error ) {
		$errors_array[] = $value;
	} else {
		$all_messages[] = $_message;
	}
}
unset( $saved_messages );
?>
<div class="ahrefs_messages_block" id="wordpress_api_error" style="display: none;">
<?php
Message::wordpress_api_error()->show();
?>
</div>
<div class="ahrefs_messages_block" data-type="stop">
	<?php
	$stop_messages = array_merge( $stop_messages, is_array( $locals['stop'] ) ? $locals['stop'] : [] );
	if ( count( $stop_messages ) ) {
		Ahrefs_Seo_Errors::show_stop_errors( $stop_messages );
	}
	?>
</div>
<?php
require_once __DIR__ . '/parts/charts.php';
?>
<div class="ahrefs_messages_block" data-type="audit-tip">
	<?php
	if ( $locals['show_last_audit_tip'] ) {
		$view->show_part( 'content-tips/last-audit', $locals ); // last audit over a month ago.
	}
	// show tips.
	array_walk(
		$all_messages,
		function ( $message ) {
			if ( is_object( $message ) && $message instanceof Message_Tip ) {
				// Message_Tip_Incompatible extracted to stop_messages.
				$message->show();
			}
		}
	);
	?>
</div>
<div class="ahrefs_messages_block" data-type="api-messages">
	<?php
	// show errors.
	$view->show_part( 'notices/api-messages', [ 'messages' => $errors_array ] );
	?>
</div>
<div class="ahrefs_messages_block" id="audit_delayed_google" style="display: none;">
<?php
Message::audit_delayed()->show();
?>
</div>
<div class="ahrefs_messages_block" data-type="api-delayed">
	<?php
	// show errors.
	$ids = [];
	array_walk(
		$all_messages,
		function ( $message ) use ( &$ids ) {
			if ( $message instanceof Message_Notice || $message instanceof Message_Error_Single ) {
				if ( ! in_array( $message->get_id(), $ids, true ) ) { // do not show duplicated messages.
					$message->show();
					$ids[] = $message->get_id();
				}
			}
		}
	);
	unset( $ids );
	?>
</div>
<div class="ahrefs_messages_block" data-type="first-or-subsequent">
	<?php
	( new Content_Tips_Content() )->maybe_show_tip();
	?>
</div>
<?php
// add placeholder for content audit table.
$screen = $view->get_ahrefs_screen();
if ( $screen instanceof Ahrefs_Seo_Screen_With_Table ) {
	$screen->show_table_placeholder();
}
