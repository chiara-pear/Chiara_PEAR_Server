<?php
/**
 * Table Definition for channels
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Channels extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'channels';                        // table name
    public $channel;                         // string(100)  not_null primary_key
    public $summary;                         // string(255)  not_null
    public $alias;                           // string(100)  not_null
    public $rest_support;                    // int(6)  not_null
    public $validatepackage;                 // string(255)  
    public $validatepackageversion;          // string(25)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Channels',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>