<?php

include 'FundingConstraint.php';

class Payson_Payson_Helper_Api {
    /*
     * Constants
     */

    var $invoiceAmountMinLimit = 30;

    const DEBUG_MODE = false;
    const API_CALL_PAY = '%s://%sapi.payson.%s/%s/Pay/';
    const API_CALL_VALIDATE = '%s://%sapi.payson.%s/%s/Validate/';
    const API_CALL_PAYMENT_DETAILS = '%s://%sapi.payson.%s/%s/%sDetails/';
    const API_CALL_PAYMENT_UPDATE = '%s://%sapi.payson.%s/%s/%sUpdate/';
    const PAY_FORWARD_URL = '%s://%s%s.payson.%s/paySecure/';
    const APPLICATION_ID = 'Magento';
    const MODULE_NAME = 'Payson_AllinOne';
    const MODULE_VERSION = '1.8.3.3';
    const DEBUG_MODE_MAIL = 'testagent-checkout2@payson.se';
    const DEBUG_MODE_AGENT_ID = '4';
    const DEBUG_MODE_MD5 = '2acab30d-fe50-426f-90d7-8c60a7eb31d4';
    const STATUS_CREATED = 'CREATED';
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CREDITED = 'CREDITED';
    const STATUS_INCOMPLETE = 'INCOMPLETE';
    const STATUS_ERROR = 'ERROR';
    const STATUS_DENIED = 'DENIED';
    const STATUS_ABORTED = 'ABORTED';
    const STATUS_CANCELED = 'CANCELED';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_REVERSALERROR = 'REVERSALERROR';
    const PAYMENT_METHOD_BANK = 'BANK';
    const PAYMENT_METHOD_CREDITCARD = 'CREDITCARD';
    const PAYMENT_METHOD_INVOICE = 'INVOICE';
    const PAYMENT_METHOD_SMS = 'SMS';
    const GUARANTEE_STATUS_WAITINGFORSEND = 'WAITINGFORSEND';
    const GUARANTEE_STATUS_WAITINGFORACCEPTANCE = 'WAITINGFORACCEPTANCE';
    const GUARANTEE_STATUS_WAITINGFORRETURN = 'WAITINGFORRETURN';
    const GUARANTEE_STATUS_WAITINGFORRETURNACCEPTANCE = 'WAITINGFORRETURNACCEPTANCE';
    const GUARANTEE_STATUS_RETURNNOTACCEPTED = 'RETURNNOTACCEPTED';
    const GUARANTEE_STATUS_NOTRECEIVED = 'NOTRECEIVED';
    const GUARANTEE_STATUS_RETURNNOTRECEIVED = 'RETURNNOTRECEIVED';
    const GUARANTEE_STATUS_MONEYRETURNEDTOSENDER = 'MONEYRETURNEDTOSENDER';
    const GUARANTEE_STATUS_RETURNACCEPTED = 'RETURNACCEPTED';
    const INVOICE_STATUS_PENDING = 'PENDING';
    const INVOICE_STATUS_ORDERCREATED = 'ORDERCREATED';
    const INVOICE_STATUS_ORDERCANCELLED = 'ORDERCANCELLED';
    const INVOICE_STATUS_SHIPPED = 'SHIPPED';
    const INVOICE_STATUS_DONE = 'DONE';
    const UPDATE_ACTION_CANCELORDER = 'CANCELORDER';
    const UPDATE_ACTION_SHIPORDER = 'SHIPORDER';
    const UPDATE_ACTION_CREDITORDER = 'CREDITORDER';
    const UPDATE_ACTION_REFUNDORDER = 'REFUND';
    const GUARANTEE_REQUIRED = 'REQUIRED';
    const GUARANTEE_OPTIONAL = 'OPTIONAL';
    const GUARANTEE_NO = 'NO';

    //const PMETHOD ='';

    /*
     * Private properties
     */
    private $discountType;
    private $numberofItems;
    private $discountVat = 0.0;
    private $_order = null;
    private $response;
    private $order_discount_item = 0.0;
    /* @var $_config Payson_Payson_Model_Config */
    private $_config;
    /* @var $_helper Payson_Payson_Helper_Data */
    private $_helper;
    private $_products = array();

    /*
     * Private methods
     */

    public function __construct() {
        $this->_config = Mage::getModel('payson/config');
        $this->_helper = Mage::helper('payson');
        $this->_invoice = Mage::getModel('payson/method/invoice');
    }
    
    private function getHttpClient($url) {

        $client = new Varien_Http_Client();
       
        $client->setUri($url)
            ->setMethod(Zend_Http_Client::POST)
            ->setHeaders(array
                (
                'PAYSON-SECURITY-USERID' => $this->_config->get('test_mode') ? self::DEBUG_MODE_AGENT_ID : $this->_config->Get('agent_id'),
                'PAYSON-SECURITY-PASSWORD' => $this->_config->get('test_mode') ? self::DEBUG_MODE_MD5 : $this->_config->Get('md5_key'),
                'PAYSON-APPLICATION-ID' => self::APPLICATION_ID,
                'PAYSON-MODULE-INFO' => self::MODULE_NAME . '|' . self::MODULE_VERSION . '|' . Mage::getVersion()
                )
            );

        return $client->resetParameters();
    }
   
    private function setResponse(
    Payson_Payson_Helper_Api_Response_Interface $response) {
        $this->response = $response;

        return $this;
    }

    //Private functions for Swedish discount and vat calculations
    private function setAverageVat($vat) {
        $this->discountVat = $vat;
    }

    private function getAverageVat() {
        return $this->discountVat;
    }

    private function setDiscountType($type) {
        $this->discountType = $type;
    }

    private function getDiscountType() {
        return $this->discountType;
    }

