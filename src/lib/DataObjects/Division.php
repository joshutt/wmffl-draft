<?php
/**
 * Table Definition for division
 */
require_once 'DB/DataObject.php';

class DataObjects_Division extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'division';            // table name
    public $DivisionID;                      // int(11)  not_null primary_key auto_increment
    public $Name;                            // string(30)  not_null unique_key
    public $startYear;                       // year(4)  not_null primary_key unsigned zerofill
    public $endYear;                         // year(4)  not_null primary_key unsigned zerofill

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
