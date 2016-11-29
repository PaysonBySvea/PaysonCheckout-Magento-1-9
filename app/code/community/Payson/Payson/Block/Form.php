<?php

class Payson_Payson_Block_Form extends Mage_Payment_Block_Form {

    protected function _construct() {
        $this->setTemplate('Payson/Payson/form.phtml');
        parent::_construct();
    }

}

