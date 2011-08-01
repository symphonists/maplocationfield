<?php

	require_once(CORE . '/class.cacheable.php');

	Class fieldMapLocation extends Field{

		private $_geocode_cache_expire = 60; // minutes

		// defaults used when user doesn't enter defaults when adding field to section
		private $_default_location = 'London, England';
		private $_default_coordinates = '51.58129468879224, -0.554702996875005'; // London, England
		private $_default_zoom = 3;

		private $_filter_origin = array();

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Map Location';
		}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function mustBeUnique(){
			return true;
		}

		public function canFilter(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `latitude` double default NULL,
				  `longitude` double default NULL,
				  `centre` varchar(255) default NULL,
				  `zoom` int(11) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `latitude` (`latitude`),
				  KEY `longitude` (`longitude`)
				) TYPE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		private function __geocodeAddress($address, $can_return_default=true) {
			$coordinates = null;

			$cache_id = md5('maplocationfield_' . $address);
			$cache = new Cacheable($this->_engine->Database);
			$cachedData = $cache->check($cache_id);

			// no data has been cached
			if(!$cachedData) {

				include_once(TOOLKIT . '/class.gateway.php');

				$ch = new Gateway;
				$ch->init();
				$ch->setopt('URL', 'http://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&sensor=false');
				$response = json_decode($ch->exec());

				$coordinates = $response->results[0]->geometry->location;

				if ($coordinates && is_object($coordinates)) {
					$cache->write($cache_id, $coordinates->lat . ', ' . $coordinates->lng, $this->_geocode_cache_expire); // cache lifetime in minutes
				}

			}
			// fill data from the cache
			else {
				$coordinates = $cachedData['data'];
			}

			// coordinates is an array, split and return
			if ($coordinates && is_object($coordinates)) {
				return $coordinates->lng . ', ' . $coordinates->lat;
			}
			// return comma delimeted string
			elseif ($coordinates) {
				return $coordinates;
			}
			// return default coordinates
			elseif ($return_default) {
				return $this->_default_coordinates;
			}
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label('Default Marker Location');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][default_location]', $this->get('default_location')));
			$wrapper->appendChild($label);

			$label = Widget::Label('Default Zoom Level');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][default_zoom]', $this->get('default_zoom')));
			$wrapper->appendChild($label);

			$this->appendShowColumnCheckbox($wrapper);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['default_location'] = $this->get('default_location');
			$fields['default_zoom'] = $this->get('default_zoom');

			if(!$fields['default_location']) $fields['default_location'] = $this->_default_location;
			$fields['default_location_coords'] = self::__geocodeAddress($fields['default_location']);

			if(!$fields['default_zoom']) $fields['default_zoom'] = $this->_default_zoom;

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");

			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL, $entry_id=NULL){
			if (class_exists('Administration') && Administration::instance()->Page) {
				Administration::instance()->Page->addScriptToHead('http://maps.google.com/maps/api/js?sensor=false', 79);
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/maplocationfield/assets/maplocationfield.publish.css', 'screen', 78);
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/maplocationfield/assets/maplocationfield.publish.js', 80);
			}

			// input values
			$coordinates = array($data['latitude'], $data['longitude']);
			$centre = $data['centre'];
			$zoom = $data['zoom'];

			// get defaults for new entries
			if (reset($coordinates) == null) $coordinates = explode(',', $this->get('default_location_coords'));
			if ($centre == null) $centre = $this->get('default_location_coords');
			if ($zoom == null) $zoom = $this->get('default_zoom');

			$label = Widget::Label('Marker Latitude/Longitude');
			$label->setAttribute('class', 'coordinates');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][coordinates]'.$fieldnamePostfix, join(', ', $coordinates)));
			$wrapper->appendChild($label);

			$label = Widget::Label('Centre Latitude/Longitude');
			$label->setAttribute('class', 'centre');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][centre]'.$fieldnamePostfix, $centre));
			$wrapper->appendChild($label);

			$label = Widget::Label('Zoom Level');
			$label->setAttribute('class', 'zoom');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][zoom]'.$fieldnamePostfix, $zoom));
			$wrapper->appendChild($label);
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			if (is_array($data)) {
				$coordinates = split(',', $data['coordinates']);

				$data = array(
					'latitude' => trim($coordinates[0]),
					'longitude' => trim($coordinates[1]),
					'centre' => $data['centre'],
					'zoom' => $data['zoom'],
				);
			}
			else {
				// Check that the $centre is actually a coordinate
				if (!preg_match('/^(-?[.0-9]+),\s?(-?[.0-9]+)$/', $data)) {
					$data = self::__geocodeAddress($data);
				}

				$coordinates = split(',', $data);

				$data = array(
					'latitude' => trim($coordinates[0]),
					'longitude' => trim($coordinates[1]),
					'centre' => $data,
					'zoom' => $this->get('default_zoom')
				);
			}

			return $data;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			$field = new XMLElement($this->get('element_name'), null, array(
				'latitude' => $data['latitude'],
				'longitude' => $data['longitude'],
			));

			$map = new XMLElement('map', null, array(
				'zoom' => $data['zoom'],
				'centre' => $data['centre']
			));
			$field->appendChild($map);

			if (count($this->_filter_origin['latitude']) > 0) {
				$distance = new XMLElement('distance');
				$distance->setAttribute('from', $this->_filter_origin['latitude'] . ',' . $this->_filter_origin['longitude']);
				$distance->setAttribute('distance', extension_maplocationfield::geoDistance($this->_filter_origin['latitude'], $this->_filter_origin['longitude'], $data['latitude'], $data['longitude'], $this->_filter_origin['unit']));
				$distance->setAttribute('unit', ($this->_filter_origin['unit'] == 'k') ? 'km' : 'miles');
				$field->appendChild($distance);
			}

			$wrapper->appendChild($field);
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			if (empty($data)) return;

			$zoom = (int)$data['zoom'] - 2;
			if ($zoom < 1) $zoom = 1;

			return sprintf(
				"<img src='http://maps.google.com/maps/api/staticmap?center=%s&zoom=%d&size=160x90&sensor=false&markers=color:red|size:small|%s' alt=''/>",
				$data['centre'],
				$zoom,
				implode(',', array($data['latitude'], $data['longitude']))
			);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			// Symphony by default splits filters by commas. We want commas, so
			// concatenate filters back together again putting commas back in
			$data = join(',', $data);

			/*
			within 20 km of 10.545, -103.1
			within 2km of 1 West Street, Burleigh Heads
			within 500 miles of England
			*/

			// is a "within" radius filter
			if(preg_match('/^within/i', $data)){
				$field_id = $this->get('id');

				// parse out individual filter parts
				preg_match('/^within ([0-9]+)\s?(km|mile|miles) of (.+)$/', $data, $filters);

				$radius = trim($filters[1]);
				$unit = strtolower(trim($filters[2]));
				$origin = trim($filters[3]);

				$lat = null;
				$lng = null;

				// is a lat/long pair
				if (preg_match('/^(-?[.0-9]+),\s?(-?[.0-9]+)$/', $origin, $latlng)) {
					$lat = $latlng[1];
					$lng = $latlng[2];
				}
				// otherwise the origin needs geocoding
				else {
					$geocode = $this->__geocodeAddress($origin);
					if ($geocode) $geocode = explode(',', $geocode);
					$lat = trim($geocode[0]);
					$lng = trim($geocode[1]);
				}

				// if we don't have a decent set of coordinates, we can't query
				if (is_null($lat) || is_null($lng)) return true;

				$this->_filter_origin['latitude'] = $lat;
				$this->_filter_origin['longitude'] = $lng;
				$this->_filter_origin['unit'] = $unit[0];

				// build the bounds within the query should look
				$radius = extension_maplocationfield::geoRadius($lat, $lng, $radius, ($unit[0] == 'k'));

				$where .= sprintf(
					" AND `t%d`.`latitude` BETWEEN %s AND %s AND `t%d`.`longitude` BETWEEN %s AND %s",
					$field_id, $radius['latMIN'], $radius['latMAX'],
					$field_id, $radius['lonMIN'], $radius['lonMAX']
				);

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";

			}

			return true;
		}

	}
