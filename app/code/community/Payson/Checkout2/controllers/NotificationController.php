<?php

class Payson_Checkout2_NotificationController extends Mage_Core_Controller_Front_Action {

    public function notifyAction() {
        $request = $this->getRequest();
        $response = $this->getResponse();

        if (!$request->isPost()) {
            $response->setHttpResponseCode(503)->sendResponse();
            
            return;
        }

        try {
            $checkoutId = $request->getParam('checkout');
            Mage::getModel('checkout2/notification')->process($checkoutId);
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $response->setHttpResponseCode(503)->sendResponse();
            exit;
        } catch (Exception $e) {
            Mage::logException($e);
            $response->setHttpResponseCode(500);
        }
    }
}

