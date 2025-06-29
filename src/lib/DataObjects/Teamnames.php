<?php
/**
 * Table Definition for teamnames
 */
require_once 'DB/DataObject.php';

class DataObjects_Teamnames extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'teamnames';           // table name
    public $teamid;                          // int(11)  not_null primary_key
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $name;                            // string(50)  not_null
    public $abbrev;                          // string(3)  not_null
    public $divisionId;                      // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
