<?php
/**
 * Table Definition for roster
 */
require_once 'DB/DataObject.php';

class DataObjects_Roster extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'roster';              // table name
    public $PlayerID;                        // int(11)  not_null multiple_key
    public $TeamID;                          // int(11)  not_null multiple_key
    public $DateOn;                          // datetime(19)  not_null multiple_key binary
    public $DateOff;                         // datetime(19)  binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
