<?php

require_once(Mage::getBaseDir('lib') . '/Payson/Checkout2/PaysonCheckout2PHP/lib/paysonapi.php');

class Payson_Checkout2_Block_Payment extends Mage_Core_Block_Template
{
    /**
     * Order instance
     */
    protected $_orderHelper;

    public function _construct() {
        $this->_orderHelper = Mage::helper('checkout2/order');
    }

    public function standardCheckoutHtml()
    {
        $checkout = $this->_orderHelper->checkout();

        return $checkout->snippet;
    }

    public function expressCheckoutHtml()
    {
        $checkout = $this->_orderHelper->expressCheckout();
        $checkoutId = $checkout->id;
        $controlKey = $this->_orderHelper->getCheckoutControlKey($checkoutId);
        $domain = '.' . $_SERVER['SERVER_NAME'];

$js = <<<HTML
    <script type="text/javascript">
        PaysonSettings = window.PaysonSettings || {}
        PaysonSettings.checkoutId = "$checkoutId";
        PaysonSettings.controlKey = "$controlKey";
    </script>
HTML;

        return $checkout->snippet . $js;
    }

    public function confirmationHtml()
    {
        $checkoutId = Mage::getSingleton('core/session')->getCheckoutId();
        $checkout = $this->_orderHelper->getCheckout($checkoutId);

        return $checkout->snippet;
    }
}