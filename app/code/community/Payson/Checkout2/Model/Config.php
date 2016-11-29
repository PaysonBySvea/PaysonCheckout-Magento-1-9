<?php

class Payson_Checkout2_Model_Config {
    /*
     * Private properties
     */

    /**
     * Default store id used in GetConfig()
     *
     * @var	int
     */
    private $default_store_id;

    /**
     * Supported currency codes
     *
     * @var	array
     */
    private $supported_currencies = array
    (
        'SEK', 'EUR'
    );

    /*
     * Public methods
     */

    /**
     * Constructor
     *
     * @return	void
     */
    public function __construct() {
        $this->setDefaultStoreId(Mage::app()->getStore()->getId());
    }

    /**
     * Set default store id
     *
     * @param	int		$store
     * @return	object			$this
     */
    public function setDefaultStoreId($store) {
        $this->default_store_id = $store;

        return $this;
    }

    /**
     * Get default store id
     *
     * @return	int
     */
    public function getDefaultStoreId() {
        return $this->default_store_id;
    }

    /**
     * Whether $currency is supported
     *
     * @param	string	$currency
     * @return	bool
     */
    public function isCurrencySupported($currency) {
        return in_array(strtoupper($currency), $this->supported_currencies);
    }

    /**
     * Get configuration value
     *
     * @param	mixed		$name
     * @param	int|null	$store		[optional]
     * @param	mixed		$default	[optional]
     * @param	string		$prefix		[optional]
     */
    public function getConfig($name, $store = null, $default = null, $prefix = 'payment/checkout2/') {
        if (!isset($store)) {
            $store = $this->getDefaultStoreId();
        }

        $name = $prefix . $name;
        $value = Mage::getStoreConfig($name, $store);

        return (isset($value) ? $value : $default);
    }

    /**
     * @see getConfig
     */
    public function get($name, $store = null, $default = null, $prefix = 'payment/checkout2/') {
        return $this->getConfig($name, $store, $default, $prefix);
    }

    public function restoreCartOnCancel() {
        return $this->get('restore_on_cancel');
    }

    public function restoreCartOnError() {
        return $this->get('restore_on_error');
    }

    public function getTestMode() {
        return $this->get('test_mode');
    }

    public function getEnabled() {
        return $this->get('active');
    }

    public function getAgentID() {
        return $this->get('agent_id');
    }

    public function getApiKey() {
        return $this->get('md5_key');
    }

    public function getTermsUrl() {
        return $this->get('terms_and_conditions_url');
    }

    public function getTheme() {
        return $this->get('checkout_theme');
    }

    public function getExtraVerification() {
        return $this->get('extra_verification');
    }

    public function getRequestPhone() {
        return $this->get('request_phone');
    }
}
