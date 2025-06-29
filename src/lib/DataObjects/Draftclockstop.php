<?php
/**
 * Table Definition for draftclockstop
 */
require_once 'DB/DataObject.php';

class DataObjects_Draftclockstop extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'draftclockstop';      // table name
    public $season;                          // year(4)  not_null multiple_key unsigned zerofill
    public $round;                           // int(11)  not_null
    public $pick;                            // int(11)  not_null
    public $timeStopped;                     // timestamp(19)  binary
    public $timeStarted;                     // timestamp(19)  binary

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
