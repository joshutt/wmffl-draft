<?php
/**
 * Table Definition for playeroverride
 */
require_once 'DB/DataObject.php';

class DataObjects_Playeroverride extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'playeroverride';      // table name
    public $playerid;                        // int(11)  not_null primary_key
    public $season;                          // year(4)  not_null primary_key multiple_key unsigned zerofill
    public $teamid;                          // int(11)  not_null primary_key
    public $pos;                             // string(2)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
