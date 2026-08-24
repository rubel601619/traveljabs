/**
 * Opening hours repeater for the Clinic Details field group.
 */
(function () {
	'use strict';

	var CONTAINER_ID = 'traveljabs-clinic-opening-hours';

	function getContainer() {
		return document.getElementById(CONTAINER_ID);
	}

	function addRow(container) {
		var tbody = container.querySelector('tbody');
		var template = container.querySelector('.traveljabs-hours-row.is-template');

		if (!tbody || !template) {
			return;
		}

		var row = template.cloneNode(true);

		row.classList.remove('is-template');
		row.removeAttribute('hidden');

		Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
			input.value = '';
		});

		tbody.insertBefore(row, template);

		var firstInput = row.querySelector('input');

		if (firstInput) {
			firstInput.focus();
		}
	}

	function onAddClick(event) {
		var trigger = event.target.closest('.traveljabs-hours-add');
		var container = getContainer();

		if (!trigger || !container || !container.contains(trigger)) {
			return;
		}

		event.preventDefault();
		addRow(container);
	}

	function onRemoveClick(event) {
		var trigger = event.target.closest('.traveljabs-hours-remove');
		var container = getContainer();

		if (!trigger || !container || !container.contains(trigger)) {
			return;
		}

		event.preventDefault();

		var row = trigger.closest('.traveljabs-hours-row');

		if (row && !row.classList.contains('is-template')) {
			row.parentNode.removeChild(row);
		}
	}

	document.addEventListener('click', onAddClick);
	document.addEventListener('click', onRemoveClick);
})();
