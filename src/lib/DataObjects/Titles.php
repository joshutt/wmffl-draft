<?php
/**
 * Table Definition for titles
 */
require_once 'DB/DataObject.php';

class DataObjects_Titles extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'titles';              // table name
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $type;                            // string(8)  not_null primary_key enum
    public $teamid;                          // int(11)  not_null primary_key

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
