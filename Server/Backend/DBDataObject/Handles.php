<?php
/**
 * Table Definition for maintainers
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Handles extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'handles';                     // table name
    var $handle;                          // string(20)  not_null primary_key
    var $name;                            // string(255)  not_null
    var $email;                           // string(255)  not_null
    var $password;                        // string(50)  not_null
    var $admin;                           // int(11)  not_null
    var $uri;
    
    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Maintainers',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>