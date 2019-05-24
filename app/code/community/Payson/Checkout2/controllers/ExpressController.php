<?php
class Payson_Checkout2_ExpressController extends Mage_Core_Controller_Front_Action
{
    private $_orderHelper;
    
    public function _construct() 
    {
        $this->_orderHelper = Mage::helper('checkout2/order');
    }

    public function newAction()
    {
    	if (!$this->_getOrderHelper()->hasActiveQuote()) {
            $this->_redirect('checkout/cart');

            return;
        }

        // Resets any previous checkouts
        $quote = $this->_getOrderHelper()->getQuote();
        $quote->setData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, null);
        $quote->save();

        $this->_redirect('checkout2/express/index');
    }

    public function indexAction()
    {
        if (!$this->_getOrderHelper()->hasActiveQuote()) {
            $this->_redirect('checkout/cart');

            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function cancelAction()
    {
        $cancelMessage = Mage::helper('checkout2')->__('Order was canceled at Payson.');
        Mage::getSingleton('core/session')->addError($cancelMessage);

        $this->_redirect('checkout2/express/index');
    }

    public function returnAction()
    {
        $quote = $this->_getOrderHelper()->getQuote();

        $checkoutId = $quote->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
        $api = $this->_getOrderHelper()->getApi();

        $checkout = $api->GetCheckout($checkoutId);

        switch ($checkout->status) {
            case 'readyToShip':
            	Mage::getSingleton('core/session')->setCheckoutId($checkoutId);

                $order = $this->_getOrderHelper()->convertQuoteToOrder($checkout->customer);
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);

                // Send order reference to Payson
                $checkout->merchant->reference = $order->getIncrementId();
                $api->UpdateCheckout($checkout);

                $successMessage = Mage::helper('checkout2')->__('The payment was successfully completed at Payson.');

                $order->sendNewOrderEmail()->save();

            	$this->_getOrderHelper()->removeCheckoutControlKey($checkoutId); 

                Mage::getSingleton('core/session')->addSuccess($successMessage);
                $this->_redirect('checkout2/payment/confirmation');

                break;

            case 'created':
            case 'processingPayment':
                $order = $this->_getOrderHelper()->convertQuoteToOrder($checkout->customer);
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true);

                // Send order reference to Payson
                $checkout->merchant->reference = $order->getIncrementId();
                $api->UpdateCheckout($checkout);

                $message = Mage::helper('checkout2')->__('Your payment is being processed by Payson.');

                Mage::getSingleton('core/session')->addError($message);
                $this->_redirect('checkout2/express/index');
                break;

            case 'denied':
                $errorMessage = Mage::helper('checkout2')->__('The payment was denied by Payson.');

                Mage::getSingleton('core/session')->addError($errorMessage);
                $quote->setIsActive(true)->save();
                $this->_redirect('checkout2/express/index');
                break;

            case 'expired':
                $errorMessage = Mage::helper('checkout2')->__('The payment was expired by Payson.');

                Mage::getSingleton('core/session')->addError($errorMessage);
                $quote->setIsActive(true)->save();
                $this->_redirect('checkout2/express/index');
                break;
            
            case 'canceled': {
                $cancelMessage = Mage::helper('checkout2')->__('Order was canceled at Payson.');
                $quote->setIsActive(true)->save();

                Mage::getSingleton('core/session')->addError($cancelMessage);
                $this->_redirect('checkout2/express/index');
                break;

            }
            default: {
                $message = Mage::helper('checkout2')->__('Something went wrong with the payment.');

                Mage::getSingleton('core/session')->addError($message);
                $this->_redirect('checkout2/express/index');
                break;
            }
        }
    }

    public function updateAction()
    {
    	if (!$this->getRequest()->isXmlHttpRequest()) {
    		return;
		}

        $orderHelper = Mage::helper('checkout2/order');
		$quote = $this->_getQuote();

		if (!$orderHelper->hasActiveQuote()) {
			$this->getResponse()->setBody('empty_cart');
			return;
		}

		// Update shipping address if address provided
        $updateAddress = (string) $this->getRequest()->getParam('setAddress');
        
        if ($updateAddress) {
        	$this->_updateShippingAddress($quote);
        }

        // Update shipping method if method provided
        $method = (string) $this->getRequest()->getParam('method');
        
        if ($method) {
        	$this->_updateShippingMethod($quote, $method);
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $quote->getPayment()->setMethod('checkout2');
        $quote->save();

        $orderHelper->updateExpressCheckout();

		$this->loadLayout();
        $this->renderLayout();
    }

    private function _updateShippingMethod($quote, $method) {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setShippingMethod($method);
    }

    private function _updateShippingAddress($quote) {
        $orderHelper = Mage::helper('checkout2/order');

		// Update shipping address
        $country    = (string) $this->getRequest()->getParam('CountryCode');
        $postcode   = (string) $this->getRequest()->getParam('PostalCode');
        $city       = (string) $this->getRequest()->getParam('City');
        $firstName  = (string) $this->getRequest()->getParam('FirstName');
        $lastName   = (string) $this->getRequest()->getParam('LastName');
        $street     = (string) $this->getRequest()->getParam('Street');

        $checkoutId = $quote->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);
        $checkout = $orderHelper->getCheckout($checkoutId);
        $paysonCustomer = $checkout->customer;

        $shippingAddress = $quote->getShippingAddress();

        $billingAddress = array(
            'firstname' => $firstName,
            'lastname' => $lastName,
            'street' => $street,
            'city' => $city,
            'region_id' => '',
            'postcode' => $postcode,
            'country_id' => $country,
            'use_for_shipping' => '1',
            'telephone' => $paysonCustomer->phone,
            'email' => $paysonCustomer->email
        );

        $quote->getBillingAddress()
            ->addData($billingAddress);

        $shippingAddress
            ->addData($billingAddress)
            ->setPaymentMethod('checkout2')
            ->setCollectShippingRates(true)
            ->save();

        // Collect and set default shipping rate
        $quote->collectTotals()->save();
        $rates = $shippingAddress->getAllShippingRates();

        if (is_null($shippingAddress->getShippingMethod()) || !$this->_isPaymentMethodAmongRates($shippingAddress->getShippingMethod(), $rates)) {

            if (count($rates) > 0) {
                $rate = $rates[0];
                $shippingAddress->setShippingMethod($rate->getCode())
                    ->setShippingAmount($rate->getPrice());
            }
        }
    }

    private function _isPaymentMethodAmongRates($code, $rates)
    {
        foreach ($rates as $index => $rate) {
            if ($rate->getCode() == $code) {
                return true;
            }
        }

        return false;
    }

    private function _getOrderHelper()
    {
        return $this->_orderHelper;
    }

    private function _getQuote()
    {
        return Mage::getSingleton('checkout/cart')->getQuote();
    }
}

