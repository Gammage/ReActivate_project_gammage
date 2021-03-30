/**
 * Content audit table page handlers.
 */
if ( jQuery( '#content_table' ).length ) {
	( function($) {
		"use strict";

		$( document ).on(
			'click',
			'a.action-delete-page',
			function() {
				// use the same remove list as existing item.
				var post_id   = $( this ).closest( 'tr' ).data( 'id' );
				var $checkbox = content.$table.find( 'tr .check-column input[data-id="' + post_id + '"]' );
				if ( $checkbox.length ) {
					$( this ).attr( 'href', $checkbox.closest( 'tr' ).find( '.row-actions a.submitdelete' ).attr( 'href' ) );
				}
				return true;
			}
		)
		$( document ).on(
			'click',
			'a.submit-include',
			function(e) {
				e.preventDefault();
				var post_id = $( this ).data( 'id' );
				content.ajax_set_page_active_or_leave( post_id, 1 );
				$( this ).closest( 'span' ).hide();
				return false;
			}
		)
		$( document ).on(
			'click',
			'a.submit-exclude',
			function(e) {
				e.preventDefault();
				var post_id = $( this ).data( 'id' );
				content.ajax_set_page_active_or_leave( post_id, 0 );
				$( this ).closest( 'span' ).hide();
				return false;
			}
		)
		$( document ).on(
			'click',
			'a.approve-keywords',
			function(e) {
				e.preventDefault();
				var post_id = $( this ).data( 'post' );
				content.ajax_approve_keyword( post_id, $( this ).closest( 'span' ) );
				$( this ).closest( 'span' ).hide();
				return false;
			}
		)
		// open keyword from data-keyword attribute or from current table cell.
		$( document ).on(
			'click',
			'a.ahrefs-open-keyword, a.ahrefs-open-all-keywords',
			function(e) {
				e.preventDefault();
				var keyword = $( this ).data( 'keyword' );
				if ( '' === keyword && $( this ).closest( '#keyword_results' ).length ) {
					// manual item from keywords table.
					keyword = $( this ).closest( 'tr' ).find( '.keyword-manual-input' ).val();
				}
				if (keyword) {
					window.open( 'https://ahrefs.com/keywords-explorer/google/us/overview?keyword=' + encodeURIComponent( keyword ), '_blank' );
				} else {
					var $td = $( this ).closest( 'td' ).find( '.content-post-keyword' ).clone();
					$td.find( 'a' ).remove();
					var keywords = $td.text().replace( /\n/g, "," ).split( "," ).map( function(e) {return escape_html( e.trim() ) } ).filter( function(e) { return '' !== e } );

					if (keywords) {
						keywords.map(
							function( kw ) {
								window.open( 'https://ahrefs.com/keywords-explorer/google/us/overview?keyword=' + encodeURIComponent( kw ), '_blank' );
							}
						);
					}
				}
			}
		)
		// [Use as anchor text...] button on expanded view.
		$( document ).on(
			'click',
			'a.new-anchors-submit-button',
			function(e) {
				e.preventDefault();
				var action = $( this ).closest( '.more-page-content' ).find( '.form-url' ).val();
				var $form  = $( '<form/>' ).attr( 'method','post' ).attr( 'action',action );
				$form.append( $( this ).closest( '.more-page-content' ).find( 'input' ).clone() );
				$( 'body' ).append( $form );
				$form.submit();
			}
		)
		// close message.
		$( document ).on(
			'click',
			'button.close-current-message',
			function(e) {
				e.preventDefault();
				if ( $( this ).closest( '#wordpress_api_error' ).length) {
					$( '#wordpress_api_error' ).hide(); // just hide is enough.
				} else {
					$( this ).closest( '.ahrefs-content-tip, .notice' ).remove(); // remove the message.
				}
				return false;
			}
		)
		$( document ).on(
			'click',
			'button.notice-dismiss',
			function(e) {
				if ( $( this ).closest( '#audit_delayed_google' ).length) {
					$( '#audit_delayed_google' ).hide().append( $( this ).closest( '.notice' ).clone() ); // hide block and recreate notice.
					$( this ).closest( '.notice' ).remove();
					return false;
				}
			}
		)
		window.content_svg_clicked = function(e) {
			var tab = $( e ).data( 'tab' ) || '';
			console.log( tab );
			if ( '' !== tab ) {
				var $item = $( '.tab-content-item[data-tab="' + tab + '"]' );
				if ( $item.length ) {
					document.location = $item.attr( 'href' );
				}
			}
			return false;
		};

		$( document ).on(
			'click',
			'p.chart-legend-item',
			function(e) {
				content_svg_clicked( e.target );
				return false;
			}
		)
		var entityMap = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;',
			'/': '&#x2F;',
			'`': '&#x60;',
			'=': '&#x3D;'
		};

		var escape_html = function(string) {
			return String( string ).replace(
				/[&<>"'`=\/]/g,
				function (s) {
					return entityMap[s];
				}
			);
		}
		$( document ).on(
			'click',
			'.sugggested-tip-close-button, #content_tip_got_it',
			function() {
				$.post(
					ajaxurl,
					{
						action: 'ahrefs_seo_content_tip_close',
						_wpnonce: $( '#table_nonce' ).val(),
						_: Math.random(),
					}
				);
				$( this ).closest( '.ahrefs-content-tip' ).hide();
				return false;
			}
		)
		$( document ).on(
			'click',
			'#keywords_tip_got_it',
			function() {
				$.post(
					ajaxurl,
					{
						action: 'ahrefs_seo_keyword_tip_close',
						_wpnonce: $( '#table_nonce' ).val(),
						_: Math.random(),
					}
				);
				$( this ).closest( '.ahrefs-content-tip' ).hide();
				return false;
			}
		)
		$( '#content_tip_check_suggested_keywords' ).on(
			'click',
			function() {
				$.post(
					ajaxurl,
					{
						action: 'ahrefs_seo_content_tip_close',
						_wpnonce: $( '#table_nonce' ).val(),
						_: Math.random(),
					}
				);
				$( '#keywords' ).val( 0 ).trigger( 'update' );
				$( '#group-filter-submit' ).trigger( 'click' );
				$( this ).closest( '.ahrefs-content-tip' ).hide();
				return false;
			}
		)

		window.content = {
			$table   : $( '#content_table' ),
			$form    : $( '#content_table' ).closest( 'form' ),
			keyword_data_table: null, // DataTable instance with keywords.
			keyword_data_set: [], // Source data set for keywords table.
			ping_timer: null, // ping timer used with setInterval/clearInterval.
			ping_next: null, // ping timer used with setTimeout/clearTimeout.
			ping_running: false, // ping timer used with setInterval/clearInterval.
			ping_interval: 120, // ping interval, seconds.
			no_rows_message: 'No keywords info available.',
			default_params : function( need_update_tabs ) {
				var params = {};
				content.$table.find( 'input.table-query' ).each(
					function() {
						params[ $( this ).data( 'name' ) || '' ] = $( this ).val() || '';
					}
				);
				if ( 'undefined' !== typeof( need_update_tabs ) && need_update_tabs ) {
					params['update_tabs'] = 1;
				}
				return params;
			},
			// show notice: text is string or array, html is string ot empty, id is string.
			show_notice : function( text, html, id ) {
				if ( 'string' !== typeof id || '' === id) {
					id = 'info';
				}
				var $item = $( '#' + id );
				if ( $item.length ) {
					$item.remove();
				}
				var $block = $( '<div class="notice notice-info is-dismissible"></div>' );
				$block.attr( 'id', id );
				if ( 'string' === typeof( text ) && '' !== text ) {
					var $p = $( '<p></p>' );
					$p.text( text );
					if ( html ) {
						$p.append( '&nbsp;' + html );
					}
					$block.append( $p );
				} else {
					for ( var k in text ) {
						$block.append( $( '<p></p>' ).append( text[k] ) );
					}
					if ( html ) {
						if ( text ) {
							$block.append( '&nbsp;' );
						}
						$block.append( html );
					}
				}
				$( '.ahrefs_messages_block[data-type="api-messages"]' ).prepend( $block );
				$( document ).trigger( 'wp-updates-notice-added' );
				$item = $( '#' + id );

				setTimeout( function() { $item.addClass( 'item-flash' ); }, 100 );
				$( 'html, body' ).animate( { scrollTop: $item.offset().top - 60 } );
			},
			show_notices : function( messages ) {
				content.show_notice( messages, 'info' );
			},
			show_notice_with_reload : function() {
				var $block = $( '#wordpress_api_error' );
				if ( ! $block.is( ':visible' )) {
					$block.show();
				}
			},
			hide_notice_with_reload : function() { // called on successful ping requests.
				var $block = $( '#wordpress_api_error' );
				if ( $block.is( ':visible' )) {
					$block.hide();
				}
			},
			show_notice_oops : function( text ) {
				if ( 'undefined' === typeof text || '' === text ) {
					content.show_notice( 'Oops, there was an error. Please try again.', '', 'notice_oops' );
				} else {
					content.show_notice( text, '', 'notice_oops' );
				}
			},
			/** Create html from array with errors */
			prepare_error_message : function( messages ) {
				var result = '';
				for ( var title in messages ) {
					var $title   = $( '<span class="message-expanded-title"></span>' ).text( title );
					var $content = $( '<span class="message-expanded-text"></span>' ).text( messages[title] );
					var $div     = $( '<div class="message-expanded-wrap"></div>' ).append( $title ).append( '<a href="#" class="message-expanded-link">(show details)</a>' ).append( $content );
					result      += $div.get( 0 ).outerHTML;
				}
				return result;
			},
			search_string_get : function() {
				return content.$form.find( 'input[name="last_search"]' ).val() || '';
			},
			search_string_set : function( value ) {
				content.$form.find( 'input[name="last_search"]' ).val( value );
			},
			tab_string_get : function() {
				return content.$form.find( 'input[name="tab"]' ).val();
			},
			tab_string_set : function( value ) {
				content.$form.find( 'input[name="tab"]' ).val( value );
			},
			date_string_get : function() {
				return content.$form.find( 'input[name="m"]' ).val();
			},
			date_string_set : function( value ) {
				content.$form.find( 'input[name="m"]' ).val( value );
			},
			cat_string_get : function() {
				return content.$form.find( 'input[name="cat"]' ).val();
			},
			cat_string_set : function( value ) {
				content.$form.find( 'input[name="cat"]' ).val( value );
			},
			author_string_set : function( value ) {
				content.$form.find( 'input[name="author"]' ).val( value );
			},
			keywords_string_set : function( value ) {
				content.$form.find( 'input[name="keywords"]' ).val( value );
			},
			loader_show : function() {
				content.$form.find( '#table_loader' ).show();
			},
			loader_hide : function() {
				content.$form.find( '#table_loader' ).hide();
			},

			update_active_filters: function() {
				content.$form.find( '#filter-by-date, #cat, #author, #keywords' ).find( 'option:selected' ).addClass( 'current' ).siblings( 'option' ).removeClass( 'current' );
			},
			maybe_update_audit_header: function( data ) {
				var in_progress = $( '#content_audit_status' ).hasClass( 'in-progress' );
				if ( data['in_progress'] && ! in_progress ) {
					$( '#content_audit_status' ).addClass( 'in-progress' );
				} else if ( ! data['in_progress'] && in_progress ) {
					$( '#content_audit_status' ).removeClass( 'in-progress' );
					content.ping();
				}
				if ( data['in_progress'] ) {
					$( '#content_audit_status .position' ).css( 'width', '' + data['percents'] + '%' );
				}
				var $hint = $( '.ahrefs-header .header-hint' );
				if ( $hint.text() !== data['last_time'] ) {
					$hint.text( data['last_time'] );
				}
			},
			load_content_details: function( post_id, $tr ) {
				$.ajax(
					{
						url: ajaxurl,
						dataType: 'json',
						method: 'post',
						data: {
							_wpnonce: content.$form.find( '#table_nonce' ).val(),
							action: 'ahrefs_seo_content_details',
							id: post_id,
						},
						success: function (response) {
							// must hide loader: replace td content.
							if ( response && response['data'] ) {
								$tr.find( 'td' ).html( '<div class="more-content">' + response['data'] + '</div>' );
								content.init();
							} else {
								$tr.find( 'td' ).html( 'No details available.' );
							}
						},
						error: function (jqXHR, exception) {
							console.log( jqXHR, exception );
							$tr.find( 'td' ).html( 'Something is going wrong, please try again or reload the page.' );
						}
					}
				);
			},
			/**
			 * Set post is active or not.
			 *
			 * @param int post_id
			 * @param bool|null is_active 0, 1 or null is not applicable.
			 */
			ajax_set_page_active_or_leave: function( post_id, is_active ) {
				content.loader_show();
				window.setTimeout(
					function() {
						$.ajax(
							{
								url: ajaxurl,
								dataType: 'json',
								method: 'post',
								data: $.extend(
									content.default_params(),
									{
										_wpnonce: content.$form.find( '#table_nonce' ).val(),
										action: 'ahrefs_seo_content_set_active',
										id: post_id,
										active: is_active,
										_ : Math.random(), // to omit possibly cache.
									}
								),

							success: function (response) {
								if ( response.success ) {
									// update current view.
									content.update( content.default_params( true ) );
								} else {
									var message = '';
									if ( response.data && response.data.message ) {
										message = response.data.message;
									}
									if ( response.data && response.data.messages ) {
										content.show_notice( '', response.data.messages );
									} else {
										content.show_notice_oops( message );
									}
									// update current view.
									content.update( content.default_params( true ) );
								}
							},
								error: function (jqXHR, exception) {
									console.log( jqXHR, exception );
									content.show_notice_oops();
									content.loader_hide();
								}
							}
						);
					},
					1
				);
			},
			/**
			 * Approve post keyword.
			 *
			 * @param int post_id
			 */
			ajax_approve_keyword: function( post_id, $approve_link ) {
				content.loader_show();
				window.setTimeout(
					function() {
						$.ajax(
							{
								url: ajaxurl,
								dataType: 'json',
								method: 'post',
								data: $.extend(
									content.default_params(),
									{
										_wpnonce: content.$form.find( '#table_nonce' ).val(),
										action: 'ahrefs_seo_content_approve_keyword',
										post: post_id,
										_ : Math.random(), // to omit possibly cache.
									}
								),

							success: function (response) {
								if ( response.success ) {
									// update current view, but do not remove approved item from suggested only view.
									content.ping();
									content.loader_hide();
								} else {
									var message = 'Action failed. Please try again or reload a page.';
									if ( response.data && response.data.message ) {
										message = response.data.message;
									}
									if ( response.data && response.data.messages ) {
										content.show_notice( '', content.prepare_error_message( response.data.messages ) );
									} else {
										content.show_notice( message, '' );
									}
									$approve_link.show();
									// update current view.
									content.update( content.default_params( true ) );
								}
							},
								error: function (jqXHR, exception) {
									console.log( jqXHR, exception );
									content.show_notice_oops();
									content.loader_hide();
									$approve_link.show();
								}
							}
						);
					},
					1
				);
			},
			ajax_bulk: function( action, ids ) {
				content.loader_show();
				window.setTimeout(
					function() {
						$.ajax(
							{
								url: ajaxurl,
								dataType: 'json',
								method: 'post',
								data: $.extend(
									content.default_params(),
									{
										_wpnonce: content.$form.find( '#table_nonce' ).val(),
										action: 'ahrefs_seo_content_bulk',
										doaction: action,
										ids: ids,
										_ : Math.random(), // to omit possibly cache.
									}
								),
							success: function (response) {
								if ( response.success ) {
									// update current view.
									content.update( content.default_params( true ) );
									if ( response.data['message'] ) {
										content.show_notice( '', response.data['message'] );
									}
									if ( response.data['new-request'] ) {
										// run ping again immediately and update timeout.
										content.set_ping_interval( response.data.timeout || 30, 5 );
									}
									if ( response.data['audit'] ) {
										content.maybe_update_audit_header( response.data['audit'] );
									}
								} else {
									content.show_notice_oops();
									// update current view.
									content.update( content.default_params( true ) );
								}
							},
								error: function (jqXHR, exception) {
									console.log( jqXHR, exception );
									content.show_notice_oops();
									content.loader_hide();
									content.update( content.default_params( true ) );
								}
							}
						);
					},
					1
				);
			},
			hide_more_info_items: function() {
				content.$table.find( '.more-info-tr' ).remove();
				// remove active class from expanded button.
				content.$table.find( '.expanded' ).removeClass( 'expanded' );
			},
			add_more_info_item: function( $tr ) {
				content.hide_more_info_items();
				var details = '<div class="row-loader"><div class="loader"></div></div>';
				var post_id = $tr.find( '.check-column input' ).data( 'id' );

				$tr.after( '<tr class="hidden more-info-tr"></tr><tr class="inline-edit-row more-info-tr more-info-tr-active" data-id="' + post_id + '"><td colspan="' + $( 'th:visible, td:visible', '.widefat:first thead' ).length + '" class="colspanchange">' + details + '</td></tr>' );
				$tr.addClass( 'expanded' );
				var $new_tr = content.$table.find( 'tr.more-info-tr-active' );
				// load details.
				content.load_content_details( post_id, $new_tr );
			},
			// update tabs content if new html has different items count.
			maybe_update_tabs_content: function( new_html ) {
				var $tabs         = content.$form.find( '.subsubsub' );
				var $new_content  = $( new_html );
				var count_current = $tabs.find( '.count' ).map( function(){return(jQuery( this ).text())} ).toArray().join( '' );
				var count_updated = $new_content.find( '.count' ).map( function(){return($( this ).text())} ).toArray().join( '' );

				if ( count_current !== count_updated ) {
					$tabs.html( $new_content.html() );
					$tabs.removeClass( 'item-flash' );
					setTimeout( function() { $tabs.addClass( 'item-flash' ); }, 100 );
				}
			},
			// update charts content.
			maybe_update_charts_content: function( charts ) {
				if (charts.left && charts.left.length) {
					$( '#charts_block_left' ).html( charts.left ).removeClass( 'item-flash' );
					setTimeout( function() { jQuery( '#charts_block_left' ).addClass( 'item-flash' ); }, 100 );
				}
				if (charts.right && charts.right.length) {
					$( '#charts_block_right' ).html( charts.right );
					$( '#charts_block_right' ).closest( '.chart-wrap' ).removeClass( 'item-flash' );
					setTimeout( function() { jQuery( '#charts_block_right' ).closest( '.chart-wrap' ).addClass( 'item-flash' ); }, 100 );
				}
				if (charts.right_legend && charts.right_legend.length) {
					$( '#charts_block_right_legend' ).html( charts.right_legend );
				}
			},
			// show block with error or add error to existing block.
			show_messages_html: function( messages_html ) {
				var $messages  = $( messages_html );
					var $items = $messages.find( '.ahrefs-message' );
					$items.each(
						function() {
							var $item          = $( this );
							var id             = $item.attr( 'id' );
							var $existing_item = $( '#' + id );
							if ( $existing_item.length ) {
								if ( $existing_item.data( 'count' ) ) { // just increase count number.
									var count = 0 + $item.data( 'count' ) + $existing_item.data( 'count' );
									$existing_item.data( 'count', count );
									var $count_block = $existing_item.find( '.ahrefs-messages-count' );
									$count_block.removeClass( 'hidden' ).text( count );
								}
							} else {
								if ( ! $( '#ahrefs_api_messsages' ).length ) {
									$( '.ahrefs_messages_block[data-type="api-messages"]' ).append( '<div class="notice notice-error is-dismissible" id="ahrefs_api_messsages"><div id="ahrefs-messages"></div></div>' );
									$( document ).trigger( 'wp-updates-notice-added' );
								}

								var $wrapper = $( '#ahrefs-messages:first .message-expanded-text' );
								if ( ! $wrapper.length ) { // append wrapper.
									var $blocks = $( '<div/>' ).append( $messages.find( '#ahrefs-messages' ).clone() );
									$blocks.find( '.ahrefs-message' ).remove();
									$( '#ahrefs-messages:first' ).empty().append( $blocks );
									$wrapper = $( '#ahrefs-messages:first .message-expanded-text' );
								}
								$wrapper.append( $item );
							}

						}
					)
			},
			show_tips : function( tips ) {
				if ( 'undefined' !== typeof tips['stop'] && null !== tips['stop'] ) { // update a whole block.
					var $stop_block = $( '.ahrefs_messages_block[data-type="stop"]' );
					if ($stop_block.html() != tips['stop'] ) { // do not show same message, that already displayed.
						$stop_block.html( tips['stop'] );
						if ( '' !== tips['stop'] ) { // do not scroll screen if stop block is empty.
							$( 'html, body' ).animate( { scrollTop: $stop_block.offset().top - 60 } );
						}
					}

				}
				if ( 'undefined' !== typeof tips['api-messages'] && null !== tips['api-messages'] && '' !== tips['api-messages'] ) { // add new messages.
					content.show_messages_html( tips['api-messages'] );
				}
				if ( 'undefined' !== typeof tips['api-delayed'] && null !== tips['api-delayed'] ) { // add new messages.
					$( '.ahrefs_messages_block[data-type="api-delayed"]' ).append( tips['api-delayed'] );
					$( document ).trigger( 'wp-updates-notice-added' );
				}
				if ( 'undefined' !== typeof tips['audit-tip'] && null !== tips['audit-tip'] ) { // add new tips.
					$( '.ahrefs_messages_block[data-type="audit-tip"]' ).append( tips['audit-tip'] );
					$( document ).trigger( 'wp-updates-notice-added' );
				}
				if ( 'undefined' !== typeof tips['first-or-subsequent'] && null !== tips['first-or-subsequent'] ) { // update whole block.
					$( '.ahrefs_messages_block[data-type="first-or-subsequent"]' ).html( tips['first-or-subsequent'] );
				}
			},
			/**
			 * Initialize data table
			 */
			keyword_popup_update_table : function() {
				if ( $( '#keyword_results' ).length && ! content.keyword_data_table ) {
					content.keyword_data_table = $( '#keyword_results' ).DataTable(
						{
							data: content.keyword_data_set,
							columns: [
								{ title: '<span></span>', orderSequence: [], render: function( data, type, row, meta ) {
									var id = 'ch_' + meta.row;
									return '<input type="checkbox" value="1" class="keyword-checked" id="' + id + '"' + (data ? ' checked' : '' ) + '><label for="' + id + '"><span class="checked"></span><span class="unchecked"></span></label>';
								}   },
								{ title: "<span>Keyword</span>", orderSequence: [ 'asc', 'desc' ], render: function( data, type, row, meta ) {
									if ( 'display' === type ) {
										if ( 'manual' !== row[2] ) {
											return data;
										}
										return '<input type="text" class="keyword-manual-input" value="' + escape_html( data ) + '" maxlength="191">';
									}
									return data;
								}, },
								{ title: "<span>Source</span>", orderSequence: [ 'asc', 'desc' ], render: function( data, type, row, meta ) {
									if ( 'display' === type ) {
										if ( 'manual' !== row[2] ) {
											return '<span class="badge-keyword-source badge-keyword-source-' + data + '">' + escape_html( data ) + '</span>';
										}
										return '';
									}
									if ( 'sort' === type ) {
										if ('gsc' === data ) {
											return 1;
										}
										if ('tf-idf' === data ) {
											return 2;
										}
										if ('manual' === data ) {
											return 3;
										}
										return data;
									}
									return data;
								}, },
								{ title: "<span>Position</span>", orderSequence: [ 'asc' ], render: function( data, type, row, meta ) {
									if ( 'display' === type ) {
										if ('' === data || null === data) {
											return '<span class="position">—</span>';
										}
										return '<span class="position">' + data.toFixed( 1 ) + '</span>';
									}
									if ( 'sort' === type ) {
										if ('' === data || null === data) {
											return 1000000.0; // show items without positions at the end.
										}
										return data;
									}
									return data;
								}, },
								{ title: "<span>Clicks</span>", orderSequence: [ 'desc' ], render: function( data, type, row, meta ) {
									if ( 'display' === type ) {
										if ('-' === data) {
											return '<span class="keyword-no-info">—</span>';
										}
										return '' + data + ' <span class="keyword-percents">' + Math.round( 100 * data / content.keyword_data_total_clicks ) + '%</span>'
									}
									return data;
								}, },
								{ title: "<span>Impressions</span>", orderSequence: [ 'desc' ], render: function( data, type, row, meta ) {
									if ( 'display' === type ) {
										if ('-' === data) {
											return '<span class="keyword-no-info">—</span>';
										}
										return '' + data + ' <span class="keyword-percents">' + Math.round( 100 * data / content.keyword_data_total_impr ) + '%</span>'
									}
									return data;
								}, },
								{ title: "", orderSequence: [], render: function( data, type, row, meta ) {
									var keyword = ( 'manual' !== row[2] ) ? row[1] : '';
									return jQuery( '<a href="#" class="ahrefs-open-keyword"><span>Explore in Ahrefs</span><span class="link-open"></span></a>' ).attr( 'data-keyword', keyword ).get( 0 ).outerHTML;
								} },
							],
							rowCallback: function (row, data) {
								if ( data[0] && ! $( '#keyword_results tr.selected' ).length ) {
									$( row ).addClass( 'selected' );
								}
							},
							'order': [],
							'pageLength' : 10,
							'paging':   false,
							'info':     false,
							'searching': false,
							language : {
								emptyTable: content.no_rows_message,
							}
						}
					);
					// add-remove actions.
					$( '#keyword_results' ).on(
						'click',
						'tbody td',
						function() {
							var $ch = $( this ).closest( 'tr' ).find( 'input.keyword-checked' );
							if ( ! $ch.is( ':checked' ) ) {
								$ch.closest( 'table' ).find( 'input.keyword-checked' ).removeAttr( 'checked' ).prop( 'checked', false );
								$ch.closest( 'table' ).find( 'tr' ).removeClass( 'selected' );
								$ch.closest( 'tr' ).addClass( 'selected' );
								$ch.attr( 'checked', 'checked' ).prop( 'checked', true );
							}
						}
					);
					$( '#ahrefs_seo_keyword_submit' ).on(
						'click',
						function() {
							var $ch = $( this ).closest( '.keyword-table-wrap' ).find( '#keyword_results' ).find( '.keyword-checked:checked' );
							if ( $ch.length ) {
								var $manual        = $ch.closest( 'tr' ).find( '.keyword-manual-input' );
								var keyword        = $manual.length ? $manual.val() : $ch.closest( 'tr' ).find( 'td:nth(1)' ).text(); // selected keyword.
								$manual            = $ch.closest( 'table' ).find( '.keyword-manual-input' );
								var keyword_manual = $manual.val(); // save user input.
								var post_id        = $( this ).closest( '.ahrefs-seo-modal-keywords' ).data( 'id' );

								content.keyword_set_post_keyword( post_id, keyword, keyword_manual, content.keyword_data_not_approved || 0 );
							}
							// $( '#TB_closeWindowButton' ).trigger( 'click' );
							return false;
						}
					);
					$( '#ahrefs_seo_keyword_cancel' ).on(
						'click',
						function() {
							$( '#TB_closeWindowButton' ).trigger( 'click' );
							return false;
						}
					);

				}
				jQuery( '#TB_window' ).on( 'tb_unload', content.keyword_popup_delete_table );
				jQuery( '#TB_ajaxContent' ).addClass( 'keywords-popup' )
			},
			keyword_popup_delete_table : function() {
				if ( content.keyword_data_table ) {
					content.keyword_data_table.destroy();
					content.keyword_data_table = null;
				};
			},
			keyword_popup_show: function( post_id, title ) {
				// window width.
				var width = Math.round( ( document.body.clientWidth ) * 0.9 ) - 30;
				if ( width > 1024 ) {
					width = 1024;
				}
				var height = Math.round( ( document.body.clientHeight ) ) - 50;
				if ( height > 1024 ) {
					height = 1024;
				}

				var url = ajaxurl + '?action=ahrefs_content_get_keyword_popup&post=' + post_id + '&_wpnonce=' + content.$form.find( '#table_nonce' ).val() + '&width=' + width + '&height=' + height;
				tb_show( 'Select target keyword', url );
			},
			keyword_show_error : function( text ) {
				if ( 'undefined' === typeof text || '' === text ) {
					text = [ 'Oops, there was an error while saving the keyword. Please try again.' ];
				} else if ( 'string' === typeof text ) {
					text = [ text ];
				}
				// keyword_popup_error_place.
				if ( "visible" === jQuery( "#TB_window" ).css( "visibility" ) && $( '#TB_ajaxContent' ).length && $( '.ahrefs-seo-modal-keywords' ).length ) { // show in current keywords popup.
					var $item = $( '.ahrefs-seo-modal-keywords' ).find( '.keyword-save-error' );
					var $div  = $( '<div class="notice notice-error is-dismissible"></div>' );
					for ( var k in text ) {
						$div.append( $( '<p/>' ).text( text[k] ) );
					}
					$item.append( $div );
					$( document ).trigger( 'wp-updates-notice-added' );
					$( '#TB_ajaxContent' ).animate( { scrollTop: 0 } );
				} else { // show in main window.
					content.show_notice( text, '' )
				}
			},
			keyword_hide_error : function() {
				$( '.ahrefs-seo-modal-keywords' ).find( '.keyword-save-error' ).empty();
			},
			keyword_show_loader : function() {
				$( '#loader_suggested_keywords' ).show();
			},
			keyword_hide_loader : function() {
				$( '#loader_suggested_keywords' ).hide();
			},
			/**
			 * Save selected keyword and user keyword input.
			 *
			 * @param post_id
			 * @param keyword
			 * @param not_approved
			 * @param keyword_manual
			 */
			keyword_set_post_keyword: function( post_id, keyword, keyword_manual, not_approved ) {
				content.keyword_hide_error();
				content.keyword_show_loader();
				$.ajax(
					{
						url: ajaxurl,
						method: 'post',
						async: true,
						data: {
							action: 'ahrefs_content_set_keyword',
							_wpnonce: content.$form.find( '#table_nonce' ).val(),
							referer: $( 'input[name="_wp_http_referer"]' ).val(),
							post: post_id,
							keyword: keyword,
							keyword_manual: keyword_manual,
							not_approved: not_approved,
						},
						success: function( response ) {
							content.keyword_hide_loader();
							if ( response['success'] ) { // update keyword in the content audit table.
								$( '#TB_closeWindowButton' ).trigger( 'click' );
								content.$table.find( '.check-column input[data-id="' + post_id + '"]' ).closest( 'tr' ).find( '.content-post-keyword' ).text( keyword ).closest( 'tr' ).find( '.column-position' ).text( '' );
								content.ping();
							} else {
								if ( response && response['data'] && response['data']['error'] ) {
									content.keyword_show_error( response['data']['error'] );
								} else {
									content.keyword_show_error();
								}
								console.log( response );
							}
						},
						error: function( jqXHR, exception ) {
							console.log( jqXHR, exception );
							content.keyword_hide_loader();
							content.keyword_show_error();
						}
					}
				);
			},
			// run keywords suggestions update.
			keyword_popup_update_suggestions: function( post_id ) {
				$.ajax(
					{
						url: ajaxurl,
						method: 'post',
						async: true,
						data: {
							action: 'ahrefs_content_get_fresh_suggestions',
							_wpnonce: content.$form.find( '#table_nonce' ).val(),
							referer: $( 'input[name="_wp_http_referer"]' ).val(),
							post: post_id,
						},
						success: function( response ) {
							$( '#loader_suggested_keywords' ).hide();
							if ( $( '#ahrefs_seo_modal_keywords' ).length && $( '#ahrefs_seo_modal_keywords' ).data( 'id' ) == response['data']['post_id'] ) { // if a table displayed and this is the table for same post as we received.
								// keywords.
								if ( response['success'] && response['data'] && response['data']['post_id'] ) {
									var different_rows = [];
									// compare with existing...
									for ( var k in response['data']['keywords'] ) {
										if ( null === content.keyword_data_set || 'undefined' === typeof( content.keyword_data_set[k] ) || content.keyword_data_set[k].slice( 1 ).toString() != response['data']['keywords'][k].slice( 1 ).toString() ) {
											different_rows.push( k );
										}
									}
									if ( null === content.keyword_data_set || content.keyword_data_set.length !== response['data']['keywords'].length || different_rows.length ) {

										content.keyword_data_total_clicks = response['data']['total_clicks'];
										content.keyword_data_total_impr   = response['data']['total_impr'];
										content.keyword_data_table.clear();
										content.keyword_data_table.rows.add( response['data']['keywords'] || [] ); // update keyword table with fresh suggestions.
										content.keyword_data_table.draw();
										content.keyword_data_set = response['data']['keywords'];
										console.log( 'Updated with fresh suggestions' );
										// blink on updated rows.
										for (var k in different_rows) {
											$( '#keyword_results tbody tr:nth(' + different_rows[k] + ') td' ).addClass( 'item-flash' );
										}
										setTimeout( function() { $( '#keyword_results tbody td.item-flash' ).removeClass( 'item-flash' ); }, 5000 );
									} else {
										console.log( 'fresh data is same as existing.' );
									}
								}
								// error message.
								if ( response['success'] && response['data'] && response['data']['errors'] ) {
									content.keyword_show_error( response['data']['errors'] );
									$( document ).trigger( 'wp-updates-notice-added' );
								}
							} else {
								console.log( response );
							}
						},
						error: function( jqXHR, exception ) {
							$( '#loader_suggested_keywords' ).hide();
							console.log( jqXHR, exception );
							content.show_notice_with_reload();
						}
					}
				);

			},
			// replace placeholder by table content.
			display: function() {
				content.loader_show();
				// use parameters from current url at first table load time.
				var query = window.location.search.substring( 1 );
				var data  = $.extend(
					content.default_params(),
					{
						tab: content.__query( query, 'tab' ) || '',
						paged: content.__query( query, 'paged' ) || '1',
						order: content.__query( query, 'order' ) || '',
						orderby: content.__query( query, 'orderby' ) || '',
						s: content.__query( query, 's' ) || null,
						cat: content.__query( query, 'cat' ) || null,
						author: content.__query( query, 'author' ) || null,
					}
				);
				$.ajax(
					{
						url: ajaxurl,
						method: 'post',
						dataType: 'json',
						data: $.extend(
							data,
							{
								_wpnonce: content.$form.find( '#table_nonce' ).val(),
								action: 'ahrefs_seo_table_content_init',
								screen_id: window.pagenow || null,
							}
						),
					success: function (response) {
						if ( response && response['data'] && response['data']['display'] ) {
							content.$table.html( response.data.display );
							content.$table.find( '.tablenav.top .tablenav-pages' ).removeClass( 'one-page' ).removeClass( 'no-pages' );
							$( "tbody" ).on(
								"click",
								".toggle-row",
								function(e) {
									e.preventDefault();
									$( this ).closest( "tr" ).toggleClass( "is-expanded" )
								}
							);
							content.init();
							content.update_active_filters();
							// start ping immediately it there are unprocessed items.
							if ( $( '#has_unprocessed_items' ).length && $( '#has_unprocessed_items' ).val() ) {
								content.set_ping_interval( 30, 6 );
							}

						} else {
							content.show_notice_with_reload();
						}
						content.loader_hide();
					},
						error: function (jqXHR, exception) {
							console.log( jqXHR, exception );
							content.loader_hide();
							content.show_notice_with_reload();
						}
					}
				);
			},
			init: function () {
				var timer;
				var delay = 500;
				// form items.
				content.$form.find( '#search-submit' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();
						content.search_string_set( content.$form.find( 'input[name="s"]' ).val() );
						content.update( content.default_params() );
					}
				);
				content.$form.find( '#doaction, #doaction2' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var action = $( this ).closest( 'div' ).find( 'select' ).val();
						var ids    = content.$table.find( 'tbody .check-column input:checked' ).map(
							function() {
								return $( this ).val();
							}
						)
						if ( ids.length ) {
							content.ajax_bulk( action, ids.toArray() );
						}
					}
				);
				content.$form.find( '#group-filter-submit' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();

						var date     = content.$form.find( '#filter-by-date' ).val();
						var cat      = content.$form.find( '#cat' ).val();
						var author   = content.$form.find( '#author' ).val();
						var keywords = content.$form.find( '#keywords' ).val();
						content.date_string_set( date );
						content.cat_string_set( cat );
						content.author_string_set( author );
						content.keywords_string_set( keywords );

						content.update_active_filters();

						content.update( content.default_params() );
					}
				);
				content.$form.on(
					'submit',
					function(e){
						e.preventDefault();
					}
				);

				content.$table.find( '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var query = this.search.substring( 1 );
						var data  = $.extend(
							content.default_params(),
							{
								paged: content.__query( query, 'paged' ) || null,
								order: content.__query( query, 'order' ) || null,
								orderby: content.__query( query, 'orderby' ) || null,
								s: content.search_string_get(),
							}
						);
						content.update( data );
					}
				);
				content.$table.find( 'a.ahrefs-cat-link' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var query  = this.search.substring( 1 );
						var cat_id = content.__query( query, 'cat' );
						if ( cat_id ) {
							content.$table.find( '#cat' ).val( cat_id ).trigger( 'change' );
							content.$table.find( '#group-filter-submit' ).trigger( 'click' );
						}
						return false;
					}
				);
				// stop or start analyze page.
				content.$table.find( 'a.action-stop, a.action-start' ).off( 'click' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var post_id   = $( this ).closest( 'tr' ).data( 'id' );
						var is_active = $( this ).hasClass( 'action-start' );
						content.ajax_set_page_active_or_leave( post_id, is_active ? 1 : 0 );
						return false;
					}
				);
				content.$table.find( 'input[name=paged]' ).off( 'keyup' ).on(
					'keyup',
					function (e) {
						if (13 == e.which) {
							e.preventDefault();
						}
						var data = content.default_params();
						window.clearTimeout( timer );
						timer = window.setTimeout(
							function () {
								content.update( data );
							},
							delay
						);
					}
				);
				content.$table.find( 'a.more-info-action, a.content-more-button' ).off( 'click' ).on(
					'click',
					function (e) {
						var $tr = $( this ).closest( 'tr' );
						if ( $tr.hasClass( 'expanded' ) ) {
							content.hide_more_info_items();
						} else {
							content.add_more_info_item( $tr );
						}
						return false;
					}
				);
				content.$table.find( '#analysis_setting_button' ).off( 'click' ).on(
					'click',
					function (e) {
						var href             = $( this ).data( 'href' ) + '&return=' + encodeURIComponent( window.location.href );
						window.location.href = href;
						return false;
					}
				);
				content.$table.find( '.change-keywords' ).off( 'click' ).on(
					'click',
					function (e) {
						var post_id = $( this ).data( 'post' );
						var title   = $( this ).closest( 'tr' ).find( 'td:nth(0) a:first' ).text();
						content.keyword_popup_show( post_id, title );
						return false;
					}
				);
				content.$table.find( '.keywords-hidden-count' ).off( 'click' ).on(
					'click',
					function (e) {
						$( this ).hide();
						$( this ).closest( 'td' ).find( '.keywords-hidden-content' ).show();
						return false;
					}
				);
			},
			// Send query once a 2 minutes and update current items if any.
			ping: function ( unpause_audit, callback ) { // unpause_audit=true : try to turn audit on from pause.
				content.ping_running = true;
				content.set_ping_interval(); // update next scheduled ping.
				var data = {};
				content.$table.find( '.check-column > input[data-id]' ).map(
					function() {
						return { id : $( this ).val(), ver: $( this ).attr( 'data-ver' ) };
					}
				).toArray().forEach(
					function( item ) {
						data[item['id']] = item['ver'];
					}
				);
				if ( jQuery.isEmptyObject( data ) ) {
					data = false;
				}
				// add 'stop' block items.
				var stopped_items = [];
				$( '.ahrefs_messages_block[data-type="stop"] > div' ).each(
					function() {
						stopped_items.push( $( this ).data( 'id' ) || '' );
					}
				);
				// use setTimeout(): give UI a chance to redraw.
				window.setTimeout(
					function() {
						$.ajax(
							{
								url: ajaxurl,
								dataType: 'json',
								method: 'post',
								data: {
									_wpnonce: content.$form.find( '#table_nonce' ).val(),
									action: 'ahrefs_seo_content_ping',
									items: data, // items or false.
									tab: content.tab_string_get(),
									chart_score : $( '.score-number' ).data( 'score' ).trim(),
									chart_pie : $( '.counter' ).map( function() {return $( this ).text().trim(); } ).toArray().join( '-' ),
									estimate: $( '#estimate_cost' ).length, // query estimate only if it exists at the page.
									first_or_sub: $( '.ahrefs_messages_block[data-type="first-or-subsequent"]' ).find( '.ahrefs-content-tip' ).data( 'time' ),
									stop: stopped_items.join( ' ' ), // string with already displayed messages from stop block.
									unpause_audit: unpause_audit, // try to turn audit on from pause.
								},

								success: function (response) {
									try {
										content.ping_running = false;
										if ( response && response['data'] ) {
											// update tabs it received.
											if (response.data.tabs && response.data.tabs.length) {
												content.maybe_update_tabs_content( response.data.tabs );
											}
											if (response.data.charts) {
												content.maybe_update_charts_content( response.data.charts );
											}
											if (response.data.tips) {
												content.show_tips( response.data.tips );
											}
											if ( response.data['audit'] ) {
												content.maybe_update_audit_header( response.data['audit'] );
											}
											var in_progress = response.data.audit && response.data.audit.in_progress;
											if ( 'undefined' !== typeof response.data['paused'] ) {
												if ( response.data['paused'] ) {
													$( '#content_audit_status' ).addClass( 'paused' );
												} else {
													$( '#content_audit_status' ).removeClass( 'paused' );
												}
											}
											if ( 'undefined' !== typeof response.data['delayed'] ) {
												if ( response.data['delayed'] && in_progress ) {
													$( '#audit_delayed_google' ).show();
												} else {
													$( '#audit_delayed_google' ).hide();
												}
											}
											if (response.data.updated && response.data.updated.length) {
												var $tr_expanded = [];
												// all content are in one html field, parse it with jQuery.
												var $div = $( '<div/>' ).html( response.data.updated );
												// hide hidden columns.
												var hidden_classes = content.$table.find( 'thead tr .manage-column.hidden' ).map( function() { return $( this ).attr( 'class' ).match( /(column-(\w)+)/ )[1] || ''} ).toArray();
												$div.find( 'td' ).removeClass( 'hidden' );
												if ( hidden_classes ) {
													for (var k in hidden_classes) {
														$div.find( 'td.' + hidden_classes[k] ).addClass( 'hidden' );
													}
												}
												$div.find( 'tr' ).each(
													function() {
														var id = $( this ).find( '.check-column > input[data-id]' ).data( 'id' ) || '';
														if ( '' !== id ) {
															// search corresponding table row and fill it with new value.
															var $tr = content.$table.find( 'tr > .check-column > input[data-id="' + id + '"]' ).closest( 'tr' );
															if ( $tr.length ) {
																$tr.html( $( this ).html() );
																$tr.addClass( 'item-updated' ).removeClass( 'item-flash' );
																setTimeout( function() { $tr.addClass( 'item-flash' ); }, 100 );
															}
															if ( $tr.hasClass( 'expanded' ) ) {
																$tr_expanded = $tr;
															}
														}
													}
												);
												content.init();
												// close and expand previously opened row again, with updated details.
												if ( $tr_expanded.length ) {
													$tr_expanded.find( '.content-more-button' ).trigger( 'click' ).trigger( 'click' );
												}
											}
											if ( response.data['new-request'] ) {
												// run ping again immediately and update timeout.
												if ( 'undefined' === typeof response.data.waiting_time ) {
													response.data.waiting_time = 15;
												}
												content.set_ping_interval( response.data.timeout || 30, response.data.waiting_time );
											}
										}
										if ( 'function' === typeof callback ) {
											callback();
										}
										content.hide_notice_with_reload();
									} catch ( e ) {
										// do not show any error at the frontend, because this update is working at the background.
										console.log( e );
									}
								},
								error: function (jqXHR, exception) {
									// do not show any error at the frontend, because this update is working at the background.
									console.log( jqXHR, exception );
									content.ping_running = false;
									if ( 'function' === typeof callback ) {
										callback();
									}
									content.show_notice_with_reload();
								}
							}
						);
					},
					1
				);
			},
			/**
			 * Set interval for ping, run immediately.
			 *
			 * @param int timeout Interval, seconds.
			 * @param int run_after Run first request immediately in seconds.
			 */
			set_ping_interval: function( timeout, run_after ) {
				if ( 'undefined' === typeof( timeout ) ) {
					timeout = content.ping_interval; // use same interval as before.
				}
				if ( timeout < 30 ) {
					timeout = 30; // do not send requests too often.
				}
				if ( timeout > 120 ) {
					timeout = 120; // at least once per 2 minutes.
				}
				if ( 'undefined' !== typeof content.ping_timer ) {
					clearInterval( content.ping_timer );
				}
				content.ping_timer    = window.setInterval( content.ping, timeout * 1000 );
				content.ping_interval = Math.ceil( timeout + 250 * Math.random() ); // in seconds.
				if ( 'undefined' !== typeof run_after && ( run_after || 0 === run_after ) && ! content.ping_running ) {
					// prepare next call using timeout.
					if ( 'number' !== typeof run_after || run_after < 5.5 ) {
						run_after = 5.5;
					} else if ( run_after > timeout ) {
						run_after = timeout - 1;
					}
					if ( 'undefined' !== typeof content.ping_next ) {
						clearTimeout( content.ping_next );
					}
					content.ping_next = setTimeout( content.ping, Math.ceil( run_after * 1000 + 250 * Math.random() ) ); // run with predefined delay sec delay.
				}
			},
			// Send query and update table parts with updated versions of rows, headers, nav.
			update: function ( data ) {
				content.loader_show();
				window.setTimeout(
					function() {
						$.ajax(
							{
								url: ajaxurl,
								dataType: 'json',
								method: 'get', // otherwise order by table header click will not work.
								data: $.extend(
									{
										_wpnonce: content.$form.find( '#table_nonce' ).val(),
										action: 'ahrefs_seo_table_content_update',
										chart_score : $( '.score-number' ).data( 'score' ).trim(),
										chart_pie: $( '.counter' ).map( function() {return $( this ).text().trim(); } ).toArray().join( '-' ),
										estimate: $( '#estimate_cost' ).length, // query estimate only if it exists at the page.
										screen_id: window.pagenow || null,
									},
									data
								),
							success: function (response) {
								if ( response && response['data'] ) {
									if (response.data.tabs && response.data.tabs.length) {
										content.maybe_update_tabs_content( response.data.tabs );
									}
									if (response.data.charts) {
										content.maybe_update_charts_content( response.data.charts );
									}
									if (response.data.rows.length) {
										content.$table.find( '#the-list' ).html( response.data.rows );
									}
									if (response.data.column_headers.length) {
										content.$table.find( 'thead tr, tfoot tr' ).html( response.data.column_headers );
									}
									if (response.data.pagination.bottom.length) {
										content.$table.find( '.tablenav.bottom .tablenav-pages' ).html( $( response.data.pagination.bottom ).html() );
									}
									// add/remove  "one-page", "no-pages" classes from .tablenav-pages on update.
									if ( 0 == response.data.total_pages ) {
										content.$table.find( '.tablenav.bottom .tablenav-pages' ).removeClass( 'one-page' ).addClass( 'no-pages' );
									} else if ( 1 == response.data.total_pages ) {
										content.$table.find( '.tablenav.bottom .tablenav-pages' ).addClass( 'one-page' ).removeClass( 'no-pages' );
									} else {
										content.$table.find( '.tablenav.bottom .tablenav-pages' ).removeClass( 'one-page' ).removeClass( 'no-pages' );
									}
									content.init();
								} else {

								}
								content.loader_hide();
							},
								error: function (jqXHR, exception) {
									console.log( jqXHR, exception );
									content.show_notice_with_reload();
									content.loader_hide();
								}
							}
						);
					},
					1
				);
			},
			/**
			 * Filter the URL Query to extract variables
			 *
			 * @see http://css-tricks.com/snippets/javascript/get-url-variables/
			 *
			 * @param    string    query The URL query part containing the variables
			 * @param    string    variable Name of the variable we want to get
			 *
			 * @return   string|boolean The variable value if available, false else.
			 */
			__query: function (query, variable) {
				var vars = query.split( "&" );
				var len  = vars.length;
				for (var i = 0; i < len; i++) {
					var pair = vars[i].split( "=" );
					if (pair[0] == variable) {
						return pair[1];
					}
				}
				return false;
			},
			init_manual_update: function () {
				// [Start new audit] button clicked.
				$( '.manual-update-content-link' ).on(
					'click',
					function() {
						if ( $( this ).hasClass( 'disabled' ) ) {
							return false;
						}
						$( '.tip-new-audit-message' ).remove();
						$.ajax(
							{
								url: ajaxurl,
								method: 'post',
								dataType: 'json',
								async: true,
								data:
								{
									_wpnonce: content.$form.find( '#table_nonce' ).val(),
									action: 'ahrefs_seo_content_manual_update',
								},
								success: function (response) {
									if ( response && response['success'] ) {
										// ping: there are unprocessed items.
										content.set_ping_interval( 60, false );
										// reload page.
										document.location.reload( true );
									} else {
										if (response.data.tips) {
											content.show_tips( response.data.tips );
										} else {
											content.show_notice_with_reload();
										}
										content.ping();
									}
									if ( $( '#last_content_audit_tip' ).length ) {
										$( '#last_content_audit_tip' ).hide();
									}
								},
								error: function (jqXHR, exception) {
									console.log( jqXHR, exception );
									content.show_notice_with_reload();
								}
							}
						);
						return false;
					}
				);
				// [Audit paused] button clicked.
				$( '.paused-audit-button' ).on(
					'click',
					function() {
						if ( ! $( this ).hasClass( 'active' ) ) {
							$( '.paused-audit-button' ).addClass( 'active' );
							$( '.tip-new-audit-message' ).remove();
							$( '.ahrefs_messages_block[data-type="stop"]' ).empty();
							content.ping(
								true,
								function() {
									$( '.paused-audit-button' ).removeClass( 'active' );
								}
							);
						}
						return false;
					}
				);
			},
			heartbeat_register: function() {
				$( document ).on(
					'heartbeat-send',
					function ( event, data ) {
						data.ahrefs_seo_content = true;
					}
				);
				$( document ).on(
					'heartbeat-tick',
					function ( event, data ) {
						if ( data.ahrefs_seo_content ) {
							if ( data.ahrefs_seo_content.need_update ) {
								content.set_ping_interval( 60, 5 );
							}
						}
					}
				);
			},
			set_keyword_error_handler : function() {
				jQuery( document ).ajaxError(
					function(event, request, settings) {
						if ( settings && settings.url && ( settings.url.indexOf( 'action=ahrefs_content_get_keyword_popup' ) >= 0 ) ) {
							console.log( event, request, settings );
							$( '#TB_ajaxContent' ).append( '<div class="notice notice-error is-dismissible"><p>Oops, there was an error while loading the keywords list. Please try again.</p></div>' ).css( 'height','120px' );
						}
					}
				);
			},
		}
		content.display();
		content.heartbeat_register();
		content.init_manual_update();
		content.set_keyword_error_handler();
		// check for updates once per 2 minutes.
		content.set_ping_interval( 120 );
	})( jQuery );
}
