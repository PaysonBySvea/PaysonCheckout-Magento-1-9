<?php

class Payson_Payson_Block_Invoice_Form extends Mage_Payment_Block_Form {

    protected function _construct() {
        $this->setTemplate('Payson/Payson/standard_form.phtml');
        parent::_construct();
    }

}

