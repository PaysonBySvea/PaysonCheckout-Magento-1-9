<?php

class Payson_Payson_Helper_Api_Response_Standard_Parameters {
    /*
     * Protected properties
     */

    /**
     * Multi-dimensional array containing parameters from the response
     * 
     * @var	array
     */
    protected $params = array();

    /*
     * Public methods
     */

    /**
     * Constructor!
     * 
     * @param	arary	$params
     * @return	void
     */
    public function __construct(array $params) {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $this->params[$key] = new self($value);
            } else {
                $this->params[$key] = $value;
            }
        }
    }

    public function __get($name) {
        return (isset($this->params[$name]) ? $this->params[$name] : null);
    }

    public function __set($name, $value) {
        $this->params[$name] = $value;
    }

    public function __isset($name) {
        return isset($this->params[$name]);
    }

    /**
     * Compile the parameters into an array
     * 
     * @return	array
     */
    public function ToArray() {
        foreach ($this->params as $key => $value) {
            if ($value instanceof
                    Payson_Payson_Helper_Api_Response_Standard_Parameters) {
                $arr[$key] = $value->ToArray();
            } else {
                $arr[$key] = $value;
            }
        }

        return $arr;
    }

}

