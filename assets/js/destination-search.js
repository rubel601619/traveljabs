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

                link.href = item.url;
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
            var response = await fetch(config.restUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Destination request failed');
            }

            var responseData = await response.json();

            if (
                !responseData ||
                !responseData.success ||
                !Array.isArray(responseData.data)
            ) {
                throw new Error('Invalid destination response');
            }

            return responseData.data.map(function (item) {
                return {
                    id: item.id,
                    title: item.title,
                    url: item.url
                };
            });
        }

        /*
         * Initial loading state.
         */
        showMessage(
            config.loadingText || 'Loading destinations...',
            'is-loading'
        );

        /*
         * Open destination list when input receives focus.
         */
        input.addEventListener('focus', function () {
            openResults();

            if (destinations.length) {
                if (input.value.trim()) {
                    filterDestinations();
                } else {
                    render(destinations);
                }
            }
        });

        /*
         * Close results on Escape.
         */
        input.addEventListener('keydown', function (event) {
            if ('Escape' === event.key) {
                closeResults();
                input.blur();
            }
        });

        /*
         * Close results when clicking outside.
         */
        document.addEventListener('click', function (event) {
            if (!search.contains(event.target)) {
                closeResults();
            }
        });

        /*
         * Load all cached destinations.
         */
        loadDestinations()
            .then(function (items) {
                destinations = items;

                input.disabled = false;

                input.placeholder =
                    config.placeholderText ||
                    'Search the destination';

                input.addEventListener(
                    'input',
                    filterDestinations
                );

                results.innerHTML = '';
            })
            .catch(function () {
                input.disabled = true;

                showMessage(
                    config.errorText ||
                    'Could not load destinations. Please try again.',
                    'is-error'
                );
            });
    });
})();