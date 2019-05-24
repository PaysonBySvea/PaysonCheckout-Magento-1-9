<?php
require_once(Mage::getBaseDir('lib') . '/Payson/Checkout2/PaysonCheckout2PHP/lib/paysonapi.php');

class Payson_Checkout2_Block_Form_Checkout2 extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payson/checkout2/form/checkout2.phtml');
    }
}