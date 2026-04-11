<?php

require_once __DIR__."/../vendor/autoload.php";

use irwinlopez1023\Magma4telegram\Magma;

try {
    Magma::autoDiscoverCommands(__DIR__ . '/Modules', 'Modules\\');
    Magma::autoDiscoverConversations(__DIR__ . '/Conversations', 'Conversations\\');
    Magma::autoDiscoverJobs(__DIR__ . '/Jobs', 'Jobs\\');

    $botToken = 'BOT_TOKEN';
    $magma = new Magma($botToken);

}catch (Exception $exception){
    echo $exception->getMessage();
}