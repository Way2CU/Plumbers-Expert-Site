/**
 * Main JavaScript
 * Tow.Expert
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var TowExpert = TowExpert || {};


function Search() {
	var self = this;

	self.search_form = null;
	self.input_field = null;
	self.gps_links = null;
	self.vehicle_type = null;
	self.results = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.search_form = $('header form');
		self.vehicle_type = self.search_form.find('input[name=type]')
		self.input_field = self.search_form.find('input[name=query]');
		self.gps_links = $('a.gps');
		self.results = $('div#results div.result');

		// connect events
		self.gps_links.click(self._handle_gps_click);
		self.vehicle_type.change(self._handle_type_change);
		self.input_field
				.focus(self._handle_input_focus)
				.blur(self._handle_input_blur);
		self.results.find('div.summary').click(self._handle_result_click);
	}

	self._handle_result_click = function(event) {
		event.preventDefault();
		var summary = $(this);
		var result = summary.closest('div.result');

		self.results.not(result).removeClass('detailed');
		result.toggleClass('detailed');
	};

	/**
	 * Handle input field gaining focus.
	 *
	 * @param object event
	 */
	self._handle_input_focus = function(event) {
		self.input_field.parent().addClass('focus');
	};

	/**
	 * Handle input field loosing focus.
	 *
	 * @param object event
	 */
	self._handle_input_blur = function(event) {
		self.input_field.parent().removeClass('focus');
	};

	/**
	 * Handle changing vehicle type.
	 *
	 * @param object event
	 */
	self._handle_type_change = function(event) {
		// empty search field, no need to refresh page
		if (self.input_field.val() == '')
			return;

		// prepare data
		var data = {
				type: self.vehicle_type.filter(':checked').val(),
				query: self.input_field.val()
			};

		// reload page
		window.location = self.search_form.attr('action') + '?' + $.param(data);
	};

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


function Contact() {
	var self = this;

	self.button_contact = null;
	self.button_cancel = null;
	self.container = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.container = $('footer form');
		self.button_contact = $('footer div.left_container a');
		self.button_cancel = self.container.find('button[type=button]');

		// connect events
		self.button_contact.click(self._handle_contact_click);
		self.button_cancel.click(self._handle_cancel_click);
		self.container.on('analytics-event', self._handle_submission);
	}

	/**
	 * Handle successful submission.
	 *
	 * @param object event
	 */
	self._handle_submission = function(event) {
		self.container.removeClass('visible');
	};

	/**
	 * Handle clicking on contact us link.
	 *
	 * @param object event
	 */
	self._handle_contact_click = function(event) {
		event.preventDefault();
		self.container.addClass('visible');
	};

	/**
	 * Handle clicking on form cancel button.
	 *
	 * @param object event
	 */
	self._handle_cancel_click = function(event) {
		event.preventDefault();
		self.container.removeClass('visible');
	};

	// finalize object
	self._init();
}


function on_site_load() {
	TowExpert.search_bar = new Search();
	TowExpert.contact_from = new Contact();

	if ($('div#map').length > 0)
		TowExpert.map = new Map();
}

$(on_site_load);
