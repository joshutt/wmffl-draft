<?php
/**
 * Table Definition for players
 */
require_once 'DB/DataObject.php';

class DataObjects_Players extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'players';             // table name
    public $PlayerID;                        // int(11)  not_null primary_key auto_increment
    public $LastName;                        // string(30)  not_null
    public $FirstName;                       // string(30)  not_null
    public $NFLTeam;                         // string(5)  
    public $Position;                        // string(2)  enum
    public $Status;                          // string(1)  
    public $StatID;                          // string(5)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
