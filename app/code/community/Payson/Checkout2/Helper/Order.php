<?php

require_once(Mage::getBaseDir('lib') . '/Payson/Checkout2/PaysonCheckout2PHP/lib/paysonapi.php');

class Payson_Checkout2_Helper_Order extends Mage_Core_Helper_Abstract
{
    protected $_order;
    protected $_config;
    protected $_api;
    protected $controlKey;

    const MODULE_NAME = 'PaysonCheckout2.0_magento';
    const MODULE_VERSION = '1.0.0.1'; 

    public function checkout() {
        $order = $this->getOrder();

        $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);

        if ($checkoutId) {
            // Already have an active checkout
            return $this->getCheckout($checkoutId);
        }

        $callPaysonApi = $this->getApi();
        $paysonMerchant = $this->_getMerchant();
        $payData = $this->_getOrderPayData();
        $customer = $this->initCustomer();
        $store = Mage::app()->getStore();
        $description = Mage::helper('checkout2')->__('Order from %s', $store->getFrontendName);

        // Init GUI
        $locale = $this->getLocale();
        $theme = $this->getConfig()->getTheme();
        $extraVerification = $this->getConfig()->getExtraVerification();
        $requestPhone = $this->getConfig()->getRequestPhone() == 1;

        $gui = new  PaysonEmbedded\Gui($locale, $theme, $extraVerification, $requestPhone);
        $checkout = new PaysonEmbedded\Checkout($paysonMerchant, $payData, $gui, $customer, $description);

        /*
         * Step 2 Create checkout
         */

