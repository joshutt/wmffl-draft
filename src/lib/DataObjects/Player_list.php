<?php
/**
 * Table Definition for player_list
 */
require_once 'DB/DataObject.php';

class DataObjects_Player_list extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'player_list';         // table name
    public $playerid;                        // int(11)  not_null primary_key auto_increment
    public $name;                            // string(51)  
    public $pos;                             // string(2)  enum
    public $season;                          // int(11)  not_null primary_key
    public $week;                            // int(11)  not_null primary_key
    public $pts;                             // int(11)  
    public $teamid;                          // int(11)  multiple_key
    public $teamname;                        // string(50)  
    public $nflteamid;                       // string(3)  primary_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
