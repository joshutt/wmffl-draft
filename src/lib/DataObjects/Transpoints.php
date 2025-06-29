<?php
/**
 * Table Definition for transpoints
 */
require_once 'DB/DataObject.php';

class DataObjects_Transpoints extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'transpoints';         // table name
    public $TeamID;                          // int(11)  not_null primary_key
    public $season;                          // int(11)  not_null primary_key
    public $ProtectionPts;                   // int(11)  not_null
    public $TransPts;                        // int(11)  not_null
    public $TotalPts;                        // int(11)  not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
