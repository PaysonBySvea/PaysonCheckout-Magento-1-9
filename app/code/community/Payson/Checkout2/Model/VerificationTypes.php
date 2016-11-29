<?php

class Payson_Checkout2_Model_VerificationTypes
{
    public function toOptionArray()
    {
        $helper = Mage::helper('checkout2');

        return array(
            array('value'=> 'none', 'label' => $helper->__('None')),
            array('value'=> 'bankid', 'label' => $helper->__('BankID')),
        );
    }
}