<?php
require_once 'Chiara/PEAR/Server.php';
require_once 'Chiara/PEAR/Server/Backend/DBDataObject.php';
require_once 'Chiara/PEAR/Server/Frontend/Xmlrpc.php';
$backend = new Chiara_PEAR_Server_Backend_DBDataObject('test');
$frontend = Chiara_PEAR_Server_Frontend_Xmlrpc::singleton('test');
$server = new Chiara_PEAR_Server('/path/to/releases');
$server->setBackend($backend);
$server->setFrontend($frontend);
$server->run();
?>