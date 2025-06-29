<?php
/**
 * Table Definition for nflteams
 */
require_once 'DB/DataObject.php';

class DataObjects_Nflteams extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nflteams';            // table name
    public $nflteam;                         // string(3)  not_null
    public $name;                            // string(25)  not_null multiple_key
    public $nickname;                        // string(20)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
