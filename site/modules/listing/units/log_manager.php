<?php

class Listing_LogManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('listing_rating_log');

		$this->addProperty('id', 'int');
		$this->addProperty('company', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('direction', 'int');
		$this->addProperty('week', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
