/**
 * Main JavaScript
 * Tow.Expert
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

// create or use existing site scope
var Site = Site || {};
var TowExpert = TowExpert || {};

// make sure variable cache exists
Site.variable_cache = Site.variable_cache || {};


/**
 * Check if site is being displayed on mobile.
 * @return boolean
 */
Site.is_mobile = function() {
	var result = false;

	// check for cached value
	if ('mobile_version' in Site.variable_cache) {
		result = Site.variable_cache['mobile_version'];

	} else {
		// detect if site is mobile
		var elements = document.getElementsByName('viewport');

		// check all tags and find `meta`
		for (var i=0, count=elements.length; i<count; i++) {
			var tag = elements[i];

			if (tag.tagName == 'META') {
				result = true;
				break;
			}
		}

		// cache value so next time we are faster
		Site.variable_cache['mobile_version'] = result;
	}

	return result;
};

function Search() {
	var self = this;

	self.header = null;
	self.search_form = null;
	self.input_field = null;
	self.gps_links = null;
	self.vehicle_type = null;
	self.results = null;
	self.show_form = null;
	self.rate_up = null;
	self.rate_down = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.header = $('header');
		self.search_form = self.header.find('form');
		self.vehicle_type = self.search_form.find('input[name=type]')
		self.input_field = self.search_form.find('input[name=query]');
		self.gps_links = $('a.gps');
		self.show_form = self.header.find('a.show_search_form');
		self.results = $('div#results div.result');
		self.rate_up = self.results.find('a.rate_up');
		self.rate_down = self.results.find('a.rate_down');

		// connect events
		self.gps_links.click(self._handle_gps_click);
		self.show_form.click(self._handle_show_form_click);
		self.vehicle_type.change(self._handle_type_change);
		self.input_field
				.focus(self._handle_input_focus)
				.blur(self._handle_input_blur);
		self.results.find('div.summary').click(self._handle_result_click);
		self.rate_up.click(self._handle_rate_click);
		self.rate_down.click(self._handle_rate_click);
	}

	/**
	 * Handle clicking on result.
	 *
	 * @param object
	 */
	self._handle_result_click = function(event) {
		event.preventDefault();
		var summary = $(this);
		var result = summary.closest('div.result');

		self.results.not(result).removeClass('detailed');
		result.toggleClass('detailed');
	};

	/**
	 * Handle clicking on rate button.
	 *
	 * @param object event
	 */
	self._handle_rate_click = function(event) {
		event.preventDefault();

		// prepare call
		var button = $(this);
		var company = button.data('company');
		var direction = button.data('direction');
		var communicator = new Communicator('listing');
		var data = {
				'id': company,
				'positive': direction
			};

		// send data to server
		communicator
			.on_success(self._handle_rate_success)
			.on_error(self._handle_rate_error)
			.get('rate_company', data);
	};

	/**
	 * Handle server response.
	 *
	 * @param object data
	 */
	self._handle_rate_success = function(data) {
		if (data.error)
			return;

		// find content to update
		var likes = $('a.rate_up[data-company=' + data.company + ']').find('span');
		var dislikes = $('a.rate_down[data-company=' + data.company + ']').find('span');

		// update elements
		likes.html(data.likes);
		dislikes.html(data.dislikes);
	};

	/**
	 * Hanlde error during communication with server.
	 *
	 * @param object xhr
	 * @param string error_code
	 * @param string message
	 */
	self._handle_rate_error = function(xhr, error_code, message) {
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
	 * Handle clicking on show form link.
	 *
	 * @param object event
	 */
	self._handle_show_form_click = function(event) {
		event.preventDefault();
		self.search_form.toggleClass('visible');
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

		// show form
		self.search_form.addClass('visible');

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

/**
 * Function called when document and images have been completely loaded.
 */
Site.on_load = function() {
	TowExpert.search_bar = new Search();
	TowExpert.contact_from = new Contact();

	if ($('div#map').length > 0)
		TowExpert.map = new Map();
};


// connect document `load` event with handler function
$(Site.on_load);
