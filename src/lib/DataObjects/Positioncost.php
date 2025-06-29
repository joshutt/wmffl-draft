<?php
/**
 * Table Definition for positioncost
 */
require_once 'DB/DataObject.php';

class DataObjects_Positioncost extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'positioncost';        // table name
    public $position;                        // string(2)  not_null primary_key
    public $years;                           // int(11)  not_null primary_key
    public $cost;                            // int(11)  
    public $startSeason;                     // year(4)  not_null primary_key unsigned zerofill
    public $endSeason;                       // year(4)  unsigned zerofill

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
