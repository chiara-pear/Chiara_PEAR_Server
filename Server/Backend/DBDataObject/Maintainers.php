<?php
/**
 * Table Definition for maintainers
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Maintainers extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'maintainers';                     // table name
    public $handle;                          // string(20)  not_null primary_key
    public $channel;                         // string(25)  not_null primary_key
    public $package;                         // string(80)  not_null primary_key
    public $role;                            // string(30)  not_null
    public $active;                          // int(4)  not_null

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Maintainers',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>