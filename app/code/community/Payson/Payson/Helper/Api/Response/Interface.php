<?php

interface Payson_Payson_Helper_Api_Response_Interface {

    /**
     * Construct a response object from a string
     * 
     * @param	string	$body
     * @return	object
     */
    public static function FromHttpBody($body);

    /**
     * Whether this response is valid
     * 
     * @return	bool
     */
    public function IsValid();
}

