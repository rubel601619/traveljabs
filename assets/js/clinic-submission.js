(function ($) {
	'use strict';

	$(document).on('change', '.traveljabs-clinic-featured-image-upload', function () {
		var input = this;
		var preview = input.closest('.traveljabs-featured-image-field').querySelector('.traveljabs-featured-image-preview');

		if (!preview || !input.files || !input.files[0]) {
			return;
		}

		preview.src = '';
		preview.classList.add('is-empty');

		var selectedFile = input.files[0];
		var reader = new FileReader();
		reader.onload = function (event) {
			if (input.files[0] !== selectedFile) {
				return;
			}

			preview.src = event.target.result;
			preview.classList.remove('is-empty');
		};
		reader.readAsDataURL(selectedFile);
	});

	$(document).on('submit', '.traveljabs-clinic-submission__form', function () {
		$(this).find('.traveljabs-submit-clinic').prop('disabled', true);
	});
}(jQuery));
