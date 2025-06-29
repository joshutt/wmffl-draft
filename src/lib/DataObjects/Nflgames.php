<?php
/**
 * Table Definition for nflgames
 */
require_once 'DB/DataObject.php';

class DataObjects_Nflgames extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nflgames';            // table name
    public $season;                          // year(4)  not_null primary_key unsigned zerofill
    public $week;                            // int(11)  not_null primary_key
    public $homeTeam;                        // string(3)  not_null primary_key
    public $roadTeam;                        // string(3)  not_null primary_key
    public $kickoff;                         // datetime(19)  not_null binary
    public $secRemain;                       // int(11)  not_null
    public $complete;                        // int(11)  not_null
    public $homeScore;                       // int(11)  
    public $roadScore;                       // int(11)  

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
