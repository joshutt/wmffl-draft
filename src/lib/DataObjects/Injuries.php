<?php
/**
 * Table Definition for injuries
 */
require_once 'DB/DataObject.php';

class DataObjects_Injuries extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'injuries';            // table name
    public $playerid;                        // int(11)  not_null primary_key multiple_key
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $week;                            // int(11)  not_null primary_key
    public $status;                          // string(1)  not_null enum
    public $details;                         // string(50)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
