<?php
/**
 * Table Definition for expandAvailable
 */
require_once 'DB/DataObject.php';

class DataObjects_ExpandAvailable extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'expandAvailable';     // table name
    public $playerid;                        // int(4)  not_null
    public $teamid;                          // int(4)  not_null
    public $firstname;                       // int(4)  not_null
    public $lastname;                        // int(4)  not_null
    public $pos;                             // int(4)  not_null
    public $type;                            // int(4)  not_null
    public $cost;                            // int(4)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
