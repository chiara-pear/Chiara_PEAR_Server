<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Package_extras extends DB_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'package_extras';                  // table name
    public $channel;                         // string(255)  not_null
    public $package;                         // string(80)
    public $cvs_uri;                         // string(255)  not_null
    public $bugs_uri;                        // string(255)  not_null
    public $docs_uri;                        // string(255)  not_null
    public $qa_approved;                     // int(1)
    public $unit_tested;                     // int(1)

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Package_extras',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>