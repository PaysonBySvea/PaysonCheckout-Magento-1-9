<?php

class Payson_Payson_Helper_Api_Response_Validate implements Payson_Payson_Helper_Api_Response_Interface {
    /*
     * Constants
     */

    const VERIFIED = 'VERIFIED';
    const INVALID = 'INVALID';

    /*
     * Private properties
     */

    private $data;

    /*
     * Public methods
     */

    /**
     * @inheritDoc
     */
    static public function FromHttpBody($data) {
        return new self($data);
    }

    /**
     * Constructor!
     * 
     * @param	string	$data
     * @return	void
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function IsValid() {
        return ($this->data === self::VERIFIED);
    }

}

