( function ( $ ) {
	function toggleConditional( checkboxId ) {
		var $box = $( '#' + checkboxId );
		if ( ! $box.length ) {
			return;
		}
		var on = $box.is( ':checked' );
		$( '[data-fbmsl-show-when="' + checkboxId + '"]' ).toggleClass( 'fbmsl-is-hidden', ! on );
	}

	function toggleItemsPermalink() {
		var on =
			$( '#fbmsl_enable_singles' ).is( ':checked' ) ||
			$( '#fbmsl_enable_items_archive' ).is( ':checked' );
		$( '#fbmsl_items_permalink_wrap' ).toggleClass( 'fbmsl-is-hidden', ! on );
	}

	function toggleColumns() {
		var isList = $( '#fbmsl_layout' ).val() === 'list';
		$( '.fbmsl-columns-row' ).toggleClass( 'fbmsl-is-hidden', isList );
	}

	$( function () {
		$( '.fbmsl-color-picker' ).wpColorPicker();

		toggleConditional( 'fbmsl_enable_category_archives' );
		toggleItemsPermalink();
		toggleColumns();

		$( document ).on(
			'change',
			'#fbmsl_enable_singles, #fbmsl_enable_items_archive',
			toggleItemsPermalink
		);
		$( document ).on( 'change', '#fbmsl_enable_category_archives', function () {
			toggleConditional( 'fbmsl_enable_category_archives' );
		} );
		$( document ).on( 'change', '#fbmsl_layout', toggleColumns );
	} );
}( jQuery ) );
