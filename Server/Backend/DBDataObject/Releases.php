<?php
/**
 * Table Definition for releases
 */
require_once 'DB/DataObject.php';

class Chiara_PEAR_Server_Backend_DBDataObject_Releases extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'releases';                        // table name
    public $id;                              // int(11)  not_null primary_key
    public $channel;                         // string(25)  not_null multiple_key
    public $package;                         // string(80)  not_null
    public $version;                         // string(20)  not_null
    public $state;                           // string(8)  not_null enum
    public $maintainer;                      // string(20)  not_null
    public $license;                         // string(20)  not_null
    public $summary;                         // blob(65535)  not_null blob
    public $description;                     // blob(65535)  not_null blob
    public $releasedate;                     // datetime(19)  not_null binary
    public $releasenotes;                    // blob(65535)  not_null blob
    public $filepath;                        // blob(65535)  not_null blob
    public $packagexml;                      // blob(16777215)  not_null blob
    public $deps;                            // blob(65535)  not_null blob

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Chiara_PEAR_Server_Backend_DBDataObject_Releases',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
?>