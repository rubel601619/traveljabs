(function () {
	'use strict';

	var config = window.traveljabsDestinationSearch || {};
	var searches = document.querySelectorAll('.traveljabs-destination-search');

	Array.prototype.forEach.call(searches, function (search) {
		var input = search.querySelector('.traveljabs-destination-search__input');
		var results = search.querySelector('.traveljabs-destination-search__results');
		var destinations = [];

		function openResults() {
			search.classList.add('is-open');
		}

		function closeResults() {
			search.classList.remove('is-open');
		}

		function render(items) {
			results.innerHTML = '';

			if (!items.length && input.value.trim()) {
				var empty = document.createElement('li');
				empty.textContent = config.notFoundText || 'No destination found.';
				results.appendChild(empty);
				return;
			}

			items.forEach(function (item) {
				var listItem = document.createElement('li');
				var link = document.createElement('a');

				link.href = item.link;
				link.textContent = item.title;
				listItem.appendChild(link);
				results.appendChild(listItem);
			});
		}

		function showMessage(message, className) {
			results.innerHTML = '';
			var item = document.createElement('li');
			item.textContent = message;
			item.className = className || '';
			results.appendChild(item);
		}

		function filterDestinations() {
			var query = input.value.trim().toLowerCase();
			var matches = destinations.filter(function (item) {
				return item.title.toLowerCase().indexOf(query) !== -1;
			});

			openResults();
			render(matches);
		}

		async function loadDestinations() {
			var page = 1;
			var allDestinations = [];
			var totalPages = 1;

			do {
				var url = new URL(config.restUrl, window.location.origin);
				url.searchParams.set('_fields', 'id,title,link');
				url.searchParams.set('per_page', '100');
				url.searchParams.set('page', page);
				url.searchParams.set('orderby', 'title');
				url.searchParams.set('order', 'asc');

				var response = await fetch(url.toString());

				if (!response.ok) {
					throw new Error('Destination request failed');
				}

				totalPages = parseInt(response.headers.get('X-WP-TotalPages'), 10) || 1;
				var items = await response.json();
				allDestinations = allDestinations.concat(items.map(function (item) {
					return {
						id: item.id,
						title: item.title.rendered,
						link: item.link
					};
				}));
				page += 1;
			} while (page <= totalPages);

			return allDestinations;
		}

		showMessage(config.loadingText || 'Loading destinations...', 'is-loading');
		input.addEventListener('focus', function () {
			openResults();

			if (destinations.length) {
				render(destinations);
			}
		});
		input.addEventListener('keydown', function (event) {
			if ('Escape' === event.key) {
				closeResults();
				input.blur();
			}
		});
		document.addEventListener('click', function (event) {
			if (!search.contains(event.target)) {
				closeResults();
			}
		});

		loadDestinations()
			.then(function (items) {
				destinations = items;
				input.disabled = false;
				input.addEventListener('input', filterDestinations);
				results.innerHTML = '';
			})
			.catch(function () {
				showMessage(config.errorText || 'Could not load destinations. Please try again.', 'is-error');
			});
	});
})();
