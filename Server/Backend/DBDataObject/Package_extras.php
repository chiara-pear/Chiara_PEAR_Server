<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Package_Extras extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'package_extras';                        // table name
    var $channel;                         // string(25)  not_null primary_key
    var $package;                         // string(80)  not_null primary_key
    var $cvs_uri;                          // string(80)  null
    var $bugs_uri;                          // string(80)  null
    var $docs_uri;                       // string(80)  null

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Package_Extras',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>