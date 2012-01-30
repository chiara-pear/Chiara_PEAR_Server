<?php
/**
 * Table Definition for maintainers
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Maintainers extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'maintainers';                     // table name
    var $handle;                          // string(20)  not_null primary_key
    var $channel;                         // string(25)  not_null primary_key
    var $package;                         // string(80)  not_null primary_key
    var $role;                            // string(30)  not_null
    var $active;                          // int(4)  not_null

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Maintainers',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>