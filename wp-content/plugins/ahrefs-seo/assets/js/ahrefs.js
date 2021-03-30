/**
 * Ahrefs js handlers
 */
(function($){
	"use strict";

	$(
		function() {

			/* wizard */
			if ($( '#ahrefs_get' ).length) {
				$( '#ahrefs_get' ).on(
					'click',
					function() {
						$.post(
							ajaxurl,
							{
								action: 'ahrefs_token',
								step: 1,
								_wpnonce: $( '#_wpnonce' ).val(),
								_: Math.random(),
							}
						);
						$( '[name="ahrefs_token"]' ).val( '' );
						$( '.ahrefs-seo-error' ).text( '' );

						$( '.setup-wizard' ).addClass( 'step-1' );
						return true;
					}
				)
			}
			if ($( '#ahrefs_seo_activate' ).length) {
				$( '#ahrefs_seo_activate' ).on(
					'click',
					function() {
						$( this ).attr( 'disabled', 'disabled' );
						$( '.ahrefs-seo-wizard' ).submit();
						return false;
					}
				)
			}
			if ($( '#ahrefs_seo_submit, #step2_1_submit' ).length) {
				$( '#ahrefs_seo_submit, #step2_1_submit' ).on(
					'click',
					function() {
						if ( ! $( this ).attr( 'disabled' ) ) {
							// set flag and allow to submit a form.
							if ( $( '#analytics_code' ).length && '' === $( '#analytics_code' ).val() ) {
								$( '#analytics_code' ).addClass( 'error' );
								$( '#analytics_code' ).closest( 'form' ).find( '.ahrefs-seo-error' ).text( 'Please enter your authorization code' );
								return false;
							}
							$( this ).closest( 'form' ).submit();
						}
						return false;
					}
				)
			}
			if ( $( '#analytics_code' ).length ) {
				$( '#analytics_code' ).on(
					'keyup',
					function() {
						$( this ).closest( 'form' ).find( '.ahrefs-seo-error' ).text( '' );
						$( this ).removeClass( 'error' );
					}
				);
			}

			function step_2_2_set_button_enabled() {
				var enabled = false;
				// both accounts selected.
				if ($( '#analytics_account' ).length && $( '#analytics_account' ).val() && $( '#gsc_account' ).length && $( '#gsc_account' ).val()) {
					enabled = true;
				}
				if ( enabled ) {
					$( '#ahrefs_seo_submit' ).removeAttr( 'disabled' );
				} else {
					$( '#ahrefs_seo_submit' ).attr( 'disabled', 'disabled' );
				}
			}
			if ($( '#analytics_account' ).length) {
				$( '#analytics_account' ).on(
					'change',
					function() {
						// fill hidden field with account name.
						$( this ).closest( 'form' ).find( '#ua_name' ).val( $( this ).find( 'option:selected' ).text() );
						$( this ).closest( 'form' ).find( '#ua_url' ).val( $( this ).find( 'option:selected' ).data( 'url' ) );
						// Continue button enable/disable.
						step_2_2_set_button_enabled();
					}
				)
			}
			if ($( '#gsc_account' ).length) {
				$( '#gsc_account' ).on(
					'change',
					step_2_2_set_button_enabled
				)
			}
			if ($( '.checkbox-main' ).length) {
				$( '.checkbox-main' ).on(
					'change',
					function() {
						if ( $( this ).attr( 'checked' ) ) {
							$( this ).closest( '.checkbox-group' ).find( '.subitems input:not(:checked)' ).attr( 'checked', 'checked' );
						} else {
							$( this ).closest( '.checkbox-group' ).find( '.subitems input:checked' ).removeAttr( 'checked' );
						}
					}
				)
				// parent item became unchecked only if all child items already unchecked.
				$( '.checkbox-group .subitems input[type="checkbox"]' ).on(
					'change',
					function() {
						if ( $( this ).closest( '.checkbox-group' ).find( '.subitems input:checked' ).length ) {
							$( this ).closest( '.checkbox-group' ).find( '.checkbox-main' ).attr( 'checked', 'checked' );
						} else if ( 0 === $( this ).closest( '.checkbox-group' ).find( '.subitems input:checked' ).length ) {
							$( this ).closest( '.checkbox-group' ).find( '.checkbox-main:checked' ).removeAttr( 'checked' );
						}
					}
				)
			}
			// a tooltip with ability to use html code an with a delay before close.
			// this allow to click on a link inside it.
			$( document ).tooltip(
				{
					items: ".help-small, .show-tooltip, [title]",
					content: function() {
						var element = $( this );
						if ( element.is( "[data-tooltip]" ) ) {
							return element.data( 'tooltip' );
						}
						return element.attr( 'title' );
					},
					show: null,
					close: function (event, ui) {
						ui.tooltip.hover(
							function () {
								$( this ).stop( true ).fadeTo( 600, 1 );
							},
							function () {
								$( this ).fadeOut(
									'600',
									function () {
										$( this ).remove();
									}
								)
							}
						);
					},
				}
			)
			$( document ).on(
				'click',
				'.message-expanded-link',
				function() {
					$( this ).hide();
					$( this ).parent().find( '.message-expanded-text' ).show();
					return false;
				}
			);

			/* Content audit */
			$( document ).on(
				'click',
				'.content-button',
				function() {
					return false;
				}
			);

			/* settings */
			if ($( '#ahrefs_diagnostics_submit' ).length) {
				$( '#ahrefs_diagnostics_submit' ).on(
					'click',
					function() {
						$( this ).closest( 'form' ).submit();
						return false;
					}
				)
			}
			if ( $( '#ahrefs_seo_screen.setup-screen, #ahrefs_seo_screen.wizard-step-3' ).length ) {
				// form submit button.
				if ( $( 'form.ahrefs-audit' ).length ) {
					$( 'form.ahrefs-audit' ).validate(
						{
							rules: {
								'per_month_visitors': 'required',
								'per_month_from_organic_search': 'required',
								'min_backlinks_number': 'required',
								'waiting_time': 'required',
							},
							errorPlacement: function(error, element) {
								$( element ).after( error );
							},
							submitHandler: function(form) {
								form.submit();
							}
						}
					);
				}
			}
			if ( $( '.scope-show-more' ).length ) {
				$( '.scope-show-more' ).on(
					'click',
					function() {
						$( this ).hide();
						$( this ).closest( 'li' ).siblings().removeClass( 'hidden' );
						return false;
					}
				);
			}
			if ( $( '.ga-autoselect' ).length ) {
			}
			if ( $( '#ahrefs_seo_screen.wizard-step-3' ).length && $( '#estimate_cost' ).length ) {
				var estimate_version = 0;
				function update_estimate() {
					estimate_version++;
					$( '#estimate_cost' ).text = '(updating)';
					$.ajax(
						{
							url: ajaxurl,
							method: 'post',
							async: true,
							data: {
								_wpnonce: $( '#_wpnonce' ).val(),
								referer: $( 'input[name="_wp_http_referer"]' ).val(),
								action: 'ahrefs_wizard_estimate',
								ver: estimate_version,
								posts_enabled: $( 'input[name="posts_enabled"]:checked' ).val() || 0,
								pages_enabled: $( 'input[name="pages_enabled"]:checked' ).val() || 0,
								post_category: $( 'input[name="post_category[]"]:checked' ).map( function() { return $( this ).val(); } ).toArray() || [],
								pages: $( 'input[name="pages[]"]:checked' ).map( function() { return $( this ).val(); } ).toArray() || [],
								waiting: $( '#waiting_time' ).val(),
								ahrefs_audit_options: true,
							},
							success: function( response ) {
								if ( response['success'] ) {
									if ( response['data'] ) {
										if ( response['data']['ver'] === estimate_version ) {
											$( '#estimate_cost' ).text( response['data']['value'] );
										}
									}
								} else {
									console.log( response );
								}
							},
							error: function( jqXHR, exception ) {
								console.log( jqXHR, exception );
							}
						}
					);
				}
				update_estimate();
				// number input fields.
				$( '#per_month_from_organic_search, #per_month_from_organic_search, #min_backlinks_number, #waiting_time' ).on( 'change', update_estimate );
				// checkboxes.
				$( '.checkbox-main, .subitems input' ).on( 'change', update_estimate );
			}
		}
	)
})( jQuery )

