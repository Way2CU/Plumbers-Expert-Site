<?php

/**
 * Module Template
 *
 * This module is a template to make process of starting a development of new module
 * fast and painless. This code reflects the state of system in general and should be
 * kept up-to-date with remainder of the system.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/manager.php');


final class QueryType {
	const CAR = 0;
	const MOTORCYCLE = 1;
	const TRUCK = 2;
}


class listing extends Module {
	private static $_instance;

	private $referrence = array(
					'car'			=> QueryType::CAR,
					'motorcycle'	=> QueryType::MOTORCYCLE,
					'truck'			=> QueryType::TRUCK
				);

	const GEOCODING_API = 'https://maps.googleapis.com/maps/api/geocode/json?address={address}&key={key}';
	const REGEX_ZIP = '/^\d{5,10}$/ui';
	const REGEX_COORDS = '/^\d{1,3}\.\d+\s\d{1,3}\.\d+$/ui';

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		parent::__construct(__FILE__);

		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$listing_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_listing'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$towing_menu->addChild('', new backend_MenuItem(
					$this->getLanguageConstant('menu_update'),
					url_GetFromFilePath($this->path.'images/update.svg'),
					window_Open(
						'listing_update',
						350,
						$this->getLanguageConstant('title_update'),
						true, true,
						backend_UrlMake($this->name, 'update')
					),
					$level=5
				));

			$listing_menu->addChild('', new backend_MenuItem(
					$this->getLanguageConstant('menu_index'),
					url_GetFromFilePath($this->path.'images/index.svg'),
					window_Open(
						'listing_index',
						650,
						$this->getLanguageConstant('title_index'),
						true, true,
						backend_UrlMake($this->name, 'index')
					),
					$level=5
				));

			$listing_menu->addSeparator(5);

			$listing_menu->addChild('', new backend_MenuItem(
					$this->getLanguageConstant('menu_set_api_key'),
					url_GetFromFilePath($this->path.'images/api_key.svg'),
					window_Open(
						'listing_api_key',
						350,
						$this->getLanguageConstant('title_api_key'),
						true, true,
						backend_UrlMake($this->name, 'api_key')
					),
					$level=5
				));

			$backend->addMenu($this->name, $listing_menu);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show_results':
					$this->tag_Results($params, $children);
					break;

				case 'show_company':
					$this->tag_Company($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'update':
					$this->updateDatabase();
					break;

				case 'update_commit':
					$this->updateDatabase_Commit();
					break;

				case 'api_key':
					$this->setKey();
					break;

				case 'api_key_save':
					$this->setKey_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$sql = "
			CREATE TABLE IF NOT EXISTS `listing_companies` (
				`id` int NOT NULL AUTO_INCREMENT,
				`oid` int NOT NULL,
				`name` varchar(150) NOT NULL,
				`address` varchar(255) NOT NULL,
				`city` varchar(100) NOT NULL,
				`zip` varchar(10) NOT NULL,
				`state` char(2) NOT NULL,
				`latitude` decimal(11,8) NOT NULL,
				`longitude` decimal(11,8) NOT NULL,
				`radius` smallint NOT NULL,
				`phone` varchar(20) NOT NULL,
				`promoted` boolean NOT NULL DEFAULT '0',
				`promotion` text NULL,
				`promotion_date` date NULL,
				`description` text NOT NULL,
				`support_car` boolean NOT NULL DEFAULT '1',
				`support_motorcycle` boolean NOT NULL DEFAULT '1',
				`support_truck` boolean NOT NULL DEFAULT '1',
				`likes` int NOT NULL DEFAULT '0',
				`dislikes` int NOT NULL DEFAULT '0',
				`active` boolean NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				INDEX `index_by_zip` (`zip`),
				INDEX `index_by_oid` (`oid`),
				INDEX `index_by_name` (`name`),
				INDEX `index_by_city` (`city`, `state`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";

		$db->query($sql);

		// store empty api key
		$this->saveSetting('api_key', '');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;
		$db->drop_tables(array('listing_companies'));
	}

	/**
	 * Parse update file and return array.
	 *
	 * @param string $filename
	 * @return array
	 */
	private function parseUpdateFile($filename) {
		$result = array();

		// make sure file exists
		if (!file_exists($filename))
			return $result;

		// open update file
		$handle = fopen($filename, 'r');

		if ($handle) {
			while (($row = fgetcsv($handle)) !== false)
				$result[] = $row;

			fclose($handle);
		}

		return $result;
	}

	/**
	 * Show upload form for database update file.
	 */
	private function updateDatabase() {
		$template = new TemplateHandler('update.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
				'form_action'	=> backend_UrlMake($this->name, 'update_commit'),
				'cancel_action'	=> window_Close('listing_update')
			);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Update database values with new ones.
	 */
	private function updateDatabase_Commit() {
		$manager = CompanyManager::getInstance();
		$csv_data = $this->parseUpdateFile($_FILES['update_file']['tmp_name']);
		$number_updated = 0;
		$number_inserted = 0;

		// load list of existing companies
		$oid_list = array();
		$companies = $manager->getItems(array('oid'), array());

		foreach ($companies as $company)
			$oid_list[] = $company->oid;

		// deactivate all the companies
		$manager->updateData(array('active' => 0), array());

		// update database
		if (count($csv_data) > 0) {
			array_shift($csv_data);

			foreach ($csv_data as $row) {
				// prepare data
				$date = !empty($row[13]) ? date('Y-m-d', strtotime($row[13])) : '';
				$data = array(
						'name'				=> $row[1],
						'address'			=> $row[2],
						'city'				=> $row[3],
						'zip'				=> $row[5],
						'state'				=> $row[4],
						'latitude'			=> $row[6],
						'longitude'			=> $row[7],
						'radius'			=> $row[9],
						'phone'				=> $row[8],
						'promotion'			=> $row[12],
						'promotion_date'	=> $date,
						'description'		=> $row[14],
						'active'			=> 1
					);

				if (in_array($row[0], $oid_list)) {
					// update existing entry
					$manager->updateData($data, array('oid' => $row[0]));
					$number_updated++;

				} else {
					// create new entry in database
					$data['oid'] = $row[0];
					$manager->insertData($data);
					$number_inserted++;
				}
			}

			$template = new TemplateHandler('message.xml', $this->path.'templates/');

			$message = $this->getLanguageConstant('message_update_complete');
			$message = str_replace(
					array('%new', '%existing'),
					array($number_inserted, $number_updated),
					$message
				);

			$params = array(
					'message'	=> $message,
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('listing_update')
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// report error
			$template = new TemplateHandler('message.xml', $this->path.'templates/');

			$params = array(
					'message'	=> $this->getLanguageConstant('message_update_failed'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('listing_update')
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
 	 * Show window for API key input.
	 */
	private function setKey() {
		$template = new TemplateHandler('api_key.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
				'form_action'	=> backend_UrlMake($this->name, 'api_key_save'),
				'cancel_action'	=> window_Close('listing_api_key')
			);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Store API key.
	 */
	private function setKey_Commit() {
		$api_key = fix_chars($_REQUEST['api_key']);
		$this->saveSetting('api_key', $api_key);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$params = array(
				'message'	=> $this->getLanguageConstant('message_api_key_saved'),
				'button'	=> $this->getLanguageConstant('close'),
				'action'	=> window_Close('listing_api_key')
			);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Get coordinates based on location.
	 *
	 * @param string $location
	 * @return array
	 */
	public function getCoordinates($location) {
		$result = array(
				'latitude'	=> null,
				'longitude'	=> null
			);

		// prepare request data
		$url = str_replace(
				array(
					'{address}',
					'{key}'
				),
				array(
					urlencode($location),
					$this->settings['api_key']
				),
				self::GEOCODING_API
			);

		// get response from Google
		$response = json_decode(file_get_contents($url));

		if ($response !== false && $response->status == 'OK' && count($response->results) > 0) {
			$data = $response->results[0];
			$result['latitude'] = $data->geometry->location->lat;
			$result['longitude'] = $data->geometry->location->lng;
		}

		return $result;
	}

	/**
	 * Show list of companies matching search query.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Results($tag_params, $children) {
		global $db, $images_path;

		$query = null;
		$query_type = QueryType::CAR;
		$limit = 10;

		// get search query
		if (isset($tag_params['query']))
			$query = fix_chars($tag_params['query']);

		if (is_null($query) && isset($_REQUEST['query']))
			$query = fix_chars($_REQUEST['query']);

		if (isset($tag_params['type']) && array_key_exists($tag_params['type'], $this->referrence))
			$query_type = $this->referrence[$tag_params['type']];

		if (isset($_REQUEST['type']) && array_key_exists($_REQUEST['type'], $this->referrence))
			$query_type = $this->referrence[$_REQUEST['type']];

		// try to detect query type
		if (preg_match(self::REGEX_COORDS, $query)) {
			// coordinates matched
			$data = explode(' ', $query);
			$location = array(
					'latitude'	=> is_numeric($data[0]) ? $data[0] : 0,
					'longitude'	=> is_numeric($data[1]) ? $data[1] : 0
				);

		} else {
			// we have no clue about the format, ask Google
			$location = $this->getCoordinates($query);
		}

		// get all companies from database
		$sql = str_replace(
			array(
				'{latitude}',
				'{longitude}',
				'{support}',
				'{limit}'
			),
			array(
				$location['latitude'],
				$location['longitude'],
				'support_car',
				50
			),
			file_get_contents($this->path.'/data/search.sql')
		);
		$companies = $db->get_results($sql);

		// show map
		$map_template = $this->loadTemplate($tag_params, 'map.xml', 'map_template');
		$map_template->restoreXML();
		$map_template->setLocalParams($location);
		$map_template->parse();

		// load template
		$template = $this->loadTemplate($tag_params, 'result.xml');

		if (count($companies) > 0) {
			$covered_distance = array();
			$covered_list = array();
			$outside_distance = array();
			$outside_list = array();

			foreach ($companies as $company) {
				// calculate distance with haversine formula
				$lat1 = deg2rad($location['latitude']);
				$lat2 = deg2rad($company->latitude);
				$delta_latitude = deg2rad($company->latitude - $location['latitude']);
				$delta_longitude = deg2rad($company->longitude - $location['longitude']);

				$a = sin($delta_latitude / 2) * sin($delta_latitude / 2) +
					cos($lat1) * cos($lat2) *
					sin($delta_longitude / 2) * sin($delta_longitude / 2);
				$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

				$radius = 3959;  // earth radius in miles
				$distance = $radius * $c;

				// store company and distance for later use
				if ($company->radius * 1.3 >= $distance) {
					$covered_distance[$company->id] = $distance;
					$covered_list[$company->id] = $company;

				} else {
					$outside_distance[$company->id] = $distance;
					$outside_list[$company->id] = $company;
				}
			}

			// sort by distance
			asort($covered_distance);

			foreach ($covered_distance as $company_id => $distance) {
				$company = $covered_list[$company_id];

				$file_name = $images_path.'company_logos/'.$company->id.'.jpg';
				if (file_exists($file_name))
					$logo_url = url_GetFromFilePath($file_name); else
					$logo_url = url_GetFromFilePath(_BASEPATH.'/'.$images_path.'default_logo.svg');

				// prepare parameters
				$params = array(
					'id'			=> $company->id,
					'name'			=> $company->name,
					'address'		=> $company->address,
					'city'			=> $company->city,
					'state'			=> $company->state,
					'logo_url'		=> $logo_url,
					'latitude'		=> $company->latitude,
					'longitude'		=> $company->longitude,
					'distance'		=> $distance >= 5 ? round($distance) : round($distance, 1),
					'likes'			=> $company->likes,
					'dislikes'		=> $company->dislikes,
					'phone'			=> '054-222-'.rand(1, 1000), // $company->phone ||,
					'description'	=> $company->description
					);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}

			// show companies outside of the support radius
			if (count($covered_distance) < 10) {
				// show message
				$message_template = $this->loadTemplate($tag_params, 'result_message.xml', 'message_template');
				$message_template->restoreXML();
				$message_template->parse();

				// show remaining results
				$outside_distance = array_slice($outside_distance, 0, 10 - count($covered_distance), true);
				foreach ($outside_distance as $company_id => $distance) {
					$company = $outside_list[$company_id];

					$file_name = $images_path.'company_logos/'.$company->id.'.jpg';
					if (file_exists($file_name))
						$logo_url = url_GetFromFilePath($file_name); else
						$logo_url = url_GetFromFilePath(_BASEPATH.'/'.$images_path.'default_logo.svg');

					// prepare parameters
					$params = array(
						'id'			=> $company->id,
						'name'			=> $company->name,
						'address'		=> $company->address,
						'city'			=> $company->city,
						'state'			=> $company->state,
						'logo_url'		=> $logo_url,
						'latitude'		=> $company->latitude,
						'longitude'		=> $company->longitude,
						'distance'		=> $distance >= 5 ? round($distance) : round($distance, 1),
						'likes'			=> $company->likes,
						'dislikes'		=> $company->dislikes,
						'phone'			=> '054-222-'.rand(1, 1000), // $company->phone ||,
						'description'	=> $company->description
						);

					$template->restoreXML();
					$template->setLocalParams($params);
					$template->parse();
				}
			}
		}
	}

	/**
	 * Tag handler for drawing company details.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Company($tag_params, $children) {
	}
}
