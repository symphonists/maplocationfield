jQuery(document).ready(function() {	
	jQuery('fieldset .field-maplocation').each(function() {
		var field = new MapLocationField(jQuery(this));
	});
});

function MapLocationField(field) {
	
	// cache the field container DOM element (jQuery)
	this.field = field;
	
	// initial options
	this.options = {
		initial_zoom: 11,
		geocode_zoom: 10
	};
	
	// placeholders
	this.map = null;
	this.geocoder = null;
	this.marker = null;
	
	this.inputs = {
		marker: field.find('label.coordinates input'),
		centre: field.find('label.centre input'),
		zoom: field.find('label.zoom input')
	}
	
	// go!
	this.init();
};

MapLocationField.prototype.init = function() {
	var self = this;
	
	// hide the input fields
	for(var input in this.inputs) {
		this.inputs[input].parent().hide();
	}
	
	// build field HTML
	var html = jQuery(
		'<ul class="tabs">' +
			'<li class="map">Map</li>' +
			'<li class="edit">Edit Location</li>' +
		'</ul>' +
		'<div class="tab-panel tab-map">' +
			'<div class="gmap"></div>' +
		'</div>' +
		'<div class="tab-panel tab-edit">' +
			'<fieldset class="coordinates">' +
				'<label>Latitude/Longitude</label>' +
				'<input type="text" name="latitude" class="text"/><input type="text" name="longitude" class="text"/>' +
				'<input type="button" value="Update Map" class="button"/>' +
			'</fieldset>' +
			'<fieldset class="geocode">' +
				'<label>Address</label>' +
				'<input type="text" name="address" class="text"/>' +
				'<input type="button" value="Update Map" class="button"/>' +
			'</fieldset>' +
		'</div>'
	).prependTo(this.field);
	
	// bind tab events
	this.field.find('ul.tabs li').bind('click', function(e) {
		e.preventDefault();
		self.setActiveTab(jQuery(this).attr('class').split(' ')[0]);
	});
	
	// open the Map tab by default
	this.setActiveTab('map');
	
	// get initial map values from the DOM
	var initial_coordinates = this.parseLatLng(this.inputs.marker.val());
	var initial_centre = this.parseLatLng(this.inputs.centre.val());
	var initial_zoom = parseInt(this.inputs.zoom.val());
	
	var marker_latlng = new google.maps.LatLng(initial_coordinates[0], initial_coordinates[1]);
	var centre_latlng = new google.maps.LatLng(initial_centre[0], initial_centre[1]);
	
	// add the map
	this.map = new google.maps.Map(this.field.find('div.gmap')[0], {
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		mapTypeControl: false,
		zoom: initial_zoom,
		center: centre_latlng,
		scrollwheel: false
	});
	
	// add the marker
	this.marker = new google.maps.Marker({
		map: self.map,
		position: marker_latlng,
		draggable: true
	});
	
	// store the updated values to the DOM
	self.storeCoordinates(self.marker.getPosition());
	self.storeCentre();
	self.storeZoom();
	
	// bind events to store new values
	google.maps.event.addListener(this.marker, 'drag', function() {
		self.storeCoordinates(self.marker.getPosition());
	});
	google.maps.event.addListener(this.marker, 'dragend', function() {
		self.moveMarker(self.marker.getPosition(), true);
	});
	google.maps.event.addListener(this.map, 'center_changed', function() {
		self.storeCentre();
	});
	google.maps.event.addListener(this.map, 'zoom_changed', function() {
		self.storeZoom();
	});
	
	// create a geocoder
	this.geocoder = new google.maps.Geocoder();
	
	// bind edit tab actions
	this.field.find('fieldset.coordinates input.text').bind('keypress', function(e) {
		if(e.keyCode == 13) {
			e.preventDefault();
			self.editLatLng();
		}
	});
	this.field.find('fieldset.geocode input.text').bind('keypress', function(e) {
		if(e.keyCode == 13) {
			e.preventDefault();
			self.editAddress();
		}
	});
	this.field.find('fieldset.coordinates input.button').bind('click', function() { self.editLatLng() });
	this.field.find('fieldset.geocode input.button').bind('click', function() { self.editAddress() });
	
};

MapLocationField.prototype.geocodeAddress = function(address, success, fail) {
	var self = this;
	
	this.geocoder.geocode({ 'address': address }, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				success(results[0]);
			} else {
				fail();
			}
		}
	);
};

MapLocationField.prototype.moveMarker = function(position, centre, zoom) {
	this.marker.setPosition(position);
	this.storeCoordinates(this.marker.getPosition());
	if (centre) this.map.setCenter(position);
	if (zoom) this.map.setZoom(zoom);
};

MapLocationField.prototype.storeCoordinates = function(latLng) {
	this.inputs.marker.val(latLng.lat() + ', ' + latLng.lng());
	this.field.find('div.tab-edit input[name=latitude]').val(latLng.lat());
	this.field.find('div.tab-edit input[name=longitude]').val(latLng.lng());
}

MapLocationField.prototype.storeZoom = function() {
	this.inputs.zoom.val(this.map.getZoom());
}

MapLocationField.prototype.storeCentre = function() {
	var centre = this.map.getCenter();
	this.inputs.centre.val(centre.lat() + ', ' + centre.lng());
}

MapLocationField.prototype.parseLatLng = function(string) {
	return string.match(/-?\d+\.\d+/g);
}

MapLocationField.prototype.setActiveTab = function(tab_name) {
	var self = this;
	
	// hide all tab panels
	this.field.find('div.tab-panel').hide();
	
	// find the desired tab and activate the tab and its panel
	this.field.find('ul.tabs li').each(function() {
		var tab = jQuery(this);
		if (tab.hasClass(tab_name)) {
			tab.addClass('active');
			self.field.find('div.tab-' + tab_name).show();
		} else {
			tab.removeClass('active');
		}
	});
}

MapLocationField.prototype.editLatLng = function() {
	var fieldset = this.field.find('fieldset.coordinates');
	var lat = fieldset.find('input[name=latitude]').val();
	var lng = fieldset.find('input[name=longitude]').val();
	
	var position = new google.maps.LatLng(lat, lng);
	this.setActiveTab('map');
	this.moveMarker(position, true);
}

MapLocationField.prototype.editAddress = function() {
	var self = this;
	var fieldset = this.field.find('fieldset.geocode');
	
	var button = fieldset.find('input[type=button]');
	var address_field = fieldset.find('input[name=address]');

	var button_value = button.val();
	button.val('Loading...').attr('disabled', 'disabled');

	var label = fieldset.find('label');
	label.find('i').remove();

	self.geocodeAddress(
		address_field.val(),
		function(result) {
			self.setActiveTab('map');
			if (result.geometry.bounds) self.map.fitBounds(result.geometry.bounds);
			self.moveMarker(result.geometry.location, true);
			address_field.val('');
			button.val(button_value).removeAttr('disabled');
		},
		function() {
			button.val(button_value).removeAttr('disabled');
			label.append('<i>Address not found</i>')
		}
	);
}