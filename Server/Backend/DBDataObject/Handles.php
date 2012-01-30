<?php
/**
 * Table Definition for maintainer handles
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Handles extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'handles';                         // table name
    public $channel;                         // string(100)  not_null primary_key
    public $handle;                          // string(20)  not_null primary_key
    public $name;                            // string(255)  not_null
    public $email;                           // string(255)  not_null
    public $uri;                             // string(255)  not_null
    public $password;                        // string(50)  not_null
    public $admin;                           // int(11)  not_null

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Handles',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>