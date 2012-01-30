<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Packages extends DB_DataObject 
{

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'packages';                        // table name
    var $channel;                         // string(255)  not_null primary_key
    var $package;                         // string(80)  not_null primary_key
    var $category_id;                     // int(6)  not_null
    var $license;                         // string(20)  not_null
    var $licenseuri;                      // string(20)  not_null
    var $summary;                         // blob(65535)  not_null blob
    var $description;                     // blob(65535)  not_null blob
    var $parent;                          // string(80)  null
    var $deprecated_channel;              // string(255)  not_null
    var $deprecated_package;              // string(80)  not_null

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Packages',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function hasReleases()
    {
        $rel = &DB_DataObject::factory('releases');
        $rel->channel = $this->channel;
        $rel->package = $this->package;
        return $rel->find();
    }
}
?>