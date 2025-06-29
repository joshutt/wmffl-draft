<?php
/**
 * Table Definition for playerteams
 */
require_once 'DB/DataObject.php';

class DataObjects_Playerteams extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'playerteams';         // table name
    public $playerid;                        // int(11)  not_null multiple_key
    public $nflteam;                         // string(3)  not_null
    public $startdate;                       // date(10)  binary
    public $enddate;                         // date(10)  binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
