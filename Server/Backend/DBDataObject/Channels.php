<?php
/**
 * Table Definition for channels
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Channels extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'channels';                        // table name
    var $channel;                         // string(100)  not_null primary_key
    var $summary;                         // string(255)  not_null
    var $alias;                           // string(100)  not_null
    var $rest_support;                    // int(6)       not_null
    var $validatepackage;                 // string(255)  
    var $validatepackageversion;          // string(25)  

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Channels',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>