<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Categories extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'categories';                     // table name
    var $id;                                         // string(25)  not_null primary_key
    var $channel;                                    // string(25)  not_null primary_key
    var $name;                                       // string(80)  not_null primary_key
    var $description;                                // string(255)  not_null
    var $alias;                                      // string(255)  not_null blob

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Categories',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>