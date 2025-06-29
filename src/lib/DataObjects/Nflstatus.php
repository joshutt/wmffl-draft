<?php
/**
 * Table Definition for nflstatus
 */
require_once 'DB/DataObject.php';

class DataObjects_Nflstatus extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nflstatus';           // table name
    public $nflteam;                         // string(3)  not_null
    public $season;                          // int(11)  not_null
    public $week;                            // int(11)  not_null
    public $status;                          // string(1)  enum

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
