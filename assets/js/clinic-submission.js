(function ($) {
	'use strict';

	$(document).on('click', '.traveljabs-select-image', function (event) {
		event.preventDefault();

		var button = $(this);
		var frame = wp.media({
			title: button.data('title') || 'Select clinic image',
			button: { text: 'Use this image' },
			multiple: false,
			type: 'image'
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#traveljabs-clinic-featured-image').val(attachment.id);
			button.siblings('.traveljabs-selected-image').text(attachment.filename || attachment.url);
		});

		frame.open();
	});

	$(document).on('submit', '.traveljabs-clinic-submission__form', function () {
		$(this).find('.traveljabs-submit-clinic').prop('disabled', true);
	});
}(jQuery));
