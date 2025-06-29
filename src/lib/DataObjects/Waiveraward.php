<?php
/**
 * Table Definition for waiveraward
 */
require_once 'DB/DataObject.php';

class DataObjects_Waiveraward extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'waiveraward';         // table name
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $week;                            // int(4)  not_null primary_key
    public $pick;                            // int(4)  not_null primary_key
    public $teamid;                          // int(4)  not_null
    public $playerid;                        // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