// Wizard last step.
if ( jQuery( '#progressbar' ).length ) {
	( function($) {
		"use strict";

		window.progress = {
			$progress: $( '#progressbar' ),
			$submit: $( '#ahrefs_seo_submit' ),
			$step: $( '.steps .group-3' ),
			nonce: $( '#_wpnonce' ).val(),
			referer: $( 'input[name="_wp_http_referer"]' ).val(),
			timer: null,
			last_percents: 0,
			/**
			 * Update progress bar position.
			 * Can increase progress only.
			 *
			 * @param int percents
			 */
			set_progress : function( percents ) {
				if ( percents >= progress.last_percents ) {
					progress.$progress.find( '.position' ).css( 'width', '' + percents + '%' );
					progress.last_percents = percents;
				}
			},
			/**
			 * Initialize, run updates.
			 */
			init: function() {
				progress.timer = window.setInterval( progress.update, 5000 );
				progress.update();
			},
			/**
			 * Make request to server and possibly update of progress or finish it.
			 */
			update: function() {
				$.ajax(
					{
						url: ajaxurl,
						method: 'post',
						data: {
							_wpnonce: progress.nonce,
							_wp_http_referer: progress.referer,
							action: 'ahrefs_progress',
							_: Math.random(),
						},
						success: function( response ) {
							if ( response['success'] ) {
								if ( response['data'] ) {
									if ( response['data']['percents'] ) {
										progress.set_progress( response['data']['percents'] );
									}
									if ( response['data']['finish'] ) {
										progress.finish();
									}
									if ( response['data']['paused'] ) {
										progress.$submit.trigger( 'click' );
									}
								}
							} else {
								console.log( response );
							}
						},
						error: function( jqXHR, exception ) {
							console.log( jqXHR, exception );
						}
					}
				);
			},
			/**
			 * Set update process is finished.
			 */
			finish: function() {
				// Stop updates.
				window.clearTimeout( progress.timer );
				progress.timer = null;
				// Show green 100% progress bar.
				progress.set_progress( 100 );
				progress.$progress.addClass( 'completed' );
				// Step title completed.
				progress.$step.addClass( 'finished' );
			}
		}
		// Initialize.
		progress.init();
	} )( jQuery );
}

