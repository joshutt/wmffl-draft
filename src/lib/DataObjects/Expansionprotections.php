<?php
/**
 * Table Definition for expansionprotections
 */
require_once 'DB/DataObject.php';

class DataObjects_Expansionprotections extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'expansionprotections';    // table name
    public $teamid;                          // int(11)  not_null primary_key
    public $playerid;                        // int(11)  not_null primary_key
    public $type;                            // string(9)  not_null enum
    public $protected;                       // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
