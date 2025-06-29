<?php
/**
 * Table Definition for draftpicks
 */
require_once 'DB/DataObject.php';

class DataObjects_Draftpicks extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'draftpicks';          // table name
    public $Season;                          // year(4)  not_null multiple_key unsigned zerofill
    public $Round;                           // int(4)  not_null
    public $Pick;                            // int(4)  
    public $teamid;                          // int(11)  
    public $orgTeam;                         // int(11)  
    public $playerid;                        // int(11)  
    public $pickTime;                        // timestamp(19)  not_null binary timestamp

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
