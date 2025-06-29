<?php
/**
 * Table Definition for gameplan
 */
require_once 'DB/DataObject.php';

class DataObjects_Gameplan extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'gameplan';            // table name
    public $gameplan_id;                     // int(11)  not_null primary_key auto_increment
    public $season;                          // year(4)  not_null multiple_key unsigned zerofill
    public $week;                            // int(11)  not_null
    public $teamid;                          // int(11)  not_null
    public $playerid;                        // int(11)  not_null
    public $side;                            // string(4)  not_null enum

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
