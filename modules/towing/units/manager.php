<?php

class CompanyManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('towing_companies');

		$this->addProperty('id', 'int');
		$this->addProperty('oid', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('address', 'varchar');
		$this->addProperty('city', 'varchar');
		$this->addProperty('zip', 'varchar');
		$this->addProperty('state', 'char');
		$this->addProperty('latitude', 'decimal');
		$this->addProperty('longitude', 'decimal');
		$this->addProperty('radius', 'smallint');
		$this->addProperty('phone', 'varchar');
		$this->addProperty('promoted', 'boolean');
		$this->addProperty('promotion', 'text');
		$this->addProperty('promotion_date', 'date');
		$this->addProperty('description', 'text');
		$this->addProperty('support_car', 'boolean');
		$this->addProperty('support_motorcycle', 'boolean');
		$this->addProperty('support_truck', 'boolean');
		$this->addProperty('likes', 'int');
		$this->addProperty('dislikes', 'int');
		$this->addProperty('active', 'boolean');
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
