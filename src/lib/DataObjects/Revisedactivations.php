<?php
/**
 * Table Definition for revisedactivations
 */
require_once 'DB/DataObject.php';

class DataObjects_Revisedactivations extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'revisedactivations';    // table name
    public $season;                          // year(4)  not_null multiple_key unsigned zerofill
    public $week;                            // int(11)  not_null
    public $teamid;                          // int(11)  not_null
    public $pos;                             // string(2)  enum
    public $playerid;                        // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
