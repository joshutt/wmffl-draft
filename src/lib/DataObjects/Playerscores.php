<?php
/**
 * Table Definition for playerscores
 */
require_once 'DB/DataObject.php';

class DataObjects_Playerscores extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'playerscores';        // table name
    public $playerid;                        // int(11)  not_null primary_key
    public $season;                          // int(11)  not_null primary_key
    public $week;                            // int(11)  not_null primary_key
    public $pts;                             // int(11)  
    public $active;                          // int(11)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