    private function setNumberOfItems($items) {
        $this->numberofItems = $items;
    }

    private function getNumberOfItems() {
        return $this->numberofItems;
    }

    private function getStoreCountry() {
        $countryCode = Mage::getStoreConfig('general/country/default');
        $country = Mage::getModel('directory/country')->loadByCode($countryCode);
        return $country->country_id;
    }

    private function setSwedishDiscountItem($item, &$total, $orderitems, $order) {

        /*
         * Discount types $item->getAppliedRuleIds():
         *  Fixed discount amount for the entire cart
         *  Precentage discount for the entire cart
         *  Fixed discount amount for each article in cart 
         */
        $rule = Mage::getModel('salesrule/rule')->load($item->getAppliedRuleIds());
        $total -= $item->getDiscountAmount();
        $numberOfItem = floor($order->getData('total_qty_ordered'));
        $items = 0;
        
        $discountAmount = $item->getDiscountAmount();
        for ($i = 0; $i <= $numberOfItem; $i++) {
            $orderVat = $orderitems['orderItemList.orderItem(' . $i . ').taxPercentage'];
            $moms += $orderVat;
            //counting number of tax rows that is not zero
            if ($orderVat != 0) {
                $items++;
            }
        }
        $this->setNumberOfItems($numberOfItem);
        $this->setDiscountType($rule->simple_action);
        $totalMoms = $moms / $items;

        $this->setAverageVat($totalMoms);

        $this->order_discount_item += $discountAmount;
    }

    private function setInternationalDiscountItem($item, &$total) {
        $total -= $item->getDiscountAmount();
        $this->order_discount_item += $item->getDiscountAmount();
    }

