<?php
/**
 * Table Definition for waiverpicks
 */
require_once 'DB/DataObject.php';

class DataObjects_Waiverpicks extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'waiverpicks';         // table name
    public $teamid;                          // int(11)  not_null primary_key
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $week;                            // int(11)  not_null primary_key
    public $playerid;                        // int(11)  not_null
    public $priority;                        // int(11)  not_null primary_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
