<?php

class Payson_Checkout2_Model_Notification {
    public function process($checkoutId) {
        $orderHelper = Mage::helper('checkout2/order');
        $api = $orderHelper->getApi();
        $checkout = $api->GetCheckout($checkoutId);

        $order = Mage::getModel('sales/order')->loadByAttribute(Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, $checkoutId);

        if ($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE || $order->getState() == Mage_Sales_Model_Order::STATE_CANCELED) {
            return;
        }

        Mage::log('Order (' . $order->getIncrementId() . ') notified status update: ' . $checkout->status);

        switch ($checkout->status) {
            case 'readyToShip':
                $message = Mage::helper('checkout2')->__('Payson completed the order payment.');
                    
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $message);

                break;

            case 'shipped':
                $order->addStatusHistoryComment(Mage::helper('checkout2')->__('Order has been marked as shipped at Payson.'));

                // Create Qty array
                $shipmentItems = array();

                foreach ($order->getAllItems() as $item) {
                    $shipmentItems [$item->getId()] = $item->getQtyToShip();
                }

                // Prepare shipment and save
                if ($order->getId() && !empty($shipmentItems) && $order->canShip()) {
                    $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($shipmentItems);
                    $shipment->register();
                }

                // Crete invoice to complete order
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            	$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                $transactionSave->save();

                break;

            case 'paidToAccount':
                $order->addStatusHistoryComment(Mage::helper('checkout2')->__('Money have been paid to account by Payson.'));

                break;

            case 'credited':
                $service = Mage::getModel('sales/service_order', $order);

                foreach ($order->getInvoiceCollection() as $invoice) {
                    $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
                    $creditmemo->register();
                    $creditmemo->save();

                    $creditmemo->sendEmail();
                    $order->addStatusHistoryComment(Mage::helper('checkout2')->__('Notified customer about creditmemo #%s.', $creditmemo->getIncrementId()))
                        ->setIsCustomerNotified(true)
                        ->save();
                }

                break;

            case 'expired':
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                $order->addStatusHistoryComment(Mage::helper('checkout2')->__('The payment was expired by Payson.'));
                
                break;

            case 'canceled': {
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                $order->addStatusHistoryComment(Mage::helper('checkout2')->__('Order was canceled at Payson.'));

                break;
            }

            case 'denied': {
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                $order->addStatusHistoryComment(Mage::helper('checkout2')->__('The order was denied by Payson.'));

                break;
            }

            default: {
                break;
            }
        }

        $order->save();
    }
}