<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Categories extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'categories';                      // table name
    public $id;                              // int(6)  not_null primary_key
    public $channel;                         // string(255)  not_null
    public $name;                            // string(255)  not_null
    public $description;                     // blob(65535)  not_null blob
    public $alias;                           // string(50)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Categories',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>