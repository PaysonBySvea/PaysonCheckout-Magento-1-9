<?php

class Payson_Checkout2_Model_Method_Checkout2 extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'checkout2';
    protected $_formBlockType = 'checkout2/form_checkout2';
    protected $_infoBlockType = 'checkout2/info_checkout2';

    protected $_isGateway = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = true;
    protected $_canCancelInvoice = true;
    protected $_canUseInternal = true; // true
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
    public function getTitle()
    {
        return Mage::helper('checkout2')->__('Payson');
    }

    /**
     * @inheritDoc
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('checkout2/payment/new');
    }

    /**
     * @inheritDoc
     */
    public function canUseForCurrency($currency)
    {
        return Mage::getModel('checkout2/config')->isCurrencySupported($currency);
    }

    /**
     * @inheritDoc
     */
    public function canUseCheckout()
    {
        $config = Mage::getModel('checkout2/config');

        return $config->getEnabled();
    }

    public function getConfigPaymentAction() {
        return self::ACTION_AUTHORIZE;
    }

    /**
     * @inheritDoc
     */
    public function authorize(Varien_Object $payment, $amount) {
        $orderHelper = Mage::helper('checkout2/order');
        $api = $orderHelper->getApi();

        $order = $payment->getOrder();
        $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
        $checkout = $api->GetCheckout($checkoutId);
        
        $payment->setTransactionId($checkout->purchaseId)->setIsTransactionClosed(0);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function capture(Varien_Object $payment, $amount) {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(Varien_Object $payment) {
        $order = $payment->getOrder();
        $orderHelper = Mage::helper('checkout2/order');
        $api = $orderHelper->getApi();
        $helper = Mage::helper('checkout2');

        if (($order->getPayment()->getMethod() == 'checkout2') && ($order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING || $order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)) {
            $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
            $checkout = $api->GetCheckout($checkoutId);
            $api->CancelCheckout($checkout);

            $order->addStatusHistoryComment($helper->__('Order was canceled at Payson'));
            $payment->setIsTransactionClosed(1);
        } else {
            Mage::throwException($helper->__('Payson is not ready to cancel the order. Please try again later.'));
        }
        return $this;
    }

	/**
     * @inheritDoc
     */
    public function void(Varien_Object $payment) {
        $this->cancel($payment);
    }

    /**
     * @inheritDoc
     */
    public function refund(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();
        $orderHelper = Mage::helper('checkout2/order');
        $api = $orderHelper->getApi();
        $helper = Mage::helper('checkout2');

        $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
        $checkout = $api->GetCheckout($checkoutId);

        $method = $payment->getMethod();
        $order_id = $order->getData('increment_id');

        $message = $helper->__('Payment was credited at Payson');

        if ($order->getBaseGrandTotal() != $amount || $checkout->payData->totalPriceIncludingTax != $amount) {
            Mage::throwException('Invalid amount');
        }

        if ($method == "checkout2") {

            if ($checkout->status == 'shipped') {
            	// Credits each order item
                foreach ($checkout->payData->items as $item) {
                	$item->creditedAmount = $item->unitPrice * $item->quantity;
                }

                $api->UpdateCheckout($checkout);
                $order->addStatusHistoryComment($message);
                Mage::getSingleton('core/session')->addSuccess($message);
            } else {
                $errorMessage = Mage::helper('payson')->__('Unable to refund order: %s. It must have status "shipped" but it´s current status is: "%s"', $order_id, $checkout->status);
                Mage::getSingleton('core/session')->addError($errorMessage);
            }
        }
        return $this;
    }
}