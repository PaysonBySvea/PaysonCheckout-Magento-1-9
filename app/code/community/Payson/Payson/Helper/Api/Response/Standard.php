<?php

class Payson_Payson_Helper_Api_Response_Standard extends Payson_Payson_Helper_Api_Response_Standard_Parameters implements Payson_Payson_Helper_Api_Response_Interface {
    /*
     * Constants
     */

    const ACK_SUCCESS = 'SUCCESS';
    const ACK_FAILURE = 'FAILURE';

    /*
     * Public methods
     */

    /**
     * Parse response object and instantiate
     * 
     * @param	string	$response
     * @return	object
     */
    static public function FromHttpBody($body) {
        $params = array();
        parse_str($body, $params);

        foreach ($params as $key => $value) {
            $sub_key = strtok($key, '_');

            if ($sub_key === $key) {
                continue;
            }

            $current = &$params;

            do {
                $matches = array();

                if (preg_match('/\((\d)\)$/', $sub_key, $matches) === 1) {
                    $sub_key = substr($sub_key, 0, -3);

                    if (!strlen($sub_key)) {
                        continue;
                    }
                }

                if (!isset($current[$sub_key]) || !is_array($current[$sub_key])) {
                    $current[$sub_key] = array();
                }

                $current = &$current[$sub_key];

                if (isset($matches[1])) {
                    if (!isset($current[$matches[1]]) ||
                            !is_array($current[$matches[1]])) {
                        $current[$matches[1]] = array();
                    }

                    $current = &$current[$matches[1]];
                }
            } while (($sub_key = strtok('_')) !== false);

            $current = $value;

            unset($params[$key]);
        }

        return new self($params);
    }

    /**
     * Populate parameters
     * 
     * @param	array	$params
     * @return	void
     */
    public function __construct(array $params) {
        if (empty($params)) {
            Mage::throwException('Invalid response');
        }

        parent::__construct($params);
    }

    /**
     * @inheritDoc
     */
    public function IsValid() {
        return (isset($this->responseEnvelope->ack) &&
                ($this->responseEnvelope->ack === self::ACK_SUCCESS));
    }

    /**
     * Compile all errors into a string
     * 
     * @return	string
     */
    public function GetError() {
        $ret = '';

        if (isset($this->errorList->error)) {
            foreach ($this->errorList->error->ToArray() as $error) {
                if (isset($error['parameter'])) {
                    $ret .= $error['parameter'] . ' ';
                }

                $ret .= '(' . $error['errorId'] . ') ' .
                        $error['message'] . ' ';
            }
        }

        return rtrim($ret);
    }

    /**
     * Get first error id
     * 
     * @return	int|null
     */
    public function GetErrorId() {
        if (isset($this->errorList->error)) {
            foreach ($this->errorList->error->ToArray() as $error) {
                if (isset($error['errorId'])) {
                    return (int) $error['errorId'];
                }
            }
        }

        return null;
    }

}