        $checkoutId = $callPaysonApi->CreateCheckout($checkout);
        $order->setData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, $checkoutId);

        $order->save();

        /*
         * Step 3 Get checkout object
         */

        return $callPaysonApi->GetCheckout($checkoutId);
    }

    public function expressCheckout() {
        $quote = Mage::getModel('checkout/cart')->getQuote();

        $checkoutId = $quote->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);

        if ($checkoutId) {
            // Already have an active checkout
            return $this->getCheckout($checkoutId);
        }

        $this->_setDefaultShipping($quote);

        $callPaysonApi = $this->getApi();
        $paysonMerchant = $this->_getExpressMerchant();
        $payData = $this->_getQuotePayData();
        $customer = $this->_initExpressCustomer();
        $store = Mage::app()->getStore();
        $description = Mage::helper('checkout2')->__('Order from %s', $store->getFrontendName());

        // Init GUI
        $locale = $this->getLocale();
        $theme = $this->getConfig()->getTheme();
        $extraVerification = $this->getConfig()->getExtraVerification();
        $requestPhone = true; // Always request phone on checkout since Magento needs a phone number to create an order
        
        $gui = new  PaysonEmbedded\Gui($locale, $theme, $extraVerification, $requestPhone);
        $checkout = new PaysonEmbedded\Checkout($paysonMerchant, $payData, $gui, $customer, $description);

        /*
         * Step 2 Create checkout
         */

        $checkoutId = $callPaysonApi->CreateCheckout($checkout);
        $quote->setData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, $checkoutId);
        //Set the email to the gest account Information
        $quote->setCustomerEmail($customer->email);
        $quote->save();

        $this->updateCheckoutControlKey($checkoutId);

        /*
         * Step 3 Get checkout object
         */

        return $callPaysonApi->GetCheckout($checkoutId);
    }

    public function updateExpressCheckout() {
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $checkoutId = $quote->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);

        $callPaysonApi = $this->getApi();

        // Fetch checkout and set new paydata
        $checkout = $callPaysonApi->GetCheckout($checkoutId);

        switch ($checkout->status) {
            case 'shipped':
            case 'paidToAccount':
            case 'canceled':
            case 'expired':
            case 'denied':

            // Don't try to update checkout at this point
            return $checkout;
        }

        $payData = $this->_getQuotePayData();
        $checkout->payData->items = $payData->items;

        // Update and return
        $checkout = $callPaysonApi->UpdateCheckout($checkout);

        return $checkout;
    }

    public function updateCheckoutControlKey($checkoutId) {
        $this->_controlKey = Mage::helper('core')->getRandomString($length = 8);
        $domain = null;
        Mage::getSingleton('core/cookie')->set($checkoutId, $this->_controlKey, 3600, '/', $domain, false, false);
    }

    public function removeCheckoutControlKey($checkoutId) {
        Mage::getSingleton('core/cookie')->delete($checkoutId);
    }

    public function getCheckoutControlKey($checkoutId) {
        $key = Mage::getSingleton('core/cookie')->get($checkoutId);

        if (!$key) {
            return $this->_controlKey;
        }

        return $key;
    }

    private function _getOrderPayData() {
        $order = $this->getOrder();
        $payData = new PaysonEmbedded\PayData($order->getOrderCurrency()->getCode());

        // Re-create paydata
        $discount = 0;

        // Add items and discount 
        foreach ($order->getAllVisibleItems() as $item) { 
            $this->prepareOrderItemData($item, $payData); 
            $discount += $item->getDiscountAmount(); 
        } 
 
        if ($discount > 0) { 
            $payData->AddOrderItem(new PaysonEmbedded\OrderItem('discount', -$discount, 1, 0.1, 'a', PaysonEmbedded\OrderItemType::DISCOUNT)); 
        } 
 
        // Calculate price for shipping 
        $this->prepareOrderShippingData($order, $payData);

        return $payData;
    }

    private function _getQuotePayData() {
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $payData = new PaysonEmbedded\PayData($quote->getQuoteCurrencyCode());

        // Re-create paydata
        $discount = 0;

        // Add items and discount
        foreach ($quote->getAllVisibleItems() as $item) {
            $this->prepareQuoteItemData($item, $payData);
            $discount += $item->getDiscountAmount();
        }

        if ($discount > 0) {
            $payData->AddOrderItem(new PaysonEmbedded\OrderItem('discount', -$discount, 1, 0.1, 'a', PaysonEmbedded\OrderItemType::DISCOUNT));
        }

        // Calculate price for shipping
        $this->prepareQuoteShippingData($quote, $payData);

        return $payData;
    }

    private function _getMerchant() {
        // URLs used by payson for redirection after a completed/canceled/notification purchase.
        $checkoutUri     = Mage::getUrl('checkout2/payment/cancel', array('_secure' => true));
        $confirmationUri = Mage::getUrl('checkout2/payment/return', array('_secure' => true));
        $notificationUri = Mage::getUrl('checkout2/notification/notify', array('_secure' => true));
        $termsUri        = $this->getConfig()->getTermsUrl();

        return new PaysonEmbedded\Merchant($checkoutUri, $confirmationUri, $notificationUri, $termsUri, 1);
    }

    private function _getExpressMerchant() {
        // URLs used by payson for redirection after a completed/canceled/notification purchase.
        $checkoutUri     = Mage::getUrl('checkout2/express/cancel', array('_secure' => true));
        $confirmationUri = Mage::getUrl('checkout2/express/return', array('_secure' => true));
        $notificationUri = Mage::getUrl('checkout2/notification/notify', array('_secure' => true));
        $termsUri        = $this->getConfig()->getTermsUrl();
        $paysonModuleInfo = self::MODULE_NAME . '|' . self::MODULE_VERSION . '|' . Mage::getVersion();

        return new PaysonEmbedded\Merchant($checkoutUri, $confirmationUri, $notificationUri, $termsUri, 1, $paysonModuleInfo);
    }

    /**
     * @return \PaysonEmbedded\PaysonApi
     */
    public function getApi() {
        if (is_null($this->_api)) {
            $testMode = $this->getConfig()->getTestMode();
            $merchantId = $this->getConfig()->getAgentId();
            $apiKey = $this->getConfig()->getApiKey();

            $this->_api = new PaysonEmbedded\PaysonApi($merchantId, $apiKey, $testMode);
        }

        return $this->_api;
    }

    /**
     * @param $checkoutId
     * @return PaysonEmbedded\Checkout
     */
    public function getCheckout($checkoutId) {
        return $this->getApi()->GetCheckout($checkoutId);
    }

    /**
     * Get config
     *
     * @return Payson_Checkout2_Model_Config
     */
    protected function getConfig() {
        if (empty($this->_config)) {
            $this->_config = Mage::getModel('checkout2/config');
        }
        return $this->_config;
    }

    /**
     *  Get order
     *
     *  @return  Mage_Sales_Model_Order
     */
    public function getOrder() {
        if (!isset($this->_order)) {
            $increment_id = $this->_getSession()->getData('last_real_order_id');

            if ($increment_id) {
                $this->_order = Mage::getModel('sales/order')
                    ->loadByIncrementId($increment_id);

                if (is_null($this->_order->getId())) {
                    $this->_order = null;
                }
            }
        }

        return $this->_order;
    }

    public function getQuote() {
        return Mage::getSingleton('checkout/cart')->getQuote();
    }

    /**
     *  Get order status
     *
     *  @return  boolean
     */
    public function hasOrder() {
        return !is_null($this->getOrder());
    }

    /**
     *  Get quote status
     *
     *  @return  boolean
     */
    public function hasActiveQuote() {
        return Mage::helper('checkout/cart')->getItemsCount() > 0;
    }

    /**
     * Gets current locale
     *
     * @return string
     */
    protected function getLocale() {
        $locale = Mage::getSingleton('core/locale')->getLocaleCode();
        $locale = substr($locale, 0, 2);

        if (!in_array($locale, array('sv', 'fi', 'en'))) {
            switch ($locale) {
                case 'da':
                case 'no': {
                    $locale = 'sv';
                    break;
                }
                default: {
                    $locale = 'en';
                }
            }
        }

        return $locale;
    }

    /**
     * Helper for checkout()
     *
     * @param	Mage_Sales_Model_Order_Item $item
     * @param	PaysonEmbedded\PayData		$payData
     * @return	void
     */
    protected function prepareOrderItemData($item, &$payData) {
        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')
            ->load($item->getProductId());

        $attributesString = "";

        if (($children = $item->getChildrenItems()) != null && !$product->isConfigurable()) {

            foreach ($children as $child) {
                $this->prepareOrderItemData($child, $payData);
            }
            return;
        }

        $productOptions = $item->getProductOptions();

        if (array_key_exists('attributes_info', $productOptions)) {
            foreach ($productOptions['attributes_info'] as $attribute) {
                $attributesString .= $attribute['label'] . ": " . $attribute['value'] . ", ";
            }

            if ($attributesString != "") {
                $attributesString = substr($attributesString, 0, strlen($attributesString) - 2);
            }
        }

        $name = $item->getName() . ($attributesString != "" ? " - " . $attributesString : "");
        $sku = $item->getSku();

        $name = strlen($name) <= 128 ? $name : substr($name, 128);
        $sku = strlen($sku) <= 128 ? $sku : substr($sku, 128);

        $tax_mod = (float) $item->getTaxPercent();
        $tax_mod /= 100;
        $tax_mod = round($tax_mod, 5);

        $qty = (float) $item->getQtyOrdered();
        $qty = round($qty, 2);

        $price = (float) $item->getRowTotalInclTax() / $qty;

        $payData->AddOrderItem(new PaysonEmbedded\OrderItem($name, $price, $qty, $tax_mod, $sku));
    }

    protected function prepareQuoteItemData($item, &$payData) {
        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')
            ->load($item->getProductId());

        $attributesString = "";

        if (($children = $item->getChildrenItems()) != null && !$product->isConfigurable()) {

            foreach ($children as $child) {
                $this->prepareQuoteItemData($child, $payData);
            }
            return;
        }

        $productOptions = $item->getProductOptions();

        if (is_null($productOptions)) {
            $productOptions = array();
        }

        if (array_key_exists('attributes_info', $productOptions)) {
            foreach ($productOptions['attributes_info'] as $attribute) {
                $attributesString .= $attribute['label'] . ": " . $attribute['value'] . ", ";
            }

            if ($attributesString != "") {
                $attributesString = substr($attributesString, 0, strlen($attributesString) - 2);
            }
        }

        $name = $item->getName() . ($attributesString != "" ? " - " . $attributesString : "");
        $sku = $item->getSku();

        $name = strlen($name) <= 128 ? $name : substr($name, 128);
        $sku = strlen($sku) <= 128 ? $sku : substr($sku, 128);

        $tax_mod = (float) $item->getTaxPercent();
        $tax_mod /= 100;
        $tax_mod = round($tax_mod, 5);

        $qty = $item->getQty();
        $qty = round($qty, 2);

        $price = (float) $item->getRowTotalInclTax() / $qty;

        $payData->AddOrderItem(new PaysonEmbedded\OrderItem($name, $price, $qty, $tax_mod, $sku));
    }

    /**
     * Helper for checkout()
     *
     * @param	object	$order
     * @param	object	$customer
     * @param	object	$store
     * @param	int		$i
     * @param	int		$total
     */
    protected function prepareOrderShippingData($order, &$payData) {
        $tax_calc = Mage::getSingleton('tax/calculation');

        $store = Mage::app()->getStore($order->getStoreId());
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        $tax_rate_req = $tax_calc->getRateRequest(
            $order->getShippingAddress(), $order->getBillingAddress(), $customer->getTaxClassId(), $store);


        if (($price = (float) $order->getShippingInclTax()) > 0) {
            $tax_mod = $tax_calc->getRate($tax_rate_req->setProductClassId(
                Mage::getStoreConfig('tax/classes/shipping_tax_class')));
            $tax_mod /= 100;
            $tax_mod = round($tax_mod, 5);

            $price -= (float) $order->getShippingDiscountAmount();

            $sku = $order->getShippingMethod();

            $payData->AddOrderItem(new PaysonEmbedded\OrderItem($order->getShippingDescription(), $price, 1, $tax_mod, $sku, PaysonEmbedded\OrderItemType::SERVICE));
        }
    }

    protected function prepareQuoteShippingData($quote, &$payData) {
        $tax_calc = Mage::getSingleton('tax/calculation');

        $store = Mage::app()->getStore($quote->getStoreId());
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $tax_rate_req = $tax_calc->getRateRequest(
            $quote->getShippingAddress(), $quote->getBillingAddress(), $customer->getTaxClassId(), $store);


        if (($price = (float) $quote->getShippingAddress()->getShippingInclTax()) > 0) {
            $tax_mod = $tax_calc->getRate($tax_rate_req->setProductClassId(
                Mage::getStoreConfig('tax/classes/shipping_tax_class')));
            $tax_mod /= 100;
            $tax_mod = round($tax_mod, 5);

            $price -= (float) $quote->getShippingDiscountAmount();

            $sku = $quote->getShippingAddress()->getShippingMethod();

            $payData->AddOrderItem(new PaysonEmbedded\OrderItem($quote->getShippingAddress()->getShippingDescription(), $price, 1, $tax_mod, $sku, PaysonEmbedded\OrderItemType::SERVICE));
        }
    }

    /**
     * Helper for checkout()
     *
     * @return \PaysonEmbedded\Customer
     */
    private function initCustomer() {
        $order = $this->getOrder();
        $testMode = $this->getConfig()->getTestMode();
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        $firstname = $testMode ? 'Tess T' : $customer->getFirstname();
        $lastname = $testMode ? 'Persson' : $customer->getLastname();
        $email = $testMode ? 'test@payson.se' : $customer->getEmail();
        $telephone = $testMode ? '' : $customer->getTelephone();
        $socialSecurityNo = $testMode ? '4605092222' : '';
        $city = $testMode ? 'Stan' : '';
        $street = $testMode ? 'Testgatan' : '';
        $postCode = $testMode ? '99999' : '';
        $country = $testMode ? 'Sverige' : '';

        if (!$testMode && $customer->getDefaultBilling()) {
            $billingAddress = Mage::getModel('customer/address')->load($customer->getDefaultBilling());

            if ($billingAddress->getId()) {
                $firstname = $billingAddress->getData('firstname');
                $lastname = $billingAddress->getData('lastname');
                $telephone = $billingAddress->getData('telephone');
                $street = join(', ', array($billingAddress->getStreet1(), $billingAddress->getStreet2()));
                $city = $billingAddress->getData('city');
                $postCode = $billingAddress->getData('postcode');
                $country = $billingAddress->getData('country_id');
            }
        }

        return new PaysonEmbedded\Customer($firstname, $lastname, $email, $telephone, $socialSecurityNo, $city, $country, $postCode, $street);
    }

    private function _initExpressCustomer() {
        $quote = Mage::getModel('checkout/cart')->getQuote();

        $testMode = $this->getConfig()->getTestMode();
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $firstname = $testMode ? 'Tess T' : $customer->getFirstname();
        $lastname = $testMode ? 'Persson' : $customer->getLastname();
        $email = $testMode ? 'test@payson.se' : $customer->getEmail();
        $telephone = $testMode ? '' : $customer->getTelephone();
        $socialSecurityNo = $testMode ? '4605092222' : '';
        $city = $testMode ? 'Stan' : '';
        $street = $testMode ? 'Testgatan' : '';
        $postCode = $testMode ? '99999' : '';
        $country = $testMode ? 'Sverige' : '';

        if ($quote->getBillingAddress()) {
            $address = $quote->getBillingAddress();

            if ($address->getId() && $address->getData('postcode')) {
                $email = $address->getData('email');                
                $firstname = $address->getData('firstname');
                $lastname = $address->getData('lastname');
                $telephone = $address->getData('telephone');
                $street = join(', ', array_filter(array($address->getStreet1(), $address->getStreet2())));
                $city = $address->getData('city');
                $postCode = $address->getData('postcode');
                $country = $address->getData('country_id');
            }
        }

        return new PaysonEmbedded\Customer($firstname, $lastname, $email, $telephone, $socialSecurityNo, $city, $country, $postCode, $street);
    }

    /**
     * Restores cart
     */
    public function restoreCart() {
        $quoteId = $this->getOrder()->getQuoteId();
        $quote = Mage::getModel('sales/quote')->load($quoteId);
        $quote->setIsActive(true)->save();
    }

    /**
     * Cancels order
     *
     * @param      string  $message  A descriptive message
     *
     */
    public function cancelOrder($message = '') {

        $order = $this->getOrder();

        if (!is_null($order)) {
            $order->cancel();

            if ($this->getConfig()->restoreCartOnCancel()) {
                $this->restoreCart();
            }

            if ($message != '') {
                $order->addStatusHistoryComment($message);
            }
        }

        $order->save();
    }

    private function _getSession() {
        if (!isset($this->_session)) {
            $this->_session = Mage::getSingleton('checkout/session');
        }

        return $this->_session;
    }
    
    public function convertQuoteToOrder($paysonCustomer) {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();

        if (is_null($quote)) {
            return null;
        }

        $addressData = $this->_udateShippingAddress($paysonCustomer);
        
        //Add address array to both billing AND shipping address.   
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData);
    
        $quote->collectTotals()->save();
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $order = $service->getOrder();
        $quote->setIsActive(false)->save();
        $order->save();

        return $order;
    }

    private function _setDefaultShipping($quote) {
        $shippingAddress = $quote->getShippingAddress();
        $shippingMethod = $shippingAddress->getShippingMethod();
            
        if ($shippingMethod) {
            return;
        }
        
        if (!$shippingAddress->getCountryId()) {
            $shippingAddress->setCountryId('SE');
        }

        if (!$shippingAddress->getPostCode()) {
            $shippingAddress->setPostcode('169 81');
        }                                                   

        $shippingAddress->setCollectShippingRates(true);

        // Collect and set default shipping rate
        $shippingAddress->collectShippingRates();
        $rates = $shippingAddress->getAllShippingRates();

        if (count($rates) > 0) {
            $rate = $rates[0];
            $shippingAddress->setShippingMethod($rate->getCode())
                ->setShippingAmount($rate->getPrice());
        }

        $quote->getPayment()->setMethod('checkout2');
        $quote->setTotalsCollectedFlag(false)->collectTotals();
    }

    private function _udateShippingAddress($paysonCustomer) {
            return array(
                'firstname' => $paysonCustomer->firstName,
                'lastname' => $paysonCustomer->lastName,
                'street' => $paysonCustomer->street,
                'city' => $paysonCustomer->city,
                'postcode'=> $paysonCustomer->postalCode,
                'telephone' => $paysonCustomer->phone,
                'country_id' => $paysonCustomer->countryCode,
        );
    }
}