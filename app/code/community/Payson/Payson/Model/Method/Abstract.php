<?php

abstract class Payson_Payson_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract {

    /**
     * @inheritDoc
     */
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false; // true
    protected $_canUseCheckout = true; // true
    protected $_canUseForMultishipping = false; // true
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false; // true

    /**
     * @inheritDoc
     */
    protected $_canCancelInvoice = true;

    /*
     * Protected methods
     */

    protected function GetCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    protected function GetQuote() {
        return $this->GetCheckout()->getQuote();
    }

    /*
     * Public methods
     */

    /**
     * Redirect url when user place order
     *
     * @return	string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('payson/checkout/redirect', array('_secure' => true));
    }

    /**
     * @inheritDoc
     */
    /* public function initialize($payment_action, $state_object)
      {
      $state_object->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
      $state_object->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
      $state_object->setIsNotified(false);

      return $this;
      } */

    /**
     * Whether this paymend method is available for specified currency
     *
     * @param	string	$currency
     * @return	bool
     */
    public function canUseForCurrency($currency) {
        return Mage::getModel('payson/config')->IsCurrencySupported(Mage::app()->getStore()->getCurrentCurrencyCode());
    }

    /**
     * @inheritDoc
     */
    public function refund(Varien_Object $payment, $amount) {

        /* @var $order Mage_Sales_Model_Order */
        $order = $payment->getOrder();

        $method = $payment->getMethod();

        if ($order->getBaseGrandTotal() != $amount) {
            Mage::throwException('Invalid amount');
        }

        $helper = Mage::helper('payson');
        $order_id = $order->getData('increment_id');
        $api = Mage::helper('payson/api');
        if ($method == "payson_standard") {
            $paysonPaymethod = $method;
        }if ($method == "payson_invoice") {
            $paysonPaymethod = $method;
        }
        $api->PaymentUpdate($order_id, $paysonPaymethod ?
                        Payson_Payson_Helper_Api::UPDATE_ACTION_CREDITORDER :
                        Payson_Payson_Helper_Api::UPDATE_ACTION_REFUNDORDER);

        $order->addStatusHistoryComment($helper->__(
                        'Payment was credited at Payson'));

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function void(Varien_Object $payment) {
        $payment->setTransactionId('auth')
                ->setIsTransactionClosed(0);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(Varien_Object $payment) {
        $order = $payment->getOrder();
        $order_id = $order->getData('increment_id');

        $api = Mage::helper('payson/api');
        $helper = Mage::helper('payson');
        $api->PaymentDetails($order_id);
        $details = $api->GetResponse();



        if (($details->type === Payson_Payson_Helper_Api::PAYMENT_METHOD_INVOICE) ||
                ($details->invoiceStatus === Payson_Payson_Helper_Api::INVOICE_STATUS_ORDERCREATED) ||
                ($details->type !== Payson_Payson_Helper_Api::PAYMENT_METHOD_INVOICE && $details->status === Payson_Payson_Helper_Api::STATUS_CREATED) ||
                $order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING) {
            $api->PaymentUpdate($order_id, Payson_Payson_Helper_Api::UPDATE_ACTION_CANCELORDER);

            $order->addStatusHistoryComment($helper->__(
                            'Order was canceled at Payson'));

            $payment->setTransactionId('auth')
                    ->setIsTransactionClosed(1);
        } else {
            Mage::throwException($helper->__('Payson is not ready to cancel the order. Please try again later.'));
        }
        return $this;
    }

}
