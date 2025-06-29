<?php
/**
 * Table Definition for stats
 */
require_once 'DB/DataObject.php';

class DataObjects_Stats extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'stats';               // table name
    public $statid;                          // int(11)  not_null primary_key
    public $Season;                          // int(11)  not_null primary_key multiple_key
    public $week;                            // int(11)  not_null primary_key
    public $played;                          // int(4)  not_null
    public $yards;                           // int(11)  
    public $intthrow;                        // int(11)  
    public $rec;                             // int(11)  
    public $fum;                             // int(11)  
    public $tackles;                         // int(11)  
    public $sacks;                           // real(12)  
    public $intcatch;                        // int(11)  
    public $passdefend;                      // int(11)  
    public $returnyards;                     // int(11)  
    public $fumrec;                          // int(11)  
    public $forcefum;                        // int(11)  
    public $tds;                             // int(11)  
    public $specTD;                          // int(11)  
    public $Safety;                          // int(11)  not_null
    public $XP;                              // int(11)  not_null
    public $MissXP;                          // int(11)  not_null
    public $FG30;                            // int(11)  not_null
    public $FG40;                            // int(11)  not_null
    public $FG50;                            // int(11)  not_null
    public $FG60;                            // int(11)  not_null
    public $MissFG30;                        // int(11)  not_null
    public $ptdiff;                          // int(11)  
    public $blockpunt;                       // int(11)  not_null
    public $blockfg;                         // int(11)  not_null
    public $blockxp;                         // int(11)  not_null
    public $penalties;                       // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
