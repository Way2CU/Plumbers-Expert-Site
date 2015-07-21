/**
 * Map JavaScript
 * Tow.Expert
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var TowExpert = TowExpert || {};


function Map() {
	var self = this;

	self.map = null;
	self.container = null;
	self.overlay = null;
	self.button_show = null;
	self.button_hide = null;
	self.center = {};
	self.initial_zoom = 9;
	self.bounds = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.container = $('div#map');
		self.overlay = self.container.find('div.overlay');
		self.button_show = self.overlay.find('a.show');
		self.button_hide = self.container.find('a.hide');

		// store our current location
		self.center = {
				lat: self.container.data('latitude'),
				lng: self.container.data('longitude')
			};

		// create Google Map
		var options = {
			backgroundColor: 'white',
			noClear: true,
			center: self.center,
			zoom: self.initial_zoom,
			minZoom: 7,
			mapTypeControl: false,
			panControl: false,
			rotateControl: false,
			scrollwheel: true,
			streetViewControl: false,
		};
		self.map = new google.maps.Map(self.container[0], options);

		// connect events
		self.button_show.click(self._handle_show_click);
		self.button_hide.click(self._handle_hide_click);

		// create markers on map
		self._create_markers();
	}

	/**
	 * Create markers for all the results on the page.
	 */
	self._create_markers = function() {
		var results = $('div#results div.result');

		// create bounds object
		self.bounds = new google.maps.LatLngBounds();

		// create our own location marker
		var base = $('base').attr('href');
		new google.maps.Marker({
			title: language_handler.getText(null, 'your_location'),
			position: self.center,
			map: self.map,
			animation: google.maps.Animation.DROP,
			icon: base + '/site/images/pin.png'
		});

		// self.bounds.extend(self.center);

		// create marker for each result
		results.each(function(index) {
			var result = $(this);
			var title = result.find('h3').html();
			var position = new google.maps.LatLng(
				result.data('latitude'),
				result.data('longitude')
				);

			// create marker
			var marker = new google.maps.Marker({
					title: title,
					position: position,
					map: self.map,
					animation: google.maps.Animation.DROP
				});

			// extend map bounds with marker position
			self.bounds.extend(marker.getPosition());
		});
	};

	/**
	 * Handle clicking on show map button.
	 *
	 * @param object event
	 */
	self._handle_show_click = function(event) {
		event.preventDefault();

		// change map state
		self.container.addClass('expanded');

		// let map know we've resized
		setTimeout(function() {
			google.maps.event.trigger(self.map, 'resize');
			self.map.fitBounds(self.bounds);
		}, 500);
	};

	/**
	 * Handle clicking on hide map button.
	 *
	 * @param object event
	 */
	self._handle_hide_click = function(event) {
		event.preventDefault();

		// change map state
		self.container.removeClass('expanded');

		// let map know we've resized
		setTimeout(function() {
			google.maps.event.trigger(self.map, 'resize');
		}, 500);
	};

	// finalize object
	self._init();
}
