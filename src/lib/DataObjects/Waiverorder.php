<?php
/**
 * Table Definition for waiverorder
 */
require_once 'DB/DataObject.php';

class DataObjects_Waiverorder extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'waiverorder';         // table name
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $week;                            // int(11)  not_null primary_key
    public $ordernumber;                     // int(11)  not_null primary_key
    public $teamid;                          // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
