<?php

class Payson_Payson_Model_Quote_Address_Total_Invoice extends Mage_Sales_Model_Quote_Address_Total_Abstract {

    protected $_code = 'payson_invoice';

    public function collect(Mage_Sales_Model_Quote_Address $address) {
        if ($address->getAddressType() !== 'shipping') {
            return $this;
        }
        $this->_config = Mage::getModel('payson/config');
        $address->setBasePaysonInvoiceFee(0);
        $address->setPaysonInvoiceFee(0);
        $quote = $address->getQuote();
        
        $method = $address->getQuote()->getPayment()->getMethod();
        if (is_null($quote->getId())) {
            return $this;
        }
        if (!$this->_config->CanInvoicePayment()) {
            return $this;
        }   
        if (($method !== 'payson_standard')||($method == "payson_invoice")) {
            return $this;
        }


        $store = $quote->getStore();
        $config = Mage::getModel('payson/config');

        $fee = $config->GetInvoiceFeeInclTax($quote);

        $base_grand_total = $address->getBaseGrandTotal();

        $address->setBasePaysonInvoiceFee($fee);
        $address->setPaysonInvoiceFee($store->convertPrice($fee, false));

        $address->setBaseGrandTotal($base_grand_total);
        $address->setGrandTotal($store->convertPrice($base_grand_total, false));

        return $this;
    }

}
