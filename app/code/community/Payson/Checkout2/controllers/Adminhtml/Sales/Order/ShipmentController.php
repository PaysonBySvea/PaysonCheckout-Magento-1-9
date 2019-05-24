<?php
require_once 'Mage/Adminhtml/controllers/Sales/Order/ShipmentController.php';

class Payson_Checkout2_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController
{

    public function saveAction()
    {

        $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));

        if (($order->getPayment()->getMethodInstance()->getCode() == "payson_checkout2")) {

            $orderId = $order->getIncrementId();
            $orderHelper = Mage::helper('checkout2/order');
            $api = $orderHelper->getApi();

            $checkoutId = $order->getData(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN);

            if ($checkoutId == '') {
                $this->handleNoCheckoutId($orderId);
                return $this;
            }

            $checkout = $api->GetCheckout($checkoutId);

            if ($checkout->status === 'readyToShip') {
                // Set order as shipped at Payson
                $api->ShipCheckout($checkout);

                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            	$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                $transactionSave->save();
            } else {
                $this->handleWrongOrderStatus($orderId, $checkout->status);

                return $this;
            }
        }

        parent::saveAction();
    }

    private function handleWrongOrderStatus($orderId, $currentStatus)
    {
        $errorMessage = Mage::helper('checkout2')->__('Unable to ship order: %s. It must have status "readyToShip" but it´s current status is: "%s".', $orderId, $currentStatus);
        Mage::getSingleton('core/session')->addError($errorMessage);
        $this->_redirectReferer();
    }

    private function handleNoCheckoutId($orderId)
    {
        $errorMessage = Mage::helper('checkout2')->__('Unable to ship order: %s. No Payson checkout ID was found.', $orderId);
        Mage::getSingleton('core/session')->addError($errorMessage);
        $this->_redirectReferer();
    }
}