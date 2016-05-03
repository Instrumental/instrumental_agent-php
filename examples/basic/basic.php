<?php

require __DIR__ . '/vendor/autoload.php';

$I = new \Instrumental\Agent();
$I->setApiKey("YOUR_API_KEY");
$I->setEnabled(true);

$I->increment("php.examples.basic.increment");