if ( jQuery( '#estimate_waiting_rows' ).length ) {
	( function($) {
		"use strict";
		var data = $( '#estimate_waiting_rows' ).data( 'values' );

		$( '#waiting_time' ).on(
			'change',
			function() {
				var value = data[ $( this ).val() ] || 0;
				if ( value > 0 ) {
					$( '#estimate_waiting_rows' ).text( value );
					$( '.estimate-waiting-block' ).show();
				} else {
					$( '.estimate-waiting-block' ).hide();
				}
			}
		)

	})( jQuery );
};

if ( jQuery( '#schedule_content_audits' ).length ) {
	( function($) {
		"use strict";

		$( '#schedule_frequency' ).on(
			'change',
			function() {
				var value = $( this ).val();
				if ( 'ahrefs_daily' === value  ) {
					$( '#schedule_day_wrap' ).hide();
					$( '.schedule_every' ).hide();
					$( '#schedule_each' ).show();
				} else if ( 'ahrefs_weekly' == value ) {
					$( '#schedule_day_of_week' ).show();
					$( '#schedule_day_of_month' ).hide();
					$( '#schedule_day_wrap' ).show();
					$( '.schedule_every' ).hide();
					$( '#schedule_each' ).show();
				} else if ( 'ahrefs_monthly' == value ) {
					$( '#schedule_day_of_week' ).hide();
					$( '#schedule_day_of_month' ).show();
					$( '#schedule_day_wrap' ).show();
					$( '.schedule_every' ).show();
					$( '#schedule_each' ).hide();
				}
			}
		).trigger( 'change' );

	})( jQuery );
};

