<?php
/**
 * Table Definition for schedule
 */
require_once 'DB/DataObject.php';

class DataObjects_Schedule extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'schedule';            // table name
    public $gameid;                          // int(11)  not_null primary_key auto_increment
    public $Season;                          // year(4)  not_null multiple_key unsigned zerofill
    public $Week;                            // int(4)  not_null
    public $label;                           // string(255)  
    public $TeamA;                           // int(11)  not_null
    public $TeamB;                           // int(11)  not_null
    public $scorea;                          // int(11)  
    public $scoreb;                          // int(11)  
    public $overtime;                        // int(4)  not_null
    public $playoffs;                        // int(1)  not_null
    public $postseason;                      // int(1)  not_null
    public $championship;                    // int(1)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
