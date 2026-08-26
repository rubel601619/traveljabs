(function () {
	'use strict';

	var config = window.traveljabsClinicSearch || {};
	var searches = document.querySelectorAll('[data-clinic-search]');
	var mapsPromise = null;
	var fallbackLocation = { lat: 51.5074, lng: -0.1278 };

	function haversineDistance(lat1, lng1, lat2, lng2) {
		var radius = 6371;
		var dLat = (lat2 - lat1) * Math.PI / 180;
		var dLng = (lng2 - lng1) * Math.PI / 180;
		var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
			Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
			Math.sin(dLng / 2) * Math.sin(dLng / 2);
		var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

		return radius * c;
	}

	function getUserLocation(callback) {
		if (!navigator.geolocation) {
			callback(fallbackLocation);
			return;
		}

		navigator.geolocation.getCurrentPosition(function (position) {
			callback({ lat: position.coords.latitude, lng: position.coords.longitude });
		}, function () {
			fetchLocationByIp(callback);
		});
	}

	function fetchLocationByIp(callback) {
		fetch('https://ipinfo.io/json')
			.then(function (response) {
				return response.ok ? response.json() : null;
			})
			.then(function (data) {
				if (data && data.loc) {
					var parts = data.loc.split(',');
					var latitude = parseFloat(parts[0]);
					var longitude = parseFloat(parts[1]);

					if (!isNaN(latitude) && !isNaN(longitude)) {
						callback({ lat: latitude, lng: longitude });
						return;
					}
				}

				callback(fallbackLocation);
			})
			.catch(function () {
				callback(fallbackLocation);
			});
	}

	function loadGoogleMaps(callback) {
		if (window.google && google.maps && google.maps.Map) {
			callback();
			return;
		}

		if (!config.apiKey) {
			return;
		}

		if (!mapsPromise) {
			mapsPromise = new Promise(function (resolve, reject) {
				var script = document.querySelector('script[src*="maps.googleapis.com/maps/api"]') || document.createElement('script');

				if (!script.src) {
					script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(config.apiKey);
					script.async = true;
					document.head.appendChild(script);
				}

				script.addEventListener('load', resolve, { once: true });
				script.addEventListener('error', reject, { once: true });
			});
		}

		mapsPromise.then(callback).catch(function () {});
	}

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>'"]/g, function (character) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				"'": '&#039;',
				'"': '&quot;'
			}[character];
		});
	}

	Array.prototype.forEach.call(searches, function (search) {
		var input = search.querySelector('.clinic-search-input');
		var button = search.querySelector('.clinic-search-btn');
		var list = search.querySelector('.clinic-list');
		var mapElement = search.querySelector('.clinic-map');
		var allClinics = (config.initialClinics || []).map(function (clinic) {
			return Object.assign({}, clinic);
		});
		var map = null;
		var markers = [];
		var infoWindows = [];
		var userLocation = fallbackLocation;

		function attachDistances(clinics) {
			clinics.forEach(function (clinic) {
				clinic.distance = clinic.latitude !== null && clinic.longitude !== null &&
					!isNaN(clinic.latitude) && !isNaN(clinic.longitude)
					? haversineDistance(userLocation.lat, userLocation.lng, clinic.latitude, clinic.longitude)
					: null;
			});
		}

		function clearMarkers() {
			markers.forEach(function (marker) { marker.setMap(null); });
			infoWindows.forEach(function (infoWindow) { infoWindow.close(); });
			markers = [];
			infoWindows = [];
		}

		function addMarkers(clinics) {
			if (!map) return;
			clearMarkers();
			var bounds = new google.maps.LatLngBounds();

			clinics.forEach(function (clinic) {
				if (clinic.latitude === null || clinic.longitude === null || isNaN(clinic.latitude) || isNaN(clinic.longitude)) return;
				var position = { lat: clinic.latitude, lng: clinic.longitude };
				bounds.extend(position);
				var marker = new google.maps.Marker({ position: position, map: map, title: clinic.title, animation: google.maps.Animation.DROP });
				var content = '<div class="gm-info-window">' +
					(clinic.thumbnail ? '<img src="' + escapeHtml(clinic.thumbnail) + '" alt="' + escapeHtml(clinic.title) + '" class="gm-thumb">' : '') +
					'<strong>' + escapeHtml(clinic.title) + '</strong><br>' +
					(clinic.address ? escapeHtml(clinic.address) + '<br>' : '') +
					(clinic.postcode ? escapeHtml(clinic.postcode) + '<br>' : '') +
					(clinic.phone ? '<a href="tel:' + escapeHtml(clinic.phone) + '">' + escapeHtml(clinic.phone) + '</a><br>' : '') +
					'<a href="' + escapeHtml(clinic.link) + '">View details</a></div>';
				var infoWindow = new google.maps.InfoWindow({ content: content });
				marker.addListener('click', function () { infoWindow.open(map, marker); });
				markers.push(marker);
				infoWindows.push(infoWindow);
			});

			if (markers.length === 1) {
				map.setCenter(markers[0].getPosition());
				map.setZoom(14);
			} else if (markers.length > 1) {
				map.fitBounds(bounds);
			}
		}

		function renderClinicList(clinics) {
			if (!clinics.length) {
				list.innerHTML = '<p class="text-muted">' + escapeHtml(config.noClinicsText || 'No clinics found.') + '</p>';
				return;
			}

			attachDistances(clinics);
			clinics = clinics.slice().sort(function (first, second) {
				return (first.distance === null ? Infinity : first.distance) - (second.distance === null ? Infinity : second.distance);
			});
			list.innerHTML = clinics.map(function (clinic) {
				var distance = clinic.distance === null ? '' : '<span class="clinic-distance">' + clinic.distance.toFixed(1) + ' km</span>';
				return '<div class="clinic-item" data-id="' + escapeHtml(clinic.id) + '">' +
					(clinic.thumbnail ? '<div class="clinic-thumb"><img src="' + escapeHtml(clinic.thumbnail) + '" alt="' + escapeHtml(clinic.title) + '"></div>' : '') +
					'<div class="clinic-info"><h3><a href="' + escapeHtml(clinic.link) + '">' + escapeHtml(clinic.title) + '</a></h3>' +
					(clinic.address ? '<p class="clinic-address">' + escapeHtml(clinic.address) + '</p>' : '') +
					'<p class="clinic-postcode">' + escapeHtml(clinic.postcode || '') + distance + '</p>' +
					(clinic.phone ? '<p class="clinic-phone"><a href="tel:' + escapeHtml(clinic.phone) + '">' + escapeHtml(clinic.phone) + '</a></p>' : '') +
					'</div></div>';
			}).join('');

			list.querySelectorAll('.clinic-item').forEach(function (item) {
				item.addEventListener('click', function () {
					var clinic = clinics.find(function (entry) { return String(entry.id) === item.dataset.id; });
					if (map && clinic && clinic.latitude !== null && clinic.longitude !== null) {
						map.setCenter({ lat: clinic.latitude, lng: clinic.longitude });
						map.setZoom(16);
					}
				});
			});
		}

		function localMatches(query) {
			if (!query) return allClinics;

			var normalizedQuery = query.toLowerCase().trim();
			var words = normalizedQuery.split(/\s+/);
			var queryWithoutSpaces = normalizedQuery.replace(/\s+/g, '');

			function clinicText(clinic) {
				return (clinic.title + ' ' + (clinic.address || '') + ' ' + (clinic.postcode || '') + ' ' + (clinic.content || '')).toLowerCase();
			}

			function matchAll(clinic) {
				var text = clinicText(clinic);
				var textWithoutSpaces = text.replace(/\s+/g, '');

				if (words.every(function (word) { return text.indexOf(word) !== -1; })) return true;
				if (textWithoutSpaces.indexOf(queryWithoutSpaces) !== -1) return true;

				var textWords = text.split(/\s+/);
				return words.every(function (word) {
					var noVowels = word.replace(/[aeiou]/g, '');

					if (noVowels.length >= 2) {
						return textWords.some(function (textWord) {
							return textWord.replace(/[aeiou]/g, '').indexOf(noVowels) !== -1;
						});
					}

					return text.indexOf(word) !== -1;
				});
			}

			var results = allClinics.filter(matchAll);

			if (results.length > 0) return results;

			results = allClinics.filter(function (clinic) {
				var text = clinicText(clinic);

				return words.some(function (word) { return text.indexOf(word) !== -1; });
			});

			if (results.length > 0) return results;

			attachDistances(allClinics);

			return allClinics.slice().sort(function (first, second) {
				var firstDistance = first.distance === null ? Infinity : first.distance;
				var secondDistance = second.distance === null ? Infinity : second.distance;

				return firstDistance - secondDistance;
			}).slice(0, 5);
		}

		function reRender() {
			var matches = localMatches(input.value.trim());
			renderClinicList(matches);
			addMarkers(matches);
		}

		function initMap() {
			loadGoogleMaps(function () {
				map = new google.maps.Map(mapElement, { zoom: 10, center: fallbackLocation, mapTypeId: google.maps.MapTypeId.ROADMAP });
				addMarkers(allClinics);
			});
		}

		renderClinicList(allClinics);
		initMap();
		getUserLocation(function (location) {
			userLocation = location;
			reRender();
		});
		input.addEventListener('input', function () {
			if (window.innerWidth >= 992) reRender();
		});
		button.addEventListener('click', reRender);
	});
})();
