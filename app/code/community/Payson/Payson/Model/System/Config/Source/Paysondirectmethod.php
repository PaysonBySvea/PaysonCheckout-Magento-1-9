<?php

/**
 * My own options
 *
 */

class Payson_Payson_Model_System_Config_Source_Paysondirectmethod {

    /**
     * Options getter
     *
     * @return array
     * 
     */

    public function toOptionArray() {

        $this->_config = Mage::getModel('payson/config');
  

        $paysonInvoice = array(
            array('value' => 7, 'label' => Mage::helper('adminhtml')->__('')),
            array('value' => 8, 'label' => Mage::helper('adminhtml')->__('INVOICE')),
            array('value' => 9, 'label' => Mage::helper('adminhtml')->__('INVOICE / SMS')),
            array('value' => 10, 'label' => Mage::helper('adminhtml')->__('INVOICE / BANK')),
            array('value' => 11, 'label' => Mage::helper('adminhtml')->__('INVOICE / CREDITCARD')),
            array('value' => 12, 'label' => Mage::helper('adminhtml')->__('INVOICE / BANK / SMS')),
            array('value' => 13, 'label' => Mage::helper('adminhtml')->__('INVOICE / CREDITCARD / SMS')),
            array('value' => 14, 'label' => Mage::helper('adminhtml')->__('INVOICE / CREDITCARD / BANK')),
            array('value' => 15, 'label' => Mage::helper('adminhtml')->__('INVOICE / CREDITCARD / BANK / SMS'))
            
        );
        $paysonDirect = array(
            array('value' => -1, 'label' => Mage::helper('adminhtml')->__('')),
            array('value' => 0, 'label' => Mage::helper('adminhtml')->__('SMS')),
            array('value' => 1, 'label' => Mage::helper('adminhtml')->__('BANK')),
            array('value' => 2, 'label' => Mage::helper('adminhtml')->__('CREDITCARD')),
            array('value' => 3, 'label' => Mage::helper('adminhtml')->__('BANK / SMS')),
            array('value' => 4, 'label' => Mage::helper('adminhtml')->__('CREDITCARD / SMS')),
            array('value' => 5, 'label' => Mage::helper('adminhtml')->__('CREDITCARD / BANK')),
            array('value' => 6, 'label' => Mage::helper('adminhtml')->__('CREDITCARD / BANK / SMS'))
            
        );
        if ($this->_config->CanInvoicePayment()) {
            return $paysonInvoice;
        } elseif ($this->_config->CanStandardPayment()) {
            return $paysonDirect;
    }
    }

}
