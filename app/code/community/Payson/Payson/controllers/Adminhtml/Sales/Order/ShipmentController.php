<?php

require_once 'Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php';

class Payson_Payson_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController {

    public function saveAction() {

        $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));
        
        if (($order->getPayment()->getMethodInstance()->getCode() == "payson_standard")||($order->getPayment()->getMethodInstance()->getCode() == "payson_invoice")) {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);                              
                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                $transactionSave->save();            
        }
        parent::saveAction();
    }

}
