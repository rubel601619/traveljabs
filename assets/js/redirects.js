/**
 * Multi-source repeater for the Traveljabs Redirects form.
 *
 * The + button adds another source field; each additional row gets a
 * - button. At least one source row always remains. Client-side checks are
 * a convenience only; the server re-validates everything.
 */
(function () {
	'use strict';

	var wrap = document.getElementById('traveljabs-sources');

	if (!wrap) {
		return;
	}

	function sourceRows() {
		return wrap.querySelectorAll('.tj-source-row');
	}

	function refreshState() {
		var rows = sourceRows();
		var single = rows.length <= 1;

		Array.prototype.forEach.call(rows, function (row) {
			var remove = row.querySelector('.tj-remove-source');

			if (remove) {
				remove.disabled = single;
			}
		});
	}

	function markDuplicates() {
		var seen = {};
		var rows = sourceRows();

		Array.prototype.forEach.call(rows, function (row) {
			var input = row.querySelector('.tj-source-input');
			var value = input ? input.value.trim().toLowerCase() : '';

			if (value === '') {
				if (input) {
					input.classList.remove('tj-duplicate');
				}
				return;
			}

			var duplicate = Object.prototype.hasOwnProperty.call(seen, value);

			if (duplicate) {
				seen[value] = true;
			}

			if (input) {
				input.classList.toggle('tj-duplicate', duplicate);
			}
		});
	}

	wrap.addEventListener('click', function (event) {
		var add = event.target.closest('.tj-add-source');

		if (add) {
			event.preventDefault();

			var currentRow = add.closest('.tj-source-row');

			if (currentRow) {
				var clone = currentRow.cloneNode(true);
				var inputs = clone.querySelectorAll('input');

				Array.prototype.forEach.call(inputs, function (input) {
					input.value = '';
					input.classList.remove('tj-duplicate');
				});

				currentRow.parentNode.insertBefore(clone, currentRow.nextSibling);

				var firstInput = clone.querySelector('input');

				if (firstInput) {
					firstInput.focus();
				}

				refreshState();
			}

			return;
		}

		var remove = event.target.closest('.tj-remove-source');

		if (remove && !remove.disabled) {
			event.preventDefault();

			var row = remove.closest('.tj-source-row');

			if (row && sourceRows().length > 1) {
				row.parentNode.removeChild(row);
				refreshState();
				markDuplicates();
			}
		}
	});

	wrap.addEventListener('input', function (event) {
		if (event.target.classList.contains('tj-source-input')) {
			markDuplicates();
		}
	});

	refreshState();
})();
