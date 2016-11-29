<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/order'), Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'Payson Checkout ID',
    'length' => '255'
));

$installer->getConnection()->addColumn($installer->getTable('sales/quote'), Payson_Checkout2_Model_Order::CHECKOUT_ID_COLUMN, array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'Payson Checkout ID',
    'length' => '255'
));

$installer->endSetup();