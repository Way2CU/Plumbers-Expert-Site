/**
 * Main JavaScript
 * Tow.Expert
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors:
 */

var TowExpert = TowExpert || {};


function SearchBar() {
	var self = this;

	self.search_form = null;
	self.input_field = null;
	self.gps_links = null;
	self.vehicle_type = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.search_form = $('header form');
		self.input_field = self.search_form.find('input[type=search]');

		self.gps_links = $('a.gps');
		self.gps_links.click(self._handle_gps_click);
	}

	/**
	 * Handle clicking on locate link.
	 *
	 * @param object event
	 */
	self._handle_gps_click = function(event) {
		event.preventDefault();

		// make sure geolocation is supported
		if (typeof navigator.geolocation == 'undefined')
			return;

		// animate gps links while we search for location
		self.gps_links.addClass('active');

		// ask browser to provide user location
		var options = {
				enableHighAccuracy: true,
				timeout: 20000,
				maximumAge: 20 * 60 * 1000  // allow 20 minutes old data
			};

		navigator.geolocation.getCurrentPosition(
						self._handle_gps_lock,
						self._handle_gps_error,
						options
					);
	};

	/**
	 * Handle acquiring GPS location.
	 *
	 * @param object position
	 */
	self._handle_gps_lock = function(position) {
		// stop animation and denote location as locked
		self.gps_links.removeClass('active');
		self.gps_links.addClass('locked');

		// put coordinates in input field
		coordinates = position.coords;
		self.input_field.val(coordinates.latitude + ' ' + coordinates.longitude);
	};

	/**
	 * Handle error while getting GPS location.
	 *
	 * @param object error
	 */
	self._handle_gps_error = function(error) {
		self.gps_links.removeClass('active locked');
	};

	// finalize object
	self._init();
}


function on_site_load() {
	TowExpert.search_bar = new SearchBar();
}

$(on_site_load);