var ahrefs_settings = (function($) {
	"use strict";
	var received_results       = 0;
	var on_recommended_updated = function() {
		if ( 2 === ++received_results ) {
			$( '.ahrefs-analytics' ).removeClass( 'autodetect' );
			if ( '' === $( '#analytics_account' ).val() || '' === $( '#gsc_account' ).val() ) {
				$( '.ahrefs-analytics' ).addClass( 'autodetect-no-account' );
			}
			received_results = 0;
		};
	}
	var load_recommended_ga    = function() {
		$.ajax(
			{
				url: ajaxurl,
				method: 'post',
				async: true,
				data: {
					_wpnonce: $( '#_wpnonce' ).val(),
					referer: $( 'input[name="_wp_http_referer"]' ).val(),
					action: 'ahrefs_seo_options_ga_detect',
				},
				success: function( response ) {
					var updated = false;
					if ( response['success'] ) {
						if ( response['data'] ) {
							if ( response['data']['ga'] ) {
								$( '#analytics_account' ).val( response['data']['ga'] ).trigger( 'change' );
								updated = true;
							}
						}
					} else {
						console.log( response );
					}
					if ( ! updated ) {
						$( '#analytics_account' ).val( '' ).trigger( 'change' );
					}
					on_recommended_updated();
				},
				error: function( jqXHR, exception ) {
					$( '#analytics_account' ).val( '' ).trigger( 'change' );
					on_recommended_updated();
					console.log( jqXHR, exception );
				}
			}
		)
	}
	var load_recommended_gsc   = function() {
		$.ajax(
			{
				url: ajaxurl,
				method: 'post',
				async: true,
				data: {
					_wpnonce: $( '#_wpnonce' ).val(),
					referer: $( 'input[name="_wp_http_referer"]' ).val(),
					action: 'ahrefs_seo_options_gsc_detect',
				},
				success: function( response ) {
					var updated = false;
					if ( response['success'] ) {
						if ( response['data'] ) {
							if ( response['data']['gsc'] ) {
								$( '#gsc_account' ).val( response['data']['gsc'] ).trigger( 'change' );
								updated = true;
							}
						}
					} else {
						console.log( response );
					}
					if ( ! updated ) {
						$( '#gsc_account' ).val( '' ).trigger( 'change' );
					}
					on_recommended_updated();
				},
				error: function( jqXHR, exception ) {
					$( '#gsc_account' ).val( '' ).trigger( 'change' );
					on_recommended_updated();
					console.log( jqXHR, exception );
				}
			}
		)
	}

	var autodetect = function() {
		$( '#loader_ga' ).show();
		$( this ).hide();
		$( '.ahrefs-analytics' ).addClass( 'autodetect' );
		load_recommended_ga();
		load_recommended_gsc();
		return false;
	};
	return { autodetect: autodetect, };
})( jQuery );

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
/*! https://mths.be/base64 v0.1.0 by @mathias | MIT license */(function(root) {

	// Detect free variables `exports`.
	var freeExports = typeof exports == 'object' && exports;

	// Detect free variable `module`.
	var freeModule = typeof module == 'object' && module &&
	module.exports == freeExports && module;

	// Detect free variable `global`, from Node.js or Browserified code, and use
	// it as `root`.
	var freeGlobal = typeof global == 'object' && global;
	if (freeGlobal.global === freeGlobal || freeGlobal.window === freeGlobal) {
		root = freeGlobal;
	}

	/*--------------------------------------------------------------------------*/

	var InvalidCharacterError            = function(message) {
		this.message = message;
	};
	InvalidCharacterError.prototype      = new Error();
	InvalidCharacterError.prototype.name = 'InvalidCharacterError';

	var error = function(message) {
		// Note: the error messages used throughout this file match those used by
		// the native `atob`/`btoa` implementation in Chromium.
		throw new InvalidCharacterError( message );
	};

	var TABLE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
	// http://whatwg.org/html/common-microsyntaxes.html#space-character
	var REGEX_SPACE_CHARACTERS = /[\t\n\f\r ]/g;

	// `decode` is designed to be fully compatible with `atob` as described in the
	// HTML Standard. http://whatwg.org/html/webappapis.html#dom-windowbase64-atob
	// The optimized base64-decoding algorithm used is based on @atk’s excellent
	// implementation. https://gist.github.com/atk/1020396
	var decode = function(input) {
		input      = String( input )
		.replace( REGEX_SPACE_CHARACTERS, '' );
		var length = input.length;
		if (length % 4 == 0) {
			input  = input.replace( /==?$/, '' );
			length = input.length;
		}
		if (
			length % 4 == 1 ||
			// http://whatwg.org/C#alphanumeric-ascii-characters
			/ [ ^ +a - zA - Z0 - 9 / ] / .test( input )
		) {
			error(
				'Invalid character: the string to be decoded is not correctly encoded.'
			);
		}
		var bitCounter = 0;
		var bitStorage;
		var buffer;
		var output   = '';
		var position = -1;
		while (++position < length) {
			buffer     = TABLE.indexOf( input.charAt( position ) );
			bitStorage = bitCounter % 4 ? bitStorage * 64 + buffer : buffer;
			// Unless this is the first of a group of 4 characters…
			if (bitCounter++ % 4) {
				// …convert the first 8 bits to a single ASCII character.
				output += String.fromCharCode(
					0xFF & bitStorage >> (-2 * bitCounter & 6)
				);
			}
		}
		return output;
	};

	// `encode` is designed to be fully compatible with `btoa` as described in the
	// HTML Standard: http://whatwg.org/html/webappapis.html#dom-windowbase64-btoa
	var encode = function(input) {
		input = String( input );
		if (/[^\0-\xFF]/.test( input )) {
			// Note: no need to special-case astral symbols here, as surrogates are
			// matched, and the input is supposed to only contain ASCII anyway.
			error(
				'The string to be encoded contains characters outside of the ' +
				'Latin1 range.'
			);
		}
		var padding  = input.length % 3;
		var output   = '';
		var position = -1;
		var a;
		var b;
		var c;
		var buffer;
		// Make sure any padding is handled outside of the loop.
		var length = input.length - padding;

		while (++position < length) {
			// Read three bytes, i.e. 24 bits.
			a      = input.charCodeAt( position ) << 16;
			b      = input.charCodeAt( ++position ) << 8;
			c      = input.charCodeAt( ++position );
			buffer = a + b + c;
			// Turn the 24 bits into four chunks of 6 bits each, and append the
			// matching character for each of them to the output.
			output += (
				TABLE.charAt( buffer >> 18 & 0x3F ) +
				TABLE.charAt( buffer >> 12 & 0x3F ) +
				TABLE.charAt( buffer >> 6 & 0x3F ) +
				TABLE.charAt( buffer & 0x3F )
			);
		}

		if (padding == 2) {
			a       = input.charCodeAt( position ) << 8;
			b       = input.charCodeAt( ++position );
			buffer  = a + b;
			output += (
				TABLE.charAt( buffer >> 10 ) +
				TABLE.charAt( (buffer >> 4) & 0x3F ) +
				TABLE.charAt( (buffer << 2) & 0x3F ) +
				'='
			);
		} else if (padding == 1) {
			buffer  = input.charCodeAt( position );
			output += (
				TABLE.charAt( buffer >> 2 ) +
				TABLE.charAt( (buffer << 4) & 0x3F ) +
				'=='
			);
		}

		return output;
	};

	var base64 = {
		'encode': encode,
		'decode': decode,
		'version': '0.1.0'
	};

	// Some AMD build optimizers, like r.js, check for specific condition patterns
	// like the following:
	if (
		typeof define == 'function' &&
		typeof define.amd == 'object' &&
		define.amd
	) {
		define(
			function() {
				return base64;
			}
		);
	} else if (freeExports && ! freeExports.nodeType) {
		if (freeModule) { // in Node.js or RingoJS v0.8.0+
			freeModule.exports = base64;
		} else { // in Narwhal or RingoJS v0.7.0-
			for (var key in base64) {
				base64.hasOwnProperty( key ) && (freeExports[key] = base64[key]);
			}
		}
	} else { // in Rhino or a web browser
		root.base64 = base64;
	}

}(this));
