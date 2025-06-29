<?php
/**
 * Table Definition for protectionallocation
 */
require_once 'DB/DataObject.php';

class DataObjects_Protectionallocation extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'protectionallocation';    // table name
    public $ProtectionID;                    // int(11)  not_null primary_key unique_key auto_increment
    public $TeamID;                          // int(11)  not_null
    public $Season;                          // year(4)  not_null unsigned zerofill
    public $Special;                         // int(4)  
    public $HC;                              // int(4)  not_null
    public $QB;                              // int(4)  not_null
    public $RB;                              // int(4)  not_null
    public $WR;                              // int(4)  not_null
    public $TE;                              // int(4)  not_null
    public $K;                               // int(4)  not_null
    public $OL;                              // int(4)  not_null
    public $DL;                              // int(4)  not_null
    public $LB;                              // int(4)  not_null
    public $DB;                              // int(4)  not_null
    public $General;                         // int(4)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
