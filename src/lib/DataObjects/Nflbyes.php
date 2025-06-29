<?php
/**
 * Table Definition for nflbyes
 */
require_once 'DB/DataObject.php';

class DataObjects_Nflbyes extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nflbyes';             // table name
    public $season;                          // year(4)  not_null unsigned zerofill
    public $week;                            // int(4)  not_null
    public $nflteam;                         // string(3)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
