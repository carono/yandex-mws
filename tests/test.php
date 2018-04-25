<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$mws = new \carono\yandex\Mws();
$mws->cert = 'lib/shop.cer';
$mws->private_key = 'lib/private.key';
$mws->shop_id = 175720;
$mws->currency = 10643;

$x = $mws->listOrders();

var_dump($x);


