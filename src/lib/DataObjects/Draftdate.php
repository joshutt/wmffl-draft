<?php
/**
 * Table Definition for draftdate
 */
require_once 'DB/DataObject.php';

class DataObjects_Draftdate extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'draftdate';           // table name
    public $UserID;                          // int(11)  not_null primary_key
    public $Date;                            // date(10)  not_null primary_key binary
    public $Attend;                          // string(1)  not_null enum

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
