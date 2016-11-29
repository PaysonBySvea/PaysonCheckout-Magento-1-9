<?php

$this->startSetup();
$this->run('
CREATE TABLE IF NOT EXISTS `' . $this->getTable('payson_order') . '`
(
	`id` INT NOT NULL AUTO_INCREMENT,
	`order_id` INT NOT NULL,
	`added` DATETIME DEFAULT NULL,
	`updated` DATETIME DEFAULT NULL,
	`valid` TINYINT(1) NOT NULL,
	`ipn_status` VARCHAR(32) DEFAULT NULL,
	`token` VARCHAR(255) DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `' . $this->getTable('payson_order_log') . '`
(
	`id` INT NOT NULL AUTO_INCREMENT,
	`payson_order_id` INT DEFAULT NULL,
	`added` DATETIME DEFAULT NULL,
	`api_call` VARCHAR(32) NOT NULL,
	`valid` TINYINT(1) NOT NULL,
	`response` TEXT NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

$this->addAttribute('order', 'payson_invoice_fee', array('type' => 'decimal', 'grid' => false));
$this->addAttribute('order', 'base_payson_invoice_fee', array('type' => 'decimal', 'grid' => false));

$this->endSetup();

