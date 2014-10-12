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


class towing extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		parent::__construct(__FILE__);

		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$towing_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_towing'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$towing_menu->addChild('', new backend_MenuItem(
					$this->getLanguageConstant('menu_update'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					window_Open(
						'towing_update',
						350,
						$this->getLanguageConstant('title_update'),
						true, true,
						backend_UrlMake($this->name, 'update')
					),
					$level=5
				));

			$backend->addMenu($this->name, $towing_menu);
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
			CREATE TABLE IF NOT EXISTS `towing_companies` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`oid` int(11) NOT NULL,
				`name` varchar(150) NOT NULL,
				`address` varchar(255) NOT NULL,
				`city` varchar(100) NOT NULL,
				`zip` varchar(10) NOT NULL,
				`state` char(2) NOT NULL,
				`latitude` decimal(11,8) NOT NULL,
				`longitude` decimal(11,8) NOT NULL,
				`radius` smallint(6) NOT NULL,
				`phone` varchar(20) NOT NULL,
				`promotion` text NULL,
				`promotion_date` date NULL,
				`description` text NOT NULL,
				PRIMARY KEY (`id`),
				INDEX `index_by_zip` (`zip`),
				INDEX `index_by_oid` (`oid`),
				INDEX `index_by_name` (`name`),
				INDEX `index_by_city` (`city`, `state`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;
		$db->drop_tables(array('towing_companies'));
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

		trigger_error('Came!');

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
				'cancel_action'	=> window_Close('towing_update')
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
		$companies = $manager->getItems(array('oid'));

		foreach ($companies as $company)
			$oid_list[] = $company->oid;

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
						'promotion_date'	=> $data,
						'description'		=> $row[14]
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
					'action'	=> window_Close('towing_update')
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
					'action'	=> window_Close('towing_update')
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}
}
