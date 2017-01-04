<?php

	Class extension_maplocationfield extends Extension{

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_maplocation`");
			return true;
		}

		public function install() {
			try {
				Symphony::Database()->query("
					CREATE TABLE `tbl_fields_maplocation` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`field_id` int(11) unsigned NOT NULL,
						`default_location` varchar(60) NOT NULL,
						`default_location_coords` varchar(60) NOT NULL,
						`default_zoom` int(11) unsigned NOT NULL,
						`api_key` text default NULL,
						PRIMARY KEY (`id`),
						UNIQUE KEY `field_id` (`field_id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
				");
			} catch (Exception $e) {
				return false;
			}
			return true;
		}

		public function update($previousVersion = false) {
			$status = array();

			// Install missing tables
			$status[] = $this->install();

			if (version_compare($previousVersion, '3.4.0', '<')) {

				// Add API-Key column
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_maplocation` LIKE 'api_key'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_maplocation` ADD `api_key` text default NULL"
					);
				}
				
			}

			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/*
			Modified from:
			http://www.kevinbradwick.co.uk/developer/php/free-to-script-to-calculate-the-radius-of-a-coordinate-using-latitude-and-longitude
		*/
		public static function geoRadius($lat, $lng, $rad, $kilometers=false) {
			$radius = ($kilometers) ? ($rad * 0.621371192) : $rad;

			(float)$dpmLAT = 1 / 69.1703234283616;

			// Latitude calculation
			(float)$usrRLAT = $dpmLAT * $radius;
			(float)$latMIN = $lat - $usrRLAT;
			(float)$latMAX = $lat + $usrRLAT;

			// Longitude calculation
			(float)$mpdLON = 69.1703234283616 * cos($lat * (pi/180));
			(float)$dpmLON = 1 / $mpdLON; // degrees per mile longintude
			$usrRLON = $dpmLON * $radius;

			$lonMIN = $lng - $usrRLON;
			$lonMAX = $lng + $usrRLON;

			return array("lonMIN" => $lonMIN, "lonMAX" => $lonMAX, "latMIN" => $latMIN, "latMAX" => $latMAX);
		}

		/*
		Calculate distance between two lat/long pairs
		*/
		public static function geoDistance($lat1, $lon1, $lat2, $lon2, $unit) {
			$theta = $lon1 - $lon2;
			$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
			$dist = acos($dist);
			$dist = rad2deg($dist);
			$miles = $dist * 60 * 1.1515;

			$unit = strtolower($unit);

			$distance = 0;

			if ($unit == "k") {
				$distance = ($miles * 1.609344);
			} else if ($unit == "n") {
				$distance = ($miles * 0.8684);
			} else {
				$distance = $miles;
			}

			return round($distance, 1);
		}

	}
