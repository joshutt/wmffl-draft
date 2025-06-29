<?php
/**
 * Table Definition for expansionpicks
 */
require_once 'DB/DataObject.php';

class DataObjects_Expansionpicks extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'expansionpicks';      // table name
    public $playerid;                        // int(11)  not_null
    public $teamid;                          // int(11)  not_null
    public $round;                           // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
