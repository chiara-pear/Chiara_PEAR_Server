<?php
/**
 * Table Definition for packages
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Packages extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'packages';                        // table name
    public $channel;                         // string(255)  not_null primary_key
    public $category_id;                     // int(6)  not_null
    public $package;                         // string(80)  not_null primary_key
    public $license;                         // string(20)  not_null
    public $licenseuri;                      // string(150)  not_null
    public $summary;                         // blob(65535)  not_null blob
    public $description;                     // blob(65535)  not_null blob
    public $parent;                          // string(80)  
    public $deprecated_package;              // string(80)  not_null
    public $deprecated_channel;              // string(255)  not_null

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