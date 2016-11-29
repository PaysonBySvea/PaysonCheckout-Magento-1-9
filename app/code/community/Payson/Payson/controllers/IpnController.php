<?php

class Payson_Payson_IpnController extends Mage_Core_Controller_Front_Action {

    public function notifyAction() {
        $request = $this->getRequest();
        $response = $this->getResponse();

        if (!$request->isPost()) {
            $response->setHttpResponseCode(503)->setBody('No!');

            return;
        }

        try {
            $api = Mage::helper('payson/api')->Validate($request->getRawBody(), $request->getHeader('Content-Type'));
        } catch (Exception $e) {
            $response->setHttpResponseCode(503)->setBody($e->getMessage());

            return;
        }
    }

}

