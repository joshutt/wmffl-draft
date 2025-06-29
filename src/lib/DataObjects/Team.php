<?php
/**
 * Table Definition for team
 */
require_once 'DB/DataObject.php';

class DataObjects_Team extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'team';                // table name
    public $TeamID;                          // int(11)  not_null primary_key multiple_key auto_increment
    public $DivisionID;                      // int(11)  not_null
    public $Name;                            // string(50)  not_null unique_key
    public $member;                          // int(11)  
    public $statid;                          // int(4)  
    public $logo;                            // string(50)  
    public $fulllogo;                        // int(4)  not_null
    public $motto;                           // string(255)  
    public $abbrev;                          // string(3)  unique_key
    public $active;                          // int(4)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
