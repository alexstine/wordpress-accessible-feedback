jQuery( document ).ready( function( $ ) {
	const form = $( '.accessible-feedback-form' );
	const feedback_item = $( form ).find( '#feedback_item' ).val();
	$( form ).submit(function() {
		const submit_button = $( this ).find( '#feedback_submit' ).val();
		const url = $( this ).attr( 'action' );
		const data = {
			'feedback_item': feedback_item
		};
		$.post( url, data, function( response ) {
			$( '.accessible-feedback-form-response' ).append( response ).attr( 'role', 'alert' );
			if ( 'Feedback collected, thank you!' == response ) {
				$( form ).hide();
			} else {
				$( feedback_item ).focus();
			}
		});
		return false;
	});
});