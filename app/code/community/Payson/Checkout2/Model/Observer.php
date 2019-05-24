<?php
class Payson_Checkout2_Model_Observer 
{
    public function checkoutCartSaveAfter(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('checkout2/order');
        $event = $observer->getEvent();
        $cart = $observer->getEvent()->getCart();

        if ($cart) { 
            $quote = $cart->getQuote();
        }

        $item = $event->getQuoteItem();

        if ($item) { 
            $quote = $item->getQuote();
        }

        if (!$quote) {
            return;
        }

        $checkoutId = $quote->getData('payson_checkout_id');

        if ($checkoutId) {
            // Will trigger an automatic update to express checkout
            $helper->updateCheckoutControlKey($checkoutId);
        }
    }
}