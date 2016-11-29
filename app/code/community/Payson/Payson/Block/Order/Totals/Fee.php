<?php

class Payson_Payson_Block_Order_Totals_Fee extends Mage_Core_Block_Abstract {

    public function initTotals() {
        $parent = $this->getParentBlock();
        $this->_order = $parent->getOrder();

        if ($this->_order->getPayment()->getMethod() === 'payson_invoice') {
            $parent->addTotalBefore(new Varien_Object(array
                (
                'code' => 'payson_invoice',
                'label' => Mage::helper('payson')->__('Invoice fee'),
                'value' => $this->_order->getPaysonInvoiceFee()
                    )), 'tax');
        }

        return $this;
    }

}

