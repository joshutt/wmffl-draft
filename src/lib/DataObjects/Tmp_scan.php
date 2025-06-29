<?php
/**
 * Table Definition for tmp_scan
 */
require_once 'DB/DataObject.php';

class DataObjects_Tmp_scan extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'tmp_scan';            // table name
    public $scanId;                          // int(11)  not_null primary_key auto_increment
    public $season;                          // int(11)  
    public $playerid;                        // int(11)  
    public $group;                           // int(11)  
    public $pos;                             // string(3)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
