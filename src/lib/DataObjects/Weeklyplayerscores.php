<?php
/**
 * Table Definition for weeklyplayerscores
 */
require_once 'DB/DataObject.php';

class DataObjects_Weeklyplayerscores extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'weeklyplayerscores';    // table name
    public $playerid;                        // int(4)  not_null
    public $name;                            // int(4)  not_null
    public $pos;                             // int(4)  not_null
    public $nflteam;                         // int(4)  not_null
    public $teamid;                          // int(4)  not_null
    public $teamname;                        // int(4)  not_null
    public $season;                          // int(4)  not_null
    public $week;                            // int(4)  not_null
    public $pts;                             // int(4)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
