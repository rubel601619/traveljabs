/**
 * Opening hours repeater for the Clinic Details field group.
 */
(function () {
	'use strict';

	var CONTAINER_ID = 'traveljabs-clinic-opening-hours';
	var DAYS = [
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday'
	];

	function getContainer() {
		return document.getElementById(CONTAINER_ID);
	}

	function addRow(container) {
		var tbody = container.querySelector('tbody');
		var template = container.querySelector('.traveljabs-hours-row.is-template');
		var rows;
		var previousDay = '';
		var previousDayIndex;
		var newDay = DAYS[0];

		if (!tbody || !template) {
			return;
		}

		rows = tbody.querySelectorAll('.traveljabs-hours-row:not(.is-template)');

		if (rows.length) {
			previousDay = rows[ rows.length - 1 ].querySelector('input[name*="[day]"]');
			previousDay = previousDay ? previousDay.value.trim().toLowerCase() : '';
			previousDayIndex = DAYS.map(function (day) {
				return day.toLowerCase();
			}).indexOf(previousDay);

			if (previousDayIndex !== -1) {
				newDay = DAYS[ ( previousDayIndex + 1 ) % DAYS.length ];
			}
		}

		var row = template.cloneNode(true);

		row.classList.remove('is-template');
		row.removeAttribute('hidden');

		Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
			input.value = '';
		});

		var dayInput = row.querySelector('input[name*="[day]"]');

		if (dayInput) {
			dayInput.value = newDay;
		}

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
