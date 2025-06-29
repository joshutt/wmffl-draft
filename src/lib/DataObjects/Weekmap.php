<?php
/**
 * Table Definition for weekmap
 */
require_once 'DB/DataObject.php';

class DataObjects_Weekmap extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'weekmap';             // table name
    public $Season;                          // year(4)  not_null primary_key unsigned zerofill
    public $Week;                            // int(11)  not_null primary_key
    public $StartDate;                       // datetime(19)  not_null binary
    public $EndDate;                         // datetime(19)  not_null binary
    public $ActivationDue;                   // datetime(19)  binary
    public $DisplayDate;                     // datetime(19)  not_null binary
    public $weekname;                        // string(22)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
