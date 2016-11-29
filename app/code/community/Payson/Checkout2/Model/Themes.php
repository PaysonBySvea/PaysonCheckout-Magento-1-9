<?php

class Payson_Checkout2_Model_Themes
{
    public function toOptionArray()
    {
        $helper = Mage::helper('checkout2');

        return array(
            array('value'=> 'White', 'label' => $helper->__('White')),
            array('value'=> 'Blue', 'label' => $helper->__('Blue')),
            array('value'=> 'Gray', 'label' => $helper->__('Gray')),
            array('value'=> 'WhiteTextLogos', 'label' => $helper->__('White with text logos')),
            array('value'=> 'GrayTextLogos', 'label' => $helper->__('Gray with text logos')),
            array('value'=> 'BlueTextLogos', 'label' => $helper->__('Blue with text logos')),
        );
    }
}