<?php

/*
require_once 'Chiara_PEAR_Server_Database.php';

$answers = array();
$answers['dbhost'] = 'localhost';
$answers['database'] = 'pear';
$answers['user'] = 'pear';
$answers['password'] = 'pear';
$answers['name'] = 'pear.saltybeagle.com';
$answers['handle'] = 'saltybeagle';
$answers['dbtype'] = 'mysqli';

$pi = new Chiara_PEAR_Server_Database_postinstall();
$pi->createDatabase($answers);
*/
require_once 'MDB2/Schema.php';
ini_set('display_errors',true);
$s = new MDB2_Schema();
$dsn = 'mysql://pear:pear@localhost/pear';
$s->connect($dsn);

$db = $s->parseDatabaseDefinitionFile('database.xml');
echo '<pre>';
print_r($s->createDatabase($db));

?>