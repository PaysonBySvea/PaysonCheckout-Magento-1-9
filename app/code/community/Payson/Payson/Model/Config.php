<?php

class Payson_Payson_Model_Config {
    /*
     * Constants
     */

    //const PAYMENT_GUARANTEE = 'payment_guarantee';

    const PAYMENT_GUARANTEE = 'NO';
    const DIRECT_PAYMENT = 'direct_payment';
    const CREDIT_CARD_PAYMENT = 'credit_card_payment';
    const INVOICE_PAYMENT = 'invoice_payment';

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
     * Constructor!
     * 
     * @return	void
     */

    public function __construct() {
        $this->SetDefaultStoreId(Mage::app()->getStore()->getId());
    }

    /**
     * Set default store id
     * 
     * @param	int		$store
     * @return	object			$this
     */
    public function SetDefaultStoreId($store) {

        $this->default_store_id = $store;

        return $this;
    }

    /**
     * Get default store id
     * 
     * @return	int
     */
    public function GetDefaultStoreId() {

        return $this->default_store_id;
    }

    /**
     * Whether $currency is supported
     * 
     * @param	string	$currency
     * @return	bool
     */
    public function IsCurrencySupported($currency) {
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
    public function GetConfig($name, $store = null, $default = null, $prefix = 'payment/payson_standard/') {
        if (!isset($store)) {
            $store = $this->GetDefaultStoreId();
        }

        $name = $prefix . $name;
        $value = Mage::getStoreConfig($name, $store);

        return (isset($value) ? $value : $default);
    }

    /**
     * @see GetConfig
     */
    public function Get($name, $store = null, $default = null, $prefix = 'payment/payson_standard/') {
        return $this->GetConfig($name, $store, $default, $prefix);
    }

    /**
     * Get Payson specific invoice fee excluding tax
     * 
     * @param	object	$order
     * @return	float
     */
    public function GetInvoiceFee($order) {
        $currency = $order->getBaseCurrencyCode();
        $currency = strtolower($currency);

        if (!$this->IsCurrencySupported($currency)) {
            return 0;
        }

        $store = Mage::app()->getStore($order->getStoreId());

        $fee = $this->GetConfig('invoice_fee_' . $currency, $store->getId(), 0, 'payment/payson_invoice/');
        $fee = round((float) $fee, 3);

        return $fee;
    }

    /**
     * Get Payson specific invoice fee including tax
     * 
     * @param	object	$order
     * @return	float
     */
    public function GetInvoiceFeeInclTax($order) {
        $fee = $this->GetInvoiceFee($order);
        $fee *= (1 + $this->GetInvoiceFeeTaxMod($order));
        $fee = round($fee, 3);

        return $fee;
    }

    /**
     * Get Payson specific invoice fee tax modifier
     * 
     * @param	object	$order
     * @return	float
     */
    public function GetInvoiceFeeTaxMod($order) {
        $store = Mage::app()->getStore($order->getStoreId());

        $tax_calc = Mage::getSingleton('tax/calculation');
        $customer = Mage::getModel('customer/customer')
                ->load($order->getCustomerId());
        $tax_class = $this->GetConfig('invoice_fee_tax', $store->getId(), 0, 'payment/payson_invoice/');

        $tax_rate_req = $tax_calc->getRateRequest(
                        $order->getShippingAddress(), $order->getBillingAddress(), $customer->getTaxClassId(), $store)
                ->setProductClassId($tax_class);

        $tax_mod = (float) $tax_calc->getRate($tax_rate_req);
        $tax_mod /= 100;
        $tax_mod = round($tax_mod, 5);

        return $tax_mod;
    }

    /**
     * Does this store support payment guarantee?
     * 
     * @param	int|null	$store	[optional]
     * @return	bool
     */
    public function CanPaymentGuarantee($store = null) {
        return (bool) $this->GetConfig(self::PAYMENT_GUARANTEE, $store, false);
    }

     /**
     * Is invoice payment enabled?
     * 
     * @param	int|null	$store	[optional]
     * @return	bool
     */

    public function CanInvoicePayment($store = null) {
        
        return $this->GetConfig('active_invoice', $store, false, 'payment/payson_invoice/');
    }    
    /**
     * Is standard payment enabled?
     * 
     * @param	int|null	$store	[optional]
     * @return	bool
     */ 
    public function CanStandardPayment($store = null) {
        return $this->GetConfig('active', $store, false, 'payment/payson_standard/');
    }

    public function restoreCartOnCancel($store = null) {
        if (!$store)
            $store = Mage::app()->getStore()->getId();
        $configValue = $this->GetConfig("restore_on_cancel", $store);

        return $configValue == 1;
    }

    public function restoreCartOnError($store = null) {
        if (!$store)
            $store = Mage::app()->getStore()->getId();
        $configValue = $this->GetConfig("restore_on_error", $store);

        return $configValue == 1;
    }

}

