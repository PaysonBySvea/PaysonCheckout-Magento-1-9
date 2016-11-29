<?php

class Payson_Payson_Model_Standard extends Mage_Payment_Model_Method_Abstract {
    /*
     * Protected properties
     */

    /**
     * @inheritDoc
     */
    protected $_code = 'payson';
    protected $_formBlockType = 'payson/form';
    //protected $_infoBlockType = 'payson/info';

    /**
     * @inheritDoc
     */
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;

    /**
     * @inheritDoc
     */
    protected $_canCancelInvoice = true;

    /*
     * Public methods
     */

    /**
     * @inheritDoc
     */
    public function initialize($payment_action, $state_object) {
        $state_object->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $state_object->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $state_object->setIsNotified(false);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function capture(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();
        $order_id = $order->getData('increment_id');

        $api = Mage::helper('payson/api');

        $api->PaymentDetails($order_id);
        $details = $api->GetResponse();

        if (($details->type ===
                Payson_Payson_Helper_Api::PAYMENT_METHOD_INVOICE) ||
                ($details->invoiceStatus ===
                Payson_Payson_Helper_Api::INVOICE_STATUS_ORDERCREATED)) {
            $api->PaymentUpdate($order_id, Payson_Payson_Helper_Api::UPDATE_ACTION_SHIPORDER);
        }

        return $this;
    }

    /**
     * Redirect url when user place order
     *
     * @return	string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('payson/checkout/redirect', array('_secure' => true));
    }

    /**
     * Whether this paymend method is available for specified currency
     *
     * @param	string	$currency
     * @return	bool
     */
    public function canUseForCurrency($currency) {
        return Mage::getModel('payson/config')->IsCurrencySupported($currency);
    }

    /**
     * @inheritDoc
     */
    public function getTitle() {
        $order = Mage::registry('current_order');
        $invoice_fee = $order->getPaysonInvoiceFee();
        $text_invoice_fee = strip_tags($invoice_fee);
        if ($this->_config->CanStandardPayment()) {
            return sprintf(Mage::helper('payson')->__('Checkout with Payson. If invoice is choosen as payment method an %s invoice fee will be added.'), ($text_invoice_fee));
        } elseif ($this->_config->CanStandardPayment()) {
            return Mage::helper('payson')->__('Checkout with Payson');
        }
    }

}
