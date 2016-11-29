<?php

$this->startSetup();
$this->run('alter table `' . $this->getTable('payson_order') . '` add store_id int;
');

$this->endSetup();

