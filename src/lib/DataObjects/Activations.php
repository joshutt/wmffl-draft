<?php
/**
 * Table Definition for activations
 */
require_once 'DB/DataObject.php';

class DataObjects_Activations extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'activations';         // table name
    public $TeamID;                          // int(11)  not_null primary_key multiple_key
    public $Season;                          // year(4)  not_null primary_key unsigned zerofill
    public $Week;                            // int(4)  not_null primary_key
    public $HC;                              // int(11)  not_null
    public $QB;                              // int(11)  not_null
    public $RB1;                             // int(11)  not_null
    public $RB2;                             // int(11)  not_null
    public $WR1;                             // int(11)  not_null
    public $WR2;                             // int(11)  not_null
    public $TE;                              // int(11)  not_null
    public $K;                               // int(11)  not_null
    public $OL;                              // int(11)  not_null
    public $DL1;                             // int(11)  not_null
    public $DL2;                             // int(11)  not_null
    public $LB1;                             // int(11)  not_null
    public $LB2;                             // int(11)  not_null
    public $DB1;                             // int(11)  not_null
    public $DB2;                             // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
