<?php

class FundingConstraint {

    const NONE = 0;
    const CREDITCARD = 1;
    const BANK = 2;
    const INVOICE = 3;
    const SMS = 4;

    public static function addConstraintsToOutput($fundingConstraints, &$output) {
        $formatString = "fundingList.fundingConstraint(%d).constraint";

        $i = 0;
        foreach ($fundingConstraints as $constraint) {
            if ($constraint != self::NONE) {
                $output[sprintf($formatString, $i)] = self::ConstantToString($constraint);
                $i++;
            }
        }
    }

    public static function ConstantToString($value) {
        switch ($value) {
            case self::BANK:
                return "BANK";
            case self::CREDITCARD:
                return "CREDITCARD";
            case self::INVOICE:
                return "INVOICE";
            case self::SMS:
                return "SMS";    
        }
    }  

}