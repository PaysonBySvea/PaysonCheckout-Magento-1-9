<?php
class Payson_Checkout2_PaymentController extends Mage_Core_Controller_Front_Action
{
    private $_orderHelper;
    
    public function _construct() {
        $this->_orderHelper = Mage::helper('checkout2/order');
    }

    public function newAction()
    {
    	if (!$this->_getOrderHelper()->hasOrder()) {
            $this->_redirect('checkout/cart');

            return;
        }

        // Resets any previous checkouts
        $order = $this->_getOrderHelper()->getOrder();
        $order->setData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, null);
        $order->save();

        $this->_redirect('checkout2/payment/index');
    }

    public function indexAction()
    {
        if (!$this->_getOrderHelper()->hasOrder()) {
            $this->_redirect('checkout/cart');

            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function confirmationAction()
    {
    	$checkoutId = Mage::getSingleton('core/session')->getCheckoutId();

        if (!$checkoutId) {
            $this->_redirect('checkout/cart');
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function cancelAction()
    {
        $cancelMessage = Mage::helper('checkout2')->__('Order was canceled at Payson.');
        Mage::getSingleton('core/session')->addError($cancelMessage);

        $this->_getOrderHelper()->cancelOrder($cancelMessage);

        $this->_redirect('checkout2/payment/index');
    }

    public function returnAction()
    {
        $order = $this->_getOrderHelper()->getOrder();
        $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
        $api = $this->_getOrderHelper()->getApi();

        $checkout = $api->GetCheckout($checkoutId);

        switch ($checkout->status) {
            case 'readyToShip':
            	Mage::getSingleton('core/session')->setCheckoutId($checkoutId);

                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);

                // Send order reference to Payson
                $checkout->merchant->reference = $order->getIncrementId();
                $api->UpdateCheckout($checkout);

                $successMessage = Mage::helper('checkout2')->__('The payment was successfully completed at Payson.');

                $order->sendNewOrderEmail()->save();

                Mage::getSingleton('core/session')->addSuccess($successMessage);
                $this->_redirect('checkout2/payment/confirmation');

                break;

            case 'created':
            case 'processingPayment':
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true);

                // Send order reference to Payson
                $checkout->merchant->reference = $order->getIncrementId();
                $api->UpdateCheckout($checkout);

                $message = Mage::helper('checkout2')->__('Your payment is being processed by Payson.');

                Mage::getSingleton('core/session')->addError($message);
                $this->_redirect('checkout2/payment/index');
                break;

            case 'denied':
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);

                $errorMessage = Mage::helper('checkout2')->__('The payment was denied by Payson.');

                Mage::getSingleton('core/session')->addError($errorMessage);
                $this->_getOrderHelper()->cancelOrder($errorMessage);
                $this->_redirect('checkout2/payment/index');
                break;

            case 'expired':
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);

                $errorMessage = Mage::helper('checkout2')->__('The payment was expired by Payson.');

                Mage::getSingleton('core/session')->addError($errorMessage);
                $this->_getOrderHelper()->cancelOrder($errorMessage);
                $this->_redirect('checkout2/payment/index');
                break;
            
            case 'canceled': {
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                $cancelMessage = Mage::helper('checkout2')->__('Order was canceled at Payson.');
                $this->_getOrderHelper()->cancelOrder($cancelMessage);

                Mage::getSingleton('core/session')->addError($cancelMessage);
                $this->_redirect('checkout2/payment/index');
                break;
            }
            default: {
                $message = Mage::helper('checkout2')->__('Something went wrong with the payment.');

                Mage::getSingleton('core/session')->addError($message);
                $this->_redirect('checkout2/payment/index');
                break;
            }
        }
    }

    private function _getOrderHelper()
    {
        return $this->_orderHelper;
    }
}