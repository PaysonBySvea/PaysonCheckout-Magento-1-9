<?php

class Payson_Payson_Model_Method_Standard extends Payson_Payson_Model_Method_Abstract {
    /*
     * Protected properties
     */

    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canCancelInvoice = true;

    /**
     * @inheritDoc
     */
    protected $_code = 'payson_standard';
    protected $_formBlockType = 'payson/standard_form';

    /*
     * Public methods
     */

    /**
     * @inheritDoc
     */
    public function getTitle() {
        $this->_config = Mage::getModel('payson/config');
        $order = Mage::registry('current_order');
        if (!isset($order) && ($invoice = Mage::registry('current_invoice'))) {
                $order = $invoice->getOrder();
            }

            if (isset($order)) {
                $invoice_fee = $order->getPaysonInvoiceFee();

                if ($invoice_fee) {
                    $invoice_fee = $order->formatPrice($invoice_fee);
                }
            } else {
                $invoice_fee = Mage::getModel('payson/config')
                        ->GetInvoiceFeeInclTax($this->getQuote());

                if ($invoice_fee) {
                    $invoice_fee = Mage::app()->getStore()
                            ->formatPrice($invoice_fee);
                }
            }
            
        $invoice_fee = strip_tags($invoice_fee);
        if($this->_config->CanInvoicePayment()){
           return sprintf(Mage::helper('payson')->__('Checkout with Payson. If invoice is choosen as payment method an %s invoice fee will be added.'), ($invoice_fee));
        }else{
          return Mage::helper('payson')->__('Checkout with Payson');  
        }
        
    }

    /**
     * @inheritDoc
     */
    public function authorize(Varien_Object $payment, $amount) {
        $payment->setTransactionId('auth')->setIsTransactionClosed(0);

        return $this;
    }
   public function canUseCheckout() {
       $this->_config = Mage::getModel('payson/config');

        if ($this->_config->CanInvoicePayment()){
                return true;
            }
        if($this->_config->CanStandardPayment()){
                return true;
            }else{
                return false;
            }
            
        }

}