    /**
     * Helper for Pay()
     *
     * @param	Mage_Sales_Model_Order_Item $item
     * @param	int		$total
     * @return	array
     */
   private function getProductOptions($id) {
        $product = new Mage_Catalog_Model_Product();		
	$product->load($id);        
        $product->getTypeInstance(true)->setStoreFilter(Mage::app()->getStore(), $product);
        return $product;
        
    }
    private function prepareOrderItemData($item, &$total, $order) {
        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')
                ->load($item->getProductId());

        $attributesString = "";
        $quoteItems = Mage::getModel('sales/quote_item')->getCollection();
        $quoteItems->addFieldToFilter('quote_id', $order->quote_id);
        $quoteItems->addFieldToFilter('product_type', 'bundle');


        if (($children = $item->getChildrenItems()) != null && !$product->isConfigurable()) {
            $args = array();
            $product = $this->getProductOptions($item->getProductId());
            
            if ($item->getProductType() != 'bundle'||$product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
            $this->prepareProductData($item->getName(), $item->getSku(), $item->getQtyOrdered(), 0, 0);
            } 
            foreach ($children as $child) {
                $this->prepareOrderItemData($child, $total, $order);
            }
            //checks if there are bundles items is present and if it is dynamic
            if (($quoteItems->getSize() < 1)) {
            return;
            } elseif ($product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC && $item->getProductType() == 'bundle') {
                return;
        }
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
        $tax_mod = (float) $item->getTaxPercent();
        $tax_mod /= 100;
        $tax_mod = round($tax_mod, 5);

        $qty = (float) $item->getQtyOrdered();
        $qty = round($qty, 2);

        $price = (float) $item->getRowTotalInclTax();

        $base_price = (($price / (1 + $tax_mod)) / $qty);
        $base_price = round($base_price, 3);

        $total += (($base_price * (1 + $tax_mod)) * $qty);

        $this->prepareProductData($name, $sku, $qty, $base_price, $tax_mod);
    }

    private function generateProductDataForPayson(array $args) {
        $productData = array();
        for ($i = 0; $i < sizeof($this->_products); $i++) {
            $productData += array
                (
                'orderItemList.orderItem(' . $i . ').description' =>
                $this->_products[$i]['Description'],
                'orderItemList.orderItem(' . $i . ').sku' =>
                $this->_products[$i]['Sku'],
                'orderItemList.orderItem(' . $i . ').quantity' =>
                $this->_products[$i]['Quantity'],
                'orderItemList.orderItem(' . $i . ').unitPrice' =>
                $this->_products[$i]['Price'],
                'orderItemList.orderItem(' . $i . ').taxPercentage' =>
                $this->_products[$i]['Tax']
            );
            $args += $productData;
        }

        return $args;
    }

    private function prepareProductData($description, $sku, $qty, $base_price, $tax_mod) {
        $description = strlen($description) <= 128 ? $description : substr($description, 128);
        $sku = strlen($sku) <= 128 ? $sku : substr($sku, 128);
        $this->_products[] = array("Description" => $description, "Sku" => $sku,
            "Quantity" => $qty, "Price" => $base_price, "Tax" => $tax_mod);
    }

    /**
     * Helper for Pay()
     *
     * @param	object	$order
     * @param	object	$customer
     * @param	object	$store
     * @param	int		$i
     * @param	int		$total
     */
    private function prepareOrderShippingData($order, $customer, $store, &$total) {
        $tax_calc = Mage::getSingleton('tax/calculation');

        $tax_rate_req = $tax_calc->getRateRequest(
                $order->getShippingAddress(), $order->getBillingAddress(), $customer->getTaxClassId(), $store);

        if (($price = (float) $order->getShippingInclTax()) > 0) {
            $tax_mod = $tax_calc->getRate($tax_rate_req->setProductClassId(
                            Mage::getStoreConfig('tax/classes/shipping_tax_class')));
            $tax_mod /= 100;
            $tax_mod = round($tax_mod, 5);

            $price -= (float) $order->getShippingDiscountAmount();

            $base_price = ($price / (1 + $tax_mod));
            $base_price = round($base_price, 3);

            $total += ($base_price * (1 + $tax_mod));

            $this->prepareProductData($order->getShippingDescription(), $order->getShippingMethod(), 1, $base_price, $tax_mod);
        }
    }

    /*
     * Public methods
     */

    /**
     * Get API response
     *
     * @return	object
     */
    public function GetResponse() {
        return $this->response;
    }

    /**
     * Get forward/redirect url after a successful Pay() call
     *
     * @return	string
     */
    public function GetPayForwardUrl() {
        $url = vsprintf(self::PAY_FORWARD_URL . '?token=' . $this->GetResponse()->TOKEN, $this->getFormatIfTest(null, true));
        return $url;
    }

    /**
     * http://api.payson.se/#title8
     *
     * @param	object	$order
     * @return	object					
     */
    public function showReceiptPage() {
        $Config = (int) $this->_config->get('show_receipt_page');
        $reciept2 = 'false';
        if ($Config === 1) {
            $reciept2 = 'true';
        }
        return $reciept2;
    }

    public function vatDiscount() {
        $inputValue = (int) $this->_config->Get('vat_discount');
        $enableVatDiscount = 'false';
        if ($inputValue === 1) {
            $enableVatDiscount = 'true';
        }
        return $enableVatDiscount;
    }

    public function Pay(Mage_Sales_Model_Order $order) {

        $payment_method = $order->getPayment()->getMethod();

        /* @var $store Mage_Core_Model_Store */
        $store = Mage::app()->getStore($order->getStoreId());
        $customer = Mage::getModel('customer/customer')
                ->load($order->getCustomerId());
        $billing_address = $order->getBillingAddress();

        // Need a two character locale code. This collects the store chosen language
        $locale_code = Mage::getSingleton('core/locale')->getLocaleCode();
        $locale_code = strtoupper(substr($locale_code, 0, 2));


        if (!in_array($locale_code, array('SV', 'FI', 'EN'))) {
            switch ($locale_code) {
                case 'DA':
                case 'NO': {
                        $locale_code = 'SV';
                        break;
                    }
                default: {
                        $locale_code = 'EN';
                    }
            }
        }

        // $args will contain the arguments of the API request to Payson
        $args = array
            (
            'returnUrl' =>
            Mage::getUrl('payson/checkout/return', array('_secure' => true)),
            'cancelUrl' =>
            Mage::getUrl('payson/checkout/cancel', array('_secure' => true)),
            'ipnNotificationUrl' =>
            Mage::getUrl('payson/ipn/notify', array('_secure' => true)),
            'localeCode' =>
            $locale_code,
            'currencyCode' =>
            strtoupper(substr($order->getOrderCurrency()->getCode(), 0, 3)),
            'memo' =>
            sprintf($this->_helper->__('Order from %s'), $store->getName()),
            'senderEmail' =>
            $order->getCustomerEmail(),
            'senderFirstName' =>
            $billing_address->getFirstname(),
            'senderLastName' =>
            $billing_address->getLastname(),
            'receiverList.receiver(0).email' =>
            $this->_config->get('test_mode') ? self::DEBUG_MODE_MAIL : $this->_config->Get('email'),
            'trackingId' => $order->getRealOrderId(),
            'showReceiptPage' => $this->showReceiptPage()
        );

        if (!$this->_config->CanPaymentGuarantee()) {
            $args['guaranteeOffered'] = self::GUARANTEE_NO;
        }
        $isCurrency = strtoupper(Mage::app()->getStore()->getCurrentCurrencyCode());
        $paymentMethod = $this->_config->get('payson_All_in_one');
        //Get Payson paymentmethod
        //Direct Payment
        if ($this->_config->CanStandardPayment() && $isCurrency == 'SEK') {
            $payment = $this->getConstrains($paymentMethod);
        }
        //InvoicePayment with check if invoice amount is above minimum
        if ($this->_config->CanInvoicePayment() && ($order->getSubtotal() >= $this->invoiceAmountMinLimit) && ($isCurrency == 'SEK')) {
            $payment = $this->getConstrains($paymentMethod);
        }
        //Remove invoice if invoice amount is below minimum amount
        if ($this->_config->CanInvoicePayment() && ($order->getSubtotal() < $this->invoiceAmountMinLimit) && ($isCurrency == 'SEK')) {
            $disableInvoice = true;
            $payment = $this->getConstrains($paymentMethod);
            if (in_array(3, $payment)) {
                $newArray = array();
                foreach ($payment as $pkey) {
                    if ($pkey != 3)
                        $newArray[] = $pkey;
                }
                $payment = $newArray;
            }
        }
        //If other currency than SEK remove payment option Invocie and SMS
        if (($this->_config->CanInvoicePayment() || $this->_config->CanStandardPayment()) && ($isCurrency != 'SEK')) {
            $payment = $this->getConstrains($paymentMethod);
            //Remove Invoice from array
            if (in_array(3, $payment)) {
                $disableInvoice = true;
                $newArray = array();
                foreach ($payment as $pkey) {
                    if ($pkey != 3) {
                        $newArray[] = $pkey;
                    }
                }
                $payment = $newArray;
            }
            //Remove SMS from array
            if (in_array(4, $payment)) {
                $newArray = array();
                foreach ($payment as $pkey) {
                    if ($pkey != 4) {
                        $newArray[] = $pkey;
                    }
                }
                $payment = $newArray;
            }
        }

        $result = (!isset($payment)) ? $payment = '' : $payment;

        define("PMETHOD", serialize($result));
        $output = array();
        FundingConstraint::addConstraintsToOutput($result, $output);
        $args = array_merge($args, $output);

        // Calculate price of each item in the order
        $total = 0;
        foreach ($order->getAllVisibleItems() as $item) {
            $this->prepareOrderItemData($item, $total, $order);
        }

        $productItems = $this->generateProductDataForPayson($args);
        $customerCountry = $order->getBillingAddress()->country_id;
        if ($this->getStoreCountry() == 'SE' && $customerCountry == 'SE' && $this->vatDiscount() == 'true') {

            foreach ($order->getAllVisibleItems() as $item) {
                $this->setSwedishDiscountItem($item, $total, $productItems, $order);
            }
            if ($this->order_discount_item > 0) {
                switch ($this->getDiscountType()) {
                    case Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION:
                    case Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION:
                        $this->prepareProductData('Rabatt inkl moms', 'Rabatt', 1, -$this->order_discount_item, $this->getAverageVat());
                        break;
                    case Mage_SalesRule_Model_Rule::BY_FIXED_ACTION:
                        $specialDiscount = $this->order_discount_item / $this->getNumberOfItems();
                        $this->prepareProductData('Rabatt inkl moms', 'Rabatt', $this->getNumberOfItems(), -$specialDiscount, $this->getAverageVat());
                        break;
                    default:
                        $this->prepareProductData('Rabatt inkl moms', 'Rabatt', 1, -$this->order_discount_item, $this->getAverageVat());
                        break;
                }
            }
        } else {

            foreach ($order->getAllVisibleItems() as $item) {
                $this->setInternationalDiscountItem($item, $total);
            } 

            if ($this->order_discount_item > 0) {
                $this->prepareProductData('discount', 'discount', 1, -$this->order_discount_item, 0.0);
            }
        }
//        

        // Calculate price for shipping
        $this->prepareOrderShippingData($order, $customer, $store, $total);
        $args = $this->generateProductDataForPayson($args);

        if ($this->_config->CanInvoicePayment() && ($order->getSubtotal() >= $this->invoiceAmountMinLimit) && !$disableInvoice) {

            if ($order->getPaysonInvoiceFee() > 0) {

                $fee = $order->getPaysonInvoiceFee();

                $args['invoiceFee'] = round((float) $fee, 3);
                $total += $fee;
            }
        }
        $roundedTotal = round($total, 2);
        if ($this->getStoreCountry() == 'SE' && $customerCountry == 'SE' && $this->vatDiscount() == 'true') {
            if ($this->order_discount_item > 0) {
                switch ($this->getDiscountType()) {
                    case Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION:
                    case Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION:
                    case Mage_SalesRule_Model_Rule::BY_FIXED_ACTION:
                    case Mage_SalesRule_Model_Rule::CART_FIXED_ACTION:
                        $roundedTotal = $roundedTotal - ($this->order_discount_item * $this->getAverageVat());
                        break;

                    default:
                        break;
                }
            }
        }
        $args['receiverList.receiver(0).amount'] = $roundedTotal;

        $url = vsprintf(self::API_CALL_PAY, $this->getFormatIfTest($order->getStoreId()));

        $client = $this->getHttpClient($url)
                ->setParameterPost($args);

        $response = Payson_Payson_Helper_Api_Response_Standard
                ::FromHttpBody($client->request('POST')->getBody());
        $this->setResponse($response);


        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $order_table = $resource->getTableName('payson_order');
        $order_log_table = $resource->getTableName('payson_order_log');

        $db->insert($order_table, array
            (
            'order_id' => $order->getRealOrderId(),
            'added' => new Zend_Db_Expr('NOW()'),
            'updated' => new Zend_Db_Expr('NOW()'),
            'valid' => (int) $response->IsValid(),
            'token' => (isset($response->TOKEN) ? $response->TOKEN :
                    new Zend_Db_Expr('NULL')),
            'store_id' => $order->getStoreId()
        ));

        $payson_order_id = $db->lastInsertId();

        $db->insert($order_log_table, array
            (
            'payson_order_id' => $payson_order_id,
            'added' => new Zend_Db_Expr('NOW()'),
            'api_call' => 'pay',
            'valid' => (int) $response->IsValid(),
            'response' => serialize($response->ToArray())
        ));

        if (!$response->IsValid()) {

            throw new Mage_Core_Exception(sprintf($this->_helper->__(
                            'Failed to initialize payment. Payson replied: %s'), $response->GetError()), $response->GetErrorId());
        }

        return $this;
    }

    public function PaymentMethod() {
        
    }

    private function getConstrains($paymentMethod) {

        $constraints = array();
        $opts = array(
            -1 => array(''),
            0 => array('sms'),
            1 => array('bank'),
            2 => array('card'),
            3 => array('bank', 'sms'),
            4 => array('card', 'sms'),
            5 => array('card', 'bank'),
            6 => array('card', 'bank', 'sms'),
            7 => array(''),
            8 => array('invoice'),
            9 => array('invoice', 'sms'),
            10 => array('invoice', 'bank'),
            11 => array('invoice', 'card'),
            12 => array('invoice', 'bank', 'sms'),
            13 => array('invoice', 'card', 'sms'),
            14 => array('invoice', 'card', 'bank'),
            15 => array('invoice', 'card', 'bank', 'sms'),
        );
        $optsStrings = array('' => FundingConstraint::NONE, 'bank' => FundingConstraint::BANK, 'card' => FundingConstraint::CREDITCARD, 'invoice' => FundingConstraint::INVOICE, 'sms' => FundingConstraint::SMS);
        if ($opts[$paymentMethod]) {
            foreach ($opts[$paymentMethod] as $methodStringName) {
                $constraints[] = $optsStrings[$methodStringName];
            }
        }
        return $constraints;
    }

    /**
     * Implements the IPN procedure
     *
     * http://api.payson.se/#title11
     *
     * @param	string	$http_body
     * @param	string	$content_type
     * @return	object					$this
     */
    public function confirmationEmail($entityId) {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $order_table = $resource->getTableName('sales_flat_order');
        $status = $db->fetchRow(
                'SELECT status FROM `' . $order_table . '` WHERE
	entity_id = ? LIMIT 0,1', $entityId);

        return $status;
    }

    public function Validate($http_body, $content_type) {

        // Parse request done by Payson to our IPN controller
        $ipn_response = Payson_Payson_Helper_Api_Response_Standard
                ::FromHttpBody($http_body);
        // Get the database connection
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $order_table = $resource->getTableName('payson_order');
        $order_log_table = $resource->getTableName('payson_order_log');


        /* Save data sent by Payson, log entry as invalid by default, this
          value will be changed later in this method if successful. No payson
          order id is set, because we dont have one yet */
        $db->insert($order_log_table, array
            (
            'added' => new Zend_Db_Expr('NOW()'),
            'api_call' => 'validate',
            'valid' => 0,
            'response' => serialize($ipn_response->ToArray())
        ));

        $order_log_id = $db->lastInsertId();

        /* Save fetch mode so that we can reset it and not mess up Magento
          functionality */
        $old_fetch_mode = $db->getFetchMode();
        $db->setFetchMode(Zend_Db::FETCH_OBJ);

        // Get payson order information and validate token
        $payson_order = $db->fetchRow(
                'SELECT
	id,
	order_id,
        store_id
FROM
	`' . $order_table . '`
WHERE
	valid = 1
AND
	token = ?
LIMIT
	0,1', $ipn_response->token);

        if ($payson_order === false) {
            Mage::throwException('Invalid token');
        }

        // Do the validate API call
        $client = $this->getHttpClient(vsprintf(self::API_CALL_VALIDATE, $this->getFormatIfTest($payson_order->store_id)))
                ->setRawData($http_body, $content_type);

        $response = Payson_Payson_Helper_Api_Response_Validate
                ::FromHttpBody($client->request('POST')->getBody());

        $this->setResponse($response);

        if (!$response->IsValid()) {
            Mage::throwException('Validate call was unsuccessful');
        }



        // Update order log with payson order id
        $db->update($order_log_table, array
            (
            'payson_order_id' => $payson_order->id
                ), array
            (
            'id = ?' => $order_log_id
        ));

        // the order model does not expect FETCH_OBJ!
        $db->setFetchMode($old_fetch_mode);

        /**
         * @var Mage_Sales_Model_Order
         */
        $order = Mage::getModel('sales/order')
                ->loadByIncrementId($payson_order->order_id);

        // Stop if order dont exist
        if (is_null($order->getId())) {
            Mage::throwException('Invalid order');
        }

        if ($order->getState() === Mage_Sales_Model_Order::STATE_COMPLETE) {
            Mage::throwException('Order is no longer active');
        }
        $sendEmail = $this->confirmationEmail($order->getEntityId());
        $receivers = $ipn_response->receiverList->receiver->ToArray();
        $investigatefee = $order['base_payson_invoice_fee'];

        $new_receivers = array();
        foreach ($receivers as $item) {
            foreach ($item as $key => $value) {
                $new_receivers[$key] = $value;
            }
        }
        $currentAmount = $new_receivers['amount'];
        $newAmount = $currentAmount += $investigatefee;

        /* Verify payment amount. floor() since there might be a precision
          difference */
        switch ($ipn_response->status) {
            case self::STATUS_COMPLETED: {
                    //Changes the status of the order from pending_payment to processing
                    if ($sendEmail['status'] == 'pending_payment') {
                        $order->sendNewOrderEmail()->save();
                    }
                    $order->setState(
                            Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $this->_config->get('test_mode') ? $this->_helper->__('Payson test completed the order payment') : $this->_helper->__('Payson completed the order payment'));
                    $order['payson_invoice_fee'] = 0;
                    $order['base_payson_invoice_fee'] = 0;
                    //It creates the invoice to the order
                    $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    $transactionSave = Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder());
                    $transactionSave->save();

                    break;
                }
            case self::STATUS_CREATED:
            case self::STATUS_PENDING:
            case self::STATUS_PROCESSING:
            case self::STATUS_CREDITED: {
                    if (($ipn_response->status === self::STATUS_PENDING) &&
                            ($ipn_response->type === self::PAYMENT_METHOD_INVOICE) &&
                            ($ipn_response->invoiceStatus ===
                            self::INVOICE_STATUS_ORDERCREATED)) {
                        if ($sendEmail['status'] == 'pending_payment') {
                            $order->sendNewOrderEmail()->save();
                        }
                        //Changes the status of the order from pending to processing
                        $order->setState(
                                Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $this->_config->get('test_mode') ? $this->_helper->__('Payson test created an invoice') : $this->_helper->__('Payson created an invoice'));
                        $order->setBaseGrandTotal($newAmount);
                        $order->setGrandTotal($newAmount);
                        $order->setTotalDue($newAmount);
                        $order->save();


                        if (isset($ipn_response->shippingAddress)) {
                            $address_info = $ipn_response->shippingAddress
                                    ->ToArray();

                            $address = $order->getShippingAddress();

                            if (isset($address_info['name'])) {
                                $address->setFirstname($address_info['name']);
                                $address->setMiddlename('');
                                $address->setLastname('');
                            }

                            if (isset($address_info['streetAddress'])) {
                                $address->setStreet($address_info['streetAddress']);
                            }

                            if (isset($address_info['postalCode'])) {
                                $address->setPostcode($address_info['streetAddress']);
                            }

                            if (isset($address_info['city'])) {
                                $address->setCity($address_info['city']);
                            }

                            if (isset($address_info['country'])) {

                                $foo = array
                                    (
                                    'afghanistan' => 'AF',
                                    'albania' => 'AL',
                                    'algeria' => 'DZ',
                                    'american samoa' => 'AS',
                                    'andorra' => 'AD',
                                    'angola' => 'AO',
                                    'anguilla' => 'AI',
                                    'antarctica' => 'AQ',
                                    'antigua and barbuda' => 'AG',
                                    'argentina' => 'AR',
                                    'armenia' => 'AM',
                                    'aruba' => 'AW',
                                    'australia' => 'AU',
                                    'austria' => 'AT',
                                    'azerbaijan' => 'AZ',
                                    'bahamas' => 'BS',
                                    'bahrain' => 'BH',
                                    'bangladesh' => 'BD',
                                    'barbados' => 'BB',
                                    'belarus' => 'BY',
                                    'belgium' => 'BE',
                                    'belize' => 'BZ',
                                    'benin' => 'BJ',
                                    'bermuda' => 'BM',
                                    'bhutan' => 'BT',
                                    'bolivia' => 'BO',
                                    'bosnia and herzegovina' => 'BA',
                                    'botswana' => 'BW',
                                    'bouvet island' => 'BV',
                                    'brazil' => 'BR',
                                    'british indian ocean territory' => 'IO',
                                    'brunei darussalam' => 'BN',
                                    'bulgaria' => 'BG',
                                    'burkina faso' => 'BF',
                                    'burundi' => 'BI',
                                    'cambodia' => 'KH',
                                    'cameroon' => 'CM',
                                    'canada' => 'CA',
                                    'cape verde' => 'CV',
                                    'cayman islands' => 'KY',
                                    'central african republic' => 'CF',
                                    'chad' => 'TD',
                                    'chile' => 'CL',
                                    'china' => 'CN',
                                    'christmas island' => 'CX',
                                    'cocos (keeling) islands' => 'CC',
                                    'colombia' => 'CO',
                                    'comoros' => 'KM',
                                    'congo' => 'CG',
                                    'congo, the democratic republic of the' => 'CD',
                                    'cook islands' => 'CK',
                                    'costa rica' => 'CR',
                                    'cote d ivoire' => 'CI',
                                    'croatia' => 'HR',
                                    'cuba' => 'CU',
                                    'cyprus' => 'CY',
                                    'czech republic' => 'CZ',
                                    'denmark' => 'DK',
                                    'djibouti' => 'DJ',
                                    'dominica' => 'DM',
                                    'dominican republic' => 'DO',
                                    'east timor' => 'TP',
                                    'ecuador' => 'EC',
                                    'egypt' => 'EG',
                                    'el salvador' => 'SV',
                                    'equatorial guinea' => 'GQ',
                                    'eritrea' => 'ER',
                                    'estonia' => 'EE',
                                    'ethiopia' => 'ET',
                                    'falkland islands (malvinas)' => 'FK',
                                    'faroe islands' => 'FO',
                                    'fiji' => 'FJ',
                                    'finland' => 'FI',
                                    'france' => 'FR',
                                    'french guiana' => 'GF',
                                    'french polynesia' => 'PF',
                                    'french southern territories' => 'TF',
                                    'gabon' => 'GA',
                                    'gambia' => 'GM',
                                    'georgia' => 'GE',
                                    'germany' => 'DE',
                                    'ghana' => 'GH',
                                    'gibraltar' => 'GI',
                                    'greece' => 'GR',
                                    'greenland' => 'GL',
                                    'grenada' => 'GD',
                                    'guadeloupe' => 'GP',
                                    'guam' => 'GU',
                                    'guatemala' => 'GT',
                                    'guinea' => 'GN',
                                    'guinea-bissau' => 'GW',
                                    'guyana' => 'GY',
                                    'haiti' => 'HT',
                                    'heard island and mcdonald islands' => 'HM',
                                    'holy see (vatican city state)' => 'VA',
                                    'honduras' => 'HN',
                                    'hong kong' => 'HK',
                                    'hungary' => 'HU',
                                    'iceland' => 'IS',
                                    'india' => 'IN',
                                    'indonesia' => 'ID',
                                    'iran, islamic republic of' => 'IR',
                                    'iraq' => 'IQ',
                                    'ireland' => 'IE',
                                    'israel' => 'IL',
                                    'italy' => 'IT',
                                    'jamaica' => 'JM',
                                    'japan' => 'JP',
                                    'jordan' => 'JO',
                                    'kazakstan' => 'KZ',
                                    'kenya' => 'KE',
                                    'kiribati' => 'KI',
                                    'korea democratic peoples republic of' => 'KP',
                                    'korea republic of' => 'KR',
                                    'kuwait' => 'KW',
                                    'kyrgyzstan' => 'KG',
                                    'lao peoples democratic republic' => 'LA',
                                    'latvia' => 'LV',
                                    'lebanon' => 'LB',
                                    'lesotho' => 'LS',
                                    'liberia' => 'LR',
                                    'libyan arab jamahiriya' => 'LY',
                                    'liechtenstein' => 'LI',
                                    'lithuania' => 'LT',
                                    'luxembourg' => 'LU',
                                    'macau' => 'MO',
                                    'macedonia, the former yugoslav republic of' => 'MK',
                                    'madagascar' => 'MG',
                                    'malawi' => 'MW',
                                    'malaysia' => 'MY',
                                    'maldives' => 'MV',
                                    'mali' => 'ML',
                                    'malta' => 'MT',
                                    'marshall islands' => 'MH',
                                    'martinique' => 'MQ',
                                    'mauritania' => 'MR',
                                    'mauritius' => 'MU',
                                    'mayotte' => 'YT',
                                    'mexico' => 'MX',
                                    'micronesia, federated states of' => 'FM',
                                    'moldova, republic of' => 'MD',
                                    'monaco' => 'MC',
                                    'mongolia' => 'MN',
                                    'montserrat' => 'MS',
                                    'morocco' => 'MA',
                                    'mozambique' => 'MZ',
                                    'myanmar' => 'MM',
                                    'namibia' => 'NA',
                                    'nauru' => 'NR',
                                    'nepal' => 'NP',
                                    'netherlands' => 'NL',
                                    'netherlands antilles' => 'AN',
                                    'new caledonia' => 'NC',
                                    'new zealand' => 'NZ',
                                    'nicaragua' => 'NI',
                                    'niger' => 'NE',
                                    'nigeria' => 'NG',
                                    'niue' => 'NU',
                                    'norfolk island' => 'NF',
                                    'northern mariana islands' => 'MP',
                                    'norway' => 'NO',
                                    'oman' => 'OM',
                                    'pakistan' => 'PK',
                                    'palau' => 'PW',
                                    'palestinian territory, occupied' => 'PS',
                                    'panama' => 'PA',
                                    'papua new guinea' => 'PG',
                                    'paraguay' => 'PY',
                                    'peru' => 'PE',
                                    'philippines' => 'PH',
                                    'pitcairn' => 'PN',
                                    'poland' => 'PL',
                                    'portugal' => 'PT',
                                    'puerto rico' => 'PR',
                                    'qatar' => 'QA',
                                    'reunion' => 'RE',
                                    'romania' => 'RO',
                                    'russian federation' => 'RU',
                                    'rwanda' => 'RW',
                                    'saint helena' => 'SH',
                                    'saint kitts and nevis' => 'KN',
                                    'saint lucia' => 'LC',
                                    'saint pierre and miquelon' => 'PM',
                                    'saint vincent and the grenadines' => 'VC',
                                    'samoa' => 'WS',
                                    'san marino' => 'SM',
                                    'sao tome and principe' => 'ST',
                                    'saudi arabia' => 'SA',
                                    'senegal' => 'SN',
                                    'seychelles' => 'SC',
                                    'sierra leone' => 'SL',
                                    'singapore' => 'SG',
                                    'slovakia' => 'SK',
                                    'slovenia' => 'SI',
                                    'solomon islands' => 'SB',
                                    'somalia' => 'SO',
                                    'south africa' => 'ZA',
                                    'south georgia and the south sandwich islands' => 'GS',
                                    'spain' => 'ES',
                                    'sri lanka' => 'LK',
                                    'sudan' => 'SD',
                                    'suriname' => 'SR',
                                    'svalbard and jan mayen' => 'SJ',
                                    'swaziland' => 'SZ',
                                    'sweden' => 'SE',
                                    'switzerland' => 'CH',
                                    'syrian arab republic' => 'SY',
                                    'taiwan, province of china' => 'TW',
                                    'tajikistan' => 'TJ',
                                    'tanzania, united republic of' => 'TZ',
                                    'thailand' => 'TH',
                                    'togo' => 'TG',
                                    'tokelau' => 'TK',
                                    'tonga' => 'TO',
                                    'trinidad and tobago' => 'TT',
                                    'tunisia' => 'TN',
                                    'turkey' => 'TR',
                                    'turkmenistan' => 'TM',
                                    'turks and caicos islands' => 'TC',
                                    'tuvalu' => 'TV',
                                    'uganda' => 'UG',
                                    'ukraine' => 'UA',
                                    'united arab emirates' => 'AE',
                                    'united kingdom' => 'GB',
                                    'united states' => 'US',
                                    'united states minor outlying islands' => 'UM',
                                    'uruguay' => 'UY',
                                    'uzbekistan' => 'UZ',
                                    'vanuatu' => 'VU',
                                    'venezuela' => 'VE',
                                    'viet nam' => 'VN',
                                    'virgin islands, british' => 'VG',
                                    'virgin islands, u.s.' => 'VI',
                                    'wallis and futuna' => 'WF',
                                    'western sahara' => 'EH',
                                    'yemen' => 'YE',
                                    'yugoslavia' => 'YU',
                                    'zambia' => 'ZM',
                                    'zimbabwe' => 'ZW'
                                );

                                $address_info['country'] = strtolower(
                                        $address_info['country']);

                                if (isset($foo[$address_info['country']])) {
                                    $address->setCountryId(
                                            $foo[$address_info['country']]);
                                }
                            }

                            $address->save();
                            $order->addStatusHistoryComment(sprintf($this->_helper->__(
                                                    'Payson updated the shipping address')));
                        }
                    } else {
                        $order['payson_invoice_fee'] = 0;
                        $order['base_payson_invoice_fee'] = 0;
                        $order->addStatusHistoryComment(sprintf(
                                        $this->_helper->__('Payson pinged the order with status %s'), $ipn_response->status));
                    }

                    break;
                }

            case self::STATUS_ERROR:
            case self::STATUS_DENIED:

                $order->cancel();


                $order->addStatusHistoryComment($this->_helper->__('The order was denied by Payson.'));

                break;

            case self::STATUS_INCOMPLETE:
            case self::STATUS_EXPIRED:
            case self::STATUS_CANCELED:
            case self::STATUS_ABORTED:
                $order->cancel();

                $order->addStatusHistoryComment($this->_helper->__('The order was canceled or not completed within allocated time'));
                break;

            case self::STATUS_REVERSALERROR:
            default: {
                    $order->cancel();
                }
        }

        $order->save();
        $db->update($order_log_table, array
            (
            'valid' => 1
                ), array
            (
            'id = ?' => $order_log_id
        ));

        $db->update($order_table, array
            (
            'ipn_status' => $ipn_response->status
                ), array
            (
            'id = ?' => $payson_order->id
        ));


        return $this;
    }

    public function PaymentDetails($order_id) {

        // Get the database connection
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $order_table = $resource->getTableName('payson_order');
        $order_log_table = $resource->getTableName('payson_order_log');
        /* Save fetch mode so that we can reset it and not mess up Magento
          functionality */
        $old_fetch_mode = $db->getFetchMode();
        $db->setFetchMode(Zend_Db::FETCH_OBJ);

        // Get payson order information and validate token
        $payson_order = $db->fetchRow(
                'SELECT
	id,
	token,
        store_id
FROM
	`' . $order_table . '`
WHERE
	valid = 1
AND
	order_id = ?
LIMIT
	0,1', $order_id);

        try {
            $payson_order !== false;
        } catch (Exception $e) {
            Mage::throwException('Invalid order id (' . $order_id . ')' . $e->getMessage());
        }
        $db->setFetchMode($old_fetch_mode);

        $args = array
            (
            'token' => $payson_order->token
        );
        $url = vsprintf(self::API_CALL_PAYMENT_DETAILS, $this->getFormatIfTest($payson_order->store_id));

        $client = $this->getHttpClient($url)
                ->setParameterPost($args);

        $response = Payson_Payson_Helper_Api_Response_Standard
                ::FromHttpBody($client->request('POST')->getBody());

        $this->setResponse($response);

        $db->insert($order_log_table, array
            (
            'payson_order_id' => $payson_order->id,
            'added' => new Zend_Db_Expr('NOW()'),
            'api_call' => 'payment_details',
            'valid' => (int) $response->IsValid(),
            'response' => serialize($response->ToArray())
        ));

        $payson_validator = $db->fetchRow(
                'SELECT ipn_status, token FROM `' . $order_table . '` WHERE order_id = ? LIMIT 0,1', $order_id);
        if ((!$response->IsValid()) && ($payson_validator->ipn_status == NULL && $payson_validator->token == NULL)) {

            $sales_flat_order = 'sales_flat_order';
            if ($order_id !== null && $payson_order !== false) {
                $new_order_id = Mage::getModel('sales/order')->loadByIncrementId($order_id)->getEntityId();
                $db->update($sales_flat_order, array('state' => 'canceled', 'status' => 'canceled'), array('entity_id = ?' => $new_order_id));
            }
        }

        if (!$response->IsValid()) {
            $redirectUrl = Mage::getUrl('checkout/cart');
            Mage::getSingleton('checkout/session')->setRedirectUrl($redirectUrl);
        }

        return $this;
    }

    /**
     * http://api.payson.se/#title13
     *
     * @params	int		$order_id	Real order id
     * @params	string	$action
     * @return	object				$this
     */
    public function PaymentUpdate($order_id, $action) {

        // Get the database connection
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $order_table = $resource->getTableName('payson_order');
        $order_log_table = $resource->getTableName('payson_order_log');

        /* Save fetch mode so that we can reset it and not mess up Magento
          functionality */
        $old_fetch_mode = $db->getFetchMode();
        $db->setFetchMode(Zend_Db::FETCH_OBJ);

        // Get payson order information and validate token
        $payson_order = $db->fetchRow(
                'SELECT
	id,
	token,
        store_id
FROM
	`' . $order_table . '`
WHERE
	valid = 1
AND
	order_id = ?
LIMIT
	0,1', $order_id);



        try {
            $payson_order !== false;
        } catch (Exception $e) {
            Mage::throwException('Invalid order id (' . $order_id . ')' . $e->getMessage());
        }

        $db->setFetchMode($old_fetch_mode);

        $args = array
            (
            'token' => $payson_order->token,
            'action' => $action
        );

        $client = $this->getHttpClient(vsprintf(self::API_CALL_PAYMENT_UPDATE, $this->getFormatIfTest($payson_order->store_id)))
                ->setParameterPost($args);

        $response = Payson_Payson_Helper_Api_Response_Standard
                ::FromHttpBody($client->request('POST')->getBody());

        $this->setResponse($response);

        $db->insert($order_log_table, array
            (
            'payson_order_id' => $payson_order->id,
            'added' => new Zend_Db_Expr('NOW()'),
            'api_call' => 'payment_update',
            'valid' => (int) $response->IsValid(),
            'response' => serialize($response->ToArray())
        ));

        return $this;
    }

    private function getFormatIfTest($storeID = null, $isForwardURL = FALSE) {

        $stack = array();
        /* @var $isTest bool */
        $isTest = ($this->_config->get('test_mode', $storeID) == "1");

        array_push($stack, self::DEBUG_MODE ? "http" : "https");
        array_push($stack, $isTest && !self::DEBUG_MODE ? "test-" : (self::DEBUG_MODE && !$isForwardURL ? "mvc" : ""));

        if ($isForwardURL == true) {
            array_push($stack, self::DEBUG_MODE ? "app" : "www");
        }

        array_push($stack, self::DEBUG_MODE ? "local" : "se");
        array_push($stack, self::DEBUG_MODE ? "Payment" : "1.0");

        array_push($stack, self::DEBUG_MODE ? "" : "Payment");
        return $stack;
    }

    public function getIpnStatus($order_id) {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $order_table = $resource->getTableName('payson_order');
        $query = 'SELECT ipn_status FROM `' . $order_table . '` WHERE order_id = ' . $order_id;
        return $db->fetchRow($query);
    }

    public function paysonApiError($error) {
        $error_code = '<html>
                            <head>
                            <meta http-equiv="Content-Type" content="text/html" charset="utf-8" />
				<script type="text/javascript"> 
                                    alert("' . $error . '");
                                    window.location="' . ('/index.php') . '";
				</script>
                            </head>
                           </html>';
        echo $error_code;
        exit;
    }

}
